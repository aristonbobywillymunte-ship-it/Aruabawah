<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Project;
use App\Models\ApifyActor;
use App\Models\ApifySetting;
use App\Models\ApifyDispatchState;
use App\Jobs\ApifyScrapingJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use App\Models\SocialMediaItem;
use App\Services\SchedulerQueueGuard;

class ApifyDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_keyword_different_case_normalizes_to_one_dispatch()
    {
        $project = new Project();
        $project->topics = [" Seno Aji ", "seno aji"];
        $keywords = array_values(array_unique($project->scrapeKeywords()));
        
        $this->assertCount(1, $keywords);
        $this->assertEquals("Seno Aji", $keywords[0]);
    }

    public function test_same_project_same_interval_merges_social_keywords_into_one_dispatch()
    {
        ApifyDispatchState::truncate();
        
        $params1 = [
            'platform' => 'Facebook',
            'keyword' => 'wagub kaltim',
            'project_id' => 1,
            'actor_id' => 1
        ];

        $params2 = [
            'platform' => 'Facebook',
            'keyword' => 'kaltim',
            'project_id' => 1,
            'actor_id' => 1
        ];

        // Social platforms now dispatch once per project per interval window.
        // Different keywords in the same project/interval should reuse the same queue slot.
        $this->assertTrue(ApifyScrapingJob::dispatchSafely($params1));
        $this->assertFalse(ApifyScrapingJob::dispatchSafely($params2));

        $states = ApifyDispatchState::all();
        $this->assertCount(1, $states);
    }
    
    public function test_duplicate_dispatch_is_prevented()
    {
        ApifyDispatchState::truncate();
        
        $params = [
            'platform' => 'Facebook',
            'keyword' => 'samarinda',
            'project_id' => 2,
            'actor_id' => 1
        ];

        $this->assertTrue(ApifyScrapingJob::dispatchSafely($params));
        // Second time should be blocked by state machine
        $this->assertFalse(ApifyScrapingJob::dispatchSafely($params));
        
        $states = ApifyDispatchState::all();
        $this->assertCount(1, $states);
    }

    public function test_failed_actor_is_retried_automatically_after_cooldown_expires(): void
    {
        Queue::fake();

        ApifySetting::create([
            'api_token' => 'test-token',
            'connection_status' => 'connected',
        ]);

        $actor = ApifyActor::create([
            'platform' => 'Facebook',
            'actor_name' => 'Facebook Posts Search Scraper',
            'actor_slug' => 'scrapeflow/facebook-posts-search-scraper',
            'function_type' => 'Search Post',
            'status' => 'active',
            'default_limit' => 50,
            'interval_minutes' => 20,
            'memory_limit' => 2048,
            'range_mode' => '7d',
            'priority' => 1,
            'last_run_status' => 'failed',
            'last_run_message' => 'Connection timeout',
            'last_run_at' => now()->subMinutes(25),
        ]);

        Project::create([
            'name' => 'Gubernur Kaltim',
            'topics' => ['gubernur kaltim'],
            'is_active' => true,
        ]);

        Artisan::call('scraping:run-apify');

        Queue::assertPushed(ApifyScrapingJob::class, 1);
        $this->assertNull(Cache::get("apify_actor_retry_at:{$actor->id}"));
    }

    public function test_scheduler_guard_ignores_stale_processing_state(): void
    {
        ApifyDispatchState::create([
            'dispatch_key' => 'stale-processing-key',
            'project_id' => 9,
            'actor_id' => 1,
            'platform' => 'Facebook',
            'keyword' => 'stale',
            'normalized_keyword' => 'stale',
            'window_start' => now()->subHours(2),
            'window_end' => now()->subHours(2)->addMinutes(20),
            'status' => 'processing',
            'queued_at' => now()->subHours(2),
            'started_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
            'created_at' => now()->subHours(2),
        ]);

        $guard = app(SchedulerQueueGuard::class);

        $this->assertNull($guard->apifyBusyReason());
    }

    public function test_maximum_cost_per_run_is_sent_to_apify_run_request()
    {
        ApifySetting::create([
            'api_token' => 'test-token',
            'connection_status' => 'connected',
        ]);

        $actor = ApifyActor::create([
            'platform' => 'Facebook',
            'actor_name' => 'Facebook Posts Search Scraper',
            'actor_slug' => 'scrapeflow/facebook-posts-search-scraper',
            'function_type' => 'Search Post',
            'status' => 'active',
            'default_limit' => 50,
            'interval_minutes' => 20,
            'memory_limit' => 2048,
            'range_mode' => '7d',
            'priority' => 1,
            'maximum_cost_per_run_usd' => 0.25,
        ]);

        $project = Project::create([
            'name' => 'Gubernur Kaltim',
            'topics' => ['gubernur kaltim'],
            'is_active' => true,
        ]);

        $runUrl = null;
        Http::fake(function ($request) use (&$runUrl) {
            $url = (string) $request->url();

            if (str_contains($url, '/acts/scrapeflow~facebook-posts-search-scraper/runs')) {
                $runUrl = $url;

                return Http::response([
                    'data' => [
                        'id' => 'run-123',
                        'defaultDatasetId' => 'dataset-123',
                    ],
                ], 201);
            }

            if (str_contains($url, '/actor-runs/run-123')) {
                return Http::response(['data' => ['status' => 'SUCCEEDED']], 200);
            }

            if (str_contains($url, '/datasets/dataset-123/items')) {
                return Http::response([], 200);
            }

            return Http::response([], 200);
        });

        (new ApifyScrapingJob([
            'platform' => 'Facebook',
            'keyword' => 'gubernur kaltim',
            'keywords' => ['gubernur kaltim'],
            'project_id' => $project->id,
            'actor_id' => $actor->id,
            'limit' => 50,
            'no_telegram' => true,
        ]))->handle();

        $this->assertNotNull($runUrl);
        $this->assertStringContainsString('memory=2048', $runUrl);
        $this->assertStringContainsString('maxTotalChargeUsd=0.25', $runUrl);
    }

    public function test_each_social_actor_sends_its_own_maximum_cost_limit()
    {
        ApifySetting::create([
            'api_token' => 'test-token',
            'connection_status' => 'connected',
        ]);

        $actors = [
            ['Facebook', 'scrapeflow/facebook-posts-search-scraper', 0.20, 'scrapeflow~facebook-posts-search-scraper'],
            ['Instagram', 'apify/instagram-hashtag-scraper', 0.15, 'apify~instagram-hashtag-scraper'],
            ['TikTok', 'paul_44/tiktok-search', 0.15, 'paul_44~tiktok-search'],
        ];

        foreach ($actors as [$platform, $slug, $maxCost]) {
            $actor = ApifyActor::create([
                'platform' => $platform,
                'actor_name' => "{$platform} Actor",
                'actor_slug' => $slug,
                'function_type' => 'Search Post',
                'status' => 'active',
                'default_limit' => 50,
                'interval_minutes' => 20,
                'memory_limit' => 2048,
                'range_mode' => '7d',
                'priority' => 1,
                'maximum_cost_per_run_usd' => $maxCost,
            ]);

            $project = Project::create([
                'name' => "{$platform} Project",
                'topics' => ['gubernur kaltim'],
                'is_active' => true,
            ]);

            $runUrl = null;
            Http::fake(function ($request) use (&$runUrl) {
                $url = (string) $request->url();

                if (str_contains($url, '/acts/') && str_contains($url, '/runs')) {
                    $runUrl = $url;

                    return Http::response([
                        'data' => [
                            'id' => 'run-cost-guard',
                            'defaultDatasetId' => 'dataset-cost-guard',
                        ],
                    ], 201);
                }

                if (str_contains($url, '/actor-runs/run-cost-guard')) {
                    return Http::response(['data' => ['status' => 'SUCCEEDED']], 200);
                }

                if (str_contains($url, '/datasets/dataset-cost-guard/items')) {
                    return Http::response([], 200);
                }

                return Http::response([], 200);
            });

            (new ApifyScrapingJob([
                'platform' => $platform,
                'keyword' => 'gubernur kaltim',
                'keywords' => ['gubernur kaltim'],
                'project_id' => $project->id,
                'actor_id' => $actor->id,
                'limit' => 50,
                'no_telegram' => true,
            ]))->handle();

            $this->assertNotNull($runUrl, "Run URL missing for {$platform}");
            $this->assertStringContainsString('maxTotalChargeUsd=' . rtrim(rtrim(number_format($maxCost, 4, '.', ''), '0'), '.'), $runUrl);
        }
    }

    public function test_social_actor_payloads_split_total_limit_across_project_keywords()
    {
        $keywords = ['gubernur kaltim', "rudy mas'ud", 'gubernur kalimantan timur'];

        $facebook = new ApifyActor([
            'platform' => 'Facebook',
            'actor_slug' => 'scrapeflow/facebook-posts-search-scraper',
            'default_limit' => 50,
            'range_mode' => '7d',
        ]);
        $instagram = new ApifyActor([
            'platform' => 'Instagram',
            'actor_slug' => 'apify/instagram-hashtag-scraper',
            'default_limit' => 50,
            'range_mode' => '7d',
        ]);
        $tiktok = new ApifyActor([
            'platform' => 'TikTok',
            'actor_slug' => 'paul_44/tiktok-search',
            'default_limit' => 50,
            'range_mode' => '7d',
        ]);

        $facebookPayload = $facebook->buildInputPayload('gubernur kaltim', 50, null, null, $keywords);
        $instagramPayload = $instagram->buildInputPayload('gubernur kaltim', 50, null, null, $keywords);
        $tiktokPayload = $tiktok->buildInputPayload('gubernur kaltim', 50, null, null, $keywords);

        $this->assertSame($keywords, $facebookPayload['searchQueries']);
        $this->assertSame(17, $facebookPayload['maxPosts']);

        $this->assertSame(['gubernur kaltim', "rudy mas'ud", 'gubernur kalimantan timur'], $instagramPayload['hashtags']);
        $this->assertSame(17, $instagramPayload['resultsLimit']);

        $this->assertSame($keywords, $tiktokPayload['keywords']);
        $this->assertSame(17, $tiktokPayload['maxItems']);
    }

    public function test_facebook_payload_comes_from_actor_database_fields()
    {
        $actor = ApifyActor::create([
            'platform' => 'Facebook',
            'actor_name' => 'Facebook Posts Search Scraper',
            'actor_slug' => 'scrapeflow/facebook-posts-search-scraper',
            'function_type' => 'Search Post',
            'status' => 'active',
            'default_keyword' => 'politik',
            'default_limit' => 42,
            'interval_minutes' => 20,
            'memory_limit' => 1024,
            'range_mode' => '30d',
            'priority' => 1,
            'keyword_field_mapping' => 'searchQueries',
            'output_mapping' => json_encode([
                'maxPosts' => 42,
                'postTimeRange' => '30d',
                'proxyConfiguration' => ['useApifyProxy' => false],
                'searchQueries' => ['{keyword}'],
            ]),
        ]);

        $payload = $actor->buildInputPayload('pilkada', null, null, null, ['pilkada', 'politik']);

        $this->assertSame(['pilkada', 'politik'], $payload['searchQueries']);
        $this->assertSame(42, $payload['maxPosts']);
        $this->assertSame('30d', $payload['postTimeRange']);
        $this->assertFalse($payload['proxyConfiguration']['useApifyProxy']);
    }

    public function test_tiktok_payload_respects_runtime_limit_even_if_actor_config_has_own_max_items()
    {
        $keywords = ['Wakil Gubernur Kalimantan Timur', 'wagub kaltim', 'Seno Aji'];

        $tiktok = new ApifyActor([
            'platform' => 'TikTok',
            'actor_slug' => 'paul_44/tiktok-search',
            'default_limit' => 50,
            'range_mode' => '7d',
            'output_mapping' => json_encode([
                'dateRange' => '7days',
                'maxItems' => 15,
                'keywords' => ['{keyword}'],
            ]),
        ]);

        $payload = $tiktok->buildInputPayload('wagub kaltim', 5, null, null, $keywords);

        $this->assertSame($keywords, $payload['keywords']);
        $this->assertSame(2, $payload['maxItems']);
    }

    public function test_apify_social_search_results_are_trusted_without_caption_keyword_match()
    {
        ApifySetting::create([
            'api_token' => 'test-token',
            'connection_status' => 'connected',
        ]);

        $project = Project::create([
            'name' => 'Wagub Kaltim',
            'topics' => ['wagub kaltim'],
            'is_active' => true,
        ]);

        $cases = [
            [
                'platform' => 'Facebook',
                'slug' => 'scrapeflow/facebook-posts-search-scraper',
                'run_id' => 'run-facebook-trusted',
                'dataset_id' => 'dataset-facebook-trusted',
                'item' => [
                    'url' => 'https://www.facebook.com/example/posts/no-keyword',
                    'pageName' => 'Kaltim Update',
                    'time' => '2026-07-13 10:00:00',
                    'text' => 'Agenda pembangunan daerah dibahas dalam forum publik hari ini.',
                ],
                'post_url' => 'https://www.facebook.com/example/posts/no-keyword',
            ],
            [
                'platform' => 'Instagram',
                'slug' => 'apify/instagram-hashtag-scraper',
                'run_id' => 'run-instagram-trusted',
                'dataset_id' => 'dataset-instagram-trusted',
                'item' => [
                    'url' => 'https://www.instagram.com/p/no-keyword/',
                    'username' => 'kaltim.update',
                    'type' => 'post',
                    'caption' => 'Agenda pembangunan daerah dibahas dalam forum publik hari ini.',
                ],
                'post_url' => 'https://www.instagram.com/p/no-keyword/',
            ],
            [
                'platform' => 'TikTok',
                'slug' => 'paul_44/tiktok-search',
                'run_id' => 'run-tiktok-trusted',
                'dataset_id' => 'dataset-tiktok-trusted',
                'item' => [
                    'webVideoUrl' => 'https://www.tiktok.com/@kaltim/video/no-keyword',
                    'authorName' => 'kaltim.update',
                    'createTimeISO' => '2026-07-13T10:00:00+08:00',
                    'description' => 'Agenda pembangunan daerah dibahas dalam forum publik hari ini.',
                ],
                'post_url' => 'https://www.tiktok.com/@kaltim/video/no-keyword',
            ],
        ];

        foreach ($cases as $index => $case) {
            $actor = ApifyActor::create([
                'platform' => $case['platform'],
                'actor_name' => "{$case['platform']} Search",
                'actor_slug' => $case['slug'],
                'function_type' => 'Search Post',
                'status' => 'active',
                'default_limit' => 50,
                'interval_minutes' => 20,
                'memory_limit' => 2048,
                'range_mode' => '7d',
                'priority' => 1,
            ]);

            $cases[$index]['actor_id'] = $actor->id;
        }

        Http::fake(function ($request) use ($cases) {
            $url = (string) $request->url();

            foreach ($cases as $case) {
                if (str_contains($url, '/acts/' . str_replace('/', '~', $case['slug']) . '/runs')) {
                    return Http::response([
                        'data' => [
                            'id' => $case['run_id'],
                            'defaultDatasetId' => $case['dataset_id'],
                        ],
                    ], 201);
                }

                if (str_contains($url, "/actor-runs/{$case['run_id']}")) {
                    return Http::response(['data' => ['status' => 'SUCCEEDED']], 200);
                }

                if (str_contains($url, "/datasets/{$case['dataset_id']}/items")) {
                    return Http::response([$case['item']], 200);
                }
            }

            return Http::response([], 200);
        });

        foreach ($cases as $case) {
            (new ApifyScrapingJob([
                'platform' => $case['platform'],
                'keyword' => 'wagub kaltim',
                'keywords' => ['wagub kaltim'],
                'project_id' => $project->id,
                'actor_id' => $case['actor_id'],
                'limit' => 50,
                'no_telegram' => true,
            ]))->handle();

            $this->assertDatabaseHas('social_media_items', [
                'platform' => $case['platform'],
                'post_url' => $case['post_url'],
            ]);
        }
    }

    public function test_social_dataset_processing_is_capped_to_requested_limit()
    {
        ApifySetting::create([
            'api_token' => 'test-token',
            'connection_status' => 'connected',
        ]);

        $actor = ApifyActor::create([
            'platform' => 'Facebook',
            'actor_name' => 'Facebook Posts Search Scraper',
            'actor_slug' => 'scrapeflow/facebook-posts-search-scraper',
            'function_type' => 'Search Post',
            'status' => 'active',
            'default_limit' => 3,
            'interval_minutes' => 20,
            'memory_limit' => 2048,
            'range_mode' => '7d',
            'priority' => 1,
        ]);

        $project = Project::create([
            'name' => 'Wagub Kaltim',
            'topics' => ['wagub kaltim'],
            'is_active' => true,
        ]);

        Http::fake(function ($request) {
            $url = (string) $request->url();

            if (str_contains($url, '/acts/scrapeflow~facebook-posts-search-scraper/runs')) {
                return Http::response([
                    'data' => [
                        'id' => 'run-over-limit',
                        'defaultDatasetId' => 'dataset-over-limit',
                    ],
                ], 201);
            }

            if (str_contains($url, '/actor-runs/run-over-limit')) {
                return Http::response(['data' => ['status' => 'SUCCEEDED']], 200);
            }

            if (str_contains($url, '/datasets/dataset-over-limit/items')) {
                return Http::response(collect(range(1, 5))->map(fn (int $number) => [
                    'url' => "https://www.facebook.com/example/posts/{$number}",
                    'pageName' => 'Kaltim Update',
                    'time' => '2026-07-13 10:00:00',
                    'text' => "Wagub Kaltim Seno Aji agenda pembangunan daerah nomor {$number}.",
                    'likes' => 4,
                    'comments' => 1,
                    'shares' => 0,
                ])->all(), 200);
            }

            return Http::response([], 200);
        });

        (new ApifyScrapingJob([
            'platform' => 'Facebook',
            'keyword' => 'wagub kaltim',
            'keywords' => ['wagub kaltim'],
            'project_id' => $project->id,
            'actor_id' => $actor->id,
            'limit' => 3,
            'no_telegram' => true,
        ]))->handle();

        $this->assertSame(3, \App\Models\SocialMediaItem::where('platform', 'Facebook')->count());
    }

    public function test_cost_limit_aborted_run_uses_partial_dataset()
    {
        ApifySetting::create([
            'api_token' => 'test-token',
            'connection_status' => 'connected',
        ]);

        $actor = ApifyActor::create([
            'platform' => 'Facebook',
            'actor_name' => 'Facebook Posts Search Scraper',
            'actor_slug' => 'scrapeflow/facebook-posts-search-scraper',
            'function_type' => 'Search Post',
            'status' => 'active',
            'default_limit' => 50,
            'interval_minutes' => 20,
            'memory_limit' => 2048,
            'range_mode' => '7d',
            'priority' => 1,
            'maximum_cost_per_run_usd' => 0.20,
        ]);

        $project = Project::create([
            'name' => 'Wagub Kaltim',
            'topics' => ['wagub kaltim'],
            'is_active' => true,
        ]);

        Http::fake(function ($request) {
            $url = (string) $request->url();

            if (str_contains($url, '/acts/scrapeflow~facebook-posts-search-scraper/runs')) {
                return Http::response([
                    'data' => [
                        'id' => 'run-cost-cap',
                        'defaultDatasetId' => 'dataset-cost-cap',
                    ],
                ], 201);
            }

            if (str_contains($url, '/actor-runs/run-cost-cap')) {
                return Http::response([
                    'data' => [
                        'status' => 'ABORTED',
                        'statusMessage' => 'This run was aborted because it reached its maximum cost of $0.20.',
                    ],
                ], 200);
            }

            if (str_contains($url, '/datasets/dataset-cost-cap/items')) {
                parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $query);
                if ((int) ($query['offset'] ?? 0) > 0) {
                    return Http::response([], 200);
                }

                return Http::response([
                    [
                        'url' => 'https://www.facebook.com/example/posts/1',
                        'pageName' => 'Kaltim Update',
                        'time' => '2026-07-13 10:00:00',
                        'text' => 'Wagub Kaltim Seno Aji menghadiri agenda pembangunan daerah.',
                        'likes' => 4,
                        'comments' => 1,
                        'shares' => 0,
                    ],
                ], 200);
            }

            return Http::response([], 200);
        });

        (new ApifyScrapingJob([
            'platform' => 'Facebook',
            'keyword' => 'wagub kaltim',
            'keywords' => ['wagub kaltim'],
            'project_id' => $project->id,
            'actor_id' => $actor->id,
            'limit' => 50,
            'no_telegram' => true,
        ]))->handle();

        $this->assertDatabaseHas('social_media_items', [
            'platform' => 'Facebook',
            'post_url' => 'https://www.facebook.com/example/posts/1',
        ]);
        $this->assertEquals('success', $actor->fresh()->last_run_status);
        $this->assertStringContainsString('Batas biaya Apify $0.20 tercapai', $actor->fresh()->last_run_message);
        $this->assertStringContainsString('data yang sudah terkumpul tetap disimpan', $actor->fresh()->last_run_message);
    }

    public function test_failed_actor_run_keeps_apify_status_message()
    {
        ApifySetting::create([
            'api_token' => 'test-token',
            'connection_status' => 'connected',
        ]);

        $actor = ApifyActor::create([
            'platform' => 'Facebook',
            'actor_name' => 'Facebook Posts Search Scraper',
            'actor_slug' => 'scrapeflow/facebook-posts-search-scraper',
            'function_type' => 'Search Post',
            'status' => 'active',
            'default_limit' => 50,
            'interval_minutes' => 20,
            'memory_limit' => 2048,
            'range_mode' => '7d',
            'priority' => 1,
            'maximum_cost_per_run_usd' => 0.20,
        ]);

        $project = Project::create([
            'name' => 'Wagub Kaltim',
            'topics' => ['wagub kaltim'],
            'is_active' => true,
        ]);

        Http::fake(function ($request) {
            $url = (string) $request->url();

            if (str_contains($url, '/acts/scrapeflow~facebook-posts-search-scraper/runs')) {
                return Http::response([
                    'data' => [
                        'id' => 'run-cost-cap-empty',
                        'defaultDatasetId' => 'dataset-cost-cap-empty',
                    ],
                ], 201);
            }

            if (str_contains($url, '/actor-runs/run-cost-cap-empty')) {
                return Http::response([
                    'data' => [
                        'status' => 'ABORTED',
                        'statusMessage' => 'This run was aborted because it reached its maximum cost of $0.20.',
                    ],
                ], 200);
            }

            if (str_contains($url, '/datasets/dataset-cost-cap-empty/items')) {
                return Http::response([], 200);
            }

            return Http::response([], 200);
        });

        (new ApifyScrapingJob([
            'platform' => 'Facebook',
            'keyword' => 'wagub kaltim',
            'keywords' => ['wagub kaltim'],
            'project_id' => $project->id,
            'actor_id' => $actor->id,
            'limit' => 50,
            'no_telegram' => true,
        ]))->handle();

        $this->assertEquals('failed', $actor->fresh()->last_run_status);
        $this->assertStringContainsString('maximum cost of $0.20', $actor->fresh()->last_run_message);
    }

    public function test_cost_limit_aborted_run_without_message_uses_usage_total()
    {
        ApifySetting::create([
            'api_token' => 'test-token',
            'connection_status' => 'connected',
        ]);

        $actor = ApifyActor::create([
            'platform' => 'Facebook',
            'actor_name' => 'Facebook Posts Search Scraper',
            'actor_slug' => 'scrapeflow/facebook-posts-search-scraper',
            'function_type' => 'Search Post',
            'status' => 'active',
            'default_limit' => 50,
            'interval_minutes' => 20,
            'memory_limit' => 2048,
            'range_mode' => '7d',
            'priority' => 1,
            'maximum_cost_per_run_usd' => 0.20,
        ]);

        $project = Project::create([
            'name' => 'Wagub Kaltim',
            'topics' => ['wagub kaltim'],
            'is_active' => true,
        ]);

        Http::fake(function ($request) {
            $url = (string) $request->url();

            if (str_contains($url, '/acts/scrapeflow~facebook-posts-search-scraper/runs')) {
                return Http::response([
                    'data' => [
                        'id' => 'run-cost-cap-no-message',
                        'defaultDatasetId' => 'dataset-cost-cap-no-message',
                    ],
                ], 201);
            }

            if (str_contains($url, '/actor-runs/run-cost-cap-no-message')) {
                return Http::response([
                    'data' => [
                        'status' => 'ABORTED',
                        'statusMessage' => null,
                        'usageTotalUsd' => 0.201,
                    ],
                ], 200);
            }

            if (str_contains($url, '/datasets/dataset-cost-cap-no-message/items')) {
                parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $query);
                if ((int) ($query['offset'] ?? 0) > 0) {
                    return Http::response([], 200);
                }

                return Http::response([
                    [
                        'url' => 'https://www.facebook.com/example/posts/2',
                        'pageName' => 'Kaltim Update',
                        'time' => '2026-07-13 11:00:00',
                        'text' => 'Wagub Kaltim Seno Aji membahas pembangunan daerah.',
                        'likes' => 5,
                        'comments' => 1,
                        'shares' => 0,
                    ],
                ], 200);
            }

            return Http::response([], 200);
        });

        (new ApifyScrapingJob([
            'platform' => 'Facebook',
            'keyword' => 'wagub kaltim',
            'keywords' => ['wagub kaltim'],
            'project_id' => $project->id,
            'actor_id' => $actor->id,
            'limit' => 50,
            'no_telegram' => true,
        ]))->handle();

        $this->assertDatabaseHas('social_media_items', [
            'platform' => 'Facebook',
            'post_url' => 'https://www.facebook.com/example/posts/2',
        ]);
        $this->assertEquals('success', $actor->fresh()->last_run_status);
        $this->assertStringContainsString('Batas biaya Apify $0.2 tercapai', $actor->fresh()->last_run_message);
    }

    public function test_poll_timeout_aborts_and_uses_partial_dataset()
    {
        ApifySetting::create([
            'api_token' => 'test-token',
            'connection_status' => 'connected',
        ]);

        $actor = ApifyActor::create([
            'platform' => 'Facebook',
            'actor_name' => 'Facebook Posts Search Scraper',
            'actor_slug' => 'scrapeflow/facebook-posts-search-scraper',
            'function_type' => 'Search Post',
            'status' => 'active',
            'default_limit' => 50,
            'interval_minutes' => 20,
            'memory_limit' => 2048,
            'range_mode' => '7d',
            'priority' => 1,
            'maximum_cost_per_run_usd' => 0.20,
        ]);

        $project = Project::create([
            'name' => 'Wagub Kaltim',
            'topics' => ['wagub kaltim'],
            'is_active' => true,
        ]);

        $state = ApifyDispatchState::create([
            'dispatch_key' => 'timeout-partial-state',
            'project_id' => $project->id,
            'actor_id' => $actor->id,
            'platform' => 'Facebook',
            'keyword' => 'wagub kaltim',
            'normalized_keyword' => 'wagub kaltim',
            'window_start' => now(),
            'window_end' => now()->addMinutes(20),
            'status' => 'queued',
            'queued_at' => now(),
        ]);

        Http::fake(function ($request) {
            $url = (string) $request->url();
            $method = $request->method();

            if (str_contains($url, '/acts/scrapeflow~facebook-posts-search-scraper/runs')) {
                return Http::response([
                    'data' => [
                        'id' => 'run-timeout-partial',
                        'defaultDatasetId' => 'dataset-timeout-partial',
                    ],
                ], 201);
            }

            if ($method === 'POST' && str_contains($url, '/actor-runs/run-timeout-partial/abort')) {
                return Http::response(['data' => ['status' => 'ABORTING']], 200);
            }

            if (str_contains($url, '/actor-runs/run-timeout-partial')) {
                return Http::response(['data' => ['status' => 'RUNNING']], 200);
            }

            if (str_contains($url, '/datasets/dataset-timeout-partial/items')) {
                parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $query);
                if ((int) ($query['offset'] ?? 0) > 0) {
                    return Http::response([], 200);
                }

                return Http::response([
                    [
                        'url' => 'https://www.facebook.com/example/posts/timeout',
                        'pageName' => 'Kaltim Update',
                        'time' => '2026-07-13 12:00:00',
                        'text' => 'Wagub Kaltim Seno Aji membahas layanan publik.',
                        'likes' => 2,
                        'comments' => 0,
                        'shares' => 0,
                    ],
                ], 200);
            }

            return Http::response([], 200);
        });

        (new ApifyScrapingJob([
            'platform' => 'Facebook',
            'keyword' => 'wagub kaltim',
            'keywords' => ['wagub kaltim'],
            'project_id' => $project->id,
            'actor_id' => $actor->id,
            'dispatch_state_id' => $state->id,
            'limit' => 50,
            'no_telegram' => true,
            'poll_timeout_seconds' => 1,
            'poll_sleep_seconds' => 1,
        ]))->handle();

        $this->assertDatabaseHas('social_media_items', [
            'platform' => 'Facebook',
            'post_url' => 'https://www.facebook.com/example/posts/timeout',
        ]);
        $this->assertEquals('success', $actor->fresh()->last_run_status);
        $this->assertEquals('success', $state->fresh()->status);
        $this->assertStringContainsString('15 menit', (string) $state->fresh()->last_error_message);
    }

    public function test_poll_timeout_without_dataset_enters_retry_wait()
    {
        ApifySetting::create([
            'api_token' => 'test-token',
            'connection_status' => 'connected',
        ]);

        $actor = ApifyActor::create([
            'platform' => 'Facebook',
            'actor_name' => 'Facebook Posts Search Scraper',
            'actor_slug' => 'scrapeflow/facebook-posts-search-scraper',
            'function_type' => 'Search Post',
            'status' => 'active',
            'default_limit' => 50,
            'interval_minutes' => 5,
            'memory_limit' => 2048,
            'range_mode' => '7d',
            'priority' => 1,
            'maximum_cost_per_run_usd' => 0.20,
        ]);

        $project = Project::create([
            'name' => 'Wagub Kaltim',
            'topics' => ['wagub kaltim'],
            'is_active' => true,
        ]);

        $state = ApifyDispatchState::create([
            'dispatch_key' => 'timeout-empty-state',
            'project_id' => $project->id,
            'actor_id' => $actor->id,
            'platform' => 'Facebook',
            'keyword' => 'wagub kaltim',
            'normalized_keyword' => 'wagub kaltim',
            'window_start' => now(),
            'window_end' => now()->addMinutes(5),
            'status' => 'queued',
            'queued_at' => now(),
        ]);

        Http::fake(function ($request) {
            $url = (string) $request->url();
            $method = $request->method();

            if (str_contains($url, '/acts/scrapeflow~facebook-posts-search-scraper/runs')) {
                return Http::response([
                    'data' => [
                        'id' => 'run-timeout-empty',
                        'defaultDatasetId' => 'dataset-timeout-empty',
                    ],
                ], 201);
            }

            if ($method === 'POST' && str_contains($url, '/actor-runs/run-timeout-empty/abort')) {
                return Http::response(['data' => ['status' => 'ABORTING']], 200);
            }

            if (str_contains($url, '/actor-runs/run-timeout-empty')) {
                return Http::response(['data' => ['status' => 'RUNNING']], 200);
            }

            if (str_contains($url, '/datasets/dataset-timeout-empty/items')) {
                return Http::response([], 200);
            }

            return Http::response([], 200);
        });

        (new ApifyScrapingJob([
            'platform' => 'Facebook',
            'keyword' => 'wagub kaltim',
            'keywords' => ['wagub kaltim'],
            'project_id' => $project->id,
            'actor_id' => $actor->id,
            'dispatch_state_id' => $state->id,
            'limit' => 50,
            'no_telegram' => true,
            'poll_timeout_seconds' => 1,
            'poll_sleep_seconds' => 1,
        ]))->handle();

        $this->assertEquals('retry_wait', $actor->fresh()->last_run_status);
        $this->assertEquals('retry_wait', $state->fresh()->status);
        $this->assertNotNull($state->fresh()->next_retry_at);
        $this->assertStringContainsString('Dataset masih kosong', (string) $state->fresh()->last_error_message);
    }

    public function test_tiktok_hard_limit_waits_for_natural_finish_without_abort()
    {
        ApifySetting::create([
            'api_token' => 'test-token',
            'connection_status' => 'connected',
        ]);

        $actor = ApifyActor::create([
            'platform' => 'TikTok',
            'actor_name' => 'TikTok Keyword Search',
            'actor_slug' => 'paul_44/tiktok-search',
            'function_type' => 'Search Post',
            'status' => 'active',
            'default_limit' => 50,
            'interval_minutes' => 60,
            'memory_limit' => 2048,
            'range_mode' => '7d',
            'priority' => 3,
            'maximum_cost_per_run_usd' => 1.00,
        ]);

        $project = Project::create([
            'name' => 'Wagub Kaltim',
            'topics' => ['Wakil Gubernur Kalimantan Timur', 'wagub kaltim', 'Seno Aji'],
            'is_active' => true,
        ]);

        $abortCalled = false;
        Http::fake(function ($request) use (&$abortCalled) {
            $url = (string) $request->url();
            $method = $request->method();

            if (str_contains($url, '/acts/paul_44~tiktok-search/runs')) {
                return Http::response([
                    'data' => [
                        'id' => 'run-tiktok-natural',
                        'defaultDatasetId' => 'dataset-tiktok-natural',
                    ],
                ], 201);
            }

            if ($method === 'POST' && str_contains($url, '/actor-runs/run-tiktok-natural/abort')) {
                $abortCalled = true;
                return Http::response(['data' => ['status' => 'ABORTING']], 200);
            }

            if (str_contains($url, '/actor-runs/run-tiktok-natural')) {
                return Http::response([
                    'data' => [
                        'status' => 'SUCCEEDED',
                        'statusMessage' => 'Finished normally',
                    ],
                ], 200);
            }

            if (str_contains($url, '/datasets/dataset-tiktok-natural/items')) {
                parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $query);

                if ((int) ($query['offset'] ?? 0) > 0) {
                    return Http::response([
                        ['url' => 'https://www.tiktok.com/@demo/video/offset-hit'],
                    ], 200);
                }

                return Http::response([
                    [
                        'url' => 'https://www.tiktok.com/@demo/video/1',
                        'description' => 'Wakil Gubernur Kalimantan Timur hadir dalam agenda daerah.',
                        'authorName' => 'Demo Author',
                        'uploadedAt' => 1784000000,
                        'diggCount' => 10,
                        'commentCount' => 2,
                        'shareCount' => 1,
                        'playCount' => 100,
                    ],
                    [
                        'url' => 'https://www.tiktok.com/@demo/video/2',
                        'description' => 'Seno Aji membahas pembangunan wilayah.',
                        'authorName' => 'Demo Author',
                        'uploadedAt' => 1784000001,
                        'diggCount' => 11,
                        'commentCount' => 3,
                        'shareCount' => 1,
                        'playCount' => 101,
                    ],
                ], 200);
            }

            return Http::response([], 200);
        });

        (new ApifyScrapingJob([
            'platform' => 'TikTok',
            'keyword' => 'Wakil Gubernur Kalimantan Timur',
            'keywords' => ['Wakil Gubernur Kalimantan Timur', 'wagub kaltim', 'Seno Aji'],
            'project_id' => $project->id,
            'actor_id' => $actor->id,
            'limit' => 1,
            'no_telegram' => true,
        ]))->handle();

        $this->assertFalse($abortCalled, 'Social actor should not be aborted after hitting local hard limit.');
        $this->assertEquals('success', $actor->fresh()->last_run_status);
        $this->assertStringContainsString('done at 1 items', (string) $actor->fresh()->last_run_message);
        $this->assertDatabaseHas('social_media_items', [
            'platform' => 'TikTok',
            'post_url' => 'https://www.tiktok.com/@demo/video/1',
        ]);
    }

    public function test_facebook_only_saves_items_that_match_project_keywords(): void
    {
        Queue::fake();

        ApifySetting::create([
            'api_token' => 'test-token',
            'connection_status' => 'connected',
        ]);

        $actor = ApifyActor::create([
            'platform' => 'Facebook',
            'actor_name' => 'Facebook Posts Search Scraper',
            'actor_slug' => 'scrapeflow/facebook-posts-search-scraper',
            'function_type' => 'Search Post',
            'status' => 'active',
            'default_limit' => 5,
            'interval_minutes' => 10,
            'memory_limit' => 2048,
            'range_mode' => '7d',
            'priority' => 1,
            'maximum_cost_per_run_usd' => 0.20,
        ]);

        $project = Project::create([
            'name' => 'Walikota Samarinda',
            'topics' => ['Andi Harun', 'Walikota Samarinda'],
            'is_active' => true,
        ]);

        Http::fake(function ($request) {
            $url = (string) $request->url();

            if (str_contains($url, '/acts/scrapeflow~facebook-posts-search-scraper/runs')) {
                return Http::response([
                    'data' => [
                        'id' => 'run-keyword-filter',
                        'defaultDatasetId' => 'dataset-keyword-filter',
                    ],
                ], 201);
            }

            if (str_contains($url, '/actor-runs/run-keyword-filter')) {
                return Http::response([
                    'data' => [
                        'status' => 'SUCCEEDED',
                    ],
                ], 200);
            }

            if (str_contains($url, '/datasets/dataset-keyword-filter/items')) {
                return Http::response([
                    [
                        'url' => 'https://facebook.com/example/noise',
                        'text' => 'Ray-Ban Meta',
                        'pageName' => 'Meta Store',
                    ],
                    [
                        'url' => 'https://facebook.com/example/relevant',
                        'text' => 'Selamat ulang tahun Wali Kota Samarinda Andi Harun.',
                        'pageName' => 'Info Samarinda',
                    ],
                ], 200);
            }

            return Http::response([], 200);
        });

        (new ApifyScrapingJob([
            'platform' => 'Facebook',
            'keyword' => 'Andi Harun',
            'keywords' => ['Andi Harun', 'Walikota Samarinda'],
            'project_id' => $project->id,
            'actor_id' => $actor->id,
            'limit' => 5,
            'no_telegram' => true,
            'poll_sleep_seconds' => 1,
            'poll_timeout_seconds' => 3,
        ]))->handle();

        $this->assertSame(1, SocialMediaItem::query()->where('project_id', $project->id)->where('platform', 'Facebook')->count());
        $this->assertDatabaseMissing('social_media_items', [
            'project_id' => $project->id,
            'post_url' => 'https://facebook.com/example/noise',
        ]);
        $this->assertDatabaseHas('social_media_items', [
            'project_id' => $project->id,
            'post_url' => 'https://facebook.com/example/relevant',
        ]);
    }
}
