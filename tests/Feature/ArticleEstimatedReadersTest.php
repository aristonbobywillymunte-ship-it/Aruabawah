<?php

namespace Tests\Feature;

use App\Jobs\AiAnalysisJob;
use App\Models\AiAnalysisResult;
use App\Models\AiPromptTemplate;
use App\Models\AiProvider;
use App\Models\Article;
use App\Models\Project;
use App\Services\AiAnalysisDispatchStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use App\Models\AiAnalysisDispatchState;
use Tests\TestCase;

class ArticleEstimatedReadersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        AiProvider::create([
            'provider_type' => 'OpenAI',
            'name' => 'OpenAI',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'fake-key',
            'model_name' => 'gpt-4o',
            'is_default' => true,
            'is_active' => true,
        ]);

        AiPromptTemplate::create([
            'name' => 'Default Article Template',
            'source_type' => 'article',
            'is_default' => true,
            'is_active' => true,
            'system_prompt' => 'test',
            'user_prompt_template' => 'test',
        ]);
    }

    public function test_success_when_project_readers_is_valid_integer()
    {
        $mockDispatch = Mockery::mock(AiAnalysisDispatchStateService::class);
        $mockDispatch->shouldReceive('resolvePromptTemplateId')->andReturn(1);
        $mockDispatch->shouldReceive('resolveProviderContextHash')->andReturn('hash');
        $mockDispatch->shouldReceive('claimProcessing')->andReturn(new AiAnalysisDispatchState(['id' => 1]));
        $mockDispatch->shouldReceive('markSuccess')->andReturn(null);
        $mockDispatch->shouldReceive('markFailed')->andReturn(null);
        $this->app->instance(AiAnalysisDispatchStateService::class, $mockDispatch);

        $project = Project::create(['name' => 'Test Project']);
        $article = Article::create(['title' => 'Test', 'content' => 'Test', 'url' => 'http://test.com', 'source_name' => 'Test', 'published_at' => now(), 'hash' => uniqid()]);

        Http::fake([
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'project_estimated_readers' => 650,
                                'potential_estimated_readers' => 1243,
                                'potential_reach_score' => 10,
                                'potential_reach_level' => 'Luar biasa/nasional',
                                'potential_reach_band' => '>=1.000 pembaca',
                                'local_relevance_score' => 50,
                                'confidence_score' => 80,
                                'confidence_level' => 'High',
                                'signals_used' => ['content'],
                                'reasoning_summary' => 'test',
                                'limitations' => 'test',
                                'is_exact_reach' => false,
                                'reach_method' => 'ai_reader_estimate_v1',
                                'recommendation' => 'test'
                            ])
                        ]
                    ]
                ]
            ], 200)
        ]);

        $job = new AiAnalysisJob([
            'type' => 'article',
            'id' => $article->id,
            'project_id' => $project->id,
            'content' => str_repeat('Ini adalah contoh berita panjang untuk memenuhi syarat', 20),
            'views' => 500,
        ]);
        $job->handle();

        $result = AiAnalysisResult::where('article_id', $article->id)->first();
        $this->assertNotNull($result);
        $this->assertEquals('success', $result->analysis_status);
        $this->assertEquals(650, $result->project_estimated_readers);
        $this->assertEquals(9, $result->project_reach_score);
        $this->assertEquals('Sangat tinggi', $result->project_reach_level);
        $this->assertEquals('Perkiraan 650 pembaca', $result->project_reach_band);
        $this->assertTrue($result->hasCompleteOfficialAiResult());
        $this->assertEquals(1243, $result->potential_estimated_readers);
    }

    public function test_invalid_ai_reach_when_project_readers_is_missing()
    {
        $mockDispatch = Mockery::mock(AiAnalysisDispatchStateService::class);
        $mockDispatch->shouldReceive('resolvePromptTemplateId')->andReturn(1);
        $mockDispatch->shouldReceive('resolveProviderContextHash')->andReturn('hash');
        $mockDispatch->shouldReceive('claimProcessing')->andReturn(new AiAnalysisDispatchState(['id' => 1]));
        $mockDispatch->shouldReceive('markSuccess')->andReturn(null);
        $mockDispatch->shouldReceive('markFailed')->andReturn(null);
        $this->app->instance(AiAnalysisDispatchStateService::class, $mockDispatch);

        $project = Project::create(['name' => 'Test Project']);
        $article = Article::create(['title' => 'Test', 'content' => 'Test', 'url' => 'http://test.com', 'source_name' => 'Test', 'published_at' => now(), 'hash' => uniqid()]);

        Http::fake([
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                // Missing project_estimated_readers
                                'potential_estimated_readers' => 1243,
                                'potential_reach_score' => 10,
                                'potential_reach_level' => 'Luar biasa/nasional',
                                'potential_reach_band' => '>=1.000 pembaca',
                                'local_relevance_score' => 50,
                                'confidence_score' => 80,
                                'confidence_level' => 'High',
                                'signals_used' => ['content'],
                                'reasoning_summary' => 'test',
                                'limitations' => 'test',
                                'is_exact_reach' => false,
                                'reach_method' => 'ai_reader_estimate_v1',
                                'recommendation' => 'test'
                            ])
                        ]
                    ]
                ]
            ], 200)
        ]);

        $job = new AiAnalysisJob([
            'type' => 'article',
            'id' => $article->id,
            'project_id' => $project->id,
            'content' => str_repeat('Ini adalah contoh berita panjang untuk memenuhi syarat', 20),
            'views' => 500,
        ]);
        $job->handle();

        $result = AiAnalysisResult::where('article_id', $article->id)->first();
        $this->assertNotNull($result);
        $this->assertEquals('invalid_ai_reach', $result->analysis_status);
        $this->assertStringContainsString('article_readers_missing', $result->validation_errors);
    }

    public function test_invalid_ai_reach_when_project_readers_is_negative()
    {
        $mockDispatch = Mockery::mock(AiAnalysisDispatchStateService::class);
        $mockDispatch->shouldReceive('resolvePromptTemplateId')->andReturn(1);
        $mockDispatch->shouldReceive('resolveProviderContextHash')->andReturn('hash');
        $mockDispatch->shouldReceive('claimProcessing')->andReturn(new AiAnalysisDispatchState(['id' => 1]));
        $mockDispatch->shouldReceive('markSuccess')->andReturn(null);
        $mockDispatch->shouldReceive('markFailed')->andReturn(null);
        $this->app->instance(AiAnalysisDispatchStateService::class, $mockDispatch);

        $project = Project::create(['name' => 'Test Project']);
        $article = Article::create(['title' => 'Test', 'content' => 'Test', 'url' => 'http://test.com', 'source_name' => 'Test', 'published_at' => now(), 'hash' => uniqid()]);

        Http::fake([
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'project_estimated_readers' => -50,
                                'potential_estimated_readers' => 1243,
                                'potential_reach_score' => 10,
                                'potential_reach_level' => 'Luar biasa/nasional',
                                'potential_reach_band' => '>=1.000 pembaca',
                                'local_relevance_score' => 50,
                                'confidence_score' => 80,
                                'confidence_level' => 'High',
                                'signals_used' => ['content'],
                                'reasoning_summary' => 'test',
                                'limitations' => 'test',
                                'is_exact_reach' => false,
                                'reach_method' => 'ai_reader_estimate_v1',
                                'recommendation' => 'test'
                            ])
                        ]
                    ]
                ]
            ], 200)
        ]);

        $job = new AiAnalysisJob([
            'type' => 'article',
            'id' => $article->id,
            'project_id' => $project->id,
            'content' => str_repeat('Ini adalah contoh berita panjang untuk memenuhi syarat', 20),
            'views' => 500,
        ]);
        $job->handle();

        $result = AiAnalysisResult::where('article_id', $article->id)->first();
        $this->assertNotNull($result);
        $this->assertEquals('invalid_ai_reach', $result->analysis_status);
        $this->assertStringContainsString('article_readers_too_low', $result->validation_errors);
    }

    public function test_invalid_ai_reach_when_project_readers_is_string()
    {
        $mockDispatch = Mockery::mock(AiAnalysisDispatchStateService::class);
        $mockDispatch->shouldReceive('resolvePromptTemplateId')->andReturn(1);
        $mockDispatch->shouldReceive('resolveProviderContextHash')->andReturn('hash');
        $mockDispatch->shouldReceive('claimProcessing')->andReturn(new AiAnalysisDispatchState(['id' => 1]));
        $mockDispatch->shouldReceive('markSuccess')->andReturn(null);
        $mockDispatch->shouldReceive('markFailed')->andReturn(null);
        $this->app->instance(AiAnalysisDispatchStateService::class, $mockDispatch);

        $project = Project::create(['name' => 'Test Project']);
        $article = Article::create(['title' => 'Test', 'content' => 'Test', 'url' => 'http://test.com', 'source_name' => 'Test', 'published_at' => now(), 'hash' => uniqid()]);

        Http::fake([
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'project_estimated_readers' => '100-200',
                                'potential_estimated_readers' => 1243,
                                'potential_reach_score' => 10,
                                'potential_reach_level' => 'Luar biasa/nasional',
                                'potential_reach_band' => '>=1.000 pembaca',
                                'local_relevance_score' => 50,
                                'confidence_score' => 80,
                                'confidence_level' => 'High',
                                'signals_used' => ['content'],
                                'reasoning_summary' => 'test',
                                'limitations' => 'test',
                                'is_exact_reach' => false,
                                'reach_method' => 'ai_reader_estimate_v1',
                                'recommendation' => 'test'
                            ])
                        ]
                    ]
                ]
            ], 200)
        ]);

        $job = new AiAnalysisJob([
            'type' => 'article',
            'id' => $article->id,
            'project_id' => $project->id,
            'content' => str_repeat('Ini adalah contoh berita panjang untuk memenuhi syarat', 20),
            'views' => 500,
        ]);
        $job->handle();

        $result = AiAnalysisResult::where('article_id', $article->id)->first();
        $this->assertNotNull($result);
        $this->assertEquals('invalid_ai_reach', $result->analysis_status);
        $this->assertStringContainsString('article_readers_missing', $result->validation_errors);
    }
}
