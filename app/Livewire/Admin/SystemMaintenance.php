<?php

namespace App\Livewire\Admin;

use App\Services\Admin\SystemMaintenanceService;
use Livewire\Component;
use Illuminate\Support\Facades\Log;

class SystemMaintenance extends Component
{
    public bool $showConfirmModal = false;
    public string $clearConfirmation = '';
    public ?array $maintenanceSummary = null;
    public ?string $flashMessage = null;
    public string $flashType = 'success';

    protected function adminOnly(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function mount(): void
    {
        $this->adminOnly();
    }

    public function confirmClearRedisQueue(): void
    {
        $this->adminOnly();
        $this->showConfirmModal = true;
        $this->clearConfirmation = '';
    }

    public function cancelClearRedisQueue(): void
    {
        $this->showConfirmModal = false;
        $this->clearConfirmation = '';
    }

    public function clearRedisQueue(): void
    {
        $this->adminOnly();
        $service = app(SystemMaintenanceService::class);

        if ($this->clearConfirmation !== $service->requiredConfirmationPhrase()) {
            $this->notify('error', 'Ketik HAPUS ANTREAN untuk melanjutkan.');
            return;
        }

        $result = $service->clearPendingQueues();
        $this->showConfirmModal = false;
        $this->clearConfirmation = '';

        Log::info('[System Maintenance] Cleared pending Redis queues', [
            'deleted_jobs' => $result['deleted_jobs'],
            'queues' => collect($result['queues'])->map(fn ($queue) => [
                'connection' => $queue['connection'],
                'queue' => $queue['queue'],
                'pending' => $queue['pending'],
                'delayed' => $queue['delayed'],
            ])->values()->all(),
            'triggered_by' => auth()->user()?->email,
        ]);

        $this->maintenanceSummary = [
            'title' => 'Redis Queue Dibersihkan',
            'detail' => "{$result['deleted_jobs']} job menunggu berhasil dihapus dari antrean Redis tanpa menyentuh job yang sedang berjalan.",
        ];

        $this->notify('success', 'Antrean Redis yang menunggu berhasil dibersihkan.');
    }

    public function restartWorkers(): void
    {
        $this->adminOnly();
        $service = app(SystemMaintenanceService::class);

        $exitCode = $service->restartWorkers();
        if ($exitCode !== 0) {
            Log::warning('[System Maintenance] Queue worker restart failed', [
                'exit_code' => $exitCode,
                'triggered_by' => auth()->user()?->email,
            ]);

            $this->notify('error', 'Restart worker gagal dikirim.');
            return;
        }

        Log::info('[System Maintenance] Queue worker restart requested', [
            'triggered_by' => auth()->user()?->email,
        ]);

        $this->maintenanceSummary = [
            'title' => 'Worker Direstart',
            'detail' => 'Signal restart worker Laravel berhasil dikirim.',
        ];

        $this->notify('success', 'Worker Laravel berhasil direstart.');
    }

    public function restartScheduler(): void
    {
        $this->adminOnly();
        $service = app(SystemMaintenanceService::class);

        $service->requestSchedulerRestart();

        Log::info('[System Maintenance] Scheduler restart requested', [
            'triggered_by' => auth()->user()?->email,
        ]);

        $this->maintenanceSummary = [
            'title' => 'Signal Restart Scheduler Dikirim',
            'detail' => 'Signal restart scheduler kontainer telah dikirim. Kontainer scheduler akan berhenti pada heartbeat berikutnya lalu dihidupkan ulang oleh Docker Compose.',
        ];

        $this->notify('success', 'Sinyal restart scheduler berhasil dikirim.');
    }

    public function clearMaintenanceCache(): void
    {
        $this->adminOnly();
        $service = app(SystemMaintenanceService::class);

        $exitCode = $service->clearApplicationCache();
        if ($exitCode !== 0) {
            Log::warning('[System Maintenance] Laravel optimize clear failed', [
                'exit_code' => $exitCode,
                'triggered_by' => auth()->user()?->email,
            ]);

            $this->notify('error', 'Bersihkan cache Laravel gagal.');
            return;
        }

        Log::info('[System Maintenance] Laravel optimize cleared', [
            'triggered_by' => auth()->user()?->email,
        ]);

        $this->maintenanceSummary = [
            'title' => 'Cache Dibersihkan',
            'detail' => 'Config, route, event, dan view cache Laravel berhasil dibersihkan.',
        ];

        $this->notify('success', 'Cache Laravel berhasil dibersihkan.');
    }

    protected function notify(string $type, string $message): void
    {
        $this->flashType = $type;
        $this->flashMessage = $message;
        $payload = [
            'type' => $type,
            'title' => $message,
            'message' => '',
        ];

        if (method_exists($this, 'dispatchBrowserEvent')) {
            $this->dispatchBrowserEvent('admin-toast', $payload);
        }

        $this->dispatch('admin-toast', payload: $payload);
    }

    public function render()
    {
        $this->adminOnly();
        $service = app(SystemMaintenanceService::class);

        return view('livewire.admin.system-maintenance', [
            'queueSnapshot' => $service->queueSnapshot(),
            'requiredConfirmationPhrase' => $service->requiredConfirmationPhrase(),
        ]);
    }
}
