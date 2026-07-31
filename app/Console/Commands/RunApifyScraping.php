<?php

namespace App\Console\Commands;

use App\Jobs\ApifyScrapingJob;
use App\Models\ApifyActor;
use App\Models\ApifySetting;
use App\Models\Project;
use App\Models\ScrapingSetting;
use App\Models\ApifyActor as ApifyActorModel;
use App\Services\ApifyActorRegistry;
use App\Services\SchedulerQueueGuard;
use App\Services\SocialProjectScrapePriorityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RunApifyScraping extends Command
{
    private const ACTOR_RECOVERY_CACHE_PREFIX = 'apify_actor_retry_at:';
    private const COMMENT_SCRAPER_STALE_MINUTES = 45;

    /**
     * The name and signature of the console command.
     * You can pass optional --platform and --project-id to scrape a specific target.
     */
    protected $signature = 'scraping:run-apify
                            {--platform= : Platform to scrape (Facebook, Instagram, TikTok)}
                            {--project-id= : Specific project ID to scrape for}
                            {--actor-id= : Specific actor ID to run (skip all other actors)}
                            {--limit= : Maximum items per actor run}
                            {--keyword= : Specific keyword override for QA purposes}
                            {--force-dispatch : Force dispatch even if duplicate/stale-safe guard matches}
                            {--no-telegram : Suppress Telegram notifications downstream}';

    protected $description = 'Dispatch Apify scraping jobs for all active projects and platforms';

    public function __construct(
        private readonly SchedulerQueueGuard $schedulerQueueGuard,
        private readonly SocialProjectScrapePriorityService $socialProjectScrapePriorityService,
    )
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $socialLog = Log::channel('social_media');
        $busyReason = $this->schedulerQueueGuard->apifyBusyReason();

        if ($busyReason !== null) {
            $this->warn("Apify scraping skipped: {$busyReason}");
            $this->schedulerQueueGuard->logSkip('apify', $busyReason, ['source' => 'command']);
            return;
        }

        // Check token is configured
        $setting = ApifySetting::first();
        if (!$setting || !$setting->isReadyForScraping()) {
            $status = $setting?->connection_status ?? 'missing';
            $this->warn("Apify scraping skipped: setting is not ready (status: {$status}).");
            Log::warning('[Apify] Command skipped because settings are not ready.', [
                'connection_status' => $status,
            ]);
            return;
        }

        app(ApifyActorRegistry::class)->syncManagedActors();

        $scrapingSetting = ScrapingSetting::first();
        $requestedLimit = $this->option('limit') ? (int) $this->option('limit') : null;
        $limitPerRun = $requestedLimit ? max(1, $requestedLimit) : null;

        $filterPlatform  = $this->option('platform');
        $filterProjectId = $this->option('project-id');
        $filterActorId   = $this->option('actor-id') ? (int) $this->option('actor-id') : null;
        $overrideKeyword = trim((string) $this->option('keyword'));
        $forceDispatch = (bool) $this->option('force-dispatch');
        $suppressTelegram = (bool) $this->option('no-telegram');

        // Load all active actors
        $actorQuery = ApifyActor::where('status', 'active')->orderBy('priority');
        if ($filterPlatform) {
            $actorQuery->where('platform', $filterPlatform);
        }
        if ($filterActorId) {
            $actorQuery->where('id', $filterActorId);
        }
        $actors = $actorQuery->get();

        if ($actors->isEmpty()) {
            $this->warn('No active Apify actors found.');
            return;
        }

        // Load projects
        if ($filterProjectId) {
            $project = Project::withTrashed()->find($filterProjectId);
            if (! $project) {
                $this->error('Project not found.');
                return;
            }
            if ($project->trashed() || ! $project->is_active) {
                $this->error('Project is deleted/inactive and cannot be scraped.');
                return;
            }
            $projects = collect([$project]);
        } else {
            $projects = Project::query()
                ->where('is_active', true)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();
            if ($projects->isEmpty()) {
                $this->warn('No projects found.');
                return;
            }
        }
        $projects = $this->socialProjectScrapePriorityService->prioritize($projects);

        $projectSummaries = $projects->map(function (Project $project) use ($overrideKeyword) {
            $keywords = array_values(array_unique(array_filter(array_map('trim', $project->scrapeKeywords()))));
            if ($overrideKeyword !== '') {
                array_unshift($keywords, $overrideKeyword);
            }

            return [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'keywords' => array_values(array_unique($keywords)),
            ];
        })->values()->all();

        $socialLog->info('[Social] Run started.', [
            'platform' => $filterPlatform ?: 'all',
            'project_id' => $filterProjectId ?: null,
            'limit' => $limitPerRun,
            'project_count' => count($projectSummaries),
            'projects' => $projectSummaries,
        ]);

        $dispatched = 0;
        $skipStats = [
            'no_keywords' => 0,
            'interval_not_due' => 0,
            'retry_wait' => 0,
            'cooldown_failed' => 0,
            'duplicate_or_stale' => 0,
        ];
        foreach ($projects as $project) {
            try {
                $projectKeywords = array_values(array_unique($project->scrapeKeywords()));
                if ($overrideKeyword !== '') {
                    array_unshift($projectKeywords, $overrideKeyword);
                }
                $projectKeywords = array_values(array_unique(array_filter(array_map('trim', $projectKeywords))));

                $socialLog->info('[Social] Project scan started.', [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'keywords' => $projectKeywords,
                'actor_count' => $actors->count(),
            ]);

            if ($projectKeywords === [] && $overrideKeyword !== '') {
                $projectKeywords = [$overrideKeyword];
            }

            if (empty($projectKeywords)) {
                $this->warn("Project [{$project->name}] has no topics/keywords. Skipping.");
                $socialLog->warning('[Social] Project skipped: no keywords.', [
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                ]);
                $skipStats['no_keywords']++;
                continue;
            }

            // Tentukan actors yang digunakan untuk project ini (jika punya paket, pakai actor paket. Jika tidak, lewati secara ketat)
            if ($project->package_id && $project->package) {
                $projectActors = $project->package->enabledActors()
                    ->when($filterPlatform, fn($q) => $q->where('platform', $filterPlatform))
                    ->orderBy('priority')
                    ->get();
            } else {
                $this->warn("Project [{$project->name}] has no active package. Skipping scraping process.");
                $socialLog->warning('[Social] Project skipped: no active package.', [
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                ]);
                continue;
            }

            $mainActors = $projectActors
                ->filter(fn ($actor) => strtolower((string) $actor->function_type) !== 'comment scraper')
                ->values();
            $commentActors = $projectActors
                ->filter(fn ($actor) => strtolower((string) $actor->function_type) === 'comment scraper')
                ->values();
            $orderedActors = $mainActors->concat($commentActors);
            $mainScraperDispatchedByPlatform = [];

            foreach ($orderedActors as $actor) {
                $lastProjectActorRunAt = $this->latestProjectActorRunAt($project->id, $actor->platform);

                $isCommentScraper = (strtolower((string) $actor->function_type) === 'comment scraper');
                $platformKey = strtolower((string) $actor->platform);
                $hasQueue = false;
                if ($isCommentScraper) {
                    $platformLower = $platformKey;
                    $preCheckQuery = \App\Models\SocialMediaItem::where('project_id', $project->id)
                        ->where('platform', $actor->platform)
                        ->whereNotNull('post_url');

                    if ($platformLower === 'tiktok') {
                        $preCheckQuery = $preCheckQuery
                            ->where('post_url', 'like', '%tiktok.com/@%')
                            ->where('post_url', 'like', '%/video/%');
                    } elseif ($platformLower === 'instagram') {
                        $preCheckQuery = $preCheckQuery
                            ->where('post_url', 'like', '%instagram.com/%');
                    }

                    $candidateCount = $preCheckQuery
                        ->get(['post_url'])
                        ->filter(function ($item) {
                            $urlHash = md5((string) $item->post_url);
                            return !\Illuminate\Support\Facades\Cache::has('comments_scraped_for_post:' . $urlHash)
                                && !\Illuminate\Support\Facades\Cache::has('comments_scraping_in_progress:' . $urlHash);
                        })->count();
                    $hasQueue = ($candidateCount > 0);
                }

                // Comment scraper must keep checking the queue every scheduler tick.
                // Do not block it behind the long actor interval when the queue is empty.
                if (! $isCommentScraper && $lastProjectActorRunAt && $actor->interval_minutes && !$hasQueue && !$forceDispatch) {
                    $nextRunAt = $lastProjectActorRunAt->copy()->addMinutes($actor->interval_minutes);
                    if (now()->lessThan($nextRunAt) && !$filterPlatform) {
                        $this->line("Skipping {$actor->platform} — next run at {$nextRunAt->format('H:i')}");
                        $socialLog->info('[Social] Actor skipped: interval not due.', [
                            'project_id' => $project->id,
                            'project_name' => $project->name,
                            'platform' => $actor->platform,
                            'actor_id' => $actor->id,
                            'last_project_run_at' => $lastProjectActorRunAt->toDateTimeString(),
                            'next_run_at' => $nextRunAt->toDateTimeString(),
                        ]);
                        $skipStats['interval_not_due']++;
                        continue;
                    }
                }

                $actorRetryAtKey = self::ACTOR_RECOVERY_CACHE_PREFIX . $actor->id;
                $actorRetryAt = Cache::get($actorRetryAtKey);
                if (filled($actorRetryAt) && ! $filterPlatform) {
                    $retryAt = Carbon::parse($actorRetryAt);
                    if (now()->lessThan($retryAt)) {
                        $this->line("Skipping {$actor->platform} — menunggu pemulihan otomatis sampai {$retryAt->format('H:i')}");
                        $socialLog->warning('[Social] Actor skipped: waiting recovery window.', [
                            'project_id' => $project->id,
                            'project_name' => $project->name,
                            'platform' => $actor->platform,
                            'actor_id' => $actor->id,
                            'retry_at' => $retryAt->toDateTimeString(),
                        ]);
                        $skipStats['retry_wait']++;
                        continue;
                    }
                }

                if (! $filterPlatform && $actor->last_run_status === 'failed') {
                    $recoveryAt = $this->actorRecoveryAt($actor);

                    if ($recoveryAt && now()->lessThan($recoveryAt)) {
                        Cache::put($actorRetryAtKey, $recoveryAt->toDateTimeString(), $recoveryAt);
                        $this->line("Skipping {$actor->platform} — last run gagal, coba lagi setelah {$recoveryAt->format('H:i')}.");
                        $socialLog->warning('[Social] Actor skipped: last run failed, cooldown applied.', [
                            'project_id' => $project->id,
                            'project_name' => $project->name,
                            'platform' => $actor->platform,
                            'actor_id' => $actor->id,
                            'retry_at' => $recoveryAt->toDateTimeString(),
                            'last_run_message' => $actor->last_run_message,
                        ]);
                        $skipStats['cooldown_failed']++;
                        continue;
                    }

                    Cache::forget($actorRetryAtKey);

                    $socialLog->info('[Social] Actor cooldown expired; retrying actor automatically.', [
                        'project_id' => $project->id,
                        'project_name' => $project->name,
                        'platform' => $actor->platform,
                        'actor_id' => $actor->id,
                        'last_run_at' => optional($actor->last_run_at)?->toDateTimeString(),
                    ]);
                } elseif (filled($actorRetryAt) && ! $filterPlatform) {
                    Cache::forget($actorRetryAtKey);
                }

                if (in_array($actor->platform, ['TikTok', 'Facebook', 'Instagram'], true)) {
                    $dispatchKeywords = $projectKeywords;
                    if ($overrideKeyword !== '') {
                        $dispatchKeywords = [$overrideKeyword];
                    }

                    // =========================================================
                    // LOGIKA KHUSUS COMMENT SCRAPER
                    // Alur: Ambil semua URL postingan dari proyek aktif (sesuai platform) →
                    //       urut dari terbaru → ambil maks 3 yang belum dicek →
                    //       tandai "dalam proses" → kirim ke Apify →
                    //       setelah selesai, tandai "selesai" (permanen).
                    //       Jika tidak ada antrean → skip tanpa membuang run.
                    // =========================================================
                    if (strtolower((string) $actor->function_type) === 'comment scraper') {
                        $platformLower = strtolower((string) $actor->platform);

                        // Ambil semua postingan dari proyek aktif ini yang masih perlu
                        // diperiksa komentarnya. SocialMediaItem adalah sumber kebenaran
                        // untuk comment scraper, jadi jangan batasi lagi ke URL artikel
                        // dashboard karena beberapa postingan valid belum tentu tercermin
                        // di tabel Article dengan URL yang identik.
                        $candidateQuery = \App\Models\SocialMediaItem::where('project_id', $project->id)
                            ->where('platform', $actor->platform)
                            ->where('comments_checked', false)
                            ->whereNotNull('post_url');

                        if ($platformLower === 'tiktok') {
                            $candidateQuery = $candidateQuery
                                ->where('post_url', 'like', '%tiktok.com/@%')
                                ->where('post_url', 'like', '%/video/%');
                        } elseif ($platformLower === 'instagram') {
                            $candidateQuery = $candidateQuery
                                ->where('post_url', 'like', '%instagram.com/%');
                        }

                        $candidateItems = $candidateQuery
                            ->orderBy('posted_at', 'desc')
                            ->orderBy('id', 'desc')
                            ->get(['id', 'post_url', 'comments_checked']);

                        // PROTEKSI: Jangan kirim ke Apify jika masih ada job comment scraper aktif
                        // pada platform yang sama. Facebook, Instagram, dan TikTok diperlakukan
                        // dengan aturan antrean yang sama agar tidak saling memblokir.
                        $activeCommentScrapersCount = \App\Models\ApifyDispatchState::whereIn('status', ['queued', 'processing'])
                            ->whereIn('actor_id', \App\Models\ApifyActor::where('function_type', 'Comment Scraper')
                                ->where('platform', $actor->platform)
                                ->pluck('id'))
                            ->where(function ($query) {
                                $staleThreshold = now()->subMinutes(self::COMMENT_SCRAPER_STALE_MINUTES);

                                $query->where(function ($queued) use ($staleThreshold) {
                                    $queued->where('status', 'queued')
                                        ->where('queued_at', '>=', $staleThreshold);
                                })->orWhere(function ($processing) use ($staleThreshold) {
                                    $processing->where('status', 'processing')
                                        ->where(function ($active) use ($staleThreshold) {
                                            $active->where('started_at', '>=', $staleThreshold)
                                                ->orWhere('updated_at', '>=', $staleThreshold);
                                        });
                                });
                            })
                            ->count();

                        if ($activeCommentScrapersCount > 0) {
                            $this->line("Skipping Comment Scraper: [{$actor->platform}] project={$project->name} — masih ada job comment scraper aktif pada platform yang sama.");
                            $socialLog->info('[Social] Comment Scraper skipped: another comment scraper job is active on the same platform.', [
                                'project_id'   => $project->id,
                                'project_name' => $project->name,
                                'platform'     => $actor->platform,
                                'actor_id'     => $actor->id,
                            ]);
                            continue;
                        }

                        // Filter: ambil URL yang belum ditandai "selesai" DAN belum "dalam proses"
                        $unprocessedUrls = [];
                        foreach ($candidateItems as $candidateItem) {
                            $urlHash = md5((string) $candidateItem->post_url);
                            $doneKey       = 'comments_scraped_for_post:' . $urlHash;
                            $inProgressKey = 'comments_scraping_in_progress:' . $urlHash;

                            if (!Cache::has($doneKey) && !Cache::has($inProgressKey)) {
                                $unprocessedUrls[] = $candidateItem->post_url;
                                if (count($unprocessedUrls) >= 3) {
                                    break; // Maksimal 3 URL per run
                                }
                            }
                        }

                        // Jika tidak ada antrean → skip, tidak perlu kirim ke Apify
                        if (empty($unprocessedUrls)) {
                            $this->line("Skipping Comment Scraper: [{$actor->platform}] project={$project->name} — tidak ada URL baru yang perlu di-scrape komentarnya.");
                            $socialLog->info('[Social] Comment Scraper skipped: no unprocessed URLs in queue.', [
                                'project_id'   => $project->id,
                                'project_name' => $project->name,
                                'platform'     => $actor->platform,
                                'actor_id'     => $actor->id,
                                'total_candidates' => $candidateItems->count(),
                            ]);
                            continue;
                        }

                        // Tandai URL sebagai "dalam proses" (TTL 30 menit) SEBELUM dispatch
                        // agar run berikutnya tidak mendispatch URL yang sama ke Apify lagi.
                        foreach ($unprocessedUrls as $urlToMark) {
                            Cache::put(
                                'comments_scraping_in_progress:' . md5((string) $urlToMark),
                                true,
                                now()->addMinutes(30)
                            );
                        }

                        $this->line("Comment Scraper [{$actor->platform}] project={$project->name} — antrean: " . count($unprocessedUrls) . " URL.");
                        $socialLog->info('[Social] Comment Scraper queue ready.', [
                            'project_id'   => $project->id,
                            'project_name' => $project->name,
                            'platform'     => $actor->platform,
                            'actor_id'     => $actor->id,
                            'queued_urls'  => $unprocessedUrls,
                        ]);

                        $dispatchKeywords = $unprocessedUrls;
                    }


                    $wasDispatched = ApifyScrapingJob::dispatchSafely([
                        'platform'    => $actor->platform,
                        'keyword'     => $dispatchKeywords[0] ?? ($actor->default_keyword ?? ''),
                        'keywords'    => $dispatchKeywords,
                        'project_id'  => $project->id,
                        'actor_id'    => $actor->id,
                        'limit'       => $limitPerRun,
                        'force_dispatch' => $forceDispatch,
                        'no_telegram' => $suppressTelegram,
                    ]);

                    if ($wasDispatched) {
                        if (! $isCommentScraper) {
                            $mainScraperDispatchedByPlatform[$platformKey] = true;
                        }

                        $this->info("✓ Dispatched: [{$actor->platform}] keywords=" . implode(', ', $dispatchKeywords) . " project={$project->name}");
                        Log::info("[Scheduler] Dispatched social ApifyScrapingJob", [
                            'platform'   => $actor->platform,
                            'keywords'   => $dispatchKeywords,
                            'project_id' => $project->id,
                            'limit'      => $limitPerRun,
                        ]);
                        $socialLog->info('[Social] Job dispatched.', [
                            'platform' => $actor->platform,
                            'project_id' => $project->id,
                            'project_name' => $project->name,
                            'keywords' => $dispatchKeywords,
                            'limit' => $limitPerRun,
                        ]);
                        $dispatched++;
                    } else {
                        $this->line("Skipping duplicate/stale-safe job: [{$actor->platform}] keywords=" . implode(', ', $dispatchKeywords) . " project={$project->name}");
                        $socialLog->info('[Social] Actor skipped: duplicate/stale-safe job.', [
                            'project_id' => $project->id,
                            'project_name' => $project->name,
                            'platform' => $actor->platform,
                            'actor_id' => $actor->id,
                            'keywords' => $dispatchKeywords,
                        ]);
                        $skipStats['duplicate_or_stale']++;
                    }

                    continue;
                }

                foreach ($projectKeywords as $keyword) {
                    $wasDispatched = ApifyScrapingJob::dispatchSafely([
                        'platform'   => $actor->platform,
                        'keyword'    => $keyword,
                        'project_id' => $project->id,
                        'actor_id'   => $actor->id,
                        'limit'      => $limitPerRun,
                        'no_telegram'=> $suppressTelegram,
                    ]);

                    if ($wasDispatched) {
                        $this->info("✓ Dispatched: [{$actor->platform}] keyword={$keyword} project={$project->name}");
                        Log::info("[Scheduler] Dispatched ApifyScrapingJob", [
                            'platform'   => $actor->platform,
                            'keyword'    => $keyword,
                            'project_id' => $project->id,
                            'limit'      => $limitPerRun,
                        ]);
                        $socialLog->info('[Social] Job dispatched.', [
                            'platform' => $actor->platform,
                            'project_id' => $project->id,
                            'project_name' => $project->name,
                            'keyword' => $keyword,
                            'limit' => $limitPerRun,
                        ]);
                        $dispatched++;
                    } else {
                        $this->line("Skipping duplicate/stale-safe job: [{$actor->platform}] keyword={$keyword} project={$project->name}");
                        $socialLog->info('[Social] Actor skipped: duplicate/stale-safe job.', [
                            'project_id' => $project->id,
                            'project_name' => $project->name,
                            'platform' => $actor->platform,
                            'actor_id' => $actor->id,
                            'keyword' => $keyword,
                        ]);
                        $skipStats['duplicate_or_stale']++;
                    }
                }

                $socialLog->info('[Social] Actor scan finished.', [
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'platform' => $actor->platform,
                    'actor_id' => $actor->id,
                ]);
            }

            $socialLog->info('[Social] Project scan finished.', [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'keywords' => $projectKeywords,
            ]);
            } catch (\Throwable $e) {
                $this->error("Error scraping project [{$project->name}]: " . $e->getMessage());
                $socialLog->error("Error scraping project [{$project->name}]", [
                    'project_id' => $project->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $this->info("Total {$dispatched} scraping job(s) dispatched.");
        $socialLog->info('[Social] Run finished.', [
            'dispatched' => $dispatched,
            'skip_summary' => $skipStats,
            'status' => $dispatched > 0 ? 'some_jobs_dispatched' : 'no_jobs_dispatched',
            'message' => $dispatched > 0
                ? 'Ada job yang dikirim ke antrean.'
                : 'Tidak ada job yang dikirim. Cek project aktif, keyword, interval actor, cooldown, atau duplikasi antrean.',
        ]);
    }

    protected function actorCooldownMinutes(?string $message, int $baseMinutes = 20): int
    {
        $message = strtolower((string) $message);

        if (str_contains($message, 'monthly usage hard limit exceeded') || str_contains($message, 'platform-feature-disabled')) {
            return 5;
        }

        if (str_contains($message, 'timeout') || str_contains($message, 'connection') || str_contains($message, 'could not')) {
            return max(15, $baseMinutes);
        }

        return max(10, $baseMinutes);
    }

    protected function actorRecoveryAt(ApifyActor $actor): ?Carbon
    {
        if (! $actor->last_run_at) {
            return null;
        }

        $cooldownMinutes = $this->actorCooldownMinutes($actor->last_run_message, (int) ($actor->interval_minutes ?? 20));

        return $actor->last_run_at->copy()->addMinutes($cooldownMinutes);
    }

    /**
     * Write a string as information output with a timestamp.
     */
    public function line($string, $style = null, $verbosity = null)
    {
        $timestamp = '[' . now()->format('Y-m-d H:i:s') . ']';
        parent::line("{$timestamp} {$string}", $style, $verbosity);
    }

    protected function latestProjectActorRunAt(int $projectId, string $platform): ?Carbon
    {
        $value = DB::table('apify_dispatch_states')
            ->where('project_id', $projectId)
            ->whereRaw('lower(platform) = ?', [strtolower($platform)])
            ->max(DB::raw('coalesce(completed_at, started_at, queued_at)'));

        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
