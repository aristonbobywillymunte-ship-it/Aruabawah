<?php

namespace Tests\Feature;

use App\Console\Commands\RunApifyScraping;
use App\Jobs\AiAnalysisJob;
use App\Jobs\ApifyScrapingJob;
use App\Jobs\TelegramNotificationJob;
use App\Models\AiPromptTemplate;
use App\Models\AiProvider;
use App\Models\ApifyActor;
use App\Models\ApifySetting;
use App\Models\Article;
use App\Models\Project;
use App\Models\SocialMediaItem;
use App\Models\TelegramSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SocialMediaPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup base settings
        ApifySetting::create([
            'api_token' => 'apify_api_testtoken123456',
            'connection_status' => 'connected',
        ]);

        ApifyActor::create([
            'platform' => 'Instagram',
            'actor_name' => 'Instagram Hashtag Scraper',
            'actor_slug' => 'apify/instagram-hashtag-scraper',
            'status' => 'active',
            'priority' => 1,
            'default_limit' => 2,
            'interval_minutes' => 120,
            'function_type' => 'Search Post',
        ]);

        AiProvider::create([
            'name' => 'Gemini Test',
            'provider_type' => 'Gemini',
            'model_name' => 'gemini-2.0-flash',
            'api_key' => 'key123',
            'is_active' => true,
            'is_default' => true,
        ]);

        AiPromptTemplate::create([
            'name' => 'Analisis Berita Utama',
            'source_type' => 'article',
            'system_prompt' => 'Article System Prompt',
            'user_prompt_template' => 'Article User Prompt {content}',
            'is_default' => true,
            'is_active' => true,
        ]);

        AiPromptTemplate::create([
            'name' => 'Analisis Medsos Utama',
            'source_type' => 'social',
            'system_prompt' => 'Social System Prompt',
            'user_prompt_template' => 'Social User Prompt {content}',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    public function test_project_inactive_does_not_dispatch_apify()
    {
        Queue::fake();

        Project::create([
            'name' => 'Inactive Project',
            'topics' => ['kaltim'],
            'is_active' => false,
        ]);

        $this->artisan('scraping:run-apify')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_empty_topics_does_not_dispatch_apify()
    {
        Queue::fake();

        Project::create([
            'name' => 'Empty Topic Project',
            'topics' => [],
            'is_active' => true,
        ]);

        $this->artisan('scraping:run-apify')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_duplicate_actor_keyword_does_not_create_duplicate_jobs()
    {
        // Use database queue connection to test raw DB jobs table constraints
        config(['queue.default' => 'database']);

        $project = Project::create([
            'name' => 'Active Project',
            'topics' => ['kaltim'],
            'is_active' => true,
        ]);

        // First dispatch should succeed and write to 'jobs' table
        $res1 = ApifyScrapingJob::dispatchSafely([
            'platform' => 'Instagram',
            'keyword' => 'kaltim',
            'project_id' => $project->id,
            'actor_id' => 1
        ]);
        $this->assertTrue($res1);

        // Second dispatch should detect the existing database job and skip
        $res2 = ApifyScrapingJob::dispatchSafely([
            'platform' => 'Instagram',
            'keyword' => 'kaltim',
            'project_id' => $project->id,
            'actor_id' => 1
        ]);
        $this->assertFalse($res2);
    }

    public function test_social_item_duplicate_is_not_stored_twice()
    {
        $postUrl = 'https://instagram.com/p/123456';

        $item1 = SocialMediaItem::updateOrCreate(
            ['post_url' => $postUrl],
            [
                'platform' => 'Instagram',
                'author_name' => 'Author A',
                'content' => 'Content text',
                'posted_at' => now(),
            ]
        );

        $item2 = SocialMediaItem::updateOrCreate(
            ['post_url' => $postUrl],
            [
                'platform' => 'Instagram',
                'author_name' => 'Author A Updated',
                'content' => 'Content text',
                'posted_at' => now(),
            ]
        );

        $this->assertEquals($item1->id, $item2->id);
        $this->assertEquals(1, SocialMediaItem::where('post_url', $postUrl)->count());
    }

    public function test_social_media_relevance_matching_rules()
    {
        $job = new ApifyScrapingJob([]);

        $reflector = new \ReflectionClass(ApifyScrapingJob::class);
        $method = $reflector->getMethod('matchesKeywordInContent');
        $method->setAccessible(true);

        // Exact match
        $this->assertTrue($method->invoke($job, 'adi', 'Rapat dipimpin oleh Adi hari ini.'));
        
        // Exact match with case insensitivity
        $this->assertTrue($method->invoke($job, 'seno aji', 'Berita Wagub Kaltim Seno Aji meninjau abrasi.'));

        // Substring boundary checks: "adi" should NOT match "adil" or "keadilan"
        $this->assertFalse($method->invoke($job, 'adi', 'Rakyat mendambakan keadilan sosial.'));
        $this->assertFalse($method->invoke($job, 'adi', 'Sikap adil sangat penting.'));

        // "kaltim" should NOT match "kaltimpost"
        $this->assertFalse($method->invoke($job, 'kaltim', 'Silakan baca di kaltimpost hari ini.'));
    }

    public function test_social_ai_uses_social_template()
    {
        Queue::fake();
        Http::fake([
            'https://api.apify.com/v2/acts/*' => Http::response(['data' => ['id' => 'run123', 'defaultDatasetId' => 'ds123']], 200),
            'https://api.apify.com/v2/actor-runs/*' => Http::response(['data' => ['status' => 'SUCCEEDED']], 200),
            'https://api.apify.com/v2/datasets/ds123/items*' => Http::response([
                [
                    'url' => 'https://instagram.com/p/987',
                    'text' => 'Pembangunan di Kaltim berjalan lancar.',
                    'username' => 'kaltim_user',
                ]
            ], 200)
        ]);

        $project = Project::create([
            'name' => 'Project Kaltim',
            'topics' => ['kaltim'],
            'is_active' => true,
        ]);

        $job = new ApifyScrapingJob([
            'platform' => 'Instagram',
            'keyword' => 'kaltim',
            'project_id' => $project->id,
            'limit' => 1,
        ]);
        $job->handle();

        $socialTemplate = AiPromptTemplate::where('source_type', 'social')->where('is_default', true)->first();

        // Verify that the AiAnalysisJob was pushed to queue with type => 'social'
        Queue::assertPushed(AiAnalysisJob::class, function ($job) use ($socialTemplate) {
            return $job->payload['type'] === 'social' && $job->payload['prompt_template_id'] === $socialTemplate->id;
        });
    }

    public function test_low_and_medium_risk_does_not_send_notification()
    {
        Queue::fake();

        $project = Project::create([
            'name' => 'Active Project',
            'topics' => ['kaltim'],
            'is_active' => true,
        ]);

        $article = Article::create([
            'url' => 'https://example.com/art1',
            'canonical_url' => 'https://example.com/art1',
            'title' => 'Article title',
            'content' => 'Short content but we pass directly to test',
            'source_name' => 'Instagram',
        ]);

        $job = new class extends AiAnalysisJob {
            public function __construct() { parent::__construct([]); }
            public function simulateNotification(array $normalized, int $projectId, int $articleId)
            {
                $normalized['article_id'] = $articleId;
                $normalized['social_media_item_id'] = null;
                $analysisId = $this->persistAnalysis($normalized);

                $canonicalReachLevel = strtolower((string) ($normalized['potential_reach_level'] ?? $normalized['reach_level'] ?? ''));
                $shouldNotify = ($normalized['analysis_status'] ?? 'success') === 'success'
                    && (
                        ($normalized['risk_level'] === 'high' || $normalized['risk_level'] === 'critical')
                        || ($normalized['risk_level'] === 'medium' && in_array($canonicalReachLevel, ['tinggi', 'sangat tinggi', 'high'], true))
                    );

                if ($shouldNotify) {
                    $shouldDispatchNotification = $this->upsertRiskNotification($analysisId);
                    if ($shouldDispatchNotification) {
                        TelegramNotificationJob::dispatch(['ai_analysis_result_id' => $analysisId]);
                    }
                }
            }
        };

        // Low risk -> no notify
        $job->simulateNotification([
            'analysis_status' => 'success',
            'summary' => 'Low risk summary',
            'risk_level' => 'low',
            'reach_level' => 'low',
            'potential_reach_level' => 'low',
            'sentiment' => 'neutral',
            'sentiment_score' => 0.0,
            'main_issue' => 'Umum',
            'entities' => '{}',
            'risk_reason' => '',
            'potential_estimated_readers' => 50,
            'potential_reach_score' => 3,
            'potential_reach_band' => '41-70 pembaca',
            'project_estimated_readers' => 20,
            'project_reach_score' => 1,
            'project_reach_level' => 'Sangat rendah',
            'project_reach_band' => '1-20 pembaca',
            'local_relevance_score' => 5,
            'confidence_score' => 60,
            'confidence_level' => 'Medium',
            'signals_used' => '[]',
            'reasoning_summary' => '',
            'limitations' => '',
            'is_exact_reach' => false,
            'reach_method' => 'ai_reader_estimate_v1',
            'recommendation' => 'None',
            'reach_trend' => 'stable',
            'reach_source' => 'unknown',
            'reach_confidence' => 'low',
            'reach_reason' => 'Legacy field',
            'raw_response' => '{}',
        ], $project->id, $article->id);

        Queue::assertNothingPushed();
    }

    public function test_high_risk_sends_notification_and_deduplicates()
    {
        Queue::fake();

        $project = Project::create([
            'name' => 'Active Project',
            'topics' => ['kaltim'],
            'is_active' => true,
        ]);

        $article = Article::create([
            'url' => 'https://example.com/art2',
            'canonical_url' => 'https://example.com/art2',
            'title' => 'Article title 2',
            'content' => 'Short content but we pass directly to test 2',
            'source_name' => 'Instagram',
        ]);

        // Create telegram settings (valid credentials)
        TelegramSetting::create([
            'bot_token' => '5432109876:XYZdefGhIJKlmNoPQRsTUVwxyZ',
            'default_chat_id' => '-100987654321',
            'is_active' => true,
        ]);

        $job = new class extends AiAnalysisJob {
            public function __construct() { parent::__construct([]); }
            public function simulateNotification(array $normalized, int $projectId, int $articleId)
            {
                $normalized['article_id'] = $articleId;
                $normalized['social_media_item_id'] = null;
                $analysisId = $this->persistAnalysis($normalized);

                $canonicalReachLevel = strtolower((string) ($normalized['potential_reach_level'] ?? $normalized['reach_level'] ?? ''));
                $shouldNotify = ($normalized['analysis_status'] ?? 'success') === 'success'
                    && (
                        ($normalized['risk_level'] === 'high' || $normalized['risk_level'] === 'critical')
                        || ($normalized['risk_level'] === 'medium' && in_array($canonicalReachLevel, ['tinggi', 'sangat tinggi', 'high'], true))
                    );

                if ($shouldNotify) {
                    $shouldDispatchNotification = $this->upsertRiskNotification($analysisId);
                    if ($shouldDispatchNotification) {
                        TelegramNotificationJob::dispatch(['ai_analysis_result_id' => $analysisId]);
                    }
                }
            }
        };

        $payload = [
            'analysis_status' => 'success',
            'summary' => 'High risk summary',
            'risk_level' => 'high',
            'reach_level' => 'high',
            'potential_reach_level' => 'high',
            'sentiment' => 'negative',
            'sentiment_score' => -0.8,
            'main_issue' => 'Krisis',
            'entities' => '{}',
            'risk_reason' => 'Severe threat',
            'potential_estimated_readers' => 500,
            'potential_reach_score' => 8,
            'potential_reach_band' => '351-600 pembaca',
            'project_estimated_readers' => 120,
            'project_reach_score' => 5,
            'project_reach_level' => 'Sedang',
            'project_reach_band' => '101-150 pembaca',
            'local_relevance_score' => 8,
            'confidence_score' => 70,
            'confidence_level' => 'Medium',
            'signals_used' => '[]',
            'reasoning_summary' => '',
            'limitations' => '',
            'is_exact_reach' => false,
            'reach_method' => 'ai_reader_estimate_v1',
            'recommendation' => 'None',
            'reach_trend' => 'stable',
            'reach_source' => 'unknown',
            'reach_confidence' => 'low',
            'reach_reason' => 'Legacy field',
            'raw_response' => '{}',
        ];

        // Send first notification
        $job->simulateNotification($payload, $project->id, $article->id);
        Queue::assertPushed(TelegramNotificationJob::class, 1);

        // Send second notification (duplicate) -> should be skipped by upsertRiskNotification
        $job->simulateNotification($payload, $project->id, $article->id);
        Queue::assertPushed(TelegramNotificationJob::class, 1); // Still 1 push total
    }

    public function test_apify_rolling_date_ranges()
    {
        config(['app.timezone' => 'Asia/Makassar']);
        date_default_timezone_set('Asia/Makassar');

        // Create Apify Actor with 7d range mode and Facebook payload template
        $actor = ApifyActor::create([
            'platform' => 'Facebook',
            'actor_name' => 'Facebook Search Posts',
            'actor_slug' => 'scrapeforge/facebook-search-posts',
            'status' => 'active',
            'priority' => 1,
            'default_limit' => 20,
            'range_mode' => '7d',
            'function_type' => 'Search Post',
            'output_mapping' => json_encode([
                'query' => '{keyword}',
                'maxPosts' => '{limit}',
                'start_date' => '{date_from}',
                'end_date' => '{date_to}',
            ]),
        ]);

        // Simulated date 1: 2026-07-09
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-07-09 08:00:00', 'Asia/Makassar'));
        $payload1 = $actor->buildInputPayload('samarinda', 10);
        $this->assertEquals('7d', $payload1['postTimeRange']);
        $this->assertArrayNotHasKey('start_date', $payload1);
        $this->assertArrayNotHasKey('end_date', $payload1);

        // Simulated date 2: 2026-07-10
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-07-10 08:00:00', 'Asia/Makassar'));
        $payload2 = $actor->buildInputPayload('samarinda', 10);
        $this->assertEquals('7d', $payload2['postTimeRange']);
        $this->assertArrayNotHasKey('start_date', $payload2);
        $this->assertArrayNotHasKey('end_date', $payload2);

        // Simulated date 3: 2026-07-11
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-07-11 08:00:00', 'Asia/Makassar'));
        $payload3 = $actor->buildInputPayload('samarinda', 10);
        $this->assertEquals('7d', $payload3['postTimeRange']);
        $this->assertArrayNotHasKey('start_date', $payload3);
        $this->assertArrayNotHasKey('end_date', $payload3);

        \Carbon\Carbon::setTestNow(); // Reset time travel
    }

    public function test_apify_memory_limit_constraints()
    {
        // 1. Model safety rule auto-normalization
        $actor = ApifyActor::create([
            'platform' => 'Facebook',
            'actor_name' => 'Facebook Search Posts',
            'actor_slug' => 'scrapeforge/facebook-search-posts',
            'status' => 'active',
            'priority' => 1,
            'default_limit' => 20,
            'range_mode' => '7d',
            'function_type' => 'Search Post',
            'memory_limit' => 512, // saved under 1024
            'output_mapping' => json_encode([
                'query' => '{keyword}',
                'maxPosts' => '{limit}',
            ]),
        ]);

        $this->assertEquals(1024, $actor->fresh()->memory_limit); // auto-normalized to 1024
        $this->assertEquals(20, $actor->fresh()->default_limit); // social actor limit now preserves saved value within 1..50

        // 2. RAM is not inside input payload
        $payload = $actor->buildInputPayload('samarinda', 50);
        $this->assertArrayNotHasKey('memory_limit', $payload);
        $this->assertArrayNotHasKey('memory', $payload);
        $this->assertEquals(50, $payload['maxPosts']); // Limit from override/param

        // 2b. Fallback and dynamic DB limits
        $payloadNoLimit = $actor->buildInputPayload('samarinda', null);
        $this->assertEquals(20, $payloadNoLimit['maxPosts']); // Fallback to saved actor limit

        // 2c. Custom DB limit (e.g. 100)
        $actor->default_limit = 100;
        $actor->save();
        $payloadCustomLimit = $actor->buildInputPayload('samarinda', null);
        $this->assertEquals(50, $payloadCustomLimit['maxPosts']); // Social actor limit is capped at 50 total.

        // 3. UI Validation
        $component = \Livewire\Livewire::actingAs(\App\Models\User::factory()->create(['role' => 'admin']))
            ->test(\App\Livewire\Admin\ApifyConfiguration::class)
            ->set('platform', 'Facebook')
            ->set('actorName', 'Facebook Search Posts')
            ->set('actorSlug', 'scrapeflow/facebook-posts-search-scraper')
            ->set('functionType', 'Search Post')
            ->set('defaultKeyword', 'samarinda')
            ->set('defaultLimit', '20')
            ->set('memory_limit', 1024)
            ->call('saveActor');

        $component->assertHasNoErrors();
        $savedActor = ApifyActor::where('actor_slug', 'scrapeflow/facebook-posts-search-scraper')->latest('id')->first();
        $this->assertEquals(20, $savedActor->default_limit);
    }
}
