<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DatabaseManagement extends Component
{
    use WithFileUploads;

    public $databaseFile;

    public function download()
    {
        $host = config('database.connections.pgsql.host');
        $port = config('database.connections.pgsql.port');
        $db = config('database.connections.pgsql.database');
        $user = config('database.connections.pgsql.username');
        $password = config('database.connections.pgsql.password');

        $tempFile = tempnam(sys_get_temp_dir(), 'backup_') . '.sql';

        $cmd = "pg_dump -h " . escapeshellarg($host) . " -p " . escapeshellarg($port) . " -U " . escapeshellarg($user) . " -F p -b -v -f " . escapeshellarg($tempFile) . " " . escapeshellarg($db);

        $descriptorspec = [
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $env = array_merge(getenv(), ['PGPASSWORD' => $password]);
        $process = proc_open($cmd, $descriptorspec, $pipes, null, $env);

        if (is_resource($process)) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $status = proc_close($process);

            if ($status !== 0) {
                Log::error('[Database] pg_dump failed: ' . $stderr);
                $this->dispatch('toast', message: 'Gagal mencadangkan database: ' . trim($stderr), type: 'danger');
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
                return;
            }
        } else {
            $this->dispatch('toast', message: 'Gagal menjalankan perintah pg_dump.', type: 'danger');
            return;
        }

        if (!file_exists($tempFile) || filesize($tempFile) === 0) {
            $this->dispatch('toast', message: 'File backup kosong atau tidak berhasil dibuat.', type: 'danger');
            return;
        }

        return response()->download($tempFile, 'backup_media_intelligent_' . now()->format('Y-m-d_H-i-s') . '.sql')->deleteFileAfterSend();
    }

    public function import()
    {
        $this->validate([
            'databaseFile' => 'required|file|max:51200', // max 50MB
        ], [
            'databaseFile.required' => 'Pilih file database SQL terlebih dahulu.',
            'databaseFile.file' => 'File tidak valid.',
            'databaseFile.max' => 'Ukuran file maksimal adalah 50MB.',
        ]);

        $host = config('database.connections.pgsql.host');
        $port = config('database.connections.pgsql.port');
        $db = config('database.connections.pgsql.database');
        $user = config('database.connections.pgsql.username');
        $password = config('database.connections.pgsql.password');

        $filePath = $this->databaseFile->getRealPath();

        if (!file_exists($filePath)) {
            $this->dispatch('toast', message: 'File unggahan tidak dapat diakses.', type: 'danger');
            return;
        }

        $descriptorspec = [
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $env = array_merge(getenv(), ['PGPASSWORD' => $password]);

        // Step 1: Clean/Drop existing tables
        $cmdClean = "psql -h " . escapeshellarg($host) . " -p " . escapeshellarg($port) . " -U " . escapeshellarg($user) . " -d " . escapeshellarg($db) . " -c 'DROP SCHEMA public CASCADE; CREATE SCHEMA public; GRANT ALL ON SCHEMA public TO public;'";
        $processClean = proc_open($cmdClean, $descriptorspec, $pipes, null, $env);

        if (is_resource($processClean)) {
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $status = proc_close($processClean);

            if ($status !== 0) {
                Log::error('[Database] Drop schema failed: ' . $stderr);
                $this->dispatch('toast', message: 'Gagal mengosongkan database lama: ' . trim($stderr), type: 'danger');
                return;
            }
        } else {
            $this->dispatch('toast', message: 'Gagal menjalankan perintah pengosongan database.', type: 'danger');
            return;
        }

        // Step 2: Import new SQL structure and data
        $cmdImport = "psql -h " . escapeshellarg($host) . " -p " . escapeshellarg($port) . " -U " . escapeshellarg($user) . " -d " . escapeshellarg($db) . " -f " . escapeshellarg($filePath);
        $processImport = proc_open($cmdImport, $descriptorspec, $pipes, null, $env);

        if (is_resource($processImport)) {
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $status = proc_close($processImport);

            if ($status !== 0) {
                Log::error('[Database] psql import failed: ' . $stderr);
                $this->dispatch('toast', message: 'Gagal mengimpor database baru: ' . trim($stderr), type: 'danger');
                return;
            }
        } else {
            $this->dispatch('toast', message: 'Gagal menjalankan perintah psql import.', type: 'danger');
            return;
        }

        $this->reset('databaseFile');
        $this->dispatch('toast', message: 'Database berhasil dipulihkan!', type: 'success');
    }

    public function render()
    {
        return view('livewire.admin.database-management');
    }
}
