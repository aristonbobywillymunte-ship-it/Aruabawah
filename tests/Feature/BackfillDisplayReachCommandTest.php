<?php

namespace Tests\Feature;

use App\Models\AiAnalysisResult;
use App\Models\Article;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillDisplayReachCommandTest extends TestCase
{
    use RefreshDatabase;

    private function createAiResult(Article $article, array $overrides = []): AiAnalysisResult
    {
        return AiAnalysisResult::create(array_merge([
            'article_id' => $article->id,
            'summary' => 'Summary',
            'sentiment' => 'positive',
            'sentiment_score' => 0.9,
            'main_issue' => 'Issue',
            'entities' => ['X'],
            'risk_level' => 'low',
            'risk_reason' => 'Reason',
            'reach_estimate' => 0,
            'reach_score_10' => 0,
            'reach_score_max' => 10,
            'reach_level' => 'Unknown',
            'reach_trend' => 'stable',
            'reach_source' => 'test',
            'reach_confidence' => 'low',
            'reach_reason' => 'Reason',
            'recommendation' => 'None',
            'raw_response' => '{}',
            'analysis_status' => 'success',
            'validation_errors' => null,
            'local_relevance_score' => 10,
            'estimated_reach_band' => 'Unknown',
            'confidence_score' => 50,
            'confidence_level' => 'Medium',
            'signals_used' => ['test'],
            'reasoning_summary' => 'Reason',
            'limitations' => 'None',
            'is_exact_reach' => false,
            'reach_method' => 'ai_reader_estimate_v1',
            'potential_estimated_readers' => 1428,
            'potential_reach_score' => 10,
            'potential_reach_level' => 'Luar biasa/nasional',
            'potential_reach_band' => '>=1.000 pembaca',
            'project_estimated_readers' => 0,
            'project_reach_score' => 0,
            'project_reach_level' => 'N/A',
            'project_reach_band' => '0 pembaca',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    public function test_dry_run_does_not_modify_id_80(): void
    {
        $project = Project::create(['name' => 'Seno Aji', 'is_active' => true]);
        $article = Article::create([
            'title' => 'Article 80',
            'content' => str_repeat('content ', 200),
            'url' => 'https://example.com/a80',
            'source_name' => 'detikinet',
            'published_at' => now(),
            'hash' => uniqid(),
        ]);
        $project->articles()->attach($article->id);
        $result = $this->createAiResult($article);

        $this->artisan('ai:backfill-display-reach', [
            '--project-id' => $project->id,
            '--ids' => (string) $article->id,
        ])
            ->expectsOutputToContain('Dry run: Ya')
            ->expectsOutputToContain('article_id=' . $article->id)
            ->assertExitCode(0);

        $fresh = AiAnalysisResult::find($result->id);
        $this->assertSame(0, (int) $fresh->project_estimated_readers);
        $this->assertSame(0, (int) $fresh->project_reach_score);
        $this->assertSame('N/A', $fresh->project_reach_level);
    }

    public function test_apply_backfills_display_reach_from_existing_metadata(): void
    {
        $project = Project::create(['name' => 'Seno Aji', 'is_active' => true]);
        $article = Article::create([
            'title' => 'Article 80',
            'content' => str_repeat('content ', 200),
            'url' => 'https://example.com/a80',
            'source_name' => 'detikinet',
            'published_at' => now(),
            'hash' => uniqid(),
        ]);
        $project->articles()->attach($article->id);
        $result = $this->createAiResult($article);

        $this->artisan('ai:backfill-display-reach', [
            '--project-id' => $project->id,
            '--ids' => (string) $article->id,
            '--apply' => true,
        ])
            ->expectsOutputToContain('Apply selesai.')
            ->assertExitCode(0);

        $fresh = AiAnalysisResult::find($result->id);
        $this->assertSame(1428, (int) $fresh->project_estimated_readers);
        $this->assertSame(10, (int) $fresh->project_reach_score);
        $this->assertSame('Luar biasa/nasional', $fresh->project_reach_level);
        $this->assertSame('Perkiraan 1.428 pembaca', $fresh->project_reach_band);
        $this->assertSame('ai_reader_estimate_v1', $fresh->reach_method);
    }

    public function test_apply_does_not_touch_other_projects_or_ids(): void
    {
        $project = Project::create(['name' => 'Seno Aji', 'is_active' => true]);
        $otherProject = Project::create(['name' => 'Other', 'is_active' => true]);

        $article = Article::create([
            'title' => 'Article 80',
            'content' => str_repeat('content ', 200),
            'url' => 'https://example.com/a80',
            'source_name' => 'detikinet',
            'published_at' => now(),
            'hash' => uniqid(),
        ]);
        $otherArticle = Article::create([
            'title' => 'Article 81',
            'content' => str_repeat('content ', 200),
            'url' => 'https://example.com/a81',
            'source_name' => 'detikinet',
            'published_at' => now(),
            'hash' => uniqid(),
        ]);
        $project->articles()->attach($article->id);
        $otherProject->articles()->attach($otherArticle->id);
        $target = $this->createAiResult($article);
        $other = $this->createAiResult($otherArticle);

        $this->artisan('ai:backfill-display-reach', [
            '--project-id' => $project->id,
            '--ids' => (string) $article->id,
            '--apply' => true,
        ])->assertExitCode(0);

        $freshTarget = AiAnalysisResult::find($target->id);
        $freshOther = AiAnalysisResult::find($other->id);

        $this->assertSame(1428, (int) $freshTarget->project_estimated_readers);
        $this->assertSame(0, (int) $freshOther->project_estimated_readers);
        $this->assertSame(0, (int) $freshOther->project_reach_score);
    }
}
