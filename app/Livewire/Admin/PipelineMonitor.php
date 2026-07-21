<?php

namespace App\Livewire\Admin;

use App\Models\AiAnalysisResult;
use App\Models\Article;
use App\Models\RiskNotification;
use App\Models\SocialMediaItem;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use App\Services\ContentMatchingService;
use App\Services\AiAnalysisDispatchStateService;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithoutUrlPagination;

class PipelineMonitor extends Component
{
    use WithPagination, WithoutUrlPagination;

    private const SOCIAL_SOURCE_NAMES = [
        'facebook',
        'instagram',
        'tiktok',
        'twitter',
        'twitter/x',
        'x.com',
        'threads',
        'youtube',
    ];

    public bool $showArticleModal = false;
    public string $viewingArticleTitle = '';
    public string $viewingArticleContent = '';

    public string $activeTab    = 'scraping';
    public string $search       = '';
    public string $filterStatus = '';
    public string $filterPlatform = '';
    public string $filterAiState = '';
    public string $filterRisk   = '';
    public string $filterProject = '';
    public int    $perPage      = 20;

    protected $queryString = [
        'activeTab'      => ['except' => 'scraping'],
        'search'         => ['except' => ''],
        'filterStatus'   => ['except' => ''],
        'filterPlatform' => ['except' => ''],
        'filterAiState'  => ['except' => ''],
        'filterRisk'     => ['except' => ''],
        'filterProject'  => ['except' => ''],
    ];

    public function updatingSearch(): void        { $this->resetPage(); }
    public function updatingFilterStatus(): void  { $this->resetPage(); }
    public function updatingFilterPlatform(): void { $this->resetPage(); }
    public function updatingFilterAiState(): void { $this->resetPage(); }
    public function updatingFilterRisk(): void    { $this->resetPage(); }
    public function updatingFilterProject(): void { $this->resetPage(); }



    public function setTab(string $tab): void
    {
        $this->activeTab    = $tab;
        $this->search       = '';
        $this->filterStatus = '';
        $this->filterPlatform = '';
        $this->filterAiState = '';
        $this->filterRisk   = '';
        $this->filterProject = '';
        $this->resetPage();
    }

    public function retryAiState(int $id): void
    {
        try {
            $state = \App\Models\AiAnalysisDispatchState::withTrashed()->findOrFail($id);

            if ($state->trashed()) {
                $state->restore();
            }

            $state->update([
                'status' => 'queued',
                'attempts' => 0,
                'next_retry_at' => null,
                'error_message' => null,
                'last_error_code' => null,
                'failure_category' => null,
                'last_failed_at' => null,
            ]);

            // Re-dispatch the job so it actually runs
            $analyzable = $state->analyzable;
            if ($analyzable) {
                $payload = [
                    'type' => $state->analyzable_type,
                    'id' => $state->analyzable_id,
                    'title' => $analyzable->title ?? $analyzable->actor_name ?? '',
                    'content' => $analyzable->content ?? $analyzable->text ?? '',
                    'url' => $analyzable->url ?? '',
                    'project_id' => $state->project_id,
                    'prompt_template_id' => $state->prompt_template_id,
                    'provider_context_hash' => $state->provider_context_hash,
                ];
                app(AiAnalysisDispatchStateService::class)->reserveQueuedStateAndDispatch(
                    $payload,
                    $state->prompt_template_id,
                    $state->provider_context_hash
                );
            }

            $payload = ['type' => 'success', 'title' => 'Tugas AI berhasil dikembalikan ke antrean.', 'message' => ''];
            if (method_exists($this, 'dispatchBrowserEvent')) {
                $this->dispatchBrowserEvent('admin-toast', $payload);
            }
            $this->dispatch('admin-toast', payload: $payload);
        } catch (\Throwable $e) {
            $payload = ['type' => 'error', 'title' => 'Gagal retry tugas AI. Silakan cek konfigurasi atau status antrean.', 'message' => ''];
            if (method_exists($this, 'dispatchBrowserEvent')) {
                $this->dispatchBrowserEvent('admin-toast', $payload);
            }
            $this->dispatch('admin-toast', payload: $payload);
        }
    }

    public function deleteAiState(int $id): void
    {
        try {
            \App\Models\AiAnalysisDispatchState::where('id', $id)->delete();
            $payload = ['type' => 'success', 'title' => 'Report item berhasil ditutup.', 'message' => ''];
            if (method_exists($this, 'dispatchBrowserEvent')) {
                $this->dispatchBrowserEvent('admin-toast', $payload);
            }
            $this->dispatch('admin-toast', payload: $payload);
        } catch (\Throwable $e) {
            $payload = ['type' => 'error', 'title' => 'Gagal menutup report item.', 'message' => ''];
            if (method_exists($this, 'dispatchBrowserEvent')) {
                $this->dispatchBrowserEvent('admin-toast', $payload);
            }
            $this->dispatch('admin-toast', payload: $payload);
        }
    }

