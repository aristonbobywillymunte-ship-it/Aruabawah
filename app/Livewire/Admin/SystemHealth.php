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
    public bool $showQueueModal = false;
    public array $queueDetails = [];
    public bool $showRedisQueueModal = false;
    public array $redisQueueDetails = [];

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
        $queueCount = DB::table('ai_analysis_dispatch_states')
            ->whereIn('status', ['queued', 'processing', 'retry_wait'])
            ->count();

        $this->aiStatus = [
            'default' => $defaultAi ? $defaultAi->name . ' (' . $defaultAi->model_name . ')' : 'Tidak Ada',
            'fallback' => $fallbackCount > 0 ? 'Tersedia (' . $fallbackCount . ')' : 'Tidak Tersedia',
            'queue_count' => $queueCount,
            'status' => $defaultAi ? 'OK' : 'Warning',
            'color' => $defaultAi ? 'green' : 'yellow',
        ];

        // 2. Apify Status
        $apifySetting = ApifySetting::first();
        $activeActors = ApifyActor::where('status', 'active')->count();
        $inactiveActors = ApifyActor::where('status', 'inactive')->count();
        
        // Menghitung jumlah antrean dispatch Apify yang sedang berjalan/menunggu
        $apifyQueueCount = DB::table('apify_dispatch_states')
            ->whereIn('status', ['queued', 'processing', 'retry_wait'])
            ->count();

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
            'queue_count' => $apifyQueueCount,
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
            
            // Hitung antrean di Redis (saluran 'default' dan 'ai-analysis' sesuai config queue)
            $redisConnection = Redis::connection('default');
            
            $defaultQueue = (int) $redisConnection->llen('queues:default');
            $defaultDelayed = (int) $redisConnection->zcard('queues:default:delayed');
            $defaultReserved = (int) $redisConnection->zcard('queues:default:reserved');
            
            $aiQueue = (int) $redisConnection->llen('queues:ai-analysis');
            $aiDelayed = (int) $redisConnection->zcard('queues:ai-analysis:delayed');
            $aiReserved = (int) $redisConnection->zcard('queues:ai-analysis:reserved');
            
            $totalRedisJobs = $defaultQueue + $defaultDelayed + $defaultReserved + $aiQueue + $aiDelayed + $aiReserved;

            $this->redisStatus = [
                'connection' => 'Connected',
                'status' => 'Normal',
                'color' => 'green',
                'queue_count' => $totalRedisJobs,
            ];
        } catch (\Throwable $e) {
            // Mocking Redis connection for local development sandbox environments if no local daemon is alive
            $this->redisStatus = [
                'connection' => 'Disconnected (Simulated OK)',
                'status' => 'Warning',
                'color' => 'yellow',
                'queue_count' => 0,
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

    public function openQueueModal(): void
    {
        $this->adminOnly();
        
        $rawItems = DB::table('ai_analysis_dispatch_states')
            ->whereIn('status', ['queued', 'processing', 'retry_wait'])
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        $this->queueDetails = $rawItems->map(function ($item) {
            $projectName = 'N/A';
            $contentTitle = 'N/A';
            $contentDate = '-';

            if ($item->project_id) {
                $project = DB::table('projects')->where('id', $item->project_id)->first();
                if ($project) {
                    $projectName = $project->name;
                }
            }

            $article = null;
            $social = null;

            if ($item->analyzable_type === 'article') {
                $article = DB::table('articles')->where('id', $item->analyzable_id)->first();
                // Fallback ke social jika tidak ketemu di articles
                if (!$article) {
                    $social = DB::table('social_media_items')->where('id', $item->analyzable_id)->first();
                }
            } else {
                $social = DB::table('social_media_items')->where('id', $item->analyzable_id)->first();
                // Fallback ke articles jika tidak ketemu di social
                if (!$social) {
                    $article = DB::table('articles')->where('id', $item->analyzable_id)->first();
                }
            }

            if ($article) {
                $contentTitle = $article->title ?: ($article->source_name ?: 'Portal News Item');
                $contentDate = $article->published_at ? \Carbon\Carbon::parse($article->published_at)->isoFormat('D MMM YYYY, HH:mm') : '-';
            } elseif ($social) {
                $text = data_get($social, 'text') ?? data_get($social, 'content');
                $author = data_get($social, 'author') ?? data_get($social, 'author_name');
                $contentTitle = $text ? mb_substr(strip_tags($text), 0, 80) . '...' : ($author ? 'Post dari ' . $author : 'Post Media Sosial');
                
                $postCreatedAt = data_get($social, 'posted_at') ?? (data_get($social, 'post_created_at') ?? data_get($social, 'created_at'));
                $contentDate = $postCreatedAt ? \Carbon\Carbon::parse($postCreatedAt)->isoFormat('D MMM YYYY, HH:mm') : '-';
            }

            return [
                'id' => $item->id,
                'type' => $item->analyzable_type === 'article' ? 'Portal Berita' : 'Media Sosial',
                'title' => $contentTitle,
                'content_date' => $contentDate,
                'project' => $projectName,
                'status' => $item->status,
                'attempts' => $item->attempts,
                'error_message' => $item->error_message ?: '-',
                'created_at' => $item->created_at ? \Carbon\Carbon::parse($item->created_at)->isoFormat('D MMM YYYY, HH:mm') : '-',
            ];
        })->toArray();

        $this->showQueueModal = true;
    }

    public function closeQueueModal(): void
    {
        $this->showQueueModal = false;
        $this->queueDetails = [];
    }

    public function openRedisQueueModal(): void
    {
        $this->adminOnly();
        $this->redisQueueDetails = [];

        try {
            $redisConnection = Redis::connection('default');
            $queues = ['default', 'ai-analysis'];

            foreach ($queues as $qName) {
                // 1. Ambil job aktif/antre (lrange)
                $rawList = $redisConnection->lrange("queues:{$qName}", 0, -1) ?: [];
                foreach ($rawList as $rawJob) {
                    $jobData = json_decode($rawJob, true);
                    if ($jobData) {
                        $this->redisQueueDetails[] = $this->parseRedisJobPayload($qName, 'Mengantre', $jobData);
                    }
                }

                // 2. Ambil job delay (zrange)
                $rawDelay = $redisConnection->zrange("queues:{$qName}:delayed", 0, -1) ?: [];
                foreach ($rawDelay as $rawJob) {
                    $jobData = json_decode($rawJob, true);
                    if ($jobData) {
                        $this->redisQueueDetails[] = $this->parseRedisJobPayload($qName, 'Tertunda (Delay)', $jobData);
                    }
                }

                // 3. Ambil job sedang diproses/reserved (zrange)
                $rawReserved = $redisConnection->zrange("queues:{$qName}:reserved", 0, -1) ?: [];
                foreach ($rawReserved as $rawJob) {
                    $jobData = json_decode($rawJob, true);
                    if ($jobData) {
                        $this->redisQueueDetails[] = $this->parseRedisJobPayload($qName, 'Diproses Worker', $jobData);
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->redisQueueDetails = [];
        }

        $this->showRedisQueueModal = true;
    }

    private function parseRedisJobPayload(string $queueName, string $status, array $jobData): array
    {
        $displayName = $jobData['displayName'] ?? ($jobData['job'] ?? 'Unknown Job');
        if (str_contains($displayName, '\\')) {
            $parts = explode('\\', $displayName);
            $displayName = end($parts);
        }

        // Parse detail payload parameter
        $targetDesc = '-';
        $payloadRaw = $jobData['data']['command'] ?? null;
        if ($payloadRaw && is_string($payloadRaw)) {
            // Unserialize command object PHP jika memungkinkan
            try {
                // membersihkan string serialized yang berpotensi error
                if (str_contains($payloadRaw, 'ApifyScrapingJob')) {
                    $targetDesc = 'Apify Scraping Pipeline';
                    if (preg_match('/"platform";s:\d+:"([^"]+)"/', $payloadRaw, $m1) && preg_match('/"keyword";s:\d+:"([^"]+)"/', $payloadRaw, $m2)) {
                        $targetDesc = "Scrape {$m1[1]}: " . mb_substr($m2[1], 0, 40) . '...';
                    }
                } elseif (str_contains($payloadRaw, 'AiAnalysisJob')) {
                    $targetDesc = 'Analisis Sentimen AI';
                    if (preg_match('/"articleId";i:(\d+)/', $payloadRaw, $m)) {
                        $targetDesc = "Analisis Artikel ID: {$m[1]}";
                    }
                }
            } catch (\Throwable $e) {
                $targetDesc = 'Job Object';
            }
        }

        $attempts = $jobData['attempts'] ?? 0;
        $createdAt = isset($jobData['createdAt']) ? \Carbon\Carbon::createFromTimestamp((int) $jobData['createdAt'])->isoFormat('D MMM YYYY, HH:mm') : '-';

        return [
            'queue' => $queueName,
            'name' => $displayName,
            'target' => $targetDesc,
            'status' => $status,
            'attempts' => $attempts,
            'created_at' => $createdAt,
        ];
    }

    public function closeRedisQueueModal(): void
    {
        $this->showRedisQueueModal = false;
        $this->redisQueueDetails = [];
    }

    public function render()
    {
        $this->adminOnly();
        return view('livewire.admin.system-health');
    }
}
