<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Services\AiProviderClient;
use App\Services\AiProviderErrorClassifier;
use App\Services\AiProviderRouter;
use App\Services\AllProvidersFailedException;
use App\Services\RateLimitRetryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Mockery;

class AiProviderRouterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AiProvider::create([
            'name' => 'Provider 1',
            'provider_type' => 'Gemini',
            'base_url' => 'https://api.gemini.com',
            'api_key' => 'secret1',
            'model_name' => 'model1',
            'is_active' => true,
            'priority' => 1,
        ]);

        AiProvider::create([
            'name' => 'Provider 2',
            'provider_type' => 'OpenAI',
            'base_url' => 'https://api.openai.com',
            'api_key' => 'secret2',
            'model_name' => 'model2',
            'is_active' => true,
            'priority' => 2,
        ]);
        
        AiProvider::create([
            'name' => 'Provider 3',
            'provider_type' => 'Gemini',
            'base_url' => 'https://api.gemini.com',
            'api_key' => 'secret3',
            'model_name' => 'model3',
            'is_active' => true,
            'priority' => 3,
        ]);
    }

    public function test_router_returns_highest_priority_provider_first()
    {
        Http::fake([
            '*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Success']]]]]], 200)
        ]);

        $router = app(AiProviderRouter::class);
        $result = $router->execute('System Prompt', 'User Prompt');

        $this->assertEquals('Provider 1', $result['provider']->name);
        $this->assertEquals('Success', $result['text']);
        $this->assertEquals(0, $result['fallback_count']);
    }

    public function test_router_falls_back_when_daily_quota_exhausted()
    {
        Http::fakeSequence()
            ->push(['error' => ['details' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel-FreeTier']]]], 429)
            ->push(['choices' => [['message' => ['content' => 'Fallback Success']]]], 200);

        $router = app(AiProviderRouter::class);
        $result = $router->execute('System Prompt', 'User Prompt');

        $this->assertEquals('Provider 2', $result['provider']->name);
        $this->assertEquals('Fallback Success', $result['text']);
        $this->assertEquals(1, $result['fallback_count']);

        $provider1 = AiProvider::where('name', 'Provider 1')->first();
        $this->assertNotNull($provider1->cooldown_until);
        $this->assertEquals(AiProviderErrorClassifier::CATEGORY_DAILY_REQUEST_QUOTA_EXHAUSTED, $provider1->last_failure_code);
    }
    
    public function test_router_falls_back_when_transient_rate_limit_and_continues_to_next_provider()
    {
        Http::fakeSequence()
            ->push([], 429, ['Retry-After' => '15'])
            ->push(['choices' => [['message' => ['content' => 'Fallback Success']]]], 200);

        $router = app(AiProviderRouter::class);
        $result = $router->execute('System Prompt', 'User Prompt');

        $this->assertEquals('Provider 2', $result['provider']->name);
        $this->assertEquals('Fallback Success', $result['text']);
        $this->assertEquals(1, $result['fallback_count']);

        $provider1 = AiProvider::where('name', 'Provider 1')->first();
        $this->assertNotNull($provider1->cooldown_until);
        $this->assertEquals(AiProviderErrorClassifier::CATEGORY_RATE_LIMIT_MINUTE, $provider1->last_failure_code);
    }

    public function test_router_throws_all_providers_failed_exception_when_all_fail()
    {
        Http::fake([
            '*' => Http::response(['error' => ['details' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel-FreeTier']]]], 429)
        ]);

        $this->expectException(AllProvidersFailedException::class);

        $router = app(AiProviderRouter::class);
        $router->execute('System Prompt', 'User Prompt');
    }
    
    public function test_router_skips_cooldown_provider()
    {
        $provider1 = AiProvider::where('name', 'Provider 1')->first();
        // Use a date far in the future
        $provider1->update(['cooldown_until' => '2099-12-31 23:59:59']);
        
        $router = app(AiProviderRouter::class);
        $availableProviders = $router->getAvailableProviders();
        
        // Assert Provider 1 is missing
        $this->assertFalse($availableProviders->pluck('name')->contains('Provider 1'));
        $this->assertEquals(2, $availableProviders->count()); // Provider 2 and 3 remain
    }

    public function test_router_skips_shared_api_key_when_daily_quota_exhausted()
    {
        // Provider 1 and Provider 3 share the same API key + model, so both should be cooled down.
        $provider3 = AiProvider::where('name', 'Provider 3')->first();
        $provider3->update([
            'api_key' => 'secret1',
            'model_name' => 'model1',
        ]);

        // Sequence of HTTP responses
        Http::fakeSequence()
            ->push(['error' => ['details' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel-FreeTier']]]], 429) // Provider 1 fails with quota
            ->push(['choices' => [['message' => ['content' => 'Fallback Success']]]], 200); // Provider 2 should be next and succeed

        $router = app(AiProviderRouter::class);
        $result = $router->execute('System Prompt', 'User Prompt');

        // It should skip Provider 3 and use Provider 2
        $this->assertEquals('Provider 2', $result['provider']->name);
        $this->assertEquals('Fallback Success', $result['text']);
        $this->assertEquals(1, $result['fallback_count']); // Only 1 fallback since Provider 3 was skipped instantly

        $provider1 = AiProvider::where('name', 'Provider 1')->first();
        $provider3 = AiProvider::where('name', 'Provider 3')->first();
        
        $this->assertNotNull($provider1->cooldown_until);
        $this->assertNotNull($provider3->cooldown_until); // Should also be on cooldown
        $this->assertEquals(AiProviderErrorClassifier::CATEGORY_DAILY_REQUEST_QUOTA_EXHAUSTED, $provider3->last_failure_code);
    }

    public function test_router_retries_after_all_providers_hit_minute_rate_limit()
    {
        Http::fakeSequence()
            ->push([], 429, ['Retry-After' => '15'])
            ->push([], 429, ['Retry-After' => '20'])
            ->push([], 429, ['Retry-After' => '30']);

        $this->expectException(RateLimitRetryException::class);

        $router = app(AiProviderRouter::class);
        try {
            $router->execute('System Prompt', 'User Prompt');
        } catch (RateLimitRetryException $e) {
            $this->assertEquals(15, $e->delaySeconds);
            throw $e;
        }
    }

    public function test_router_handles_exception_as_unavailable()
    {
        Http::fake(function ($request) {
            throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Connection timed out');
        });

        $this->expectException(AllProvidersFailedException::class);

        $router = app(AiProviderRouter::class);
        $router->execute('System Prompt', 'User Prompt');

        $provider1 = AiProvider::where('name', 'Provider 1')->first();
        $this->assertEquals(AiProviderErrorClassifier::CATEGORY_PROVIDER_UNAVAILABLE, $provider1->last_failure_code);
    }
}