    public function retryNotification(int $id): void
    {
        try {
            $notification = \App\Models\RiskNotification::with(['aiAnalysisResult.article', 'aiAnalysisResult.socialMediaItem'])->findOrFail($id);
            $analysis = $notification->aiAnalysisResult;
            
            if (!$analysis) {
                $payload = ['type' => 'error', 'title' => 'Hasil analisis tidak ditemukan.', 'message' => ''];
                if (method_exists($this, 'dispatchBrowserEvent')) {
                    $this->dispatchBrowserEvent('admin-toast', $payload);
                }
                $this->dispatch('admin-toast', payload: $payload);
                return;
            }

            // Find project
            $projectId = $analysis->article
                ? $this->resolveProjectIdFromArticle($analysis->article)
                : $this->resolveProjectIdFromSocialItem($analysis->socialMediaItem);
            $projectName = \App\Models\Project::find($projectId)?->name ?? 'N/A';

            $title = $analysis->article ? $analysis->article->title : ($analysis->socialMediaItem ? 'Post dari ' . $analysis->socialMediaItem->platform . ' oleh ' . $analysis->socialMediaItem->author_name : 'Postingan');
            $url = $analysis->article ? $analysis->article->url : ($analysis->socialMediaItem ? $analysis->socialMediaItem->post_url : '');
            $sourceName = $analysis->article ? $analysis->article->source_name : ($analysis->socialMediaItem ? $analysis->socialMediaItem->platform : 'Google News');

            $notification->update([
                'status' => 'pending',
                'error_message' => null,
            ]);

            \App\Jobs\TelegramNotificationJob::dispatch([
                'ai_analysis_result_id' => $analysis->id,
                'project_id' => $projectId,
                'project_name' => $projectName,
                'title' => $title,
                'url' => $url,
                'source_name' => $sourceName,
                'risk_level' => $analysis->risk_level,
                'reach_level' => $analysis->reach_level,
                'sentiment' => $analysis->sentiment,
                'summary' => $analysis->summary,
                'reason' => $analysis->risk_reason,
            ])->delay(now()->addMinute())->onQueue('notification');

            $payload = ['type' => 'success', 'title' => 'Notifikasi berhasil dikirim ulang.', 'message' => ''];
            if (method_exists($this, 'dispatchBrowserEvent')) {
                $this->dispatchBrowserEvent('admin-toast', $payload);
            }
            $this->dispatch('admin-toast', payload: $payload);
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim ulang notifikasi: ' . $e->getMessage());
            $payload = ['type' => 'error', 'title' => 'Gagal mengirim ulang notifikasi.', 'message' => ''];
            if (method_exists($this, 'dispatchBrowserEvent')) {
                $this->dispatchBrowserEvent('admin-toast', $payload);
            }
            $this->dispatch('admin-toast', payload: $payload);
        }
    }

    public function deleteNotification(int $id): void
    {
        try {
            \App\Models\RiskNotification::where('id', $id)->delete();
            $payload = ['type' => 'success', 'title' => 'Notifikasi berhasil dihapus.', 'message' => ''];
            if (method_exists($this, 'dispatchBrowserEvent')) {
                $this->dispatchBrowserEvent('admin-toast', $payload);
            }
            $this->dispatch('admin-toast', payload: $payload);
        } catch (\Throwable $e) {
            $payload = ['type' => 'error', 'title' => 'Gagal menghapus notifikasi.', 'message' => ''];
            if (method_exists($this, 'dispatchBrowserEvent')) {
                $this->dispatchBrowserEvent('admin-toast', $payload);
            }
            $this->dispatch('admin-toast', payload: $payload);
        }
    }

    public function clearAllPendingAiStates(): void
    {
        try {
            $queuedCount = \App\Models\AiAnalysisDispatchState::where('status', 'queued')->delete();
            $retryCount = \App\Models\AiAnalysisDispatchState::query()
                ->where('status', 'retry_wait')
                ->where(function ($query) {
                    $query->whereNull('next_retry_at')
                        ->orWhere('next_retry_at', '<=', now()->subMinutes(5));
                })
                ->delete();

            $payload = ['type' => 'success', 'title' => "Berhasil menutup {$queuedCount} antrean queued dan {$retryCount} retry yang sudah lewat tenggat.", 'message' => ''];
            if (method_exists($this, 'dispatchBrowserEvent')) {
                $this->dispatchBrowserEvent('admin-toast', $payload);
            }
            $this->dispatch('admin-toast', payload: $payload);
        } catch (\Throwable $e) {
            $payload = ['type' => 'error', 'title' => 'Gagal menutup semua tugas AI.', 'message' => ''];
            if (method_exists($this, 'dispatchBrowserEvent')) {
                $this->dispatchBrowserEvent('admin-toast', $payload);
            }
            $this->dispatch('admin-toast', payload: $payload);
        }
    }

