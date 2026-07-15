<?php

namespace Tests\Feature;

use App\Exports\ArticlesExport;
use App\Http\Controllers\ReportController;
use App\Models\AiAnalysisResult;
use App\Models\Article;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class OfficialArticleReachDisplayTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'admin']);
        $this->project = Project::create([
            'name' => 'Official Reach Project',
            'topics' => ['kaltim'],
            'is_active' => true,
        ]);
        $this->project->users()->attach($this->user->id);
    }

    private function createArticleWithAi(array $overrides = []): Article
    {
        $article = Article::create([
            'published_at' => now(),
            'title' => 'Reach Article ' . uniqid(),
            'content' => str_repeat('content ', 100),
            'url' => 'https://example.com/' . uniqid(),
            'canonical_url' => 'https://example.com/' . uniqid(),
            'source_name' => 'News',
        ]);

        $this->project->articles()->attach($article->id);

        AiAnalysisResult::create(array_merge([
            'article_id' => $article->id,
            'analysis_status' => 'success',
            'reach_method' => 'ai_reader_estimate_v1',
            'summary' => 'Summary',
            'sentiment' => 'positive',
            'sentiment_score' => 0.75,
            'main_issue' => 'Issue',
            'entities' => ['Kaltim'],
            'risk_level' => 'low',
            'risk_reason' => 'Test',
            'recommendation' => 'Test',
            'raw_response' => '{}',
            'potential_estimated_readers' => 1243,
            'potential_reach_score' => 10,
            'potential_reach_level' => 'Luar biasa/nasional',
            'potential_reach_band' => '1000+ pembaca',
            'project_estimated_readers' => null,
            'project_reach_score' => 9,
            'project_reach_level' => 'High',
            'project_reach_band' => '501-1000 pembaca',
            'local_relevance_score' => 85,
            'confidence_score' => 65,
            'confidence_level' => 'Medium',
            'signals_used' => ['test'],
            'reasoning_summary' => 'Test',
            'limitations' => 'Test',
            'is_exact_reach' => false,
            'reach_estimate' => 0,
            'reach_score_10' => 0,
            'reach_score_max' => 10,
            'reach_level' => 'Unknown',
            'estimated_reach_band' => 'Unknown',
            'reach_trend' => 'stable',
            'reach_source' => 'unknown',
            'reach_confidence' => 'low',
            'reach_reason' => 'Legacy',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return $article->fresh(['aiAnalysisResult']);
    }

    public function test_official_project_reach_helper_returns_null_without_project_estimated_readers(): void
    {
        $article = $this->createArticleWithAi();

        $analysis = $article->aiAnalysisResult;
        $this->assertTrue($analysis->analysis_status === 'success');
        $this->assertSame('ai_reader_estimate_v1', $analysis->reach_method);
        $this->assertFalse($analysis->hasCompleteOfficialAiResult());
        $this->assertNull($analysis->officialArticleEstimatedReaders());
        $this->assertFalse($analysis->hasOfficialProjectReach());
    }

    public function test_dashboard_report_and_export_hide_incomplete_official_ai_result(): void
    {
        $article = $this->createArticleWithAi();

        $dashboard = Livewire::actingAs($this->user)->test('⚡media-dashboard', ['projectId' => $this->project->id]);
        $display = $dashboard->instance()->getProjectReachDisplayData($article);

        $this->assertFalse($display['hasOfficialProjectReach']);
        $this->assertFalse($display['hasReadableAiReach']);
        $this->assertNull($display['reachValue']);
        $this->assertNull($display['scoreValue']);
        $this->assertSame('Belum dinilai AI', $display['levelLabel']);

        $reportController = app(ReportController::class);
        $reportMethod = new ReflectionMethod($reportController, 'formatReachSummary');
        $reportMethod->setAccessible(true);
        $reportSummary = $reportMethod->invoke($reportController, $article->aiAnalysisResult);

        $this->assertSame('Belum dinilai AI', $reportSummary['project']);
        $this->assertSame('Belum dinilai AI', $reportSummary['potential']);

        $export = new ArticlesExport($this->project->id, $this->project->name);
        $exportMethod = new ReflectionMethod($export, 'formatReachSummary');
        $exportMethod->setAccessible(true);
        $exportSummary = $exportMethod->invoke($export, $article->aiAnalysisResult);

        $this->assertSame('Belum dinilai AI', $exportSummary['project']);
        $this->assertSame('Belum dinilai AI', $exportSummary['potential']);
    }
}
