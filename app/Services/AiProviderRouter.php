<?php

namespace App\Services;

use App\Models\AiProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Exception;

class AllProvidersFailedException extends Exception {}
class AllProvidersCoolingDownException extends Exception {
    public function __construct(public int $delaySeconds, string $message = 'All active providers are cooling down.')
    {
        parent::__construct($message);
    }
}
class RateLimitRetryException extends Exception {
    public $delaySeconds;
    public function __construct(int $delaySeconds)
    {
        $this->delaySeconds = $delaySeconds;
        parent::__construct("Rate limit exceeded, retry after {$delaySeconds}s");
    }
}

class AiProviderRouter
{
    protected AiProviderClient $client;
    protected AiProviderErrorClassifier $classifier;

    public function __construct(AiProviderClient $client, AiProviderErrorClassifier $classifier)
    {
        $this->client = $client;
        $this->classifier = $classifier;
    }

    /**
     * Get ordered available providers that are active, compatible, and not in cooldown.
     */
    public function getAvailableProviders(string $task = 'article_analysis', array $options = [])
    {
        AiProvider::syncDefaultToEligible();

        $query = AiProvider::query()
            ->where('is_active', true);

        if (Schema::hasColumn('ai_providers', 'cooldown_until')) {
            $query->where(function ($subQuery) {
                $subQuery->whereNull('cooldown_until')
                    ->orWhere('cooldown_until', '<=', now());
            });
        }

        if (Schema::hasColumn('ai_providers', 'priority')) {
            $query->orderBy('priority', 'asc')->orderBy('id', 'asc');
        } else {
            $query->orderBy('id', 'asc');
        }

        $providers = $query->get();

        $providers = $providers
            ->filter(fn (AiProvider $provider) => $this->isCompatible($provider, $task, $options))
            ->values();

        return $providers;
    }

