<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Article;
use App\Models\AiAnalysisResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

class ProjectMetricsAuditTest extends TestCase
{
    use RefreshDatabase;

    private function createAiResult($articleId, $status = 'success', $risk = 'low')
    {
        AiAnalysisResult::create([
            'article_id' => $articleId,
            'analysis_status' => $status,
            'reach_method' => 'ai_reader_estimate_v1',
            'summary' => 'Valid summary',
            'sentiment' => 'positive',
            'sentiment_score' => 0.8,
            'main_issue' => 'none',
            'risk_level' => $risk,
            'project_estimated_readers' => 100,
            'potential_estimated_readers' => 200,
            'project_reach_score' => 2,
            'project_reach_level' => 'low',
            'project_reach_band' => 'Sangat terbatas',
            'potential_reach_score' => 4,
            'potential_reach_level' => 'local',
            'potential_reach_band' => 'Lokal',
            'confidence_score' => 55,
            'confidence_level' => 'medium',
            'reach_estimate' => 100,
            'reach_score_10' => 5,
            'reach_score_max' => 10,
            'reach_level' => 'low',
            'reach_trend' => 'stable',
            'reach_source' => 'system',
            'reach_confidence' => 0.9,
            'reach_reason' => 'test',
            'recommendation' => 'test',
            'raw_response' => 'test',
            'is_exact_reach' => false,
        ]);
    }

    public function test_projects_list_calculates_canonical_ai_correctly()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $project = Project::create(['name' => 'Audit Project', 'topics' => ['Audit']]);
        $project->users()->attach($user->id);

        // Article 1: Canonical Valid AI (Success + Low Risk)
        $article1 = Article::create(['title' => 'Test 1', 'content' => 'Content 1', 'source_name' => 'News']);
        $project->articles()->attach($article1->id);
        $this->createAiResult($article1->id, 'success', 'low');

        // Article 2: Pending AI (No AiAnalysisResult at all)
        $article2 = Article::create(['title' => 'Test 2', 'content' => 'Content 2', 'source_name' => 'News']);
        $project->articles()->attach($article2->id);

        // Article 3: Failed AI
        $article3 = Article::create(['title' => 'Test 3', 'content' => 'Content 3', 'source_name' => 'News']);
        $project->articles()->attach($article3->id);
        $this->createAiResult($article3->id, 'failed', 'low');

        // Article 4: High Risk Canonical AI (Success + Critical Risk)
        $article4 = Article::create(['title' => 'Test 4', 'content' => 'Content 4', 'source_name' => 'News']);
        $project->articles()->attach($article4->id);
        $this->createAiResult($article4->id, 'success', 'critical');

        $component = Livewire::actingAs($user)
            ->test('App\Http\Livewire\ProjectsList');

        // Test the array output directly rather than the HTML since the component 
        // might auto-redirect to media-dashboard if a project is found.
        $projects = $component->instance()->getProjects();
        $auditProject = collect($projects)->firstWhere('name', 'Audit Project');

        $this->assertNotNull($auditProject);
        $this->assertEquals(2, $auditProject['ai_valid'], 'AI Valid should be 2');
        $this->assertEquals(1, $auditProject['ai_failed'], 'AI Failed should be 1');
        $this->assertEquals(2, $auditProject['ai_pending'], 'AI Pending should be 2');
        $this->assertEquals(1, $auditProject['high_risk'], 'High Risk should be 1');
    }
}
