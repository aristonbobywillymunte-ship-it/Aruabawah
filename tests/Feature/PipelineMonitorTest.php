<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Article;
use App\Models\Project;
use App\Models\AiAnalysisDispatchState;
use App\Livewire\Admin\PipelineMonitor;
use Livewire\Livewire;

class PipelineMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_monitor_shows_single_row_for_multi_project_article()
    {
        $projectA = Project::create(['name' => 'Project A', 'topics' => ['A'], 'is_active' => true]);
        $projectB = Project::create(['name' => 'Project B', 'topics' => ['B'], 'is_active' => true]);

        $article = Article::create([
            'title' => 'Test Article',
            'content' => 'Content',
            'url' => 'http://example.com',
            'sentiment' => 'neutral',
            'source_name' => 'test'
        ]);

        $article->projects()->attach([$projectA->id, $projectB->id]);

        $component = Livewire::test(PipelineMonitor::class)
            ->set('activeTab', 'scraping');

        $items = $component->viewData('items');
        
        $this->assertCount(1, $items); // Only one row!
        
        $firstItem = $items->first();
        $this->assertEquals($article->id, $firstItem->id);
        
        // Ensure both projects are loaded in the eager loaded relation
        $this->assertCount(2, $firstItem->projects);
        $this->assertTrue($firstItem->projects->contains('id', $projectA->id));
        $this->assertTrue($firstItem->projects->contains('id', $projectB->id));
    }

    public function test_filter_project_finds_article_without_duplicate()
    {
        $projectA = Project::create(['name' => 'Project A', 'topics' => ['A'], 'is_active' => true]);
        $projectB = Project::create(['name' => 'Project B', 'topics' => ['B'], 'is_active' => true]);

        $article = Article::create([
            'title' => 'Test Article',
            'content' => 'Content',
            'url' => 'http://example.com',
            'sentiment' => 'neutral',
            'source_name' => 'test'
        ]);

        $article->projects()->attach([$projectA->id, $projectB->id]);

        $componentA = Livewire::test(PipelineMonitor::class)
            ->set('activeTab', 'scraping')
            ->set('filterProject', (string)$projectA->id);

        $itemsA = $componentA->viewData('items');
        $this->assertCount(1, $itemsA);

        $componentB = Livewire::test(PipelineMonitor::class)
            ->set('activeTab', 'scraping')
            ->set('filterProject', (string)$projectB->id);

        $itemsB = $componentB->viewData('items');
        $this->assertCount(1, $itemsB);
    }

    public function test_failed_ai_state_shows_safe_failure_category(): void
    {
        $project = Project::create(['name' => 'Project Safe Label', 'topics' => ['A'], 'is_active' => true]);

        AiAnalysisDispatchState::create([
            'analyzable_type' => 'article',
            'analyzable_id' => 77,
            'project_id' => $project->id,
            'prompt_template_id' => null,
            'provider_context_hash' => str_repeat('a', 64),
            'dispatch_key' => str_repeat('b', 64),
            'status' => 'failed',
            'attempts' => 1,
            'failure_category' => 'rate_limit',
            'last_error_code' => 'quota_exhausted',
            'error_message' => 'Provider rate limit reached. Retrying later.',
            'last_attempt_at' => now(),
            'last_failed_at' => now(),
            'completed_at' => now(),
            'meta_json' => [],
        ]);

        $component = Livewire::test(PipelineMonitor::class)
            ->set('activeTab', 'queue-failed');

        $items = $component->viewData('items');
        $this->assertCount(1, $items);
        $this->assertSame('rate_limit', $items->first()->failure_category);
        $this->assertSame('quota_exhausted', $items->first()->last_error_code);
        $this->assertSame('Provider rate limit reached. Retrying later.', $items->first()->error_message);
    }

    public function test_pipeline_monitor_shows_backfill_stats(): void
    {
        // 1. Setup candidate (analysis_status=success, reach_method=ai_reader_estimate_v1, project_estimated_readers=null)
        $project = Project::create(['name' => 'Project Backfill', 'topics' => ['A'], 'is_active' => true]);
        
        $article = Article::create([
            'title' => 'Test Backfill',
            'content' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. '.str_repeat('word ', 50),
            'url' => 'http://example.com/test',
            'sentiment' => 'neutral',
            'source_name' => 'test'
        ]);
        $article->projects()->attach($project->id);

        \App\Models\AiAnalysisResult::create([
            'article_id' => $article->id,
            'social_media_item_id' => null,
            'summary' => 'Test',
            'sentiment' => 'neutral',
            'sentiment_score' => 0,
            'main_issue' => 'A',
            'risk_level' => 'low',
            'risk_reason' => 'Test',
            'reach_level' => 'low',
            'reach_trend' => 'stable',
            'reach_source' => 'test',
            'reach_confidence' => 0.8,
            'reach_reason' => 'test',
            'recommendation' => 'test',
            'raw_response' => '{}',
            'analysis_status' => 'success',
            'reach_method' => 'ai_reader_estimate_v1',
            'project_estimated_readers' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Setup Cooldown Provider
        \App\Models\AiProvider::create([
            'name' => 'Provider Test',
            'provider_type' => 'Gemini',
            'model_name' => 'gemini-1.5-pro',
            'base_url' => 'http://example.com',
            'api_key' => 'secret',
            'is_active' => true,
            'priority' => 1,
            'cooldown_until' => now()->addHours(2),
            'last_failure_code' => 'daily_quota_exhausted'
        ]);

        // 3. View Monitor
        $component = Livewire::test(PipelineMonitor::class)
            ->set('activeTab', 'scraping');

        $backfillStats = $component->viewData('backfillStats');
        
        $this->assertNotNull($backfillStats);
        $this->assertEquals(1, $backfillStats['candidates']);
        $this->assertEquals(10, $backfillStats['batch_size']);
        $this->assertEquals('5 menit', $backfillStats['interval']);
        $this->assertCount(1, $backfillStats['cooldown_providers']);
        $this->assertEquals('Provider Test', $backfillStats['cooldown_providers']->first()->name);
        $this->assertEquals('daily_quota_exhausted', $backfillStats['cooldown_providers']->first()->last_failure_code);
    }

    public function test_clear_all_pending_ai_states_keeps_fresh_retry_wait_states(): void
    {
        $project = Project::create(['name' => 'Project Retry Safe', 'topics' => ['A'], 'is_active' => true]);

        $queued = AiAnalysisDispatchState::create([
            'analyzable_type' => 'article',
            'analyzable_id' => 101,
            'project_id' => $project->id,
            'prompt_template_id' => null,
            'provider_context_hash' => str_repeat('a', 64),
            'dispatch_key' => str_repeat('c', 64),
            'status' => 'queued',
            'attempts' => 0,
            'meta_json' => [],
        ]);

        $retryWait = AiAnalysisDispatchState::create([
            'analyzable_type' => 'article',
            'analyzable_id' => 102,
            'project_id' => $project->id,
            'prompt_template_id' => null,
            'provider_context_hash' => str_repeat('b', 64),
            'dispatch_key' => str_repeat('d', 64),
            'status' => 'retry_wait',
            'attempts' => 1,
            'next_retry_at' => now()->addMinutes(30),
            'meta_json' => [],
        ]);

        $processing = AiAnalysisDispatchState::create([
            'analyzable_type' => 'article',
            'analyzable_id' => 103,
            'project_id' => $project->id,
            'prompt_template_id' => null,
            'provider_context_hash' => str_repeat('e', 64),
            'dispatch_key' => str_repeat('f', 64),
            'status' => 'processing',
            'attempts' => 1,
            'meta_json' => [],
        ]);

        Livewire::test(PipelineMonitor::class)
            ->call('clearAllPendingAiStates')
            ->assertDispatched('admin-toast');

        $this->assertSoftDeleted('ai_analysis_dispatch_states', ['id' => $queued->id]);
        $this->assertDatabaseHas('ai_analysis_dispatch_states', [
            'id' => $retryWait->id,
            'status' => 'retry_wait',
        ]);
        $this->assertDatabaseHas('ai_analysis_dispatch_states', [
            'id' => $processing->id,
            'status' => 'processing',
        ]);
    }
}
