<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Article;
use App\Models\Project;
use App\Models\User;
use App\Models\AiAnalysisResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

class AiCardDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $project;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create(['role' => 'admin']);
        $this->project = Project::create(['name' => 'Test Project', 'topics' => ['test']]);
        $this->project->users()->attach($this->user->id);
    }

    public function test_ai_v1_valid_shows_all()
    {
        $article = Article::create(['published_at' => now(), 'title' => 'Valid AI Article', 'content' => 'content', 'url' => 'http://test', 'source_name' => 'News']);
        $this->project->articles()->attach($article->id);

        AiAnalysisResult::create([
            'article_id' => $article->id,
            'analysis_status' => 'success',
            'reach_method' => 'ai_reader_estimate_v1',
            'sentiment' => 'positive', 'sentiment_score' => 0, 'main_issue' => 'x', 'risk_level' => 'Low', 'recommendation' => 'x', 'raw_response' => 'x',
            'summary' => 'This is a valid AI summary',
            'project_estimated_readers' => 500,
            'project_reach_score' => 8,
            'project_reach_level' => 'Tinggi',
            'reach_estimate' => 0, 'reach_score_10' => 0, 'reach_score_max' => 10, 'reach_level' => 'Unknown', 'estimated_reach_band' => 'Unknown', 'reach_trend' => 'stable', 'reach_source' => 'unknown', 'reach_confidence' => 'low', 'reach_reason' => 'Legacy'
        ]);

        Livewire::actingAs($this->user)
            ->test('⚡media-dashboard', ['projectId' => $this->project->id])
            ->assertSee('This is a valid AI summary')
            ->assertSee('Positif')
            ->assertSee('500');
    }

    public function test_ai_v1_without_official_project_readers_shows_belum_tersedia()
    {
        $article = Article::create(['published_at' => now(), 'title' => 'Missing Project Reach', 'content' => 'content', 'url' => 'http://test-missing', 'source_name' => 'News']);
        $this->project->articles()->attach($article->id);

        AiAnalysisResult::create([
            'article_id' => $article->id,
            'analysis_status' => 'success',
            'reach_method' => 'ai_reader_estimate_v1',
            'sentiment' => 'positive', 'sentiment_score' => 0, 'main_issue' => 'x', 'risk_level' => 'Low', 'recommendation' => 'x', 'raw_response' => 'x',
            'summary' => 'This summary should still render',
            'potential_estimated_readers' => 1243,
            'potential_reach_score' => 10,
            'potential_reach_level' => 'Luar biasa/nasional',
            'potential_reach_band' => '1000+ pembaca',
            'project_estimated_readers' => null,
            'project_reach_score' => 9,
            'project_reach_level' => 'High',
            'project_reach_band' => '501-1000 pembaca',
            'reach_estimate' => 0, 'reach_score_10' => 0, 'reach_score_max' => 10, 'reach_level' => 'Unknown', 'estimated_reach_band' => 'Unknown', 'reach_trend' => 'stable', 'reach_source' => 'unknown', 'reach_confidence' => 'low', 'reach_reason' => 'Legacy'
        ]);

        Livewire::actingAs($this->user)
            ->test('⚡media-dashboard', ['projectId' => $this->project->id])
            ->assertDontSee('Missing Project Reach')
            ->assertDontSee('1.243');
    }

    public function test_success_but_missing_or_legacy_method_shows_fallback()
    {
        $article = Article::create([
            'published_at' => now(), 'title' => 'Legacy AI Article',
            'content' => 'content',
            'url' => 'http://test',
            'source_name' => 'News',
            'sentiment' => 'negative',
        ]);
        $this->project->articles()->attach($article->id);

        AiAnalysisResult::create([
            'article_id' => $article->id,
            'analysis_status' => 'success',
            'reach_method' => 'legacy_method',
            'sentiment' => 'positive', 'sentiment_score' => 0, 'main_issue' => 'x', 'risk_level' => 'Low', 'recommendation' => 'x', 'raw_response' => 'x',
            'summary' => 'Legacy AI summary',
            'reach_estimate' => 0, 'reach_score_10' => 0, 'reach_score_max' => 10, 'reach_level' => 'Unknown', 'estimated_reach_band' => 'Unknown', 'reach_trend' => 'stable', 'reach_source' => 'unknown', 'reach_confidence' => 'low', 'reach_reason' => 'Legacy'
        ]);

        Livewire::actingAs($this->user)
            ->test('⚡media-dashboard', ['projectId' => $this->project->id])
            ->assertDontSee('Legacy AI summary')
            ->assertDontSee('Legacy AI Article');
    }

    public function test_invalid_ai_reach_shows_fallback()
    {
        $article = Article::create([
            'published_at' => now(), 'title' => 'Invalid AI Article',
            'content' => 'content',
            'url' => 'http://test',
            'source_name' => 'News',
            'sentiment' => 'negative',
        ]);
        $this->project->articles()->attach($article->id);

        AiAnalysisResult::create([
            'article_id' => $article->id,
            'analysis_status' => 'invalid_ai_reach',
            'reach_method' => 'ai_reader_estimate_v1',
            'sentiment' => 'positive', 'sentiment_score' => 0, 'main_issue' => 'x', 'risk_level' => 'Low', 'recommendation' => 'x', 'raw_response' => 'x',
            'summary' => 'x',
            'reach_estimate' => 0, 'reach_score_10' => 0, 'reach_score_max' => 10, 'reach_level' => 'Unknown', 'estimated_reach_band' => 'Unknown', 'reach_trend' => 'stable', 'reach_source' => 'unknown', 'reach_confidence' => 'low', 'reach_reason' => 'Legacy'
        ]);

                $comp = Livewire::actingAs($this->user)
            ->test('⚡media-dashboard', ['projectId' => $this->project->id])
            ->set('selectedSentiment', []);
        file_put_contents('test_dump.html', $comp->html());
        $comp->assertDontSee('Invalid AI Article');
    }

    public function test_no_ai_shows_fallback()
    {
        $article = Article::create([
            'published_at' => now(), 'title' => 'No AI Article',
            'content' => 'content',
            'url' => 'http://test',
            'source_name' => 'News',
            'sentiment' => 'positive', 'sentiment_score' => 0, 'main_issue' => 'x', 'risk_level' => 'Low', 'recommendation' => 'x', 'raw_response' => 'x',
        ]);
        $this->project->articles()->attach($article->id);

                $comp = Livewire::actingAs($this->user)
            ->test('⚡media-dashboard', ['projectId' => $this->project->id])
            ->set('selectedSentiment', []);
        file_put_contents('test_dump.html', $comp->html());
        $comp->assertDontSee('No AI Article');
    }

    public function test_popular_sorting_only_uses_valid_ai_v1()
    {
        $article1 = Article::create(['published_at' => now(), 'title' => 'Legacy Popular', 'content' => 'content', 'url' => 'http://test1', 'source_name' => 'News']);
        $this->project->articles()->attach($article1->id);
        AiAnalysisResult::create([
            'article_id' => $article1->id,
            'analysis_status' => 'success',
            'reach_method' => 'legacy',
            'project_estimated_readers' => 9999,
            'sentiment' => 'positive', 'sentiment_score' => 0, 'main_issue' => 'x', 'risk_level' => 'Low', 'recommendation' => 'x', 'raw_response' => 'x', 'summary' => 's',
            'reach_estimate' => 0, 'reach_score_10' => 0, 'reach_score_max' => 10, 'reach_level' => 'Unknown', 'estimated_reach_band' => 'Unknown', 'reach_trend' => 'stable', 'reach_source' => 'unknown', 'reach_confidence' => 'low', 'reach_reason' => 'Legacy'
        ]);

        $article2 = Article::create(['published_at' => now(), 'title' => 'Valid Popular', 'content' => 'content', 'url' => 'http://test2', 'source_name' => 'News']);
        $this->project->articles()->attach($article2->id);
        AiAnalysisResult::create([
            'article_id' => $article2->id,
            'analysis_status' => 'success',
            'reach_method' => 'ai_reader_estimate_v1',
            'project_estimated_readers' => 500,
            'project_reach_score' => 8,
            'project_reach_level' => 'High',
            'sentiment' => 'positive', 'sentiment_score' => 0, 'main_issue' => 'x', 'risk_level' => 'Low', 'recommendation' => 'x', 'raw_response' => 'x', 'summary' => 's',
            'reach_estimate' => 0, 'reach_score_10' => 0, 'reach_score_max' => 10, 'reach_level' => 'Unknown', 'estimated_reach_band' => 'Unknown', 'reach_trend' => 'stable', 'reach_source' => 'unknown', 'reach_confidence' => 'low', 'reach_reason' => 'Legacy'
        ]);

        $component = Livewire::actingAs($this->user)
            ->test('⚡media-dashboard', ['projectId' => $this->project->id])
            ->set('sortBy', 'popular');
            
        $articles = $component->instance()->getArticles();
        
        $this->assertEquals($article2->id, $articles->first()->id);
    }
}
