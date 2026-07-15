<?php

namespace Tests\Feature;

use App\Models\AiAnalysisResult;
use App\Models\Article;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectsListArticleReachTest extends TestCase
{
    use RefreshDatabase;

    private function createAiResult(Article $article, array $overrides = []): AiAnalysisResult
    {
        return AiAnalysisResult::create(array_merge([
            'article_id' => $article->id,
            'social_media_item_id' => null,
            'summary' => 'Official AI summary',
            'sentiment' => 'positive',
            'sentiment_score' => 0.9,
            'main_issue' => 'Governance',
            'entities' => ['Kaltim'],
            'risk_level' => 'low',
            'risk_reason' => 'Official AI test',
            'reach_estimate' => 100,
            'reach_score_10' => 5,
            'reach_score_max' => 10,
            'reach_level' => 'Low',
            'reach_trend' => 'stable',
            'reach_source' => 'test',
            'reach_confidence' => 'medium',
            'reach_reason' => 'Official AI test',
            'recommendation' => 'No action',
            'raw_response' => json_encode(['ok' => true]),
            'analysis_status' => 'success',
            'validation_errors' => null,
            'local_relevance_score' => 80,
            'estimated_reach_band' => 'Perkiraan 100 pembaca',
            'confidence_score' => 60,
            'confidence_level' => 'Medium',
            'signals_used' => ['test'],
            'reasoning_summary' => 'Official AI test',
            'limitations' => 'None',
            'is_exact_reach' => false,
            'reach_method' => 'ai_reader_estimate_v1',
            'potential_reach_score' => 6,
            'potential_reach_level' => 'Medium',
            'potential_reach_band' => 'Perkiraan 100 pembaca',
            'potential_estimated_readers' => 100,
            'project_estimated_readers' => 75,
            'project_reach_score' => 5,
            'project_reach_level' => 'Local',
            'project_reach_band' => 'Perkiraan 75 pembaca relevan',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    public function test_projects_list_uses_official_ai_reach_without_random_fallback(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $projectWithOfficialReach = Project::create([
            'name' => 'Project Official Reach',
            'topics' => ['kaltim'],
            'is_active' => true,
        ]);

        $article1 = Article::create([
            'title' => 'Article 1',
            'content' => str_repeat('Content A ', 100),
            'url' => 'https://example.com/articles/1',
            'canonical_url' => 'https://example.com/articles/1',
            'source_name' => 'Example News',
            'published_at' => now(),
        ]);
        $projectWithOfficialReach->articles()->attach($article1->id);
        $this->createAiResult($article1, [
            'project_estimated_readers' => 300,
            'potential_estimated_readers' => 500,
            'potential_reach_score' => 7,
            'project_reach_score' => 4,
        ]);

        $article2 = Article::create([
            'title' => 'Article 2',
            'content' => str_repeat('Content B ', 100),
            'url' => 'https://example.com/articles/2',
            'canonical_url' => 'https://example.com/articles/2',
            'source_name' => 'Example News',
            'published_at' => now(),
        ]);
        $projectWithOfficialReach->articles()->attach($article2->id);
        $this->createAiResult($article2, [
            'analysis_status' => 'failed',
            'project_estimated_readers' => 9999,
            'potential_estimated_readers' => 9999,
            'reach_method' => 'legacy_method',
        ]);

        $projectWithoutOfficialReach = Project::create([
            'name' => 'Project No Official Reach',
            'topics' => ['samarinda'],
            'is_active' => true,
        ]);

        $article3 = Article::create([
            'title' => 'Article 3',
            'content' => str_repeat('Content C ', 100),
            'url' => 'https://example.com/articles/3',
            'canonical_url' => 'https://example.com/articles/3',
            'source_name' => 'Example News',
            'published_at' => now(),
        ]);
        $projectWithoutOfficialReach->articles()->attach($article3->id);
        $this->createAiResult($article3, [
            'analysis_status' => 'success',
            'reach_method' => 'ai_reader_estimate_v1',
            'project_estimated_readers' => null,
            'potential_estimated_readers' => 500,
            'reach_estimate' => 999,
            'project_reach_score' => 9,
            'project_reach_level' => 'High',
            'project_reach_band' => '501-1000 pembaca',
        ]);

        $component = Livewire::actingAs($user)->test('App\Http\Livewire\ProjectsList');
        $projectsA = collect($component->instance()->getProjects());
        $projectsB = collect($component->instance()->getProjects());

        $officialProject = $projectsA->firstWhere('name', 'Project Official Reach');
        $noOfficialProject = $projectsA->firstWhere('name', 'Project No Official Reach');
        $officialProjectRepeat = $projectsB->firstWhere('name', 'Project Official Reach');

        $this->assertNotNull($officialProject);
        $this->assertSame('300', preg_replace('/[^0-9]/', '', (string) $officialProject['reach']));
        $this->assertSame('300', preg_replace('/[^0-9]/', '', (string) $officialProjectRepeat['reach']));
        $this->assertNotSame('Belum tersedia', (string) $officialProject['reach']);

        $this->assertNotNull($noOfficialProject);
        $this->assertSame('Belum tersedia', (string) $noOfficialProject['reach']);

        $this->assertSame($officialProject['reach'], $officialProjectRepeat['reach']);
        $this->assertNotEquals(
            $officialProject['reach'],
            $noOfficialProject['reach'],
            'Project tanpa AI resmi harus menampilkan status kosong, bukan angka sintetis'
        );
    }

    public function test_projects_list_card_uses_article_found_ready_and_pending_labels(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $project = Project::create([
            'name' => 'Project Stats',
            'topics' => ['kaltim'],
            'is_active' => true,
        ]);

        $article = Article::create([
            'title' => 'Project Stats Article',
            'content' => str_repeat('Content D ', 100),
            'url' => 'https://example.com/articles/stats',
            'canonical_url' => 'https://example.com/articles/stats',
            'source_name' => 'Example News',
            'published_at' => now(),
        ]);
        $project->articles()->attach($article->id);

        AiAnalysisResult::create([
            'article_id' => $article->id,
            'analysis_status' => 'success',
            'reach_method' => 'ai_reader_estimate_v1',
            'project_estimated_readers' => 300,
            'project_reach_score' => 7,
            'project_reach_level' => 'Tinggi',
            'project_reach_band' => 'Perkiraan 300 pembaca',
            'sentiment' => 'positive',
            'sentiment_score' => 0,
            'main_issue' => 'x',
            'risk_level' => 'Low',
            'recommendation' => 'x',
            'raw_response' => 'x',
            'summary' => 'Summary',
            'reach_estimate' => 0,
            'reach_score_10' => 0,
            'reach_score_max' => 10,
            'reach_level' => 'Unknown',
            'estimated_reach_band' => 'Unknown',
            'reach_trend' => 'stable',
            'reach_source' => 'unknown',
            'reach_confidence' => 'low',
            'reach_reason' => 'Legacy',
        ]);

        $projects = collect(Livewire::actingAs($user)
            ->test('App\\Http\\Livewire\\ProjectsList')
            ->instance()
            ->getProjects());

        $card = $projects->firstWhere('name', 'Project Stats');
        $this->assertNotNull($card);
        $this->assertSame('1', (string) $card['mentions']);
        $this->assertSame(1, $card['total_articles_found']);
        $this->assertSame(1, $card['ai_valid']);
        $this->assertSame(0, $card['ai_pending']);
    }
}
