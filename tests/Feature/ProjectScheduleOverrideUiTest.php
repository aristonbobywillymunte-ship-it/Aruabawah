<?php

namespace Tests\Feature;

use App\Livewire\ProjectCreate;
use App\Livewire\ProjectEditModal;
use App\Models\Package;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectScheduleOverrideUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_create_persists_portal_and_social_overrides(): void
    {
        Queue::fake();
        $package = $this->createPackage();

        Livewire::test(ProjectCreate::class)
            ->set('packageId', $package->id)
            ->set('name', 'Project Override Baru')
            ->set('topicsString', 'Seno Aji, Kaltim')
            ->set('telegramChatId', '10022334455')
            ->set('news_run_times_override', ['07:00', '19:00'])
            ->set('social_run_times_override', ['10:00', '16:00', '22:00'])
            ->call('createProject')
            ->assertHasNoErrors();

        $project = Project::where('name', 'Project Override Baru')->firstOrFail();

        $this->assertSame($package->id, $project->package_id);
        $this->assertSame(['07:00', '19:00'], $project->news_run_times_override);
        $this->assertSame(['10:00', '16:00', '22:00'], $project->social_run_times_override);
    }

    public function test_project_create_rejects_partial_portal_override(): void
    {
        Queue::fake();
        $package = $this->createPackage();

        Livewire::test(ProjectCreate::class)
            ->set('packageId', $package->id)
            ->set('name', 'Project Override Partial')
            ->set('topicsString', 'Seno Aji, Kaltim')
            ->set('telegramChatId', '10022334455')
            ->set('news_run_times_override', ['07:00', ''])
            ->set('social_run_times_override', [])
            ->call('createProject')
            ->assertHasErrors(['news_run_times_override']);
    }

    public function test_project_edit_persists_and_allows_null_overrides(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $package = $this->createPackage();

        $project = Project::create([
            'name' => 'Project Edit Override',
            'topics' => ['seno aji'],
            'package_id' => $package->id,
            'news_run_times_override' => ['08:00', '20:00'],
            'social_run_times_override' => ['09:00', '15:00', '21:00'],
        ]);

        Livewire::actingAs($admin)
            ->test(ProjectEditModal::class)
            ->call('open', $project->id)
            ->set('telegramChatId', '10022334455')
            ->set('news_run_times_override', ['', ''])
            ->set('social_run_times_override', ['', '', ''])
            ->call('updateProject')
            ->assertHasNoErrors();

        $project->refresh();

        $this->assertNull($project->news_run_times_override);
        $this->assertNull($project->social_run_times_override);
        $this->assertSame($package->id, $project->package_id);
    }

    public function test_project_edit_rejects_partial_social_override(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $package = $this->createPackage();

        $project = Project::create([
            'name' => 'Project Edit Partial',
            'topics' => ['seno aji'],
            'package_id' => $package->id,
        ]);

        Livewire::actingAs($admin)
            ->test(ProjectEditModal::class)
            ->call('open', $project->id)
            ->set('telegramChatId', '10022334455')
            ->set('social_run_times_override', ['10:00', '', '22:00'])
            ->call('updateProject')
            ->assertHasErrors(['social_run_times_override']);
    }

    protected function createPackage(): Package
    {
        return Package::create([
            'name' => 'Schedule Package',
            'price' => 100000,
            'use_portal' => true,
            'news_interval_minutes' => 5,
            'social_interval_minutes' => 10,
            'news_runs_per_day' => 2,
            'news_run_times' => ['08:00', '20:00'],
            'social_runs_per_day' => 3,
            'social_run_times' => ['09:00', '15:00', '21:00'],
            'is_active' => true,
        ]);
    }
}
