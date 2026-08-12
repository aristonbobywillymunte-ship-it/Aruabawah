<?php

namespace App\Console\Commands;

use App\Jobs\ApifyScrapingJob;
use App\Models\ApifyActor;
use App\Models\ApifySetting;
use App\Models\Project;
use App\Models\ScrapingSetting;
use App\Models\ApifyActor as ApifyActorModel;
use App\Services\ApifyActorRegistry;
use App\Services\Scraping\ProjectScheduleResolver;
use App\Services\Scraping\SocialCommentScraperDispatcher;
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
        private readonly ProjectScheduleResolver $projectScheduleResolver,
        private readonly SocialCommentScraperDispatcher $socialCommentScraperDispatcher,
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
                $lastProjectActorRunAt = $this->latestProjectActorRunAt($project->id, $actor->id);

                $isCommentScraper = (strtolower((string) $actor->function_type) === 'comment scraper');
                $platformKey = strtolower((string) $actor->platform);

                // Comment scraper must keep checking the queue every scheduler tick.
                // Do not block it behind actor interval timing when the queue is empty.
                $socialScheduleResult = $this->projectScheduleResolver->resolveSocial($project);
                $socialSchedule = $socialScheduleResult['times'] ?? [];
                $socialScheduleSource = $socialScheduleResult['source'] ?? 'none';
                $socialScheduleReason = $socialScheduleResult['reason'] ?? null;

                if (! $isCommentScraper && ! $forceDispatch) {
                    if ($socialSchedule === []) {
                        $this->line("Skipping {$actor->platform} — social schedule not configured.");
                        $socialLog->info('[Social] Actor skipped: no valid daily schedule.', [
                            'project_id' => $project->id,
                            'project_name' => $project->name,
                            'platform' => $actor->platform,
                            'actor_id' => $actor->id,
                            'last_project_run_at' => optional($lastProjectActorRunAt)?->toDateTimeString(),
                            'schedule_source' => $socialScheduleSource,
                            'schedule_reason' => $socialScheduleReason,
                        ]);
                        $skipStats['interval_not_due']++;
                        continue;
                    }

                    $latestDueSlotAt = $this->latestDueSlotAt($socialSchedule);

                    if (! $latestDueSlotAt || $this->isSlotFulfilled($lastProjectActorRunAt, $latestDueSlotAt)) {
                        $this->line("Skipping {$actor->platform} — social schedule not due.");
                        $socialLog->info('[Social] Actor skipped: daily schedule not due.', [
                            'project_id' => $project->id,
                            'project_name' => $project->name,
                            'platform' => $actor->platform,
                            'actor_id' => $actor->id,
                            'last_project_run_at' => optional($lastProjectActorRunAt)?->toDateTimeString(),
                            'schedule' => $socialSchedule,
                            'schedule_source' => $socialScheduleSource,
                            'latest_due_slot_at' => optional($latestDueSlotAt)?->toDateTimeString(),
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
                    // Alur kandidat komentar dipusatkan di service shared agar
                    // scheduler dan post-import trigger memakai aturan yang sama.
                    // =========================================================
                    if (strtolower((string) $actor->function_type) === 'comment scraper') {
                        $commentDispatch = $this->socialCommentScraperDispatcher->dispatchEligible($project, $actor->platform);

                        if (! ($commentDispatch['dispatched'] ?? false)) {
                            $this->line("Skipping Comment Scraper: [{$actor->platform}] project={$project->name} — " . ($commentDispatch['reason'] ?? 'no eligible URLs.'));
                            $socialLog->info('[Social] Comment Scraper skipped.', [
                                'project_id' => $project->id,
                                'project_name' => $project->name,
                                'platform' => $actor->platform,
                                'actor_id' => $actor->id,
                                'reason' => $commentDispatch['reason'] ?? 'unknown',
                                'eligible_count' => $commentDispatch['count'] ?? 0,
                            ]);
                            continue;
                        }

                        $this->line("Comment Scraper [{$actor->platform}] project={$project->name} — antrean siap.");
                        $socialLog->info('[Social] Comment Scraper queue ready.', [
                            'project_id' => $project->id,
                            'project_name' => $project->name,
                            'platform' => $actor->platform,
                            'actor_id' => $actor->id,
                            'queued_count' => $commentDispatch['count'] ?? 0,
                        ]);

                        continue;
                    }


                    $isFacebookMainScraper = $actor->platform === 'Facebook' && ! $isCommentScraper;
                    $chunks = $isFacebookMainScraper ? array_chunk($dispatchKeywords, 4) : [$dispatchKeywords];

                    foreach ($chunks as $chunkIndex => $dispatchKeywordsChunk) {
                        if (empty($dispatchKeywordsChunk)) {
                            continue;
                        }

                        $wasDispatched = ApifyScrapingJob::dispatchSafely([
                            'platform'    => $actor->platform,
                            'keyword'     => $dispatchKeywordsChunk[0] ?? ($actor->default_keyword ?? ''),
                            'keywords'    => $dispatchKeywordsChunk,
                            'project_id'  => $project->id,
                            'actor_id'    => $actor->id,
                            'limit'       => $limitPerRun,
                            'scheduled_execution' => ! $isCommentScraper && ! $forceDispatch,
                            'force_dispatch' => $isCommentScraper ? true : $forceDispatch,
                            'no_telegram' => $suppressTelegram,
                        ]);

                        if ($wasDispatched) {
                            if (! $isCommentScraper) {
                                $mainScraperDispatchedByPlatform[$platformKey] = true;
                            }

                            $this->info("✓ Dispatched: [{$actor->platform}] keywords=" . implode(', ', $dispatchKeywordsChunk) . " project={$project->name}");
                            Log::info("[Scheduler] Dispatched social ApifyScrapingJob", [
                                'platform'   => $actor->platform,
                                'keywords'   => $dispatchKeywordsChunk,
                                'project_id' => $project->id,
                                'limit'      => $limitPerRun,
                            ]);
                            $socialLog->info('[Social] Job dispatched.', [
                                'platform' => $actor->platform,
                                'project_id' => $project->id,
                                'project_name' => $project->name,
                                'keywords' => $dispatchKeywordsChunk,
                                'limit' => $limitPerRun,
                            ]);
                            $dispatched++;
                        } else {
                            $this->line("Skipping duplicate/stale-safe job: [{$actor->platform}] keywords=" . implode(', ', $dispatchKeywordsChunk) . " project={$project->name}");
                            $socialLog->info('[Social] Actor skipped: duplicate/stale-safe job.', [
                                'project_id' => $project->id,
                                'project_name' => $project->name,
                                'platform' => $actor->platform,
                                'actor_id' => $actor->id,
                                'keywords' => $dispatchKeywordsChunk,
                            ]);
                            $skipStats['duplicate_or_stale']++;
                        }
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
                        'scheduled_execution' => false,
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
                : 'Tidak ada job yang dikirim. Cek project aktif, keyword, schedule harian, cooldown, atau duplikasi antrean.',
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

        $cooldownMinutes = $this->actorCooldownMinutes($actor->last_run_message, $this->apifyScheduleRetryCooldownMinutes());

        return $actor->last_run_at->copy()->addMinutes($cooldownMinutes);
    }

    protected function apifyScheduleRetryCooldownMinutes(): int
    {
        return max(1, (int) config('services.apify.schedule_retry_cooldown_minutes', 10));
    }

    /**
     * Write a string as information output with a timestamp.
     */
    public function line($string, $style = null, $verbosity = null)
    {
        $timestamp = '[' . now()->format('Y-m-d H:i:s') . ']';
        parent::line("{$timestamp} {$string}", $style, $verbosity);
    }

    protected function latestProjectActorRunAt(int $projectId, int $actorId): ?Carbon
    {
        $value = DB::table('apify_dispatch_states')
            ->where('project_id', $projectId)
            ->where('actor_id', $actorId)
            ->where('status', 'success')
            ->where('is_scheduled_execution', true)
            ->whereNotNull('completed_at')
            ->max('completed_at');

        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function normalizeDailyRunTimes($runsPerDay, $times): array
    {
        $count = (int) ($runsPerDay ?? 0);
        if ($count <= 0) {
            return [];
        }

        if (is_string($times)) {
            $times = preg_split('/[\s,]+/', trim($times)) ?: [];
        }

        if (! is_array($times)) {
            return [];
        }

        $normalized = [];
        foreach ($times as $time) {
            $time = trim((string) $time);
            if ($time === '' || ! preg_match('/^(?:[01]?\d|2[0-3]):[0-5]\d$/', $time)) {
                continue;
            }
            $normalized[] = $time;
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return count($normalized) === $count ? $normalized : [];
    }

    protected function latestDueSlotAt(array $runTimes, ?Carbon $now = null): ?Carbon
    {
        $now ??= now();

        $dueSlots = [];
        foreach ($runTimes as $time) {
            try {
                $dueSlots[] = Carbon::createFromFormat('Y-m-d H:i', $now->format('Y-m-d') . ' ' . $time, $now->timezone);
            } catch (\Throwable) {
                continue;
            }
        }

        if ($dueSlots === []) {
            return null;
        }

        usort($dueSlots, fn (Carbon $a, Carbon $b) => $a->timestamp <=> $b->timestamp);

        $latestDueSlot = null;
        foreach ($dueSlots as $slot) {
            if ($slot->lessThanOrEqualTo($now)) {
                $latestDueSlot = $slot;
            }
        }

        if (! $latestDueSlot) {
            return Carbon::createFromFormat(
                'Y-m-d H:i',
                $now->copy()->subDay()->format('Y-m-d') . ' ' . end($dueSlots)->format('H:i'),
                $now->timezone
            );
        }

        return $latestDueSlot;
    }

    protected function isSlotFulfilled(?Carbon $lastRunAt, Carbon $latestDueSlotAt): bool
    {
        return $lastRunAt !== null && $lastRunAt->greaterThanOrEqualTo($latestDueSlotAt);
    }
}
