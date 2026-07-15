<?php

namespace App\Services;

use Illuminate\Http\Client\Response;

class AiProviderErrorClassifier
{
    public const CATEGORY_RATE_LIMIT_MINUTE = 'rate_limit_minute';
    public const CATEGORY_DAILY_REQUEST_QUOTA_EXHAUSTED = 'daily_request_quota_exhausted';
    public const CATEGORY_DAILY_TOKEN_QUOTA_EXHAUSTED = 'daily_token_quota_exhausted';
    public const CATEGORY_TRANSIENT_PROVIDER_ERROR = 'transient_provider_error';
    public const CATEGORY_AUTHENTICATION_ERROR = 'authentication_error';
    public const CATEGORY_INVALID_CONFIGURATION = 'invalid_configuration';
    public const CATEGORY_INVALID_REQUEST = 'invalid_request';
    public const CATEGORY_INVALID_RESPONSE = 'invalid_response';
    public const CATEGORY_PROVIDER_UNAVAILABLE = 'provider_unavailable';
    public const CATEGORY_NON_RETRYABLE_ERROR = 'non_retryable_error';
    public const CATEGORY_UNKNOWN = 'unknown_error';

    public function classifyResponse(Response $response): array
    {
        $status = $response->status();
        $bodyString = strtolower($response->body());
        $jsonData = $response->json() ?? [];
        $quotaMeta = $this->extractQuotaMetadata($jsonData);

        // 1. Check Authentication & Configuration
        if ($status === 401 || $status === 403) {
            return [
                'category' => self::CATEGORY_AUTHENTICATION_ERROR,
                'retryable' => false,
                'cooldown_seconds' => null,
            ];
        }

        // 2. Check 400 Bad Request
        if ($status === 400) {
            return [
                'category' => self::CATEGORY_INVALID_REQUEST,
                'retryable' => false,
                'cooldown_seconds' => null,
            ];
        }

        if ($status === 404) {
            return [
                'category' => self::CATEGORY_INVALID_CONFIGURATION,
                'retryable' => false,
                'cooldown_seconds' => null,
                'http_status' => $status,
                'status' => $quotaMeta['status'] ?? 'NOT_FOUND',
                'message' => 'Provider model or endpoint is not supported.',
            ];
        }

        // 3. Check Rate Limits (429)
        if ($status === 429) {
            if (($quotaMeta['quota_id'] ?? null) !== null) {
                $quotaId = strtolower((string) $quotaMeta['quota_id']);
                if (str_contains($quotaId, 'perday') && str_contains($quotaId, 'requests')) {
                    return $this->result(
                        self::CATEGORY_DAILY_REQUEST_QUOTA_EXHAUSTED,
                        'daily_request_quota_exhausted',
                        'Daily request quota exhausted.',
                        false,
                        $status,
                        $quotaMeta + ['cooldown_seconds' => 86400]
                    );
                }
                if (str_contains($quotaId, 'perday') && str_contains($quotaId, 'tokens')) {
                    return $this->result(
                        self::CATEGORY_DAILY_TOKEN_QUOTA_EXHAUSTED,
                        'daily_token_quota_exhausted',
                        'Daily token quota exhausted.',
                        false,
                        $status,
                        $quotaMeta + ['cooldown_seconds' => 86400]
                    );
                }
            }

            // Fallback parsing via text
            if (str_contains($bodyString, 'perday') || str_contains($bodyString, 'per day')) {
                if (str_contains($bodyString, 'token')) {
                    return $this->result(
                        self::CATEGORY_DAILY_TOKEN_QUOTA_EXHAUSTED,
                        'daily_token_quota_exhausted',
                        'Daily token quota exhausted.',
                        false,
                        $status,
                        $quotaMeta + ['cooldown_seconds' => 86400]
                    );
                }
                return $this->result(
                    self::CATEGORY_DAILY_REQUEST_QUOTA_EXHAUSTED,
                    'daily_request_quota_exhausted',
                    'Daily request quota exhausted.',
                    false,
                    $status,
                    $quotaMeta + ['cooldown_seconds' => 86400]
                );
            }

            // Fallback parsing for quota exhausted without specific "per day" but explicitly saying quota exhausted
            if (str_contains($bodyString, 'quota exceeded') || str_contains($bodyString, 'quota_exhausted')) {
                 return $this->result(
                     self::CATEGORY_DAILY_REQUEST_QUOTA_EXHAUSTED,
                     'daily_request_quota_exhausted',
                     'Daily request quota exhausted.',
                     false,
                     $status,
                     $quotaMeta + ['cooldown_seconds' => 86400]
                 );
            }

            // Jika bukan quota exhausted, maka ini adalah minute rate limit
            $delay = $quotaMeta['retry_after_seconds'] ?? null;
            if (!is_int($delay) || $delay < 1) {
                $retryAfter = $response->header('Retry-After');
                $delay = $retryAfter && is_numeric($retryAfter) ? (int) $retryAfter : 60;
            }
            return [
                'category' => self::CATEGORY_RATE_LIMIT_MINUTE,
                'retryable' => true,
                'cooldown_seconds' => $delay,
                'quota_id' => $quotaMeta['quota_id'] ?? null,
                'quota_metric' => $quotaMeta['quota_metric'] ?? null,
                'retry_delay_seconds' => $delay,
                'http_status' => $status,
                'status' => 'RATE_LIMIT',
                'message' => 'Provider rate limit reached. Retrying later.',
            ];
        }

        // 4. Server errors
        if ($status >= 500) {
            if ($status === 503) {
                return ['category' => self::CATEGORY_PROVIDER_UNAVAILABLE, 'retryable' => true, 'cooldown_seconds' => 60];
            }
            return ['category' => self::CATEGORY_TRANSIENT_PROVIDER_ERROR, 'retryable' => true, 'cooldown_seconds' => 60];
        }

        return [
            'category' => self::CATEGORY_UNKNOWN,
            'retryable' => false,
            'cooldown_seconds' => null,
            'quota_id' => $quotaMeta['quota_id'] ?? null,
            'quota_metric' => $quotaMeta['quota_metric'] ?? null,
            'retry_delay_seconds' => $quotaMeta['retry_after_seconds'] ?? null,
            'http_status' => $status,
            'status' => 'UNKNOWN',
            'message' => 'AI analysis failed.',
        ];
    }

