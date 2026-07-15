<?php

namespace App\Console\Commands;

use App\Models\NewsSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use ReflectionClass;

class TestSourceDiscovery extends Command
{
    protected $signature = 'news:test-source-discovery
                            {--source-id= : News source ID}
                            {--keyword= : Discovery keyword}
                            {--mode=search : Discovery mode: search|feed|sitemap}
                            {--limit=5 : Max URLs to inspect}
                            {--process-one : Stop after the first primary match is processed}';

    protected $description = 'Dry-run discovery and final extraction for one news source without writing to DB';

    public function handle(): int
    {
        $sourceId = (int) $this->option('source-id');
        $keyword = trim((string) $this->option('keyword'));
        $mode = strtolower(trim((string) $this->option('mode'))) ?: 'search';
        $limit = max(1, min(10, (int) $this->option('limit')));
        $processOne = (bool) $this->option('process-one');

        if ($sourceId <= 0 || $keyword === '') {
            $this->error('source-id dan keyword wajib diisi.');
            return self::FAILURE;
        }

        $source = NewsSource::query()->find($sourceId);
        if (! $source) {
            $this->error("News source {$sourceId} tidak ditemukan.");
            return self::FAILURE;
        }

        $helper = app(RunNewsPortalScraping::class);
        $ref = new ReflectionClass($helper);

        $fetchFullContent = $ref->getMethod('fetchFullContent');
        $fetchFullContent->setAccessible(true);
        $normalizeUrl = $ref->getMethod('normalizeUrl');
        $normalizeUrl->setAccessible(true);
        $isLikelyArticleUrl = $ref->getMethod('isLikelyArticleUrl');
        $isLikelyArticleUrl->setAccessible(true);

        $searchUrl = $this->buildDiscoveryUrl($source, $mode, $keyword);
        if ($searchUrl === '') {
            $this->error("Source {$source->name} tidak punya URL discovery untuk mode {$mode}.");
            return self::FAILURE;
        }

        $httpStatus = 0;
        $html = $this->fetchHtml($searchUrl, $httpStatus);
        if ($html === '') {
            $this->line(json_encode([
                'source' => $source->name,
                'mode' => $mode,
                'search_url' => $searchUrl,
                'found_count' => 0,
                'accepted_count' => 0,
                'duplicate_count' => 0,
                'invalid_domain_count' => 0,
                'non_article_count' => 0,
                'fetch_failed_count' => 0,
                'skipped_count' => 0,
                'discovered_urls' => [],
                'accepted_urls' => [],
                'rejected_urls' => [],
                'final_articles' => [],
                'reason' => "Gagal mengambil halaman discovery (HTTP {$httpStatus})",
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $selectors = array_values(array_filter([
            $source->article_link_selector,
            $source->search_result_selector,
        ]));
        $baseUrl = $source->base_url ?: ('https://' . $source->domain);

        $rawLinks = [];
        foreach ($selectors as $selector) {
            $rawLinks = array_merge($rawLinks, $this->extractHrefListFromSelectorHtml($html, $selector, $baseUrl));
        }

        if (empty($rawLinks)) {
            $rawLinks = $this->extractHrefListFromHtml($html, $baseUrl);
        }

        $rawLinks = array_values(array_unique(array_filter($rawLinks)));
        $metrics = [
            'accepted' => 0,
            'duplicate' => 0,
            'invalid_domain' => 0,
            'non_article' => 0,
            'fetch_failed' => 0,
            'skipped' => 0,
        ];
        $seen = [];
        $accepted = [];
        $rejected = [];
        $finalArticles = [];
        $processedPrimary = false;

        foreach ($rawLinks as $index => $url) {
            $finalStatus = 'skipped';
            $reason = 'Tidak diproses';
            $canonicalUrl = null;
            $contentLength = null;
            $result = null;
            $relevance = [
                'status' => null,
                'keyword_match_score' => null,
                'matched_topic_terms' => [],
                'matched_location_terms' => [],
                'local_relevance_score' => null,
                'match_source' => null,
                'match_strength' => null,
                'topic_in_title' => false,
                'location_in_title' => false,
                'topic_in_lead' => false,
                'location_in_lead' => false,
                'topic_occurrences' => 0,
                'location_occurrences' => 0,
                'minimum_term_distance' => null,
                'same_sentence_match' => false,
                'same_paragraph_match' => false,
            ];

            if (isset($seen[$url])) {
                $finalStatus = 'duplicate';
                $reason = 'Duplicate URL dalam satu run';
            } elseif (! $this->belongsToSourceDomain($url, $source)) {
                $finalStatus = 'invalid_domain';
                $reason = 'URL tidak berada di domain source yang valid';
            } elseif ($metrics['accepted'] >= $limit) {
                $finalStatus = 'skipped';
                $reason = "Limit accepted {$limit} sudah tercapai";
            } elseif (! (bool) $isLikelyArticleUrl->invoke($helper, $url, $source)) {
                $finalStatus = 'non_article';
                $reason = 'URL non-artikel atau tidak lolos filter source';
            } else {
                try {
                    $result = $fetchFullContent->invoke($helper, $url);
                    $canonicalUrl = (string) ($result['canonical_url'] ?? $url);
                    $content = trim((string) ($result['content'] ?? ''));
                    $contentLength = mb_strlen($content);
                    $title = trim((string) ($result['title'] ?? ''));
                    $sourceName = trim((string) ($result['source_name'] ?? ''));
                    $publishedAt = $result['published_at'] ?? null;
                    $lead = mb_substr($content, 0, 600);
                    $relevance = $this->getStrictKeywordRelevance($keyword, $title, $lead, $content);

                    if ($relevance['status'] !== 'relevant') {
                        $finalStatus = 'skipped';
                        $reason = $relevance['reason'] ?? 'Strict keyword concept mismatch';
                    } elseif ($contentLength >= 500 && $title !== '' && $canonicalUrl !== '') {
                        $finalStatus = 'accepted';
                        $reason = 'Extraction success';
                        $accepted[] = [
                            'url' => $url,
                            'status' => 'accepted',
                            'reason' => $reason,
                        ];
                        $finalArticles[] = [
                            'url' => $url,
                            'canonical_url' => $canonicalUrl,
                            'title' => $title,
                            'source_name' => $sourceName,
                            'published_at' => optional($publishedAt)?->toIso8601String(),
                            'content_length' => $contentLength,
                            'preview' => mb_substr($content, 0, 400),
                            'passed' => true,
                            'keyword_match_score' => $relevance['keyword_match_score'],
                            'matched_topic_terms' => $relevance['matched_topic_terms'],
                            'matched_location_terms' => $relevance['matched_location_terms'],
                            'local_relevance_score' => $relevance['local_relevance_score'],
                            'match_source' => $relevance['match_source'],
                            'match_strength' => $relevance['match_strength'],
                            'topic_in_title' => $relevance['topic_in_title'],
                            'location_in_title' => $relevance['location_in_title'],
                            'topic_in_lead' => $relevance['topic_in_lead'],
                            'location_in_lead' => $relevance['location_in_lead'],
                            'topic_occurrences' => $relevance['topic_occurrences'],
                            'location_occurrences' => $relevance['location_occurrences'],
                            'minimum_term_distance' => $relevance['minimum_term_distance'],
                            'same_sentence_match' => $relevance['same_sentence_match'],
                            'same_paragraph_match' => $relevance['same_paragraph_match'],
                        ];

                        if ($processOne && $relevance['match_strength'] === 'primary') {
                            $processedPrimary = true;
                        }
                    } else {
                        $finalStatus = 'skipped';
                        $reason = "Content kurang dari 500 karakter ({$contentLength})";
                    }
                } catch (\Throwable $e) {
                    $finalStatus = 'fetch_failed';
                    $reason = $e->getMessage();
                }
            }

            $seen[$url] = true;
            $metrics[$finalStatus]++;

            $rejected[] = [
                'index' => $index,
                'url' => $url,
                'final_status' => $finalStatus,
                'reason' => $reason,
                'canonical_url' => $canonicalUrl,
                'content_length' => $contentLength,
                'keyword_match_score' => $relevance['keyword_match_score'],
                'matched_topic_terms' => $relevance['matched_topic_terms'],
                'matched_location_terms' => $relevance['matched_location_terms'],
                'local_relevance_score' => $relevance['local_relevance_score'],
                'match_source' => $relevance['match_source'],
                'match_strength' => $relevance['match_strength'],
                'topic_in_title' => $relevance['topic_in_title'],
                'location_in_title' => $relevance['location_in_title'],
                'topic_in_lead' => $relevance['topic_in_lead'],
                'location_in_lead' => $relevance['location_in_lead'],
                'topic_occurrences' => $relevance['topic_occurrences'],
                'location_occurrences' => $relevance['location_occurrences'],
                'minimum_term_distance' => $relevance['minimum_term_distance'],
                'same_sentence_match' => $relevance['same_sentence_match'],
                'same_paragraph_match' => $relevance['same_paragraph_match'],
            ];

            if ($processOne && $processedPrimary) {
                break;
            }
        }

        $this->line(json_encode([
            'source' => $source->name,
            'mode' => $mode,
            'search_url' => $searchUrl,
            'found_count' => count($rawLinks),
            'accepted_count' => $metrics['accepted'],
            'duplicate_count' => $metrics['duplicate'],
            'invalid_domain_count' => $metrics['invalid_domain'],
            'non_article_count' => $metrics['non_article'],
            'fetch_failed_count' => $metrics['fetch_failed'],
            'skipped_count' => $metrics['skipped'],
            'discovered_urls' => $rawLinks,
            'accepted_urls' => $accepted,
            'rejected_urls' => $rejected,
            'final_articles' => $finalArticles,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }

    private function buildDiscoveryUrl(NewsSource $source, string $mode, string $keyword): string
    {
        return match ($mode) {
            'feed' => (string) ($source->feed_url ?: ''),
            'sitemap' => (string) ($source->sitemap_url ?: ''),
            default => (string) ($source->search_url ? str_replace('{keyword}', rawurlencode($keyword), $source->search_url) : ''),
        };
    }

    private function fetchHtml(string $url, int &$httpStatus): string
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36'])
                ->get($url);

            $httpStatus = $response->status();
            return $response->successful() ? (string) $response->body() : '';
        } catch (\Throwable) {
            $httpStatus = 0;
            return '';
        }
    }

    private function extractHrefListFromSelectorHtml(string $html, string $selector, string $baseUrl): array
    {
        $results = [];
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query($this->convertSelectorToXPath($selector));
        if ($nodes && $nodes->length > 0) {
            foreach ($nodes as $node) {
                $href = trim((string) $node->getAttribute('href'));
                if ($href === '') {
                    continue;
                }
                $results[] = $this->normalizeUrl($href, $baseUrl);
            }
        }

        return array_values(array_filter($results));
    }

    private function getStrictKeywordRelevance(string $keyword, string $title, string $lead, string $content): array
    {
        $titleHaystack = mb_strtolower(trim($title));
        $leadHaystack = mb_strtolower(trim($lead));
        $contentHaystack = mb_strtolower(trim($content));
        $topicTerms = [
            'wagub',
            'wakil gubernur',
        ];
        $locationTerms = [
            'kaltim',
            'kalimantan timur',
        ];

        $matchedTopic = [];
        foreach ($topicTerms as $term) {
            if (str_contains($titleHaystack, $term)) {
                $matchedTopic[] = $term;
            }
        }

        $matchedLocation = [];
        foreach ($locationTerms as $term) {
            if (str_contains($titleHaystack, $term)) {
                $matchedLocation[] = $term;
            }
        }

        $localSignals = [
            'samarinda',
            'pemprov kaltim',
            'seno aji',
            'ingkong ala',
            'bpn kaltim',
            'dprd kaltim',
            'gubernur kaltim',
            'wakil gubernur kaltim',
        ];
        $matchedLocalSignals = [];
        foreach ($localSignals as $term) {
            if (str_contains($contentHaystack, $term) || str_contains($titleHaystack, $term) || str_contains($leadHaystack, $term)) {
                $matchedLocalSignals[] = $term;
            }
        }

        $topicInTitle = $this->containsAny($titleHaystack, $topicTerms);
        $locationInTitle = $this->containsAny($titleHaystack, $locationTerms);
        $topicInLead = $this->containsAny($leadHaystack, $topicTerms);
        $locationInLead = $this->containsAny($leadHaystack, $locationTerms);

        $matchSource = null;
        $matchStrength = 'incidental';
        if (($topicInTitle && $locationInTitle) || ($topicInTitle && $locationInLead) || ($locationInTitle && $topicInLead)) {
            $matchSource = 'title/snippet';
            $matchStrength = 'primary';
        } elseif (($topicInLead && $locationInLead) || ($topicInTitle && $locationInLead) || ($locationInTitle && $topicInLead)) {
            $matchSource = 'lead';
            $matchStrength = 'secondary';
        } elseif (! empty($matchedTopic) && ! empty($matchedLocation)) {
            $matchSource = 'body';
            $matchStrength = 'incidental';
        }

        $topicOccurrences = $this->countTermOccurrences($titleHaystack . ' ' . $leadHaystack . ' ' . $contentHaystack, $topicTerms);
        $locationOccurrences = $this->countTermOccurrences($titleHaystack . ' ' . $leadHaystack . ' ' . $contentHaystack, $locationTerms);
        $minimumTermDistance = $this->minimumTermDistance($titleHaystack, $contentHaystack, $topicTerms, $locationTerms);
        $sameSentenceMatch = $this->hasSameSentenceMatch($contentHaystack, $topicTerms, $locationTerms);
        $sameParagraphMatch = $this->hasSameParagraphMatch($contentHaystack, $topicTerms, $locationTerms);

        $keywordMatchScore = 0;
        if ($topicInTitle || $topicInLead) {
            $keywordMatchScore += 50;
        }
        if ($locationInTitle || $locationInLead) {
            $keywordMatchScore += 40;
        }
        if (! empty($matchedLocalSignals)) {
            $keywordMatchScore += min(10, count($matchedLocalSignals) * 5);
        }

        $status = (! empty($matchedTopic) && ! empty($matchedLocation)) ? 'relevant' : 'out_of_scope';
        if ($status !== 'relevant') {
            $keywordMatchScore = min($keywordMatchScore, 19);
        }

        if ($matchStrength === 'incidental') {
            $status = 'out_of_scope';
            $keywordMatchScore = min($keywordMatchScore, 19);
            $matchSource = $matchSource ?: 'body';
        } elseif ($matchStrength === 'secondary') {
            $status = 'relevant';
            $keywordMatchScore = max($keywordMatchScore, 70);
        } elseif ($matchStrength === 'primary') {
            $status = 'relevant';
            $keywordMatchScore = max($keywordMatchScore, 85);
        }

        return [
            'status' => $status,
            'reason' => $status === 'relevant' ? 'Matched strict keyword concept' : 'Incidental body mention',
            'keyword_match_score' => $keywordMatchScore,
            'matched_topic_terms' => array_values(array_unique($matchedTopic)),
            'matched_location_terms' => array_values(array_unique($matchedLocation)),
            'local_relevance_score' => $keywordMatchScore,
            'match_source' => $matchSource,
            'match_strength' => $matchStrength,
            'topic_in_title' => $topicInTitle,
            'location_in_title' => $locationInTitle,
            'topic_in_lead' => $topicInLead,
            'location_in_lead' => $locationInLead,
            'topic_occurrences' => $topicOccurrences,
            'location_occurrences' => $locationOccurrences,
            'minimum_term_distance' => $minimumTermDistance,
            'same_sentence_match' => $sameSentenceMatch,
            'same_paragraph_match' => $sameParagraphMatch,
        ];
    }

    private function countTermOccurrences(string $haystack, array $terms): int
    {
        $count = 0;
        foreach ($terms as $term) {
            $count += substr_count($haystack, mb_strtolower($term));
        }

        return $count;
    }

    private function minimumTermDistance(string $titleHaystack, string $contentHaystack, array $topicTerms, array $locationTerms): int
    {
        $combined = $titleHaystack . "\n" . $contentHaystack;
        $positionsTopic = [];
        $positionsLocation = [];

        foreach ($topicTerms as $term) {
            $offset = 0;
            while (($pos = mb_stripos($combined, $term, $offset)) !== false) {
                $positionsTopic[] = $pos;
                $offset = $pos + 1;
            }
        }

        foreach ($locationTerms as $term) {
            $offset = 0;
            while (($pos = mb_stripos($combined, $term, $offset)) !== false) {
                $positionsLocation[] = $pos;
                $offset = $pos + 1;
            }
        }

        if (empty($positionsTopic) || empty($positionsLocation)) {
            return 9999;
        }

        $min = 9999;
        foreach ($positionsTopic as $topicPos) {
            foreach ($positionsLocation as $locationPos) {
                $min = min($min, abs($topicPos - $locationPos));
            }
        }

        return $min;
    }

    private function hasSameSentenceMatch(string $contentHaystack, array $topicTerms, array $locationTerms): bool
    {
        $sentences = preg_split('/(?<=[.!?])\s+/u', $contentHaystack) ?: [];
        foreach ($sentences as $sentence) {
            if ($this->containsAny($sentence, $topicTerms) && $this->containsAny($sentence, $locationTerms)) {
                return true;
            }
        }

        return false;
    }

    private function hasSameParagraphMatch(string $contentHaystack, array $topicTerms, array $locationTerms): bool
    {
        $paragraphs = preg_split('/\n{2,}/u', $contentHaystack) ?: [];
        foreach ($paragraphs as $paragraph) {
            if ($this->containsAny($paragraph, $topicTerms) && $this->containsAny($paragraph, $locationTerms)) {
                return true;
            }
        }

        return false;
    }

    private function containsAny(string $haystack, array $terms): bool
    {
        foreach ($terms as $term) {
            if (str_contains($haystack, mb_strtolower($term))) {
                return true;
            }
        }

        return false;
    }

    private function extractHrefListFromHtml(string $html, string $baseUrl): array
    {
        $results = [];
        preg_match_all('~href=["\']([^"\']+)["\']~i', $html, $matches);
        foreach ($matches[1] as $href) {
            $resolved = $this->normalizeUrl($href, $baseUrl);
            if ($resolved) {
                $results[] = $resolved;
            }
        }

        return array_values(array_filter($results));
    }

    private function normalizeUrl(string $href, string $baseUrl): ?string
    {
        $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($href === '' || str_starts_with($href, 'javascript:') || str_starts_with($href, '#')) {
            return null;
        }

        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
    }

    private function belongsToSourceDomain(string $url, NewsSource $source): bool
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $host = preg_replace('/^www\./', '', strtolower($host));
        $domain = preg_replace('/^www\./', '', strtolower((string) $source->domain));

        return $host !== '' && ($host === $domain || str_ends_with($host, '.' . $domain));
    }

    private function convertSelectorToXPath(string $selector): string
    {
        $parts = explode(' ', trim($selector));
        $xpathParts = [];

        foreach ($parts as $part) {
            if (str_starts_with($part, '.')) {
                $className = substr($part, 1);
                $xpathParts[] = "//*[contains(@class, '{$className}')]";
            } elseif (str_starts_with($part, '#')) {
                $idName = substr($part, 1);
                $xpathParts[] = "//*[@id='{$idName}']";
            } elseif (str_contains($part, '.')) {
                $subParts = explode('.', $part);
                $tag = $subParts[0] ?: '*';
                $className = $subParts[1] ?? '';
                $xpathParts[] = "//{$tag}[contains(@class, '{$className}')]";
            } else {
                $xpathParts[] = "//{$part}";
            }
        }

        if (count($xpathParts) === 1) {
            return $xpathParts[0];
        }

        $base = str_replace('//', '', $xpathParts[0]);
        $descendant = str_replace('//', '', $xpathParts[1]);
        return "//{$base}//{$descendant}";
    }
}
