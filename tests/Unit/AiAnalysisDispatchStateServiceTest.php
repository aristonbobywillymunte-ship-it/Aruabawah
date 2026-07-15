<?php

namespace Tests\Unit;

use App\Models\AiAnalysisDispatchState;
use App\Models\AiPromptTemplate;
use App\Models\AiProvider;
use App\Models\Article;
use App\Models\Project;
use App\Services\AiAnalysisDispatchStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AiAnalysisDispatchStateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserve_blocks_duplicate_dispatch_until_state_changes(): void
    {
        [$project, $article, $template, $provider] = $this->seedAiContext();
        $service = app(AiAnalysisDispatchStateService::class);
        $payload = $this->articlePayload($project, $article);

        $decision1 = $service->reserveQueuedState($payload, $template->id, $this->providerHash());
        $this->assertTrue($decision1['should_dispatch']);
        $this->assertSame('queued', $decision1['status']);

        $decision2 = $service->reserveQueuedState($payload, $template->id, $this->providerHash());
        $this->assertFalse($decision2['should_dispatch']);
        $this->assertSame('queued', $decision2['status']);

        $state = AiAnalysisDispatchState::query()->first();
        $this->assertSame('queued', $state->status);
        $this->assertSame(0, $state->attempts);
    }

    public function test_retry_wait_respects_next_retry_before_requeueing(): void
    {
        [$project, $article, $template, $provider] = $this->seedAiContext();
        $service = app(AiAnalysisDispatchStateService::class);
        $payload = $this->articlePayload($project, $article);

        $service->reserveQueuedState($payload, $template->id, $this->providerHash());
        $service->claimProcessing($payload, $template->id, $this->providerHash());
        $service->markRetryWait($payload, 'quota_exhausted', 'Quota exhausted', $template->id, $this->providerHash());

        $state = AiAnalysisDispatchState::query()->first();
        $this->assertSame('retry_wait', $state->status);
        $this->assertSame('rate_limit', $state->failure_category);
        $this->assertSame('quota_exhausted', $state->last_error_code);
        $this->assertSame('Provider rate limit reached. Retrying later.', $state->error_message);
        $this->assertNotNull($state->next_retry_at);

        $state->forceFill(['next_retry_at' => now()->addHour()])->save();

        $decision1 = $service->reserveQueuedState($payload, $template->id, $this->providerHash());
        $this->assertFalse($decision1['should_dispatch']);
        $this->assertSame('retry_wait', $decision1['status']);

        $state->forceFill(['next_retry_at' => now()->subMinute()])->save();

        $decision2 = $service->reserveQueuedState($payload, $template->id, $this->providerHash());
        $this->assertTrue($decision2['should_dispatch']);
        $this->assertSame('queued', $decision2['status']);
    }

    public function test_success_locks_out_future_dispatches(): void
    {
        [$project, $article, $template, $provider] = $this->seedAiContext();
        $service = app(AiAnalysisDispatchStateService::class);
        $payload = $this->articlePayload($project, $article);

        $service->reserveQueuedState($payload, $template->id, $this->providerHash());
        $service->claimProcessing($payload, $template->id, $this->providerHash());
        $service->markSuccess($payload, 123, $template->id, $this->providerHash());

        $state = AiAnalysisDispatchState::query()->first();
        $this->assertSame('success', $state->status);
        $this->assertNotNull($state->completed_at);
        $this->assertNull($state->failure_category);
        $this->assertNull($state->last_failed_at);
        $this->assertNull($state->error_message);

        $decision = $service->reserveQueuedState($payload, $template->id, $this->providerHash());
        $this->assertFalse($decision['should_dispatch']);
        $this->assertSame('success', $decision['status']);
    }

    public function test_classify_failure_maps_known_error_categories(): void
    {
        $service = app(AiAnalysisDispatchStateService::class);

        $rateLimit = $service->classifyFailure(new \RuntimeException('HTTP 429 rate limit exceeded'));
        $this->assertSame('rate_limit', $rateLimit['category']);
        $this->assertSame('retry_wait', $rateLimit['status']);

        $auth = $service->classifyFailure(new \RuntimeException('401 Unauthorized - invalid api key'));
        $this->assertSame('authentication_error', $auth['category']);
        $this->assertSame('failed', $auth['status']);

        $model = $service->classifyFailure(new \RuntimeException('models/gemini-2.5-flash-live-preview is not found'));
        $this->assertSame('model_not_found', $model['category']);
        $this->assertSame('failed', $model['status']);
    }

    public function test_invalid_payload_with_missing_id_is_skipped(): void
    {
        [$project, $article, $template, $provider] = $this->seedAiContext();
        $service = app(AiAnalysisDispatchStateService::class);
        $payload = $this->articlePayload($project, $article);
        unset($payload['id']);

        $decision = $service->reserveQueuedState($payload, $template->id, $this->providerHash());
        $this->assertFalse($decision['should_dispatch']);
        $this->assertSame('failed', $decision['status']);
        $this->assertStringContainsString('invalid_payload', $decision['reason']);
        $this->assertSame(0, AiAnalysisDispatchState::count());
    }

    public function test_invalid_payload_with_missing_project_id_is_skipped(): void
    {
        [$project, $article, $template, $provider] = $this->seedAiContext();
        $service = app(AiAnalysisDispatchStateService::class);
        $payload = $this->articlePayload($project, $article);
        unset($payload['project_id']);

        $decision = $service->reserveQueuedState($payload, $template->id, $this->providerHash());
        $this->assertFalse($decision['should_dispatch']);
        $this->assertSame('failed', $decision['status']);
        $this->assertStringContainsString('invalid_payload', $decision['reason']);
        $this->assertSame(0, AiAnalysisDispatchState::count());
    }

    private function seedAiContext(): array
    {
        $project = Project::create([
            'name' => 'Dispatch Test Project',
            'topics' => ['wagub kaltim'],
            'is_active' => true,
        ]);

        $article = Article::create([
            'title' => 'Wagub Kaltim Test Article',
            'content' => str_repeat('Konten artikel yang panjang dan relevan. ', 30),
            'url' => 'https://example.test/articles/1',
            'canonical_url' => 'https://example.test/articles/1',
            'source_name' => 'Detikcom',
            'published_at' => Carbon::now(),
            'sentiment' => 'neutral',
            'category' => 'news',
        ]);

        $provider = AiProvider::create([
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

        return [$project, $article, $template, $provider];
    }

    private function articlePayload(Project $project, Article $article): array
    {
        return [
            'type' => 'article',
            'id' => $article->id,
            'project_id' => $project->id,
            'title' => $article->title,
            'content' => $article->content,
            'url' => $article->url,
            'source_name' => $article->source_name,
            'published_at' => Carbon::parse($article->published_at)->toIso8601String(),
        ];
    }

    private function providerHash(): string
    {
        return app(AiAnalysisDispatchStateService::class)->resolveProviderContextHash();
    }
}
