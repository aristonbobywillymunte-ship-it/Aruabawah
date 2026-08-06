<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AdminApifyFinancialReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->regularUser = User::factory()->create([
            'role' => 'user',
        ]);
    }

    public function test_non_admins_cannot_access_financial_report()
    {
        $this->actingAs($this->regularUser)
            ->get(route('admin.apify-financials'))
            ->assertStatus(403);
    }

    public function test_admins_can_access_financial_report()
    {
        $this->actingAs($this->adminUser)
            ->get(route('admin.apify-financials'))
            ->assertStatus(200)
            ->assertSee('Apify Billing & Usage');
    }

    public function test_financial_report_renders_with_mocked_data()
    {
        // Mock a project
        $projectId = DB::table('projects')->insertGetId([
            'name' => 'Test Project',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mock an actor
        $actorId = DB::table('apify_actors')->insertGetId([
            'platform' => 'Facebook',
            'actor_name' => 'Facebook Scraper',
            'actor_slug' => 'apify/facebook-scraper',
            'function_type' => 'Search Post',
            'status' => 'active',
            'priority' => 1,
            'default_limit' => 10,
            'interval_minutes' => 60,
            'memory_limit' => 512,
            'range_mode' => '7d',
            'keyword_field_mapping' => 'test',
        ]);

        // Mock a dispatch state run
        DB::table('apify_dispatch_states')->insert([
            'project_id' => $projectId,
            'platform' => 'Facebook',
            'actor_id' => $actorId,
            'keyword' => 'test keyword',
            'run_id' => 'test-run-123',
            'actual_cost_usd' => 1.5,
            'items_collected' => 100,
            'run_duration_secs' => 120,
            'completed_at' => now()->subDay(),
            'status' => 'success',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\ApifyFinancialReport::class)
            ->assertStatus(200)
            ->assertSee('Facebook')
            ->assertSee('Facebook Scraper')
            ->assertSee('Test Project')
            ->assertSee('1.5000') // Check cost formatting
            ->assertSee('100 Item');
    }

    public function test_open_items_for_post()
    {
        $projectId = DB::table('projects')->insertGetId([
            'name' => 'Test Project',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('social_media_items')->insert([
            'project_id' => $projectId,
            'platform' => 'Facebook',
            'post_url' => 'https://facebook.com/post/1',
            'content' => 'This is a test post',
            'author_name' => 'John Doe',
            'posted_at' => now(),
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\ApifyFinancialReport::class)
            ->call('openItems', $projectId, 'Facebook', 'test', 'run-1', 'Test Project')
            ->assertSet('showItemsModal', true)
            ->assertSet('selectedPlatform', 'Facebook')
            ->assertSet('isCommentModal', false)
            ->assertSee('John Doe'); // Modal should render the name from social_media_items
    }
}
