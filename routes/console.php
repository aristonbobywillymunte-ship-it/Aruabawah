<?php

use Illuminate\Foundation\Inspiring;
use App\Services\SchedulerQueueGuard;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schedule;

// Check if the scheduler should restart (triggered from System Maintenance)
try {
    $cacheStore = config('cache.default');
    $cacheTableReady = true;

    if ($cacheStore === 'database') {
        $cacheTableReady = \Illuminate\Support\Facades\Schema::hasTable('cache');
    }

    if ($cacheTableReady && \Illuminate\Support\Facades\Cache::get('scheduler_should_restart')) {
        \Illuminate\Support\Facades\Cache::forget('scheduler_should_restart');
        \Illuminate\Support\Facades\Log::info('[Scheduler] Exit signal received. Terminating scheduler process.');

        $ppid = function_exists('posix_getppid') ? posix_getppid() : null;
        if ($ppid && function_exists('posix_kill')) {
            posix_kill($ppid, 15); // SIGTERM (15) to kill parent artisan schedule:work
        }
        exit(0);
    }
} catch (\Throwable $e) {
    \Illuminate\Support\Facades\Log::warning('[Scheduler] Restart guard skipped: ' . $e->getMessage());
}

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Scheduler: Dynamic configurations from database ─────────────────────────
try {
    $settings = \Illuminate\Support\Facades\Schema::hasTable('scraping_settings')
        ? \Illuminate\Support\Facades\DB::table('scraping_settings')->first()
        : null;
} catch (\Exception $e) {
    $settings = null;
}

try {
    $apifySetting = \Illuminate\Support\Facades\Schema::hasTable('apify_settings')
        ? \App\Models\ApifySetting::first()
        : null;
} catch (\Exception $e) {
    $apifySetting = null;
}

$newsInterval = $settings?->google_news_interval ?? 5;
$apifyInterval = $settings?->portal_crawling_interval ?? 1;
$limitPerRun = max(1, (int) ($settings?->limit_per_run ?? 1));
$isActive = $settings?->is_active ?? 1;
$apifyReady = $apifySetting?->isReadyForScraping() ?? false;
$newsSchedulerEnabled = (bool) config('services.news.scheduler_enabled', true);
$apifySchedulerEnabled = (bool) config('services.apify.scheduler_enabled', true);

if ($isActive) {
    $minutesToCron = function ($minutes) {
        $minutes = max(1, (int) $minutes);
        if ($minutes < 60) {
            return "*/{$minutes} * * * *";
        }
        $hours = max(1, (int) floor($minutes / 60));
        return "0 */{$hours} * * *";
    };

    // Run Apify / Portal scraping
    if ($apifySchedulerEnabled && $apifyInterval > 0 && $apifyReady) {
        Schedule::command('scraping:run-apify --limit=' . $limitPerRun)
            ->cron($minutesToCron($apifyInterval))
            ->when(function () use ($apifySchedulerEnabled, $settings, $apifySetting) {
                if (! $apifySchedulerEnabled || ! (bool) ($settings?->is_active ?? false) || ! (bool) ($apifySetting?->isReadyForScraping() ?? false)) {
                    return false;
                }

                $guard = app(SchedulerQueueGuard::class);
                $busyReason = $guard->apifyBusyReason();

                if ($busyReason !== null) {
                    $guard->logSkip('apify', $busyReason, ['source' => 'schedule']);
                    return false;
                }

                return true;
            })
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/scraping-scheduler.log'));
    }

    // Run News Portal scraping
    if ($newsSchedulerEnabled && $newsInterval > 0) {
        Schedule::command('scraping:run-news --limit=' . $limitPerRun)
            ->cron($minutesToCron($newsInterval))
            ->when(function () use ($newsSchedulerEnabled, $settings) {
                if (! $newsSchedulerEnabled || ! (bool) ($settings?->is_active ?? false)) {
                    return false;
                }

                $guard = app(SchedulerQueueGuard::class);
                $busyReason = $guard->newsBusyReason();

                if ($busyReason !== null) {
                    $guard->logSkip('portal', $busyReason, ['source' => 'schedule']);
                    return false;
                }

                return true;
            })
            ->withoutOverlapping(60)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/news-scraping.log'));
    }

    // Run AI provider health check
    Schedule::command('ai:check-provider-health')
        ->everyFiveMinutes()
        ->when(fn () => $isActive)
        ->withoutOverlapping()
        ->runInBackground()
        ->appendOutputTo(storage_path('logs/ai-health-check-scheduler.log'));

    Schedule::command('ai:requeue-overdue-retries --limit=1')
        ->everyMinute()
        ->when(function () use ($isActive) {
            if (! $isActive) {
                return false;
            }

            try {
                return \App\Models\AiAnalysisDispatchState::query()
                    ->where('status', 'retry_wait')
                    ->whereNotNull('next_retry_at')
                    ->where('next_retry_at', '<=', now())
                    ->exists();
            } catch (\Throwable $e) {
                return false;
            }
        })
        ->withoutOverlapping()
        ->runInBackground()
        ->appendOutputTo(storage_path('logs/ai-requeue-overdue.log'));

    // Run AI backfill readers, but only when ai-analysis and ai-backfill queues are idle.
    Schedule::command('ai:backfill-article-readers --execute --limit=10')
        ->cron('*/5 * * * *')
        ->when(function () use ($isActive) {
            if (! $isActive) {
                return false;
            }

            try {
                $aiAnalysis = Queue::connection('redis-ai')->size('ai-analysis');
                $aiBackfill = Queue::connection('redis-ai')->size('ai-backfill');
                return $aiAnalysis === 0 && $aiBackfill === 0;
            } catch (\Throwable $e) {
                return false;
            }
        })
        ->withoutOverlapping()
        ->runInBackground()
        ->appendOutputTo(storage_path('logs/ai-backfill-scheduler.log'));
}

// ── Scheduler: Heartbeat untuk System Health Dashboard ─────────────────────────
Schedule::call(function () {
    $heartbeat = now()->timestamp;
    \Illuminate\Support\Facades\Cache::put('scheduler_heartbeat', $heartbeat, now()->addMinutes(3));
    
    // Broadcast event real-time untuk memperbarui detak di dashboard
    event(new \App\Events\RealtimeNotificationEvent('scheduler', 'Heartbeat', 'Scheduler heartbeat updated.', [
        'heartbeat' => $heartbeat
    ]));
    
    // Check if restart is requested via maintenance panel
    if (\Illuminate\Support\Facades\Cache::pull('scheduler_should_restart')) {
        \Illuminate\Support\Facades\Log::info('[Scheduler] Exit requested by admin maintenance. Restarting container.');
        exit(0);
    }
})->everyMinute();
