<?php

namespace App\Livewire;

use App\Jobs\BootstrapNewProjectScrapingJob;
use App\Models\Project;

class ProjectsList extends \App\Http\Livewire\ProjectsList
{
    public function runScraping($id)
    {
        $project = Project::accessibleBy(auth()->user())->findOrFail($id);

        BootstrapNewProjectScrapingJob::dispatch($project->id);

        $message = "Proyek '{$project->name}' telah didaftarkan ke antrean scraping langsung!";
        session()->flash('message', $message);
        $this->notifyProjectAction("Proyek '{$project->name}' sedang berjalan di background.", 'success');

        $this->forgetProjectsCache();
        $this->redirect(request()->header('Referer') ?: '/');
    }
}
