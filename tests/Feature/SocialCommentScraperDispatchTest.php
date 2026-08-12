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
