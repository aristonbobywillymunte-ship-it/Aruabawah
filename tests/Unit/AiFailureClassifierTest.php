<?php

namespace Tests\Unit;

use App\Services\AiFailureClassifier;
use Tests\TestCase;

class AiFailureClassifierTest extends TestCase
{
    public function test_sanitizes_secret_leaks_and_masks_urls(): void
    {
        $classifier = app(AiFailureClassifier::class);

        $message = $classifier->sanitizeMessage('Request failed for https://api.example.com/v1?key=SECRET123&token=ABC with Authorization: Bearer SECRET456');

        $this->assertStringNotContainsString('SECRET123', $message);
        $this->assertStringNotContainsString('SECRET456', $message);
        $this->assertStringNotContainsString('api.example.com/v1?key=', $message);
        $this->assertStringContainsString('[masked-url]', $message);
    }

    public function test_classifies_common_ai_failures_with_retry_policy(): void
    {
        $classifier = app(AiFailureClassifier::class);

        $timeout = $classifier->classify(exception: new \RuntimeException('cURL error 28: Operation timed out after 30000 milliseconds'));
        $this->assertSame('timeout', $timeout['category']);
        $this->assertTrue($timeout['retryable']);

        $invalidJson = $classifier->classify('invalid_json', 'Failed to decode JSON response from AI.');
        $this->assertSame('invalid_json', $invalidJson['category']);
        $this->assertFalse($invalidJson['retryable']);

        $invalidContent = $classifier->classify('content_too_short', 'Content is too short for AI analysis.');
        $this->assertSame('invalid_content', $invalidContent['category']);
        $this->assertFalse($invalidContent['retryable']);

        $allProvidersFailed = $classifier->classify('all_providers_failed', 'All active AI providers failed.');
        $this->assertSame('provider_unavailable', $allProvidersFailed['category']);
        $this->assertSame('all_providers_failed', $allProvidersFailed['code']);
        $this->assertTrue($allProvidersFailed['retryable']);
    }
}
