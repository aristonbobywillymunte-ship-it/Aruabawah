<?php

namespace Tests\Feature;

use App\Models\AiAnalysisDispatchState;
use App\Models\AiPromptTemplate;
use App\Models\AiProvider;
use App\Models\Article;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AiDispatchStateReconcileTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconcile_command_dry_run_does_not_change_db(): void
    {
        [$project, $article, $template, $provider] = $this->seedAiContext();

        $state = AiAnalysisDispatchState::create([
            'analyzable_type' => 'article',
            'analyzable_id' => $article->id,
            'project_id' => $project->id,
            'prompt_template_id' => $template->id,
            'provider_context_hash' => 'hash',
            'dispatch_key' => 'key1',
            'status' => 'queued',
            'attempts' => 0,
        ]);

        \App\Models\AiAnalysisResult::create([
            'article_id' => $article->id,
            'summary' => 'Result Summary',
            'sentiment' => 'neutral',
            'sentiment_score' => 0.0,
            'main_issue' => 'Test Issue',
            'risk_level' => 'low',
            'reach_level' => 'Low',
            'reach_trend' => 'stable',
            'reach_source' => 'Test Source',
            'reach_confidence' => 'medium',
            'reach_reason' => 'Test Reason',
            'local_relevance_score' => 10,
            'confidence_score' => 65,
            'confidence_level' => 'Medium',
            'reach_method' => 'test_method',
            'potential_reach_level' => 'Low',
            'potential_reach_band' => 'Low',
            'project_reach_level' => 'Low',
            'project_reach_band' => 'Low',
            'analysis_status' => 'success',
            'recommendation' => 'Test Recommendation',
            'raw_response' => '{}',
        ]);

        $this->artisan('ai:reconcile-dispatch-states', ['--ids' => $state->id])
            ->assertExitCode(0);

        $state->refresh();
        $this->assertSame('queued', $state->status);
    }

    public function test_reconcile_command_apply_syncs_to_success(): void
    {
        [$project, $article, $template, $provider] = $this->seedAiContext();

        $state = AiAnalysisDispatchState::create([
            'analyzable_type' => 'article',
            'analyzable_id' => $article->id,
            'project_id' => $project->id,
            'prompt_template_id' => $template->id,
            'provider_context_hash' => 'hash',
            'dispatch_key' => 'key1',
            'status' => 'queued',
            'attempts' => 0,
        ]);

        \App\Models\AiAnalysisResult::create([
            'article_id' => $article->id,
            'summary' => 'Result Summary',
            'sentiment' => 'neutral',
            'sentiment_score' => 0.0,
            'main_issue' => 'Test Issue',
            'risk_level' => 'low',
            'reach_level' => 'Low',
            'reach_trend' => 'stable',
            'reach_source' => 'Test Source',
            'reach_confidence' => 'medium',
            'reach_reason' => 'Test Reason',
            'local_relevance_score' => 10,
            'confidence_score' => 65,
            'confidence_level' => 'Medium',
            'reach_method' => 'test_method',
            'potential_reach_level' => 'Low',
            'potential_reach_band' => 'Low',
            'project_reach_level' => 'Low',
            'project_reach_band' => 'Low',
            'analysis_status' => 'success',
            'recommendation' => 'Test Recommendation',
            'raw_response' => '{}',
        ]);

        $this->artisan('ai:reconcile-dispatch-states', ['--ids' => $state->id, '--apply' => true])
            ->assertExitCode(0);

        $state->refresh();
        $this->assertSame('success', $state->status);
    }

    public function test_reconcile_command_closes_orphan_states(): void
    {
        [$project, $article, $template, $provider] = $this->seedAiContext();

        $state = AiAnalysisDispatchState::create([
            'analyzable_type' => 'article',
            'analyzable_id' => 0,
            'project_id' => $project->id,
            'prompt_template_id' => $template->id,
            'provider_context_hash' => 'hash',
            'dispatch_key' => 'key2',
            'status' => 'queued',
            'attempts' => 0,
        ]);

        $this->artisan('ai:reconcile-dispatch-states', ['--ids' => $state->id, '--apply' => true])
            ->assertExitCode(0);

        $state->refresh();
        $this->assertSame('failed', $state->status);
        $this->assertSame('orphan_dispatch_state', $state->last_error_code);
        $this->assertSame('non_retryable_orphan', $state->failure_category);
    }

    private function seedAiContext(): array
    {
        $project = Project::create([
            'name' => 'Dispatch Feature Test Project',
            'topics' => ['wagub kaltim'],
            'is_active' => true,
        ]);

        $article = Article::create([
            'title' => 'Wagub Kaltim Feature Test Article',
            'content' => str_repeat('Konten artikel yang panjang dan relevan. ', 30),
            'url' => 'https://example.test/articles/2',
            'canonical_url' => 'https://example.test/articles/2',
            'source_name' => 'Detikcom',
            'published_at' => Carbon::now(),
            'sentiment' => 'neutral',
            'category' => 'news',
        ]);

        $provider = AiProvider::create([
            'name' => 'OpenAI Feature Test',
            'provider_type' => 'OpenAI',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'test-key-2',
            'model_name' => 'gpt-4o-mini',
            'temperature' => 0.2,
            'max_tokens' => 256,
            'is_active' => true,
            'is_default' => true,
        ]);

        $template = AiPromptTemplate::create([
            'name' => 'Analisis Berita Feature',
            'source_type' => 'article',
            'system_prompt' => 'System prompt test',
            'user_prompt_template' => 'Analisis artikel: {title}',
            'is_active' => true,
            'is_default' => true,
        ]);

        return [$project, $article, $template, $provider];
    }
}
