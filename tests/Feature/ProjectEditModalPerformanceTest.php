<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Package;
use Livewire\Livewire;
use App\Livewire\ProjectEditModal;
use App\Http\Livewire\ProjectsList;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use App\Jobs\ProjectContentResyncJob;

class ProjectEditModalPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $project;
    protected $package;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        $this->package = Package::create([
            'name' => 'Pro',
            'price' => 500000,
            'is_active' => true,
        ]);
        
        $this->project = Project::create([
            'name' => 'Test Project Performance',
            'user_id' => $this->user->id,
            'package_id' => $this->package->id,
            'topics' => ['test', 'performance'],
        ]);
        
        DB::table('project_telegram_recipients')->insert([
            'project_id' => $this->project->id,
            'chat_id' => '1234567890',
            'is_active' => true,
        ]);
    }

    public function test_projects_list_renders_without_edit_state()
    {
        $this->actingAs($this->user);
        
        Livewire::test(ProjectsList::class)
            ->assertSet('showConfirmModal', false)
            ->assertDontSeeHtml('wire:model="editName"');
    }

    public function test_project_edit_modal_component_loads_project_data_correctly()
    {
        $this->actingAs($this->user);
        
        Livewire::test(ProjectEditModal::class)
            ->call('open', $this->project->id)
            ->assertSet('editProjectId', $this->project->id)
            ->assertSet('editName', 'Test Project Performance')
            ->assertSet('editTopicsString', 'test, performance')
            ->assertSet('telegramChatId', '1234567890')
            ->assertSet('showModal', true);
    }

    public function test_project_edit_modal_updates_project_and_dispatches_event()
    {
        $this->actingAs($this->user);
        Queue::fake();
        
        Livewire::test(ProjectEditModal::class)
            ->call('open', $this->project->id)
            ->set('editName', 'Updated Project Name')
            ->set('editTopicsString', 'updated, topics')
            ->call('updateProject')
            ->assertDispatched('project-updated', projectId: $this->project->id)
            ->assertSet('showModal', false);
            
        Queue::assertPushed(ProjectContentResyncJob::class, function ($job) {
            return $job->project->id === $this->project->id;
        });
            
        $this->assertDatabaseHas('projects', [
            'id' => $this->project->id,
            'name' => 'Updated Project Name',
        ]);
    }

    public function test_project_edit_modal_validates_input()
    {
        $this->actingAs($this->user);
        
        Livewire::test(ProjectEditModal::class)
            ->call('open', $this->project->id)
            ->set('editName', '')
            ->call('updateProject')
            ->assertHasErrors(['editName' => 'required']);
    }

    public function test_unauthorized_user_cannot_edit_project()
    {
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);
        
        // This should fail because the project belongs to $this->user
        Livewire::test(ProjectEditModal::class)
            ->call('open', $this->project->id)
            ->assertForbidden(); // Assuming accessibleBy uses global scope or gates that throw 403, or findOrFail throws 404
    }
}
