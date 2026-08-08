<?php

namespace Tests\Feature;

use App\Jobs\AiAnalysisJob;
use App\Models\AiAnalysisResult;
use App\Models\AiPromptTemplate;
use App\Models\AiProvider;
use App\Models\Article;
use App\Models\Project;
use App\Models\SocialMediaItem;
use App\Models\TelegramSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiFinalQualityGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Event::fake();
        Http::fake();

        AiProvider::create([
            'name' => 'OpenAI',
            'api_key' => 'fake',
            'is_active' => true,
            'api_base_url' => 'https://api.openai.com',
            'model_name' => 'gpt-4o',
            'priority' => 1,
            'rate_limit_per_minute' => 100,
        ]);

        AiPromptTemplate::create([
            'source_type' => 'article',
            'name' => 'Default Article Template',
            'system_prompt' => 'You are an AI.',
            'user_prompt_template' => 'Analyze this.',
            'output_schema' => '{"type": "object"}',
            'is_default' => true,
            'is_active' => true,
        ]);

        AiPromptTemplate::create([
            'source_type' => 'social',
            'name' => 'Default Social Template',
            'system_prompt' => 'You are an AI.',
            'user_prompt_template' => 'Analyze this.',
            'output_schema' => '{"type": "object"}',
            'is_default' => true,
            'is_active' => true,
        ]);

        TelegramSetting::create([
            'bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            'chat_id' => '123456789',
            'is_active' => true,
        ]);
    }

    private function createFakeRouterReturn(array $resultData)
    {
        $this->app->instance(\App\Services\AiProviderRouter::class, new class($resultData) {
            private $resultData;
            public function __construct($resultData) { $this->resultData = $resultData; }
            public function execute() {
                return [
                    'provider' => AiProvider::first(),
                    'text' => json_encode($this->resultData),
                    'fallback_count' => 0,
                ];
            }
        });
    }

    public function test_ai_output_is_noise_false_is_saved()
    {
        $article = Article::factory()->create();
        $project = Project::factory()->create();
        $article->projects()->attach($project->id);

        $this->createFakeRouterReturn([
            'is_noise' => false,
            'noise_reason' => 'Clear news',
            'subjects' => ['subject A', 'subject B'],
            'quality_confidence' => 95,
            'sentiment' => 'positive',
        ]);

        (new AiAnalysisJob(['type' => 'article', 'id' => $article->id, 'content' => str_repeat('A', 150), 'project_id' => $project->id]))->handle();

        $result = AiAnalysisResult::where('article_id', $article->id)->first();
        $this->assertNotNull($result);
        $this->assertFalse($result->is_noise);
        $this->assertEquals('Clear news', $result->noise_reason);
        $this->assertEquals(['subject A', 'subject B'], $result->subjects);
        $this->assertEquals(95, $result->quality_confidence);
    }

    public function test_ai_output_is_noise_true_is_saved_and_no_notification()
    {
        $article = Article::factory()->create();
        $project = Project::factory()->create();
        $article->projects()->attach($project->id);

        $this->createFakeRouterReturn([
            'is_noise' => true,
            'noise_reason' => 'Spam content',
            'subjects' => ['spam'],
            'quality_confidence' => 100,
            'risk_level' => 'critical', // Should normally notify
        ]);

        (new AiAnalysisJob(['type' => 'article', 'id' => $article->id, 'content' => str_repeat('A', 150), 'project_id' => $project->id]))->handle();

        $result = AiAnalysisResult::where('article_id', $article->id)->first();
        $this->assertTrue($result->is_noise);
        $this->assertEquals('Spam content', $result->noise_reason);

        // Ensure no telegram job dispatched due to noise
        Queue::assertNotPushed(\App\Jobs\TelegramNotificationJob::class);
    }

    public function test_missing_quality_fields_backward_compatibility()
    {
        $article = Article::factory()->create();
        $project = Project::factory()->create();
        $article->projects()->attach($project->id);

        $this->createFakeRouterReturn([
            'sentiment' => 'neutral',
            // Missing is_noise completely
        ]);

        (new AiAnalysisJob(['type' => 'article', 'id' => $article->id, 'content' => str_repeat('A', 150), 'project_id' => $project->id]))->handle();

        $result = AiAnalysisResult::where('article_id', $article->id)->first();
        $this->assertNull($result->is_noise);
        $this->assertNull($result->noise_reason);
    }

    public function test_social_raw_json_gets_quality_gate_metadata()
    {
        $social = SocialMediaItem::factory()->create(['content' => str_repeat('B', 150), 'raw_json' => '{"foo":"bar"}']);
        $project = Project::factory()->create();
        $social->projects()->attach($project->id);

        $this->createFakeRouterReturn([
            'is_noise' => false,
            'noise_reason' => 'Valid tweet',
            'subjects' => ['Topic X'],
            'quality_confidence' => 90,
        ]);

        (new AiAnalysisJob(['type' => 'social', 'item_id' => $social->id, 'content' => $social->content, 'project_id' => $project->id]))->handle();

        $social->refresh();
        $rawJson = json_decode($social->raw_json, true);
        $this->assertFalse($rawJson['ai_analysis']['is_noise']);
        $this->assertEquals('Valid tweet', $rawJson['ai_analysis']['noise_reason']);
        $this->assertEquals(['Topic X'], $rawJson['ai_analysis']['subjects']);
        $this->assertEquals(90, $rawJson['ai_analysis']['quality_confidence']);
    }

    public function test_one_content_multiple_projects_one_global_analysis()
    {
        $article = Article::factory()->create();
        $project1 = Project::factory()->create();
        $project2 = Project::factory()->create();
        $article->projects()->attach([$project1->id, $project2->id]);

        $this->createFakeRouterReturn(['is_noise' => true]);

        (new AiAnalysisJob(['type' => 'article', 'id' => $article->id, 'content' => str_repeat('A', 150), 'project_id' => $project1->id]))->handle();
        (new AiAnalysisJob(['type' => 'article', 'id' => $article->id, 'content' => str_repeat('A', 150), 'project_id' => $project2->id]))->handle();

        $this->assertEquals(1, AiAnalysisResult::where('article_id', $article->id)->count());
        $this->assertEquals(1, \DB::table('ai_analysis_dispatch_states')->where('analyzable_type', 'article')->where('analyzable_id', $article->id)->count());
    }

    public function test_project_touching_when_analysis_completed()
    {
        $article = Article::factory()->create();
        $project1 = Project::factory()->create(['updated_at' => now()->subDay()]);
        $project2 = Project::factory()->create(['updated_at' => now()->subDay()]);
        $article->projects()->attach([$project1->id, $project2->id]);

        $this->createFakeRouterReturn(['is_noise' => false]);
        (new AiAnalysisJob(['type' => 'article', 'id' => $article->id, 'content' => str_repeat('A', 150), 'project_id' => $project1->id]))->handle();

        $project1->refresh();
        $project2->refresh();

        $this->assertTrue($project1->updated_at->isToday());
        $this->assertTrue($project2->updated_at->isToday()); // The sibling project is also touched!
    }
}
