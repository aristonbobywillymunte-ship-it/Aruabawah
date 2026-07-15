<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Throwable;

class AiFailureClassifier
{
    public function classify(?string $errorCode = null, ?string $errorMessage = null, ?Throwable $exception = null): array
    {
        $normalizedCode = strtolower(trim((string) $errorCode));
        $sanitizedMessage = $this->sanitizeMessage($errorMessage ?? ($exception?->getMessage() ?? ''));

        $fromCode = $this->classifyFromCode($normalizedCode, $sanitizedMessage);
        if ($fromCode !== null) {
            return $fromCode;
        }

        $fromException = $this->classifyFromException($exception, $sanitizedMessage);
        if ($fromException !== null) {
            return $fromException;
        }

        $fromMessage = $this->classifyFromMessage($sanitizedMessage);
        if ($fromMessage !== null) {
            return $fromMessage;
        }

        return $this->result(
            category: 'unknown_error',
            code: $normalizedCode !== '' ? $normalizedCode : 'unknown_error',
            message: 'AI analysis failed.',
            retryable: false
        );
    }

    public function sanitizeMessage(?string $message): string
    {
        $message = trim((string) $message);
        if ($message === '') {
            return '';
        }

        $message = preg_replace('~https?://[^\s]+~i', '[masked-url]', $message) ?? $message;
        $message = preg_replace('~\bkey=[^&\s]+~i', 'key=[masked]', $message) ?? $message;
        $message = preg_replace('~\bapi_key=[^&\s]+~i', 'api_key=[masked]', $message) ?? $message;
        $message = preg_replace('~\btoken=[^&\s]+~i', 'token=[masked]', $message) ?? $message;
        $message = preg_replace('~(Authorization\s*:\s*Bearer\s+)[A-Za-z0-9._-]+~i', '$1[masked]', $message) ?? $message;
        $message = preg_replace('~\bbot\d+:[A-Za-z0-9_-]{20,}\b~', '[masked-telegram-token]', $message) ?? $message;
        $message = preg_replace('~\b\d{5,}:[A-Za-z0-9_-]{20,}\b~', '[masked-token]', $message) ?? $message;
        $message = preg_replace('~\s+~u', ' ', $message) ?? $message;

        return mb_substr(trim($message), 0, 500);
    }

    private function classifyFromCode(string $errorCode, string $sanitizedMessage): ?array
    {
        if ($errorCode === '') {
            return null;
        }

        return match ($errorCode) {
            'empty_content', 'content_too_short' => $this->result(
                'invalid_content',
                $errorCode,
                'Content is not sufficient for AI analysis.',
                false
            ),
            'missing_configuration', 'project_not_found' => $this->result(
                'configuration_error',
                $errorCode,
                $errorCode === 'project_not_found'
                    ? 'Referenced project was not found.'
                    : 'AI provider or prompt template is not ready.',
                false
            ),
            'invalid_json', 'json_decode_failed', 'retry_decode_failed' => $this->result(
                'invalid_json',
                $errorCode,
                'AI returned invalid JSON.',
                false
            ),
            'invalid_ai_reach' => $this->result(
                'invalid_ai_reach',
                $errorCode,
                'AI reach output failed validation.',
                false
            ),
            'all_providers_failed' => $this->result(
                'provider_unavailable',
                $errorCode,
                'All active AI providers failed.',
                true
            ),
            'analysis_failed' => $this->classifyFromMessage($sanitizedMessage)
                ?? $this->result('unknown_error', $errorCode, 'AI analysis failed.', false),
            'quota_exhausted' => $this->result(
                'rate_limit',
                $errorCode,
                'Provider rate limit reached. Retrying later.',
                true
            ),
            'transient_error' => $this->result(
                'provider_unavailable',
                $errorCode,
                'Provider temporarily unavailable.',
                true
            ),
            'database_error' => $this->result(
                'database_error',
                $errorCode,
                'Database error occurred while storing AI result.',
                true
            ),
            default => null,
        };
    }

