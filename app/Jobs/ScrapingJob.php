<?php

namespace App\Jobs;

use App\Models\ScrapingItem;
use App\Models\NewsSource;
use App\Services\AiAnalysisDispatchStateService;
use Illuminate\Support\Facades\Http;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ScrapingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MIN_FULL_CONTENT_LENGTH = 500;

    public array $item;

    public function __construct(array $item)
    {
        $this->item = $item;
    }

    public function handle(): void
    {
        Log::info('[Pipeline] ScrapingJob crawling content from: ' . $this->item['url']);

        $content = $this->fetchFullContent((string) $this->item['url']);
        if (trim($content) === '') {
            $content = (string) ($this->item['content'] ?? $this->item['title'] ?? '');
        }

        if (mb_strlen(trim($content)) < self::MIN_FULL_CONTENT_LENGTH) {
            $scrapeRecord = ScrapingItem::updateOrCreate(
                ['url' => $this->item['url']],
                [
                    'candidate_link_id' => $this->item['candidate_link_id'] ?? 1,
                    'status' => 'partial',
                    'retry_count' => 0,
                    'last_attempt_at' => now(),
                    'error_message' => 'Content too short for final article (' . mb_strlen(trim($content)) . ' chars).',
                ]
            );

            Log::warning('[Pipeline] ScrapingJob content too short, skipping AI dispatch.', [
                'url' => $scrapeRecord->url,
                'content_length' => mb_strlen(trim($content)),
            ]);

            return;
        }

        // Create or update record in database matching scraping_items schema
        $scrapeRecord = ScrapingItem::updateOrCreate(
            ['url' => $this->item['url']],
            [
                'candidate_link_id' => $this->item['candidate_link_id'] ?? 1,
                'status' => 'scraped',
                'retry_count' => 0,
                'last_attempt_at' => now(),
                'error_message' => null,
            ]
        );

        Log::info('[Pipeline] Scraping completed successfully for URL: ' . $scrapeRecord->url . '. Dispatching AiAnalysisJob.');

        $dispatchStateService = app(AiAnalysisDispatchStateService::class);
        $promptTemplateId = $dispatchStateService->resolvePromptTemplateId('article');
        $providerContextHash = $dispatchStateService->resolveProviderContextHash();
        $decision = $dispatchStateService->reserveQueuedStateAndDispatch([
            'type' => 'article',
            'id' => $scrapeRecord->id,
            'project_id' => $this->item['project_id'] ?? null,
            'title' => $this->item['title'],
            'content' => $content,
            'url' => $this->item['url'],
            'source_name' => $this->item['source_name'] ?? null,
        ], $promptTemplateId, $providerContextHash);

        if (! ($decision['should_dispatch'] ?? false)) {
            Log::info('[Pipeline] ScrapingJob skipped AI dispatch due to persistent dispatch state.', [
                'url' => $scrapeRecord->url,
                'status' => $decision['status'] ?? 'unknown',
                'reason' => $decision['reason'] ?? 'unknown',
            ]);

            return;
        }

        Cache::put("ai_analysis_lock_article_{$scrapeRecord->id}", true, now()->addMinutes(15));
    }

    private function fetchFullContent(string $url): string
    {
        try {
            $canonicalUrl = $url;
            $html = $this->fetchRenderedHtml($url);
            if ($html === '') {
                $response = Http::timeout(15)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36'])
                    ->get($url);

                if (!$response->successful()) {
                    return '';
                }

                $html = $response->body();
            }

            $content = $this->extractReadableContent($html, null);

            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new \DOMXPath($dom);
            $canonicalNodes = $xpath->query('//link[@rel="canonical"]');
            if ($canonicalNodes && $canonicalNodes->length > 0) {
                $canonicalUrl = trim($canonicalNodes->item(0)->getAttribute('href'));
                if ($canonicalUrl !== '' && $canonicalUrl !== $url) {
                    $canonicalHtml = $this->fetchRenderedHtml($canonicalUrl);
                    if ($canonicalHtml === '') {
                        $canonicalResponse = Http::timeout(15)
                            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36'])
                            ->get($canonicalUrl);

                        if ($canonicalResponse->successful()) {
                            $canonicalHtml = $canonicalResponse->body();
                        }
                    }

                    if ($canonicalHtml !== '') {
                        $canonicalContent = $this->extractReadableContent($canonicalHtml, null);
                        if (mb_strlen($canonicalContent) > mb_strlen($content)) {
                            $content = $canonicalContent;
                        }
                    }
                }
            }

            return $content;
        } catch (\Throwable $e) {
            Log::warning('[Pipeline] ScrapingJob failed to extract full content: ' . $e->getMessage());
            return '';
        }
    }

    private function fetchRenderedHtml(string $url): string
    {
        $chrome = $this->resolveChromeBinary();
        if ($chrome === null) {
            return '';
        }

        $command = escapeshellarg($chrome)
            . ' --headless --disable-gpu --no-first-run --no-default-browser-check'
            . ' --virtual-time-budget=5000 --dump-dom '
            . escapeshellarg($url)
            . ' 2>/dev/null';

        $output = shell_exec($command);
        if (!is_string($output)) {
            return '';
        }

        return trim($output);
    }

    private function resolveChromeBinary(): ?string
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

    private function convertSelectorToXPath(string $selector): string
    {
        $selector = trim($selector);
        $parts = explode(',', $selector);
        $xpathParts = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;
            
            // Simple mapping for class/id
            if (str_starts_with($part, '.')) {
                $class = substr($part, 1);
                $xpathParts[] = "//*[contains(concat(' ', normalize-space(@class), ' '), ' $class ')]";
            } elseif (str_starts_with($part, '#')) {
                $id = substr($part, 1);
                $xpathParts[] = "//*[@id='$id']";
            } else {
                // E.g. 'article', 'div'
                $xpathParts[] = "//" . $part;
            }
        }
        return empty($xpathParts) ? '//p' : implode(' | ', $xpathParts);
    }

    private function extractReadableContent(string $html, ?NewsSource $source): string
    {
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);

        if ($source && !empty($source->article_noise_selector)) {
            $noiseSelectors = explode(',', $source->article_noise_selector);
            foreach ($noiseSelectors as $nSel) {
                $nSel = trim($nSel);
                if (empty($nSel)) continue;
                $nQuery = $this->convertSelectorToXPath($nSel);
                $noiseNodes = $xpath->query($nQuery);
                if ($noiseNodes) {
                    foreach ($noiseNodes as $nNode) {
                        $nNode->parentNode->removeChild($nNode);
                    }
                }
            }
        }

        if ($source && !empty($source->article_content_selector)) {
            $xpathQuery = $this->convertSelectorToXPath($source->article_content_selector);
            $nodes = $xpath->query($xpathQuery);
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
}
