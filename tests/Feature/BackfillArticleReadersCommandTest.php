<?php

namespace Tests\Feature;

use App\Models\AiAnalysisResult;
use App\Models\Article;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use App\Jobs\AiAnalysisJob;
use App\Jobs\BackfillArticleReadersJob;
use Tests\TestCase;

class BackfillArticleReadersCommandTest extends TestCase
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

    public function test_dry_run_does_not_dispatch_jobs_but_finds_candidates()
    {
        Queue::fake();

        $project = Project::create(['name' => 'Active Project', 'is_active' => true]);
        
        $article1 = Article::create([
            'title' => 'Article null reach',
            'content' => str_repeat('Content A ', 100),
            'url' => 'https://example.com/articles/1',
            'source_name' => 'Example News',
            'published_at' => now(),
            'hash' => uniqid()
        ]);
        $project->articles()->attach($article1->id);

        $this->createAiResult($article1, [
            'project_estimated_readers' => null,
        ]);

        $this->artisan('ai:backfill-article-readers', ['--dry-run' => true])
             ->expectsOutputToContain('Ditemukan 1 artikel kandidat.')
             ->expectsOutputToContain('[DRY RUN] Akan dispatch AI Job untuk Article ID: ' . $article1->id)
             ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_execute_dispatches_jobs_for_valid_candidates_only()
    {
        Queue::fake();

        $project = Project::create(['name' => 'Active Project', 'is_active' => true]);
        $inactiveProject = Project::create(['name' => 'Inactive', 'is_active' => false]);

        // Candidate 1: valid null reach
        $article1 = Article::create([
            'title' => 'Article valid',
            'content' => str_repeat('Content A ', 100),
            'url' => 'https://example.com/articles/1',
            'source_name' => 'Example News',
            'published_at' => now(),
            'hash' => uniqid()
        ]);
        $project->articles()->attach($article1->id);
        $this->createAiResult($article1, [
            'project_estimated_readers' => null,
        ]);

        // Candidate 2: has valid reach already
        $article2 = Article::create([
            'title' => 'Article filled',
            'content' => str_repeat('Content B ', 100),
            'url' => 'https://example.com/articles/2',
            'source_name' => 'Example News',
            'published_at' => now(),
            'hash' => uniqid()
        ]);
        $project->articles()->attach($article2->id);
        $this->createAiResult($article2, [
            'project_estimated_readers' => 500,
        ]);

        // Candidate 3: Google URL
        $article3 = Article::create([
            'title' => 'Google URL',
            'content' => str_repeat('Content C ', 100),
            'url' => 'https://news.google.com/articles/3',
            'source_name' => 'Example News',
            'published_at' => now(),
            'hash' => uniqid()
        ]);
        $project->articles()->attach($article3->id);
        $this->createAiResult($article3, [
            'project_estimated_readers' => null,
        ]);

        // Candidate 4: only inactive project
        $article4 = Article::create([
            'title' => 'Inactive Project',
            'content' => str_repeat('Content D ', 100),
            'url' => 'https://example.com/articles/4',
            'source_name' => 'Example News',
            'published_at' => now(),
            'hash' => uniqid()
        ]);
        $inactiveProject->articles()->attach($article4->id);
        $this->createAiResult($article4, [
            'project_estimated_readers' => null,
        ]);

        $this->artisan('ai:backfill-article-readers', ['--execute' => true])
             ->expectsOutputToContain('Ditemukan 1 artikel kandidat.')
             ->expectsOutputToContain('Selesai. Telah mengantrekan 1 jobs.')
             ->assertExitCode(0);

        Queue::assertPushed(BackfillArticleReadersJob::class, 1);
        Queue::assertPushed(BackfillArticleReadersJob::class, function ($job) use ($article1) {
            $payload = $job->payload;
            return $payload['id'] === $article1->id && $payload['type'] === 'article';
        });
    }

    public function test_execute_also_dispatches_articles_with_existing_readers_but_missing_score()
    {
        Queue::fake();

        $project = Project::create(['name' => 'Active Project', 'is_active' => true]);

        $article = Article::create([
            'title' => 'Article with readers',
            'content' => str_repeat('Content A ', 100),
            'url' => 'https://example.com/articles/readers',
            'source_name' => 'Example News',
            'published_at' => now(),
            'hash' => uniqid()
        ]);
        $project->articles()->attach($article->id);
        $this->createAiResult($article, [
            'project_estimated_readers' => 1200,
            'project_reach_score' => null,
            'project_reach_level' => null,
            'project_reach_band' => null,
        ]);

        $this->artisan('ai:backfill-article-readers', ['--execute' => true, '--limit' => 10])
             ->expectsOutputToContain('Ditemukan 1 artikel kandidat.')
             ->expectsOutputToContain('Selesai. Telah mengantrekan 1 jobs.')
             ->assertExitCode(0);

        Queue::assertPushed(BackfillArticleReadersJob::class, 1);
        Queue::assertPushed(BackfillArticleReadersJob::class, function ($job) use ($article) {
            $payload = $job->payload;
            return $payload['id'] === $article->id && $payload['type'] === 'article';
        });
    }

    public function test_execute_skips_when_ai_backfill_queue_is_busy()
    {
        Queue::shouldReceive('connection')
            ->with('redis-ai')
            ->andReturn(new class {
                public function size(string $queue): int
                {
                    return $queue === 'ai-backfill' ? 2 : 0;
                }
            });

        $project = Project::create(['name' => 'Active Project', 'is_active' => true]);

        $article = Article::create([
            'title' => 'Article valid',
            'content' => str_repeat('Content A ', 100),
            'url' => 'https://example.com/articles/1',
            'source_name' => 'Example News',
            'published_at' => now(),
            'hash' => uniqid()
        ]);
        $project->articles()->attach($article->id);
        $this->createAiResult($article, [
            'project_estimated_readers' => null,
        ]);

        $this->artisan('ai:backfill-article-readers', ['--execute' => true, '--limit' => 10])
            ->expectsOutputToContain('Queue ai-backfill masih berisi job. Backfill ditunda agar tidak membuat duplikasi.')
            ->assertExitCode(0);
    }
}
