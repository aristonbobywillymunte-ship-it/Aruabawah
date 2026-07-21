<?php

namespace App\Services;

use App\Models\AiAnalysisDispatchState;
use App\Jobs\AiAnalysisJob;
use App\Models\AiPromptTemplate;
use App\Models\AiProvider;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AiAnalysisDispatchStateService
{
    public const MAX_AUTO_ATTEMPTS = 3;
    public const DEFAULT_RETRY_MINUTES = 5;

    public function buildDispatchKey(string $analyzableType, int $analyzableId, ?int $projectId, ?int $promptTemplateId, string $providerContextHash): string
    {
        return hash('sha256', implode('|', [
            strtolower(trim($analyzableType)),
            $analyzableId,
        ]));
    }

    public function resolvePromptTemplateId(string $sourceType): ?int
    {
        return AiPromptTemplate::query()
            ->where('source_type', $sourceType)
            ->where('is_default', true)
            ->where('is_active', true)
            ->value('id');
    }

    public function resolveProviderContextHash(): string
    {
        $providers = AiProvider::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get(['id', 'name', 'provider_type', 'model_name', 'base_url', 'is_default']);

        $fingerprint = $providers
            ->map(static function (AiProvider $provider): string {
                return implode('|', [
                    $provider->id,
                    $provider->name,
                    $provider->provider_type,
                    $provider->model_name,
                    $provider->base_url ?? '',
                    (int) $provider->is_default,
                ]);
            })
            ->implode('||');

        return hash('sha256', $fingerprint !== '' ? $fingerprint : 'no-active-provider');
    }

    public function hasActiveProviders(): bool
    {
        return AiProvider::query()->where('is_active', true)->exists();
    }

    public function reserveQueuedState(array $payload, ?int $promptTemplateId = null, ?string $providerContextHash = null): array
    {
        try {
            $context = $this->normalizePayloadContext($payload, $promptTemplateId, $providerContextHash);
        } catch (\InvalidArgumentException $e) {
            \Illuminate\Support\Facades\Log::warning('[AI Dispatch] Skipped reserveQueuedState due to invalid payload: ' . $e->getMessage(), [
                'payload_keys' => array_keys($payload),
            ]);
            return [
                'should_dispatch' => false,
                'status' => 'failed',
                'reason' => 'invalid_payload: ' . $e->getMessage(),
                'state' => null,
            ];
        }

        return DB::transaction(function () use ($context) {
            $state = AiAnalysisDispatchState::query()
                ->where('dispatch_key', $context['dispatch_key'])
                ->lockForUpdate()
                ->first();

            if ($state) {
                return $this->decisionFromExistingState($state, $context);
            }

            if ($context['missing_configuration']) {
                $classification = app(AiFailureClassifier::class)->classify(
                    'missing_configuration',
                    'AI provider or prompt template is not ready.'
                );
                $state = AiAnalysisDispatchState::create(array_merge($context['model'], [
                    'status' => 'failed',
                    'attempts' => 0,
                    'failure_category' => $classification['category'],
                    'last_error_code' => $classification['code'],
                    'error_message' => $classification['message'],
                    'last_failed_at' => now(),
                    'completed_at' => now(),
                    'meta_json' => $context['meta_json'],
                ]));

                return $this->decision($state, false, 'failed', 'missing_configuration');
            }

            $state = AiAnalysisDispatchState::create(array_merge($context['model'], [
                'status' => 'queued',
                'attempts' => 0,
                'meta_json' => $context['meta_json'],
            ]));

            return $this->decision($state, true, 'queued', 'queued');
        });
    }

    public function reserveQueuedStateAndDispatch(array $payload, ?int $promptTemplateId = null, ?string $providerContextHash = null): array
    {
        $decision = $this->reserveQueuedState($payload, $promptTemplateId, $providerContextHash);

        if (! ($decision['should_dispatch'] ?? false)) {
            return $decision;
        }

        try {
            AiAnalysisJob::dispatch(array_merge($payload, [
                'prompt_template_id' => $promptTemplateId,
                'provider_context_hash' => $providerContextHash,
            ]))->onConnection('redis-ai')->onQueue('ai-analysis');

            return $decision;
        } catch (\Throwable $e) {
            Log::error('[AI Dispatch] Failed to enqueue AiAnalysisJob after reserveQueuedState.', [
                'dispatch_state_id' => $decision['state']->id ?? null,
                'analyzable_id' => $payload['id'] ?? $payload['item_id'] ?? null,
                'project_id' => $payload['project_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            $this->markFailed(
                $payload,
                'dispatch_enqueue_failed',
                'Failed to enqueue AI job after reserving dispatch state.',
                $promptTemplateId,
                $providerContextHash,
                $e
            );

            return [
                'should_dispatch' => false,
                'status' => 'failed',
                'reason' => 'dispatch_enqueue_failed',
                'state' => $decision['state'] ?? null,
            ];
        }
    }

    public function claimProcessing(array $payload, ?int $promptTemplateId = null, ?string $providerContextHash = null): ?AiAnalysisDispatchState
    {
        try {
            $context = $this->normalizePayloadContext($payload, $promptTemplateId, $providerContextHash);
        } catch (\InvalidArgumentException $e) {
            \Illuminate\Support\Facades\Log::warning('[AI Dispatch] Skipped claimProcessing due to invalid payload: ' . $e->getMessage(), [
                'payload_keys' => array_keys($payload),
            ]);
            return null;
        }

        return DB::transaction(function () use ($context) {
            $state = AiAnalysisDispatchState::query()
                ->where('dispatch_key', $context['dispatch_key'])
                ->lockForUpdate()
                ->first();

            if (! $state) {
                $state = AiAnalysisDispatchState::create(array_merge($context['model'], [
                    'status' => 'processing',
                    'attempts' => 1,
                    'last_attempt_at' => now(),
                    'meta_json' => $context['meta_json'],
                ]));

                return $state;
            }

            if ($state->status === 'success') {
                return null;
            }

            if ($state->status === 'failed') {
                return null;
            }

            if ($state->status === 'processing') {
                return null;
            }

            if ($state->status === 'retry_wait' && $state->next_retry_at && $state->next_retry_at->isFuture()) {
                return null;
            }

            if (! in_array($state->status, ['queued', 'retry_wait'], true)) {
                return null;
            }

            $state->forceFill([
                'status' => 'processing',
                'attempts' => max(0, (int) $state->attempts) + 1,
                'last_attempt_at' => now(),
                'meta_json' => $context['meta_json'],
            ])->save();

            return $state->refresh();
        });
    }

    public function markSuccess(array $payload, int $analysisId, ?int $promptTemplateId = null, ?string $providerContextHash = null): ?AiAnalysisDispatchState
    {
        try {
            $context = $this->normalizePayloadContext($payload, $promptTemplateId, $providerContextHash);
        } catch (\InvalidArgumentException $e) {
            \Illuminate\Support\Facades\Log::warning('[AI Dispatch] Skipped markSuccess due to invalid payload: ' . $e->getMessage(), [
                'payload_keys' => array_keys($payload),
            ]);
            return null;
        }

        return DB::transaction(function () use ($context, $analysisId) {
            $state = AiAnalysisDispatchState::query()
                ->where('dispatch_key', $context['dispatch_key'])
                ->lockForUpdate()
                ->first();

            if (! $state) {
                $state = AiAnalysisDispatchState::create(array_merge($context['model'], [
                    'status' => 'success',
                    'attempts' => 1,
                    'failure_category' => null,
                    'last_error_code' => null,
                    'error_message' => null,
                    'last_failed_at' => null,
                    'completed_at' => now(),
                    'meta_json' => array_merge($context['meta_json'], ['analysis_id' => $analysisId]),
                ]));
                return $state;
            }

            $state->forceFill([
                'status' => 'success',
                'completed_at' => now(),
                'failure_category' => null,
                'last_error_code' => null,
                'error_message' => null,
                'last_failed_at' => null,
                'next_retry_at' => null,
                'meta_json' => array_merge($context['meta_json'], ['analysis_id' => $analysisId]),
            ])->save();

            return $state->refresh();
        });
    }

    public function markRetryWait(array $payload, string $errorCode, string $errorMessage, ?int $promptTemplateId = null, ?string $providerContextHash = null, ?\Throwable $exception = null): ?AiAnalysisDispatchState
    {
        try {
            $context = $this->normalizePayloadContext($payload, $promptTemplateId, $providerContextHash);
        } catch (\InvalidArgumentException $e) {
            \Illuminate\Support\Facades\Log::warning('[AI Dispatch] Skipped markRetryWait due to invalid payload: ' . $e->getMessage(), [
                'payload_keys' => array_keys($payload),
            ]);
            return null;
        }
        $classification = app(AiFailureClassifier::class)->classify($errorCode, $errorMessage, $exception);
        $nextRetryAt = now()->addMinutes($this->backoffMinutes((int) ($context['attempts_hint'] ?? 1)));

        return $this->persistFailureState($context, 'retry_wait', $classification, $nextRetryAt);
    }

    public function markFailed(array $payload, string $errorCode, string $errorMessage, ?int $promptTemplateId = null, ?string $providerContextHash = null, ?\Throwable $exception = null): ?AiAnalysisDispatchState
    {
        try {
            $context = $this->normalizePayloadContext($payload, $promptTemplateId, $providerContextHash);
        } catch (\InvalidArgumentException $e) {
            \Illuminate\Support\Facades\Log::warning('[AI Dispatch] Skipped markFailed due to invalid payload: ' . $e->getMessage(), [
                'payload_keys' => array_keys($payload),
            ]);
            return null;
        }
        $classification = app(AiFailureClassifier::class)->classify($errorCode, $errorMessage, $exception);

        return $this->persistFailureState($context, 'failed', $classification, null);
    }

    public function classifyFailure(\Throwable $exception): array
    {
        return app(AiFailureClassifier::class)->classify(exception: $exception);
    }

    private function persistFailureState(array $context, string $status, array $classification, ?Carbon $nextRetryAt): ?AiAnalysisDispatchState
    {
        return DB::transaction(function () use ($context, $status, $classification, $nextRetryAt) {
            $state = AiAnalysisDispatchState::query()
                ->where('dispatch_key', $context['dispatch_key'])
                ->lockForUpdate()
                ->first();
            $attempts = max((int) ($context['attempts_hint'] ?? 0), (int) ($state->attempts ?? 0));
            $now = now();
            $errorCode = (string) ($classification['code'] ?? 'analysis_failed');
            $errorMessage = (string) ($classification['message'] ?? 'AI analysis failed.');
            $failureCategory = (string) ($classification['category'] ?? 'unknown_error');

            if (! $state) {
                $state = AiAnalysisDispatchState::create(array_merge($context['model'], [
                    'status' => $status,
                    'attempts' => $attempts,
                    'failure_category' => $failureCategory,
                    'last_error_code' => $errorCode,
                    'error_message' => $errorMessage,
                    'last_attempt_at' => $now,
                    'last_failed_at' => $now,
                    'next_retry_at' => $status === 'retry_wait' ? ($nextRetryAt ?? now()->addMinutes($this->backoffMinutes($attempts))) : null,
                    'completed_at' => $status === 'failed' ? $now : null,
                    'meta_json' => $context['meta_json'],
                ]));

                return $state;
            }

            $state->forceFill([
                'status' => $status,
                'failure_category' => $failureCategory,
                'last_error_code' => $errorCode,
                'error_message' => $errorMessage,
                'last_attempt_at' => $now,
                'last_failed_at' => $now,
                'attempts' => $attempts,
                'next_retry_at' => $status === 'retry_wait' ? ($nextRetryAt ?? now()->addMinutes($this->backoffMinutes($attempts))) : null,
                'completed_at' => $status === 'failed' ? $now : null,
                'meta_json' => $context['meta_json'],
            ])->save();

            return $state->refresh();
        });
    }

    private function decisionFromExistingState(AiAnalysisDispatchState $state, array $context): array
    {
        if ($state->status === 'success') {
            return $this->decision($state, false, 'success', 'already_success');
        }

        if (in_array($state->status, ['queued', 'processing'], true)) {
            return $this->decision($state, false, $state->status, 'duplicate_inflight');
        }

        if ($state->status === 'retry_wait') {
            if ($state->next_retry_at && $state->next_retry_at->isFuture()) {
                return $this->decision($state, false, 'retry_wait', 'retry_not_due');
            }

            if ((int) $state->attempts >= self::MAX_AUTO_ATTEMPTS) {
                $state->forceFill([
                    'status' => 'failed',
                    'last_error_code' => $state->last_error_code ?: 'max_attempts_reached',
                    'last_failed_at' => now(),
                    'completed_at' => now(),
                    'next_retry_at' => null,
                ])->save();

                return $this->decision($state->refresh(), false, 'failed', 'max_attempts_reached');
            }

            $state->forceFill([
                'status' => 'queued',
                'error_message' => null,
                'failure_category' => null,
                'next_retry_at' => null,
                'meta_json' => $context['meta_json'],
            ])->save();

            return $this->decision($state->refresh(), true, 'queued', 'retry_ready');
        }

        if ($state->status === 'failed') {
            return $this->decision($state, false, 'failed', 'permanent_failure_locked');
        }

        return $this->decision($state, false, $state->status, 'duplicate_locked');
    }

    private function normalizePayloadContext(array $payload, ?int $promptTemplateId, ?string $providerContextHash): array
    {
        $analyzableType = strtolower((string) ($payload['type'] ?? 'article'));
        $analyzableId = (int) ($payload['id'] ?? $payload['item_id'] ?? 0);
        $projectIdRaw = $payload['project_id'] ?? null;
        $projectId = is_numeric($projectIdRaw) ? (int) $projectIdRaw : null;

        // Perform payload validations
        if ($analyzableId <= 0) {
            throw new \InvalidArgumentException("analyzable_id must be greater than 0.");
        }
        if ($projectId === null || $projectId <= 0) {
            throw new \InvalidArgumentException("project_id must be greater than 0.");
        }
        if (! \App\Models\Project::query()->where('id', $projectId)->exists()) {
            throw new \InvalidArgumentException("Project ID {$projectId} does not exist.");
        }
        if ($analyzableType === 'social') {
            if (! \App\Models\Article::query()->where('id', $analyzableId)->exists()) {
                throw new \InvalidArgumentException("Mirrored Article target with ID {$analyzableId} does not exist.");
            }
            $itemId = (int) ($payload['item_id'] ?? 0);
            if ($itemId > 0 && ! \App\Models\SocialMediaItem::query()->where('id', $itemId)->exists()) {
                throw new \InvalidArgumentException("SocialMediaItem target with ID {$itemId} does not exist.");
            }
        } elseif ($analyzableType === 'article') {
            if (! \App\Models\Article::query()->where('id', $analyzableId)->exists()) {
                throw new \InvalidArgumentException("Article target with ID {$analyzableId} does not exist.");
            }
        } else {
            throw new \InvalidArgumentException("Unsupported analyzable type: {$analyzableType}");
        }

        $sourceType = $analyzableType === 'social' ? 'social' : 'article';

        $resolvedPromptTemplateId = $promptTemplateId ?? $this->resolvePromptTemplateId($sourceType);
        $resolvedProviderHash = $providerContextHash ?? $this->resolveProviderContextHash();

        $dispatchKey = $this->buildDispatchKey(
            analyzableType: $analyzableType,
            analyzableId: $analyzableId,
            projectId: $projectId,
            promptTemplateId: $resolvedPromptTemplateId,
            providerContextHash: $resolvedProviderHash
        );

        return [
            'dispatch_key' => $dispatchKey,
            'model' => [
                'analyzable_type' => $analyzableType,
                'analyzable_id' => $analyzableId,
                'project_id' => $projectId,
                'prompt_template_id' => $resolvedPromptTemplateId,
                'provider_context_hash' => $resolvedProviderHash,
                'dispatch_key' => $dispatchKey,
            ],
            'meta_json' => [
                'payload_type' => $analyzableType,
                'project_id' => $projectId,
                'prompt_template_id' => $resolvedPromptTemplateId,
                'provider_context_hash' => $resolvedProviderHash,
            ],
            'missing_configuration' => $resolvedPromptTemplateId === null || ! $this->hasActiveProviders(),
            'attempts_hint' => (int) ($payload['attempts'] ?? 0),
        ];
    }

    private function decision(AiAnalysisDispatchState $state, bool $shouldDispatch, string $status, string $reason): array
    {
        return [
            'should_dispatch' => $shouldDispatch,
            'status' => $status,
            'reason' => $reason,
            'state' => $state,
        ];
    }

    private function backoffMinutes(int $attempts): int
    {
        $attempts = max(1, $attempts);
        $minutes = self::DEFAULT_RETRY_MINUTES * (2 ** min($attempts - 1, 4));

        return min(720, $minutes);
    }
}
