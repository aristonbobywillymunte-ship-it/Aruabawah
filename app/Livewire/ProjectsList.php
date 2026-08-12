<?php

namespace App\Livewire;

use App\Jobs\BootstrapNewProjectScrapingJob;
use App\Models\Project;

class ProjectsList extends \App\Http\Livewire\ProjectsList
{
    public function runScraping($projectId)
    {
        $project = Project::accessibleBy(auth()->user())->findOrFail($projectId);

        BootstrapNewProjectScrapingJob::dispatch($project->id);

        $message = "Scraping untuk proyek '{$project->name}' sudah masuk antrean. Proses portal berita dan media sosial berjalan di latar belakang.";

        session()->flash('message', $message);
        $this->notifyProjectAction($message, 'success');

        return redirect()->to(request()->header('Referer') ?? '/');
    }
}