    /**
     * Execute a request trying available providers in sequence.
     * Throws AllProvidersFailedException if all fail or are unavailable.
     * Throws RateLimitRetryException if the current provider hit a minute rate limit that we should backoff and retry (don't failover yet).
     * 
     * @return array [ 'provider' => AiProvider, 'text' => string ]
     */
    public function execute(string $systemPrompt, string $userPrompt, array $options = [], $articleIdForLog = null, string $task = 'article_analysis'): array
    {
        $providers = $this->getAvailableProviders($task, $options);
        $articleContext = $articleIdForLog ? " for Article {$articleIdForLog}" : "";

        if ($providers->isEmpty()) {
            $coolingDownProviders = AiProvider::query()
                ->where('is_active', true)
                ->whereNotNull('cooldown_until')
                ->get(['id', 'name', 'cooldown_until']);

            if ($coolingDownProviders->isNotEmpty()) {
                $delay = $coolingDownProviders
                    ->map(fn (AiProvider $provider) => max(30, now()->diffInSeconds($provider->cooldown_until, false) + 30))
                    ->min() ?? 60;

                Log::warning("[AiRouter] All active AI providers are cooling down{$articleContext}. Retrying later in {$delay}s.");
                throw new AllProvidersCoolingDownException($delay);
            }

            Log::error("[AiRouter] No active AI providers available.");
            throw new AllProvidersFailedException("No active providers available.");
        }

        $fallbackCount = 0;
        $lastErrorCategory = null;
        $fallbackReason = null;
        $skippedProviderFingerprints = [];
        $minuteRateLimitDelays = [];

        foreach ($providers as $provider) {
            $providerFingerprint = $this->sharedQuotaFingerprint($provider);
            if (filled($providerFingerprint) && in_array($providerFingerprint, $skippedProviderFingerprints, true)) {
                Log::info("[AiRouter] Skipping provider {$provider->name} because its shared quota fingerprint is on cooldown from a previous daily quota failure.");
                $fallbackCount++;
                continue;
            }

            $providerName = $provider->name;
            
            // Periksa Local Rate Limiter
            $rateLimitKey = 'ai-provider-' . $provider->id;
            $maxRequests = $provider->requests_per_minute ?? 15;
            
            if (RateLimiter::tooManyAttempts($rateLimitKey, $maxRequests)) {
                $seconds = RateLimiter::availableIn($rateLimitKey);
                $delay = max(5, $seconds);
                Log::info("[AiRouter] Local rate limit hit for provider {$providerName}{$articleContext}. Cooling down provider and trying the next eligible provider ({$delay}s).");
                $this->handleProviderError($provider, AiProviderErrorClassifier::CATEGORY_RATE_LIMIT_MINUTE, $delay);
                $minuteRateLimitDelays[] = $delay;
                $fallbackCount++;
                $fallbackReason = AiProviderErrorClassifier::CATEGORY_RATE_LIMIT_MINUTE;
                continue;
            }

            try {
                RateLimiter::hit($rateLimitKey, 60);

                Log::info("[AiRouter] Trying provider: {$providerName} (Fallback: {$fallbackCount}){$articleContext}");
                $response = $this->client->sendRequest($provider, $systemPrompt, $userPrompt, $options);
                
                if ($response->successful()) {
                    $text = $this->client->parseResponse($provider, $response);
                    
                    if (empty($text)) {
                        // Response empty or parse failed => Invalid Response
                        $this->handleProviderError($provider, AiProviderErrorClassifier::CATEGORY_INVALID_RESPONSE, null);
                        $fallbackCount++;
                        continue; // try next provider
                    }

                    // Success!
                    Log::info("[AiRouter] Success using provider: {$providerName}{$articleContext}");
                    return [
                        'provider' => $provider,
                        'text' => $text,
                        'fallback_count' => $fallbackCount,
                        'provider_id_used' => $provider->id,
                        'provider_name' => $provider->name,
                        'model_used' => $provider->model_name,
                        'fallback_reason' => $fallbackReason,
                        'last_error_category' => $lastErrorCategory,
                    ];
                }

                // If not successful, classify error
                $classification = $this->classifier->classifyResponse($response);
                $category = $classification['category'];
                $failureCode = $classification['code'] ?? $category;
                $lastErrorCategory = $failureCode;
                
                Log::warning("[AiRouter] Provider {$providerName} failed with {$category}{$articleContext}. Status: " . $response->status());
                
                $safeUrl = preg_replace('/key=[^&\s]+/', 'key=***', $response->effectiveUri());
                Log::error("[AiRouter] URL: " . $safeUrl);
                Log::error("[AiRouter] Response body: " . mb_substr($response->body(), 0, 1000));

                if ($category === AiProviderErrorClassifier::CATEGORY_RATE_LIMIT_MINUTE) {
                    // This is a transient rate limit (not daily quota). Mark cooldown for this provider and fail over to the next eligible provider.
                    $delay = $classification['cooldown_seconds'] ?? 60;
                    $this->handleProviderError($provider, $category, $delay);
                    $minuteRateLimitDelays[] = max(5, $delay);
                    $fallbackCount++;
                    $fallbackReason = $category;
                    continue;
                }

                // For other errors (daily quota, auth, invalid config, 5xx), mark cooldown and failover
                $this->handleProviderError($provider, $category, $classification['cooldown_seconds']);
                
                if (filled($providerFingerprint) && in_array($category, [AiProviderErrorClassifier::CATEGORY_DAILY_REQUEST_QUOTA_EXHAUSTED, AiProviderErrorClassifier::CATEGORY_DAILY_TOKEN_QUOTA_EXHAUSTED], true)) {
                    $skippedProviderFingerprints[] = $providerFingerprint;
                    $sharedProviders = AiProvider::query()
                        ->where('id', '!=', $provider->id)
                        ->where('api_key', $provider->api_key)
                        ->where('model_name', $provider->model_name)
                        ->get();
                    foreach ($sharedProviders as $shared) {
                        Log::info("[AiRouter] Putting shared provider {$shared->name} on cooldown because of daily quota exhaustion on {$providerName}.");
                        $this->handleProviderError($shared, $category, $classification['cooldown_seconds']);
                    }
                }

                $fallbackCount++;
                $fallbackReason = $category;

            } catch (\Exception $e) {
                $msg = preg_replace('/key=[^&\s]+/', 'key=***', $e->getMessage());
                Log::error("[AiRouter] Exception using provider {$providerName}{$articleContext}: {$msg}");
                
                $category = str_contains(strtolower($e->getMessage()), 'cURL error 28') || str_contains(strtolower($e->getMessage()), 'cURL error 7')
                    ? AiProviderErrorClassifier::CATEGORY_PROVIDER_UNAVAILABLE 
                    : AiProviderErrorClassifier::CATEGORY_UNKNOWN;

                $this->handleProviderError($provider, $category, 60);
                $fallbackCount++;
                $fallbackReason = $category;
            }
        }

        if (!empty($minuteRateLimitDelays)) {
            $delay = min($minuteRateLimitDelays);
            Log::warning("[AiRouter] All eligible providers hit minute rate limit{$articleContext}. Backing off for {$delay}s.");
            throw new RateLimitRetryException($delay);
        }

        Log::error("[AiRouter] All available providers failed{$articleContext}.");
        throw new AllProvidersFailedException("All AI providers exhausted.");
    }

