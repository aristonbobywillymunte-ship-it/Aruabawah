<?php

namespace Tests\Feature;

use App\Jobs\ApifyScrapingJob;
use App\Models\ApifyActor;
use App\Models\ApifyDispatchState;
use App\Models\ApifySetting;
use App\Models\Package;
use App\Models\Project;
use App\Models\SocialMediaItem;
use App\Services\Scraping\SocialCommentScraperDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class SocialCommentScraperDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_social_import_triggers_comment_dispatch_after_facebook_post_is_persisted(): void
    {
        $this->runMainSocialImportDispatchScenario('Facebook', 'https://www.facebook.com/wagubkaltim/posts/1001');
    }

    public function test_main_social_import_triggers_comment_dispatch_after_instagram_post_is_persisted(): void
    {
        $this->runMainSocialImportDispatchScenario('Instagram', 'https://www.instagram.com/p/wagubkaltim001');
    }

    public function test_main_social_import_triggers_comment_dispatch_after_tiktok_post_is_persisted(): void
    {
        $this->runMainSocialImportDispatchScenario('TikTok', 'https://www.tiktok.com/@wagubkaltim/video/1001');
    }

    public function test_comment_dispatcher_fallback_scheduler_finds_pending_urls(): void
    {
        Queue::fake();
        Cache::flush();
        ApifyDispatchState::truncate();

        $package = Package::create([
            'name' => 'Fallback Package',
            'description' => 'Test package',
            'price' => 0,
            'social_media_features' => [],
            'news_portal_features' => [],
            'advantages' => [],
            'is_active' => true,
            'use_portal' => true,
            'news_interval_minutes' => 15,
            'social_interval_minutes' => 15,
            'news_runs_per_day' => 1,
            'news_run_times' => ['08:00'],
            'social_runs_per_day' => 1,
            'social_run_times' => ['08:00'],
            'is_popular' => false,
            'max_projects' => 5,
            'max_keywords_per_project' => 5,
        ]);

        $project = Project::create([
            'name' => 'Wagub Kaltim',
            'topics' => ['Wagub Kaltim'],
            'is_active' => true,
            'package_id' => $package->id,
        ]);

        $actor = ApifyActor::create([
            'platform' => 'Facebook',
            'actor_name' => 'Facebook Comment Scraper',
            'actor_slug' => 'test/facebook-comment-scraper',
            'function_type' => 'Comment Scraper',
            'status' => 'active',
            'priority' => 1,
            'default_limit' => 10,
            'interval_minutes' => 30,
            'memory_limit' => 1024,
            'range_mode' => '7d',
        ]);
        $package->actors()->attach($actor->id, [
            'is_enabled' => true,
            'cost_per_run_usd' => 0.25,
            'default_limit' => 10,
            'memory_limit' => 1024,
        ]);

        $post = SocialMediaItem::create([
            'project_id' => $project->id,
            'platform' => 'Facebook',
            'post_url' => 'https://www.facebook.com/wagubkaltim/posts/1001',
            'author_name' => 'Wagub Kaltim',
            'content' => 'Wagub Kaltim hadir di acara publik bersama warga.',
            'posted_at' => now(),
            'comments_checked' => false,
        ]);
        $post->projects()->attach($project->id);

        $service = app(SocialCommentScraperDispatcher::class);
        $result = $service->dispatchEligible($project, 'Facebook');

        $this->assertTrue($result['dispatched']);
        $this->assertSame($actor->id, $result['actor_id']);
        Queue::assertPushed(ApifyScrapingJob::class, 1);
    }

    public function test_resolve_candidate_urls_returns_plain_strings_for_all_supported_platforms(): void
    {
        Cache::flush();

        $package = Package::create([
            'name' => 'String Candidate Package',
            'description' => 'Test package',
            'price' => 0,
            'social_media_features' => [],
            'news_portal_features' => [],
            'advantages' => [],
            'is_active' => true,
            'use_portal' => true,
            'news_interval_minutes' => 15,
            'social_interval_minutes' => 15,
            'news_runs_per_day' => 1,
            'news_run_times' => ['08:00'],
            'social_runs_per_day' => 1,
            'social_run_times' => ['08:00'],
            'is_popular' => false,
            'max_projects' => 5,
            'max_keywords_per_project' => 5,
        ]);

        $project = Project::create([
            'name' => 'String Candidate Project',
            'topics' => ['Wagub Kaltim'],
            'is_active' => true,
            'package_id' => $package->id,
        ]);

        $facebookActor = ApifyActor::create([
            'platform' => 'Facebook',
            'actor_name' => 'Facebook Comment Scraper',
            'actor_slug' => 'test/facebook-comment-scraper-string',
            'function_type' => 'Comment Scraper',
            'status' => 'active',
            'priority' => 1,
            'default_limit' => 10,
            'interval_minutes' => 30,
            'memory_limit' => 1024,
            'range_mode' => '7d',
        ]);
        $instagramActor = ApifyActor::create([
            'platform' => 'Instagram',
            'actor_name' => 'Instagram Comment Scraper',
            'actor_slug' => 'test/instagram-comment-scraper-string',
            'function_type' => 'Comment Scraper',
            'status' => 'active',
            'priority' => 1,
            'default_limit' => 10,
            'interval_minutes' => 30,
            'memory_limit' => 1024,
            'range_mode' => '7d',
        ]);
        $tiktokActor = ApifyActor::create([
            'platform' => 'TikTok',
            'actor_name' => 'TikTok Comment Scraper',
            'actor_slug' => 'test/tiktok-comment-scraper-string',
            'function_type' => 'Comment Scraper',
            'status' => 'active',
            'priority' => 1,
            'default_limit' => 10,
            'interval_minutes' => 30,
            'memory_limit' => 1024,
            'range_mode' => '7d',
        ]);

        foreach ([$facebookActor, $instagramActor, $tiktokActor] as $actor) {
            $package->actors()->attach($actor->id, [
                'is_enabled' => true,
                'cost_per_run_usd' => 0.25,
                'default_limit' => 10,
                'memory_limit' => 1024,
            ]);
        }

        foreach ([
            'Facebook' => 'https://www.facebook.com/example/posts/123',
            'Instagram' => 'https://www.instagram.com/p/example123/',
            'TikTok' => 'https://www.tiktok.com/@example/video/123',
        ] as $platform => $url) {
            $item = SocialMediaItem::create([
                'project_id' => $project->id,
                'platform' => $platform,
                'post_url' => $url,
                'author_name' => 'Example Author',
                'content' => 'Example content',
                'posted_at' => now(),
                'comments_checked' => false,
            ]);
            $item->projects()->attach($project->id);
        }

        $dispatcher = app(SocialCommentScraperDispatcher::class);

        $facebookCandidates = $dispatcher->resolveCandidateUrls($project, 'Facebook');
        $instagramCandidates = $dispatcher->resolveCandidateUrls($project, 'Instagram');
        $tiktokCandidates = $dispatcher->resolveCandidateUrls($project, 'TikTok');

        $this->assertNotEmpty($facebookCandidates);
        $this->assertNotEmpty($instagramCandidates);
        $this->assertNotEmpty($tiktokCandidates);

        $this->assertTrue($facebookCandidates->every(fn ($candidate) => is_string($candidate)));
        $this->assertTrue($instagramCandidates->every(fn ($candidate) => is_string($candidate)));
        $this->assertTrue($tiktokCandidates->every(fn ($candidate) => is_string($candidate)));

        $this->assertSame('https://www.facebook.com/example/posts/123', $facebookCandidates->first());
        $this->assertSame('https://www.instagram.com/p/example123/', $instagramCandidates->first());
        $this->assertSame('https://www.tiktok.com/@example/video/123', $tiktokCandidates->first());
    }

    public function test_active_comment_scraper_blocks_duplicate_dispatch_for_same_project_and_platform(): void
    {
        Queue::fake();
        Cache::flush();
        ApifyDispatchState::truncate();

        $package = Package::create([
            'name' => 'Duplicate Guard Package',
            'description' => 'Test package',
            'price' => 0,
            'social_media_features' => [],
            'news_portal_features' => [],
            'advantages' => [],
            'is_active' => true,
            'use_portal' => true,
            'news_interval_minutes' => 15,
            'social_interval_minutes' => 15,
            'news_runs_per_day' => 1,
            'news_run_times' => ['08:00'],
            'social_runs_per_day' => 1,
            'social_run_times' => ['08:00'],
            'is_popular' => false,
            'max_projects' => 5,
            'max_keywords_per_project' => 5,
        ]);

        $project = Project::create([
            'name' => 'Duplicate Guard Project',
            'topics' => ['Wagub Kaltim'],
            'is_active' => true,
            'package_id' => $package->id,
        ]);

        $actor = ApifyActor::create([
            'platform' => 'Facebook',
            'actor_name' => 'Facebook Comment Scraper',
            'actor_slug' => 'test/facebook-comment-scraper-active',
            'function_type' => 'Comment Scraper',
            'status' => 'active',
            'priority' => 1,
            'default_limit' => 10,
            'interval_minutes' => 30,
            'memory_limit' => 1024,
            'range_mode' => '7d',
        ]);
        $package->actors()->attach($actor->id, [
            'is_enabled' => true,
            'cost_per_run_usd' => 0.25,
            'default_limit' => 10,
            'memory_limit' => 1024,
        ]);

        $post = SocialMediaItem::create([
            'project_id' => $project->id,
            'platform' => 'Facebook',
            'post_url' => 'https://www.facebook.com/duplicate-guard/posts/1001',
            'author_name' => 'Duplicate Guard',
            'content' => 'Duplicate guard test post.',
            'posted_at' => now(),
            'comments_checked' => false,
        ]);
        $post->projects()->attach($project->id);

        ApifyDispatchState::create([
            'project_id' => $project->id,
            'actor_id' => $actor->id,
            'platform' => 'Facebook',
            'keyword' => $post->post_url,
            'normalized_keyword' => $post->post_url,
            'dispatch_key' => 'comment-duplicate-guard',
            'window_start' => now()->subMinutes(5),
            'window_end' => now()->addMinutes(25),
            'status' => 'processing',
            'queued_at' => now()->subMinutes(1),
            'started_at' => now()->subMinutes(1),
            'updated_at' => now()->subMinutes(1),
            'created_at' => now()->subMinutes(1),
        ]);

        $service = app(SocialCommentScraperDispatcher::class);
        $result = $service->dispatchEligible($project, 'Facebook');

        $this->assertFalse($result['dispatched']);
        $this->assertSame('active_comment_scraper', $result['reason']);
        Queue::assertNothingPushed();
    }

    public function test_comment_payloads_use_resolved_string_urls_without_json_wrapping(): void
    {
        Cache::flush();

        $package = Package::create([
            'name' => 'Payload String Package',
            'description' => 'Test package',
            'price' => 0,
            'social_media_features' => [],
            'news_portal_features' => [],
            'advantages' => [],
            'is_active' => true,
            'use_portal' => true,
            'news_interval_minutes' => 15,
            'social_interval_minutes' => 15,
            'news_runs_per_day' => 1,
            'news_run_times' => ['08:00'],
            'social_runs_per_day' => 1,
            'social_run_times' => ['08:00'],
            'is_popular' => false,
            'max_projects' => 5,
            'max_keywords_per_project' => 5,
        ]);

        $project = Project::create([
            'name' => 'Payload String Project',
            'topics' => ['Wagub Kaltim'],
            'is_active' => true,
            'package_id' => $package->id,
        ]);

        $actor = ApifyActor::create([
            'platform' => 'Facebook',
            'actor_name' => 'Facebook Comment Scraper',
            'actor_slug' => 'test/facebook-comment-scraper-payload',
            'function_type' => 'Comment Scraper',
            'status' => 'active',
            'priority' => 1,
            'default_limit' => 10,
            'interval_minutes' => 30,
            'memory_limit' => 1024,
            'range_mode' => '7d',
        ]);
        $package->actors()->attach($actor->id, [
            'is_enabled' => true,
            'cost_per_run_usd' => 0.25,
            'default_limit' => 10,
            'memory_limit' => 1024,
        ]);

        $facebook = SocialMediaItem::create([
            'project_id' => $project->id,
            'platform' => 'Facebook',
            'post_url' => 'https://www.facebook.com/example/posts/123',
            'author_name' => 'Example Author',
            'content' => 'Example content',
            'posted_at' => now(),
            'comments_checked' => false,
        ]);
        $facebook->projects()->attach($project->id);

        $instagram = SocialMediaItem::create([
            'project_id' => $project->id,
            'platform' => 'Instagram',
            'post_url' => 'https://www.instagram.com/p/example123/',
            'author_name' => 'Example Author',
            'content' => 'Example content',
            'posted_at' => now(),
            'comments_checked' => false,
        ]);
        $instagram->projects()->attach($project->id);

        $tiktok = SocialMediaItem::create([
            'project_id' => $project->id,
            'platform' => 'TikTok',
            'post_url' => 'https://www.tiktok.com/@example/video/123',
            'author_name' => 'Example Author',
            'content' => 'Example content',
            'posted_at' => now(),
            'comments_checked' => false,
        ]);
        $tiktok->projects()->attach($project->id);

        $dispatcher = app(SocialCommentScraperDispatcher::class);
        $candidates = $dispatcher->resolveCandidateUrls($project, 'Facebook');
        $this->assertTrue($candidates->every(fn ($candidate) => is_string($candidate)));

        $resolved = $candidates->values()->all();

        $facebookPayload = (new ApifyActor([
            'platform' => 'Facebook',
            'actor_name' => 'Facebook Comment Scraper',
            'actor_slug' => 'test/facebook-comment-scraper-payload',
            'function_type' => 'Comment Scraper',
            'default_limit' => 10,
            'range_mode' => '7d',
        ]))->buildInputPayload($resolved[0], 10, null, null, $resolved);

        $instagramCandidates = $dispatcher->resolveCandidateUrls($project, 'Instagram');
        $instagramPayload = (new ApifyActor([
            'platform' => 'Instagram',
            'actor_name' => 'Instagram Comment Scraper',
            'actor_slug' => 'test/instagram-comment-scraper-payload',
            'function_type' => 'Comment Scraper',
            'default_limit' => 10,
            'range_mode' => '7d',
        ]))->buildInputPayload($instagramCandidates->first(), 10, null, null, $instagramCandidates->values()->all());

        $tiktokCandidates = $dispatcher->resolveCandidateUrls($project, 'TikTok');
        $tiktokPayload = (new ApifyActor([
            'platform' => 'TikTok',
            'actor_name' => 'TikTok Comment Scraper',
            'actor_slug' => 'test/tiktok-comment-scraper-payload',
            'function_type' => 'Comment Scraper',
            'default_limit' => 10,
            'range_mode' => '7d',
        ]))->buildInputPayload($tiktokCandidates->first(), 10, null, null, $tiktokCandidates->values()->all());

        $this->assertNotEmpty($facebookPayload['startUrls'] ?? []);
        $this->assertNotEmpty($instagramPayload['directUrls'] ?? []);
        $this->assertNotEmpty($tiktokPayload['postURLs'] ?? []);
    }

    public function test_project_without_package_cannot_use_global_comment_actor(): void
    {
        $project = Project::create([
            'name' => 'No Package Project',
            'topics' => ['Wagub Kaltim'],
            'is_active' => true,
        ]);

        ApifyActor::create([
            'platform' => 'Instagram',
            'actor_name' => 'Global Instagram Comment Scraper',
            'actor_slug' => 'test/global-instagram-comment-scraper',
            'function_type' => 'Comment Scraper',
            'status' => 'active',
            'priority' => 1,
            'default_limit' => 10,
            'interval_minutes' => 30,
            'memory_limit' => 1024,
            'range_mode' => '7d',
        ]);

        $dispatcher = app(SocialCommentScraperDispatcher::class);

        $this->assertFalse($dispatcher->hasEnabledCommentScraperActor($project, 'Instagram'));
        $this->assertNull($dispatcher->resolveCommentScraperActor($project, 'Instagram'));
        $this->assertSame('comment_actor_missing', $dispatcher->dispatchEligible($project, 'Instagram')['reason']);
    }

    protected function runMainSocialImportDispatchScenario(string $platform, string $postUrl): void
    {
        ApifySetting::create([
            'api_token' => 'test-token',
            'connection_status' => 'connected',
        ]);

        $package = Package::create([
            'name' => "Pkg {$platform}",
            'description' => 'Test package',
            'price' => 0,
            'social_media_features' => [],
            'news_portal_features' => [],
            'advantages' => [],
            'is_active' => true,
            'use_portal' => true,
            'news_interval_minutes' => 15,
            'social_interval_minutes' => 15,
            'news_runs_per_day' => 1,
            'news_run_times' => ['08:00'],
            'social_runs_per_day' => 1,
            'social_run_times' => ['08:00'],
            'is_popular' => false,
            'max_projects' => 5,
            'max_keywords_per_project' => 5,
        ]);

        $project = Project::create([
            'name' => "Wagub Kaltim {$platform}",
            'topics' => ['Wagub Kaltim'],
            'is_active' => true,
            'package_id' => $package->id,
        ]);

        $actor = ApifyActor::create([
            'platform' => $platform,
            'actor_name' => "{$platform} Search Actor",
            'actor_slug' => "test/{$platform}-search-actor",
            'function_type' => 'Search Post',
            'status' => 'active',
            'priority' => 1,
            'default_limit' => 10,
            'interval_minutes' => 30,
            'memory_limit' => 1024,
            'range_mode' => '7d',
        ]);

        $package->actors()->attach($actor->id, [
            'is_enabled' => true,
            'cost_per_run_usd' => 0.25,
            'default_limit' => 10,
            'memory_limit' => 1024,
        ]);

        $dispatcher = Mockery::mock(SocialCommentScraperDispatcher::class);
        $dispatcher->shouldReceive('hasEnabledCommentScraperActor')
            ->once()
            ->withArgs(function (Project $boundProject, string $boundPlatform) use ($project, $platform) {
                return $boundProject->id === $project->id && $boundPlatform === $platform;
            })
            ->andReturn(true);
        $dispatcher->shouldReceive('dispatchEligible')
            ->once()
            ->withArgs(function (Project $boundProject, string $boundPlatform) use ($project, $platform) {
                $this->assertSame($project->id, $boundProject->id);
                $this->assertSame($platform, $boundPlatform);
                return true;
            })
            ->andReturn(['dispatched' => false, 'reason' => 'mocked']);

        app()->instance(SocialCommentScraperDispatcher::class, $dispatcher);

        Http::fake([
            'https://api.apify.com/v2/acts/*/runs*' => Http::response([
                'data' => [
                    'id' => 'run-social-' . strtolower($platform),
                    'defaultDatasetId' => 'dataset-social-' . strtolower($platform),
                ],
            ], 201),
            'https://api.apify.com/v2/actor-runs/*' => Http::response([
                'data' => ['status' => 'SUCCEEDED'],
            ], 200),
            'https://api.apify.com/v2/datasets/*/items*' => Http::response([
                [
                    'url' => $postUrl,
                    'text' => 'Wagub Kaltim hadir di acara publik bersama warga.',
                    'username' => 'kaltim_user',
                ],
            ], 200),
        ]);

        (new ApifyScrapingJob([
            'platform' => $platform,
            'keyword' => 'Wagub Kaltim',
            'keywords' => ['Wagub Kaltim'],
            'project_id' => $project->id,
            'actor_id' => $actor->id,
            'limit' => 1,
            'no_telegram' => true,
        ]))->handle();

        $this->assertDatabaseHas('social_media_items', [
            'post_url' => $postUrl,
            'project_id' => $project->id,
            'platform' => $platform,
        ]);
        $this->assertTrue(
            SocialMediaItem::where('post_url', $postUrl)
                ->first()
                ->projects()
                ->where('projects.id', $project->id)
                ->exists(),
            'Social item must be linked to the project before comment dispatch verification completes.'
        );
        $this->assertDatabaseHas('social_media_items', [
            'post_url' => $postUrl,
            'project_id' => $project->id,
            'platform' => $platform,
            'comments_checked' => false,
        ]);

        Cache::flush();
    }

    public function socialDispatchCases(): array
    {
        return [
            ['Facebook', 'https://www.facebook.com/wagubkaltim/posts/1001'],
            ['Instagram', 'https://www.instagram.com/p/wagubkaltim001/'],
            ['TikTok', 'https://www.tiktok.com/@wagubkaltim/video/1001'],
        ];
    }
}
