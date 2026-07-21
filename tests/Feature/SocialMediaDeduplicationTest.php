<?php

namespace Tests\Feature;

use App\Jobs\ApifyScrapingJob;
use App\Models\ApifyActor;
use App\Models\ApifySetting;
use App\Models\Project;
use App\Models\SocialMediaItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialMediaDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_post_url_is_normalized_before_upsert(): void
    {
        ApifySetting::create([
            'api_token' => 'test-token',
            'connection_status' => 'connected',
        ]);

        $actor = ApifyActor::create([
            'platform' => 'Instagram',
            'actor_name' => 'Instagram Hashtag Scraper',
            'actor_slug' => 'apify/instagram-hashtag-scraper',
            'function_type' => 'Search Post',
            'status' => 'active',
            'default_limit' => 50,
            'interval_minutes' => 20,
            'memory_limit' => 1024,
            'range_mode' => '7d',
            'priority' => 1,
        ]);

        $project = Project::create([
            'name' => 'Dedup Project',
            'topics' => ['dedup'],
            'is_active' => true,
        ]);

        Http::fake(function ($request) {
            $url = (string) $request->url();

            if (str_contains($url, '/runs')) {
                return Http::response([
                    'data' => [
                        'id' => 'run-1',
                        'defaultDatasetId' => 'dataset-1',
                    ],
                ], 201);
            }

            if (str_contains($url, '/actor-runs/run-1')) {
                return Http::response(['data' => ['status' => 'SUCCEEDED']], 200);
            }

            if (str_contains($url, '/datasets/dataset-1/items')) {
                return Http::response([
                    [
                        'url' => 'https://www.instagram.com/p/ABC123/?utm_source=test',
                        'caption' => 'Caption one',
                        'authorMeta' => ['name' => 'Tester'],
                        'timestamp' => now()->timestamp,
                    ],
                    [
                        'url' => 'https://www.instagram.com/p/ABC123/',
                        'caption' => 'Caption one updated',
                        'authorMeta' => ['name' => 'Tester'],
                        'timestamp' => now()->timestamp,
                    ],
                ], 200);
            }

            return Http::response([], 200);
        });

        (new ApifyScrapingJob([
            'platform' => 'Instagram',
            'keyword' => 'dedup',
            'keywords' => ['dedup'],
            'project_id' => $project->id,
            'actor_id' => $actor->id,
            'limit' => 50,
            'no_telegram' => true,
        ]))->handle();

        $this->assertSame(1, SocialMediaItem::count());
        $item = SocialMediaItem::first();
        $this->assertSame('https://www.instagram.com/p/ABC123', $item->post_url);
        $this->assertSame('Caption one updated', $item->content);
    }
}
