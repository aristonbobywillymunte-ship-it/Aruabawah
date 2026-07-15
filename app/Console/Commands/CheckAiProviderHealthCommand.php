<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AiProvider;
use App\Services\AiProviderClient;
use App\Services\AiProviderErrorClassifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CheckAiProviderHealthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:check-provider-health';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically check the health of AI providers that are currently in cooldown or failed.';

    /**
     * Execute the console command.
     */
    public function handle(AiProviderClient $client, AiProviderErrorClassifier $classifier)
    {
        $this->info('Starting AI Provider Health Check...');

        // Get active providers that are either on cooldown or failed recently
        $providers = AiProvider::where('is_active', true)
            ->where(function ($q) {
                $q->whereNotNull('cooldown_until')
                  ->orWhereNotNull('last_failure_code');
            })
            ->get();

        if ($providers->isEmpty()) {
            $this->info('No providers in cooldown or failed state to check.');
            return;
        }

        $now = now();
        $successCount = 0;
        $failedCount = 0;
        $skippedCount = 0;

        foreach ($providers as $provider) {
            // Check if we should skip due to daily quota and cooldown not yet passed
            $isDailyQuota = in_array($provider->last_failure_code, [
                AiProviderErrorClassifier::CATEGORY_DAILY_REQUEST_QUOTA_EXHAUSTED,
                AiProviderErrorClassifier::CATEGORY_DAILY_TOKEN_QUOTA_EXHAUSTED
            ]);

            if ($isDailyQuota && $provider->cooldown_until && $now->lessThan($provider->cooldown_until)) {
                $this->line("Skipping {$provider->name} (ID: {$provider->id}) - Daily quota exhausted, cooling down until {$provider->cooldown_until}");
                $skippedCount++;
                continue;
            }

            $this->info("Testing {$provider->name} (ID: {$provider->id})...");

            try {
                $response = $client->sendRequest($provider, 'System health check.', 'Hello, AI!', ['temperature' => 0.1]);

                if ($response->successful()) {
                    // Success: Clear cooldowns and errors
                    $provider->last_tested_at = now();
                    $provider->last_test_status = 'success';
                    $provider->cooldown_until = null;
                    $provider->last_failure_code = null;
                    $provider->last_error = null;
                    $provider->save();

                    // Clear any shared API key cache blocks
                    if (filled($provider->api_key)) {
                        $cacheKey = 'ai_shared_quota_blocked:' . md5($provider->api_key);
                        Cache::forget($cacheKey);
                    }

                    $this->info(" -> Success. Provider is now eligible.");
                    Log::info("AI Provider Health Check: {$provider->name} (ID: {$provider->id}) recovered successfully.");
                    $successCount++;
                } else {
                    // Failed: Reclassify the error
                    $errorData = $classifier->classifyResponse($response);
                    
                    $provider->last_tested_at = now();
                    $provider->last_test_status = 'failed';
                    $provider->last_failure_code = $errorData['category'] ?? AiProviderErrorClassifier::CATEGORY_UNKNOWN;
                    $provider->last_error = $response->body();
                    
                    if (!empty($errorData['cooldown_seconds'])) {
                        $provider->cooldown_until = now()->addSeconds($errorData['cooldown_seconds']);
                    }

                    $provider->save();
                    $this->error(" -> Failed. Reclassified as {$provider->last_failure_code}.");
                    Log::warning("AI Provider Health Check: {$provider->name} (ID: {$provider->id}) failed again with code {$provider->last_failure_code}.");
                    $failedCount++;
                }
            } catch (\Exception $e) {
                // Network or client exception
                $provider->last_tested_at = now();
                $provider->last_test_status = 'failed';
                $provider->last_failure_code = AiProviderErrorClassifier::CATEGORY_PROVIDER_UNAVAILABLE;
                $provider->last_error = $e->getMessage();
                $provider->cooldown_until = now()->addMinutes(1); // Small cooldown for basic unavailability
                $provider->save();

                $this->error(" -> Exception: {$e->getMessage()}");
                Log::error("AI Provider Health Check: {$provider->name} (ID: {$provider->id}) exception: {$e->getMessage()}");
                $failedCount++;
            }
        }

        $this->info("Health Check Complete. Success: {$successCount}, Failed: {$failedCount}, Skipped: {$skippedCount}");
    }
}