    public function getSummaryStats(): array
    {
        $pendingJobs  = DB::table('jobs')->count();

        // Artikel dari portal berita (Global count)
        $globalArticles = $this->portalArticleBaseQuery()->count();
        // Artikel unik yang cocok dengan setidaknya satu project aktif
        $portalItems = 0;
        $activeProjects = Project::where('is_active', true)->get();
        $this->portalArticleBaseQuery()
            ->select(['id', 'title', 'content'])
            ->chunkById(250, function ($articles) use (&$portalItems, $activeProjects) {
                foreach ($articles as $article) {
                    $content = trim(($article->title ?? '') . "\n" . ($article->content ?? ''));
                    foreach ($activeProjects as $project) {
                        if ($this->projectKeywordsMatch($project, $content)) {
                            $portalItems++;
                            break;
                        }
                    }
                }
            });

        // Social media items
        $globalSocial = SocialMediaItem::count();
        $socialItems  = SocialMediaItem::count();

        // AI dispatch state breakdown
        $dispatchStates = DB::table('ai_analysis_dispatch_states')
            ->select('status', DB::raw('count(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $aiQueued    = $dispatchStates['queued'] ?? 0;
        $aiRetry     = $dispatchStates['retry_wait'] ?? 0;
        $aiProcessing = $dispatchStates['processing'] ?? 0;
        $failedJobs  = DB::table('ai_analysis_dispatch_states')
            ->where('status', 'failed')
            ->whereNotIn('last_error_code', ['orphan_dispatch_state', 'empty_content', 'invalid_content', 'stale_orphan'])
            ->count();
        $skippedJobs = DB::table('ai_analysis_dispatch_states')
            ->where('status', 'failed')
            ->whereIn('last_error_code', ['orphan_dispatch_state', 'empty_content', 'invalid_content', 'stale_orphan'])
            ->count();
        $aiPending   = $aiQueued + $aiRetry + $aiProcessing;

        $aiTotal     = AiAnalysisResult::count();
        $aiSuccess   = AiAnalysisResult::where('analysis_status', 'success')->count();
        $aiFailed    = AiAnalysisResult::where('analysis_status', 'failed')->count();
        $notifTotal  = RiskNotification::count();
        $notifSent   = RiskNotification::where('status', 'sent')->count();
        $notifFailed = RiskNotification::where('status', 'failed')->count();
        $highRisk    = AiAnalysisResult::where('risk_level', 'high')->count();

        // ── Backfill Stats ──
        $backfillCandidates = DB::table('ai_analysis_results')
            ->join('articles', 'ai_analysis_results.article_id', '=', 'articles.id')
            ->where('ai_analysis_results.analysis_status', 'success')
            ->where('ai_analysis_results.reach_method', 'ai_reader_estimate_v1')
            ->whereNotNull('ai_analysis_results.article_id')
            ->where('articles.url', 'not ilike', '%google.com%')
            ->whereNotNull('articles.content')
            ->whereRaw('LENGTH(articles.content) > 100')
            ->where(function ($q) {
                $q->whereNull('ai_analysis_results.project_estimated_readers')
                    ->orWhereNull('ai_analysis_results.project_reach_score')
                    ->orWhereNull('ai_analysis_results.project_reach_level')
                    ->orWhereNull('ai_analysis_results.project_reach_band');
            })
            ->count('ai_analysis_results.id');

        $backfillQueue = 0;
        try {
            $redisQueue = \Illuminate\Support\Facades\Queue::connection('redis-ai');
            if (method_exists($redisQueue, 'size')) {
                $backfillQueue = $redisQueue->size('ai-backfill');
            }
        } catch (\Throwable $e) {}

        $scrapingSettings = DB::table('scraping_settings')->first();
        $isSchedulerActive = (bool) ($scrapingSettings->is_active ?? true);

        // Recent success
        $recentSuccess = DB::table('ai_analysis_results')
            ->where('reach_method', 'ai_reader_estimate_v1')
            ->whereNotNull('project_estimated_readers')
            ->where('updated_at', '>=', now()->subMinutes(15))
            ->count();

        // Cooldowns
        $cooldownProviders = \App\Models\AiProvider::whereNotNull('cooldown_until')
            ->where('cooldown_until', '>', now())
            ->get(['name', 'cooldown_until', 'last_failure_code']);

        $lastExecutionTime = null;
        $logPath = storage_path('logs/ai-backfill-scheduler.log');
        if (file_exists($logPath)) {
            $lastExecutionTime = \Carbon\Carbon::createFromTimestamp(filemtime($logPath))->diffForHumans();
        }

        $backfillStats = [
            'candidates' => $backfillCandidates,
            'queue' => $backfillQueue,
            'scheduler_active' => $isSchedulerActive,
            'batch_size' => 10,
            'interval' => '5 menit',
            'last_execution' => $lastExecutionTime ?? 'Belum pernah',
            'recent_success' => $recentSuccess,
            'cooldown_providers' => $cooldownProviders,
        ];

        return compact(
            'pendingJobs', 'failedJobs', 'globalArticles', 'portalItems', 'globalSocial', 'socialItems',
            'aiTotal', 'aiSuccess', 'aiFailed', 'aiPending', 'aiQueued', 'aiRetry', 'aiProcessing',
            'notifTotal', 'notifSent', 'notifFailed', 'highRisk', 'backfillStats', 'skippedJobs'
        );
    }

    /**
     * Tab Scraping: artikel portal yang tampil berdasarkan filter project aktif.
     */
    public function getScrapingItems()
    {
        $query = $this->portalArticleBaseQuery()
            ->with(['aiAnalysisResult']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('articles.title', 'ilike', "%{$this->search}%")
                  ->orWhere('articles.source_name', 'ilike', "%{$this->search}%")
                  ->orWhere('articles.content', 'ilike', "%{$this->search}%");
            });
        }

            if ($this->filterProject) {
                $project = Project::find($this->filterProject);
                if ($project) {
                $primaryKeywords = $project->scrapeKeywordVariants();
                $contextKeywords = $project->scrapeContextKeywordVariants();
                $keywords = array_values(array_unique(array_filter(array_merge($primaryKeywords, $contextKeywords))));
                $excludeKeywords = $project->scrapeExcludeKeywords();

                $query->where(function ($contentQuery) use ($keywords) {
                    foreach ($keywords as $index => $keyword) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                                $contentQuery->{$method}(function ($inner) use ($keyword) {
                                $inner->where('articles.title', 'ilike', '%' . $keyword . '%')
                                    ->orWhere('articles.content', 'ilike', '%' . $keyword . '%')
                                    ->orWhere('articles.excerpt', 'ilike', '%' . $keyword . '%')
                                    ->orWhere('ai.summary', 'ilike', '%' . $keyword . '%');
                            });
                        }
                    });

                foreach ($excludeKeywords as $keyword) {
                    $query->where(function ($inner) use ($keyword) {
                        $inner->whereRaw('LOWER(COALESCE(articles.title, \'\')) NOT LIKE ?', ['%' . strtolower($keyword) . '%'])
                            ->whereRaw('LOWER(COALESCE(articles.content, \'\')) NOT LIKE ?', ['%' . strtolower($keyword) . '%']);
                    });
                }
            }
        }

