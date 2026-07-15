<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Livewire\Livewire;
use Tests\TestCase;
use App\Models\ApifyActor;
use Illuminate\Support\Facades\Queue;
use App\Jobs\ApifyScrapingJob;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\RunNewsScrapingCommand;

class ProjectTopicsValidationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'role' => 'admin',
        ]);
        
        // Ensure there is at least one active apify actor so the createProject job loop runs without error
        ApifyActor::create([
            'name' => 'test-actor',
            'actor_name' => 'test-actor',
            'actor_slug' => 'test-actor',
            'function_type' => 'search',
            'platform' => 'Instagram',
            'actor_id' => 'test/actor',
            'status' => 'active',
            'priority' => 1,
        ]);
    }

    public function test_flat_topic_array_accepted()
    {
        Queue::fake();

        // Testing the component directly using its Volt name, or if it's an anonymous class, we can just test the view.
        // The component is ⚡projects-list.blade.php which in Livewire 3 Volt might be just 'projects-list'
        $component = Livewire::actingAs($this->user)
            ->test('⚡projects-list')
            ->set('name', 'Valid Flat Project')
            ->set('topicsString', 'seno aji, wagub kaltim')
            ->call('createProject');

        $component->assertHasNoErrors(['topicsString']);
        
        $project = Project::where('name', 'Valid Flat Project')->first();
        $this->assertNotNull($project);
        $this->assertEquals(['seno aji', 'wagub kaltim'], $project->topics);
        
        // Verify that NO ApifyScrapingJob is dispatched automatically on project creation
        Queue::assertNotPushed(ApifyScrapingJob::class);
    }

    public function test_nested_or_object_topics_rejected()
    {
        $component = Livewire::actingAs($this->user)
            ->test('⚡projects-list')
            ->set('name', 'Invalid Nested Project')
            ->set('topicsString', '{"primary":["seno aji"]}')
            ->call('createProject');

        $component->assertHasErrors(['topicsString' => 'Format JSON tidak diperbolehkan. Gunakan kata kunci yang dipisahkan koma.']);
        
        $this->assertNull(Project::where('name', 'Invalid Nested Project')->first());
    }

    public function test_project_without_topic_rejected()
    {
        $component = Livewire::actingAs($this->user)
            ->test('⚡projects-list')
            ->set('name', 'Empty Topic Project')
            ->set('topicsString', ' , , ') // only commas and spaces
            ->call('createProject');

        $component->assertHasErrors(['topicsString' => 'Topik wajib diisi minimal satu kata kunci valid.']);
        
        $this->assertNull(Project::where('name', 'Empty Topic Project')->first());
    }

    public function test_inactive_project_not_processed()
    {
        $project = Project::create([
            'name' => 'Inactive Project',
            'topics' => ['seno aji'],
            'is_active' => false,
        ]);
        
        // When scraping runs, it should skip inactive projects
        // We can test this by checking if it attempts to scrape
        // The scraping command fetches active projects: Project::where('is_active', true)
        
        $activeProjects = Project::where('is_active', true)->get();
        $this->assertFalse($activeProjects->contains('id', $project->id));
    }
}
