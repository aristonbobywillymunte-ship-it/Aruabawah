<?php

namespace Tests\Feature;

use App\Jobs\BootstrapNewProjectScrapingJob;
use App\Livewire\ProjectsList;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectsListRunScrapingTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_run_scraping_queues_job_and_shows_success_feedback(): void
    {
        Queue::fake();

        $user = User::create([
            'name' => 'Run Scraping Admin',
            'email' => 'run-scraping-admin@test.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $project = Project::create([
            'name' => 'Wagub Kaltim QA',
            'topics' => ['Wagub Kaltim'],
            'is_active' => true,
        ]);

        $sessionMessage = "Proyek '{$project->name}' telah didaftarkan ke antrean scraping langsung!";
        $toastMessage = "Proyek '{$project->name}' sedang berjalan di background.";

        Livewire::actingAs($user)
            ->test(ProjectsList::class)
            ->call('confirmRunScraping', $project->id)
            ->assertSet('showConfirmModal', true)
            ->assertSet('confirmAction', 'run_scraping')
            ->assertSet('confirmProjectId', $project->id)
            ->call('runConfirmedProjectAction')
            ->assertSet('toastType', 'success')
            ->assertSet('toastMessage', $toastMessage)
            ->assertSessionHas('message', $sessionMessage)
            ->assertRedirect('/');

        Queue::assertPushed(BootstrapNewProjectScrapingJob::class, function (BootstrapNewProjectScrapingJob $job) use ($project) {
            return $job->projectId === $project->id
                && $job->queue === 'news';
        });
    }
}