    protected function handleProviderError(AiProvider $provider, string $category, ?int $cooldownSeconds)
    {
        if (Schema::hasColumn('ai_providers', 'last_failure_code')) {
            $provider->last_failure_code = $category;
        }

        if (Schema::hasColumn('ai_providers', 'cooldown_until') && $cooldownSeconds) {
            $provider->cooldown_until = now()->addSeconds($cooldownSeconds);
            Log::info("[AiRouter] Putting provider {$provider->name} on cooldown for {$cooldownSeconds}s.");
        }

        if (Schema::hasColumn('ai_providers', 'last_error')) {
            $provider->last_error = "Failed with category: " . $category . " at " . now()->toDateTimeString();
        }
        
        $provider->save();
    }

    protected function effectivePriority(AiProvider $provider): int
    {
        if (! Schema::hasColumn('ai_providers', 'priority')) {
            return $provider->isEligibleForUse() ? 0 : 1000 + (int) $provider->id;
        }

        return (int) ($provider->priority ?? ($provider->isEligibleForUse() ? 0 : 1000 + $provider->id));
    }

    protected function sharedQuotaFingerprint(AiProvider $provider): ?string
    {
        $apiKey = trim((string) ($provider->api_key ?? ''));
        $modelName = trim((string) ($provider->model_name ?? ''));

        if ($apiKey === '' || $modelName === '') {
            return null;
        }

        return hash('sha256', strtolower($apiKey . '|' . $modelName));
    }

    protected function isCompatible(AiProvider $provider, string $task, array $options = []): bool
    {
        if (! Schema::hasColumn('ai_providers', 'capabilities')) {
            return true;
        }

        $capabilities = $provider->capabilities;
        if (blank($capabilities)) {
            return true;
        }

        if (is_string($capabilities)) {
            $decoded = json_decode($capabilities, true);
            $capabilities = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        if (! is_array($capabilities) || empty($capabilities)) {
            return true;
        }

        $normalizedTask = strtolower(trim($task));
        $taskAliases = [
            'article_analysis' => ['article_analysis', 'article', 'analysis', 'text', 'json', 'structured_response'],
            'article_readers_backfill' => ['article_readers_backfill', 'article', 'analysis', 'text', 'json', 'structured_response'],
            'project_insight' => ['project_insight', 'article', 'analysis', 'text', 'json', 'structured_response'],
        ];

        $requiredTokens = $taskAliases[$normalizedTask] ?? [$normalizedTask];
        $availableTokens = [];

        foreach (['tasks', 'task_types', 'capabilities', 'supports', 'modes'] as $key) {
            $value = $capabilities[$key] ?? null;
            if (is_array($value)) {
                $availableTokens = array_merge($availableTokens, array_map('strval', $value));
            } elseif (is_string($value) && trim($value) !== '') {
                $availableTokens[] = $value;
            }
        }

        foreach ($capabilities as $key => $value) {
            if (in_array((string) $key, ['supports_json', 'supports_text', 'supports_structured_response'], true) && $value === false) {
                if ($key === 'supports_json' && (($options['response_format'] ?? null) === 'json_object')) {
                    return false;
                }
            }
        }

        if (empty($availableTokens)) {
            return true;
        }

        $availableTokens = array_map(fn ($token) => strtolower(trim((string) $token)), $availableTokens);

        foreach ($requiredTokens as $required) {
            if (in_array(strtolower($required), $availableTokens, true)) {
                return true;
            }
        }

        return false;
    }
}
