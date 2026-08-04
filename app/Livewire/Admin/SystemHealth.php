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
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\WithPagination;

class SystemHealth extends Component
{
    use WithPagination;

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
    
    // Properti filter pencarian & pagination AI Pipeline
    public string $searchQuery = '';
    public string $filterStatus = '';
    public string $filterProject = '';
    public string $filterType = '';
    public string $filterActor = '';
    public int $perPage = 15;
    
    public bool $showRedisQueueModal = false;
    public array $redisQueueDetails = [];
    public bool $showApifyQueueModal = false;
    public array $apifyQueueDetails = [];

    // State untuk Modal Konfirmasi Error Handling AI
    public bool $showConfirmModal = false;
    public string $confirmActionType = '';
    public ?int $confirmItemId = null;

    protected $queryString = [
        'searchQuery' => ['except' => ''],
        'filterStatus' => ['except' => ''],
        'filterProject' => ['except' => ''],
        'filterType' => ['except' => ''],
        'filterActor' => ['except' => ''],
    ];

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

        $apifyErrors = ApifyActor::where('last_run_status', 'failed')
            ->whereNotNull('last_run_message')
            ->latest('updated_at')
            ->limit(3)
            ->get()
            ->map(function ($item) {
                return '[Apify Scraper] ' . $item->platform . ' (' . $item->function_type . ') - ' . Str::limit($item->last_run_message, 120);
            })
            ->toArray();

        $apifyQueueErrors = DB::table('apify_dispatch_states')
            ->where('status', 'retry_wait')
            ->whereNotNull('last_error_message')
            ->orderBy('id', 'desc')
            ->limit(3)
            ->get()
            ->map(function ($item) {
                return '[Apify Queue] ' . $item->platform . ' - ' . Str::limit($item->last_error_message, 120);
            })
            ->toArray();

