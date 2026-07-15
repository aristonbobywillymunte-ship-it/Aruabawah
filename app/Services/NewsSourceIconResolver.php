<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class NewsSourceIconResolver
{
    public function resolve(?string $baseUrl, ?string $domain = null, ?string $fallbackName = null): ?string
    {
        $candidates = $this->discoverCandidates($baseUrl, $domain, $fallbackName);

        foreach ($candidates as $candidate) {
            if ($this->looksLikeImageUrl($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function discoverCandidates(?string $baseUrl, ?string $domain = null, ?string $fallbackName = null): array
    {
        $urls = [];

        $normalizedBase = $this->normalizeUrl($baseUrl);
        if ($normalizedBase) {
            $urls[] = $normalizedBase;
        }

        $domain = $domain ? trim(strtolower($domain)) : null;
        if ($domain !== null && $domain !== '') {
            $urls[] = 'https://' . ltrim($domain, '/');
            if (! str_contains($domain, 'www.')) {
                $urls[] = 'https://www.' . ltrim($domain, '/');
            }
        }

        $fallbackName = $fallbackName ? trim(strtolower($fallbackName)) : null;
        if ($fallbackName) {
            $slug = preg_replace('/[^a-z0-9]+/i', '', $fallbackName) ?: $fallbackName;
            if ($slug !== '') {
                $urls[] = 'https://' . $slug;
            }
        }

        $urls = array_values(array_unique(array_filter($urls)));
        $candidates = [];

        foreach ($urls as $url) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    ])
                    ->get($url);

                if (! $response->successful()) {
                    continue;
                }

                $html = (string) $response->body();
                $parsed = $this->extractFromHtml($html, $url);

                foreach ($parsed as $candidate) {
                    $candidates[] = $candidate;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * @return array<int, string>
     */
    public function extractFromHtml(string $html, ?string $pageUrl = null): array
    {
        $candidates = [];
        $baseUrl = $pageUrl ? $this->getOrigin($pageUrl) : null;

        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);

        $selectors = [
            '//link[contains(translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "icon")]/@href',
            '//meta[contains(translate(@property, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "og:image")]/@content',
            '//meta[contains(translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "twitter:image")]/@content',
            '//img[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "logo")]/@src',
            '//img[contains(translate(@id, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "logo")]/@src',
        ];

        foreach ($selectors as $selector) {
            foreach ($xpath->query($selector) as $node) {
                $value = trim((string) $node->nodeValue);
                if ($value === '') {
                    continue;
                }
                $resolved = $this->resolveRelativeUrl($value, $pageUrl);
                if ($resolved !== null) {
                    $candidates[] = $resolved;
                }
            }
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private function normalizeUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return 'https://' . ltrim($url, '/');
    }

    private function resolveRelativeUrl(string $url, ?string $pageUrl = null): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        if (Str::startsWith($url, '//')) {
            return 'https:' . $url;
        }

        if (! $pageUrl) {
            return null;
        }

        $origin = $this->getOrigin($pageUrl);
        if (! $origin) {
            return null;
        }

        if (Str::startsWith($url, '/')) {
            return rtrim($origin, '/') . $url;
        }

        $path = parse_url($pageUrl, PHP_URL_PATH) ?: '/';
        $dir = preg_replace('#/[^/]*$#', '/', $path) ?: '/';

        return rtrim($origin, '/') . rtrim($dir, '/') . '/' . ltrim($url, '/');
    }

    private function looksLikeImageUrl(string $url): bool
    {
        return (bool) preg_match('/\.(png|jpg|jpeg|webp|gif|ico)(\?.*)?$/i', $url)
            || str_contains(strtolower($url), 'favicon')
            || str_contains(strtolower($url), 'logo');
    }

    private function getOrigin(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (! empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin;
    }
}