        if ($this->filterPlatform) {
            $query->where('articles.source_name', $this->filterPlatform);
        }

        if ($this->filterAiState === 'success') {
            $query->whereHas('aiAnalysisResult', function ($q) {
                $q->where('analysis_status', 'success');
            });
        } elseif ($this->filterAiState === 'failed') {
            $query->whereHas('aiAnalysisResult', function ($q) {
                $q->where('analysis_status', 'failed');
            });
        } elseif ($this->filterAiState === 'pending') {
            $query->where(function ($q) {
                $q->whereDoesntHave('aiAnalysisResult')
                  ->orWhereIn('articles.id', function ($sub) {
                      $sub->select('analyzable_id')
                          ->from('ai_analysis_dispatch_states')
                          ->where('analyzable_type', 'article')
                          ->whereIn('status', ['queued', 'retry_wait', 'processing']);
                  });
            });
        }

        return $query->orderByDesc('articles.published_at')->paginate($this->perPage);
    }

    /**
     * Tab Social: Social media items (dari Apify scraper)
     */
    public function getSocialItems()
    {
        $query = SocialMediaItem::query()
            ->select(
                'social_media_items.id',
                'social_media_items.platform',
                'social_media_items.post_url',
                'social_media_items.author_name',
                'social_media_items.author_url',
                'social_media_items.content',
                'social_media_items.posted_at',
                'social_media_items.like_count',
                'social_media_items.comment_count',
                'social_media_items.share_count',
                'social_media_items.view_count',
                'social_media_items.follower_count',
                'social_media_items.created_at',
                'social_media_items.updated_at'
            )
            ->with(['projects', 'aiAnalysisResult']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('content', 'ilike', "%{$this->search}%")
                  ->orWhere('author_name', 'ilike', "%{$this->search}%");
            });
        }

        if ($this->filterPlatform) {
            $query->where('platform', $this->filterPlatform);
        }

        if ($this->filterProject) {
            $project = Project::find($this->filterProject);
            if ($project) {
                $query->where(function ($q) use ($project) {
                    $q->where(function ($contentQuery) use ($project) {
                        $primaryKeywords = $project->scrapeKeywordVariants();
                        $contextKeywords = $project->scrapeContextKeywordVariants();
                        $matchKeywords = array_values(array_unique(array_filter(array_merge($primaryKeywords, $contextKeywords))));
                        foreach ($matchKeywords as $index => $keyword) {
                            $method = $index === 0 ? 'where' : 'orWhere';
                            $contentQuery->{$method}(function ($inner) use ($keyword) {
                                $inner->where('content', 'ilike', '%' . $keyword . '%')
                                    ->orWhere('raw_json', 'ilike', '%' . $keyword . '%')
                                    ->orWhere('author_name', 'ilike', '%' . $keyword . '%');
                            });
                        }
                    });
                });
            }
        }

        if ($this->filterAiState === 'success') {
            $query->whereHas('aiAnalysisResult', function ($q) {
                $q->where('analysis_status', 'success');
            });
        } elseif ($this->filterAiState === 'failed') {
            $query->whereHas('aiAnalysisResult', function ($q) {
                $q->where('analysis_status', 'failed');
            });
        } elseif ($this->filterAiState === 'pending') {
            $query->where(function ($q) {
                $q->whereDoesntHave('aiAnalysisResult')
                  ->orWhereIn('social_media_items.id', function ($sub) {
                      $sub->select('analyzable_id')
                          ->from('ai_analysis_dispatch_states')
                          ->where('analyzable_type', 'social')
                          ->whereIn('status', ['queued', 'retry_wait', 'processing']);
                  });
            });
        }

        return $query->latest()->paginate($this->perPage);
    }

    /**
     * Tab AI: Hasil analisis AI
     */
    public function getAiItems()
    {
        $query = AiAnalysisResult::query()
            ->select('ai_analysis_results.*')
            ->with(['article', 'socialMediaItem']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('ai.summary', 'ilike', "%{$this->search}%")
                  ->orWhere('ai_analysis_results.main_issue', 'ilike', "%{$this->search}%")
                  ->orWhere('ai_analysis_results.risk_reason', 'ilike', "%{$this->search}%")
                  ->orWhereHas('article', function($sq) {
                      $sq->where('title', 'ilike', "%{$this->search}%")
                         ->orWhere('content', 'ilike', "%{$this->search}%");
                  })
                  ->orWhereHas('socialMediaItem', function($sq) {
                      $sq->where('content', 'ilike', "%{$this->search}%")
                         ->orWhere('author_name', 'ilike', "%{$this->search}%");
                  });
            });
        }

        if ($this->filterStatus) {
            $query->where('ai_analysis_results.analysis_status', $this->filterStatus);
        }

        if ($this->filterRisk) {
            $query->where('ai_analysis_results.risk_level', $this->filterRisk);
        }

        if ($this->filterProject) {
            $project = Project::find($this->filterProject);
            if ($project) {
                $query->where(function ($q) use ($project) {
                    $q->whereHas('article', function ($sq) use ($project) {
                        $sq->where(function ($contentQuery) use ($project) {
                            $primaryKeywords = $project->scrapeKeywordVariants();
                            $contextKeywords = $project->scrapeContextKeywordVariants();
                            $matchKeywords = array_values(array_unique(array_filter(array_merge($primaryKeywords, $contextKeywords))));
                            foreach ($matchKeywords as $index => $keyword) {
                                $method = $index === 0 ? 'where' : 'orWhere';
                                $contentQuery->{$method}(function ($inner) use ($keyword) {
                                $inner->where('title', 'ilike', '%' . $keyword . '%')
                                    ->orWhere('content', 'ilike', '%' . $keyword . '%')
                                    ->orWhere('excerpt', 'ilike', '%' . $keyword . '%')
                                    ->orWhere('ai.summary', 'ilike', '%' . $keyword . '%');
                            });
                        }
                    });
                    })
                    ->orWhereHas('socialMediaItem', function ($sq) use ($project) {
                        $sq->where(function ($contentQuery) use ($project) {
                            $primaryKeywords = $project->scrapeKeywordVariants();
                            $contextKeywords = $project->scrapeContextKeywordVariants();
                            $matchKeywords = array_values(array_unique(array_filter(array_merge($primaryKeywords, $contextKeywords))));
                            foreach ($matchKeywords as $index => $keyword) {
                                $method = $index === 0 ? 'where' : 'orWhere';
                                $contentQuery->{$method}(function ($inner) use ($keyword) {
                                    $inner->where('content', 'ilike', '%' . $keyword . '%')
                                        ->orWhere('raw_json', 'ilike', '%' . $keyword . '%')
                                        ->orWhere('author_name', 'ilike', '%' . $keyword . '%');
                                });
                            }
                        });
                    });
                });
            }
        }

        return $query->orderByDesc('ai_analysis_results.created_at')->paginate($this->perPage);
    }

    /**
     * Tab Notifikasi
     */
    public function getNotificationItems()
    {
        $query = RiskNotification::query()
            ->with(['aiAnalysisResult.article']);

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->search) {
            $query->where('error_message', 'ilike', "%{$this->search}%");
        }

        return $query->latest()->paginate($this->perPage);
    }

    public function getFailedJobs()
    {
        $query = \App\Models\AiAnalysisDispatchState::query()
            ->with('project')
            ->where('status', 'failed')
            ->whereNotIn('last_error_code', ['orphan_dispatch_state', 'empty_content', 'invalid_content', 'stale_orphan'])
            ->orderByDesc('updated_at');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('error_message', 'ilike', "%{$this->search}%")
                  ->orWhere('last_error_code', 'ilike', "%{$this->search}%")
                  ->orWhere('failure_category', 'ilike', "%{$this->search}%")
                  ->orWhere('analyzable_id', (int)$this->search)
                  ->orWhereHas('project', function ($p) {
                      $p->where('name', 'ilike', "%{$this->search}%");
                  });
            });
        }

        return $query->paginate($this->perPage);
    }

    public function getPendingJobs()
    {
        $query = \App\Models\AiAnalysisDispatchState::query()
            ->with('project')
            ->whereIn('status', ['queued', 'retry_wait', 'processing'])
            ->orderByDesc('updated_at');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('error_message', 'ilike', "%{$this->search}%")
                  ->orWhere('last_error_code', 'ilike', "%{$this->search}%")
                  ->orWhere('failure_category', 'ilike', "%{$this->search}%")
                  ->orWhere('status', 'ilike', "%{$this->search}%")
                  ->orWhere('analyzable_id', (int)$this->search)
                  ->orWhereHas('project', function ($p) {
                      $p->where('name', 'ilike', "%{$this->search}%");
                  });
            });
        }

        return $query->paginate($this->perPage);
    }

    public function getProjects()
    {
        return Project::orderBy('name')->get();
    }

    public function getSources(): array
    {
        return $this->portalArticleBaseQuery()
            ->distinct()
            ->pluck('articles.source_name')
            ->filter()
            ->sort()
            ->values()
            ->toArray();
    }

    protected function resolveProjectIdFromArticle(?Article $article): ?int
    {
        if (! $article) {
            return null;
        }

        $content = trim(($article->title ?? '') . "\n" . ($article->content ?? ''));

        foreach (Project::where('is_active', true)->get() as $project) {
            if ($this->projectKeywordsMatch($project, $content)) {
                return $project->id;
            }
        }

        return null;
    }

    protected function resolveProjectIdFromSocialItem(?SocialMediaItem $item): ?int
    {
        if (! $item) {
            return null;
        }

        $content = $this->buildSocialMatchText(
            $item->content ?? null,
            $item->raw_json ?? null,
        );

        foreach (Project::where('is_active', true)->get() as $project) {
            if ($this->projectKeywordsMatch($project, $content)) {
                return $project->id;
            }
        }

        return null;
    }

    protected function projectKeywordsMatch(Project $project, string $content): bool
    {
        $primaryKeywords = $project->scrapeKeywordVariants();
        $contextKeywords = $project->scrapeContextKeywordVariants();
        $matchKeywords = array_values(array_unique(array_filter(array_merge($primaryKeywords, $contextKeywords))));
        $excludeKeywords = $project->scrapeExcludeKeywords();

        if ($matchKeywords === []) {
            return false;
        }

        foreach ($excludeKeywords as $keyword) {
            if ($this->matchesText($keyword, $content)) {
                return false;
            }
        }

        foreach ($matchKeywords as $keyword) {
            if ($this->matchesText($keyword, $content)) {
                return true;
            }
        }

        return false;
    }

    protected function matchesText(string $keyword, string $text): bool
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return false;
        }

        $keyword = preg_replace('/[’‘`´]/u', "'", $keyword);
        $text = preg_replace('/[’‘`´]/u', "'", $text);
        $keyword = preg_replace('/\s+/u', ' ', $keyword);
        $escapedKeyword = preg_quote($keyword, '/');
        $pattern = '/(?<![\p{L}\p{N}])' . $escapedKeyword . '(?![\p{L}\p{N}])/iu';

        return preg_match($pattern, $text) === 1;
    }

    protected function portalArticleBaseQuery()
    {
        return Article::query()
            ->where(function ($query) {
                $query->whereNull('articles.source_name')
                    ->orWhereRaw(
                        'LOWER(TRIM(articles.source_name)) NOT IN (' . implode(',', array_fill(0, count(self::SOCIAL_SOURCE_NAMES), '?')) . ')',
                        self::SOCIAL_SOURCE_NAMES
                    );
            });
    }

    public function getPlatforms(): array
    {
        return SocialMediaItem::distinct()->pluck('platform')->filter()->values()->toArray();
    }

    public function getHealthStatusStats(): array
    {
        $lastScrapingRunItem = \App\Models\ScrapingItem::latest('updated_at')->first();
        $lastScrapingRun = $lastScrapingRunItem ? $lastScrapingRunItem->updated_at->diffForHumans() : 'Belum Pernah';
        
        $lastArticle = Article::latest('created_at')->first();
        $lastArticleInserted = $lastArticle ? $lastArticle->title . ' (' . $lastArticle->created_at->diffForHumans() . ')' : 'Belum Ada';

        $scrapingInserted = Article::count();
        $scrapingReused = max(0, DB::table('candidate_links')->where('status', 'approved')->count() - $scrapingInserted);
        $scrapingRejected = DB::table('candidate_links')->where('status', 'rejected')->count();
        $scrapingPartial = DB::table('candidate_links')->where('status', 'partial')->count();
        $scrapingError = \App\Models\ScrapingItem::whereNotNull('error_message')
            ->whereNotIn('status', ['rejected', 'partial'])
            ->count();

        $mutexCount = DB::table('cache')->where('key', 'like', '%framework/schedule%')->count();
        $mutexStatus = $mutexCount > 0 ? "Locked ({$mutexCount} locks active)" : "Normal";

        $lastMemoryExhausted = 'Tidak Terdeteksi';
        $logPath = storage_path('logs/laravel.log');
        if (file_exists($logPath) && is_readable($logPath)) {
            $handle = fopen($logPath, 'r');
            if ($handle) {
                fseek($handle, -4096, SEEK_END);
                $buffer = fread($handle, 4096);
                fclose($handle);
                if (str_contains($buffer, 'Allowed memory size of') || str_contains($buffer, 'Memory exhausted')) {
                    $lines = explode("\n", $buffer);
                    foreach (array_reverse($lines) as $line) {
                        if (str_contains($line, 'Allowed memory size of') || str_contains($line, 'Memory exhausted')) {
                            if (preg_match('/^\[([^\]]+)\]/', $line, $matches)) {
                                $lastMemoryExhausted = $matches[1];
                                break;
                            }
                        }
                    }
                }
            }
        }

        $heartbeat = \Illuminate\Support\Facades\Cache::get('scheduler_heartbeat');
        $diff = $heartbeat ? (now()->timestamp - $heartbeat) : null;
        $schedulerActive = $diff !== null && $diff < 180;

        $scrapingSettings = DB::table('scraping_settings')->first();
        $scrapingRunNewsActive = (bool) ($scrapingSettings->is_active ?? true);

        $aiAnalysisQueue = 0;
        $aiBackfillQueue = 0;
        try {
            $redis = \Illuminate\Support\Facades\Redis::connection();
            $aiAnalysisQueue = $redis->llen('queues:ai-analysis');
            $aiBackfillQueue = $redis->llen('queues:ai-backfill');
        } catch (\Throwable $e) {
            $aiAnalysisQueue = DB::table('ai_analysis_dispatch_states')->where('status', 'queued')->count();
        }

        $failedJobsCount = DB::table('failed_jobs')->count();

        $providers = \App\Models\AiProvider::orderBy('priority', 'asc')->get()->map(function($p) {
            $isBackup3 = str_contains(strtolower($p->name), 'backup3') || str_contains(strtolower($p->name), 'backup - 3') || str_contains(strtolower($p->name), 'backup-3') || str_contains(strtolower($p->name), 'backup 3');
            $eligible = $p->is_active && ($p->cooldown_until === null || \Carbon\Carbon::parse($p->cooldown_until)->isPast());
            return [
                'name' => $p->name,
                'active' => $p->is_active,
                'cooldown_until' => $p->cooldown_until,
                'last_failure_code' => $p->last_failure_code,
                'is_backup3' => $isBackup3,
                'eligible' => $eligible,
            ];
        });

        $lastHealthCheck = \App\Models\AiProvider::max('last_tested_at');
        $lastHealthCheckTime = $lastHealthCheck ? \Carbon\Carbon::parse($lastHealthCheck)->diffForHumans() : 'Belum Pernah';

        $backfillCandidates = DB::table('ai_analysis_results')
            ->join('articles', 'ai_analysis_results.article_id', '=', 'articles.id')
            ->where('ai_analysis_results.analysis_status', 'success')
            ->where('ai_analysis_results.reach_method', 'ai_reader_estimate_v1')
            ->whereNull('ai_analysis_results.project_estimated_readers')
            ->whereNotNull('ai_analysis_results.article_id')
            ->where('articles.url', 'not ilike', '%google.com%')
            ->whereNotNull('articles.content')
            ->whereRaw('LENGTH(articles.content) > 100')
            ->count();

        $delayedBackfill = DB::table('jobs')->where('queue', 'ai-backfill')->count();
        
        $lastBackfillSuccessVal = \App\Models\AiAnalysisResult::where('reach_method', 'ai_reader_estimate_v1')
            ->whereNotNull('project_estimated_readers')
            ->latest('updated_at')
            ->value('updated_at');
        $lastBackfillSuccess = $lastBackfillSuccessVal ? \Carbon\Carbon::parse($lastBackfillSuccessVal)->diffForHumans() : 'Belum Ada';

        $latestNotifications = RiskNotification::latest()->limit(5)->get()->map(function($n) {
            return [
                'title' => $n->aiAnalysisResult->article->title ?? 'N/A',
                'status' => $n->status,
                'updated_at' => $n->updated_at->diffForHumans(),
                'error_message' => $n->error_message,
            ];
        });

        $teleSetting = \App\Models\TelegramSetting::first();
        $telegramActive = (bool) ($teleSetting?->is_active ?? false);
        $telegramRecipientCount = \App\Models\ProjectTelegramRecipient::where('is_active', true)->count();

        // Breakdown Belum Siap Tampil (Pending)
        $pendingNoAi = Article::query()
            ->whereDoesntHave('aiAnalysisResult')
            ->count();

        $pendingBackfillReach = DB::table('ai_analysis_results')
            ->join('articles', 'ai_analysis_results.article_id', '=', 'articles.id')
            ->where('ai_analysis_results.analysis_status', 'success')
            ->where('ai_analysis_results.reach_method', 'ai_reader_estimate_v1')
            ->where('articles.url', 'not ilike', '%google.com%')
            ->where(function ($q) {
                $q->whereNull('ai_analysis_results.project_estimated_readers')
                    ->orWhereNull('ai_analysis_results.project_reach_score')
                    ->orWhereNull('ai_analysis_results.project_reach_level')
                    ->orWhereNull('ai_analysis_results.project_reach_band');
            })
            ->count();

        $pendingRetryWait = DB::table('ai_analysis_dispatch_states')
            ->where('status', 'retry_wait')
            ->count();

        $pendingLegacy = DB::table('ai_analysis_results')
            ->where('analysis_status', 'success')
            ->where(function($q) {
                $q->whereNull('reach_method')
                  ->orWhere('reach_method', '!=', 'ai_reader_estimate_v1');
            })
            ->count();

        $pendingInvalidReach = DB::table('ai_analysis_results')
            ->where('analysis_status', 'invalid_ai_reach')
            ->count();

        $pendingZeroReaders = DB::table('ai_analysis_results')
            ->where('analysis_status', 'success')
            ->where(function ($q) {
                $q->where('project_estimated_readers', 0)
                    ->orWhereNull('project_reach_score')
                    ->orWhereNull('project_reach_level')
                    ->orWhereNull('project_reach_band');
            })
            ->count();

        // Scan log file for failover metrics since recreate (2026-07-09 04:33:45)
        $recreateTime = \Carbon\Carbon::parse('2026-07-09 04:33:45');
        $lastFailoverEvent = 'Tidak Ada';
        $lastBackup3Success = 'Tidak Ada';
        $lastRateLimitEvent = 'Tidak Ada';
        $failoverStatus = 'NOT VERIFIED';

        $logPath = storage_path('logs/laravel.log');
        if (file_exists($logPath) && is_readable($logPath)) {
            $logContent = file_get_contents($logPath);
            preg_match_all('/\[(2026-07-09 \d{2}:\d{2}:\d{2})\]\s+local\.(?:INFO|WARNING|ERROR):\s+\[AiRouter\]\s+(.*)/i', $logContent, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                $time = \Carbon\Carbon::parse($match[1]);
                if ($time->greaterThanOrEqualTo($recreateTime)) {
                    $message = $match[2];
                    
                    if (str_contains($message, 'rate_limit') || str_contains($message, 'quota')) {
                        $lastRateLimitEvent = $time->diffForHumans();
                    }
                    
                    if (str_contains($message, 'Trying provider') && str_contains($message, 'Fallback:')) {
                        $lastFailoverEvent = $time->diffForHumans();
                    }
                    
                    if (str_contains($message, 'Success using provider') && str_contains($message, 'Backup3')) {
                        $lastBackup3Success = $time->diffForHumans();
                        $failoverStatus = 'VERIFIED';
                    }
                }
            }
        }

        return compact(
            'lastScrapingRun', 'lastArticleInserted', 'scrapingInserted', 'scrapingReused',
            'scrapingRejected', 'scrapingPartial', 'scrapingError', 'mutexStatus', 'lastMemoryExhausted',
            'schedulerActive', 'scrapingRunNewsActive', 'aiAnalysisQueue', 'aiBackfillQueue', 'failedJobsCount',
            'providers', 'lastHealthCheckTime', 'backfillCandidates', 'delayedBackfill', 'lastBackfillSuccess',
            'latestNotifications', 'telegramActive', 'telegramRecipientCount',
            'pendingNoAi', 'pendingBackfillReach', 'pendingRetryWait', 'pendingLegacy', 'pendingInvalidReach', 'pendingZeroReaders',
            'lastFailoverEvent', 'lastBackup3Success', 'lastRateLimitEvent', 'failoverStatus'
        );
    }

    public function render()
    {
        $stats    = $this->getSummaryStats();
        $projects = $this->getProjects();
        $sources  = $this->getSources();
        $platforms = $this->getPlatforms();

        $viewData = array_merge($stats, [
            'projects'  => $projects,
            'sources'   => $sources,
            'platforms' => $platforms,
        ]);

        $items = match ($this->activeTab) {
            'scraping'      => $this->getScrapingItems(),
            'social'        => $this->getSocialItems(),
            'ai'            => $this->getAiItems(),
            'notifications' => $this->getNotificationItems(),
            'queue-pending' => $this->getPendingJobs(),
            'queue-failed'  => $this->getFailedJobs(),
            'health'        => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
            default         => $this->getScrapingItems(),
        };

        $healthStats = [];
        if ($this->activeTab === 'health') {
            $healthStats = $this->getHealthStatusStats();
        }

        return view('livewire.admin.pipeline-monitor', array_merge($viewData, [
            'items' => $items,
            'healthStats' => $healthStats
        ]));
    }

    public function viewArticle(string $type, int $id): void
    {
        if ($type === 'article') {
            $model = Article::find($id);
            if ($model) {
                $this->viewingArticleTitle = $model->title ?? 'Tanpa Judul';
                $this->viewingArticleContent = $model->content ?? '';
            }
        } elseif ($type === 'social') {
            $model = SocialMediaItem::find($id);
            if ($model) {
                $this->viewingArticleTitle = 'Social Media: ' . ucfirst($model->platform);
                $this->viewingArticleContent = $model->content ?? '';
            }
        }
        $this->showArticleModal = true;
    }

    public function closeArticleModal(): void
    {
        $this->showArticleModal = false;
        $this->viewingArticleTitle = '';
        $this->viewingArticleContent = '';
    }

    public function failureCategoryLabel(?string $category): string
    {
        return match ($category) {
            'non_retryable_orphan' => 'Closed / Stale Dispatch',
            'timeout' => 'Timeout',
            'rate_limit' => 'Rate Limit',
            'authentication_error' => 'Authentication Error',
            'model_not_found' => 'Model Not Found',
            'provider_unavailable' => 'Provider Unavailable',
            'network_error' => 'Network Error',
            'invalid_json' => 'Invalid JSON',
            'invalid_ai_reach' => 'Invalid AI Reach',
            'invalid_content' => 'Invalid Content',
            'configuration_error' => 'Configuration Error',
            'database_error' => 'Database Error',
            default => 'Unknown Error',
        };
    }

    public function failureCodeLabel(?string $code): string
    {
        return match ($code) {
            'orphan_dispatch_state' => 'Closed / Stale Dispatch',
            'empty_content' => 'Skipped / Invalid Content',
            'invalid_content' => 'Skipped / Invalid Content',
            'rate_limit' => 'Retryable / Rate Limit',
            'provider_unavailable' => 'Retryable / Provider Unavailable',
            'timeout' => 'Retryable / Timeout',
            'temporary_provider_error' => 'Retryable / Temporary Provider Error',
            'analysis_failed' => 'Retryable / Analysis Failed',
            'invalid_ai_reach' => 'Non-retryable / Invalid AI Reach',
            default => 'Unknown Error',
        };
    }

    public function failureCodeDescription(?string $code): string
    {
        return match ($code) {
            'orphan_dispatch_state' => 'Dispatch state closed because target article/social is missing.',
            'empty_content', 'invalid_content' => 'Content is too short or empty for AI analysis.',
            'rate_limit' => 'AI provider is rate-limited and can be retried later.',
            'provider_unavailable' => 'Provider is temporarily unavailable and can be retried later.',
            'timeout' => 'AI request timed out and can be retried later.',
            'temporary_provider_error' => 'Temporary provider error can be retried later.',
            'analysis_failed' => 'Retry only when this failure is classified as retryable.',
            'invalid_ai_reach' => 'AI reach output failed validation and was not marked as official.',
            default => 'Non-retryable or unclassified state.',
        };
    }

    public function isRetryableFailure(?string $code, ?string $category): bool
    {
        return in_array($code, [
            'rate_limit',
            'provider_unavailable',
            'timeout',
            'temporary_provider_error',
        ], true) || ($code === 'analysis_failed' && in_array($category, [
            'rate_limit',
            'provider_unavailable',
            'timeout',
            'database_error',
        ], true));
    }

    public function isNonRetryableStale(?string $code): bool
    {
        return in_array($code, ['orphan_dispatch_state', 'empty_content', 'invalid_content'], true);
    }
}
