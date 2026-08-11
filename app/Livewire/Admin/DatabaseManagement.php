<?php

namespace App\Livewire\Admin;

use App\Services\Admin\DatabaseManagementService;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

class DatabaseManagement extends Component
{
    use WithFileUploads;

    public $databaseFile;
    public string $restoreConfirmation = '';

    public function mount(): void
    {
        $this->ensureAdminAccess();
    }

    public function download()
    {
        $this->ensureAdminAccess();

        try {
            $tempFile = app(DatabaseManagementService::class)->exportBackup();
        } catch (RuntimeException $exception) {
            Log::error('[Database] pg_dump failed: ' . $this->redactSecrets($exception->getMessage()));
            $this->dispatch('toast', message: 'Gagal membuat cadangan database. Periksa log sistem.', type: 'danger');
            return;
        }

        return response()->download($tempFile, 'backup_media_intelligent_' . now()->format('Y-m-d_H-i-s') . '.sql')->deleteFileAfterSend();
    }

    public function import()
    {
        $this->ensureAdminAccess();

        $this->validate([
            'databaseFile' => [
                'required',
                'file',
                'max:51200',
                function ($attribute, $value, $fail) {
                    if (strtolower((string) $value->getClientOriginalExtension()) !== 'sql') {
                        $fail('File harus berformat .sql.');
                        return;
                    }

                    $realPath = $value->getRealPath();

                    if (! $realPath || ! is_readable($realPath)) {
                        $fail('File unggahan tidak dapat dibaca.');
                        return;
                    }

                    if (filesize($realPath) <= 0) {
                        $fail('File SQL tidak boleh kosong.');
                        return;
                    }

                    if (! app(DatabaseManagementService::class)->hasPostgresDumpSignature($realPath)) {
                        $fail('File harus berupa backup PostgreSQL plain-text (.sql) yang dikenali.');
                    }
                },
            ],
            'restoreConfirmation' => ['required', 'in:PULIHKAN DATABASE'],
        ], [
            'databaseFile.required' => 'Pilih file database SQL terlebih dahulu.',
            'databaseFile.file' => 'File tidak valid.',
            'databaseFile.max' => 'Ukuran file maksimal adalah 50MB.',
            'restoreConfirmation.required' => 'Ketik PULIHKAN DATABASE untuk melanjutkan.',
            'restoreConfirmation.in' => 'Konfirmasi restore belum sesuai.',
        ]);

        $filePath = $this->databaseFile->getRealPath();

        if (!file_exists($filePath)) {
            $this->dispatch('toast', message: 'File unggahan tidak dapat diakses.', type: 'danger');
            return;
        }

        try {
            app(DatabaseManagementService::class)->restoreBackup($filePath);
        } catch (RuntimeException $exception) {
            Log::error('[Database] psql restore failed: ' . $this->redactSecrets($exception->getMessage()));
            $this->dispatch('toast', message: 'Pemulihan database gagal. Data lama tetap dipertahankan.', type: 'danger');
            return;
        }

        $this->reset('databaseFile');
        $this->restoreConfirmation = '';
        $this->dispatch('toast', message: 'Database berhasil dipulihkan!', type: 'success');
    }

    public function render()
    {
        return view('livewire.admin.database-management');
    }

    private function ensureAdminAccess(): void
    {
        abort_unless(auth()->check() && auth()->user()->isAdmin(), 403, 'Akses ditolak. Hanya admin yang dapat mengelola database.');
    }

    private function redactSecrets(string $message): string
    {
        $patterns = [
            '/PGPASSWORD\s*=\s*[^ \n\r\t;]+/i' => 'PGPASSWORD=[redacted]',
            '/password\s*[:=]\s*[^ \n\r\t;]+/i' => 'password=[redacted]',
            '/Bearer\s+[A-Za-z0-9\-\._~\+\/]+=*/i' => 'Bearer [redacted]',
            '/Authorization:\s*[^\n\r]+/i' => 'Authorization: [redacted]',
            '/api[_-]?token\s*[:=]\s*[^ \n\r\t;]+/i' => 'api_token=[redacted]',
        ];

        return preg_replace(array_keys($patterns), array_values($patterns), $message) ?? '[redacted]';
    }
}
