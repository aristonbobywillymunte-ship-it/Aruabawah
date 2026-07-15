<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobFailed;

class AppServiceProvider extends ServiceProvider
{
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
            Log::channel('queue')->info("[Queue] Processing: " . $event->job->resolveName(), [
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'job_id' => $event->job->getJobId(),
            ]);
        });

        Queue::after(function (JobProcessed $event) {
            Log::channel('queue')->info("[Queue] Processed: " . $event->job->resolveName(), [
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'job_id' => $event->job->getJobId(),
            ]);
        });

        Queue::failing(function (JobFailed $event) {
            Log::channel('queue')->error("[Queue] Failed: " . $event->job->resolveName(), [
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'job_id' => $event->job->getJobId(),
                'exception' => $event->exception->getMessage(),
            ]);
        });
    }
}