        $this->latestErrors = array_merge($scrapeErrors, $aiErrors, $apifyErrors, $apifyQueueErrors);
    }

    public function clearErrors(): void
    {
        $this->adminOnly();
        \App\Models\ScrapingItem::whereNotNull('error_message')->update(['error_message' => null]);
        \App\Models\AiProvider::whereNotNull('last_error')->update(['last_error' => null]);
        \App\Models\ApifyActor::where('last_run_status', 'failed')->update([
            'last_run_status' => null,
            'last_run_message' => null
        ]);
        DB::table('apify_dispatch_states')
            ->where('status', 'retry_wait')
            ->update([
                'status' => 'failed', // change to final failed or clear error message
                'last_error_message' => null
            ]);
        $this->checkHealth();
    }

    public function openQueueModal(): void
    {
        $this->adminOnly();
        $this->resetPage(); // Reset pagination page Livewire
        $this->showQueueModal = true;
    }

    public function closeQueueModal(): void
    {
        $this->showQueueModal = false;
    }

    public function openConfirmModal(string $actionType, ?int $itemId = null): void
    {
        $this->adminOnly();
        $this->confirmActionType = $actionType;
        $this->confirmItemId = $itemId;
        $this->showConfirmModal = true;
    }

    public function closeConfirmModal(): void
    {
        $this->showConfirmModal = false;
        $this->confirmActionType = '';
        $this->confirmItemId = null;
    }

    public function executeConfirmAction(): void
    {
        $this->adminOnly();

        if ($this->confirmActionType === 'clean_ghosts') {
            DB::table('ai_analysis_dispatch_states')
                ->whereRaw('LENGTH(dispatch_key) = 32')
                ->whereIn('status', ['queued', 'processing', 'retry_wait'])
                ->update([
                    'status' => 'failed',
                    'error_message' => 'Legacy MD5 ghost record (cleaned manually)',
                    'failure_category' => 'ghost_record',
                    'updated_at' => now(),
                ]);
        } elseif ($this->confirmActionType === 'purge_queue') {
            try {
                Redis::connection('default')->del('queues:ai-analysis');
                DB::table('ai_analysis_dispatch_states')
                    ->whereIn('status', ['queued', 'processing'])
                    ->update([
                        'status' => 'failed',
                        'error_message' => 'Queue purged manually',
                        'updated_at' => now(),
                    ]);
            } catch (\Throwable $e) {
                // Ignore redis connection issues gracefully
            }
        } elseif ($this->confirmActionType === 'clean_apify_ghosts') {
            DB::table('apify_dispatch_states')
                ->whereIn('status', ['queued', 'processing'])
                ->update([
                    'status' => 'failed',
                    'message' => 'Data dibersihkan manual dari UI',
                    'updated_at' => now(),
                ]);
        } elseif ($this->confirmActionType === 'purge_apify_queue') {
            try {
                // Hati-hati karena apify bisa ada di queue default, kita hanya update DB saja
                DB::table('apify_dispatch_states')
                    ->whereIn('status', ['queued', 'processing'])
                    ->update([
                        'status' => 'failed',
                        'message' => 'Antrean Apify dibersihkan secara manual',
                        'updated_at' => now(),
                    ]);
            } catch (\Throwable $e) {
            }
        } elseif ($this->confirmActionType === 'force_apify_requeue' && $this->confirmItemId) {
            $state = DB::table('apify_dispatch_states')->where('id', $this->confirmItemId)->first();
            if ($state) {
                DB::table('apify_dispatch_states')
                    ->where('id', $this->confirmItemId)
                    ->update([
                        'status' => 'queued',
                        'message' => 'Dikirim ulang secara manual',
                        'attempts' => 0,
                        'updated_at' => now(),
                    ]);
            }
        } elseif ($this->confirmActionType === 'force_requeue' && $this->confirmItemId) {
            $state = DB::table('ai_analysis_dispatch_states')->where('id', $this->confirmItemId)->first();
            if ($state) {
                DB::table('ai_analysis_dispatch_states')
                    ->where('id', $this->confirmItemId)
                    ->update([
                        'status' => 'queued',
                        'updated_at' => now(),
                    ]);

                // Basic payload construction
                $payloadType = $state->analyzable_type;
                if (str_contains(strtolower($payloadType), 'social')) {
                    $payloadType = 'social';
                } elseif (str_contains(strtolower($payloadType), 'article')) {
                    $payloadType = 'article';
                }

                $payload = [
                    'type' => $payloadType,
                    'item_id' => $state->analyzable_id,
                    'project_id' => $state->project_id,
                ];

                \App\Jobs\AiAnalysisJob::dispatch(array_merge($payload, [
                    'prompt_template_id' => $state->prompt_template_id,
                    'provider_context_hash' => $state->provider_context_hash,
                ]))->onConnection('redis-ai')->onQueue('ai-analysis');
            }
        }

        $this->closeConfirmModal();
        $this->checkHealth();
    }

    public function getQueueData()
    {
        $query = DB::table('ai_analysis_dispatch_states')
            ->whereIn('status', ['queued', 'processing', 'retry_wait'])
            ->orderBy('id', 'desc');

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterType) {
            $query->where('analyzable_type', $this->filterType);
        }

        if ($this->filterProject) {
            $query->where('project_id', $this->filterProject);
        }

        if ($this->filterActor) {
            $actor = $this->filterActor;
            if (str_contains($actor, ' ')) {
                // Format: 'TikTok Post' / 'TikTok Comment'
                [$platform, $type] = explode(' ', $actor, 2);
                if ($type === 'Post') {
                    $query->where('analyzable_type', 'social')
                          ->whereIn('analyzable_id', function ($sub) use ($platform) {
                              $sub->select('id')->from('social_media_items')->where('platform', $platform);
                          });
                } elseif ($type === 'Comment') {
                    // Menyaring postingan sosial media yang memiliki data komentar terkait
                    // di tabel social_media_comments agar akurat sesuai keadaan fisik database.
                    $query->where('analyzable_type', 'social')
                          ->whereIn('analyzable_id', function ($sub) use ($platform) {
                              $sub->select('social_media_item_id')
                                  ->from('social_media_comments')
                                  ->whereIn('social_media_item_id', function ($inner) use ($platform) {
                                      $inner->select('id')->from('social_media_items')->where('platform', $platform);
                                  });
                          });
                }
            } elseif ($actor === 'Portal Berita') {
                $query->where('analyzable_type', 'article')
                      ->whereNotIn('analyzable_id', function ($sub) {
                          $sub->select('id')->from('articles')->whereIn('source_name', ['TikTok', 'Instagram', 'Facebook']);
                      });
            }
        }

        if ($this->searchQuery) {
            $search = '%' . strtolower($this->searchQuery) . '%';
            $query->where(function($q) use ($search) {
                $q->where('error_message', 'like', $search)
                  ->orWhereIn('analyzable_id', function($sub) use ($search) {
                      $sub->select('id')->from('articles')->where('title', 'like', $search)->orWhere('content', 'like', $search);
                  })
                  ->orWhereIn('analyzable_id', function($sub) use ($search) {
                      $sub->select('id')->from('social_media_items')->where('content', 'like', $search)->orWhere('author_name', 'like', $search);
                  });
            });
        }

        $rawItems = $query->paginate($this->perPage, ['*'], 'queuePage');

        $rawItems->getCollection()->transform(function ($item) {
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
                if (!$article) {
                    $social = DB::table('social_media_items')->where('id', $item->analyzable_id)->first();
                }
            } else {
                $social = DB::table('social_media_items')->where('id', $item->analyzable_id)->first();
                if (!$social) {
                    $article = DB::table('articles')->where('id', $item->analyzable_id)->first();
                }
            }

            $contentUrl = null;
            if ($article) {
                $contentTitle = $article->title ?: ($article->source_name ?: 'Portal News Item');
                $contentDate = $article->published_at ? \Carbon\Carbon::parse($article->published_at)->isoFormat('D MMM YYYY, HH:mm') : '-';
                $contentUrl = $article->url ?: $article->canonical_url;
            } elseif ($social) {
                $text = data_get($social, 'text') ?? data_get($social, 'content');
                $author = data_get($social, 'author') ?? data_get($social, 'author_name');
                $contentTitle = $text ? mb_substr(strip_tags($text), 0, 80) . '...' : ($author ? 'Post dari ' . $author : 'Post Media Sosial');
                
                $postCreatedAt = data_get($social, 'posted_at') ?? (data_get($social, 'post_created_at') ?? data_get($social, 'created_at'));
                $contentDate = $postCreatedAt ? \Carbon\Carbon::parse($postCreatedAt)->isoFormat('D MMM YYYY, HH:mm') : '-';
                $contentUrl = data_get($social, 'post_url') ?? data_get($social, 'url');
            }

            $isActuallySocial = false;
            if ($article && in_array(strtolower($article->source_name ?? ''), ['facebook', 'instagram', 'tiktok'])) {
                $isActuallySocial = true;
            }

            $displayType = ($item->analyzable_type === 'article' && !$isActuallySocial) ? 'Portal Berita' : 'Media Sosial';

            return [
                'id' => $item->id,
                'type' => $displayType,
                'title' => $contentTitle,
                'content_date' => $contentDate,
                'project' => $projectName,
                'status' => $item->status,
                'attempts' => $item->attempts,
                'error_message' => $item->error_message ?: '-',
                'created_at' => $item->created_at ? \Carbon\Carbon::parse($item->created_at)->isoFormat('D MMM YYYY, HH:mm') : '-',
                'url' => $contentUrl,
            ];
        });

        return $rawItems;
    }

    public function openApifyQueueModal(): void
    {
        $this->adminOnly();
        
        $rawItems = DB::table('apify_dispatch_states')
            ->whereIn('status', ['queued', 'processing', 'retry_wait'])
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        $this->apifyQueueDetails = $rawItems->map(function ($item) {
            $projectName = 'N/A';
            if ($item->project_id) {
                $project = DB::table('projects')->where('id', $item->project_id)->first();
                if ($project) {
                    $projectName = $project->name;
                }
            }

            $actorName = 'N/A';
            if ($item->actor_id) {
                $actor = DB::table('apify_actors')->where('id', $item->actor_id)->first();
                if ($actor) {
                    $actorName = $actor->actor_name;
                }
            }

            return [
                'id' => $item->id,
                'run_id' => $item->run_id ?: '-',
                'platform' => $item->platform,
                'actor' => $actorName,
                'keyword' => $item->keyword ?: '-',
                'project' => $projectName,
                'status' => $item->status,
                'attempts' => $item->attempts,
                'error_message' => ($item->last_error_message ?? $item->last_error_code) ?: '-',
                'queued_at' => $item->queued_at ? \Carbon\Carbon::parse($item->queued_at)->isoFormat('D MMM YYYY, HH:mm') : '-',
                'started_at' => $item->started_at ? \Carbon\Carbon::parse($item->started_at)->isoFormat('D MMM YYYY, HH:mm') : '-',
                'completed_at' => $item->completed_at ? \Carbon\Carbon::parse($item->completed_at)->isoFormat('D MMM YYYY, HH:mm') : '-',
            ];
        })->toArray();

        $this->showApifyQueueModal = true;
    }

    public function closeApifyQueueModal(): void
    {
        $this->showApifyQueueModal = false;
        $this->apifyQueueDetails = [];
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
        $projectName = 'N/A';
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

                // Cari project_id dari payload string serialized (mendukung integer dan string)
                if (preg_match('/"project_id";i:(\d+)/', $payloadRaw, $mProj) || preg_match('/"project_id";s:\d+:"([^"]+)"/', $payloadRaw, $mProj)) {
                    $projId = (int) $mProj[1];
                    $project = DB::table('projects')->where('id', $projId)->first();
                    if ($project) {
                        $projectName = $project->name;
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
            'project' => $projectName,
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