    public function classifyInvalidJson(): array
    {
        return [
            'category' => self::CATEGORY_INVALID_RESPONSE,
            'retryable' => true,
            'cooldown_seconds' => null, // fallback can be immediate
        ];
    }

    private function result(
        string $category,
        string $code,
        string $message,
        bool $retryable,
        ?int $httpStatus = null,
        array $extra = []
    ): array {
        return array_merge([
            'category' => $category,
            'code' => $code,
            'message' => $message,
            'retryable' => $retryable,
            'cooldown_seconds' => $retryable ? ($extra['retry_delay_seconds'] ?? 60) : ($extra['cooldown_seconds'] ?? null),
            'http_status' => $httpStatus,
        ], $extra);
    }

    private function extractQuotaMetadata(array $jsonData): array
    {
        $quotaId = null;
        $quotaMetric = null;
        $retryAfterSeconds = null;
        $status = null;
        $message = null;

        $error = $jsonData['error'] ?? [];
        if (is_array($error)) {
            $status = $error['status'] ?? $status;
            $message = $error['message'] ?? $message;
            foreach (($error['details'] ?? []) as $detail) {
                if (! is_array($detail)) {
                    continue;
                }
                $quotaId ??= $detail['quotaId'] ?? null;
                $quotaMetric ??= $detail['quotaMetric'] ?? null;

                $retryDelay = $detail['retryDelay'] ?? null;
                if (is_array($retryDelay) && isset($retryDelay['seconds']) && is_numeric($retryDelay['seconds'])) {
                    $retryAfterSeconds ??= (int) $retryDelay['seconds'];
                }
            }
        }

        return [
            'quota_id' => $quotaId,
            'quota_metric' => $quotaMetric,
            'retry_after_seconds' => $retryAfterSeconds,
            'status' => $status,
            'message' => $message,
        ];
    }
}
