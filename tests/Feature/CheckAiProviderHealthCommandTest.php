<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\AiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class CheckAiProviderHealthCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_skips_healthy_providers()
    {
        $provider = AiProvider::create([
            'name' => 'Provider',
            'provider_type' => 'OpenAI',
            'model_name' => 'gpt-3.5-turbo',
            'temperature' => 0.1,
            'is_active' => true,
            'cooldown_until' => null,
            'last_failure_code' => null,
        ]);

        Http::fake();

        $this->artisan('ai:check-provider-health')
            ->expectsOutput('Starting AI Provider Health Check...')
            ->expectsOutput('No providers in cooldown or failed state to check.')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_it_skips_daily_quota_before_cooldown_expires()
    {
        $provider = AiProvider::create([
            'name' => 'Provider',
            'provider_type' => 'OpenAI',
            'model_name' => 'gpt-3.5-turbo',
            'temperature' => 0.1,
            'is_active' => true,
            'cooldown_until' => now()->addHours(2),
            'last_failure_code' => 'daily_request_quota_exhausted',
        ]);

        Http::fake();

        $this->artisan('ai:check-provider-health')
            ->expectsOutputToContain('Skipping ' . $provider->name)
            ->expectsOutputToContain('Skipped: 1')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_it_tests_daily_quota_if_cooldown_expired()
    {
        $provider = AiProvider::create([
            'name' => 'Provider',
            'provider_type' => 'OpenAI',
            'model_name' => 'gpt-3.5-turbo',
            'temperature' => 0.1,
            'name' => 'Recovering Provider',
            'is_active' => true,
            'cooldown_until' => now()->subMinutes(5), // expired
            'last_failure_code' => 'daily_request_quota_exhausted',
            'api_key' => 'fake_api_key_123',
        ]);

        Http::fake([
            '*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Hello']]]]]], 200)
        ]);

        $this->artisan('ai:check-provider-health')
            ->expectsOutputToContain('Testing Recovering Provider')
            ->expectsOutputToContain('Success')
            ->assertExitCode(0);

        $provider->refresh();
        $this->assertNull($provider->cooldown_until);
        $this->assertNull($provider->last_failure_code);
        $this->assertEquals('success', $provider->last_test_status);
        $this->assertFalse(Cache::has('ai_shared_quota_blocked:' . md5('fake_api_key_123')));
    }

    public function test_it_tests_and_reclassifies_on_failure()
    {
        $provider = AiProvider::create([
            'name' => 'Provider',
            'provider_type' => 'OpenAI',
            'model_name' => 'gpt-3.5-turbo',
            'temperature' => 0.1,
            'name' => 'Still Failing Provider',
            'is_active' => true,
            'cooldown_until' => now()->subMinutes(1),
            'last_failure_code' => 'rate_limit_minute',
        ]);

        Http::fake([
            '*' => Http::response(['error' => ['message' => 'Service Unavailable']], 503)
        ]);

        $this->artisan('ai:check-provider-health')
            ->expectsOutputToContain('Testing Still Failing Provider')
            ->expectsOutputToContain('Failed')
            ->assertExitCode(0);

        $provider->refresh();
        $this->assertNotNull($provider->cooldown_until);
        $this->assertEquals('provider_unavailable', $provider->last_failure_code);
        $this->assertEquals('failed', $provider->last_test_status);
    }

    public function test_it_reclassifies_not_found_model_as_invalid_configuration()
    {
        $provider = AiProvider::create([
            'name' => 'Bad Model Provider',
            'provider_type' => 'Gemini',
            'base_url' => 'https://generativelanguage.googleapis.com/v1',
            'model_name' => 'gemini-embedding-2',
            'temperature' => 0.1,
            'is_active' => true,
            'cooldown_until' => null,
            'last_failure_code' => 'unknown_error',
            'api_key' => 'fake_api_key_123',
        ]);

        Http::fake([
            '*' => Http::response([
                'error' => [
                    'code' => 404,
                    'message' => 'models/gemini-embedding-2 is not found for API version v1, or is not supported for generateContent.',
                    'status' => 'NOT_FOUND',
                ],
            ], 404),
        ]);

        $this->artisan('ai:check-provider-health')
            ->expectsOutputToContain('Testing Bad Model Provider')
            ->expectsOutputToContain('Failed')
            ->assertExitCode(0);

        $provider->refresh();
        $this->assertNull($provider->cooldown_until);
        $this->assertEquals('invalid_configuration', $provider->last_failure_code);
        $this->assertEquals('failed', $provider->last_test_status);
    }
}
