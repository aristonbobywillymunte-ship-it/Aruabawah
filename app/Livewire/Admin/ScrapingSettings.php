<?php

namespace App\Livewire\Admin;

use App\Models\ScrapingSetting;
use Livewire\Component;

class ScrapingSettings extends Component
{
    // Form fields
    public int $google_news_interval = 5;
    public int $portal_crawling_interval = 120;
    public int $limit_per_run = 50;
    public string $date_range = '7d';
    public int $timeout_seconds = 30;
    public int $retry_limit = 3;
    public int $retry_delay_minutes = 10;
    public bool $is_active = true;
    public bool $enable_realtime = false;

    // UI state
    public bool $showEditModal = false;
    public ?string $flashMessage = null;
    public ?string $flashType = null;

    protected function adminOnly(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'Akses ditolak.');
    }

    protected function setting(): ScrapingSetting
    {
        return ScrapingSetting::firstOrCreate(['id' => 1]);
    }

    protected function rules(): array
    {
        return [
            'google_news_interval' => ['required', 'integer', 'min:5', 'max:1440'],
            'portal_crawling_interval' => ['required', 'integer', 'min:5', 'max:1440'],
            'limit_per_run' => ['required', 'integer', 'min:1', 'max:1000'],
            'date_range' => ['required', 'string', 'in:24h,7d,30d,90d'],
            'timeout_seconds' => ['required', 'integer', 'min:5', 'max:300'],
            'retry_limit' => ['required', 'integer', 'min:0', 'max:10'],
            'retry_delay_minutes' => ['required', 'integer', 'min:1', 'max:180'],
            'is_active' => ['boolean'],
            'enable_realtime' => ['boolean'],
        ];
    }

    public function mount(): void
    {
        $this->adminOnly();
        $this->loadSettings();
    }

    public function render()
    {
        $this->adminOnly();
        return view('livewire.admin.scraping-settings', [
            'setting' => $this->setting(),
        ]);
    }

    public function loadSettings(): void
    {
        $setting = $this->setting();
        $this->google_news_interval = (int) ($setting->google_news_interval ?? 5);
        $this->portal_crawling_interval = (int) ($setting->portal_crawling_interval ?? 120);
        $this->limit_per_run = (int) ($setting->limit_per_run ?? 50);
        $this->date_range = (string) ($setting->date_range ?? '7d');
        $this->timeout_seconds = (int) ($setting->timeout_seconds ?? 30);
        $this->retry_limit = (int) ($setting->retry_limit ?? 3);
        $this->retry_delay_minutes = (int) ($setting->retry_delay_minutes ?? 10);
        $this->is_active = (bool) ($setting->is_active ?? true);
        $this->enable_realtime = $setting->enable_realtime ?? false;
    }

    public function openEditModal(): void
    {
        $this->adminOnly();
        $this->loadSettings();
        $this->showEditModal = true;
    }

    public function save(): void
    {
        $this->adminOnly();
        $this->validate();

        $setting = $this->setting();
        $setting->update([
            'google_news_interval' => $this->google_news_interval,
            'portal_crawling_interval' => $this->portal_crawling_interval,
            'limit_per_run' => $this->limit_per_run,
            'date_range' => $this->date_range,
            'timeout_seconds' => $this->timeout_seconds,
            'retry_limit' => $this->retry_limit,
            'retry_delay_minutes' => $this->retry_delay_minutes,
            'is_active' => $this->is_active,
            'enable_realtime' => $this->enable_realtime,
        ]);

        $this->showEditModal = false;
        $this->notify('success', 'Konfigurasi scraping berhasil diperbarui.');
    }

    public function toggleStatus(): void
    {
        $this->adminOnly();
        $setting = $this->setting();
        $setting->is_active = !$setting->is_active;
        $setting->save();
        $this->is_active = $setting->is_active;

        $this->notify('success', 'Status aktivitas scraping berhasil diperbarui.');
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
}
