<?php

namespace App\Livewire\Admin;

use App\Models\AiProvider;
use App\Models\ApifyActor;
use App\Models\ApifySetting;
use App\Models\ScrapingItem;
use App\Models\TelegramSetting;
use App\Models\RiskNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Livewire\Component;
use Livewire\Attributes\On;

class SystemHealth extends Component
{
    public array $aiStatus = [];
    public array $apifyStatus = [];
    public array $scrapingStatus = [];
    public array $telegramStatus = [];
    public array $dbStatus = [];
    public array $redisStatus = [];
    public array $schedulerStatus = [];
    public array $reverbStatus = [];
    public array $latestErrors = [];

    #[On('echo:system-alerts,RealtimeNotificationEvent')]
    public function handleRealtimeNotification($event): void
    {
        $this->checkHealth();
    }

    protected function adminOnly(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function mount(): void
    {
        $this->adminOnly();
        $this->checkHealth();
    }

    public function checkHealth(): void
    {
        $this->adminOnly();
        \App\Models\AiProvider::syncDefaultToEligible();

        // 1. AI Provider Status
        $defaultAi = AiProvider::where('is_default', true)->where('is_active', true)->first();
        $fallbackCount = AiProvider::where('is_default', false)->where('is_active', true)->count();
        $this->aiStatus = [
            'default' => $defaultAi ? $defaultAi->name . ' (' . $defaultAi->model_name . ')' : 'Tidak Ada',
            'fallback' => $fallbackCount > 0 ? 'Tersedia (' . $fallbackCount . ')' : 'Tidak Tersedia',
            'status' => $defaultAi ? 'OK' : 'Warning',
            'color' => $defaultAi ? 'green' : 'yellow',
        ];

        // 2. Apify Status
        $apifySetting = ApifySetting::first();
        $activeActors = ApifyActor::where('status', 'active')->count();
        $inactiveActors = ApifyActor::where('status', 'inactive')->count();
        $failedActors = ApifyActor::where('status', 'active')
            ->where('last_run_status', 'failed')
            ->get()
            ->filter(fn ($actor) => !ApifyActor::shouldSuppressUiError($actor->last_run_message));
        $hasFailures = $failedActors->isNotEmpty();
        
        $status = 'OK';
        $color = 'green';
        $failedMessage = '';
        
        if (!$apifySetting || !$apifySetting->api_token || $activeActors === 0) {
            $status = 'Warning';
            $color = 'yellow';
        } elseif ($hasFailures) {
            $status = 'Error';
            $color = 'red';
            $failedMessage = $failedActors->pluck('platform')->unique()->implode(', ') . ' limit/error';
        }

        $this->apifyStatus = [
            'token' => ($apifySetting && $apifySetting->api_token) ? 'Tersedia' : 'Belum Diisi',
            'active_actors' => $activeActors,
            'inactive_actors' => $inactiveActors,
            'status' => $status,
            'color' => $color,
            'failed_message' => $failedMessage,
        ];

        // 3. Scraping Status
        $pendingScrape = ScrapingItem::where('status', 'pending')->count();
        $failedScrape = ScrapingItem::where('status', 'failed')->count();
        $this->scrapingStatus = [
            'pending' => $pendingScrape,
            'failed' => $failedScrape,
            'status' => $failedScrape > 0 ? 'Warning' : 'OK',
            'color' => $failedScrape > 0 ? 'yellow' : 'green',
        ];

        // 4. Telegram Status
        $teleSetting = TelegramSetting::first();
        $lastSent = RiskNotification::where('status', 'sent')->latest('updated_at')->first();
        $telegramCredentialStatus = $teleSetting?->notificationCredentialStatus() ?? [
            'ready' => false,
            'issues' => ['missing_setting'],
        ];
        $hasRealToken = $telegramCredentialStatus['ready'];

        $this->telegramStatus = [
            'active' => ($teleSetting && $teleSetting->is_active) ? ($hasRealToken ? 'Active' : 'Belum Dikonfigurasi') : 'Inactive',
            'last_sent' => $lastSent ? $lastSent->updated_at->diffForHumans() : 'Belum pernah',
            'status' => ($teleSetting && $teleSetting->is_active && $hasRealToken) ? 'OK' : 'Warning',
            'color' => ($teleSetting && $teleSetting->is_active && $hasRealToken) ? 'green' : 'yellow',
            'issues' => $telegramCredentialStatus['issues'] ?? [],
        ];

        // 5. Database Status (PostgreSQL/SQLite fallback)
        try {
            DB::connection()->getPdo();
            $dbName = config('database.default');
            $this->dbStatus = [
                'connection' => strtoupper($dbName),
                'status' => 'Normal',
                'color' => 'green',
            ];
        } catch (\Throwable $e) {
            $this->dbStatus = [
                'connection' => 'PostgreSQL/SQLite',
                'status' => 'Error Connection',
                'color' => 'red',
            ];
        }

        // 6. Redis Status
        try {
            Redis::ping();
            $this->redisStatus = [
                'connection' => 'Connected',
                'status' => 'Normal',
                'color' => 'green',
            ];
        } catch (\Throwable $e) {
            // Mocking Redis connection for local development sandbox environments if no local daemon is alive
            $this->redisStatus = [
                'connection' => 'Disconnected (Simulated OK)',
                'status' => 'Warning',
                'color' => 'yellow',
            ];
        }

        // 7. Scheduler Status
        $heartbeat = \Illuminate\Support\Facades\Cache::get('scheduler_heartbeat');
        $diff = $heartbeat ? (now()->timestamp - $heartbeat) : null;
        $isActive = $diff !== null && $diff < 180; // Toleransi 3 menit

        $this->schedulerStatus = [
            'status' => $isActive ? 'Active' : 'Offline',
            'color' => $isActive ? 'green' : 'red',
            'last_seen' => $heartbeat ? \Carbon\Carbon::createFromTimestamp($heartbeat)->diffForHumans() : 'Never',
            'timestamp' => $heartbeat,
        ];

        // 7.5. Reverb Status
        $isReverbRunning = \App\Helpers\ReverbManager::isRunning();
        $this->reverbStatus = [
            'status' => $isReverbRunning ? 'Active' : 'Offline',
            'color' => $isReverbRunning ? 'green' : 'red',
        ];

        // 8. Latest Errors
        $scrapeErrors = ScrapingItem::whereNotNull('error_message')
            ->where('error_message', 'not like', '%Content too short%')
            ->where('error_message', 'not like', '%Resolved URL is not a valid portal article%')
            ->where('error_message', 'not like', '%Keyword filter did not match%')
            ->latest('updated_at')
            ->limit(3)
            ->get()
            ->map(function ($item) {
                return '[Scraper] URL: ' . $item->url . ' - ' . $item->error_message;
            })
            ->toArray();

        $aiErrors = AiProvider::whereNotNull('last_error')
            ->latest('updated_at')
            ->limit(2)
            ->get()
            ->map(function ($item) {
                return '[AI Provider] ' . $item->name . ' - ' . $item->last_error;
            })
            ->toArray();

        $this->latestErrors = array_merge($scrapeErrors, $aiErrors);
    }

    public function clearErrors(): void
    {
        $this->adminOnly();
        \App\Models\ScrapingItem::whereNotNull('error_message')->update(['error_message' => null]);
        \App\Models\AiProvider::whereNotNull('last_error')->update(['last_error' => null]);
        $this->checkHealth();
    }

    public function render()
    {
        $this->adminOnly();
        return view('livewire.admin.system-health');
    }
}
