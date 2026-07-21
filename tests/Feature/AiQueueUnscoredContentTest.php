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
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiQueueUnscoredContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_queues_article_without_ai_result_when_project_matches(): void
    {
        Queue::fake();

        $project = Project::create([
            'name' => 'Queue Unscored Project',
            'topics' => ['bupatikukar'],
            'is_active' => true,
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

        AiPromptTemplate::create([
            'name' => 'AI Article Prompt',
            'source_type' => 'article',
            'system_prompt' => 'System prompt test',
            'user_prompt_template' => 'Analisis artikel: {title}',
            'is_active' => true,
            'is_default' => true,
        ]);

        $article = Article::create([
            'title' => 'Aulia Rahman Basri dan Bupati Kukar',
            'content' => str_repeat('Konten artikel yang panjang dan relevan. ', 30),
            'url' => 'https://example.test/articles/unscored-1',
            'canonical_url' => 'https://example.test/articles/unscored-1',
            'source_name' => 'detikcom',
            'published_at' => Carbon::now(),
            'sentiment' => 'neutral',
            'category' => 'news',
        ]);

        $this->artisan('ai:queue-unscored-content', [
            '--limit' => 10,
            '--hours' => 48,
        ])->assertExitCode(0);

        Queue::assertPushed(AiAnalysisJob::class, function (AiAnalysisJob $job) use ($article, $project): bool {
            return (int) ($job->payload['id'] ?? 0) === $article->id
                && (int) ($job->payload['project_id'] ?? 0) === $project->id
                && ($job->payload['type'] ?? null) === 'article'
                && ($job->payload['no_telegram'] ?? false) === true;
        });
    }

    public function test_skips_article_with_existing_dispatch_state_or_result(): void
    {
        Queue::fake();

        $project = Project::create([
            'name' => 'Queue Unscored Project 2',
            'topics' => ['bupatikukar'],
            'is_active' => true,
        ]);

        AiProvider::create([
            'name' => 'OpenAI Test 2',
            'provider_type' => 'OpenAI',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'test-key-2',
            'model_name' => 'gpt-4o-mini',
            'temperature' => 0.2,
            'max_tokens' => 256,
            'is_active' => true,
            'is_default' => true,
        ]);

        AiPromptTemplate::create([
            'name' => 'AI Article Prompt 2',
            'source_type' => 'article',
            'system_prompt' => 'System prompt test',
            'user_prompt_template' => 'Analisis artikel: {title}',
            'is_active' => true,
            'is_default' => true,
        ]);

        $article = Article::create([
            'title' => 'Aulia Rahman Basri dan Bupati Kukar Dua',
            'content' => str_repeat('Konten artikel yang panjang dan relevan. ', 30),
            'url' => 'https://example.test/articles/unscored-2',
            'canonical_url' => 'https://example.test/articles/unscored-2',
            'source_name' => 'detikcom',
            'published_at' => Carbon::now(),
            'sentiment' => 'neutral',
            'category' => 'news',
        ]);

        AiAnalysisDispatchState::create([
            'analyzable_type' => 'article',
            'analyzable_id' => $article->id,
            'project_id' => $project->id,
            'prompt_template_id' => null,
            'provider_context_hash' => app(\App\Services\AiAnalysisDispatchStateService::class)->resolveProviderContextHash(),
            'dispatch_key' => hash('sha256', 'article|' . $article->id),
            'status' => 'retry_wait',
            'attempts' => 1,
            'next_retry_at' => now()->addMinutes(15),
        ]);

        $this->artisan('ai:queue-unscored-content', [
            '--limit' => 10,
            '--hours' => 48,
        ])->assertExitCode(0);

        Queue::assertNothingPushed();
    }
}
