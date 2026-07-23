<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobFailed;

class AppServiceProvider extends ServiceProvider
{
    private const AI_WORKER_ACTIVE_CACHE_KEY = 'ai-worker:active';
    private const AI_WORKER_ACTIVE_TTL_SECONDS = 30;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Queue::before(function (JobProcessing $event) {
            $this->touchAiWorkerActivity($event->connectionName, $event->job->getQueue());
            Log::channel('queue')->info("[Queue] Processing: " . $event->job->resolveName(), [
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'job_id' => $event->job->getJobId(),
            ]);
        });

        Queue::after(function (JobProcessed $event) {
            $this->touchAiWorkerActivity($event->connectionName, $event->job->getQueue());
            Log::channel('queue')->info("[Queue] Processed: " . $event->job->resolveName(), [
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'job_id' => $event->job->getJobId(),
            ]);
        });

        Queue::failing(function (JobFailed $event) {
            $this->touchAiWorkerActivity($event->connectionName, $event->job->getQueue());
            Log::channel('queue')->error("[Queue] Failed: " . $event->job->resolveName(), [
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'job_id' => $event->job->getJobId(),
                'exception' => $event->exception->getMessage(),
            ]);
        });
    }

    private function touchAiWorkerActivity(?string $connectionName, ?string $queueName): void
    {
        if ($connectionName !== 'redis-ai') {
            return;
        }

        if (! in_array($queueName, ['ai-analysis', 'ai-backfill'], true)) {
            return;
        }

        Cache::put(self::AI_WORKER_ACTIVE_CACHE_KEY, now()->timestamp, now()->addSeconds(self::AI_WORKER_ACTIVE_TTL_SECONDS));
    }
}
