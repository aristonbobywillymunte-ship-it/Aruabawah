<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\ApifySetting;
use App\Models\ApifyActor;
use App\Models\Project;
use App\Models\ApifyDispatchState;
use App\Jobs\ApifyScrapingJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;

class ApifyTokenFailoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup initial project and actor
        Project::create(['id' => 1, 'name' => 'Test', 'topics' => ['test'], 'is_active' => true]);
        
        ApifyActor::create([
            'id' => 1,
            'platform' => 'Facebook',
            'actor_name' => 'FB Scraper',
            'actor_slug' => 'test/fb-scraper',
            'function_type' => 'Search Post',
            'status' => 'active',
            'priority' => 1,
        ]);
        
        ApifySetting::create([
            'api_token' => 'token-main',
            'connection_status' => 'connected',
            'active_token_index' => 0,
            
            'api_token_backup_1' => 'token-bk1',
            'connection_status_backup_1' => 'connected',
            
            'api_token_backup_2' => 'token-bk2',
            'connection_status_backup_2' => 'connected',
            
            'api_token_backup_3' => 'token-bk3',
            'connection_status_backup_3' => 'error', // invalid
        ]);
    }

    protected function getJobParams(): array
    {
        return [
            'platform' => 'Facebook',
            'keyword' => 'test',
            'project_id' => 1,
            'actor_id' => 1
        ];
    }

    public function test_main_quota_fails_rotates_to_backup_1_and_succeeds()
    {
        Queue::fake();
        
        Http::fake([
            'api.apify.com/v2/acts/*' => Http::sequence()
                ->push(['error' => ['message' => 'monthly usage hard limit exceeded']], 403) // Token main gagal
                ->push(['data' => ['id' => 'run-bk1', 'defaultDatasetId' => 'ds-bk1']], 201), // Token bk1 sukses
                
            'api.apify.com/v2/actor-runs/*' => Http::response(['data' => ['status' => 'SUCCEEDED']], 200),
            'api.apify.com/v2/datasets/*' => Http::response([], 200)
        ]);

        $job = new ApifyScrapingJob($this->getJobParams());
        $job->handle();

        $setting = ApifySetting::first();
        // Token active berpindah ke index 1 (backup 1)
        $this->assertEquals(1, $setting->active_token_index);
        
        // Memastikan tidak ada dispatch baru
        Queue::assertNothingPushed();
    }

    public function test_main_and_backup1_fail_rotates_to_backup_2_and_succeeds()
    {
        Http::fake([
            'api.apify.com/v2/acts/*' => Http::sequence()
                ->push(['error' => ['message' => 'monthly usage hard limit exceeded']], 403) // main gagal
                ->push(['error' => ['message' => 'invalid token']], 401) // bk1 gagal
                ->push(['data' => ['id' => 'run-bk2', 'defaultDatasetId' => 'ds-bk2']], 201), // bk2 sukses
                
            'api.apify.com/v2/actor-runs/*' => Http::response(['data' => ['status' => 'SUCCEEDED']], 200),
            'api.apify.com/v2/datasets/*' => Http::response([], 200)
        ]);

        $job = new ApifyScrapingJob($this->getJobParams());
        $job->handle();

        $setting = ApifySetting::first();
        $this->assertEquals(2, $setting->active_token_index);
    }

    public function test_backup_with_status_not_connected_is_skipped()
    {
        // bk3 berstatus 'error', main dan bk1 gagal limit, harusnya lari ke bk2
        Http::fake([
            'api.apify.com/v2/acts/*' => Http::sequence()
                ->push(['error' => ['message' => 'hard limit exceeded']], 403) // main gagal
                ->push(['error' => ['message' => 'hard limit exceeded']], 403) // bk1 gagal
                ->push(['data' => ['id' => 'run-bk2', 'defaultDatasetId' => 'ds-bk2']], 201), // bk2 sukses
                
            'api.apify.com/v2/actor-runs/*' => Http::response(['data' => ['status' => 'SUCCEEDED']], 200),
            'api.apify.com/v2/datasets/*' => Http::response([], 200)
        ]);
        
        // Memastikan bk3 akan di-skip oleh getNextEligibleTokenIndex
        $setting = ApifySetting::first();
        $setting->update(['active_token_index' => 0]);

        $job = new ApifyScrapingJob($this->getJobParams());
        $job->handle();

        $this->assertEquals(2, ApifySetting::first()->active_token_index);
    }

    public function test_if_only_main_token_and_it_fails_job_stops_without_redispatch()
    {
        Queue::fake();
        
        // Kosongkan semua backup
        $setting = ApifySetting::first();
        $setting->update([
            'api_token_backup_1' => null,
            'api_token_backup_2' => null,
            'api_token_backup_3' => null,
        ]);

        Http::fake([
            'api.apify.com/v2/acts/*' => Http::response(['error' => ['message' => 'monthly usage hard limit exceeded']], 403),
        ]);

        $job = new ApifyScrapingJob($this->getJobParams());
        $job->handle();

        Queue::assertNothingPushed();
        
        $actor = ApifyActor::find(1);
        $this->assertStringContainsString('APIFY_ALL_TOKENS_EXHAUSTED', $actor->last_run_message);
        $this->assertEquals('failed', $actor->last_run_status);
    }

    public function test_all_tokens_exhausted_results_in_exhausted_status()
    {
        Queue::fake();

        // Main, bk1, bk2 akan gagal limit. bk3 status error jadi di-skip.
        Http::fake([
            'api.apify.com/v2/acts/*' => Http::sequence()
                ->push(['error' => ['message' => 'hard limit exceeded']], 403) // main
                ->push(['error' => ['message' => 'hard limit exceeded']], 403) // bk1
                ->push(['error' => ['message' => 'hard limit exceeded']], 403), // bk2
        ]);

        $job = new ApifyScrapingJob($this->getJobParams());
        $job->handle();

        Queue::assertNothingPushed();
        $actor = ApifyActor::find(1);
        $this->assertStringContainsString('APIFY_ALL_TOKENS_EXHAUSTED', $actor->last_run_message);
    }

    public function test_normal_http_5xx_does_not_rotate_token()
    {
        Queue::fake();

        Http::fake([
            'api.apify.com/v2/acts/*' => Http::response('Internal Server Error', 500),
        ]);

        $job = new ApifyScrapingJob($this->getJobParams());
        $job->handle();

        Queue::assertNothingPushed();
        
        $setting = ApifySetting::first();
        // Tetap di token main (index 0)
        $this->assertEquals(0, $setting->active_token_index);
        
        $actor = ApifyActor::find(1);
        $this->assertEquals('failed', $actor->last_run_status);
        $this->assertStringNotContainsString('APIFY_ALL_TOKENS_EXHAUSTED', $actor->last_run_message);
    }

    public function test_successful_token_becomes_active_index()
    {
        // Set awal token aktif ke backup 2
        ApifySetting::first()->update(['active_token_index' => 2]);

        Http::fake([
            'api.apify.com/v2/acts/*' => Http::sequence()
                ->push(['data' => ['id' => 'run-bk2', 'defaultDatasetId' => 'ds-bk2']], 201),
                
            'api.apify.com/v2/actor-runs/*' => Http::response(['data' => ['status' => 'SUCCEEDED']], 200),
            'api.apify.com/v2/datasets/*' => Http::response([], 200)
        ]);

        $job = new ApifyScrapingJob($this->getJobParams());
        $job->handle();

        // Harus tetap index 2
        $this->assertEquals(2, ApifySetting::first()->active_token_index);
    }

    public function test_token_value_is_not_exposed_in_error_logs()
    {
        Http::fake([
            'api.apify.com/v2/acts/*' => Http::response(['error' => ['message' => 'hard limit exceeded']], 403),
        ]);

        $job = new ApifyScrapingJob($this->getJobParams());
        $job->handle();

        $actor = ApifyActor::find(1);
        $this->assertStringNotContainsString('token-main', $actor->last_run_message);
        $this->assertStringNotContainsString('token-bk1', $actor->last_run_message);
    }
}
