<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Article;
use App\Models\SocialMediaItem;
use App\Models\Project;
use App\Services\ContentMatchingService;
use Illuminate\Support\Facades\DB;

class ContentMatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Project::truncate();
        Article::truncate();
        DB::table('project_articles')->truncate();
    }

    public function test_single_article_saved_once_and_matches_only_a()
    {
        $projectA = Project::create([
            'name' => 'Project A',
            'topics' => ['Seno Aji'],
            'is_active' => true,
        ]);

        $article = Article::create([
            'title' => 'Seno Aji mengunjungi sekolah',
            'content' => 'Acara berlangsung meriah',
            'url' => 'http://example.com/1',
            'sentiment' => 'neutral',
            'source_name' => 'test'
        ]);

        $service = app(ContentMatchingService::class);
        $matched = $service->crossLinkToActiveProjects($article, $projectA->id);

        $this->assertCount(1, $matched);
        $this->assertEquals([$projectA->id], $matched);
        $this->assertCount(1, $article->fresh()->projects);
        $this->assertEquals(1, Article::count()); // One article stored once
    }

    public function test_two_projects_result_in_two_pivots()
    {
        $projectA = Project::create(['name' => 'A', 'topics' => ['Seno Aji'], 'is_active' => true]);
        $projectB = Project::create(['name' => 'B', 'topics' => ['Rudy Mas\'ud'], 'is_active' => true]);

        $article = Article::create([
            'title' => 'Seno Aji dan Rudy Mas\'ud bertemu',
            'content' => 'Acara pertemuan',
            'url' => 'http://example.com/2',
            'sentiment' => 'neutral',
            'source_name' => 'test'
        ]);

        $service = app(ContentMatchingService::class);
        $matched = $service->crossLinkToActiveProjects($article, $projectA->id);

        $this->assertCount(2, $matched);
        $this->assertEquals(2, DB::table('project_articles')->where('article_id', $article->id)->count());
    }

    public function test_duplicate_pivot_is_rejected()
    {
        $projectA = Project::create(['name' => 'A', 'topics' => ['Seno Aji'], 'is_active' => true]);

        $article = Article::create([
            'title' => 'Seno Aji hadir',
            'content' => 'Acara pertemuan',
            'url' => 'http://example.com/3',
            'sentiment' => 'neutral',
            'source_name' => 'test'
        ]);

        $service = app(ContentMatchingService::class);
        $service->crossLinkToActiveProjects($article, $projectA->id);
        
        // Simulating second scrape / rediscovery
        $service->crossLinkToActiveProjects($article, $projectA->id);

        // Should still only have 1 pivot
        $this->assertEquals(1, DB::table('project_articles')->where('article_id', $article->id)->count());
    }

    public function test_discovery_project_is_not_exclusive_owner()
    {
        $projectA = Project::create(['name' => 'A', 'topics' => ['Berita bola'], 'is_active' => true]);
        $projectB = Project::create(['name' => 'B', 'topics' => ['Timnas menang'], 'is_active' => true]);

        $article = Article::create([
            'title' => 'Berita bola terbaru',
            'content' => 'Timnas menang lagi',
            'url' => 'http://example.com/4',
            'sentiment' => 'neutral',
            'source_name' => 'test'
        ]);

        $service = app(ContentMatchingService::class);
        // Project A is the discovery project
        $service->crossLinkToActiveProjects($article, $projectA->id);

        // Project B should also own it via pivot
        $hasPivotB = DB::table('project_articles')->where('article_id', $article->id)->where('project_id', $projectB->id)->exists();
        $this->assertTrue($hasPivotB);
    }

    public function test_unicode_case_insensitive_and_normalization()
    {
        $service = app(ContentMatchingService::class);
        
        // Seno Aji matches "Seno Aji"
        $this->assertTrue($service->isStrictMatch("Seno Aji", "Hari ini Seno Aji hadir"));
        
        // "seno aji" matches
        $this->assertTrue($service->isStrictMatch("Seno Aji", "Hari ini seno aji hadir"));
        
        // Klopp not matches Seno Aji (false positive prevention)
        $this->assertFalse($service->isStrictMatch("Aji", "Jurgen Klopp wajib menang"));
        
        // Rudy Mas'ud matches Rudy Mas’ud (apostrophe normalization)
        $this->assertTrue($service->isStrictMatch("Rudy Mas'ud", "Rudy Mas’ud tiba di lokasi"));
        $this->assertTrue($service->isStrictMatch("Rudy Mas’ud", "Rudy Mas'ud tiba di lokasi"));
        
        // Rudy Hartono not matches Rudy Mas'ud
        $this->assertFalse($service->isStrictMatch("Rudy Mas'ud", "Rudy Hartono hadir"));
        
        // Short keyword rejection (<= 2 chars)
        $this->assertFalse($service->isStrictMatch("aj", "Ada saja")); 
        
        // Hyphenated phrase
        $this->assertTrue($service->isStrictMatch("rumah-sakit", "dia ke rumah-sakit kemarin"));
    }

    public function test_social_match_ignores_apify_search_query_fields(): void
    {
        $project = Project::create([
            'name' => 'Project Gubernur Kaltim',
            'topics' => ['gubernur kaltim'],
            'is_active' => true,
        ]);

        $item = SocialMediaItem::create([
            'project_id' => null,
            'platform' => 'Instagram',
            'post_url' => 'https://instagram.com/p/test-query-field',
            'author_name' => 'Akun Publik',
            'content' => 'Konten umum tanpa kecocokan kata kunci proyek.',
            'raw_json' => json_encode([
                'searchQuery' => 'gubernur kaltim',
                'searchTerm' => 'gubernur kaltim',
                'keyword' => 'gubernur kaltim',
                'query' => 'gubernur kaltim',
            ]),
        ]);

        $service = app(ContentMatchingService::class);
        $matched = $service->crossLinkToActiveProjects($item, null);

        $this->assertSame([], $matched);
        $this->assertSame(0, DB::table('project_social_media_items')->where('social_media_item_id', $item->id)->count());
    }

    public function test_social_discovery_project_is_not_auto_linked_without_keyword_match(): void
    {
        $project = Project::create([
            'name' => 'Project Gubernur Kaltim',
            'topics' => ['gubernur kaltim'],
            'is_active' => true,
        ]);

        $item = SocialMediaItem::create([
            'project_id' => null,
            'platform' => 'Instagram',
            'post_url' => 'https://instagram.com/p/test-discovery-link',
            'author_name' => 'Akun Publik',
            'content' => 'Konten umum tentang kegiatan provinsi tanpa keyword proyek.',
            'raw_json' => json_encode([
                'hashtags' => ['kaltimbergerak'],
                'caption' => 'Konten umum tanpa keyword proyek.',
            ]),
        ]);

        $service = app(ContentMatchingService::class);
        $matched = $service->crossLinkToActiveProjects($item, $project->id);

        $this->assertSame([], $matched);
        $this->assertSame(0, DB::table('project_social_media_items')->where('social_media_item_id', $item->id)->count());
    }
}
