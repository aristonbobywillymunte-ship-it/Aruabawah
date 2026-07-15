<?php

namespace Tests\Feature;

use App\Jobs\AiAnalysisJob;
use App\Models\AiAnalysisDispatchState;
use App\Models\AiPromptTemplate;
use App\Models\AiProvider;
use App\Models\Article;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiRetryWaitRequeueTest extends TestCase
{
    use RefreshDatabase;

    public function test_requeue_overdue_retry_wait_dispatches_only_due_items_once(): void
    {
        Queue::fake();

        [$project, $article, $template] = $this->seedAiContext();

        $dueState = AiAnalysisDispatchState::create([
            'analyzable_type' => 'article',
            'analyzable_id' => $article->id,
            'project_id' => $project->id,
            'prompt_template_id' => $template->id,
            'provider_context_hash' => $this->providerHash(),
            'dispatch_key' => hash('sha256', 'article|' . $article->id),
            'status' => 'retry_wait',
            'attempts' => 1,
            'failure_category' => 'rate_limit',
            'last_error_code' => 'rate_limit',
            'error_message' => 'Provider rate limit reached. Retrying later.',
            'next_retry_at' => now()->subMinute(),
        ]);

        AiAnalysisDispatchState::create([
            'analyzable_type' => 'article',
            'analyzable_id' => $article->id + 1,
            'project_id' => $project->id,
            'prompt_template_id' => $template->id,
            'provider_context_hash' => $this->providerHash(),
            'dispatch_key' => hash('sha256', 'article|' . ($article->id + 1)),
            'status' => 'retry_wait',
            'attempts' => 1,
            'failure_category' => 'rate_limit',
            'last_error_code' => 'rate_limit',
            'error_message' => 'Provider rate limit reached. Retrying later.',
            'next_retry_at' => now()->addHour(),
        ]);

        $exit = Artisan::call('ai:requeue-overdue-retries', ['--limit' => 1]);

        $this->assertSame(0, $exit);
        Queue::assertPushed(AiAnalysisJob::class, 1);
        Queue::assertPushedOn('ai-analysis', AiAnalysisJob::class);
        Queue::assertPushed(AiAnalysisJob::class, function (AiAnalysisJob $job) use ($article, $project) {
            return $job->payload['id'] === $article->id && $job->payload['project_id'] === $project->id;
        });
    }

    private function seedAiContext(): array
    {
        $project = Project::create([
            'name' => 'Retry Requeue Test Project',
            'topics' => ['seno aji'],
            'is_active' => true,
        ]);

        $article = Article::create([
            'title' => 'Retry Requeue Article',
            'content' => str_repeat('Konten artikel yang panjang dan relevan. ', 30),
            'url' => 'https://example.test/articles/requeue-1',
            'canonical_url' => 'https://example.test/articles/requeue-1',
            'source_name' => 'Detikcom',
            'published_at' => Carbon::now(),
            'sentiment' => 'neutral',
            'category' => 'news',
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

    private function providerHash(): string
    {
        return app(\App\Services\AiAnalysisDispatchStateService::class)->resolveProviderContextHash();
    }
}
