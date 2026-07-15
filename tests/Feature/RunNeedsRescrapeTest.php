<?php

namespace Tests\Feature;

use App\Models\AiAnalysisResult;
use App\Models\AiPromptTemplate;
use App\Models\AiProvider;
use App\Models\Article;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RunNeedsRescrapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_change_data(): void
    {
        [$project, $article] = $this->seedNeedsRescrapePortal();

        $this->artisan('scraping:run-needs-rescrape', [
            '--project-id' => $project->id,
            '--limit' => 1,
        ])->assertExitCode(0);

        $this->assertSame('needs_rescrape', DB::table('project_articles')->where('project_id', $project->id)->where('article_id', $article->id)->value('rescrape_status'));
    }

    public function test_apply_limit_one_clears_display_ready_overlap(): void
    {
        [$project, $article] = $this->seedNeedsRescrapePortal(displayReady: true);

        $this->artisan('scraping:run-needs-rescrape', [
            '--project-id' => $project->id,
            '--limit' => 1,
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertNull(DB::table('project_articles')->where('project_id', $project->id)->where('article_id', $article->id)->value('rescrape_status'));
    }

    public function test_apply_limit_one_keeps_non_display_ready_item_marked_for_rescrape(): void
    {
        [$project, $article] = $this->seedNeedsRescrapePortal(displayReady: false, currentContent: 'short content');

        $this->artisan('scraping:run-needs-rescrape', [
            '--project-id' => $project->id,
            '--limit' => 1,
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertSame('needs_rescrape', DB::table('project_articles')->where('project_id', $project->id)->where('article_id', $article->id)->value('rescrape_status'));
        $this->assertSame('no_ai_result', DB::table('project_articles')->where('project_id', $project->id)->where('article_id', $article->id)->value('rescrape_reason'));
        $this->assertSame('short content', DB::table('articles')->where('id', $article->id)->value('content'));
    }

    private function seedNeedsRescrapePortal(bool $displayReady = false, string $currentContent = 'existing content'): array
    {
        $project = Project::create([
            'name' => 'Needs Rescrape Test Project',
            'topics' => ['test'],
            'is_active' => true,
        ]);

        $article = Article::create([
            'title' => 'Rescrape Portal Article',
            'content' => $currentContent,
            'url' => 'https://example.test/article/needs-rescrape',
            'canonical_url' => 'https://example.test/article/needs-rescrape',
            'source_name' => 'Example News',
            'published_at' => now(),
            'sentiment' => 'neutral',
            'category' => 'news',
        ]);

        DB::table('project_articles')->insert([
            'project_id' => $project->id,
            'article_id' => $article->id,
            'rescrape_status' => 'needs_rescrape',
            'rescrape_reason' => $displayReady ? 'display_sync_gap' : 'no_ai_result',
            'rescrape_source' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($displayReady) {
            AiAnalysisResult::create([
                'article_id' => $article->id,
                'summary' => 'ok',
                'sentiment' => 'neutral',
                'sentiment_score' => 0,
                'main_issue' => 'test',
                'risk_level' => 'low',
                'risk_reason' => 'test',
                'reach_estimate' => 0,
                'reach_score_10' => 1,
                'reach_score_max' => 10,
                'reach_level' => 'N/A',
                'local_relevance_score' => 0,
                'estimated_reach_band' => 'N/A',
                'confidence_score' => 0,
                'confidence_level' => 'low',
                'reach_trend' => 'flat',
                'reach_source' => 'test',
                'reach_confidence' => 'low',
                'reach_reason' => 'test',
                'signals_used' => json_encode([]),
                'reasoning_summary' => 'test',
                'limitations' => 'test',
                'is_exact_reach' => false,
                'reach_method' => 'ai_reader_estimate_v1',
                'potential_reach_score' => 1,
                'potential_reach_level' => 'Low',
                'potential_reach_band' => 'Low',
                'potential_estimated_readers' => 100,
                'project_estimated_readers' => 847,
                'project_reach_score' => 9,
                'project_reach_level' => 'Sangat tinggi',
                'project_reach_band' => 'N/A',
                'analysis_status' => 'success',
                'validation_errors' => null,
                'recommendation' => 'test',
                'raw_response' => '{}',
            ]);
        }

        AiProvider::create([
            'name' => 'Test Provider',
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
            'name' => 'Test Template',
            'source_type' => 'article',
            'system_prompt' => 'System prompt test',
            'user_prompt_template' => 'Analisis artikel: {title}',
            'is_active' => true,
            'is_default' => true,
        ]);

        return [$project, $article];
    }
}
