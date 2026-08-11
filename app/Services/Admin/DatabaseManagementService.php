<?php

namespace App\Services\Admin;

use RuntimeException;

class DatabaseManagementService
{
    public function exportBackup(): string
    {
        $connection = config('database.connections.pgsql');
        $tempFile = $this->createTempFile('backup_');
        $keepTempFile = false;

        try {
            $command = $this->buildExportCommand($connection, $tempFile);

            $result = $this->runCommand($command, [
                'PGPASSWORD' => (string) $connection['password'],
            ]);

            if ($result['exit_code'] !== 0) {
                throw new RuntimeException(trim($result['stderr']) ?: 'Gagal menjalankan pg_dump.');
            }

            if (! file_exists($tempFile) || filesize($tempFile) === 0) {
                throw new RuntimeException('File backup kosong atau tidak berhasil dibuat.');
            }

            $keepTempFile = true;

            return $tempFile;
        } finally {
            if (! $keepTempFile) {
                $this->cleanupFile($tempFile);
            }
        }
    }

    public function restoreBackup(string $sqlFilePath): void
    {
        $connection = config('database.connections.pgsql');
        $restoreScript = $this->createTempFile('restore_');

        try {
            $this->writeRestoreScript($restoreScript, $sqlFilePath);
            $command = $this->buildRestoreCommand($connection, $restoreScript);
            $result = $this->runCommand($command, [
                'PGPASSWORD' => (string) $connection['password'],
            ]);

            if ($result['exit_code'] !== 0) {
                throw new RuntimeException(trim($result['stderr']) ?: 'Gagal menjalankan psql.');
            }
        } finally {
            $this->cleanupFile($restoreScript);
        }
    }

    public function hasPostgresDumpSignature(string $filePath): bool
    {
        $handle = @fopen($filePath, 'rb');

        if ($handle === false) {
            return false;
        }

        try {
            $chunk = fread($handle, 2048) ?: '';
        } finally {
            fclose($handle);
        }

        return str_contains($chunk, 'PostgreSQL database dump');
    }

    protected function buildExportCommand(array $connection, string $tempFile): string
    {
        return sprintf(
            'pg_dump -h %s -p %s -U %s -F p -b -v -f %s %s',
            escapeshellarg($connection['host']),
            escapeshellarg((string) $connection['port']),
            escapeshellarg($connection['username']),
            escapeshellarg($tempFile),
            escapeshellarg($connection['database'])
        );
    }

    protected function buildRestoreCommand(array $connection, string $restoreScript): string
    {
        return sprintf(
            'psql -X -h %s -p %s -U %s -d %s --single-transaction -v ON_ERROR_STOP=1 -f %s',
            escapeshellarg($connection['host']),
            escapeshellarg((string) $connection['port']),
            escapeshellarg($connection['username']),
            escapeshellarg($connection['database']),
            escapeshellarg($restoreScript)
        );
    }

    protected function writeRestoreScript(string $restoreScript, string $sqlFilePath): void
    {
        $header = implode(PHP_EOL, [
            'DROP SCHEMA IF EXISTS public CASCADE;',
            'CREATE SCHEMA public AUTHORIZATION CURRENT_USER;',
            'SET search_path TO public, pg_catalog;',
            '',
        ]);

        $sqlContents = file_get_contents($sqlFilePath);

        if ($sqlContents === false) {
            throw new RuntimeException('Gagal membaca file pemulihan sementara.');
        }

        $written = $this->writeTempFileContents($restoreScript, $header . $sqlContents);

        if ($written === false) {
            throw new RuntimeException('Gagal menyiapkan file pemulihan sementara.');
        }
    }

    protected function writeTempFileContents(string $path, string $contents): int|false
    {
        return file_put_contents($path, $contents);
    }

    protected function createTempFile(string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);

        if ($path === false) {
            throw new RuntimeException('Gagal membuat file sementara.');
        }

        return $path;
    }

    protected function runCommand(string $command, array $env = []): array
    {
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, null, array_merge(getenv(), $env));

        if (! is_resource($process)) {
            throw new RuntimeException('Gagal menjalankan proses database.');
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'stdout' => $stdout,
            'stderr' => $stderr,
            'exit_code' => $exitCode,
        ];
    }

    private function cleanupFile(?string $path): void
    {
        if ($path && file_exists($path)) {
            @unlink($path);
        }
    }
}
