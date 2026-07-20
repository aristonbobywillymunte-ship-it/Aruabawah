<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApifyActor extends Model
{
    public const MAX_SOCIAL_ITEMS_PER_RUN = 50;

    public const NON_UI_ERROR_PATTERNS = [
        'maximum cost',
        'max total charge',
        'maxtotalchargeusd',
        'partial: cost limit reached',
        'done at',
        'hard limit reached',
        'monthly usage hard limit exceeded',
        'platform-feature-disabled',
    ];

    protected $fillable = [
        'platform',
        'actor_name',
        'actor_slug',
        'function_type',
        'default_keyword',
        'default_limit',
        'date_from',
        'date_to',
        'status',
        'last_run_at',
        'last_run_status',
        'last_run_message',
        'keyword_field_mapping',
        'output_mapping',
        'build',
        'timeout_seconds',
        'no_timeout',
        'interval_minutes',
        'memory_limit',
        'range_mode',
        'priority',
        'maximum_cost_per_run_usd',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'last_run_at' => 'datetime',
        'timeout_seconds' => 'integer',
        'no_timeout' => 'boolean',
        'interval_minutes' => 'integer',
        'memory_limit' => 'integer',
        'priority' => 'integer',
        'maximum_cost_per_run_usd' => 'decimal:4',
    ];

    protected static function booted()
    {
        static::saving(function ($actor) {
        });
    }

    public static function shouldSuppressUiError(?string $message): bool
    {
        if (!$message) {
            return false;
        }

        foreach (self::NON_UI_ERROR_PATTERNS as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public static function friendlyRunMessage(?string $message): ?string
    {
        if (!$message) {
            return null;
        }

        if (str_contains($message, 'Monthly usage hard limit exceeded') || str_contains($message, 'platform-feature-disabled')) {
            $checkDateTime = \Carbon\Carbon::now()->locale('id')->addMinutes(5)->translatedFormat('d F Y \p\u\k\u\l H:i');
            return "Batas pengambilan data bulanan telah terlampaui. Seluruh pemantauan media sosial ditangguhkan sampai kuota diperbarui atau paket layanan ditingkatkan. Pemeriksaan ulang akan dilakukan pada tanggal {$checkDateTime}.";
        }

        if (str_contains($message, 'Batas biaya Apify') || str_contains($message, 'partial: cost limit reached')) {
            return 'Selesai sebagian. Batas biaya Apify tercapai, run dihentikan aman, dan data yang sudah terkumpul tetap disimpan serta diproses.';
        }

        if (str_contains($message, 'done at') || str_contains($message, 'Hard limit reached')) {
            return 'Pencarian selesai setelah mencapai batas hasil yang ditetapkan.';
        }

        if (str_contains($message, 'ABORTED') || str_contains($message, 'did not succeed')) {
            return 'Koneksi/permintaan scraper dibatalkan oleh server Apify (Aborted). Sistem akan mencoba ulang secara otomatis.';
        }

        if (self::shouldSuppressUiError($message)) {
            return 'Apify sedang diblok sementara karena limit. Menunggu pemulihan otomatis.';
        }

        return $message;
    }

    public static function friendlyRunStatus(?string $message): ?string
    {
        if (!$message) {
            return null;
        }

        if (str_contains($message, 'Monthly usage hard limit exceeded') || str_contains($message, 'platform-feature-disabled')) {
            return 'Kuota Habis';
        }

        if (str_contains($message, 'Batas biaya Apify') || str_contains($message, 'partial: cost limit reached')) {
            return 'Selesai Sebagian';
        }

        if (str_contains($message, 'done at') || str_contains($message, 'Hard limit reached')) {
            return 'Selesai';
        }

        if (self::shouldSuppressUiError($message)) {
            return 'Diblok sementara';
        }

        return null;
    }

    public function buildInputPayload(?string $keyword = null, ?int $limit = null, ?string $dateFrom = null, ?string $dateTo = null, ?array $keywords = null): array
    {
        $resolvedLimit = $limit;
        if (is_null($resolvedLimit)) {
            $resolvedLimit = (int) ($this->default_limit ?? 50);
        }

        if ($this->platform === 'TikTok') {
            return $this->buildTikTokInputPayload($keyword, $keywords, $resolvedLimit, $dateFrom, $dateTo);
        }

        if ($this->platform === 'Facebook') {
            return $this->buildFacebookInputPayload($keyword, $keywords, $resolvedLimit, $dateFrom, $dateTo);
        }

        if ($this->platform === 'Instagram') {
            return $this->buildInstagramInputPayload($keyword, $keywords, $resolvedLimit, $dateFrom, $dateTo);
        }

        $payload = $this->resolveTemplatePayload($keyword, $resolvedLimit, $dateFrom, $dateTo);

        if (!empty($payload)) {
            // Template already handles date interpolation via {date_from}, {date_to}, {time_filter}.
            // Do NOT re-merge resolveDatePayload() — it would add unwanted generic dateFrom/dateTo keys.
            return $payload;
        }

        $field = $this->keyword_field_mapping ?: 'searchTerms';
        $value = $keyword ?: $this->default_keyword;

        $payload = [
            $field => [$value],
            'maxResults' => $resolvedLimit,
        ];

        return array_merge($payload, $this->resolveDatePayload($dateFrom, $dateTo));
    }

    protected function buildFacebookInputPayload(?string $keyword, ?array $keywords, int $limit, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $keywords = array_values(array_filter(array_map(
            fn ($value) => $this->sanitizeSocialKeyword((string) $value),
            $keywords ?? [$keyword]
        )));

        if ($keywords === []) {
            $keywords = [$this->sanitizeSocialKeyword((string) ($keyword ?: $this->default_keyword))];
        }

        $config = [];
        if (filled($this->output_mapping)) {
            $decoded = json_decode($this->output_mapping, true);
            if (is_array($decoded)) {
                $config = $decoded;
            }
        }

        $postTimeRange = (string) data_get($config, 'postTimeRange', $this->resolveTimeFilter());
        if ($postTimeRange === '' || str_contains($postTimeRange, '{time_filter}')) {
            $postTimeRange = $this->resolveTimeFilter();
        }
        $useApifyProxy = (bool) data_get($config, 'proxyConfiguration.useApifyProxy', true);
        $configuredMaxPosts = data_get($config, 'maxPosts', null);
        $resolvedMaxPosts = (int) $configuredMaxPosts;
        if ($resolvedMaxPosts < 1 || str_contains((string) $configuredMaxPosts, '{limit}')) {
            $resolvedMaxPosts = $limit;
        }
        return [
            'maxPosts' => $resolvedMaxPosts,
            'postTimeRange' => $postTimeRange ?: $this->resolveTimeFilter(),
            'proxyConfiguration' => [
                'useApifyProxy' => $useApifyProxy,
            ],
            'searchQueries' => $keywords,
        ];
    }

    protected function buildInstagramInputPayload(?string $keyword, ?array $keywords, int $limit, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $keywords = array_values(array_filter(array_map(
            fn ($value) => $this->normalizeInstagramSearchKeyword((string) $value),
            $keywords ?? [$keyword]
        )));

        if ($keywords === []) {
            $keywords = [];
        }

        $hashtags = array_values(array_filter(array_map(
            fn ($value) => $this->normalizeInstagramPayloadHashtag((string) $value),
            $keywords
        )));

        $config = [];
        if (filled($this->output_mapping)) {
            $decoded = json_decode($this->output_mapping, true);
            if (is_array($decoded)) {
                $config = $decoded;
            }
        }

        $resultsType = trim((string) data_get($config, 'resultsType', 'posts'));
        if (! in_array($resultsType, ['posts', 'reels'], true)) {
            $resultsType = 'posts';
        }

        $configuredResultsLimit = data_get($config, 'resultsLimit', null);
        $configuredTotalLimit = (int) $configuredResultsLimit;
        if ($configuredTotalLimit < 1 || str_contains((string) $configuredResultsLimit, '{limit}')) {
            $configuredTotalLimit = (int) ($this->default_limit ?? $limit);
        }

        return [
            'hashtags' => $hashtags,
            'resultsType' => $resultsType,
            'resultsLimit' => $configuredTotalLimit,
        ];
    }

    protected function buildTikTokInputPayload(?string $keyword, ?array $keywords, int $limit, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $keywords = array_values(array_filter(array_map(
            fn ($value) => $this->normalizeTikTokHashtag((string) $value),
            $keywords ?? [$keyword]
        )));

        if ($keywords === []) {
            $keywords = [$this->normalizeTikTokHashtag((string) ($keyword ?: $this->default_keyword))];
        }

        $configuredTotalLimit = (int) ($this->default_limit ?? $this->tiktok_results_per_page ?? $limit);
        if ($configuredTotalLimit < 1) {
            $configuredTotalLimit = max(1, $limit);
        }

        return [
            'customMapFunction' => '(object) => { return {...object} }',
            'endPage' => 1,
            'extendOutputFunction' => '($) => { return {} }',
            'maxItems' => $configuredTotalLimit,
            'hashtags' => $keywords,
            'proxyConfiguration' => [
                'useApifyProxy' => (bool) ($this->tiktok_use_apify_proxy ?? true),
            ],
        ];
    }

    protected function perKeywordLimit(int $totalLimit, array $keywords): int
    {
        $keywordCount = max(1, count(array_values(array_filter($keywords))));

        return max(1, (int) ceil($totalLimit / $keywordCount));
    }

    protected function sanitizeSocialKeyword(string $value): string
    {
        $value = str_replace(["'", "`", "’", "‘"], ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * TikTok hashtag normalization follows the exact Instagram hashtag rules.
     * Keep the implementation routed through the Instagram helper so both actors
     * stay aligned if hashtag cleaning changes later.
     */
    protected function normalizeTikTokHashtag(string $value): string
    {
        return $this->normalizeInstagramPayloadHashtag($value);
    }

    protected function sanitizeInstagramHashtag(string $value): string
    {
        return $this->normalizeInstagramPayloadHashtag($value);
    }

    protected function normalizeInstagramSearchKeyword(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = str_replace(["'", "’", "‘", "`"], '', $value);
        $value = trim($value, " \t\n\r\0\x0B#");
        $value = preg_replace('/[^\p{L}\p{N}\s_]+/u', '', $value) ?? $value;

        return preg_replace('/\s+/u', '', $value) ?? $value;
    }

    protected function normalizeInstagramPayloadHashtag(string $value): string
    {
        return $this->normalizeInstagramSearchKeyword($value);
    }

    public function resolveDatePayload(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $input = [];

        if ($dateFrom) {
            $input['dateFrom'] = $dateFrom;
        } elseif ($this->date_from) {
            $input['dateFrom'] = $this->date_from->format('Y-m-d');
        }

        if ($dateTo) {
            $input['dateTo'] = $dateTo;
        } elseif ($this->date_to) {
            $input['dateTo'] = $this->date_to->format('Y-m-d');
        }

        if (!isset($input['dateFrom']) && !isset($input['dateTo']) && !empty($this->range_mode)) {
            $days = match ($this->range_mode) {
                '24h' => 1,
                '7d' => 7,
                '30d' => 30,
                '90d' => 90,
                default => null,
            };

            if ($days !== null) {
                $input['dateFrom'] = now()->subDays($days)->format('Y-m-d');
                $input['dateTo'] = now()->format('Y-m-d');
            }
        }

        return $input;
    }

    protected function resolveTemplatePayload(?string $keyword = null, ?int $limit = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        if (blank($this->output_mapping)) {
            return [];
        }

        $template = json_decode($this->output_mapping, true);
        if (!is_array($template)) {
            return [];
        }

        $resolvedLimit = $limit;
        if (is_null($resolvedLimit)) {
            $resolvedLimit = (int) ($this->default_limit ?? 50);
        }

        $context = [
            'keyword' => $keyword ?: ($this->default_keyword ?? ''),
            'keyword_urlencoded' => rawurlencode($keyword ?: ($this->default_keyword ?? '')),
            'limit' => (string) $resolvedLimit,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'time_filter' => $this->resolveTimeFilter(),
            'post_time_range' => '',
        ];

        $datePayload = $this->resolveDatePayload($dateFrom, $dateTo);
        $context['date_from'] = $context['date_from'] ?: ($datePayload['dateFrom'] ?? '');
        $context['date_to'] = $context['date_to'] ?: ($datePayload['dateTo'] ?? '');

        return $this->interpolateTemplate($template, $context);
    }

    protected function resolveTimeFilter(): string
    {
        if ($this->platform === 'TikTok') {
            return match ($this->range_mode) {
                '24h', '1d' => 'YESTERDAY',
                '7d' => 'THIS_WEEK',
                '30d' => 'THIS_MONTH',
                '90d' => 'LAST_THREE_MONTHS',
                '180d' => 'LAST_SIX_MONTHS',
                'all' => 'ALL_TIME',
                default => 'THIS_WEEK',
            };
        }

        // Facebook scrapeflow / other platforms format
        return match ($this->range_mode) {
            '24h', '1d' => '24h',
            '7d' => '7d',
            '30d' => '30d',
            '90d' => '90d',
            default => '7d',
        };
    }

    protected function interpolateTemplate(mixed $value, array $context): mixed
    {
        if (is_array($value)) {
            $resolved = [];
            foreach ($value as $key => $item) {
                $resolved[$key] = $this->interpolateTemplate($item, $context);
            }

            return $resolved;
        }

        if (!is_string($value)) {
            return $value;
        }

        $resolved = preg_replace_callback('/\{([a-z_]+)\}/i', function ($matches) use ($context) {
            return $context[$matches[1]] ?? $matches[0];
        }, $value);

        if (is_string($resolved) && preg_match('/^-?\d+$/', $resolved)) {
            return (int) $resolved;
        }

        if (is_string($resolved) && in_array(Str::lower($resolved), ['true', 'false'], true)) {
            return Str::lower($resolved) === 'true';
        }

        return $resolved;
    }
}