    private function classifyFromException(?Throwable $exception, string $sanitizedMessage): ?array
    {
        if (! $exception) {
            return null;
        }

        $class = get_class($exception);
        $message = strtolower($sanitizedMessage !== '' ? $sanitizedMessage : $exception->getMessage());
        $code = $exception->getCode();
        $httpStatus = $this->extractHttpStatus($message, is_int($code) ? $code : null);

        if ($exception instanceof ConnectionException) {
            return $this->result(
                'network_error',
                'connection_exception',
                'Provider tidak merespons dalam batas waktu.',
                true,
                $httpStatus
            );
        }

        if (str_contains($message, 'timeout') || str_contains($message, 'timed out') || str_contains($message, 'operation timed out') || str_contains($message, 'curl error 28')) {
            return $this->result(
                'timeout',
                'timeout',
                'Provider tidak merespons dalam batas waktu.',
                true,
                $httpStatus
            );
        }

        if (str_contains($message, '429') || str_contains($message, 'quota') || str_contains($message, 'rate limit') || str_contains($message, 'resource_exhausted') || str_contains($message, 'too many requests')) {
            return $this->result(
                'rate_limit',
                'rate_limit',
                'Provider rate limit reached. Retrying later.',
                true,
                $httpStatus
            );
        }

        if (str_contains($message, '401') || str_contains($message, '403') || str_contains($message, 'unauthorized') || str_contains($message, 'forbidden') || str_contains($message, 'invalid api key') || str_contains($message, 'authentication')) {
            return $this->result(
                'authentication_error',
                'authentication_error',
                'Provider authentication failed.',
                false,
                $httpStatus
            );
        }

        if ($httpStatus === 404 || str_contains($message, 'not found')) {
            if (str_contains($message, 'model') || str_contains($message, 'models/') || str_contains($message, 'generatecontent')) {
                return $this->result(
                    'model_not_found',
                    'model_not_found',
                    'AI model was not found or is not supported.',
                    false,
                    $httpStatus
                );
            }

            return $this->result(
                'provider_unavailable',
                'provider_unavailable',
                'AI provider endpoint was not found.',
                false,
                $httpStatus
            );
        }

        if ($httpStatus !== null && $httpStatus >= 500) {
            return $this->result(
                'provider_unavailable',
                'provider_unavailable',
                'Provider temporarily unavailable.',
                true,
                $httpStatus
            );
        }

        if (str_contains($message, 'sqlstate') || str_contains($message, 'database') || str_contains($message, 'deadlock') || str_contains($message, 'connection refused')) {
            return $this->result(
                'database_error',
                'database_error',
                'Database error occurred while storing AI result.',
                true,
                $httpStatus
            );
        }

        if (str_contains($message, 'json') || str_contains($class, 'json')) {
            return $this->result(
                'invalid_json',
                'invalid_json',
                'AI returned invalid JSON.',
                false,
                $httpStatus
            );
        }

        if (str_contains($message, 'invalid ai reach') || str_contains($message, 'reach output failed validation')) {
            return $this->result(
                'invalid_ai_reach',
                'invalid_ai_reach',
                'AI reach output failed validation.',
                false,
                $httpStatus
            );
        }

        return null;
    }

    private function classifyFromMessage(string $message): ?array
    {
        $message = strtolower($message);

        if ($message === '') {
            return null;
        }

        if (str_contains($message, 'content is empty') || str_contains($message, 'too short') || str_contains($message, 'insufficient content')) {
            return $this->result('invalid_content', 'invalid_content', 'Content is not sufficient for AI analysis.', false);
        }

        if (str_contains($message, 'provider or prompt template is not ready') || str_contains($message, 'missing configuration') || str_contains($message, 'configurasi') || str_contains($message, 'not ready')) {
            return $this->result('configuration_error', 'configuration_error', 'AI provider or prompt template is not ready.', false);
        }

        if (str_contains($message, 'invalid json') || str_contains($message, 'failed to decode json') || str_contains($message, 'decode json')) {
            return $this->result('invalid_json', 'invalid_json', 'AI returned invalid JSON.', false);
        }

        if (str_contains($message, 'invalid ai reach') || str_contains($message, 'reach output failed validation')) {
            return $this->result('invalid_ai_reach', 'invalid_ai_reach', 'AI reach output failed validation.', false);
        }

        return null;
    }

    private function extractHttpStatus(string $message, ?int $fallback = null): ?int
    {
        if (preg_match('/\bHTTP\s+(\d{3})\b/i', $message, $matches)) {
            return (int) $matches[1];
        }

        if ($fallback !== null && $fallback >= 100 && $fallback <= 599) {
            return $fallback;
        }

        return null;
    }

    private function result(string $category, string $code, string $message, bool $retryable, ?int $httpStatus = null): array
    {
        return [
            'category' => $category,
            'code' => $code,
            'message' => $message,
            'retryable' => $retryable,
            'status' => $retryable ? 'retry_wait' : 'failed',
            'http_status' => $httpStatus,
        ];
    }
}
