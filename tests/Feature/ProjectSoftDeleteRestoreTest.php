<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Project;
use App\Models\Article;
use App\Models\SocialMediaItem;
use App\Models\AiAnalysisResult;
use App\Models\ApifyActor;
use App\Models\ApifySetting;
use App\Models\ScrapingSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

class ProjectSoftDeleteRestoreTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // Buat user admin
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);

        // Seed ApifySettings
        ApifySetting::create([
            'api_token' => 'apify_test_token_123',
            'connection_status' => 'connected',
        ]);

        ScrapingSetting::create([
            'limit_per_run' => 10,
            'is_active' => true,
        ]);
    }

    public function test_project_delete_performs_soft_delete_and_deactivates()
    {
        $project = Project::create([
            'name' => 'Proyek Test Soft Delete',
            'topics' => ['test'],
            'is_active' => true,
        ]);

        Livewire::test('⚡projects-list')
            ->call('deleteProject', $project->id);

        $project->refresh();
        $this->assertFalse($project->is_active);
        $this->assertNotNull($project->deleted_at);
        $this->assertTrue($project->trashed());
    }

    public function test_project_restore_sets_deleted_at_null_and_activates()
    {
        $project = Project::create([
            'name' => 'Proyek Test Restore',
            'topics' => ['test'],
            'is_active' => false,
        ]);
        $project->delete();

        Livewire::test('⚡projects-list')
            ->call('restoreProject', $project->id);

        $project = Project::withTrashed()->find($project->id);
        $this->assertTrue($project->is_active);
        $this->assertNull($project->deleted_at);
        $this->assertFalse($project->trashed());
    }

    public function test_soft_deleted_project_does_not_participate_in_scraping()
    {
        // Seed active actor first
        ApifyActor::create([
            'platform' => 'Facebook',
            'actor_slug' => 'scrapeflow/facebook-posts-search-scraper',
            'actor_name' => 'Facebook Actor',
            'status' => 'active',
            'priority' => 1,
            'default_limit' => 50,
            'memory_limit' => 1024,
            'range_mode' => '7d',
            'function_type' => 'Search Post',
            'keyword_field_mapping' => 'searchQueries',
            'output_mapping' => '[]',
        ]);

        $projectActive = Project::create([
            'name' => 'Project Active',
            'topics' => ['kunci_active'],
            'is_active' => true,
        ]);

        $projectDeleted = Project::create([
            'name' => 'Project Deleted',
            'topics' => ['kunci_deleted'],
            'is_active' => true,
        ]);
        $projectDeleted->delete();

        // 1. Apify Scraping Command check
        $this->artisan('scraping:run-apify --platform=Facebook --no-telegram')
            ->expectsOutputToContain('Dispatched: [Facebook] keyword=kunci_active')
            ->doesntExpectOutputToContain('kunci_deleted')
            ->assertExitCode(0);

        // 2. News Portal Scraping Command check
        $this->artisan('scraping:run-news --no-telegram')
            ->doesntExpectOutputToContain('kunci_deleted')
            ->assertExitCode(0);
    }

    public function test_manual_scraping_project_soft_deleted_is_rejected()
    {
        // Seed active actor first
        ApifyActor::create([
            'platform' => 'Facebook',
            'actor_slug' => 'scrapeflow/facebook-posts-search-scraper',
            'actor_name' => 'Facebook Actor',
            'status' => 'active',
            'priority' => 1,
            'default_limit' => 50,
            'memory_limit' => 1024,
            'range_mode' => '7d',
            'function_type' => 'Search Post',
            'keyword_field_mapping' => 'searchQueries',
            'output_mapping' => '[]',
        ]);

        $projectDeleted = Project::create([
            'name' => 'Project Deleted',
            'topics' => ['kunci_deleted'],
            'is_active' => true,
        ]);
        $projectDeleted->delete();

        // Check Apify
        $this->artisan("scraping:run-apify --project-id={$projectDeleted->id} --no-telegram")
            ->expectsOutputToContain('Project is deleted/inactive and cannot be scraped.')
            ->assertExitCode(0);

        // Check News
        $this->artisan("scraping:run-news --project-id={$projectDeleted->id} --no-telegram")
            ->expectsOutputToContain('Project is deleted/inactive and cannot be scraped.')
            ->assertExitCode(0);
    }

    public function test_project_soft_delete_preserves_articles_and_ai_results()
    {
        $project = Project::create([
            'name' => 'Project Data Test',
            'topics' => ['kunci'],
            'is_active' => true,
        ]);

        $article = Article::create([
            'title' => 'Article Test title',
            'content' => 'Content of testing article is here.',
            'url' => 'https://example.com/test-article',
            'source_name' => 'News',
        ]);

        $project->articles()->attach($article->id);

        $ai = AiAnalysisResult::create([
            'article_id' => $article->id,
            'analysis_status' => 'success',
            'sentiment' => 'positive',
            'sentiment_score' => 0.8,
            'risk_level' => 'low',
            'summary' => 'Summary of testing article',
            'main_issue' => 'testing main issue',
            'reach_estimate' => 100,
            'reach_score_10' => 5,
            'reach_score_max' => 10,
            'is_exact_reach' => true,
            'reach_level' => 'medium',
            'reach_trend' => 'stable',
            'reach_source' => 'online',
            'reach_confidence' => 'high',
            'reach_reason' => 'none',
            'recommendation' => 'keep monitoring',
            'raw_response' => '{}',
        ]);

        // Soft Delete Project
        Livewire::test('⚡projects-list')
            ->call('deleteProject', $project->id);

        // Verify that Pivot and related entities still exist in DB
        $this->assertDatabaseHas('project_articles', [
            'project_id' => $project->id,
            'article_id' => $article->id,
        ]);
        $this->assertDatabaseHas('articles', ['id' => $article->id]);
        $this->assertDatabaseHas('ai_analysis_results', ['id' => $ai->id]);
    }

    public function test_force_delete_project_removes_project_but_keeps_article_data()
    {
        $project = Project::create([
            'name' => 'Project Force Delete',
            'topics' => ['kunci'],
            'is_active' => false,
        ]);
        $project->delete();

        $article = Article::create([
            'title' => 'Article Keep Test',
            'content' => 'Konten artikel tetap tersimpan setelah proyek dihapus permanen.',
            'url' => 'https://example.com/keep-article',
            'source_name' => 'News',
            'project_id' => $project->id,
        ]);

        $social = SocialMediaItem::create([
            'project_id' => $project->id,
            'platform' => 'Facebook',
            'post_url' => 'https://facebook.com/posts/keep-social',
            'author_name' => 'Akun Uji',
            'content' => 'Konten sosial tetap tersimpan setelah proyek dihapus permanen.',
            'posted_at' => now(),
        ]);

        $project->articles()->attach($article->id);
        $project->socialMediaItems()->attach($social->id);

        Livewire::test('⚡projects-list')
            ->call('forceDeleteProject', $project->id);

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('project_articles', [
            'project_id' => $project->id,
            'article_id' => $article->id,
        ]);
        $this->assertDatabaseMissing('project_social_media_items', [
            'project_id' => $project->id,
            'social_media_item_id' => $social->id,
        ]);
        $this->assertDatabaseHas('articles', ['id' => $article->id]);
        $this->assertDatabaseHas('articles', ['id' => $article->id, 'project_id' => null]);
        $this->assertDatabaseHas('social_media_items', ['id' => $social->id]);
        $this->assertDatabaseHas('social_media_items', ['id' => $social->id, 'project_id' => null]);
    }
}
