<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DatabaseManagementService;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseBackupController extends Controller
{
    public function download(): BinaryFileResponse|\Illuminate\Http\RedirectResponse
    {
        abort_unless(auth()->check() && auth()->user()->isAdmin(), 403, 'Akses ditolak.');

        try {
            $tempFile = app(DatabaseManagementService::class)->exportBackup();
        } catch (RuntimeException $exception) {
            Log::error('[Database] pg_dump failed: ' . $exception->getMessage());
            return redirect()->route('admin.database')->with('error', 'Gagal membuat cadangan database.');
        }

        return response()->download(
            $tempFile,
            'backup_media_intelligent_' . now()->format('Y-m-d_H-i-s') . '.sql'
        )->deleteFileAfterSend();
    }
}
