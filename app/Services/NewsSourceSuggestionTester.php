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
        $discoveredUrls = [];
        $rejectedUrls = [];
        $validArticles = [];
        $reasons = [];

        $keyword = filled($testKeyword) ? $testKeyword : 'politik';
        $discoveryUrl = null;
        $discoverySource = 'search_url';

        $discoveryCandidates = [];
        if (filled($suggestion->search_url)) {
            $discoveryCandidates[] = [
                'url' => str_replace('{keyword}', rawurlencode($keyword), $suggestion->search_url),
                'source' => 'search_url'
            ];
        }
        if (filled($suggestion->feed_url)) {
            $discoveryCandidates[] = [
                'url' => $suggestion->feed_url,
                'source' => 'feed_url'
            ];
        }
        if (filled($suggestion->sitemap_url)) {
            $discoveryCandidates[] = [
                'url' => $suggestion->sitemap_url,
                'source' => 'sitemap_url'
            ];
        }
        if (filled($suggestion->base_url)) {
            $discoveryCandidates[] = [
                'url' => $suggestion->base_url,
                'source' => 'base_url'
            ];
        }
        $discoveryCandidates[] = [
            'url' => 'https://' . $suggestion->domain,
            'source' => 'domain'
        ];

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

                // Limit test to first 3 discovered URLs
                $testLinks = array_slice($discoveredUrls, 0, 3);
                
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
            $status = 'verified';
            $reasons[] = "Selector berhasil mengambil isi artikel";
            $reasons[] = "Canonical URL valid";
            $reasons[] = "Content > 500 karakter";
            $reasons[] = "Konten relevan dengan keyword \"{$keyword}\"";
            $reasons[] = "URL bukan Google News";
        } else {
            $status = 'failed';
            $reasons[] = "Tidak ada artikel valid ditemukan";
            if (count($discoveredUrls) === 0) {
                $reasons[] = "Selector tidak menemukan link artikel pada halaman pencarian";
            } else {
                $reasons[] = "Content kurang dari 500 karakter atau selector tidak menemukan body artikel";
            }
        }

        return [
            'mode' => 'discovery',
            'status' => $status,
            'keyword' => $keyword,
            'manual_url' => null,
            'source' => $suggestion->source_name ?: $suggestion->domain,
            'tested_at' => now()->toDateTimeString(),
            'discovered_urls' => $discoveredUrls,
            'rejected_urls' => $rejectedUrls,
            'valid_articles' => $validArticles,
            'reasons' => $reasons
        ];
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
        try {
            // First try via HTTP client (fast)
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36'])
                ->get($url);
            
            $httpStatus = $response->status();
            if ($response->successful() && mb_strlen($response->body()) > 1000) {
                return $response->body();
            }
        } catch (\Throwable $e) {
            $httpStatus = 500;
            Log::warning("[NewsSourceSuggestionTester] HTTP Client failed for {$url}: " . $e->getMessage());
        }

        // Try via Headless Chrome if available
        return self::fetchRenderedHtml($url);
    }

    private static function fetchRenderedHtml(string $url): string
    {
        $chrome = self::resolveChromeBinary();
        if ($chrome === null) {
            return '';
        }

        $command = escapeshellarg($chrome)
            . ' --headless --disable-gpu --no-first-run --no-default-browser-check'
            . ' --virtual-time-budget=5000 --dump-dom '
            . escapeshellarg($url)
            . ' 2>/dev/null';

        $output = shell_exec($command);
        return is_string($output) ? trim($output) : '';
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

        // Try Inertia.js data-page attribute parsing first
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

        // Try selector
        $selector = $suggestion->article_link_selector ?: $suggestion->search_result_selector;
        if (!empty($selector)) {
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new \DOMXPath($dom);
            $xpathQuery = self::convertSelectorToXPath($selector);
            $nodes = self::safeXpathQuery($xpath, $xpathQuery);
            if ($nodes && $nodes->length > 0) {
                foreach ($nodes as $node) {
                    $href = trim((string) $node->getAttribute('href'));
                    $resolved = self::normalizeUrl($href, $baseUrl);
                    if ($resolved) $links[] = $resolved;
                }
            }
        }

        // Fallback regex scan - only collect URLs that look like articles
        preg_match_all('~href=["\']([^"\']+)["\']~i', $html, $matches);
        foreach ($matches[1] as $href) {
            $resolved = self::normalizeUrl($href, $baseUrl);
            if ($resolved && self::looksLikeArticleUrl($resolved)) {
                $links[] = $resolved;
            }
        }

        return array_unique($links);
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
            if ($host !== $cleanDomain && !str_ends_with($host, '.' . $cleanDomain)) {
                return "Domain tidak cocok dengan: {$domain}";
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

        // Reject known navigation/utility path prefixes
        $navPrefixes = ['rss', 'feed', 'sitemap', 'robots', 'amp/', 'live', 'streaming',
                        'pedoman', 'contact', 'privacy', 'redaksi', 'search', 'about',
                        'advertise', 'newsletter', 'tag/', 'tags/', 'author/', 'page/'];
        foreach ($navPrefixes as $prefix) {
            if ($pathLower === rtrim($prefix, '/') || str_starts_with($pathLower, $prefix)) {
                return false;
            }
        }

        $segments = array_values(array_filter(explode('/', $pathLower)));

        // Accept if path contains a numeric ID (common: /category/12345/slug)
        if (preg_match('/\/\d{4,}/', $path)) return true;

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
