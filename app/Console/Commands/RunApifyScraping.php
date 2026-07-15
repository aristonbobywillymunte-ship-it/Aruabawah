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
    /**
     * The name and signature of the console command.
     * You can pass optional --platform and --project-id to scrape a specific target.
     */
    protected $signature = 'scraping:run-apify
                            {--platform= : Platform to scrape (Facebook, Instagram, TikTok)}
                            {--project-id= : Specific project ID to scrape for}
                            {--limit= : Maximum items per actor run}
                            {--keyword= : Specific keyword override for QA purposes}
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
        $configuredLimit = (int) ($scrapingSetting?->limit_per_run ?? 3);
        $configuredLimit = max(1, $configuredLimit);
        $requestedLimit = $this->option('limit') ? (int) $this->option('limit') : null;
        $limitPerRun = $requestedLimit ? max(1, $requestedLimit) : $configuredLimit;
        $limitPerRun = min(ApifyActorModel::MAX_SOCIAL_ITEMS_PER_RUN, $limitPerRun);

        $filterPlatform  = $this->option('platform');
        $filterProjectId = $this->option('project-id');
        $overrideKeyword = trim((string) $this->option('keyword'));
        $suppressTelegram = (bool) $this->option('no-telegram');

        // Load all active actors
        $actorQuery = ApifyActor::where('status', 'active')->orderBy('priority');
        if ($filterPlatform) {
            $actorQuery->where('platform', $filterPlatform);
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

            foreach ($actors as $actor) {
                $lastProjectActorRunAt = $this->latestProjectActorRunAt($project->id, $actor->platform);

                // Check if interval has passed since the last run for this project + platform
                if ($lastProjectActorRunAt && $actor->interval_minutes) {
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

                $actorRetryAtKey = "apify_actor_retry_at:{$actor->id}";
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

                if (filled($actorRetryAt) && ! $filterPlatform) {
                    Cache::forget($actorRetryAtKey);
                }

                if (! $filterPlatform && $actor->last_run_status === 'failed' && blank($actorRetryAt)) {
                    $cooldownMinutes = $this->actorCooldownMinutes($actor->last_run_message, (int) ($actor->interval_minutes ?? 20));
                    $retryAt = now()->addMinutes($cooldownMinutes);
                    Cache::put($actorRetryAtKey, $retryAt->toDateTimeString(), $retryAt);
                    $this->line("Skipping {$actor->platform} — last run gagal, coba lagi setelah {$retryAt->format('H:i')}.");
                    $socialLog->warning('[Social] Actor skipped: last run failed, cooldown applied.', [
                        'project_id' => $project->id,
                        'project_name' => $project->name,
                        'platform' => $actor->platform,
                        'actor_id' => $actor->id,
                        'retry_at' => $retryAt->toDateTimeString(),
                        'last_run_message' => $actor->last_run_message,
                    ]);
                    $skipStats['cooldown_failed']++;
                    continue;
                }

                if (in_array($actor->platform, ['TikTok', 'Facebook', 'Instagram'], true)) {
                    $dispatchKeywords = $projectKeywords;
                    if ($overrideKeyword !== '') {
                        $dispatchKeywords = [$overrideKeyword];
                    }

                    $wasDispatched = ApifyScrapingJob::dispatchSafely([
                        'platform'    => $actor->platform,
                        'keyword'     => $dispatchKeywords[0] ?? ($actor->default_keyword ?? ''),
                        'keywords'    => $dispatchKeywords,
                        'project_id'  => $project->id,
                        'actor_id'    => $actor->id,
                        'limit'       => $limitPerRun,
                        'no_telegram' => $suppressTelegram,
                    ]);

                    if ($wasDispatched) {
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
