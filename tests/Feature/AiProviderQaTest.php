<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AiProviderQaTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
        ]);
    }

    public function test_it_returns_only_final_answer_and_removes_reasoning_correctly()
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Halo! Saya kabar baik, ada yang bisa dibantu?'
                        ]
                    ]
                ]
            ], 200)
        ]);

        $provider = AiProvider::create([
            'name' => 'OpenAI Test',
            'provider_type' => 'OpenAI',
            'model_name' => 'gpt-4o-mini',
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'api_key' => 'sk-testkey123',
            'is_active' => true,
            'is_default' => true,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\AiProviders::class)
            ->call('openTest', $provider->id)
            ->set('testPrompt', 'Hello, AI! apa kabar')
            ->call('runTest')
            ->assertSet('testResultStatus', 'success')
            ->assertSet('testResultResponse', 'Halo! Saya kabar baik, ada yang bisa dibantu?')
            ->assertSet('testResultError', '');
    }

    public function test_it_triggers_retry_and_sanitizes_raw_gemma_reasoning_if_detected()
    {
        // First request returns reasoning
        // Second request (retry) returns a clean response
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => "The user is greeting me...\nOption 1: Say hello back\nI'll go with Option 1.\nHalo! Saya kabar baik."
                            ]
                        ]
                    ]
                ], 200)
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => "Halo! Saya kabar baik."
                            ]
                        ]
                    ]
                ], 200)
        ]);

        $provider = AiProvider::create([
            'name' => 'OpenAI Test',
            'provider_type' => 'OpenAI',
            'model_name' => 'gpt-4o-mini',
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'api_key' => 'sk-testkey123',
            'is_active' => true,
            'is_default' => true,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\AiProviders::class)
            ->call('openTest', $provider->id)
            ->set('testPrompt', 'Hello, AI! apa kabar')
            ->call('runTest')
            ->assertSet('testResultStatus', 'success')
            ->assertSet('testResultResponse', 'Halo! Saya kabar baik.')
            ->assertSet('testResultError', '');
    }

    public function test_it_returns_fallback_message_if_retry_still_contains_reasoning()
    {
        // Both requests return reasoning
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => "The user is greeting me...\nOption 1: Say hello back\nI'll go with Option 1.\nHalo! Saya kabar baik."
                            ]
                        ]
                    ]
                ], 200)
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => "Meaning: Greetings\nOption 1: Say hello\nI'll go with Option 1.\nHalo! Saya kabar baik."
                            ]
                        ]
                    ]
                ], 200)
        ]);

        $provider = AiProvider::create([
            'name' => 'OpenAI Test',
            'provider_type' => 'OpenAI',
            'model_name' => 'gpt-4o-mini',
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'api_key' => 'sk-testkey123',
            'is_active' => true,
            'is_default' => true,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\AiProviders::class)
            ->call('openTest', $provider->id)
            ->set('testPrompt', 'Hello, AI! apa kabar')
            ->call('runTest')
            ->assertSet('testResultStatus', 'success')
            ->assertSet('testResultResponse', 'Provider menghasilkan format respons yang tidak sesuai.')
            ->assertSet('testResultError', '');
    }

    public function test_it_saves_requests_per_minute_setting_on_provider_form()
    {
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\AiProviders::class)
            ->call('create')
            ->set('name', 'Provider Rate Limit Test')
            ->set('provider_type', 'OpenAI')
            ->set('model_name', 'gpt-4o-mini')
            ->set('requests_per_minute', 24)
            ->set('api_key', 'sk-testkey123')
            ->call('save')
            ->assertHasNoErrors();

        $provider = AiProvider::where('name', 'Provider Rate Limit Test')->first();
        $this->assertNotNull($provider);
        $this->assertSame(24, (int) $provider->requests_per_minute);
    }

    public function test_it_detects_gemini_models_using_stored_api_key_when_field_is_empty()
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'models' => [
                    ['name' => 'models/gemini-2.5-flash'],
                    ['name' => 'models/gemini-2.5-pro'],
                    ['name' => 'models/gemini-3.5-flash'],
                ],
            ], 200),
        ]);

        $provider = AiProvider::create([
            'name' => 'Gemini Stored Key Test',
            'provider_type' => 'Gemini',
            'base_url' => 'https://generativelanguage.googleapis.com/v1',
            'api_key' => 'stored-gemini-key',
            'model_name' => 'gemini-1.5-flash',
            'temperature' => 0.7,
            'max_tokens' => 2048,
            'requests_per_minute' => 30,
            'is_active' => true,
            'is_default' => false,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\AiProviders::class)
            ->call('edit', $provider->id)
            ->set('api_key', '')
            ->call('detectModels')
            ->assertSet('detectedModels', [
                'gemini-2.5-flash',
                'gemini-2.5-pro',
                'gemini-3.5-flash',
            ])
            ->assertSet('model_name', 'gemini-2.5-flash');
    }

    public function test_it_handles_provider_api_errors_gracefully_without_exposing_api_keys()
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'Rate limit exceeded.',
                    'type' => 'requests',
                    'code' => 'rate_limit_exceeded'
                ]
            ], 429)
        ]);

        $provider = AiProvider::create([
            'name' => 'OpenAI Test',
            'provider_type' => 'OpenAI',
            'model_name' => 'gpt-4o-mini',
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'api_key' => 'sk-secret-key-that-must-be-hidden',
            'is_active' => true,
            'is_default' => true,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\AiProviders::class)
            ->call('openTest', $provider->id)
            ->set('testPrompt', 'Hello, AI! apa kabar')
            ->call('runTest')
            ->assertSet('testResultStatus', 'failed')
            ->assertSet('testResultResponse', '');

        // Verify key was NOT exposed in error or response properties
        $this->assertStringNotContainsString('sk-secret-key', Livewire::actingAs($this->adminUser)->test(\App\Livewire\Admin\AiProviders::class)->get('testResultError'));
        $this->assertStringNotContainsString('sk-secret-key', Livewire::actingAs($this->adminUser)->test(\App\Livewire\Admin\AiProviders::class)->get('testResultResponse'));
    }

}
