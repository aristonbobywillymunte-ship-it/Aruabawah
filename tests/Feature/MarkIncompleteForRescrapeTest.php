<?php

namespace Tests\Feature;

use App\Models\AiAnalysisResult;
use App\Models\Article;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarkIncompleteForRescrapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_change_data(): void
    {
        $project = Project::create([
            'name' => 'Dry Run Project',
            'topics' => ['test'],
            'is_active' => true,
        ]);

        $article = Article::create([
            'title' => 'Needs Rescrape',
            'content' => 'content',
            'url' => 'https://example.test/1',
            'canonical_url' => 'https://example.test/1',
            'source_name' => 'Example',
            'published_at' => now(),
            'sentiment' => 'neutral',
            'category' => 'news',
        ]);

        DB::table('project_articles')->insert([
            'project_id' => $project->id,
            'article_id' => $article->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exit = Artisan::call('articles:mark-incomplete-for-rescrape', [
            '--ids' => (string) $article->id,
        ]);

        $this->assertSame(0, $exit);
        $this->assertNull(DB::table('project_articles')->where('project_id', $project->id)->value('rescrape_status'));
    }

    public function test_apply_marks_exact_ids_and_skips_id_80(): void
    {
        $project = Project::create([
            'name' => 'Apply Project',
            'topics' => ['test'],
            'is_active' => true,
        ]);

        foreach ([765, 80, 798] as $id) {
            DB::table('articles')->insert([
                'id' => $id,
                'title' => 'Article ' . $id,
                'content' => 'content',
                'url' => 'https://example.test/' . $id,
                'canonical_url' => 'https://example.test/' . $id,
                'source_name' => 'Example',
                'published_at' => now(),
                'sentiment' => 'neutral',
                'category' => 'news',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('project_articles')->insert([
                'project_id' => $project->id,
                'article_id' => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        AiAnalysisResult::query()->create([
            'article_id' => 765,
            'summary' => 'ok',
            'sentiment' => 'neutral',
            'sentiment_score' => 0.0,
            'main_issue' => 'test',
            'risk_level' => 'low',
            'risk_reason' => 'test',
            'reach_estimate' => 0,
            'reach_score_10' => 0,
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
            'potential_reach_score' => 0,
            'potential_reach_level' => 'N/A',
            'potential_reach_band' => 'N/A',
            'potential_estimated_readers' => 0,
            'project_estimated_readers' => 847,
            'project_reach_score' => 9,
            'project_reach_level' => 'Sangat tinggi',
            'project_reach_band' => 'N/A',
            'analysis_status' => 'success',
            'validation_errors' => null,
            'recommendation' => 'test',
            'raw_response' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exit = Artisan::call('articles:mark-incomplete-for-rescrape', [
            '--ids' => '765,80,798',
            '--project-id' => $project->id,
            '--apply' => true,
        ]);

        $this->assertSame(0, $exit);
        $this->assertNull(DB::table('project_articles')->where('article_id', 765)->value('rescrape_status'));
        $this->assertNull(DB::table('project_articles')->where('article_id', 80)->value('rescrape_status'));
        $this->assertSame('needs_rescrape', DB::table('project_articles')->where('article_id', 798)->value('rescrape_status'));
    }

    public function test_cross_project_records_can_be_marked_per_project(): void
    {
        $projectA = Project::create(['name' => 'Project A', 'topics' => ['a'], 'is_active' => true]);
        $projectB = Project::create(['name' => 'Project B', 'topics' => ['b'], 'is_active' => true]);

        $article = Article::create([
            'title' => 'Cross Project',
            'content' => 'content',
            'url' => 'https://example.test/cross',
            'canonical_url' => 'https://example.test/cross',
            'source_name' => 'Example',
            'published_at' => now(),
            'sentiment' => 'neutral',
            'category' => 'news',
        ]);

        foreach ([$projectA->id, $projectB->id] as $projectId) {
            DB::table('project_articles')->insert([
                'project_id' => $projectId,
                'article_id' => $article->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $exit = Artisan::call('articles:mark-incomplete-for-rescrape', [
            '--ids' => (string) $article->id,
            '--project-id' => $projectA->id,
            '--apply' => true,
        ]);

        $this->assertSame(0, $exit);
        $this->assertSame('needs_rescrape', DB::table('project_articles')->where('project_id', $projectA->id)->where('article_id', $article->id)->value('rescrape_status'));
        $this->assertNull(DB::table('project_articles')->where('project_id', $projectB->id)->where('article_id', $article->id)->value('rescrape_status'));
    }

    public function test_pending_operational_excludes_needs_rescrape(): void
    {
        $project = Project::create([
            'name' => 'Dashboard Project',
            'topics' => ['test'],
            'is_active' => true,
        ]);

        $article = Article::create([
            'title' => 'Rescrape Item',
            'content' => 'content',
            'url' => 'https://example.test/dashboard',
            'canonical_url' => 'https://example.test/dashboard',
            'source_name' => 'Example',
            'published_at' => now(),
            'sentiment' => 'neutral',
            'category' => 'news',
        ]);

        DB::table('project_articles')->insert([
            'project_id' => $project->id,
            'article_id' => $article->id,
            'rescrape_status' => 'needs_rescrape',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create();
        $user->projects()->attach($project->id);
        $this->actingAs($user);

        $summary = app(\App\Http\Livewire\ProjectsList::class)->getProjects();
        $this->assertSame(0, $summary[0]['ai_pending']);
        $this->assertSame(1, $summary[0]['ai_rescrape']);
    }

    public function test_apply_clears_stale_rescrape_for_display_ready_overlap(): void
    {
        $project = Project::create([
            'name' => 'Overlap Project',
            'topics' => ['test'],
            'is_active' => true,
        ]);

        $article = Article::create([
            'title' => 'Display Ready Article',
            'content' => 'content',
            'url' => 'https://example.test/overlap',
            'canonical_url' => 'https://example.test/overlap',
            'source_name' => 'Example',
            'published_at' => now(),
            'sentiment' => 'neutral',
            'category' => 'news',
        ]);

        DB::table('project_articles')->insert([
            'project_id' => $project->id,
            'article_id' => $article->id,
            'rescrape_status' => 'needs_rescrape',
            'rescrape_reason' => 'old reason',
            'rescrape_requested_at' => now(),
            'rescrape_source' => 'legacy',
            'rescrape_meta' => json_encode(['legacy' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AiAnalysisResult::create([
            'article_id' => $article->id,
            'summary' => 'ok',
            'sentiment' => 'neutral',
            'sentiment_score' => 0.0,
            'main_issue' => 'test',
            'risk_level' => 'low',
            'risk_reason' => 'test',
            'reach_estimate' => 0,
            'reach_score_10' => 0,
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
            'potential_reach_score' => 0,
            'potential_reach_level' => 'N/A',
            'potential_reach_band' => 'N/A',
            'potential_estimated_readers' => 0,
            'project_estimated_readers' => 847,
            'project_reach_score' => 9,
            'project_reach_level' => 'Sangat tinggi',
            'project_reach_band' => 'N/A',
            'analysis_status' => 'success',
            'validation_errors' => null,
            'recommendation' => 'test',
            'raw_response' => '{}',
        ]);

        $this->artisan('articles:mark-incomplete-for-rescrape', [
            '--ids' => (string) $article->id,
            '--project-id' => $project->id,
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertNull(DB::table('project_articles')->where('project_id', $project->id)->where('article_id', $article->id)->value('rescrape_status'));
        $this->assertNull(DB::table('project_articles')->where('project_id', $project->id)->where('article_id', $article->id)->value('rescrape_reason'));
        $this->assertNull(DB::table('project_articles')->where('project_id', $project->id)->where('article_id', $article->id)->value('rescrape_requested_at'));
    }
}
