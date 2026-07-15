<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\ScrapingItem;
use App\Models\Project;
use App\Models\NewsSource;
use App\Models\ReachAssessment;
use App\Jobs\AiAnalysisJob;
use App\Services\NewsProjectScrapePriorityService;
use App\Services\News\GoogleNewsUrlDecoderService;
use App\Services\AiAnalysisDispatchStateService;
use App\Services\ReachScoringService;
use App\Services\SchedulerQueueGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RunNewsPortalScraping extends Command
{
    private const MIN_FULL_CONTENT_LENGTH = 500;
    private const AI_ANALYSIS_CACHE_LOCK_MINUTES = 15;
    private array $runStats = [];
    private array $runCandidateLogs = [];
    private array $seenCanonicalUrls = [];

    protected $signature = 'scraping:run-news
                            {--project-id= : Specific project ID}
                            {--source-id= : Restrict manual portal discovery to one news source}
                            {--keyword= : Override keyword}
                            {--discovery-mode=auto : auto|manual|google_news}
                            {--url= : Direct portal article URL for smoke test}
                            {--limit=3 : Max RSS items to stage}
                            {--no-telegram : Suppress Telegram notification dispatch}
                            {--no-ai : Do not dispatch AI analysis jobs}
                            {--no-reach : Do not calculate or store reach assessments}';

    protected $description = 'Scrape news articles from Google News RSS for all active project keywords';

    public function __construct(
        private readonly ReachScoringService $reachScoringService,
        private readonly GoogleNewsUrlDecoderService $googleNewsUrlDecoderService,
        private readonly NewsProjectScrapePriorityService $projectScrapePriorityService,
        private readonly AiAnalysisDispatchStateService $aiAnalysisDispatchStateService,
        private readonly SchedulerQueueGuard $schedulerQueueGuard,
    )
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $portalLog = Log::channel('portal_manual');
        $googleNewsLog = Log::channel('google_news');
        $filterProjectId = $this->option('project-id');
        $filterSourceId  = $this->option('source-id');
        $forceKeyword    = $this->option('keyword');
        $discoveryMode   = strtolower(trim((string) $this->option('discovery-mode'))) ?: 'auto';
        $directUrl       = trim((string) $this->option('url'));
        $limit           = max(1, (int) $this->option('limit'));
        $suppressTelegram = (bool) $this->option('no-telegram');
        $suppressAi = (bool) $this->option('no-ai');
        $suppressReach = (bool) $this->option('no-reach');

        $runLock = Cache::lock('news:run-active', 3600);
        if (! $runLock->get()) {
            $busyReason = $this->schedulerQueueGuard->newsBusyReason() ?? 'Scan portal sebelumnya masih berjalan.';
            $this->warn("News scraping skipped: {$busyReason}");
            $this->schedulerQueueGuard->logSkip('portal', $busyReason, ['source' => 'command']);
            return;
        }

        try {
        if ($filterProjectId) {
            $project = Project::withTrashed()->find($filterProjectId);
            if (! $project) {
                $this->error('Project not found.');
                return;
            }
            if ($project->trashed() || ! $project->is_active) {
                $this->error('Project is deleted/inactive and cannot be scraped.');
                return;
            }
            $projects = collect([$project]);
        } else {
            $projects = Project::query()
                ->where('is_active', true)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();
            if ($projects->isEmpty()) {
                $this->warn('No projects found.');
                return;
            }
        }
        $projects = $this->projectScrapePriorityService->prioritize($projects);
        $portalLog->info('[Portal] Run started.', [
            'project_id' => $filterProjectId ?: null,
            'source_id' => $filterSourceId ?: null,
            'keyword' => $forceKeyword ?: null,
            'limit' => $limit,
        ]);

        $totalInserted = 0;
        $totalReused = 0;
        $this->runStats = [
            'discovered' => 0,
            'found_count' => 0,
            'rss_item_count' => 0,
            'matched_count' => 0,
            'candidate_seen_count' => 0,
            'candidate_processed_count' => 0,
            'duplicate_candidate_count' => 0,
            'resolved_count' => 0,
            'unresolved_count' => 0,
            'rejected_count' => 0,
            'rejected_keyword' => 0,
            'rejected_short_content' => 0,
            'rejected_invalid_url' => 0,
            'partial_count' => 0,
            'error_count' => 0,
            'scraped_count' => 0,
            'reused_article_count' => 0,
            'new_article_count' => 0,
            'newly_inserted' => 0,
            'reused_existing' => 0,
            'attached_project_count' => 0,
            'processed_success_count' => 0,
            'saved_count' => 0,
            'skipped_count' => 0,
            'manual_saved_count' => 0,
            'google_saved_count' => 0,
            'discovery_source' => 'google_news',
        ];
        $this->runCandidateLogs = [];
        $this->seenCanonicalUrls = [];

        foreach ($projects as $project) {
            $keywords = $forceKeyword ? [$forceKeyword] : $project->scrapeKeywords();

            if (empty($keywords)) {
                $this->warn("Project [{$project->name}] has no keywords. Skipping.");
                continue;
            }

            $projectLock = Cache::lock('news:project-scrape:' . $project->id, 1800);
            if (! $projectLock->get()) {
                $this->warn("Project [{$project->name}] is already being processed. Skipping.");
                continue;
            }

            try {
                foreach ($keywords as $keyword) {
                    $this->info("Scraping news for: [{$project->name}] keyword=\"{$keyword}\"");
                    if ($directUrl !== '') {
                        $outcome = $this->scrapeDirectPortalUrl($directUrl, $project, $suppressTelegram, $suppressAi, $suppressReach);
                        $this->info(sprintf(
                            '  → Inserted %d / Reused %d',
                            $outcome['newly_inserted'] ?? 0,
                            $outcome['reused_existing'] ?? 0
                        ));
                        $totalInserted += (int) ($outcome['newly_inserted'] ?? 0);
                        $totalReused += (int) ($outcome['reused_existing'] ?? 0);
                        continue;
                    }
                    $outcome = $this->scrapeManualSourcesFirst(
                        keyword: $keyword,
                        project: $project,
                        limit: $limit,
                        suppressTelegram: $suppressTelegram,
                        suppressAi: $suppressAi,
                        suppressReach: $suppressReach,
                        sourceId: $filterSourceId ? (int) $filterSourceId : null,
                        discoveryMode: $discoveryMode,
                    );
                    $this->info(sprintf(
                        '  → Inserted %d / Reused %d',
                        $outcome['newly_inserted'] ?? 0,
                        $outcome['reused_existing'] ?? 0
                    ));
                    $portalLog->info('[Portal] Project keyword processed.', [
                        'project_id' => $project->id,
                        'project_name' => $project->name,
                        'keyword' => $keyword,
                        'inserted' => (int) ($outcome['newly_inserted'] ?? 0),
                        'reused' => (int) ($outcome['reused_existing'] ?? 0),
                        'source' => $discoveryMode,
                    ]);
                    $totalInserted += (int) ($outcome['newly_inserted'] ?? 0);
                    $totalReused += (int) ($outcome['reused_existing'] ?? 0);
                }
            } finally {
                $projectLock->release();
            }
        }

        $this->info('Discovery summary: ' . json_encode(array_merge($this->runStats, [
            'keyword' => $forceKeyword ?: 'project-topics',
            'requested_limit' => $limit,
            'discovered' => $this->runStats['discovered'] ?? $this->runStats['found_count'],
        ]), JSON_UNESCAPED_UNICODE));
        foreach ($this->runCandidateLogs as $candidateLog) {
            $this->line('Candidate: ' . json_encode($candidateLog, JSON_UNESCAPED_UNICODE));
        }
        $this->info(sprintf(
            'Total inserted %d / reused %d news article(s).',
            $totalInserted,
            $totalReused
        ));
        $portalLog->info('[Portal] Run finished.', [
            'inserted' => $totalInserted,
            'reused' => $totalReused,
            'stats' => $this->runStats,
        ]);
        } finally {
            $runLock->release();
        }
    }

    private function scrapeManualSourcesFirst(
        string $keyword,
        Project $project,
        int $limit,
        bool $suppressTelegram,
        bool $suppressAi,
        bool $suppressReach,
        ?int $sourceId = null,
        string $discoveryMode = 'auto'
    ): array {
        $manualSaved = 0;
        $manualInserted = 0;
        $manualReused = 0;
        $manualRejected = 0;
        $manualPartial = 0;
        $manualError = 0;
        $manualRequested = in_array($discoveryMode, ['auto', 'manual'], true);
        $fallbackRequested = in_array($discoveryMode, ['auto', 'google_news'], true);

        if ($manualRequested) {
            foreach ($this->getManualPortalSources($sourceId) as $source) {
                $discoveryUrl = $this->buildDiscoveryUrl($source, $keyword);
                if (! $discoveryUrl) {
                    Log::info('[NewsPortal] Manual portal source skipped: missing discovery URL.', [
                        'source_id' => $source->id,
                        'source' => $source->name,
                        'keyword' => $keyword,
                    ]);
                    continue;
                }

                try {
                    $this->beginProjectFirstAttempt($project);
                    $response = Http::timeout((int) ($source->timeout_seconds ?? 15))
                        ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36'])
                        ->get($discoveryUrl);

                    if (! $response->successful()) {
                        Log::warning('[NewsPortal] Manual portal discovery failed.', [
                            'source_id' => $source->id,
                            'source' => $source->name,
                            'keyword' => $keyword,
                            'status' => $response->status(),
                        ]);
                        continue;
                    }

                    $candidateUrls = $this->extractArticleUrlsFromDiscovery($source, (string) $response->body(), max($limit * 3, $limit));
                    if (empty($candidateUrls)) {
                        Log::info('[NewsPortal] Manual portal source returned no valid candidate URLs.', [
                            'source_id' => $source->id,
                            'source' => $source->name,
                            'keyword' => $keyword,
                        ]);
                        continue;
                    }

                    $this->runStats['discovery_source'] = 'manual_portal';
                    $this->runStats['found_count'] += count($candidateUrls);
                    $this->runStats['candidate_seen_count'] += count($candidateUrls);
                    $this->runStats['discovered'] += count($candidateUrls);

                    foreach ($candidateUrls as $index => $candidateUrl) {
                        if ($manualSaved >= $limit) {
                            break 2;
                        }

                        $outcome = $this->processPortalCandidate(
                            project: $project,
                            candidateUrl: $candidateUrl,
                            discoveryUrl: $candidateUrl,
                            discoveryTitle: '',
                            discoverySourceName: $source->name,
                            discoveryPublishedAt: null,
                            sourceType: 'manual_portal',
                            suppressTelegram: $suppressTelegram,
                            suppressAi: $suppressAi,
                            suppressReach: $suppressReach,
                            candidateIndex: $index,
                            keyword: $keyword,
                        );
                        $manualSaved += (int) (($outcome['newly_inserted'] ?? 0) + ($outcome['reused_existing'] ?? 0));
                        $manualInserted += (int) ($outcome['newly_inserted'] ?? 0);
                        $manualReused += (int) ($outcome['reused_existing'] ?? 0);
                        $manualRejected += (int) ($outcome['rejected'] ?? 0);
                        $manualPartial += (int) ($outcome['partial'] ?? 0);
                        $manualError += (int) ($outcome['error'] ?? 0);
                    }
                } catch (\Throwable $e) {
                    Log::warning('[NewsPortal] Manual portal discovery exception.', [
                        'source_id' => $source->id,
                        'source' => $source->name,
                        'keyword' => $keyword,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $googleSaved = 0;
        if ($fallbackRequested) {
            $this->info(sprintf(
                '  → manual discovery completed (Inserted %d / Reused %d). Starting Google News fallback stage.',
                $manualInserted,
                $manualReused
            ));

            $googleOutcome = $this->scrapeGoogleNews($keyword, $project, $limit, $suppressTelegram, $suppressAi, $suppressReach);
            $googleSaved = (int) (($googleOutcome['newly_inserted'] ?? 0) + ($googleOutcome['reused_existing'] ?? 0));
            if ($googleSaved > 0) {
                $this->runStats['discovery_source'] = $manualRequested ? 'manual_portal+google_news' : 'google_news_fallback';
            }
            Log::channel('google_news')->info('[GoogleNews] Fallback stage finished.', [
                'project_id' => $project->id,
                'keyword' => $keyword,
                'inserted' => (int) ($googleOutcome['newly_inserted'] ?? 0),
                'reused' => (int) ($googleOutcome['reused_existing'] ?? 0),
                'rejected' => (int) ($googleOutcome['rejected'] ?? 0),
                'partial' => (int) ($googleOutcome['partial'] ?? 0),
                'error' => (int) ($googleOutcome['error'] ?? 0),
            ]);
            $manualInserted += (int) ($googleOutcome['newly_inserted'] ?? 0);
            $manualReused += (int) ($googleOutcome['reused_existing'] ?? 0);
            $manualRejected += (int) ($googleOutcome['rejected'] ?? 0);
            $manualPartial += (int) ($googleOutcome['partial'] ?? 0);
            $manualError += (int) ($googleOutcome['error'] ?? 0);
        }

        $this->runStats['manual_saved_count'] += $manualSaved;
        $this->runStats['google_saved_count'] += $googleSaved;

        return [
            'newly_inserted' => $manualInserted,
            'reused_existing' => $manualReused,
            'rejected' => $manualRejected,
            'partial' => $manualPartial,
            'error' => $manualError,
        ];
    }

    private function scrapeGoogleNews(
        string $keyword,
        Project $project,
        int $limit = 3,
        bool $suppressTelegram = false,
        bool $suppressAi = false,
        bool $suppressReach = false
    ): array
    {
        $projectId = $project->id;
        // Google News RSS – supports Bahasa Indonesia (hl=id&gl=ID)
        $url = 'https://news.google.com/rss/search?' . http_build_query([
            'q'    => $keyword,
            'hl'   => 'id',
            'gl'   => 'ID',
            'ceid' => 'ID:id',
            'num'  => 30,
        ]);

        try {
            $this->beginProjectFirstAttempt($project);
            $response = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36'])
                ->get($url);

            if (!$response->successful()) {
                Log::error("[NewsPortal] Google News RSS failed: HTTP {$response->status()} for keyword: {$keyword}");
                Log::channel('google_news')->error('[GoogleNews] RSS failed.', [
                    'keyword' => $keyword,
                    'status' => $response->status(),
                    'status_label' => 'Gagal ambil RSS',
                    'message' => 'Google News tidak bisa diambil. Periksa koneksi, blokir, atau status HTTP.',
                ]);
                return [
                    'newly_inserted' => 0,
                    'reused_existing' => 0,
                    'rejected' => 0,
                    'partial' => 0,
                    'error' => 1,
                ];
            }

            // Parse RSS XML
            $xml = simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);

            if (!$xml || !isset($xml->channel->item)) {
                Log::warning("[NewsPortal] No items found in RSS for keyword: {$keyword}");
                Log::channel('google_news')->warning('[GoogleNews] RSS empty.', [
                    'keyword' => $keyword,
                    'status_label' => 'RSS kosong',
                    'message' => 'RSS terbaca, tetapi tidak ada item berita yang bisa diproses.',
                ]);
                return [
                    'newly_inserted' => 0,
                    'reused_existing' => 0,
                    'rejected' => 0,
                    'partial' => 0,
                    'error' => 1,
                ];
            }

            $saved = 0;
            $inserted = 0;
            $reused = 0;
            $rejected = 0;
            $partial = 0;
            $error = 0;
            $rssItems = [];
            if (isset($xml->channel->item)) {
                foreach ($xml->channel->item as $rssItem) {
                    $rssItems[] = $rssItem;
                }
            }
            $items = collect($rssItems)->take(max($limit * 10, 10))->values();
            $this->runStats['rss_item_count'] += $items->count();
            $this->runStats['candidate_seen_count'] += $items->count();
            $this->runStats['found_count'] += $items->count();
            $this->runStats['discovered'] += $items->count();
            if ($items->isNotEmpty()) {
                $this->beginProjectFirstAttempt($project);
            }

            foreach ($items as $index => $item) {
                if ($saved >= $limit) {
                    break;
                }

                $title      = (string) $item->title;
                $googleLink = (string) $item->link;
                $pubDate    = (string) $item->pubDate;
                $sourceName = (string) ($item->source ?? 'Google News');
                $description = strip_tags((string) ($item->description ?? ''));
                $discoveryUrl = !empty($googleLink) ? $googleLink : null;

                if (empty($title)) {
                    $this->runStats['skipped_count']++;
                    $this->runStats['error_count']++;
                    $error++;
                    $this->runCandidateLogs[] = [
                        'index' => $index,
                        'original_url' => $discoveryUrl ?? '-',
                        'resolved_url' => null,
                        'candidate_link_id' => null,
                        'scraping_item_id' => null,
                        'article_id' => null,
                        'final_status' => 'skipped',
                        'reason' => 'Empty title from discovery item',
                    ];
                    continue;
                }

                if (!$this->shouldTrustDiscoveryKeyword('google_news') && !$this->articleMatchesProjectKeywords($project, $title, $description)) {
                    $this->runStats['skipped_count']++;
                    $this->runStats['rejected_count']++;
                    $this->runStats['rejected_keyword']++;
                    $rejected++;
                    $this->runCandidateLogs[] = [
                        'index' => $index,
                        'original_url' => $discoveryUrl ?? '-',
                        'resolved_url' => null,
                        'candidate_link_id' => null,
                        'scraping_item_id' => null,
                        'article_id' => null,
                        'final_status' => 'skipped',
                        'reason' => 'Keyword filter did not match',
                        'title_final' => $title,
                    ];
                    continue;
                }
                $this->runStats['matched_count']++;

                $articleUrl = $discoveryUrl;

                if (empty($articleUrl)) {
                    $this->runStats['unresolved_count']++;
                    $this->runStats['skipped_count']++;
                    $this->runStats['rejected_count']++;
                    $this->runStats['rejected_invalid_url']++;
                    $error++;
                    $this->runCandidateLogs[] = [
                        'index' => $index,
                        'original_url' => $discoveryUrl ?? '-',
                        'resolved_url' => null,
                        'candidate_link_id' => null,
                        'scraping_item_id' => null,
                        'article_id' => null,
                        'final_status' => 'unresolved',
                        'reason' => 'No portal URL resolved from discovery',
                    ];
                    continue;
                }
                $publishedAt = null;
                try {
                    $publishedAt = \Carbon\Carbon::parse($pubDate);
                } catch (\Exception $e) {}

            $outcome = $this->processPortalCandidate(
                project: $project,
                candidateUrl: $articleUrl,
                discoveryUrl: $discoveryUrl,
                discoveryTitle: $title,
                discoverySourceName: $sourceName,
                discoveryPublishedAt: $publishedAt,
                sourceType: 'google_news',
                suppressTelegram: $suppressTelegram,
                suppressAi: $suppressAi,
                suppressReach: $suppressReach,
                candidateIndex: $index,
                keyword: $keyword,
            );
            $inserted += (int) ($outcome['newly_inserted'] ?? 0);
            $reused += (int) ($outcome['reused_existing'] ?? 0);
            $rejected += (int) ($outcome['rejected'] ?? 0);
            $partial += (int) ($outcome['partial'] ?? 0);
            $error += (int) ($outcome['error'] ?? 0);
            $saved += (int) (($outcome['newly_inserted'] ?? 0) + ($outcome['reused_existing'] ?? 0));
        }

            return [
                'newly_inserted' => $inserted,
                'reused_existing' => $reused,
                'rejected' => $rejected,
                'partial' => $partial,
                'error' => $error,
            ];
        } catch (\Exception $e) {
            Log::error("[NewsPortal] Exception for keyword \"{$keyword}\": " . $e->getMessage());
            Log::channel('google_news')->error('[GoogleNews] Tahap cadangan gagal.', [
                'keyword' => $keyword,
                'status_label' => 'Error proses',
                'message' => 'Proses Google News terhenti karena error teknis saat memproses kandidat.',
                'error_message' => $e->getMessage(),
            ]);
            return [
                'newly_inserted' => 0,
                'reused_existing' => 0,
                'rejected' => 0,
                'partial' => 0,
                'error' => 1,
            ];
        }
    }

    private function processPortalCandidate(
        Project $project,
        string $candidateUrl,
        ?string $discoveryUrl,
        string $discoveryTitle,
        string $discoverySourceName,
        ?Carbon $discoveryPublishedAt,
        string $sourceType,
        bool $suppressTelegram,
        bool $suppressAi,
        bool $suppressReach,
        int $candidateIndex = 0,
        ?string $keyword = null
    ): array {
        $this->runStats['candidate_processed_count']++;
        $fetchResult = $this->fetchFullContent($candidateUrl, $project, $keyword);
        $resolvedPortalUrl = !empty($fetchResult['resolved_url']) ? $fetchResult['resolved_url'] : $candidateUrl;
        $canonicalUrl = !empty($fetchResult['canonical_url']) ? $fetchResult['canonical_url'] : $resolvedPortalUrl;
        $finalArticleUrl = $canonicalUrl ?: $resolvedPortalUrl;
        $finalContent = !empty($fetchResult['content']) ? $fetchResult['content'] : $discoveryTitle;
        $finalTitle = $fetchResult['title'] ?: $discoveryTitle;
        $finalSourceName = $fetchResult['source_name'] ?: $discoverySourceName;
        $finalPublishedAt = $fetchResult['published_at'] ?? $discoveryPublishedAt;
        $resolutionTrace = $fetchResult['resolution_trace'] ?? [];
        $contentLength = mb_strlen(trim($finalContent));
        $candidateLinkId = null;
        $scrapingItemId = null;

        if ($canonicalUrl !== '' && isset($this->seenCanonicalUrls[$canonicalUrl])) {
            $this->runStats['duplicate_candidate_count']++;
            $this->runStats['skipped_count']++;
            $candidateLinkId = DB::table('candidate_links')->where('canonical_url', $canonicalUrl ?: $candidateUrl)->value('id');
            $scrapingItemId = $candidateLinkId ? ScrapingItem::where('candidate_link_id', $candidateLinkId)->value('id') : null;
            $this->runCandidateLogs[] = [
                'index' => $candidateIndex,
                'original_url' => $discoveryUrl ?? $candidateUrl,
                'resolved_url' => $canonicalUrl,
                'candidate_link_id' => $candidateLinkId,
                'scraping_item_id' => $scrapingItemId,
                'article_id' => null,
                'final_status' => 'duplicate',
                'reason' => 'Canonical URL already processed in this cycle',
                'title_final' => $finalTitle,
                'canonical_url_final' => $canonicalUrl,
                'source_name_final' => $finalSourceName,
                'content_length' => $contentLength,
                'resolution_trace' => $resolutionTrace,
            ];

            unset($fetchResult, $finalContent);
            return [
                'status' => 'duplicate',
                'newly_inserted' => 0,
                'reused_existing' => 0,
                'rejected' => 0,
                'partial' => 0,
                'error' => 0,
            ];
        }

        if ($sourceType === 'google_news' && empty($fetchResult['resolved_url'])) {
            $this->runStats['unresolved_count']++;
        } elseif ($candidateUrl === $canonicalUrl) {
            $this->runStats['unresolved_count']++;
        } else {
            $this->runStats['resolved_count']++;
        }

        \Illuminate\Support\Facades\Log::channel('portal_manual')->info('[Portal] Scraping candidate article details.', [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'keyword' => $keyword ?: 'N/A',
            'url' => $canonicalUrl,
            'original_url' => $discoveryUrl ?? $candidateUrl,
            'source_type' => $sourceType,
            'title' => $finalTitle,
            'content_length' => $contentLength,
        ]);

        $this->line(sprintf(
            '    → discovery=%s | original=%s | resolved=%s | title="%s" | source="%s" | published_at=%s | content_length=%d',
            $sourceType,
            $discoveryUrl ?? $candidateUrl,
            $canonicalUrl,
            $finalTitle,
            $finalSourceName,
            optional($finalPublishedAt)?->toIso8601String() ?? 'null',
            $contentLength
        ));

        if (! $this->isFinalPortalArticleUrl($canonicalUrl)) {
            $this->markPortalCandidateRejected(
                url: $discoveryUrl ?? $candidateUrl,
                canonicalUrl: $canonicalUrl,
                projectId: $project->id,
                reason: 'Resolved URL is not a valid portal article',
                title: $discoveryTitle,
                sourceType: $sourceType,
            );
            $this->runStats['rejected_count']++;
            $this->runStats['rejected_invalid_url']++;
            $this->runStats['skipped_count']++;
            $candidateLinkId = DB::table('candidate_links')->where('canonical_url', $canonicalUrl ?: $candidateUrl)->value('id');
            $scrapingItemId = $candidateLinkId ? ScrapingItem::where('candidate_link_id', $candidateLinkId)->value('id') : null;
            $this->runCandidateLogs[] = [
                'index' => $candidateIndex,
                'original_url' => $discoveryUrl ?? $candidateUrl,
                'resolved_url' => $canonicalUrl,
                'candidate_link_id' => $candidateLinkId,
                'scraping_item_id' => $scrapingItemId,
                'article_id' => null,
                'final_status' => 'rejected',
                'reason' => 'Resolved URL is not a valid portal article',
                'title_final' => $finalTitle,
                'canonical_url_final' => $canonicalUrl,
                'source_name_final' => $finalSourceName,
                'content_length' => $contentLength,
                'resolution_trace' => $resolutionTrace,
            ];

            unset($fetchResult, $finalContent);
            return [
                'status' => 'rejected',
                'newly_inserted' => 0,
                'reused_existing' => 0,
                'rejected' => 1,
                'partial' => 0,
                'error' => 0,
            ];
        }

        if (!$this->shouldTrustDiscoveryKeyword($sourceType) && !$this->articleMatchesProjectKeywords($project, $finalTitle, $finalContent)) {
            $this->markPortalCandidateRejected(
                url: $discoveryUrl ?? $candidateUrl,
                canonicalUrl: $canonicalUrl,
                projectId: $project->id,
                reason: 'Keyword filter did not match in final scraped content',
                title: $finalTitle,
                sourceType: $sourceType,
            );
            $this->runStats['rejected_count']++;
            $this->runStats['rejected_keyword']++;
            $this->runStats['skipped_count']++;
            $candidateLinkId = DB::table('candidate_links')->where('canonical_url', $canonicalUrl ?: $candidateUrl)->value('id');
            $scrapingItemId = $candidateLinkId ? ScrapingItem::where('candidate_link_id', $candidateLinkId)->value('id') : null;
            $this->runCandidateLogs[] = [
                'index' => $candidateIndex,
                'original_url' => $discoveryUrl ?? $candidateUrl,
                'resolved_url' => $canonicalUrl,
                'candidate_link_id' => $candidateLinkId,
                'scraping_item_id' => $scrapingItemId,
                'article_id' => null,
                'final_status' => 'skipped',
                'reason' => 'Keyword filter did not match in final scraped content',
                'title_final' => $finalTitle,
                'canonical_url_final' => $canonicalUrl,
                'source_name_final' => $finalSourceName,
                'content_length' => $contentLength,
                'resolution_trace' => $resolutionTrace,
            ];

            unset($fetchResult, $finalContent);
            return [
                'status' => 'rejected',
                'newly_inserted' => 0,
                'reused_existing' => 0,
                'rejected' => 1,
                'partial' => 0,
                'error' => 0,
            ];
        }

        if ($contentLength < self::MIN_FULL_CONTENT_LENGTH) {
            $this->markShortContentAsPartial(
                url: $discoveryUrl ?? $candidateUrl,
                canonicalUrl: $canonicalUrl,
                projectId: $project->id,
                content: $finalContent,
                title: $discoveryTitle,
                sourceType: $sourceType,
            );
            $this->runStats['partial_count']++;
            $this->runStats['rejected_short_content']++;
            $this->runStats['skipped_count']++;
            $candidateLinkId = DB::table('candidate_links')->where('canonical_url', $canonicalUrl ?: $candidateUrl)->value('id');
            $scrapingItemId = $candidateLinkId ? ScrapingItem::where('candidate_link_id', $candidateLinkId)->value('id') : null;
            $this->runCandidateLogs[] = [
                'index' => $candidateIndex,
                'original_url' => $discoveryUrl ?? $candidateUrl,
                'resolved_url' => $canonicalUrl,
                'candidate_link_id' => $candidateLinkId,
                'scraping_item_id' => $scrapingItemId,
                'article_id' => null,
                'final_status' => 'partial',
                'reason' => 'Content too short for final article',
                'title_final' => $finalTitle,
                'canonical_url_final' => $canonicalUrl,
                'source_name_final' => $finalSourceName,
                'content_length' => $contentLength,
                'resolution_trace' => $resolutionTrace,
            ];

            unset($fetchResult, $finalContent);
            return [
                'status' => 'partial',
                'newly_inserted' => 0,
                'reused_existing' => 0,
                'rejected' => 0,
                'partial' => 1,
                'error' => 0,
            ];
        }

        $existingArticleId = Article::where('canonical_url', $canonicalUrl)->value('id');
        $articleResult = $this->stagePipelineArticle(
            project: $project,
            title: $finalTitle,
            url: $finalArticleUrl,
            canonicalUrl: $canonicalUrl,
            content: $finalContent,
            sourceName: $finalSourceName,
            publishedAt: $finalPublishedAt,
            discoveryUrl: $discoveryUrl,
            sourceType: $sourceType,
        );
        $articleId = $articleResult['article_id'] ?? null;

        if ($articleId === null) {
            $this->runStats['duplicate_candidate_count']++;
            $this->runStats['skipped_count']++;
            $candidateLinkId = DB::table('candidate_links')->where('canonical_url', $canonicalUrl)->value('id');
            $scrapingItemId = $candidateLinkId ? ScrapingItem::where('candidate_link_id', $candidateLinkId)->value('id') : null;
            $this->runCandidateLogs[] = [
                'index' => $candidateIndex,
                'original_url' => $discoveryUrl ?? $candidateUrl,
                'resolved_url' => $canonicalUrl,
                'candidate_link_id' => $candidateLinkId,
                'scraping_item_id' => $scrapingItemId,
                'article_id' => null,
                'final_status' => 'duplicate',
                'reason' => 'Canonical URL already processed and not attached',
                'title_final' => $finalTitle,
                'canonical_url_final' => $canonicalUrl,
                'source_name_final' => $finalSourceName,
                'content_length' => $contentLength,
            ];

            unset($fetchResult, $finalContent);
            return [
                'status' => 'duplicate',
                'newly_inserted' => 0,
                'reused_existing' => 0,
                'rejected' => 0,
                'partial' => 0,
                'error' => 0,
            ];
        }

        $this->runStats['scraped_count']++;
        if ($existingArticleId) {
            $this->runStats['reused_article_count']++;
            $this->runStats['reused_existing']++;
        } else {
            $this->runStats['new_article_count']++;
            $this->runStats['saved_count']++;
            $this->runStats['newly_inserted']++;
        }
        $this->runStats['attached_project_count']++;
        $this->runStats['processed_success_count']++;
        $this->seenCanonicalUrls[$canonicalUrl] = true;

        $reachResult = $this->persistReachAssessment($project, $articleId, $suppressReach);
        $candidateLinkId = DB::table('candidate_links')->where('canonical_url', $canonicalUrl)->value('id');
        $scrapingItemId = $candidateLinkId ? ScrapingItem::where('candidate_link_id', $candidateLinkId)->value('id') : null;
        $this->runCandidateLogs[] = [
            'index' => $candidateIndex,
            'original_url' => $discoveryUrl ?? $candidateUrl,
            'resolved_url' => $canonicalUrl,
            'candidate_link_id' => $candidateLinkId,
            'scraping_item_id' => $scrapingItemId,
            'article_id' => $articleId,
            'final_status' => $existingArticleId ? 'reused' : 'scraped',
            'reason' => $existingArticleId ? 'Existing article reused' : 'New article saved',
            'title_final' => $finalTitle,
            'canonical_url_final' => $canonicalUrl,
            'source_name_final' => $finalSourceName,
            'content_length' => $contentLength,
            'reach_status' => $reachResult['reach_status'],
            'adjusted_local_reach_score' => $reachResult['adjusted_local_reach_score'],
            'adjusted_local_reach_level' => $reachResult['adjusted_local_reach_level'],
            'relevance_status' => $reachResult['relevance_status'],
            'confidence_score' => $reachResult['confidence_score'],
        ];

        $articleModel = $articleResult['article'] ?? Article::find($articleId);
        $articleChanged = (bool) ($articleResult['article_changed'] ?? false);
        $aiStatus = $this->dispatchAiAnalysisIfEligible(
            project: $project,
            articleId: $articleId,
            title: $finalTitle,
            url: $finalArticleUrl ?: $canonicalUrl,
            content: $finalContent,
            sourceName: $finalSourceName,
            publishedAt: $finalPublishedAt,
            suppressAi: $suppressAi,
            suppressTelegram: $suppressTelegram,
            articleChanged: $articleChanged,
            reusedArticle: (bool) $existingArticleId,
        );

        $this->runCandidateLogs[array_key_last($this->runCandidateLogs)]['ai_status'] = $aiStatus;

        unset($fetchResult, $finalContent);
        return [
            'status' => $existingArticleId ? 'reused' : 'scraped',
            'article_id' => $articleId,
            'newly_inserted' => $existingArticleId ? 0 : 1,
            'reused_existing' => $existingArticleId ? 1 : 0,
            'rejected' => 0,
            'partial' => 0,
            'error' => 0,
        ];
    }

    private function scrapeDirectPortalUrl(string $url, Project $project, bool $suppressTelegram = false, bool $suppressAi = false, bool $suppressReach = false): array
    {
        try {
            $this->beginProjectFirstAttempt($project);
            $fetchResult = $this->fetchFullContent($url, $project, null);
            $canonicalUrl = !empty($fetchResult['canonical_url']) ? $fetchResult['canonical_url'] : $url;
            $content = trim((string) ($fetchResult['content'] ?? ''));
            $title = trim((string) ($fetchResult['title'] ?? ''));
            $sourceName = trim((string) ($fetchResult['source_name'] ?? ''));
            $publishedAt = $fetchResult['published_at'] ?? null;

            if (! $this->isFinalPortalArticleUrl($canonicalUrl)) {
                $this->line(json_encode([
                    'status' => 'rejected',
                    'reason' => 'Direct URL did not resolve to a valid portal article',
                    'original_url' => $url,
                    'resolved_url' => $canonicalUrl,
                ], JSON_UNESCAPED_UNICODE));
                return [
                    'status' => 'rejected',
                    'newly_inserted' => 0,
                    'reused_existing' => 0,
                    'rejected' => 1,
                    'partial' => 0,
                    'error' => 0,
                ];
            }

            if (mb_strlen($content) < self::MIN_FULL_CONTENT_LENGTH) {
                $this->line(json_encode([
                    'status' => 'partial',
                    'reason' => 'Content too short for final article',
                    'original_url' => $url,
                    'resolved_url' => $canonicalUrl,
                    'content_length' => mb_strlen($content),
                ], JSON_UNESCAPED_UNICODE));
                return [
                    'status' => 'partial',
                    'newly_inserted' => 0,
                    'reused_existing' => 0,
                    'rejected' => 0,
                    'partial' => 1,
                    'error' => 0,
                ];
            }

            $existingArticleId = Article::where('canonical_url', $canonicalUrl)->value('id');
            $articleResult = $this->stagePipelineArticle(
                project: $project,
                title: $title ?: basename(parse_url($canonicalUrl, PHP_URL_PATH) ?: 'article'),
                url: $canonicalUrl,
                canonicalUrl: $canonicalUrl,
                content: $content,
                sourceName: $sourceName ?: ($project->name ?: 'unknown'),
                publishedAt: $publishedAt,
                discoveryUrl: $url,
                sourceType: 'direct_url',
            );
            $articleId = $articleResult['article_id'] ?? null;

            if ($articleId === null) {
                return [
                    'status' => 'duplicate',
                    'newly_inserted' => 0,
                    'reused_existing' => 0,
                    'rejected' => 0,
                    'partial' => 0,
                    'error' => 0,
                ];
            }

            $reachResult = $this->persistReachAssessment($project, $articleId, $suppressReach);
            $articleChanged = (bool) ($articleResult['article_changed'] ?? false);
            $aiStatus = $this->dispatchAiAnalysisIfEligible(
                project: $project,
                articleId: $articleId,
                title: $title,
                url: $canonicalUrl,
                content: $content,
                sourceName: $sourceName,
                publishedAt: $publishedAt,
                suppressAi: $suppressAi,
                suppressTelegram: $suppressTelegram,
                articleChanged: $articleChanged,
                reusedArticle: (bool) $existingArticleId,
            );
            $this->runCandidateLogs[] = [
                'index' => 0,
                'original_url' => $url,
                'resolved_url' => $canonicalUrl,
                'candidate_link_id' => DB::table('candidate_links')->where('canonical_url', $canonicalUrl)->value('id'),
                'scraping_item_id' => ScrapingItem::where('candidate_link_id', DB::table('candidate_links')->where('canonical_url', $canonicalUrl)->value('id'))->value('id'),
                'article_id' => $articleId,
                'final_status' => $existingArticleId ? 'reused' : 'scraped',
                'reason' => $existingArticleId ? 'Existing article reused' : 'New article saved',
                'title_final' => $title,
                'canonical_url_final' => $canonicalUrl,
                'source_name_final' => $sourceName,
                'content_length' => mb_strlen($content),
                'reach_status' => $reachResult['reach_status'],
                'adjusted_local_reach_score' => $reachResult['adjusted_local_reach_score'],
                'adjusted_local_reach_level' => $reachResult['adjusted_local_reach_level'],
                'relevance_status' => $reachResult['relevance_status'],
                'confidence_score' => $reachResult['confidence_score'],
                'ai_status' => $aiStatus,
            ];

            $this->line('Direct URL smoke test: ' . json_encode([
                'article_id' => $articleId,
                'reach_status' => $reachResult['reach_status'],
                'adjusted_local_reach_score' => $reachResult['adjusted_local_reach_score'],
                'adjusted_local_reach_level' => $reachResult['adjusted_local_reach_level'],
                'relevance_status' => $reachResult['relevance_status'],
                'confidence_score' => $reachResult['confidence_score'],
            ], JSON_UNESCAPED_UNICODE));

            return [
                'status' => $existingArticleId ? 'reused' : 'scraped',
                'article_id' => $articleId,
                'newly_inserted' => $existingArticleId ? 0 : 1,
                'reused_existing' => $existingArticleId ? 1 : 0,
                'rejected' => 0,
                'partial' => 0,
                'error' => 0,
            ];
        } catch (\Throwable $e) {
            Log::warning('[NewsPortal] Direct URL smoke test failed.', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return [
                'status' => 'error',
                'newly_inserted' => 0,
                'reused_existing' => 0,
                'rejected' => 0,
                'partial' => 0,
                'error' => 1,
            ];
        }
    }

    private function isFinalPortalArticleUrl(string $url): bool
    {
        if ($url === '' || $this->isGoogleUrl($url)) {
            return false;
        }

        return $this->isLikelyArticleUrl($url, new NewsSource(['domain' => parse_url($url, PHP_URL_HOST) ?: '']));
    }

    private function isGoogleUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        return $host === 'google.com'
            || str_ends_with($host, '.google.com')
            || $host === 'news.google.com';
    }

    private function markPortalCandidateRejected(string $url, string $canonicalUrl, int $projectId, string $reason, string $title = '', string $sourceType = 'google_news'): void
    {
        DB::table('candidate_links')->updateOrInsert(
            ['canonical_url' => $canonicalUrl ?: $url],
            [
                'url' => $url,
                'source_type' => $sourceType,
                'status' => 'rejected',
                'project_id' => $projectId,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $candidate = DB::table('candidate_links')->where('canonical_url', $canonicalUrl ?: $url)->first();
        if ($candidate) {
            ScrapingItem::updateOrCreate(
                ['candidate_link_id' => $candidate->id],
                [
                    'url' => $url,
                    'status' => 'rejected',
                    'retry_count' => 0,
                    'last_attempt_at' => now(),
                    'error_message' => trim($reason . ($title !== '' ? ' Title: ' . $title : '')),
                ]
            );
        }
    }

    private function stagePipelineArticle(
        Project $project,
        string $title,
        string $url,
        string $canonicalUrl,
        string $content,
        string $sourceName,
        ?\Carbon\Carbon $publishedAt,
        ?string $discoveryUrl = null,
        string $sourceType = 'google_news'
    ): array {
        if (! $this->isFinalPortalArticleUrl($canonicalUrl)) {
            return [
                'article_id' => null,
                'article' => null,
                'article_changed' => false,
            ];
        }

        DB::table('candidate_links')->updateOrInsert(
            ['canonical_url' => $canonicalUrl],
            [
                'url' => $discoveryUrl ?: $url,
                'source_type' => $sourceType,
                'status' => 'approved',
                'project_id' => $project->id,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $candidate = DB::table('candidate_links')->where('canonical_url', $canonicalUrl)->first();
        if (! $candidate) {
            return [
                'article_id' => null,
                'article' => null,
                'article_changed' => false,
            ];
        }

        ScrapingItem::updateOrCreate(
            ['candidate_link_id' => $candidate->id],
            [
                'url' => $url,
                'status' => 'scraped',
                'retry_count' => 0,
                'last_attempt_at' => now(),
                'error_message' => null,
            ]
        );

        $article = Article::firstOrNew(['canonical_url' => $canonicalUrl]);
        $payload = [
            'title' => $title,
            'content' => $content,
            'url' => $url,
            'source_name' => $sourceName,
            'published_at' => $publishedAt,
        ];

        if (! $article->exists) {
            $payload['sentiment'] = 'neutral';
            $payload['category'] = 'news';
        }

        $articleChanged = ! $article->exists || $this->articleFieldsChanged($article, $payload);
        $article->fill($payload);

        if ($articleChanged) {
            $article->save();
        }

        // Cross-link to ALL active projects that match the keywords (Bank Berita Concept)
        $matchingService = app(\App\Services\ContentMatchingService::class);
        $matchingService->crossLinkToActiveProjects($article, $project->id);

        return [
            'article_id' => $article->id,
            'article' => $article,
            'article_changed' => $articleChanged,
        ];
    }

    private function dispatchAiAnalysisIfEligible(
        Project $project,
        int $articleId,
        string $title,
        string $url,
        string $content,
        string $sourceName,
        ?Carbon $publishedAt,
        bool $suppressAi,
        bool $suppressTelegram,
        bool $articleChanged,
        bool $reusedArticle
    ): string {
        if (! $this->isFinalPortalArticleUrl($url)) {
            return 'skipped_existing';
        }

        if ($suppressAi) {
            return 'skipped_existing';
        }

        if (empty($articleId) || empty($project->id)) {
            \Illuminate\Support\Facades\Log::warning('[Portal Scraping] Skipped AI dispatch: missing article_id or project_id.', [
                'article_id' => $articleId,
                'project_id' => $project->id ?? null,
            ]);
            return 'skipped_existing';
        }

        $payload = [
            'type' => 'article',
            'id' => $articleId,
            'project_id' => $project->id,
            'title' => $title,
            'url' => $url,
            'content' => $content,
            'source_name' => $sourceName,
            'published_at' => optional($publishedAt)?->toIso8601String(),
            'no_telegram' => $suppressTelegram,
        ];

        $promptTemplateId = $this->aiAnalysisDispatchStateService->resolvePromptTemplateId('article');
        $providerContextHash = $this->aiAnalysisDispatchStateService->resolveProviderContextHash();
        $decision = $this->aiAnalysisDispatchStateService->reserveQueuedStateAndDispatch($payload, $promptTemplateId, $providerContextHash);

        if (! ($decision['should_dispatch'] ?? false)) {
            return match ($decision['status'] ?? 'skipped_existing') {
                'success' => 'skipped_existing',
                'retry_wait' => 'retry_wait',
                'failed' => 'failed',
                'queued', 'processing' => 'skipped_existing',
                default => 'skipped_existing',
            };
        }

        Cache::put("ai_analysis_lock_article_{$articleId}", true, now()->addMinutes(self::AI_ANALYSIS_CACHE_LOCK_MINUTES));

        return 'queued';
    }

    private function articleFieldsChanged(Article $article, array $payload): bool
    {
        $publishedAt = $this->normalizeComparableDateTime($payload['published_at'] ?? null);
        $currentPublishedAt = $this->normalizeComparableDateTime($article->published_at);

        return $this->normalizeComparableText((string) $article->title) !== $this->normalizeComparableText((string) ($payload['title'] ?? ''))
            || $this->normalizeComparableText((string) $article->content) !== $this->normalizeComparableText((string) ($payload['content'] ?? ''))
            || $this->normalizeComparableUrl((string) $article->url) !== $this->normalizeComparableUrl((string) ($payload['url'] ?? ''))
            || $this->normalizeComparableText((string) $article->source_name) !== $this->normalizeComparableText((string) ($payload['source_name'] ?? ''))
            || $currentPublishedAt !== $publishedAt;
    }

    private function normalizeComparableDateTime(mixed $value): ?string
    {
        if ($value instanceof Carbon) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m-d H:i:s');
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function normalizeComparableText(string $value): string
    {
        $value = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value);

        return mb_strtolower((string) $value);
    }

    private function normalizeComparableUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return rtrim($value, '/');
    }

    private function persistReachAssessment(Project $project, int $articleId, bool $suppressReach): array
    {
        // Deterministic reach is kept only as an internal baseline for now.
        // Do not auto-persist it from the main scraping pipeline.
        if (true) {
            return [
                'reach_status' => 'skipped',
                'adjusted_local_reach_score' => null,
                'adjusted_local_reach_level' => null,
                'relevance_status' => null,
                'confidence_score' => null,
            ];
        }
        return [
            'reach_status' => 'skipped',
            'adjusted_local_reach_score' => null,
            'adjusted_local_reach_level' => null,
            'relevance_status' => null,
            'confidence_score' => null,
        ];
    }

    private function beginProjectFirstAttempt(Project $project): void
    {
        $this->projectScrapePriorityService->recordAttempt($project);
    }

    private function markShortContentAsPartial(string $url, string $canonicalUrl, int $projectId, string $content, string $title, string $sourceType = 'google_news'): void
    {
        DB::table('candidate_links')->updateOrInsert(
            ['canonical_url' => $canonicalUrl],
            [
                'url' => $url,
                'source_type' => $sourceType,
                'status' => 'partial',
                'project_id' => $projectId,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $candidate = DB::table('candidate_links')->where('canonical_url', $canonicalUrl)->first();
        if ($candidate) {
            ScrapingItem::updateOrCreate(
                ['candidate_link_id' => $candidate->id],
                [
                    'url' => $url,
                    'status' => 'partial',
                    'retry_count' => 0,
                    'last_attempt_at' => now(),
                    'error_message' => 'Content too short for final article (' . mb_strlen(trim($content)) . ' chars). Title: ' . $title,
                ]
            );
        }
    }

    private function getManualPortalSources(?int $sourceId = null)
    {
        $query = NewsSource::query()
            ->where('is_active', true)
            ->whereIn('crawling_type', ['html', 'rss'])
            ->where(function ($builder) {
                $builder->whereNull('source_type')
                    ->orWhereNotIn('source_type', ['social_video', 'radio_tv']);
            })
            ->where(function ($builder) {
                $builder->where(function ($query) {
                    $query->where('is_search_enabled', true)
                        ->whereNotNull('search_url');
                })->orWhere(function ($query) {
                    $query->where('is_feed_enabled', true)
                        ->whereNotNull('feed_url');
                })->orWhere(function ($query) {
                    $query->where('is_sitemap_enabled', true)
                        ->whereNotNull('sitemap_url');
                });
            })
            ->orderByRaw('CASE WHEN scrape_priority IS NULL THEN 1 ELSE 0 END')
            ->orderBy('scrape_priority')
            ->orderBy('id');

        if ($sourceId) {
            $query->where('id', $sourceId);
        }

        return $query->get();
    }

    private function safeKeywordText(string $title, string $description, int $maxBytes = 8192): string
    {
        $truncatedDesc = mb_substr($description, 0, $maxBytes, 'UTF-8');
        return mb_strtolower(trim($title . ' ' . $truncatedDesc));
    }

    private function articleMatchesProjectKeywords(Project $project, string $title, string $description): bool
    {
        $haystack = $this->safeKeywordText($title, $description);
        $keywords = collect($project->topics ?? [])
            ->filter(fn ($keyword) => is_string($keyword) && trim($keyword) !== '')
            ->map(fn ($keyword) => mb_strtolower(trim($keyword)));

        foreach ($keywords as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return true;
            }

            $parts = array_values(array_filter(explode(' ', $keyword), fn ($part) => mb_strlen(trim($part)) >= 3));
            if (empty($parts)) {
                continue;
            }

            $matchedParts = 0;
            foreach ($parts as $part) {
                if (str_contains($haystack, $part)) {
                    $matchedParts++;
                }
            }

            if ($matchedParts === count($parts)) {
                return true;
            }
        }

        return false;
    }

    private function shouldTrustDiscoveryKeyword(string $sourceType): bool
    {
        return in_array($sourceType, ['manual_portal', 'google_news'], true);
    }

    private function fetchFullContent(string $url, ?Project $project = null, ?string $keyword = null): array
    {
        $resolutionTrace = [];
        $resolvedUrl = $this->resolvePortalUrl($url, $resolutionTrace);
        $canonicalUrl = $resolvedUrl ?: $url;
        $content = '';
        $title = '';
        $sourceName = '';
        $publishedAt = null;
        $transportTrace = [];

        try {
            $parsedUrl = parse_url($canonicalUrl);
            $host = $parsedUrl['host'] ?? '';
            
            // Bersihkan domain (misal: www.detik.com -> detik.com)
            $domain = preg_replace('/^www\./', '', strtolower($host));
            
            $source = NewsSource::where('is_active', true)
                ->where(function($q) use ($domain) {
                    $q->where('domain', $domain)
                      ->orWhere('domain', 'like', '%' . $domain . '%');
                })
                ->first();
                
            $rawResponse = $this->safePortalGet($canonicalUrl, $transportTrace);

            $rawHtml = $rawResponse->successful() ? $rawResponse->body() : '';

            $renderedHtml = $this->fetchRenderedHtml($canonicalUrl);
            $html = $rawHtml !== '' ? $rawHtml : $renderedHtml;

            if ($html === '' || mb_strlen(trim($this->extractReadableContent($html, $source))) < 200) {
                $alternativeHtml = $rawHtml !== '' && $renderedHtml !== '' ? $renderedHtml : $rawHtml;
                if ($alternativeHtml !== '' && mb_strlen(trim($this->extractReadableContent($alternativeHtml, $source))) > mb_strlen(trim($this->extractReadableContent($html, $source)))) {
                    $html = $alternativeHtml;
                }
            }

            if ($html === '') {
                return [
                    'content' => '',
                    'canonical_url' => $canonicalUrl ?: $url,
                    'resolved_url' => $resolvedUrl,
                    'resolution_trace' => $resolutionTrace,
                    'title' => '',
                    'source_name' => '',
                    'published_at' => null,
                ];
            }

            $inertiaData = $this->extractInertiaPageData($html);
            if ($inertiaData) {
                $props = $inertiaData['props'] ?? $inertiaData;
                $content = $this->findValueInArrayByKeys($props, ['content', 'body', 'article_body', 'isi', 'isi_berita']) ?? '';
                $content = trim(strip_tags(html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                $title = $this->findValueInArrayByKeys($props, ['title', 'heading', 'judul']) ?? '';
                $sourceName = $this->extractArticleSourceName($html, $source, $canonicalUrl);
                $dateStr = $this->findValueInArrayByKeys($props, ['published_at', 'created_at', 'date', 'tanggal']) ?? '';
                $publishedAt = null;
                if ($dateStr) {
                    try { $publishedAt = \Carbon\Carbon::parse($dateStr); } catch (\Exception $e) {}
                }
            } else {
                $content = $this->extractReadableContent($html, $source);
                $title = $this->extractArticleTitle($html, $source);
                $sourceName = $this->extractArticleSourceName($html, $source, $canonicalUrl);
                $publishedAt = $this->extractArticlePublishedAt($html);
            }

            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new \DOMXPath($dom);
            $canonicalNodes = $xpath->query('//link[@rel="canonical"]');
            if ($canonicalNodes && $canonicalNodes->length > 0) {
                $canonicalHref = trim($canonicalNodes->item(0)->getAttribute('href'));
                if (!empty($canonicalHref)) {
                    $canonicalUrl = $canonicalHref;
                }
            }

            if (!empty($canonicalUrl) && $canonicalUrl !== $url) {
                $canonicalResponse = $this->safePortalGet($canonicalUrl, $transportTrace);
                $canonicalRawHtml = $canonicalResponse->successful() ? $canonicalResponse->body() : '';

                $canonicalRenderedHtml = $this->fetchRenderedHtml($canonicalUrl);
                $canonicalHtml = $canonicalRawHtml !== '' ? $canonicalRawHtml : $canonicalRenderedHtml;

                if ($canonicalHtml === '' || mb_strlen(trim($this->extractReadableContent($canonicalHtml, $source))) < 200) {
                    $canonicalAlternativeHtml = $canonicalRawHtml !== '' && $canonicalRenderedHtml !== '' ? $canonicalRenderedHtml : $canonicalRawHtml;
                    if ($canonicalAlternativeHtml !== '' && mb_strlen(trim($this->extractReadableContent($canonicalAlternativeHtml, $source))) > mb_strlen(trim($this->extractReadableContent($canonicalHtml, $source)))) {
                        $canonicalHtml = $canonicalAlternativeHtml;
                    }
                }

                if ($canonicalHtml !== '') {
                    $canonicalContent = $this->extractReadableContent($canonicalHtml, $source);
                    $canonicalTitle = $this->extractArticleTitle($canonicalHtml, $source);
                    $canonicalSourceName = $this->extractArticleSourceName($canonicalHtml, $source, $canonicalUrl);
                    $canonicalPublishedAt = $this->extractArticlePublishedAt($canonicalHtml);
                    if (mb_strlen($canonicalContent) > mb_strlen($content)) {
                        $content = $canonicalContent;
                    }
                    if ($canonicalTitle !== '') {
                        $title = $canonicalTitle;
                    }
                    if ($canonicalSourceName !== '') {
                        $sourceName = $canonicalSourceName;
                    }
                    if ($canonicalPublishedAt !== null) {
                        $publishedAt = $canonicalPublishedAt;
                    }
                }
            }

            if (mb_strlen(trim($content)) < 200) {
                // Jika hasil masih terlalu pendek, tetap gunakan hasil terbaik yang ditemukan.
                $content = trim($content);
            }

            if (! empty($transportTrace)) {
                Log::info('[NewsPortal] Portal content fetch transport detail.', [
                    'url' => $url,
                    'canonical_url' => $canonicalUrl,
                    'project_id' => $project?->id,
                    'keyword' => $keyword,
                    'transport_trace' => $transportTrace,
                ]);
            }
            
        } catch (\Exception $e) {
            $projInfo = $project ? "Project: {$project->name} (ID: {$project->id})" : "Project: N/A";
            $kwInfo = $keyword ? "Keyword: {$keyword}" : "Keyword: N/A";
            Log::warning("[NewsPortal] Gagal mengambil konten penuh untuk URL: {$url}. {$projInfo}, {$kwInfo}. Error: " . $e->getMessage());
        }

        $result = [
            'content' => $content,
            'canonical_url' => $canonicalUrl,
            'resolved_url' => $resolvedUrl,
            'resolution_trace' => $resolutionTrace,
            'title' => $title,
            'source_name' => $sourceName,
            'published_at' => $publishedAt,
            'transport_trace' => $transportTrace,
        ];
        unset($rawHtml, $renderedHtml, $html, $canonicalHtml, $canonicalRawHtml, $canonicalRenderedHtml, $content, $title, $sourceName, $publishedAt, $resolvedUrl, $canonicalUrl, $resolutionTrace, $transportTrace);
        return $result;
    }

    private function extractArticlePublishedAt(string $html): ?Carbon
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);

        $queries = [
            '//meta[@property="article:published_time"]/@content',
            '//meta[@property="og:published_time"]/@content',
            '//meta[@name="datePublished"]/@content',
            '//time/@datetime',
        ];

        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes && $nodes->length > 0) {
                $raw = trim((string) $nodes->item(0)->textContent);
                if ($raw !== '') {
                    $parsed = $this->parseIndonesianDate($raw);
                    if ($parsed) {
                        return $parsed->utc();
                    }
                    Log::debug('[NewsPortal] Failed to parse published_at from HTML meta.', [
                        'raw' => $raw,
                    ]);
                }
            }
        }

        if (preg_match('~"datePublished"\s*:\s*"([^"]+)"~i', $html, $matches)) {
            $raw = trim($matches[1]);
            $parsed = $this->parseIndonesianDate($raw);
            if ($parsed) {
                return $parsed->utc();
            }
            Log::debug('[NewsPortal] Failed to parse published_at from JSON-LD.', [
                'raw' => $raw,
            ]);
        }

        return null;
    }

    private function extractArticleTitle(string $html, ?NewsSource $source = null): string
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);

        $queries = [
            '//meta[@property="og:title"]/@content',
            '//meta[@name="twitter:title"]/@content',
            '//meta[@name="title"]/@content',
            '//title',
        ];

        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes && $nodes->length > 0) {
                $raw = trim((string) $nodes->item(0)->textContent);
                $clean = $this->normalizeArticleTitle($raw);
                if ($clean !== '') {
                    return $clean;
                }
            }
        }

        if ($source && !empty($source->selector)) {
            $nodes = $xpath->query($this->convertSelectorToXPath($source->selector));
            if ($nodes && $nodes->length > 0) {
                $raw = trim((string) $nodes->item(0)->textContent);
                $clean = $this->normalizeArticleTitle($raw);
                if ($clean !== '') {
                    return $clean;
                }
            }
        }

        return '';
    }

    private function extractArticleSourceName(string $html, ?NewsSource $source, string $canonicalUrl): string
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);

        $queries = [
            '//meta[@property="og:site_name"]/@content',
            '//meta[@name="application-name"]/@content',
        ];

        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes && $nodes->length > 0) {
                $raw = trim((string) $nodes->item(0)->textContent);
                if ($raw !== '' && strlen($raw) < 50 && !str_contains($raw, ':') && !str_contains($raw, ' - ')) {
                    return $raw;
                }
            }
        }

        if ($source && filled($source->name)) {
            return $source->name;
        }

        $host = parse_url($canonicalUrl, PHP_URL_HOST) ?: '';
        return $host !== '' ? preg_replace('/^www\./', '', $host) : '';
    }

    private function normalizeArticleTitle(string $title): string
    {
        $title = html_entity_decode(trim($title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = preg_replace('/\s+/u', ' ', $title);
        $title = preg_replace('/\s*[|\-]\s*(?:Kompas\.com|Detikcom|detikcom|Katakaltim\.com|Kompas\.com)$/iu', '', $title);
        return trim((string) $title);
    }

    private function discoverPortalCandidates(string $keyword, int $limit): array
    {
        $candidates = [];
        $sources = NewsSource::query()
            ->where('is_active', true)
            ->whereIn('crawling_type', ['html', 'rss'])
            ->orderBy('name')
            ->take($limit)
            ->get();

        foreach ($sources as $source) {
            $discoveryUrl = $this->buildDiscoveryUrl($source, $keyword);
            if (! $discoveryUrl) {
                continue;
            }

            try {
                $response = Http::timeout((int) ($source->timeout_seconds ?? 15))
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36'])
                    ->get($discoveryUrl);

                if (!$response->successful()) {
                    continue;
                }

                $found = $this->extractArticleUrlsFromDiscovery($source, (string) $response->body(), $limit);
                foreach ($found as $candidate) {
                    if ($candidate && $this->isLikelyArticleUrl($candidate, $source) && !in_array($candidate, $candidates, true)) {
                        $candidates[] = $candidate;
                    }
                    if (count($candidates) >= $limit) {
                        break 2;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[NewsPortal] Portal discovery failed.', [
                    'source' => $source->domain,
                    'keyword' => $keyword,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $candidates;
    }

    private function buildDiscoveryUrl(NewsSource $source, string $keyword): ?string
    {
        if ($source->is_search_enabled && filled($source->search_url)) {
            return str_replace('{keyword}', rawurlencode($keyword), $source->search_url);
        }

        if ($source->is_feed_enabled && filled($source->feed_url)) {
            return str_replace('{keyword}', rawurlencode($keyword), $source->feed_url);
        }

        if ($source->is_sitemap_enabled && filled($source->sitemap_url)) {
            return $source->sitemap_url;
        }

        return null;
    }

    private function extractArticleUrlsFromDiscovery(NewsSource $source, string $body, int $limit = 3): array
    {
        $results = [];
        $selectors = array_values(array_filter([
            $source->article_link_selector,
            $source->search_result_selector,
        ]));

        $baseUrl = $source->base_url ?: ('https://' . $source->domain);

        foreach ($selectors as $selector) {
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $body);
            $xpath = new \DOMXPath($dom);
            $xpathQuery = $this->convertSelectorToXPath($selector);
            $nodes = $xpath->query($xpathQuery);
            if ($nodes && $nodes->length > 0) {
                foreach ($nodes as $node) {
                    $href = trim((string) $node->getAttribute('href'));
                    $resolved = $this->normalizeUrl($href, $baseUrl);
                    if ($resolved && $this->isLikelyArticleUrl($resolved, $source)) {
                        $results[] = $resolved;
                        if (count($results) >= $limit) {
                            return array_values(array_unique($results));
                        }
                    }
                }
            }
        }

        if ($source->is_feed_enabled && str_contains($body, '<item>')) {
            $xml = @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($xml && isset($xml->channel->item)) {
                foreach ($xml->channel->item as $item) {
                    $link = trim((string) ($item->link ?? ''));
                    $resolved = $this->normalizeUrl($link, $baseUrl);
                    if ($resolved && $this->isLikelyArticleUrl($resolved, $source)) {
                        $results[] = $resolved;
                        if (count($results) >= $limit) {
                            return array_values(array_unique($results));
                        }
                    }
                }
            }
        }

        // Try sitemap loc links if sitemap_url is used
        if ($source->is_sitemap_enabled && (str_contains($body, '<sitemap') || str_contains($body, '<urlset') || str_contains($body, '<loc>'))) {
            preg_match_all('~<loc>(.*?)</loc>~is', $body, $locMatches);
            foreach ($locMatches[1] as $loc) {
                $resolved = $this->normalizeUrl(trim($loc), $baseUrl);
                if ($resolved && $this->isLikelyArticleUrl($resolved, $source)) {
                    $results[] = $resolved;
                    if (count($results) >= $limit) {
                        return array_values(array_unique($results));
                    }
                }
            }
        }

        preg_match_all('~href=["\']([^"\']+)["\']~i', $body, $matches);
        foreach ($matches[1] as $href) {
            $resolved = $this->normalizeUrl($href, $baseUrl);
            if ($resolved && $this->isLikelyArticleUrl($resolved, $source)) {
                $results[] = $resolved;
                if (count($results) >= $limit) {
                    return array_values(array_unique($results));
                }
            }
        }

        return array_values(array_unique($results));
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

    private function belongsToDomain(string $url, string $domain): bool
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $host = preg_replace('/^www\./', '', strtolower($host));
        $domain = preg_replace('/^www\./', '', strtolower($domain));

        return $host !== '' && ($host === $domain || str_ends_with($host, '.' . $domain));
    }

    private function isLikelyArticleUrl(string $url, NewsSource $source): bool
    {
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            return false;
        }

        if (!$this->belongsToDomain($url, $source->domain)) {
            return false;
        }

        $lower = strtolower($url);
        $rejectFragments = [
            'connect.',
            '/connect',
            'login',
            'auth',
            'account',
            'register',
            '/search',
            '/tag',
            '/kategori',
            '/category',
            '/ads',
            '/static',
            '/asset',
            'javascript:',
            '#',
        ];

        foreach ($rejectFragments as $fragment) {
            if (str_contains($lower, $fragment)) {
                return false;
            }
        }

        if ($source->domain === 'detik.com') {
            if (!preg_match('~^https?://(?:www\.)?(?:[^/]+\.)?detik\.com/.+~i', $url)) {
                return false;
            }

            if (str_contains($lower, 'connect.detik.com')) {
                return false;
            }

            $allowPatterns = [
                '~/(?:berita|news|detiknews|jatim|jabar|jogja|sumut|kaltim|sulsel|bali|finance|sport|inet|oto|travel|health|edu|hot|food|sepakbola|wolipop|x)/~i',
                '~/(?:d-\d+\/[^\/?#]+)(?:[\/?#]|$)~i',
                '~/\d{4}/\d{2}/\d{2}/~',
                '~-[0-9]+(?:\?|$)~',
            ];

            foreach ($allowPatterns as $pattern) {
                if (preg_match($pattern, $url)) {
                    return true;
                }
            }

            return false;
        }

        if ($source->domain === 'kompas.com') {
            if (!preg_match('~^https?://(?:www\.)?(?:[^/]+\.)?kompas\.com/.+~i', $url)) {
                return false;
            }

            $allowPatterns = [
                '~/(?:read|tren|skola|regional|money|tekno|sport|food|bola|travel|edukasi|properti|otomotif|health|lifestyle)/~i',
                '~/\d{4}/\d{2}/\d{2}/~',
                '~-[0-9]+(?:\?|$)~',
            ];

            foreach ($allowPatterns as $pattern) {
                if (preg_match($pattern, $url)) {
                    return true;
                }
            }

            return false;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $path = trim($path, '/');
        if ($path === '') {
            return false;
        }

        $segments = array_values(array_filter(explode('/', $path)));
        if (count($segments) < 1) {
            return false;
        }

        $articleHints = ['berita', 'news', 'read', 'detail', 'artikel', 'article', 'story', 'news'];
        $pathLower = strtolower($path);
        foreach ($articleHints as $hint) {
            if (str_contains($pathLower, $hint)) {
                return true;
            }
        }

        if (preg_match('/\d{4}\/\d{2}\/\d{2}/', $pathLower)) {
            return true;
        }

        if (preg_match('/\b\d{4,}\b/', $pathLower)) {
            return true;
        }

        // Allow single segment if it looks like a long slug (contains multiple hyphens)
        if (count($segments) === 1 && substr_count($segments[0], '-') >= 3) {
            return true;
        }

        return count($segments) >= 2;
    }

    private function resolvePortalUrl(string $url, array &$trace = []): ?string
    {
        if ($this->isGoogleNewsUrl($url)) {
            $decoded = $this->googleNewsUrlDecoderService->decode($url);
            $trace = array_merge($trace, $decoded['trace'] ?? []);

            if ($decoded['success'] ?? false) {
                $trace[] = [
                    'stage' => 'google_decoder_success',
                    'url' => $decoded['original_url'],
                    'method' => $decoded['method'] ?? null,
                ];

                return $decoded['original_url'];
            }

            $trace[] = [
                'stage' => 'google_decoder_failed',
                'error' => $decoded['error'] ?? 'Google decoder failed',
                'method' => $decoded['method'] ?? null,
            ];
        }

        $attemptQueue = [$url];
        if (str_contains($url, 'news.google.com/rss/articles/')) {
            $attemptQueue[] = str_replace('/rss/articles/', '/articles/', $url);
        }

        $visited = [];

        foreach ($attemptQueue as $seedUrl) {
            $current = $seedUrl;

            for ($i = 0; $i < 4; $i++) {
                if (isset($visited[$current])) {
                    $trace[] = [
                        'stage' => 'visited_loop',
                        'url' => $current,
                    ];
                    break;
                }
                $visited[$current] = true;

                try {
                    $response = Http::timeout(15)
                        ->withoutRedirecting()
                        ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36'])
                        ->get($current);

                    $body = (string) $response->body();
                    $effectiveUrl = $response->effectiveUri() ? (string) $response->effectiveUri() : $current;
                    $trace[] = [
                        'stage' => 'http_get',
                        'requested_url' => $current,
                        'status' => $response->status(),
                        'effective_url' => $effectiveUrl,
                    ];

                    $location = trim((string) $response->header('Location'));
                    if ($response->status() >= 300 && $response->status() < 400 && filled($location)) {
                        $nextUrl = $this->normalizeUrl($location, $current);
                        $trace[] = [
                            'stage' => 'redirect_header',
                            'from' => $current,
                            'to' => $nextUrl,
                        ];
                        $current = $nextUrl ?: $location;
                        continue;
                    }

                    if (preg_match('/<meta[^>]+http-equiv=["\']refresh["\'][^>]+url=([^"\']+)/i', $body, $matches)) {
                        $metaUrl = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        $nextUrl = $this->normalizeUrl($metaUrl, $current);
                        if (filled($nextUrl)) {
                            $trace[] = [
                                'stage' => 'meta_refresh',
                                'from' => $current,
                                'to' => $nextUrl,
                            ];
                            $current = $nextUrl;
                            continue;
                        }
                    }

                    if ($effectiveUrl !== '' && !str_contains($effectiveUrl, 'google.com') && !str_contains($effectiveUrl, 'news.google.com')) {
                        $trace[] = [
                            'stage' => 'effective_uri_portal',
                            'url' => $effectiveUrl,
                        ];
                        return $effectiveUrl;
                    }

                    $portalCandidate = $this->extractPortalUrlFromHtml($body, $current, $trace);
                    if ($portalCandidate !== null) {
                        return $portalCandidate;
                    }

                    if (!str_contains($current, 'google.com') && !str_contains($current, 'news.google.com')) {
                        $trace[] = [
                            'stage' => 'current_portal',
                            'url' => $current,
                        ];
                        return $current;
                    }
                } catch (\Throwable $e) {
                    $trace[] = [
                        'stage' => 'exception',
                        'url' => $current,
                        'error' => $e->getMessage(),
                    ];
                    Log::warning('[NewsPortal] Failed to resolve portal URL.', [
                        'url' => $current,
                        'error' => $e->getMessage(),
                    ]);
                    break;
                }
            }
        }

        $trace[] = [
            'stage' => 'final_failure',
            'url' => $url,
            'reason' => 'No non-Google portal URL resolved',
        ];

        return null;
    }

    private function isGoogleNewsUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && (($parts['host'] ?? null) === 'news.google.com')
            && in_array($parts['scheme'] ?? null, ['http', 'https'], true);
    }

    private function extractPortalUrlFromHtml(string $html, string $baseUrl, array &$trace = []): ?string
    {
        $patterns = [
            '/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)["\']/i',
            '/<meta[^>]+property=["\']og:url["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<a[^>]+href=["\']([^"\']+)["\']/i',
        ];

        foreach ($patterns as $pattern) {
            if (!preg_match_all($pattern, $html, $matches)) {
                continue;
            }

            foreach (($matches[1] ?? []) as $rawUrl) {
                $normalized = $this->normalizeUrl(html_entity_decode(trim($rawUrl), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $baseUrl);
                if ($normalized === null) {
                    continue;
                }

                $host = strtolower((string) parse_url($normalized, PHP_URL_HOST));
                if ($host === '' || str_contains($host, 'google.com') || str_contains($host, 'gstatic.com') || str_contains($host, 'googlesyndication.com')) {
                    continue;
                }

                $trace[] = [
                    'stage' => 'html_portal_candidate',
                    'url' => $normalized,
                ];

                return $normalized;
            }
        }

        return null;
    }

    private function extractReadableContent(string $html, ?NewsSource $source): string
    {
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

        $selector = $source?->article_content_selector ?: ($source?->selector ?? null);
        if ($source && !empty($selector)) {
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new \DOMXPath($dom);

            $xpathQuery = $this->convertSelectorToXPath($selector);
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

        // 2. Try common article container selectors if specific source selector fails or is not defined
        $fallbackSelectors = [
            'article',
            '.entry-content',
            '.post-content',
            '.article-content',
            '.article-body',
            '.detail__body-text',
            '.read__right',
            'main'
        ];

        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($dom);

        foreach ($fallbackSelectors as $fbSelector) {
            $xpathQuery = $this->convertSelectorToXPath($fbSelector);
            $nodes = $xpath->query($xpathQuery);
            if ($nodes && $nodes->length > 0) {
                // Get the first matching container, usually the main article body
                $container = $nodes->item(0);
                // Extract paragraphs inside this container to avoid capturing sidebar links if any
                $paragraphs = $xpath->query('.//p', $container);
                if ($paragraphs && $paragraphs->length > 0) {
                    $text = '';
                    foreach ($paragraphs as $p) {
                        $text .= $p->textContent . "\n";
                    }
                    $cleaned = trim(preg_replace('/\s+/', ' ', strip_tags($text)));
                    if (mb_strlen($cleaned) > 200) {
                        return $cleaned;
                    }
                } else {
                    // If no <p> tags, just get the text content of the container
                    $cleaned = trim(preg_replace('/\s+/', ' ', strip_tags($container->textContent)));
                    if (mb_strlen($cleaned) > 200) {
                        return $cleaned;
                    }
                }
            }
        }

        // 3. Ultimate fallback: just get all <p> tags on the page
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $paragraphs = $dom->getElementsByTagName('p');
        $text = '';
        foreach ($paragraphs as $p) {
            $text .= $p->textContent . "\n";
        }

        return trim(preg_replace('/\s+/', ' ', strip_tags($text)));
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

    private function safePortalGet(string $url, array &$trace = []): \Illuminate\Http\Client\Response
    {
        $request = fn (bool $verify = true) => Http::timeout(15)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36'])
            ->when(! $verify, fn ($http) => $http->withoutVerifying())
            ->get($url);

        try {
            return $request(true);
        } catch (\Throwable $e) {
            if (! str_contains($e->getMessage(), 'cURL error 60')) {
                throw $e;
            }

            $trace[] = [
                'stage' => 'ssl_verify_failed',
                'url' => $url,
                'error' => $e->getMessage(),
            ];
            Log::warning('[NewsPortal] SSL verification failed, retrying without certificate check.', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            $response = $request(false);
            $trace[] = [
                'stage' => 'ssl_verify_fallback_used',
                'url' => $url,
                'status' => $response->status(),
            ];

            return $response;
        }
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
        $parts = explode(' ', trim($selector));
        $xpathParts = [];
        
        foreach ($parts as $part) {
            if (str_starts_with($part, '.')) {
                $className = substr($part, 1);
                $xpathParts[] = "//*[contains(@class, '{$className}')]";
            } elseif (str_starts_with($part, '#')) {
                $idName = substr($part, 1);
                $xpathParts[] = "//*[@id='{$idName}']";
            } else {
                if (str_contains($part, '.')) {
                    $subParts = explode('.', $part);
                    $tag = $subParts[0] ?: '*';
                    $className = $subParts[1];
                    $xpathParts[] = "//{$tag}[contains(@class, '{$className}')]";
                } else {
                    $xpathParts[] = "//{$part}";
                }
            }
        }
        
        if (count($xpathParts) === 1) {
            return $xpathParts[0];
        }
        
        $base = str_replace('//', '', $xpathParts[0]);
        $descendant = str_replace('//', '', $xpathParts[1]);
        return "//{$base}//{$descendant}";
    }

    /**
     * Write a string as information output with a timestamp.
     */
    public function line($string, $style = null, $verbosity = null)
    {
        $timestamp = '[' . now()->format('Y-m-d H:i:s') . ']';
        parent::line("{$timestamp} {$string}", $style, $verbosity);
    }

    private function extractInertiaPageData(string $html): ?array
    {
        if (preg_match('/data-page="([^"]+)"/', $html, $matches)) {
            $decoded = json_decode(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
            return is_array($decoded) ? $decoded : null;
        }
        return null;
    }

    private function findValueInArrayByKeys(array $arr, array $targetKeys): ?string
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

    private function parseIndonesianDate(string $raw): ?Carbon
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $rawCleaned = preg_replace('/^(?:senin|selasa|rabu|kamis|jum\'?at|sabtu|minggu)\s*,\s*/i', '', $raw);

        try {
            return Carbon::parse($rawCleaned);
        } catch (\Throwable $e) {}

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}/', $rawCleaned)) {
            try {
                return Carbon::createFromFormat('d/m/Y', substr($rawCleaned, 0, 10))->startOfDay();
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
            return Carbon::parse($lower);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
