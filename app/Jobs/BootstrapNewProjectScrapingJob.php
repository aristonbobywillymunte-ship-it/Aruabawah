<?php

namespace App\Jobs;

use App\Models\Project;
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

        $project = Project::with('package')->find($this->projectId);

        // Hanya jalankan scraping portal jika proyek tidak memiliki paket atau paket mengaktifkan use_portal
        $usePortal = $project?->package ? (bool) $project->package->use_portal : true;

        if ($usePortal) {
            $newsExitCode = Artisan::call('scraping:run-news', [
                '--project-id' => $this->projectId,
                '--limit' => 3,
            ]);

            Log::info('[Bootstrap] Portal/news scraping finished for new project.', [
                'project_id' => $this->projectId,
                'exit_code' => $newsExitCode,
            ]);
        } else {
            Log::info('[Bootstrap] Portal scraping skipped; package use_portal is disabled.', [
                'project_id' => $this->projectId,
            ]);
        }

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
