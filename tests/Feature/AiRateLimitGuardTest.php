<?php

namespace Tests\Feature;

use App\Jobs\AiAnalysisJob;
use App\Models\AiProvider;
use App\Models\AiPromptTemplate;
use App\Services\AllProvidersCoolingDownException;
use App\Services\AiProviderRouter;
use App\Queue\Middleware\AiAnalysisRateThrottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiRateLimitGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_job_exposes_throttle_middleware(): void
    {
        $job = new AiAnalysisJob([
            'type' => 'article',
            'id' => 1,
            'content' => str_repeat('x', 500),
        ]);

        $middlewares = $job->middleware();

        $this->assertCount(1, $middlewares);
        $this->assertInstanceOf(AiAnalysisRateThrottle::class, $middlewares[0]);
    }

    public function test_throttle_middleware_releases_job_when_called_too_soon(): void
    {
        Cache::put('ai-analysis:last-dispatch-at', now()->timestamp, now()->addDay());

        $middleware = new AiAnalysisRateThrottle(15);
        $released = null;

        $job = new class {
            public ?int $released = null;

            public function release(int $delay): void
            {
                $this->released = $delay;
            }
        };

        $result = $middleware->handle($job, function () {
            return 'next';
        });

        $this->assertNull($result);
        $this->assertNotNull($job->released);
        $this->assertGreaterThanOrEqual(1, $job->released);
    }

    public function test_all_provider_cooldown_returns_retry_wait_instead_of_failed(): void
    {
        AiPromptTemplate::create([
            'name' => 'Article Prompt',
            'source_type' => 'article',
            'system_prompt' => 'System',
            'user_prompt_template' => 'Prompt',
            'is_default' => true,
            'is_active' => true,
        ]);

        AiProvider::create([
            'name' => 'Provider A',
            'provider_type' => 'Gemini',
            'base_url' => 'https://example.com',
            'api_key' => 'a',
            'model_name' => 'model-a',
            'is_active' => true,
            'cooldown_until' => now()->addMinutes(10),
        ]);

        AiProvider::create([
            'name' => 'Provider B',
            'provider_type' => 'OpenAI',
            'base_url' => 'https://example.com',
            'api_key' => 'b',
            'model_name' => 'model-b',
            'is_active' => true,
            'cooldown_until' => now()->addMinutes(5),
        ]);

        $router = app(AiProviderRouter::class);

        $this->expectException(AllProvidersCoolingDownException::class);
        $this->expectExceptionMessage('All active providers are cooling down.');

        $router->execute('System', 'Prompt');
    }
}
