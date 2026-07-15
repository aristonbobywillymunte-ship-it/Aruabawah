<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

class SystemMaintenance extends Component
{
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

    public function clearApifyQueue(): void
    {
        $this->adminOnly();

        $deleted = DB::table('jobs')
            ->where('payload', 'like', '%"displayName":"App\\\\Jobs\\\\ApifyScrapingJob"%')
            ->delete();

        Log::info('[Apify Maintenance] Cleared Apify queue', [
            'deleted_jobs' => $deleted,
            'triggered_by' => auth()->user()?->email,
        ]);

        $this->maintenanceSummary = [
            'title' => 'Apify Queue Dibersihkan',
            'detail' => "{$deleted} job Apify dihapus dari antrean.",
        ];

        $this->notify('success', 'Antrean Apify berhasil dibersihkan.');
    }

    public function restartWorkers(): void
    {
        $this->adminOnly();

        Artisan::call('queue:restart');

        Log::info('[Apify Maintenance] Queue worker restart requested', [
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

        // Signal scheduler container to exit. It will restart automatically due to restart:always
        \Illuminate\Support\Facades\Cache::put('scheduler_should_restart', true, 60);

        Log::info('[Apify Maintenance] Scheduler restart requested', [
            'triggered_by' => auth()->user()?->email,
        ]);

        $this->maintenanceSummary = [
            'title' => 'Signal Restart Scheduler Dikirim',
            'detail' => 'Signal restart scheduler kontainer telah dikirim. Kontainer scheduler akan melakukan restart dalam waktu maksimal 60 detik pada detak berikutnya.',
        ];

        $this->notify('success', 'Sinyal restart scheduler berhasil dikirim.');
    }

    public function clearMaintenanceCache(): void
    {
        $this->adminOnly();

        Artisan::call('optimize:clear');

        Log::info('[Apify Maintenance] Laravel optimize cleared', [
            'triggered_by' => auth()->user()?->email,
        ]);

        $this->maintenanceSummary = [
            'title' => 'Cache Dibersihkan',
            'detail' => 'Config, route, event, dan view cache Laravel berhasil dibersihkan.',
        ];

        $this->notify('success', 'Cache Laravel berhasil dibersihkan.');
    }

    public function startReverb(): void
    {
        $this->adminOnly();

        if (\App\Helpers\ReverbManager::start()) {
            Log::info('[Reverb Maintenance] Started Reverb server', [
                'triggered_by' => auth()->user()?->email,
            ]);

            $this->maintenanceSummary = [
                'title' => 'Server Reverb Dinyalakan',
                'detail' => 'Proses daemon Laravel Reverb berhasil dimulai di background.',
            ];

            $this->notify('success', 'Server Reverb berhasil dinyalakan.');
        } else {
            $this->notify('error', 'Gagal menyalakan server Reverb.');
        }
    }

    public function stopReverb(): void
    {
        $this->adminOnly();

        if (\App\Helpers\ReverbManager::stop()) {
            Log::info('[Reverb Maintenance] Stopped Reverb server', [
                'triggered_by' => auth()->user()?->email,
            ]);

            $this->maintenanceSummary = [
                'title' => 'Server Reverb Dimatikan',
                'detail' => 'Proses daemon Laravel Reverb berhasil dihentikan.',
            ];

            $this->notify('success', 'Server Reverb berhasil dimatikan.');
        } else {
            $this->notify('error', 'Gagal mematikan server Reverb.');
        }
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

        return view('livewire.admin.system-maintenance', [
            'isReverbRunning' => \App\Helpers\ReverbManager::isRunning(),
        ]);
    }
}
