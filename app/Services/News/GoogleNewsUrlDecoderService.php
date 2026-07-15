<?php

namespace App\Services\News;

use Closure;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class GoogleNewsUrlDecoderService
{
    private const METHOD = 'googlenewsdecoder_0.1.7';
    private const MAX_URL_LENGTH = 16384;
    private const MAX_SEGMENT_LENGTH = 12288;
    private const PROCESS_TIMEOUT_SECONDS = 20;
    private const PYTHON_BINARY_CANDIDATES = [
        '/opt/google-news-venv/bin/python',
        '/opt/google-news-venv/bin/python3',
        'python3',
    ];
    private const REJECT_PATH_FRAGMENTS = [
        '/search',
        '/tag',
        '/kategori',
        '/category',
        '/rss',
        '/feed',
    ];

    public function __construct(
        private readonly ?string $scriptPath = null,
        private readonly ?Closure $runner = null,
    ) {
    }

    public function decode(string $googleUrl): array
    {
        $trace = [];
        $googleUrl = trim($googleUrl);

        if ($googleUrl === '') {
            return $this->failure('Google News URL is required', $trace);
        }

        if ($validationError = $this->validateGoogleUrl($googleUrl)) {
            $trace[] = ['stage' => 'input_validation_failed', 'error' => $validationError];
            return $this->failure($validationError, $trace);
        }

        try {
            $output = $this->runDecoderProcess($googleUrl);
            $trace[] = ['stage' => 'process_completed', 'output_length' => strlen($output)];
        } catch (ProcessTimedOutException $exception) {
            $trace[] = ['stage' => 'process_timeout', 'error' => $exception->getMessage()];
            return $this->failure('Google decoder process timed out', $trace);
        } catch (\Throwable $exception) {
            $trace[] = ['stage' => 'process_failed', 'error' => $this->sanitizeError($exception->getMessage())];
            return $this->failure('Google decoder process failed', $trace);
        }

        $decoded = json_decode($output, true);
        if (! is_array($decoded)) {
            $trace[] = ['stage' => 'json_decode_failed'];
            return $this->failure('Google decoder returned invalid JSON', $trace);
        }

        $trace[] = [
            'stage' => 'helper_result',
            'success' => (bool) ($decoded['success'] ?? false),
            'method' => $decoded['method'] ?? null,
            'error' => $decoded['error'] ?? null,
        ];

        if (! ($decoded['success'] ?? false)) {
            return $this->failure((string) ($decoded['error'] ?? 'Google decoder failed'), $trace);
        }

        $originalUrl = trim((string) ($decoded['original_url'] ?? ''));
        if ($originalUrl === '') {
            return $this->failure('Google decoder returned empty original URL', $trace);
        }

        if ($decodedValidationError = $this->validateDecodedUrl($originalUrl)) {
            $trace[] = ['stage' => 'decoded_validation_failed', 'error' => $decodedValidationError];
            return $this->failure($decodedValidationError, $trace);
        }

        return [
            'success' => true,
            'original_url' => $originalUrl,
            'method' => self::METHOD,
            'error' => null,
            'trace' => $trace,
        ];
    }

    protected function runDecoderProcess(string $googleUrl): string
    {
        if (is_callable($this->runner)) {
            return (string) call_user_func($this->runner, $googleUrl, $this->resolveScriptPath());
        }

        $process = new Process([$this->resolvePythonBinary(), $this->resolveScriptPath(), $googleUrl], base_path());
        $process->setTimeout(self::PROCESS_TIMEOUT_SECONDS);
        $process->setIdleTimeout(self::PROCESS_TIMEOUT_SECONDS);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException($this->sanitizeError($process->getErrorOutput() ?: $process->getOutput()));
        }

        return (string) $process->getOutput();
    }

    private function resolveScriptPath(): string
    {
        return $this->scriptPath ?: base_path('scripts/google-news/decode_google_news_url.py');
    }

    private function resolvePythonBinary(): string
    {
        foreach (self::PYTHON_BINARY_CANDIDATES as $candidate) {
            if ($candidate === 'python3' || is_file($candidate)) {
                return $candidate;
            }
        }

        return 'python3';
    }

    private function validateGoogleUrl(string $googleUrl): ?string
    {
        if (strlen($googleUrl) > self::MAX_URL_LENGTH) {
            return 'Google News URL exceeds maximum length';
        }

        $parts = parse_url($googleUrl);
        if (! is_array($parts)) {
            return 'Google News URL is malformed';
        }

        if (($parts['scheme'] ?? null) !== 'https') {
            return 'Google News URL must use https';
        }

        if (($parts['host'] ?? null) !== 'news.google.com') {
            return 'Google News host must be news.google.com';
        }

        $path = (string) ($parts['path'] ?? '');
        $allowedPrefixes = ['/rss/articles/', '/articles/', '/read/'];
        $allowedPath = false;
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $allowedPath = true;
                break;
            }
        }

        if (! $allowedPath) {
            return 'Google News path is not allowed';
        }

        foreach (explode('/', $path) as $segment) {
            if (strlen($segment) > self::MAX_SEGMENT_LENGTH) {
                return 'Google News path segment exceeds maximum length';
            }
        }

        return null;
    }

    private function validateDecodedUrl(string $decodedUrl): ?string
    {
        $parts = parse_url($decodedUrl);
        if (! is_array($parts)) {
            return 'Decoded URL is malformed';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return 'Decoded URL must use http or https';
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'Decoded URL must not contain credentials';
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return 'Decoded URL host is missing';
        }

        if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
            return 'Decoded URL must not target localhost';
        }

        if ($host === 'google.com' || str_ends_with($host, '.google.com')) {
            return 'Decoded URL is still a Google URL';
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return 'Decoded URL must not target a private or reserved IP';
            }
        }

        $path = strtolower((string) ($parts['path'] ?? ''));
        foreach (self::REJECT_PATH_FRAGMENTS as $fragment) {
            if (str_contains($path, $fragment)) {
                return 'Decoded URL points to a non-article path';
            }
        }

        return null;
    }

    private function sanitizeError(string $error): string
    {
        $error = trim(preg_replace('/\s+/', ' ', $error));

        return mb_substr($error, 0, 500);
    }

    private function failure(string $error, array $trace): array
    {
        return [
            'success' => false,
            'original_url' => null,
            'method' => self::METHOD,
            'error' => $this->sanitizeError($error),
            'trace' => $trace,
        ];
    }
}
