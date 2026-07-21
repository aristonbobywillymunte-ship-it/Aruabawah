<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class BootstrapNewProjectScrapingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

    public function __construct(
        public int $projectId,
    ) {
        $this->queue = 'news';
    }

    public function handle(): void
    {
        Log::info('[Bootstrap] New project scraping started.', [
            'project_id' => $this->projectId,
        ]);

        $newsExitCode = Artisan::call('scraping:run-news', [
            '--project-id' => $this->projectId,
            '--limit' => 3,
        ]);

        Log::info('[Bootstrap] Portal/news scraping finished for new project.', [
            'project_id' => $this->projectId,
            'exit_code' => $newsExitCode,
        ]);

        $apifyExitCode = Artisan::call('scraping:run-apify', [
            '--project-id' => $this->projectId,
            '--force-dispatch' => true,
        ]);

        Log::info('[Bootstrap] Apify scraping finished for new project.', [
            'project_id' => $this->projectId,
            'exit_code' => $apifyExitCode,
        ]);
    }
}
