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
        $this->adminOnly();
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

        try {
            $result = $service->clearPendingQueues();
            $this->showConfirmModal = false;
            $this->clearConfirmation = '';

            Log::info('[System Maintenance] Cleared pending Redis queues', [
                'deleted_jobs' => $result['deleted_jobs'],
                'succeeded_queues' => $result['succeeded_queues'],
                'failed_queues' => $result['failed_queues'],
                'partial_failure' => $result['partial_failure'],
                'triggered_by' => auth()->user()?->email,
            ]);

            $detail = $result['partial_failure']
                ? 'Sebagian antrean berhasil dibersihkan, namun ada antrean yang gagal diperiksa atau dibersihkan.'
                : 'Antrean Redis yang menunggu berhasil dibersihkan tanpa menyentuh job yang sedang berjalan.';

            $this->maintenanceSummary = [
                'title' => $result['partial_failure'] ? 'Redis Queue Dibersihkan Sebagian' : 'Redis Queue Dibersihkan',
                'detail' => $detail,
            ];

            $message = $result['partial_failure']
                ? 'Sebagian antrean Redis berhasil dibersihkan.'
                : 'Antrean Redis yang menunggu berhasil dibersihkan.';

            $this->notify($result['partial_failure'] ? 'warning' : 'success', $message);
        } catch (\Throwable $e) {
            $this->showConfirmModal = false;
            $this->clearConfirmation = '';
            Log::warning('[System Maintenance] Failed to clear Redis queues', [
                'triggered_by' => auth()->user()?->email,
            ]);
            $this->maintenanceSummary = [
                'title' => 'Redis Queue Tidak Dapat Diperiksa',
                'detail' => 'Status antrean Redis tidak tersedia saat ini. Silakan coba lagi nanti.',
            ];
            $this->notify('error', 'Status antrean Redis sedang tidak tersedia.');
        }
    }

    public function restartWorkers(): void
    {
        $this->adminOnly();
        $service = app(SystemMaintenanceService::class);

        try {
            $result = $service->restartWorkers();
            if (($result['status'] ?? 'error') !== 'ok' || (int) ($result['exit_code'] ?? 1) !== 0) {
                Log::warning('[System Maintenance] Queue worker restart failed', [
                    'error' => $result['error'] ?? null,
                    'triggered_by' => auth()->user()?->email,
                ]);

                $this->notify('error', 'Signal restart worker gagal dikirim.');
                return;
            }
        } catch (\Throwable $e) {
            Log::warning('[System Maintenance] Queue worker restart failed', [
                'error' => 'exception',
                'triggered_by' => auth()->user()?->email,
            ]);

            $this->notify('error', 'Signal restart worker gagal dikirim.');
            return;
        }

        Log::info('[System Maintenance] Queue worker restart requested', [
            'triggered_by' => auth()->user()?->email,
        ]);

        $this->maintenanceSummary = [
            'title' => 'Signal Restart Worker Dikirim',
            'detail' => 'Signal restart worker Laravel berhasil dikirim. Worker akan berhenti setelah menyelesaikan job yang sedang berjalan lalu lanjut loop normal berikutnya.',
        ];

        $this->notify('success', 'Sinyal restart worker Laravel berhasil dikirim.');
    }

    public function restartScheduler(): void
    {
        $this->adminOnly();
        $service = app(SystemMaintenanceService::class);

        try {
            $result = $service->requestSchedulerRestart();
            if (($result['status'] ?? 'error') !== 'ok') {
                Log::warning('[System Maintenance] Scheduler restart failed', [
                    'error' => $result['error'] ?? null,
                    'triggered_by' => auth()->user()?->email,
                ]);

                $this->notify('error', 'Signal restart scheduler gagal dikirim.');
                return;
            }
        } catch (\Throwable $e) {
            Log::warning('[System Maintenance] Scheduler restart failed', [
                'error' => 'exception',
                'triggered_by' => auth()->user()?->email,
            ]);

            $this->notify('error', 'Signal restart scheduler gagal dikirim.');
            return;
        }

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

        try {
            $result = $service->clearApplicationCache();
            if (($result['status'] ?? 'error') !== 'ok' || (int) ($result['exit_code'] ?? 1) !== 0) {
                Log::warning('[System Maintenance] Laravel optimize clear failed', [
                    'error' => $result['error'] ?? null,
                    'triggered_by' => auth()->user()?->email,
                ]);

                $this->notify('error', 'Bersihkan cache Laravel gagal.');
                return;
            }
        } catch (\Throwable $e) {
            Log::warning('[System Maintenance] Laravel optimize clear failed', [
                'error' => 'exception',
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
        try {
            $queueSnapshot = $service->queueSnapshot();
        } catch (\Throwable $e) {
            Log::warning('[System Maintenance] Queue snapshot unavailable', [
                'triggered_by' => auth()->user()?->email,
            ]);
            $queueSnapshot = [[
                'queue_connection' => 'maintenance',
                'redis_connection' => null,
                'queue' => 'maintenance',
                'status' => 'error',
                'pending' => null,
                'delayed' => null,
                'reserved' => null,
                'total' => null,
                'error' => 'Status antrean Redis tidak tersedia.',
            ]];
        }

        return view('livewire.admin.system-maintenance', [
            'queueSnapshot' => $queueSnapshot,
            'requiredConfirmationPhrase' => $service->requiredConfirmationPhrase(),
        ]);
    }
}
