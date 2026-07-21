<?php

namespace Tests\Feature;

use App\Jobs\AiAnalysisJob;
use App\Models\AiAnalysisDispatchState;
use App\Models\AiAnalysisResult;
use App\Models\AiPromptTemplate;
use App\Models\AiProvider;
use App\Models\Article;
use App\Models\Project;
use App\Models\SocialMediaItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiRequeueOrphanQueuedStatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_change_data(): void
    {
        Queue::fake();

        [$project, $article, $template] = $this->seedAiContext();
        $state = $this->createQueuedState($project, $article, $template);

        $this->artisan('ai:requeue-orphan-queued-states', ['--limit' => 5])
            ->assertExitCode(0);

        Queue::assertNothingPushed();

        $state->refresh();
        $this->assertSame('queued', $state->status);
        $this->assertSame(0, (int) $state->attempts);
        $this->assertNull($state->last_error_code);
    }

    public function test_apply_limit_one_dispatches_job_to_redis_ai_queue_with_no_telegram(): void
    {
        Queue::fake();

        [$project, $article, $template] = $this->seedAiContext();
        $state = $this->createQueuedState($project, $article, $template);

        $this->artisan('ai:requeue-orphan-queued-states', ['--limit' => 1, '--apply' => true])
            ->assertExitCode(0);

        Queue::assertPushedOn('ai-analysis', AiAnalysisJob::class);
        Queue::assertPushed(AiAnalysisJob::class, function (AiAnalysisJob $job) use ($article, $project): bool {
            return (int) $job->payload['id'] === $article->id
                && (int) $job->payload['project_id'] === $project->id
                && ($job->payload['no_telegram'] ?? false) === true
                && $job->connection === 'redis-ai'
                && $job->queue === 'ai-analysis';
        });

        $state->refresh();
        $this->assertSame('queued', $state->status);
        $this->assertSame(0, (int) $state->attempts);
    }

    public function test_state_with_ai_result_is_not_requeued(): void
    {
        Queue::fake();

        [$project, $article, $template] = $this->seedAiContext();
        AiAnalysisResult::create([
            'article_id' => $article->id,
            'summary' => 'Sudah ada hasil AI',
            'sentiment' => 'neutral',
            'sentiment_score' => 0.0,
            'main_issue' => 'Test',
            'risk_level' => 'low',
            'reach_level' => 'Low',
            'reach_trend' => 'stable',
            'reach_source' => 'test',
            'reach_confidence' => 'medium',
            'reach_reason' => 'test',
            'recommendation' => 'test',
            'raw_response' => '{}',
            'analysis_status' => 'success',
            'reach_method' => 'ai_reader_estimate_v1',
            'project_estimated_readers' => 100,
            'project_reach_score' => 4,
            'project_reach_level' => 'Rendah',
        ]);
        $this->createQueuedState($project, $article, $template);

        $this->artisan('ai:requeue-orphan-queued-states', ['--limit' => 5, '--apply' => true])
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_missing_analyzable_is_not_requeued(): void
    {
        Queue::fake();

        [$project, $article, $template] = $this->seedAiContext();

        AiAnalysisDispatchState::create([
            'analyzable_type' => Article::class,
            'analyzable_id' => 999999,
            'project_id' => $project->id,
            'prompt_template_id' => $template->id,
            'provider_context_hash' => $this->providerHash(),
            'dispatch_key' => $this->dispatchKey(Article::class, 999999),
            'status' => 'queued',
            'attempts' => 0,
        ]);

        $this->artisan('ai:requeue-orphan-queued-states', ['--limit' => 5, '--apply' => true])
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_attempts_greater_than_zero_are_skipped(): void
    {
        Queue::fake();

        [$project, $article, $template] = $this->seedAiContext();

        AiAnalysisDispatchState::create([
            'analyzable_type' => Article::class,
            'analyzable_id' => $article->id,
            'project_id' => $project->id,
            'prompt_template_id' => $template->id,
            'provider_context_hash' => $this->providerHash(),
            'dispatch_key' => $this->dispatchKey(Article::class, $article->id),
            'status' => 'queued',
            'attempts' => 1,
        ]);

        $this->artisan('ai:requeue-orphan-queued-states', ['--limit' => 5, '--apply' => true])
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_auto_drain_requires_limit_one(): void
    {
        $this->artisan('ai:requeue-orphan-queued-states', [
            '--auto-drain' => true,
            '--limit' => 2,
        ])->assertExitCode(1);
    }

    public function test_apply_keeps_orphan_state_queued_until_job_consumes_it(): void
    {
        Queue::fake();

        [$project, $article, $template] = $this->seedAiContext();
        $state = $this->createQueuedState($project, $article, $template);

        $this->artisan('ai:requeue-orphan-queued-states', ['--limit' => 1, '--apply' => true])
            ->assertExitCode(0);

        Queue::assertPushedOn('ai-analysis', AiAnalysisJob::class);

        $state->refresh();
        $this->assertSame('queued', $state->status);
        $this->assertSame(0, (int) $state->attempts);
        $this->assertNull($state->last_error_code);
    }

    private function seedAiContext(): array
    {
        $project = Project::create([
            'name' => 'Orphan Requeue Test Project',
            'topics' => ['walikota samarinda'],
            'is_active' => true,
        ]);

        $article = Article::create([
            'title' => 'Orphan Requeue Article',
            'content' => str_repeat('Konten artikel yang panjang dan relevan. ', 30),
            'url' => 'https://example.test/articles/orphan-1',
            'canonical_url' => 'https://example.test/articles/orphan-1',
            'source_name' => 'detikcom',
            'published_at' => Carbon::now(),
            'sentiment' => 'neutral',
            'category' => 'news',
        ]);

        SocialMediaItem::create([
            'platform' => 'tiktok',
            'post_url' => 'https://www.tiktok.com/@example/video/123',
            'author_name' => 'Example Author',
            'content' => 'Konten sosial untuk test command requeue.',
            'posted_at' => Carbon::now()->subDay(),
            'raw_json' => '{}',
        ]);

        AiProvider::create([
            'name' => 'OpenAI Test',
            'provider_type' => 'OpenAI',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'test-key',
            'model_name' => 'gpt-4o-mini',
            'temperature' => 0.2,
            'max_tokens' => 256,
            'is_active' => true,
            'is_default' => true,
        ]);

        $template = AiPromptTemplate::create([
            'name' => 'Analisis Berita Utama',
            'source_type' => 'article',
            'system_prompt' => 'System prompt test',
            'user_prompt_template' => 'Analisis artikel: {title}',
            'is_active' => true,
            'is_default' => true,
        ]);

        return [$project, $article, $template];
    }

    private function createQueuedState(Project $project, Article $article, AiPromptTemplate $template): AiAnalysisDispatchState
    {
        return AiAnalysisDispatchState::create([
            'analyzable_type' => Article::class,
            'analyzable_id' => $article->id,
            'project_id' => $project->id,
            'prompt_template_id' => $template->id,
            'provider_context_hash' => $this->providerHash(),
            'dispatch_key' => $this->dispatchKey(Article::class, $article->id),
            'status' => 'queued',
            'attempts' => 0,
        ]);
    }

    private function providerHash(): string
    {
        return app(\App\Services\AiAnalysisDispatchStateService::class)->resolveProviderContextHash();
    }

    private function dispatchKey(string $type, int $id): string
    {
        return hash('sha256', strtolower(trim($type)) . '|' . $id);
    }
}
