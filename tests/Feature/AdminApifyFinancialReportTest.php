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

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::factory()->create(['role' => 'admin']);
    }

    private function createDependencies()
    {
        // Packages
        $packageA = DB::table('packages')->insertGetId(['name' => 'Package A', 'created_at' => now(), 'updated_at' => now()]);
        $packageB = DB::table('packages')->insertGetId(['name' => 'Package B', 'created_at' => now(), 'updated_at' => now()]);

        // Projects
        $projectA = DB::table('projects')->insertGetId(['name' => 'Project A', 'package_id' => $packageA, 'created_at' => now(), 'updated_at' => now()]);
        $projectB = DB::table('projects')->insertGetId(['name' => 'Project B', 'package_id' => $packageB, 'created_at' => now(), 'updated_at' => now()]);

        // Actor
        $actorId = DB::table('apify_actors')->insertGetId([
            'platform' => 'Facebook',
            'actor_name' => 'FB Scraper',
            'actor_slug' => 'apify/fb',
            'function_type' => 'Search Post',
            'status' => 'active',
            'priority' => 1,
            'default_limit' => 10,
            'interval_minutes' => 60,
            'memory_limit' => 512,
            'range_mode' => '7d',
            'keyword_field_mapping' => 'test',
        ]);

        // Package Actors mapping (Cost Limit source)
        DB::table('package_actors')->insert([
            ['package_id' => $packageA, 'apify_actor_id' => $actorId, 'cost_per_run_usd' => 0.5000, 'created_at' => now(), 'updated_at' => now()],
            ['package_id' => $packageB, 'apify_actor_id' => $actorId, 'cost_per_run_usd' => 1.5000, 'created_at' => now(), 'updated_at' => now()],
        ]);

        return [$projectA, $projectB, $actorId];
    }

    public function test_run_with_cost_greater_than_zero_shows_berhasil_and_limits()
    {
        [$projectA, $projectB, $actorId] = $this->createDependencies();

        DB::table('apify_dispatch_states')->insert([
            'dispatch_key' => 'key1',
            'project_id' => $projectA,
            'platform' => 'Facebook',
            'actor_id' => $actorId,
            'actual_cost_usd' => 0.1234,
            'items_collected' => 10,
            'status' => 'success',
            'completed_at' => now(),
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\ApifyFinancialReport::class)
            ->assertSee('Project A')
            ->assertSee('0.5000') // Package A cost limit
            ->assertSee('0.1234') // Actual cost
            ->assertSee('Berhasil');
    }

    public function test_run_zero_cost_and_zero_items_shows_tidak_ada_hasil()
    {
        [$projectA, $projectB, $actorId] = $this->createDependencies();

        DB::table('apify_dispatch_states')->insert([
            'dispatch_key' => 'key2',
            'project_id' => $projectA,
            'platform' => 'Facebook',
            'actor_id' => $actorId,
            'actual_cost_usd' => 0.0000,
            'items_collected' => 0,
            'status' => 'success',
            'completed_at' => now(),
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\ApifyFinancialReport::class)
            ->assertSee('Tidak ada hasil')
            ->assertSee('Actor selesai tetapi tidak menghasilkan item');
    }

    public function test_all_tokens_exhausted_shows_correct_message()
    {
        [$projectA, $projectB, $actorId] = $this->createDependencies();

        DB::table('apify_dispatch_states')->insert([
            'dispatch_key' => 'key3',
            'project_id' => $projectA,
            'platform' => 'Facebook',
            'actor_id' => $actorId,
            'actual_cost_usd' => 0.0000,
            'items_collected' => 0,
            'status' => 'failed',
            'last_error_code' => 'APIFY_ALL_TOKENS_EXHAUSTED',
            'completed_at' => now(),
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\ApifyFinancialReport::class)
            ->assertSee('Token/kuota tidak tersedia');
    }

    public function test_monthly_quota_exhausted_shows_correct_message()
    {
        [$projectA, $projectB, $actorId] = $this->createDependencies();

        DB::table('apify_dispatch_states')->insert([
            'dispatch_key' => 'key4',
            'project_id' => $projectA,
            'platform' => 'Facebook',
            'actor_id' => $actorId,
            'actual_cost_usd' => 0.0000,
            'items_collected' => 0,
            'status' => 'failed',
            'last_error_message' => 'monthly usage hard limit exceeded',
            'completed_at' => now(),
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\ApifyFinancialReport::class)
            ->assertSee('Kuota Apify habis');
    }

    public function test_cost_limit_reached_with_items_shows_selesai_sebagian()
    {
        [$projectA, $projectB, $actorId] = $this->createDependencies();

        DB::table('apify_dispatch_states')->insert([
            'dispatch_key' => 'key5',
            'project_id' => $projectA,
            'platform' => 'Facebook',
            'actor_id' => $actorId,
            'actual_cost_usd' => 0.5000,
            'items_collected' => 50,
            'status' => 'success',
            'last_error_message' => 'partial: cost limit reached',
            'completed_at' => now(),
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\ApifyFinancialReport::class)
            ->assertSee('Selesai sebagian')
            ->assertSee('batas biaya Paket');
    }

    public function test_dataset_fetch_failure_shows_correct_message()
    {
        [$projectA, $projectB, $actorId] = $this->createDependencies();

        DB::table('apify_dispatch_states')->insert([
            'dispatch_key' => 'key6',
            'project_id' => $projectA,
            'platform' => 'Facebook',
            'actor_id' => $actorId,
            'actual_cost_usd' => 0.0020,
            'items_collected' => 0,
            'status' => 'failed',
            'last_error_message' => 'failed to fetch dataset from apify',
            'completed_at' => now(),
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\ApifyFinancialReport::class)
            ->assertSee('Gagal mengambil hasil');
    }

    public function test_project_filter_works_and_isolation()
    {
        [$projectA, $projectB, $actorId] = $this->createDependencies();

        DB::table('apify_dispatch_states')->insert([
            ['dispatch_key' => 'runA', 'project_id' => $projectA, 'platform' => 'Facebook', 'actor_id' => $actorId, 'actual_cost_usd' => 0.1, 'items_collected' => 1, 'status' => 'success', 'completed_at' => now()],
            ['dispatch_key' => 'runB', 'project_id' => $projectB, 'platform' => 'Facebook', 'actor_id' => $actorId, 'actual_cost_usd' => 0.2, 'items_collected' => 2, 'status' => 'success', 'completed_at' => now()],
        ]);

        // Filter Project A
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\ApifyFinancialReport::class)
            ->set('projectId', $projectA)
            ->assertSee('Project A')
            ->assertDontSee('Project B')
            ->assertSee('0.5000') // Project A cost limit
            ->assertDontSee('1.5000'); // Project B cost limit must not leak
    }
}
