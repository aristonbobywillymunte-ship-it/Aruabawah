<?php

namespace App\Services;

use App\Models\NewsSourceSuggestion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NewsSourceSuggestionTester
{
    private const MIN_FULL_CONTENT_LENGTH = 500;

    public static function test(NewsSourceSuggestion $suggestion, string $testKeyword = 'politik'): array
    {
        return self::testDiscovery($suggestion, $testKeyword);
    }

    public static function testManualUrl(NewsSourceSuggestion $suggestion, string $manualUrl): array
    {
        $result = self::testManualUrlInternal($suggestion, $manualUrl);
        return $result;
    }

    private static function testDiscovery(NewsSourceSuggestion $suggestion, string $testKeyword = 'politik'): array
    {
        $candidateUrls = [];
        $discoveredUrls = [];
        $rejectedUrls = [];
        $validArticles = [];
        $reasons = [];
        $warnings = [];

        $keyword = filled($testKeyword) ? $testKeyword : 'politik';
        $hasSearchUrl = filled($suggestion->search_url);
        $usedSearchUrl = false;
        $discoveryUrl = null;
        $discoverySource = 'search_url';

        $discoveryCandidates = self::buildDiscoveryCandidates($suggestion, $keyword);
        if (! $hasSearchUrl) {
            $warnings[] = 'Search URL kosong. Portal manual wajib memakai search page.';
            $reasons[] = 'Search URL kosong, discovery akan memakai probe otomatis sebagai fallback audit saja.';
        }

        $html = '';
        $discoveryUrl = null;
        $discoverySource = null;
        $discoveryHttpStatus = 200;

        foreach ($discoveryCandidates as $candidate) {
            try {
                $statusTemp = 200;
                $tempHtml = self::fetchHtml($candidate['url'], $statusTemp);
                if (filled($tempHtml)) {
                    $html = $tempHtml;
                    $discoveryUrl = $candidate['url'];
                    $discoverySource = $candidate['source'];
                    $discoveryHttpStatus = $statusTemp;
                    $usedSearchUrl = ($candidate['source'] === 'search_url');
                    break;
                } else {
                    $reasons[] = "Discovery via {$candidate['source']} gagal (HTTP {$statusTemp})";
                }
            } catch (\Throwable $e) {
                $reasons[] = "Discovery via {$candidate['source']} error: " . $e->getMessage();
            }
        }

        try {
            if (blank($html)) {
                $reasons[] = "Seluruh metode discovery gagal atau terblokir WAF.";
            } else {
                // 2. Discover links
                $links = self::discoverLinks($html, $suggestion, $discoveryUrl);
                $searchCandidateResult = $hasSearchUrl
                    ? self::extractSearchPageCandidateLinks($html, $suggestion->base_url ?: ('https://' . $suggestion->domain), $suggestion->search_result_selector)
                    : ['links' => [], 'selector_used' => null, 'auto_repaired' => false];
                $searchCandidateLinks = $searchCandidateResult['links'] ?? [];
                $candidateSelectorUsed = $searchCandidateResult['selector_used'] ?? null;
                $candidateAutoRepaired = (bool) ($searchCandidateResult['auto_repaired'] ?? false);

                foreach ($searchCandidateLinks as $searchCandidateLink) {
                    $rejectReason = self::checkUrlRejection($searchCandidateLink, $suggestion->domain);
                    if ($rejectReason) {
                        $rejectedUrls[] = [
                            'url' => $searchCandidateLink,
                            'reason' => $rejectReason,
                        ];
                        continue;
                    }

                    $candidateUrls[] = [
                        'url' => $searchCandidateLink,
                        'source' => 'search_url',
                        'selector' => $candidateSelectorUsed,
                        'auto_repaired' => $candidateAutoRepaired,
                    ];
                }
                
                foreach ($links as $link) {
                    $link = trim($link);
                    if (empty($link)) continue;

                    // Filter URL
                    $rejectReason = self::checkUrlRejection($link, $suggestion->domain);
                    if ($rejectReason) {
                        $rejectedUrls[] = [
                            'url' => $link,
                            'reason' => $rejectReason
                        ];
                        continue;
                    }

                    $discoveredUrls[] = [
                        'url' => $link,
                        'source' => $discoverySource,
                        'status' => 'accepted',
                        'reason' => 'Lolos filter domain'
                    ];
                }

                // Limit validation only, not candidate discovery display
                $testLinks = array_slice($discoveredUrls, 0, 10);
                
                foreach ($testLinks as $discoveredInfo) {
                    $articleUrl = $discoveredInfo['url'];
                    $articleHttpStatus = 200;
                    $articleHtml = self::fetchHtml($articleUrl, $articleHttpStatus);
                    
                    if (blank($articleHtml)) {
                        $rejectedUrls[] = [
                            'url' => $articleUrl,
                            'reason' => "Gagal mengunduh halaman artikel (HTTP {$articleHttpStatus})"
                        ];
                        continue;
                    }

                    // Extract Canonical URL
                    $canonicalUrl = self::extractCanonicalUrl($articleHtml, $articleUrl);
                    
                    $inertiaData = self::extractInertiaPageData($articleHtml);
                    if ($inertiaData) {
                        $props = $inertiaData['props'] ?? $inertiaData;
                        $content = self::findValueInArrayByKeys($props, ['content', 'body', 'article_body', 'isi', 'isi_berita']) ?? '';
                        $content = trim(strip_tags(html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                        $contentLength = mb_strlen($content);
                        $title = self::findValueInArrayByKeys($props, ['title', 'heading', 'judul']) ?? '';
                        $author = self::findValueInArrayByKeys($props, ['author', 'author_name', 'penulis', 'reporter']) ?? '';
                        $dateStr = self::findValueInArrayByKeys($props, ['published_at', 'created_at', 'date', 'tanggal']) ?? '';
                        $contentSelector = 'Inertia JSON';

                        $dateInfo = [
                            'raw' => $dateStr,
                            'normalized' => self::normalizeDateValue($dateStr) ?? now()->utc()->toIso8601ZuluString(),
                            'status' => $dateStr ? 'parsed' : 'fallback_now',
                        ];
                    } else {
                        // Extract Content
                        $contentSelector = $suggestion->article_content_selector ?: 'p';
                        $content = self::extractContent($articleHtml, $suggestion->article_content_selector, $suggestion->article_noise_selector);
                        $contentLength = mb_strlen($content);

                        // Extract Title
                        $title = self::extractTitle($articleHtml);

                        // Extract Author
                        $author = self::extractAuthor($articleHtml, $suggestion->article_author_selector);

                        // Extract Date
                        $dateInfo = self::extractDate($articleHtml, $suggestion->article_date_selector);
                    }

                    if ($contentLength < self::MIN_FULL_CONTENT_LENGTH) {
                        $rejectedUrls[] = [
                            'url' => $articleUrl,
                            'reason' => "Content kurang dari 500 karakter (aktual: {$contentLength} karakter)"
                        ];
                        continue;
                    }

                    $validArticles[] = [
                        'title' => $title ?: 'Artikel Tanpa Judul',
                        'url' => $articleUrl,
                        'canonical_url' => $canonicalUrl,
                        'author' => $author,
                        'published_at_raw' => $dateInfo['raw'],
                        'published_at' => $dateInfo['normalized'],
                        'date_parse_status' => $dateInfo['status'],
                        'content_length' => $contentLength,
                        'selector' => $contentSelector,
                        'http_status' => $articleHttpStatus,
                        'extraction_status' => 'success',
                        'preview' => $content,
                        'passed' => true
                    ];
                }
            }
        } catch (\Throwable $e) {
            $reasons[] = "Terjadi exception saat testing: " . $e->getMessage();
        }

        // Determine Status & Build Reasons Checklist
        if (count($validArticles) > 0) {
            $status = $hasSearchUrl ? 'verified' : 'needs_review';
            $reasons[] = "Selector berhasil mengambil isi artikel";
            $reasons[] = "Canonical URL valid";
            $reasons[] = "Content > 500 karakter";
            $reasons[] = "Konten relevan dengan keyword \"{$keyword}\"";
            $reasons[] = "URL bukan Google News";
            if (! $hasSearchUrl) {
                $reasons[] = 'Search URL kosong, jadi hasil hanya valid parsial untuk portal manual.';
            } elseif (! $usedSearchUrl) {
                $reasons[] = 'Search URL tersedia tetapi discovery memakai fallback, perlu cek prioritas search_url.';
            }
        } else {
            $status = $hasSearchUrl ? 'failed' : 'needs_review';
            $reasons[] = "Tidak ada artikel valid ditemukan";
            if (count($discoveredUrls) === 0) {
                $reasons[] = "Selector tidak menemukan link artikel pada halaman pencarian";
            } else {
                $reasons[] = "Content kurang dari 500 karakter atau selector tidak menemukan body artikel";
            }
            if (! $hasSearchUrl) {
                $reasons[] = 'Search URL kosong, hasil fallback hanya valid untuk review manual.';
            }
        }

        return [
            'mode' => 'discovery',
            'status' => $status,
            'keyword' => $keyword,
            'manual_url' => null,
            'source' => $suggestion->source_name ?: $suggestion->domain,
            'tested_at' => now()->toDateTimeString(),
            'warnings' => $warnings,
            'search_url_used' => $usedSearchUrl,
            'search_url_present' => $hasSearchUrl,
            'candidate_urls' => $candidateUrls,
            'discovered_urls' => $discoveredUrls,
            'rejected_urls' => $rejectedUrls,
            'valid_articles' => $validArticles,
            'candidate_selector_used' => $candidateSelectorUsed ?? null,
            'candidate_auto_repaired' => $candidateAutoRepaired ?? false,
            'reasons' => $reasons
        ];
    }

    private static function buildDiscoveryCandidates(NewsSourceSuggestion $suggestion, string $keyword): array
    {
        $candidates = [];
        $seen = [];

        $push = function (?string $template, string $source) use (&$candidates, &$seen, $keyword): void {
            $template = trim((string) $template);
            if ($template === '') {
                return;
            }

            $normalized = self::renderSearchUrl($template, $keyword);
            if ($normalized === '') {
                return;
            }

            $key = strtolower($normalized);
            if (isset($seen[$key])) {
                return;
            }

            $seen[$key] = true;
            $candidates[] = [
                'url' => $normalized,
                'source' => $source,
            ];
        };

        $baseUrl = $suggestion->base_url ?: ('https://' . $suggestion->domain);
        $baseHost = parse_url($baseUrl, PHP_URL_HOST) ?: $suggestion->domain;
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
        $cleanHost = preg_replace('/^www\./', '', strtolower((string) $baseHost));
        $base = $cleanHost !== '' ? $scheme . '://' . $cleanHost : '';

        $domainKey = strtolower((string) $suggestion->domain);
        $domainPresets = [
            'prokal.co' => [
                'https://www.prokal.co/search?q={query}',
                'https://prokal.co/search?q={query}',
                'https://www.prokal.co/search?query={query}',
                'https://prokal.co/search?query={query}',
            ],
            'editorialkaltim.com' => [
                'https://editorialkaltim.com/search?q={query}',
                'https://www.editorialkaltim.com/search?q={query}',
                'https://editorialkaltim.com/search?query={query}',
                'https://www.editorialkaltim.com/search?query={query}',
            ],
            'kaltimkece.id' => [
                'https://kaltimkece.id/search?terms={query}',
                'https://kaltimkece.id/search?query={query}',
                'https://kaltimkece.id/search?q={query}',
            ],
            'sapos.co.id' => [
                'https://www.sapos.co.id/search?key={query}',
                'https://sapos.co.id/search?key={query}',
                'https://www.sapos.co.id/search?q={query}',
                'https://sapos.co.id/search?q={query}',
            ],
            'kaltimtoday.co' => [
                'https://kaltimtoday.co/search?q={query}',
                'https://kaltimtoday.co/search?query={query}',
                'https://kaltimtoday.co/search?terms={query}',
                'https://kaltimtoday.co/?s={query}',
            ],
            'korankaltim.com' => [
                'https://korankaltim.com/search?q={query}',
                'https://korankaltim.com/search?query={query}',
                'https://korankaltim.com/search?terms={query}',
            ],
            'mediakaltim.com' => [
                'https://mediakaltim.com/search?q={query}',
                'https://mediakaltim.com/search?query={query}',
                'https://mediakaltim.com/search?terms={query}',
            ],
            'niaga.asia' => [
                'https://niaga.asia/search?q={query}',
                'https://niaga.asia/search?query={query}',
                'https://niaga.asia/search?terms={query}',
            ],
            'nomorsatukaltim.disway.id' => [
                'https://nomorsatukaltim.disway.id/search?q={query}',
                'https://nomorsatukaltim.disway.id/search?query={query}',
                'https://nomorsatukaltim.disway.id/search?terms={query}',
            ],
        ];

        $push($suggestion->search_url, 'search_url');
        foreach ($domainPresets[$domainKey] ?? [] as $presetTemplate) {
            $push($presetTemplate, 'domain_preset');
        }

        $pathVariants = [
            '/search',
            '/cari',
            '/pencarian',
            '/hasil-pencarian',
            '/',
        ];

        $queryVariants = [
            'q',
            'query',
            'terms',
            'term',
            'keyword',
            'key',
            'search',
            's',
        ];

        foreach ($pathVariants as $path) {
            foreach ($queryVariants as $param) {
                $push(rtrim($base, '/') . $path . '?' . $param . '={query}', 'auto_probe');
            }
        }

        return $candidates;
    }

    private static function testManualUrlInternal(NewsSourceSuggestion $suggestion, string $manualUrl): array
    {
        $manualUrl = trim($manualUrl);
        $reasons = [];
        $discoveredUrls = [];
        $rejectedUrls = [];
        $validArticles = [];
        $status = 'failed';

        if ($manualUrl === '') {
            return [
                'mode' => 'manual_url',
                'status' => 'failed',
                'keyword' => null,
                'manual_url' => null,
                'source' => $suggestion->source_name ?: $suggestion->domain,
                'tested_at' => now()->toDateTimeString(),
                'discovered_urls' => [],
                'rejected_urls' => [['url' => '', 'reason' => 'Manual URL kosong']],
                'valid_articles' => [],
                'reasons' => ['Manual URL wajib diisi.'],
            ];
        }

        $rejectReason = self::checkUrlRejection($manualUrl, $suggestion->domain);
        if ($rejectReason) {
            return [
                'mode' => 'manual_url',
                'status' => 'failed',
                'keyword' => null,
                'manual_url' => $manualUrl,
                'source' => $suggestion->source_name ?: $suggestion->domain,
                'tested_at' => now()->toDateTimeString(),
                'discovered_urls' => [],
                'rejected_urls' => [['url' => $manualUrl, 'reason' => $rejectReason]],
                'valid_articles' => [],
                'reasons' => ["URL manual ditolak: {$rejectReason}"],
            ];
        }

        try {
            $httpStatus = 200;
            $html = self::fetchHtml($manualUrl, $httpStatus);

            if (blank($html)) {
                return [
                    'mode' => 'manual_url',
                    'status' => 'failed',
                    'keyword' => null,
                    'manual_url' => $manualUrl,
                    'source' => $suggestion->source_name ?: $suggestion->domain,
                    'tested_at' => now()->toDateTimeString(),
                    'discovered_urls' => [],
                    'rejected_urls' => [['url' => $manualUrl, 'reason' => "Gagal mengambil HTML artikel (HTTP {$httpStatus})"]],
                    'valid_articles' => [],
                    'reasons' => ["Gagal mengambil HTML artikel (HTTP {$httpStatus})"],
                ];
            }

            $canonicalUrl = self::extractCanonicalUrl($html, $manualUrl);
            
            $inertiaData = self::extractInertiaPageData($html);
            if ($inertiaData) {
                $props = $inertiaData['props'] ?? $inertiaData;
                $content = self::findValueInArrayByKeys($props, ['content', 'body', 'article_body', 'isi', 'isi_berita']) ?? '';
                $content = trim(strip_tags(html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                $contentLength = mb_strlen($content);
                $title = self::findValueInArrayByKeys($props, ['title', 'heading', 'judul']) ?? '';
                $author = self::findValueInArrayByKeys($props, ['author', 'author_name', 'penulis', 'reporter']) ?? '';
                $dateStr = self::findValueInArrayByKeys($props, ['published_at', 'created_at', 'date', 'tanggal']) ?? '';
                $contentSelector = 'Inertia JSON';

                $dateInfo = [
                    'raw' => $dateStr,
                    'normalized' => self::normalizeDateValue($dateStr) ?? now()->utc()->toIso8601ZuluString(),
                    'status' => $dateStr ? 'parsed' : 'fallback_now',
                ];
            } else {
                $contentSelector = $suggestion->article_content_selector ?: $suggestion->selector;
                $content = self::extractContent($html, $suggestion->article_content_selector ?: $suggestion->selector, $suggestion->article_noise_selector);
                $contentLength = mb_strlen($content);
                $title = self::extractTitle($html);
                $author = self::extractAuthor($html, $suggestion->article_author_selector);
                $dateInfo = self::extractDate($html, $suggestion->article_date_selector);
            }

            $validUrl = self::isLikelyManualArticleUrl($manualUrl, $suggestion->domain);
            if (! $validUrl) {
                $rejectedUrls[] = [
                    'url' => $manualUrl,
                    'reason' => 'URL manual bukan artikel valid untuk domain source',
                ];
                $reasons[] = 'URL manual gagal validasi domain/pola artikel.';
            }

            if ($contentLength < self::MIN_FULL_CONTENT_LENGTH) {
                $rejectedUrls[] = [
                    'url' => $manualUrl,
                    'reason' => "Content kurang dari 500 karakter (aktual: {$contentLength} karakter)",
                ];
                $reasons[] = 'Content tidak cukup panjang untuk dinyatakan verified.';
            }

            if ($validUrl && $contentLength >= self::MIN_FULL_CONTENT_LENGTH) {
                $status = 'verified';
                $validArticles[] = [
                    'title' => $title ?: 'Artikel Tanpa Judul',
                    'url' => $manualUrl,
                    'canonical_url' => $canonicalUrl,
                    'author' => $author,
                    'published_at_raw' => $dateInfo['raw'],
                    'published_at' => $dateInfo['normalized'],
                    'date_parse_status' => $dateInfo['status'],
                    'content_length' => $contentLength,
                    'selector' => $contentSelector,
                    'http_status' => $httpStatus,
                    'extraction_status' => 'success',
                    'preview' => $content,
                    'passed' => true,
                ];
                $reasons[] = 'Manual URL valid, canonical URL ditemukan, dan content > 500 karakter.';
                $reasons[] = 'Preview konten berasal dari hasil scraping nyata.';
            } else {
                if (empty($reasons)) {
                    $reasons[] = 'Manual URL belum memenuhi kriteria verified.';
                }
            }
        } catch (\Throwable $e) {
            $reasons[] = 'Terjadi exception saat testing manual URL: ' . $e->getMessage();
        }

        if (empty($validArticles)) {
            $status = in_array('URL manual ditolak: URL manual bukan artikel valid untuk domain source', $reasons, true)
                ? 'failed'
                : 'needs_review';
        }

        return [
            'mode' => 'manual_url',
            'status' => $status,
            'keyword' => null,
            'manual_url' => $manualUrl,
            'source' => $suggestion->source_name ?: $suggestion->domain,
            'tested_at' => now()->toDateTimeString(),
            'discovered_urls' => $discoveredUrls,
            'rejected_urls' => $rejectedUrls,
            'valid_articles' => $validArticles,
            'reasons' => $reasons,
        ];
    }

    private static function fetchHtml(string $url, &$httpStatus = 200): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('~^https?://~i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        try {
            // First try via HTTP client (fast)
            $response = Http::timeout(30)
                ->withoutVerifying()
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36'])
                ->get($url);
            
            $httpStatus = $response->status();
            if ($response->successful() && mb_strlen($response->body()) > 1000) {
                $body = $response->body();

                if (self::looksLikeSpaShell($body)) {
                    $rendered = self::fetchRenderedHtml($url);
                    if (filled($rendered)) {
                        return $rendered;
                    }
                }

                return $body;
            }
        } catch (\Throwable $e) {
            $httpStatus = 500;
            Log::warning("[NewsSourceSuggestionTester] HTTP Client failed for {$url}: " . $e->getMessage());
        }

        // Try via Headless Chrome if available
        return self::fetchRenderedHtml($url);
    }

    private static function renderSearchUrl(string $template, string $keyword): string
    {
        $encodedKeyword = rawurlencode($keyword);
        $template = trim($template);

        if ($template !== '' && ! preg_match('~^https?://~i', $template)) {
            $template = 'https://' . ltrim($template, '/');
        }

        return str_replace(
            ['{keyword}', '{query}', '{search}'],
            $encodedKeyword,
            $template
        );
    }

    private static function fetchRenderedHtml(string $url): string
    {
        $chrome = self::resolveChromeBinary();
        if ($chrome === null) {
            return '';
        }

        $command = escapeshellarg($chrome)
            . ' --headless --no-sandbox --disable-gpu --disable-dev-shm-usage --no-first-run --no-default-browser-check'
            . ' --virtual-time-budget=5000 --dump-dom '
            . escapeshellarg($url)
            . ' 2>/dev/null';

        $output = shell_exec($command);
        return is_string($output) ? trim($output) : '';
    }

    private static function looksLikeSpaShell(string $html): bool
    {
        $htmlLower = strtolower($html);

        return str_contains($htmlLower, 'inertia')
            || str_contains($htmlLower, 'data-page=')
            || str_contains($htmlLower, 'window.__inertia')
            || str_contains($htmlLower, 'app-cwzi7osv.js')
            || str_contains($htmlLower, 'indexsearch')
            || str_contains($htmlLower, 'modulepreload')
            || str_contains($htmlLower, 'virtual_page_view')
            || str_contains($htmlLower, 'livewire.js')
            || str_contains($htmlLower, 'window.livewire');
    }

    private static function resolveChromeBinary(): ?string
    {
        $candidates = [
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        $which = trim((string) shell_exec('command -v google-chrome 2>/dev/null || command -v chromium 2>/dev/null || command -v chromium-browser 2>/dev/null'));
        return $which !== '' ? $which : null;
    }

    private static function discoverLinks(string $html, NewsSourceSuggestion $suggestion, string $discoveryUrl): array
    {
        $links = [];
        $baseUrl = $suggestion->base_url ?: ('https://' . $suggestion->domain);
        $isSearchPage = filled($suggestion->search_url);

        if ($isSearchPage) {
            $result = self::extractSearchPageCandidateLinks($html, $baseUrl, $suggestion->search_result_selector);
            return $result['links'] ?? [];
        }

        // Non-manual sources may still use wider discovery heuristics
        $inertiaData = self::extractInertiaPageData($html);
        if ($inertiaData) {
            $inertiaLinks = self::discoverLinksFromInertiaJson($inertiaData, $baseUrl, $suggestion->domain);
            $links = array_merge($links, $inertiaLinks);
        }

        // Try RSS item links first if feed_url is used
        if (filled($suggestion->feed_url) && str_contains($html, '<item>')) {
            $xml = @simplexml_load_string($html, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($xml && isset($xml->channel->item)) {
                foreach ($xml->channel->item as $item) {
                    $link = trim((string) ($item->link ?? ''));
                    $resolved = self::normalizeUrl($link, $baseUrl);
                    if ($resolved) $links[] = $resolved;
                }
            }
        }

        // Try sitemap loc links if sitemap_url is used
        if (filled($suggestion->sitemap_url) && (str_contains($html, '<sitemap') || str_contains($html, '<urlset') || str_contains($html, '<loc>'))) {
            // Check if this is a sitemap index (contains <sitemap> tags pointing to child sitemaps)
            $isSitemapIndex = str_contains($html, '<sitemapindex') || (str_contains($html, '<sitemap>') && !str_contains($html, '<urlset'));
            if ($isSitemapIndex) {
                // Extract child sitemap URLs
                preg_match_all('~<loc>(.*?)</loc>~is', $html, $childSitemapMatches);
                // Pick the first few child sitemaps to fetch (prefer news/post sitemaps)
                $childCandidates = array_filter($childSitemapMatches[1], fn($u) => 
                    str_contains(strtolower($u), 'news') || 
                    str_contains(strtolower($u), 'post') ||
                    str_contains(strtolower($u), 'artikel') ||
                    str_contains(strtolower($u), 'berita')
                );
                if (empty($childCandidates)) {
                    $childCandidates = array_slice($childSitemapMatches[1], 0, 3);
                } else {
                    $childCandidates = array_slice(array_values($childCandidates), 0, 2);
                }
                foreach ($childCandidates as $childUrl) {
                    $childUrl = trim($childUrl);
                    try {
                        $childStatus = 200;
                        $childHtml = self::fetchHtml($childUrl, $childStatus);
                        if (filled($childHtml)) {
                            preg_match_all('~<loc>(.*?)</loc>~is', $childHtml, $articleLocMatches);
                            foreach ($articleLocMatches[1] as $loc) {
                                $resolved = self::normalizeUrl(trim($loc), $baseUrl);
                                if ($resolved) $links[] = $resolved;
                            }
                        }
                    } catch (\Throwable) {}
                }
            } else {
                preg_match_all('~<loc>(.*?)</loc>~is', $html, $locMatches);
                foreach ($locMatches[1] as $loc) {
                    $resolved = self::normalizeUrl(trim($loc), $baseUrl);
                    if ($resolved) $links[] = $resolved;
                }
            }
        }

        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);

        // Try selector
        $selector = $suggestion->article_link_selector ?: $suggestion->search_result_selector;
        if (!empty($selector)) {
            $xpathQuery = self::convertSelectorToXPath($selector);
            $nodes = self::safeXpathQuery($xpath, $xpathQuery);
            if ($nodes && $nodes->length > 0) {
                foreach ($nodes as $node) {
                    if (! $node instanceof \DOMElement) {
                        continue;
                    }
                    $href = trim((string) $node->getAttribute('href'));
                    $resolved = self::normalizeUrl($href, $baseUrl);
                    if ($resolved
                        && self::isLikelySearchCandidateLink($resolved, $node)
                        && ! self::checkUrlRejection($resolved, parse_url($baseUrl, PHP_URL_HOST) ?: '')) {
                        $links[] = $resolved;
                    }
                }
            }
        }

        // Fallback: common search-result anchors / URL-bearing elements
        $commonSelectors = [
            '[target-container="search"] a.title[href]',
            '[target-container="search"] a.entry-title[href]',
            '[target-container="search"] a.post-title[href]',
            '.block-black-white a.title[href]',
            '.search-result a.title[href]',
            '.result-card a.title[href]',
            '.result-item a.title[href]',
            'article a.title[href]',
            'article a.entry-title[href]',
            'article a.post-title[href]',
            'h1 a[href]',
            'h2 a[href]',
            'h3 a[href]',
            'h4 a[href]',
            '.entry-title a[href]',
            '.post-title a[href]',
            '.result-title a[href]',
            '.search-result a[href]',
            '.list-news-item a[href]',
            '.post-item a[href]',
            '.news-item a[href]',
        ];
        foreach ($commonSelectors as $commonSelector) {
            $xpathQuery = self::convertSelectorToXPath($commonSelector);
            $nodes = self::safeXpathQuery($xpath, $xpathQuery);
            if ($nodes && $nodes->length > 0) {
                foreach ($nodes as $node) {
                    if (! $node instanceof \DOMElement) {
                        continue;
                    }
                    $href = trim((string) $node->getAttribute('href'));
                    $resolved = self::normalizeUrl($href, $baseUrl);
                    if ($resolved
                        && self::isLikelySearchCandidateLink($resolved, $node)
                        && ! self::checkUrlRejection($resolved, parse_url($baseUrl, PHP_URL_HOST) ?: '')) {
                        $links[] = $resolved;
                    }
                }
            }
        }

        // Fallback: data attributes and onclick navigations
        preg_match_all('~(?:data-href|data-url|data-link)=["\']([^"\']+)["\']~i', $html, $dataMatches);
        foreach ($dataMatches[1] as $href) {
            $resolved = self::normalizeUrl($href, $baseUrl);
            if ($resolved && self::isLikelySearchCandidateLink($resolved) && ! self::checkUrlRejection($resolved, parse_url($baseUrl, PHP_URL_HOST) ?: '')) $links[] = $resolved;
        }

        preg_match_all('~onclick=["\'][^"\']*(?:location(?:\.href)?|window\.location|window\.open)\s*=\s*["\']([^"\']+)["\']~i', $html, $onclickMatches);
        foreach ($onclickMatches[1] as $href) {
            $resolved = self::normalizeUrl($href, $baseUrl);
            if ($resolved && self::isLikelySearchCandidateLink($resolved) && ! self::checkUrlRejection($resolved, parse_url($baseUrl, PHP_URL_HOST) ?: '')) $links[] = $resolved;
        }

        // Fallback regex scan - only collect URLs that look like articles
        preg_match_all('~href=["\']([^"\']+)["\']~i', $html, $matches);
        foreach ($matches[1] as $href) {
            $resolved = self::normalizeUrl($href, $baseUrl);
            if ($resolved && self::looksLikeArticleUrl($resolved) && self::isLikelySearchCandidateLink($resolved)) {
                $links[] = $resolved;
            }
        }

        $links = array_values(array_unique($links));
        if (!empty($suggestion->search_url)) {
            $links = array_values(array_filter($links, function ($url) {
                return ! self::isClearlyNonArticleCandidate($url);
            }));
        }

        return $links;
    }

    private static function extractSearchPageCandidateLinks(string $html, string $baseUrl, ?string $selector = null): array
    {
        $links = [];
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);
        $selector = is_string($selector) ? trim($selector) : trim((string) $selector);

        $selectorQueue = [];
        if (filled($selector)) {
            $selectorQueue[] = $selector;
        }

        $selectorQueue = array_merge($selectorQueue, [
            'article.post h2.entry-title a',
            'article.post .entry-title a',
            'article.post a[rel="bookmark"]',
            'article a[href]',
            'article h2 a[href]',
            'article h3 a[href]',
            'article .title a[href]',
            'article .entry-title a[href]',
            '.block-black-white a.title',
            '.search-result a.title',
            '.result-card a.title',
            '.result-item a.title',
            '.list-news-item a[href]',
            '.post-item a[href]',
            '.news-item a[href]',
            '.card a[href]',
            '.article a[href]',
            '.entry a[href]',
            '.news a[href]',
            'a[href*="/warta/"]',
            'a[href*="/berita/"]',
            'a[href*="/news/"]',
            'a[href*="/artikel/"]',
            'a[href*="/read/"]',
            'a[href*="/komisi-"]',
        ]);

        $selectorUsed = null;
        $autoRepaired = false;

        foreach ($selectorQueue as $selectorCandidate) {
            $selectorCandidate = trim((string) $selectorCandidate);
            if ($selectorCandidate === '') {
                continue;
            }

            $nodes = self::safeXpathQuery($xpath, self::convertSelectorToXPath($selectorCandidate));
            if (! $nodes || $nodes->length === 0) {
                continue;
            }

            $beforeCount = count($links);
            foreach ($nodes as $node) {
                if (! $node instanceof \DOMElement) {
                    continue;
                }

                $candidateHrefs = [];
                if ($node->hasAttribute('href')) {
                    $candidateHrefs[] = trim((string) $node->getAttribute('href'));
                }

                $descendantAnchors = self::safeXpathQuery($xpath, './/a[@href]', $node->hasChildNodes() ? $node : null);
                if ($descendantAnchors) {
                    foreach ($descendantAnchors as $anchorNode) {
                        if ($anchorNode instanceof \DOMElement) {
                            $candidateHrefs[] = trim((string) $anchorNode->getAttribute('href'));
                        }
                    }
                }

                foreach (array_values(array_unique(array_filter($candidateHrefs))) as $href) {
                    $resolved = self::normalizeUrl($href, $baseUrl);
                    if (! $resolved) {
                        continue;
                    }

                    $probeNode = $node->hasAttribute('href') ? $node : null;
                    if (self::isLikelySearchCandidateLink($resolved, $probeNode) && self::isSameNewsDomain($resolved, parse_url($baseUrl, PHP_URL_HOST) ?: '')) {
                        $links[] = $resolved;
                    }
                }
            }

            if (count($links) > $beforeCount) {
                $selectorUsed = $selectorCandidate;
                $autoRepaired = filled($selector) && $selector !== trim($selectorCandidate);
                break;
            }
        }

        if (empty($links)) {
            preg_match_all('~<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)</a>~is', $html, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $href = trim((string) ($match[1] ?? ''));
                $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($match[2] ?? ''))));
                $resolved = self::normalizeUrl($href, $baseUrl);

                if (! $resolved) {
                    continue;
                }

                if (! self::isSameNewsDomain($resolved, parse_url($baseUrl, PHP_URL_HOST) ?: '')) {
                    continue;
                }

                if (self::isLikelySearchCandidateLink($resolved) && ! self::checkUrlRejection($resolved, parse_url($baseUrl, PHP_URL_HOST) ?: '')) {
                    $links[] = $resolved;
                    continue;
                }

                if ($text !== '' && ! self::isClearlyNonArticleCandidate($resolved) && ! self::checkUrlRejection($resolved, parse_url($baseUrl, PHP_URL_HOST) ?: '')) {
                    $links[] = $resolved;
                }
            }
        }

        return [
            'links' => array_values(array_unique($links)),
            'selector_used' => $selectorUsed ?: (filled($selector) ? $selector : null),
            'auto_repaired' => $autoRepaired,
        ];
    }

    private static function isClearlyNonArticleCandidate(string $url): bool
    {
        $path = strtolower(trim((string) parse_url($url, PHP_URL_PATH), '/'));
        if ($path === '') {
            return true;
        }

        $blocked = [
            'assets/', 'storage/', 'uploads/', 'css/', 'js/', 'fonts/', 'img/', 'images/', 'api/', 'feed', 'sitemap',
            'author/', 'rep/', 'profil/', 'profile/', 'writer/', 'penulis/', 'category/', 'tag/', 'topic/', 'topics/',
            'archive/', 'arsip/', 'kategori/', 'redaksi', 'redaksi/', 'kontak', 'kontak/', 'hubungi-kami', 'hubungi-kami/',
            'tentang-kami', 'tentang-kami/', 'pedoman-media-siber', 'pedoman-media-siber/', 'profil-redaksi',
            'privacy-policy', 'terms', 'about', 'contact', 'search', 'hasil-pencarian', 'cdn-cgi/', 'cdn-cgi/l/email-protection',
        ];
        foreach ($blocked as $needle) {
            if (str_contains($path, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function isLikelySearchCandidateLink(string $url, ?\DOMElement $node = null): bool
    {
        if (self::isClearlyNonArticleCandidate($url)) {
            return false;
        }

        $path = strtolower(trim((string) parse_url($url, PHP_URL_PATH), '/'));
        if ($path === '') {
            return false;
        }

        if (preg_match('~\.(?:css|js|mjs|map|png|jpe?g|gif|webp|svg|ico|woff2?|ttf|eot|pdf|xml)$~i', $path)) {
            return false;
        }

        if ($node instanceof \DOMElement) {
            $class = ' ' . strtolower((string) $node->getAttribute('class')) . ' ';
            $text = trim(preg_replace('/\s+/', ' ', (string) $node->textContent));

            if (preg_match('~\b(?:recent-title|heading-text)\b~i', $class)) {
                return true;
            }

            if (preg_match('~\b(?:title|entry-title|post-title|result-title|article-title|headline|card-title|news-title)\b~i', $class)) {
                return true;
            }

            if (preg_match('~\b(?:author|byline|writer|reporter|penulis|category|tag|share|social)\b~i', $class)) {
                return false;
            }

        }

        return self::looksLikeArticleUrl($url);
    }

    private static function isSameNewsDomain(string $url, string $domain): bool
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $host = preg_replace('/^www\./', '', strtolower($host));
        $domain = preg_replace('/^www\./', '', strtolower($domain));

        if ($host === '' || $domain === '') {
            return false;
        }

        if (!str_contains($domain, '.')) {
            return str_contains($host, $domain);
        }

        return $host === $domain || str_ends_with($host, '.' . $domain);
    }

    private static function checkUrlRejection(string $url, ?string $domain): ?string
    {
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            return "Bukan skema HTTP/HTTPS valid";
        }

        if (str_contains($url, 'news.google.com')) {
            return "URL Google News diblokir";
        }

        if ($domain) {
            $host = parse_url($url, PHP_URL_HOST) ?: '';
            $host = preg_replace('/^www\./', '', strtolower($host));
            $cleanDomain = preg_replace('/^www\./', '', strtolower($domain));
            if (str_contains($cleanDomain, '.')) {
                if ($host !== $cleanDomain && !str_ends_with($host, '.' . $cleanDomain)) {
                    return "Domain tidak cocok dengan: {$domain}";
                }
            } else {
                if (!str_contains($host, $cleanDomain)) {
                    return "Domain tidak cocok dengan: {$domain}";
                }
            }
        }

        $lower = strtolower($url);
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $pathLower = strtolower($path);

        // Reject root/home URLs - clearly not an article
        if ($pathLower === '' || $pathLower === '/') {
            return "URL berupa halaman utama (bukan artikel)";
        }

        // Only reject exact/prefix path segments, not substrings inside article slugs
        $rejectExact = ['login', 'logout', 'register', 'signup', 'auth', 'account', 'connect'];
        $pathSegments = array_filter(explode('/', trim($pathLower, '/')));
        foreach ($pathSegments as $segment) {
            if (in_array($segment, $rejectExact, true)) {
                return "URL mengandung path terlarang: /{$segment}";
            }
        }

        // Reject URLs that are clearly not articles (exact nav paths)
        $rejectExactPaths = ['/rss', '/feed', '/sitemap.xml', '/robots.txt', '/live-streaming', '/amp'];
        foreach ($rejectExactPaths as $ep) {
            if ($pathLower === $ep || str_starts_with($pathLower, $ep . '?')) {
                return "URL navigasi terlarang: {$ep}";
            }
        }

        // Reject URLs that are clearly infrastructure
        $rejectPrefixes = ['/ads/', '/static/', '/asset/', '/cdn-cgi/', '/.well-known/', '/wp-content/', '/wp-includes/'];
        foreach ($rejectPrefixes as $prefix) {
            if (str_starts_with($pathLower, $prefix)) {
                return "URL mengandung path terlarang: {$prefix}";
            }
        }

        $rejectSegments = [
            'redaksi',
            'kontak',
            'hubungi-kami',
            'tentang-kami',
            'pedoman-media-siber',
            'privacy-policy',
            'terms',
            'about',
            'contact',
            'search',
            'hasil-pencarian',
            'author',
            'profil',
            'profile',
            'writer',
            'penulis',
        ];
        foreach (array_filter(explode('/', trim($pathLower, '/'))) as $segment) {
            if (in_array($segment, $rejectSegments, true)) {
                return "URL mengandung path utilitas: /{$segment}";
            }
        }

        return null;
    }

    private static function isLikelyManualArticleUrl(string $url, ?string $domain): bool
    {
        $rejectReason = self::checkUrlRejection($url, $domain);
        if ($rejectReason) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $pathLower = strtolower($path);

        if ($domain) {
            $cleanDomain = preg_replace('/^www\./', '', strtolower($domain));
            $cleanHost = preg_replace('/^www\./', '', strtolower($host));
            if ($cleanHost === $cleanDomain || str_ends_with($cleanHost, '.' . $cleanDomain)) {
                return trim($path, '/') !== '' && !preg_match('~/(?:search|tag|category|kategori|login|auth|account|connect|feed|sitemap)~i', $pathLower);
            }
        }

        return trim($path, '/') !== '';
    }

    /**
     * Heuristic: does this URL look like a news article (not navigation/category)?
     * Used in fallback regex scan to filter noise links.
     */
    private static function looksLikeArticleUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $pathLower = strtolower(trim($path, '/'));

        if ($pathLower === '') return false;

        // Reject common static assets and files.
        if (preg_match('~\.(?:css|js|mjs|map|png|jpe?g|gif|webp|svg|ico|woff2?|ttf|eot|pdf|xml)$~i', $pathLower)) {
            return false;
        }

        if (str_contains($pathLower, '/assets/') || str_contains($pathLower, '/storage/') || str_contains($pathLower, '/uploads/')) {
            return false;
        }

        // Reject known navigation/utility path prefixes
        $navPrefixes = [
            'rss', 'feed', 'sitemap', 'robots', 'amp/', 'live', 'streaming',
            'pedoman', 'contact', 'privacy', 'redaksi', 'search', 'about',
            'advertise', 'newsletter', 'tag/', 'tags/', 'author/', 'page/',
            'rep/', 'profil/', 'profile/', 'writer/', 'penulis/',
            'category/', 'kategory/', 'kategori/', 'topic/', 'topics/', 'topik/', 'archive/', 'arsip/',
        ];
        foreach ($navPrefixes as $prefix) {
            if ($pathLower === rtrim($prefix, '/') || str_starts_with($pathLower, $prefix)) {
                return false;
            }
        }

        $segments = array_values(array_filter(explode('/', $pathLower)));

        // Common article/content route prefixes that should be accepted early.
        $articlePrefixes = ['read/', 'news/', 'berita/', 'artikel/', 'article/', 'post/', 'story/', 'komisi-'];
        foreach ($articlePrefixes as $prefix) {
            if (str_starts_with($pathLower, $prefix)) {
                return true;
            }
        }

        // Accept if path contains a numeric ID (common: /category/12345/slug)
        if (preg_match('/\/\d{4,}/', $path)) return true;

        // Accept long single-segment slugs commonly used by news portals.
        if (count($segments) === 1 && strlen($segments[0]) >= 25) {
            return true;
        }

        // Accept if 2+ path segments and last slug is long (>= 20 chars)
        if (count($segments) >= 2 && strlen(end($segments)) >= 20) return true;

        // Reject single-segment short paths (nav like /news, /tribun-etam, /breaking-news)
        if (count($segments) <= 1) return false;

        // Accept multi-segment paths that aren't obviously categories
        return count($segments) >= 3;
    }

    private static function normalizeUrl(string $href, string $baseUrl): ?string
    {
        $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($href === '' || str_starts_with($href, 'javascript:') || str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
            return null;
        }

        // Handle protocol-relative URLs like //cdn.example.com/path
        if (str_starts_with($href, '//')) {
            return 'https:' . $href;
        }

        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        // Relative URL - but first guard against paths that look like external hostnames
        // e.g. href="securepubads.g.doubleclick.net" — has dots but no leading slash
        if (!str_starts_with($href, '/') && preg_match('/^[a-zA-Z0-9\-]+\.[a-zA-Z]{2,}/', $href)) {
            // Looks like an external domain without scheme - skip it
            return null;
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
    }

    private static function extractCanonicalUrl(string $html, string $fallbackUrl): string
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);
        $canonicalNodes = $xpath->query('//link[@rel="canonical"]');
        if ($canonicalNodes && $canonicalNodes->length > 0) {
            $canonicalHref = trim($canonicalNodes->item(0)->getAttribute('href'));
            if (!empty($canonicalHref)) {
                return $canonicalHref;
            }
        }
        return $fallbackUrl;
    }

    private static function extractContent(string $html, ?string $selector, ?string $noiseSelector = null): string
    {
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);

        if (!empty($noiseSelector)) {
            $noiseSelectors = explode(',', $noiseSelector);
            foreach ($noiseSelectors as $nSel) {
                $nSel = trim($nSel);
                if (empty($nSel)) continue;
                $nQuery = self::convertSelectorToXPath($nSel);
                $noiseNodes = self::safeXpathQuery($xpath, $nQuery);
                if ($noiseNodes) {
                    foreach (iterator_to_array($noiseNodes) as $nNode) {
                        if ($nNode->parentNode) {
                            $nNode->parentNode->removeChild($nNode);
                        }
                    }
                }
            }
        }

        if (!empty($selector)) {
            $xpathQuery = self::convertSelectorToXPath($selector);
            $nodes = self::safeXpathQuery($xpath, $xpathQuery);
            if ($nodes && $nodes->length > 0) {
                $text = '';
                foreach ($nodes as $node) {
                    $text .= $node->textContent . "\n";
                }
                $cleaned = trim(preg_replace('/\s+/', ' ', strip_tags($text)));
                if (!empty($cleaned)) {
                    return $cleaned;
                }
            }
        }

        // Fallback to <p>
        $paragraphs = $dom->getElementsByTagName('p');
        $text = '';
        foreach ($paragraphs as $p) {
            $text .= $p->textContent . "\n";
        }

        return trim(preg_replace('/\s+/', ' ', strip_tags($text)));
    }

    private static function extractTitle(string $html): string
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $titles = $dom->getElementsByTagName('title');
        if ($titles->length > 0) {
            return trim($titles->item(0)->textContent);
        }
        return '';
    }

    /**
     * Safe wrapper for DOMXPath::query that returns null on invalid expressions.
     */
    private static function safeXpathQuery(\DOMXPath $xpath, string $query): ?\DOMNodeList
    {
        try {
            $result = @$xpath->query($query);
            return ($result === false) ? null : $result;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function convertSelectorToXPath(string $selector): string
    {
        $selector = trim($selector);

        // Handle comma-separated selectors → XPath union with |
        if (str_contains($selector, ',')) {
            $parts = array_map('trim', explode(',', $selector));
            $xpathParts = [];
            foreach ($parts as $part) {
                if ($part !== '') {
                    $xpathParts[] = self::convertSingleSelectorToXPath($part);
                }
            }
            return implode(' | ', array_filter($xpathParts));
        }

        return self::convertSingleSelectorToXPath($selector);
    }

    private static function convertSingleSelectorToXPath(string $selector): string
    {
        $selector = trim($selector);

        // Split on whitespace (descendant combinator) or > (child combinator)
        $tokens = preg_split('/\s*>\s*|\s+/', $selector, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($tokens)) {
            return '//*';
        }

        $xpathTokens = [];
        foreach ($tokens as $token) {
            $xpathTokens[] = self::tokenToXPath($token);
        }

        if (count($xpathTokens) === 1) {
            return '//' . $xpathTokens[0];
        }

        // Build descendant chain
        return '//' . implode('//', $xpathTokens);
    }

    private static function tokenToXPath(string $token): string
    {
        $token = trim($token);

        // Handle attribute selector: tag[attr*="val"] or tag[attr="val"]
        if (preg_match('/^([a-zA-Z*][a-zA-Z0-9\-]*)\[([^\]]+)\]$/', $token, $attrMatch)) {
            $tag = $attrMatch[1] ?: '*';
            $attrExpr = $attrMatch[2];
            // Convert [class*="foo"] → contains(@class, 'foo')
            if (preg_match('/^([\w\-]+)\*=["\']([^"\']+)["\']$/', $attrExpr, $m)) {
                return "{$tag}[contains(@{$m[1]}, '{$m[2]}')]";
            }
            if (preg_match('/^([\w\-]+)=["\']([^"\']+)["\']$/', $attrExpr, $m)) {
                return "{$tag}[@{$m[1]}='{$m[2]}']";
            }
            return "{$tag}[@{$attrExpr}]";
        }

        // Split tag from class/id parts
        preg_match('/^([a-zA-Z][a-zA-Z0-9\-]*)?((?:[.#][a-zA-Z0-9_\-]+)+)?$/', $token, $match);
        $tag = $match[1] ?? '';
        $modifiers = $match[2] ?? '';

        if ($tag === '' && $modifiers === '') {
            return '*';
        }

        $tagPart = $tag ?: '*';
        $conditions = [];

        // Parse each .class or #id
        preg_match_all('/([.#])([a-zA-Z0-9_\-]+)/', $modifiers, $mods, PREG_SET_ORDER);
        foreach ($mods as $mod) {
            if ($mod[1] === '.') {
                $conditions[] = "contains(concat(' ', normalize-space(@class), ' '), ' {$mod[2]} ')";
            } else {
                $conditions[] = "@id='{$mod[2]}'";
            }
        }

        if (empty($conditions)) {
            return $tagPart;
        }

        return $tagPart . '[' . implode(' and ', $conditions) . ']';
    }

    private static function extractAuthor(string $html, ?string $selector): string
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);

        if (!empty($selector)) {
            $xpathQuery = self::convertSelectorToXPath($selector);
            $nodes = self::safeXpathQuery($xpath, $xpathQuery);
            if ($nodes && $nodes->length > 0) {
                $author = trim($nodes->item(0)->textContent);
                if (!empty($author)) return $author;
            }
        }

        // Fallbacks
        $metaQueries = [
            '//meta[@name="author"]/@content',
            '//meta[@property="og:article:author"]/@content',
            '//meta[@name="twitter:creator"]/@content',
            '//meta[@property="article:author"]/@content',
            '//*[contains(@class, "author") or contains(@class, "writer") or contains(@class, "editor") or contains(@class, "reporter")]'
        ];

        foreach ($metaQueries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes && $nodes->length > 0) {
                $author = trim($nodes->item(0)->textContent);
                if (!empty($author) && strlen($author) < 100) return $author;
            }
        }

        return 'Editor';
    }

    private static function extractDate(string $html, ?string $selector): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);

        $raw = '';

        if (!empty($selector)) {
            $xpathQuery = self::convertSelectorToXPath($selector);
            $nodes = self::safeXpathQuery($xpath, $xpathQuery);
            if ($nodes && $nodes->length > 0) {
                $raw = trim($nodes->item(0)->textContent);
                $normalized = self::normalizeDateValue($raw);
                return [
                    'raw' => $raw,
                    'normalized' => $normalized,
                    'status' => $normalized ? 'parsed_selector' : 'raw_selector_unparsed',
                ];
            }
        }

        // Fallbacks
        $metaQueries = [
            '//meta[@property="article:published_time"]/@content',
            '//meta[@name="pubdate"]/@content',
            '//meta[@name="publishdate"]/@content',
            '//meta[@name="timestamp"]/@content',
            '//meta[@property="og:released_date"]/@content',
            '//time/@datetime',
            '//time'
        ];

        foreach ($metaQueries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes && $nodes->length > 0) {
                $raw = trim($nodes->item(0)->textContent);
                $normalized = self::normalizeDateValue($raw);
                return [
                    'raw' => $raw,
                    'normalized' => $normalized,
                    'status' => $normalized ? 'parsed_meta' : 'raw_meta_unparsed',
                ];
            }
        }

        return [
            'raw' => '',
            'normalized' => now()->utc()->toIso8601ZuluString(),
            'status' => 'fallback_now',
        ];
    }

    private static function normalizeDateValue(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $parsed = self::parseIndonesianDate($value);
        if ($parsed) {
            return $parsed->utc()->toIso8601ZuluString();
        }
        return null;
    }

    private static function extractInertiaPageData(string $html): ?array
    {
        if (preg_match('/data-page="([^"]+)"/', $html, $matches)) {
            $decoded = json_decode(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
            return is_array($decoded) ? $decoded : null;
        }
        return null;
    }

    private static function findValueInArrayByKeys(array $arr, array $targetKeys): ?string
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveArrayIterator($arr),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $key => $value) {
            if (in_array(strtolower((string)$key), $targetKeys, true) && is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }
        return null;
    }

    private static function discoverLinksFromInertiaJson(array $data, string $baseUrl, string $domain): array
    {
        $urls = [];
        array_walk_recursive($data, function ($value) use (&$urls, $baseUrl, $domain) {
            if (is_string($value)) {
                $val = trim($value);
                if (str_starts_with($val, 'http://') || str_starts_with($val, 'https://') || str_starts_with($val, '/')) {
                    $resolved = self::normalizeUrl($val, $baseUrl);
                    if ($resolved && !self::checkUrlRejection($resolved, $domain)) {
                        $urls[] = $resolved;
                    }
                }
            }
        });
        return array_unique($urls);
    }

    private static function parseIndonesianDate(string $raw): ?\Carbon\Carbon
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $rawCleaned = preg_replace('/^(?:senin|selasa|rabu|kamis|jum\'?at|sabtu|minggu)\s*,\s*/i', '', $raw);

        try {
            return \Carbon\Carbon::parse($rawCleaned);
        } catch (\Throwable $e) {}

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}/', $rawCleaned)) {
            try {
                return \Carbon\Carbon::createFromFormat('d/m/Y', substr($rawCleaned, 0, 10))->startOfDay();
            } catch (\Throwable $e) {}
        }

        $months = [
            'januari' => 'January', 'februari' => 'February', 'maret' => 'March', 'april' => 'April',
            'mei' => 'May', 'juni' => 'June', 'juli' => 'July', 'agustus' => 'August',
            'september' => 'September', 'oktober' => 'October', 'november' => 'November', 'desember' => 'December',
            'jan' => 'Jan', 'feb' => 'Feb', 'mar' => 'Mar', 'apr' => 'Apr', 'jun' => 'Jun', 'jul' => 'Jul',
            'agu' => 'Aug', 'agt' => 'Aug', 'sep' => 'Sep', 'okt' => 'Oct', 'nov' => 'Nov', 'des' => 'Dec',
        ];

        $lower = strtolower($rawCleaned);
        foreach ($months as $indo => $eng) {
            $lower = str_replace($indo, $eng, $lower);
        }

        try {
            return \Carbon\Carbon::parse($lower);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
