<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Models\Project;
use App\Livewire\ProjectCreate;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use App\Jobs\BootstrapNewProjectScrapingJob;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectCreatePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Buat package dummy untuk test
        Package::create([
            'name' => 'Basic Package',
            'is_active' => true,
            'price' => 100000,
        ]);
        
        Package::create([
            'name' => 'Inactive Package',
            'is_active' => false,
            'price' => 50000,
        ]);
    }

    public function test_create_project_page_is_accessible_to_authenticated_users()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('projects.create'));

        $response->assertStatus(200);
        $response->assertSeeLivewire(ProjectCreate::class);
    }

    public function test_create_project_page_redirects_unauthenticated_users()
    {
        $response = $this->get(route('projects.create'));

        $response->assertRedirect('/login');
    }

    public function test_validation_fails_when_fields_are_missing()
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ProjectCreate::class)
            ->call('createProject')
            ->assertHasErrors(['name', 'topicsString', 'telegramChatId', 'packageId']);
    }

    public function test_validation_fails_when_package_is_inactive()
    {
        $user = User::factory()->create();
        $inactivePackage = Package::where('is_active', false)->first();

        Livewire::actingAs($user)
            ->test(ProjectCreate::class)
            ->set('name', 'Test Project')
            ->set('topicsString', 'Keyword 1, Keyword 2')
            ->set('telegramChatId', '123456789')
            ->set('packageId', $inactivePackage->id)
            ->call('createProject')
            ->assertStatus(404); // findOrFail on is_active=true will throw ModelNotFoundException -> 404
    }

    public function test_project_can_be_created_successfully()
    {
        Queue::fake();

        $user = User::factory()->create();
        $activePackage = Package::where('is_active', true)->first();

        Livewire::actingAs($user)
            ->test(ProjectCreate::class)
            ->set('name', 'Test Project Success')
            ->set('topicsString', 'Jokowi, Prabowo')
            ->set('telegramChatId', '123456789')
            ->set('packageId', $activePackage->id)
            ->call('createProject')
            ->assertRedirect('/');

        // Verify project created in DB
        $this->assertDatabaseHas('projects', [
            'name' => 'Test Project Success',
            'package_id' => $activePackage->id,
        ]);

        $project = Project::where('name', 'Test Project Success')->first();
        
        // Verify topics stored correctly as JSON
        $this->assertEquals(['Jokowi', 'Prabowo'], $project->topics);

        // Verify telegram recipients stored
        $this->assertDatabaseHas('project_telegram_recipients', [
            'project_id' => $project->id,
            'chat_id' => '123456789'
        ]);

        // Verify job dispatched
        Queue::assertPushed(BootstrapNewProjectScrapingJob::class, function ($job) use ($project) {
            return $job->projectId === $project->id;
        });
    }

    public function test_json_topics_are_rejected()
    {
        $user = User::factory()->create();
        $activePackage = Package::where('is_active', true)->first();

        Livewire::actingAs($user)
            ->test(ProjectCreate::class)
            ->set('name', 'Test Project JSON')
            ->set('topicsString', '{"key": "value"}')
            ->set('telegramChatId', '123456789')
            ->set('packageId', $activePackage->id)
            ->call('createProject')
            ->assertHasErrors(['topicsString' => 'Format JSON tidak diperbolehkan. Gunakan kata kunci yang dipisahkan koma.']);
    }
}
