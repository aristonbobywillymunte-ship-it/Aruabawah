<?php

namespace App\Console\Commands;

use App\Models\AiAnalysisResult;
use App\Services\AiAnalysisDispatchStateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RunNeedsRescrape extends Command
{
    protected $signature = 'scraping:run-needs-rescrape
                            {--ids= : Comma-separated exact article IDs}
                            {--project-id= : Specific project ID}
                            {--limit=1 : Maximum items to process}
                            {--apply : Actually run the recommended action instead of dry-run}
                            {--no-telegram : Suppress downstream Telegram notifications}';

    protected $description = 'Run small safe rescrape passes for content marked needs_rescrape.';

    public function __construct(
        private readonly AiAnalysisDispatchStateService $dispatchStateService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $projectId = $this->option('project-id');
        $projectId = is_numeric($projectId) ? (int) $projectId : null;
        $ids = array_values(array_filter(array_map('intval', explode(',', (string) $this->option('ids')))));
        $limit = max(1, (int) $this->option('limit'));
        $apply = (bool) $this->option('apply');
        $suppressTelegram = (bool) $this->option('no-telegram');

        $query = DB::table('project_articles as pa')
            ->join('articles as a', 'pa.article_id', '=', 'a.id')
            ->leftJoin('ai_analysis_results as ai', 'a.id', '=', 'ai.article_id')
            ->where('pa.rescrape_status', 'needs_rescrape');

        if ($ids !== []) {
            $query->whereIn('pa.article_id', $ids);
        }

        if ($projectId !== null) {
            $query->where('pa.project_id', $projectId);
        }

        $items = $query->orderBy('pa.project_id')->orderBy('pa.article_id')->limit($limit)->get([
            'pa.project_id',
            'pa.article_id',
            'pa.rescrape_reason',
            'pa.rescrape_source',
            'pa.rescrape_requested_at',
            'pa.rescrape_meta',
            'a.title',
            'a.url',
            'a.content',
            'a.source_name',
            'a.canonical_url',
            'ai.analysis_status',
            'ai.validation_errors',
            'ai.reach_method',
            'ai.project_estimated_readers',
            'ai.project_reach_score',
            'ai.project_reach_level',
            'ai.potential_estimated_readers',
            'ai.summary',
            'ai.sentiment',
            'ai.risk_level',
        ]);

        if ($items->isEmpty()) {
            $this->info('No needs_rescrape items found.');
            return self::SUCCESS;
        }

        foreach ($items as $item) {
            $classification = $this->classifyAction($item);

            $this->line(json_encode([
                'project_id' => (int) $item->project_id,
                'article_id' => (int) $item->article_id,
                'title' => $item->title,
                'source' => $item->source_name,
                'url' => $item->url,
                'current_status' => $item->rescrape_reason,
                'recommended_action' => $classification['action'],
                'can_finish_without_scrape' => $classification['can_finish_without_scrape'],
                'needs_ai_dispatch' => $classification['needs_ai_dispatch'],
                'needs_backfill' => $classification['needs_backfill'],
                'stay_needs_rescrape' => $classification['stay_needs_rescrape'],
                'display_ready' => $classification['display_ready'],
                'ai_analysis_status' => $item->analysis_status,
                'validation_errors' => $item->validation_errors,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            if (! $apply) {
                continue;
            }

            $projectIdValue = (int) $item->project_id;
            $articleIdValue = (int) $item->article_id;

            if ($classification['action'] === 'clear_stale_overlap') {
                $this->clearRescrape($projectIdValue, $articleIdValue);
                continue;
            }

            if ($classification['action'] === 'ai_success_but_reach_missing') {
                $this->applyBackfillDisplayReach($projectIdValue, $articleIdValue);
                $this->clearRescrape($projectIdValue, $articleIdValue);
                continue;
            }

            if ($classification['action'] === 'invalid_ai_reach_with_reader_metadata') {
                $this->normalizeInvalidAiReach($articleIdValue);
                $this->applyBackfillDisplayReach($projectIdValue, $articleIdValue);
                $this->clearRescrape($projectIdValue, $articleIdValue);
                continue;
            }

            if ($classification['action'] === 'no_ai_result_but_content_valid') {
                $this->dispatchAiForArticle(
                    projectId: $projectIdValue,
                    articleId: $articleIdValue,
                    title: (string) $item->title,
                    content: (string) $item->content,
                    url: (string) $item->url,
                    sourceName: (string) ($item->source_name ?? 'article'),
                    suppressTelegram: $suppressTelegram
                );
                continue;
            }

            $this->appendRescrapeNote($projectIdValue, $articleIdValue, $classification['action'], $item->rescrape_reason);
        }

        $this->info($apply ? 'Apply mode completed.' : 'Dry-run complete.');
        return self::SUCCESS;
    }

    private function classifyAction(object $item): array
    {
        $analysisStatus = (string) ($item->analysis_status ?? '');
        $reachMethod = (string) ($item->reach_method ?? '');
        $projectReaders = $item->project_estimated_readers;
        $potentialReaders = $item->potential_estimated_readers;
        $hasReaderMetadata = ($projectReaders !== null && (int) $projectReaders >= 1) || ($potentialReaders !== null && (int) $potentialReaders >= 1);

        $displayReady = $this->isDisplayReady($item);
        if ($displayReady) {
            return $this->actionRow('clear_stale_overlap', true, false, false, true, true);
        }

        if ($analysisStatus === 'success') {
            if ($reachMethod === 'ai_reader_estimate_v1' || $hasReaderMetadata) {
                return $this->actionRow('ai_success_but_reach_missing', true, false, true, true, false);
            }

            return $this->actionRow('true_scrape_needed', false, false, false, false, false);
        }

        if ($analysisStatus === 'invalid_ai_reach') {
            if ($hasReaderMetadata) {
                return $this->actionRow('invalid_ai_reach_with_reader_metadata', true, false, true, true, false);
            }

            return $this->actionRow('invalid_ai_reach_without_reader_metadata', false, false, false, false, false);
        }

        if ($analysisStatus === '' || $analysisStatus === null) {
            if (trim((string) ($item->content ?? '')) !== '') {
                return $this->actionRow('no_ai_result_but_content_valid', true, true, false, true, false);
            }

            return $this->actionRow('true_scrape_needed', false, false, false, false, false);
        }

        return $this->actionRow('true_scrape_needed', false, false, false, false, false);
    }

    private function actionRow(string $action, bool $canFinishWithoutScrape, bool $needsAiDispatch, bool $needsBackfill, bool $stayNeedsRescrape, bool $displayReady): array
    {
        return [
            'action' => $action,
            'can_finish_without_scrape' => $canFinishWithoutScrape,
            'needs_ai_dispatch' => $needsAiDispatch,
            'needs_backfill' => $needsBackfill,
            'stay_needs_rescrape' => $stayNeedsRescrape,
            'display_ready' => $displayReady,
        ];
    }

    private function isDisplayReady(object $item): bool
    {
        return ($item->analysis_status ?? null) === 'success'
            && ($item->reach_method ?? null) === 'ai_reader_estimate_v1'
            && ! is_null($item->project_estimated_readers)
            && (int) $item->project_estimated_readers >= 1
            && ! is_null($item->project_reach_score)
            && ! is_null($item->project_reach_level)
            && trim((string) ($item->summary ?? '')) !== ''
            && trim((string) ($item->sentiment ?? '')) !== ''
            && trim((string) ($item->risk_level ?? '')) !== '';
    }

    private function normalizeInvalidAiReach(int $articleId): void
    {
        $row = AiAnalysisResult::query()->where('article_id', $articleId)->first();
        if (! $row) {
            return;
        }

        $effectiveReaders = (int) ($row->project_estimated_readers ?? $row->potential_estimated_readers ?? 0);
        if ($effectiveReaders < 1) {
            return;
        }

        $row->forceFill([
            'analysis_status' => 'success',
            'project_estimated_readers' => $effectiveReaders,
            'project_reach_score' => AiAnalysisResult::officialProjectReachScoreForReaders($effectiveReaders),
            'project_reach_level' => AiAnalysisResult::officialProjectReachLevelForScore(AiAnalysisResult::officialProjectReachScoreForReaders($effectiveReaders)),
            'project_reach_band' => AiAnalysisResult::officialProjectReachBandForReaders($effectiveReaders),
            'reach_method' => 'ai_reader_estimate_v1',
            'updated_at' => now(),
        ])->save();
    }

    private function dispatchAiForArticle(int $projectId, int $articleId, string $title, string $content, string $url, string $sourceName, bool $suppressTelegram): void
    {
        $payload = [
            'type' => 'article',
            'id' => $articleId,
            'project_id' => $projectId,
            'title' => $title,
            'content' => $content,
            'url' => $url,
            'source_name' => $sourceName ?: 'article',
            'published_at' => null,
            'no_telegram' => $suppressTelegram,
        ];

        $promptTemplateId = $this->dispatchStateService->resolvePromptTemplateId('article');
        $providerContextHash = $this->dispatchStateService->resolveProviderContextHash();
        $this->dispatchStateService->reserveQueuedStateAndDispatch($payload, $promptTemplateId, $providerContextHash);
    }

    private function applyBackfillDisplayReach(int $projectId, int $articleId): void
    {
        Artisan::call('ai:backfill-display-reach', [
            '--project-id' => $projectId,
            '--ids' => (string) $articleId,
            '--apply' => true,
        ]);
    }

    private function clearRescrape(int $projectId, int $articleId): void
    {
        DB::table('project_articles')
            ->where('project_id', $projectId)
            ->where('article_id', $articleId)
            ->update([
                'rescrape_status' => null,
                'rescrape_reason' => null,
                'rescrape_requested_at' => null,
                'rescrape_source' => null,
                'rescrape_meta' => null,
                'updated_at' => now(),
            ]);
    }

    private function appendRescrapeNote(int $projectId, int $articleId, string $note, ?string $existingReason): void
    {
        $reason = trim(collect([$existingReason, $note])->filter()->implode(', '));

        DB::table('project_articles')
            ->where('project_id', $projectId)
            ->where('article_id', $articleId)
            ->update([
                'rescrape_status' => 'needs_rescrape',
                'rescrape_reason' => $reason,
                'rescrape_requested_at' => now(),
                'rescrape_source' => 'needs_rescrape_runner',
                'rescrape_meta' => json_encode([
                    'note' => $note,
                    'updated_at' => now()->toIso8601String(),
                ]),
                'updated_at' => now(),
            ]);
    }
}
