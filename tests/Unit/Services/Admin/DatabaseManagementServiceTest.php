<?php

namespace Tests\Unit\Services\Admin;

use App\Services\Admin\DatabaseManagementService;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Tests\TestCase;

class DatabaseManagementServiceTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/db-mgmt-' . bin2hex(random_bytes(4));
        mkdir($this->tempDir);

        Config::set('database.connections.pgsql', [
            'host' => '127.0.0.1',
            'port' => '5432',
            'username' => 'dbuser',
            'password' => 'secret',
            'database' => 'media_intelligent',
        ]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            foreach (glob($this->tempDir . '/*') as $file) {
                @unlink($file);
            }

            @rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    public function test_restore_command_includes_psql_atomic_flags(): void
    {
        $service = new class extends DatabaseManagementService {
            public array $commands = [];
            public ?string $restoreScriptContents = null;
            public ?string $restoreScriptPath = null;

            protected function createTempFile(string $prefix): string
            {
                return tempnam(sys_get_temp_dir(), $prefix);
            }

            protected function writeRestoreScript(string $restoreScript, string $sqlFilePath): void
            {
                $this->restoreScriptPath = $restoreScript;
                parent::writeRestoreScript($restoreScript, $sqlFilePath);
                $this->restoreScriptContents = file_get_contents($restoreScript);
            }

            protected function runCommand(string $command, array $env = []): array
            {
                $this->commands[] = $command;

                return [
                    'stdout' => '',
                    'stderr' => '',
                    'exit_code' => 0,
                ];
            }
        };

        $upload = $this->tempDir . '/restore.sql';
        file_put_contents($upload, "-- PostgreSQL database dump\nCREATE TABLE demo(id int);\n");

        $service->restoreBackup($upload);

        $this->assertCount(1, $service->commands);
        $this->assertStringContainsString('psql -X', $service->commands[0]);
        $this->assertStringContainsString('--single-transaction', $service->commands[0]);
        $this->assertStringContainsString('ON_ERROR_STOP=1', $service->commands[0]);
        $this->assertStringContainsString('-f', $service->commands[0]);
        $this->assertStringContainsString('DROP SCHEMA IF EXISTS public CASCADE;', $service->restoreScriptContents);
        $this->assertStringContainsString('CREATE SCHEMA public AUTHORIZATION CURRENT_USER;', $service->restoreScriptContents);
        $this->assertStringContainsString('SET search_path TO public, pg_catalog;', $service->restoreScriptContents);
        $this->assertStringContainsString('CREATE TABLE demo(id int);', $service->restoreScriptContents);
        $this->assertFileDoesNotExist($service->restoreScriptPath);
        $this->assertFileExists($upload);
    }

    public function test_restore_failure_cleans_temp_script_and_only_runs_once(): void
    {
        $service = new class extends DatabaseManagementService {
            public array $commands = [];
            public ?string $restoreScriptPath = null;

            protected function createTempFile(string $prefix): string
            {
                return tempnam(sys_get_temp_dir(), $prefix);
            }

            protected function writeRestoreScript(string $restoreScript, string $sqlFilePath): void
            {
                $this->restoreScriptPath = $restoreScript;
                parent::writeRestoreScript($restoreScript, $sqlFilePath);
            }

            protected function runCommand(string $command, array $env = []): array
            {
                $this->commands[] = $command;

                return [
                    'stdout' => '',
                    'stderr' => 'psql failed',
                    'exit_code' => 1,
                ];
            }
        };

        $upload = $this->tempDir . '/restore.sql';
        file_put_contents($upload, "-- PostgreSQL database dump\nCREATE TABLE demo(id int);\n");

        try {
            $service->restoreBackup($upload);
            $this->fail('Expected RuntimeException not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('psql failed', $exception->getMessage());
        }

        $this->assertCount(1, $service->commands);
        $this->assertFileDoesNotExist($service->restoreScriptPath);
        $this->assertFileExists($upload);
        $this->assertStringNotContainsString('DROP SCHEMA', implode("\n", $service->commands));
    }

    public function test_restore_write_throw_cleans_temp_script_and_skips_process(): void
    {
        $service = new class extends DatabaseManagementService {
            public ?string $restoreScriptPath = null;
            public bool $runCommandCalled = false;

            protected function createTempFile(string $prefix): string
            {
                return tempnam(sys_get_temp_dir(), $prefix);
            }

            protected function writeRestoreScript(string $restoreScript, string $sqlFilePath): void
            {
                $this->restoreScriptPath = $restoreScript;
                throw new RuntimeException('gagal menyiapkan skrip');
            }

            protected function runCommand(string $command, array $env = []): array
            {
                $this->runCommandCalled = true;

                return [
                    'stdout' => '',
                    'stderr' => '',
                    'exit_code' => 0,
                ];
            }
        };

        $upload = $this->tempDir . '/restore.sql';
        file_put_contents($upload, "-- PostgreSQL database dump\nCREATE TABLE demo(id int);\n");

        try {
            $service->restoreBackup($upload);
            $this->fail('Expected RuntimeException not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('gagal menyiapkan skrip', $exception->getMessage());
        }

        $this->assertFalse($service->runCommandCalled);
        $this->assertFileDoesNotExist($service->restoreScriptPath);
        $this->assertFileExists($upload);
    }

    public function test_restore_process_throw_cleans_temp_script(): void
    {
        $service = new class extends DatabaseManagementService {
            public array $commands = [];
            public ?string $restoreScriptPath = null;

            protected function createTempFile(string $prefix): string
            {
                return tempnam(sys_get_temp_dir(), $prefix);
            }

            protected function writeRestoreScript(string $restoreScript, string $sqlFilePath): void
            {
                $this->restoreScriptPath = $restoreScript;
                parent::writeRestoreScript($restoreScript, $sqlFilePath);
            }

            protected function runCommand(string $command, array $env = []): array
            {
                $this->commands[] = $command;
                throw new RuntimeException('proc_open gagal');
            }
        };

        $upload = $this->tempDir . '/restore.sql';
        file_put_contents($upload, "-- PostgreSQL database dump\nCREATE TABLE demo(id int);\n");

        try {
            $service->restoreBackup($upload);
            $this->fail('Expected RuntimeException not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('proc_open gagal', $exception->getMessage());
        }

        $this->assertCount(1, $service->commands);
        $this->assertFileDoesNotExist($service->restoreScriptPath);
        $this->assertFileExists($upload);
    }

    public function test_restore_file_write_failure_is_detected(): void
    {
        $service = new class extends DatabaseManagementService {
            public ?string $restoreScriptPath = null;

            protected function createTempFile(string $prefix): string
            {
                return tempnam(sys_get_temp_dir(), $prefix);
            }

            protected function writeTempFileContents(string $path, string $contents): int|false
            {
                $this->restoreScriptPath = $path;

                return false;
            }
        };

        $upload = $this->tempDir . '/restore.sql';
        file_put_contents($upload, "-- PostgreSQL database dump\nCREATE TABLE demo(id int);\n");

        try {
            $service->restoreBackup($upload);
            $this->fail('Expected RuntimeException not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Gagal menyiapkan file pemulihan sementara.', $exception->getMessage());
        }

        $this->assertFileDoesNotExist($service->restoreScriptPath);
        $this->assertFileExists($upload);
    }

    public function test_export_process_throw_cleans_temp_file(): void
    {
        $service = new class extends DatabaseManagementService {
            public ?string $tempFile = null;

            protected function createTempFile(string $prefix): string
            {
                $this->tempFile = tempnam(sys_get_temp_dir(), $prefix);

                return $this->tempFile;
            }

            protected function runCommand(string $command, array $env = []): array
            {
                throw new RuntimeException('pg_dump runtime crash');
            }
        };

        try {
            $service->exportBackup();
            $this->fail('Expected RuntimeException not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('pg_dump runtime crash', $exception->getMessage());
        }

        $this->assertFileDoesNotExist($service->tempFile);
    }

    public function test_export_success_uses_single_temp_path_and_does_not_leave_orphan(): void
    {
        $createdTempFiles = [];

        $service = new class($createdTempFiles) extends DatabaseManagementService {
            public array $commands = [];
            public array $createdTempFiles;
            public ?string $exportTemp = null;

            public function __construct(array &$createdTempFiles)
            {
                $this->createdTempFiles = &$createdTempFiles;
            }

            protected function createTempFile(string $prefix): string
            {
                $path = tempnam(sys_get_temp_dir(), $prefix);
                $this->createdTempFiles[] = $path;
                $this->exportTemp = $path;

                return $path;
            }

            protected function runCommand(string $command, array $env = []): array
            {
                $this->commands[] = $command;

                file_put_contents($this->exportTemp, "-- PostgreSQL database dump\n");

                return [
                    'stdout' => '',
                    'stderr' => '',
                    'exit_code' => 0,
                ];
            }
        };

        $path = $service->exportBackup();

        $this->assertCount(1, $createdTempFiles);
        $this->assertSame($createdTempFiles[0], $path);
        $this->assertStringNotContainsString('.sql', basename($path));
        $this->assertFileExists($path);

        @unlink($path);
    }

    public function test_export_failure_cleans_temp_file(): void
    {
        $service = new class extends DatabaseManagementService {
            public ?string $tempFile = null;

            protected function createTempFile(string $prefix): string
            {
                $this->tempFile = tempnam(sys_get_temp_dir(), $prefix);

                return $this->tempFile;
            }

            protected function runCommand(string $command, array $env = []): array
            {
                return [
                    'stdout' => '',
                    'stderr' => 'backup failed',
                    'exit_code' => 1,
                ];
            }
        };

        try {
            $service->exportBackup();
            $this->fail('Expected RuntimeException not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('backup failed', $exception->getMessage());
        }

        $this->assertFileDoesNotExist($service->tempFile);
    }

    public function test_export_empty_result_cleans_temp_file(): void
    {
        $service = new class extends DatabaseManagementService {
            public ?string $tempFile = null;

            protected function createTempFile(string $prefix): string
            {
                $this->tempFile = tempnam(sys_get_temp_dir(), $prefix);

                return $this->tempFile;
            }

            protected function runCommand(string $command, array $env = []): array
            {
                file_put_contents($this->tempFile, '');

                return [
                    'stdout' => '',
                    'stderr' => '',
                    'exit_code' => 0,
                ];
            }
        };

        try {
            $service->exportBackup();
            $this->fail('Expected RuntimeException not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('File backup kosong atau tidak berhasil dibuat.', $exception->getMessage());
        }

        $this->assertFileDoesNotExist($service->tempFile);
    }
}
