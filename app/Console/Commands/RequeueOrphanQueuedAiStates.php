<?php

namespace App\Console\Commands;

use App\Jobs\AiAnalysisJob;
use App\Models\AiAnalysisDispatchState;
use App\Models\Article;
use App\Models\SocialMediaItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;

class RequeueOrphanQueuedAiStates extends Command
{
    protected $signature = 'ai:requeue-orphan-queued-states
                            {--limit=5 : Maximum eligible states to requeue per run}
                            {--apply : Actually dispatch jobs instead of dry-run}
                            {--auto-drain : Keep running small batches until done or a stop condition is hit}
                            {--sleep=90 : Seconds to wait between auto-drain batches}
                            {--max-batches=15 : Maximum batches to run in auto-drain mode}
                            {--stop-on-failed-after=200 : Stop if failed_jobs ids exceed this baseline}';

    protected $description = 'Requeue orphan queued AI states selectively with safety checks.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $apply = (bool) $this->option('apply');
        $autoDrain = (bool) $this->option('auto-drain');
        $sleepSeconds = max(1, (int) $this->option('sleep'));
        $maxBatches = max(1, (int) $this->option('max-batches'));
        $stopOnFailedAfter = max(0, (int) $this->option('stop-on-failed-after'));

        if ($autoDrain && $limit !== 1) {
            $this->error('Auto-drain mode requires --limit=1.');
            return self::FAILURE;
        }

        if ($autoDrain) {
            return $this->handleAutoDrain($sleepSeconds, $maxBatches, $stopOnFailedAfter);
        }

        $this->runSingleBatch($limit, $apply);
        return self::SUCCESS;
    }

    private function handleAutoDrain(int $sleepSeconds, int $maxBatches, int $stopOnFailedAfter): int
    {
        $batchCount = 0;
        $successCount = 0;
        $queuedInitial = $this->queuedCount();
        $failedInitial = $this->failedJobsCount();
        $failedBaseline = $this->failedJobsNewAfterBaseline($stopOnFailedAfter);
        $dispatchedStateIds = [];

        while ($batchCount < $maxBatches) {
            $batchCount++;

            $before = $this->snapshotState($stopOnFailedAfter, $failedBaseline);
            if ($before['queued'] <= 0) {
                $this->info('No queued states remain. Auto-drain complete.');
                break;
            }

            $result = $this->runSingleBatch(1, true, true, $dispatchedStateIds);
            $successCount += $result['success_count'];

            $this->waitForQueueDrain($sleepSeconds, $stopOnFailedAfter, $failedBaseline);

            $after = $this->snapshotState($stopOnFailedAfter, $failedBaseline);
            if ($after['failed_jobs_new_after_baseline'] > $before['failed_jobs_new_after_baseline']) {
                $this->warn('Stop: failed_jobs id above baseline detected.');
                break;
            }
            if ($after['retry_wait'] > $before['retry_wait']) {
                $this->warn('Stop: retry_wait increased.');
                break;
            }
            if ($after['analysis_failed'] > $before['analysis_failed']) {
                $this->warn('Stop: analysis_failed increased.');
                break;
            }
            if ($after['provider_cooldown'] > 0) {
                $this->warn('Stop: provider cooldown detected.');
                break;
            }
            if ($after['processing'] > 0 && $after['ai_analysis'] === 0) {
                $this->warn('Stop: processing stuck without queue movement.');
                break;
            }

            if ($after['queued'] <= 0) {
                $this->info('Queued states drained to zero.');
                break;
            }
        }

        $final = $this->snapshotState($stopOnFailedAfter, $failedBaseline);
        $this->info(json_encode([
            'mode' => 'auto-drain',
            'batches' => $batchCount,
            'success_count' => $successCount,
            'queued_initial' => $queuedInitial,
            'queued_final' => $final['queued'],
            'failed_initial' => $failedInitial,
            'failed_final' => $final['failed_jobs_total'],
            'stop_reason' => $final['stop_reason'] ?? null,
            'final' => $final,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function waitForQueueDrain(int $sleepSeconds, int $stopOnFailedAfter, ?int $failedBaseline = null): void
    {
        $maxWaitSeconds = max(15, $sleepSeconds);
        $elapsed = 0;

        while ($elapsed < $maxWaitSeconds) {
            $snapshot = $this->snapshotState($stopOnFailedAfter, $failedBaseline);
            if ($snapshot['ai_analysis'] <= 0 && $snapshot['processing'] <= 0) {
                return;
            }

            sleep(1);
            $elapsed++;
        }
    }

    private function runSingleBatch(int $limit, bool $apply, bool $silentSummary = false, array &$dispatchedStateIds = []): array
    {
        $now = now();

        $states = AiAnalysisDispatchState::query()
            ->where('status', 'queued')
            ->where('attempts', 0)
            ->when($dispatchedStateIds !== [], function ($query) use (&$dispatchedStateIds) {
                $query->whereNotIn('id', $dispatchedStateIds);
            })
            ->orderBy('id')
            ->get();

        $eligible = 0;
        $processed = 0;
        foreach ($states as $state) {
            if ($processed >= $limit) {
                break;
            }

            [$canDispatch, $reason, $payload, $details] = $this->buildPayloadForState($state);
            $source = $details['source'] ?? '-';

            $this->line(sprintf(
                'ds=%d | type=%s | id=%d | project=%d | source=%s | eligible=%s | reason=%s',
                $state->id,
                $state->analyzable_type,
                $state->analyzable_id,
                (int) $state->project_id,
                $source,
                $canDispatch ? 'yes' : 'no',
                $reason
            ));
            $processed++;

            if (! $canDispatch || ! $apply) {
                continue;
            }

            try {
                AiAnalysisJob::dispatch(array_merge($payload, [
                    'no_telegram' => true,
                ]))
                    ->onConnection('redis-ai')
                    ->onQueue('ai-analysis');

                $eligible++;
                $dispatchedStateIds[] = $state->id;
            } catch (\Throwable $e) {
                Log::error('[AI Requeue Orphan] Failed to enqueue dispatched orphan state.', [
                    'dispatch_state_id' => $state->id,
                    'analyzable_id' => $state->analyzable_id,
                    'project_id' => $state->project_id,
                    'error' => $e->getMessage(),
                ]);

                $state->forceFill([
                    'status' => 'failed',
                    'last_error_code' => 'dispatch_enqueue_failed',
                    'error_message' => 'Failed to requeue orphan queued state.',
                    'last_failed_at' => $now,
                    'completed_at' => $now,
                ])->save();
            }
        }

        if (! $silentSummary) {
            $this->info($apply
                ? "Applied {$eligible} orphan queued state(s)."
                : 'Dry-run selesai.');
        }

        return [
            'success_count' => $eligible,
        ];
    }

    private function queuedCount(): int
    {
        return (int) AiAnalysisDispatchState::query()->where('status', 'queued')->count();
    }

    private function failedJobsCount(): int
    {
        return (int) DB::table('failed_jobs')->count();
    }

    private function snapshotState(int $stopOnFailedAfter, ?int $failedBaseline = null): array
    {
        $redis = Queue::connection('redis-ai');
        $failedJobsTotal = $this->failedJobsCount();
        $baselineCount = $failedBaseline ?? $this->failedJobsNewAfterBaseline($stopOnFailedAfter);
        $providerCooldown = \App\Models\AiProvider::query()
            ->where('is_active', true)
            ->whereNotNull('cooldown_until')
            ->where('cooldown_until', '>', now())
            ->count();

        return [
            'ai_analysis' => $redis->size('ai-analysis'),
            'ai_backfill' => $redis->size('ai-backfill'),
            'queued' => $this->queuedCount(),
            'processing' => (int) AiAnalysisDispatchState::query()->where('status', 'processing')->count(),
            'retry_wait' => (int) AiAnalysisDispatchState::query()->where('status', 'retry_wait')->count(),
            'analysis_failed' => (int) AiAnalysisDispatchState::query()->where('status', 'failed')->where('last_error_code', 'analysis_failed')->count(),
            'failed_jobs_total' => $failedJobsTotal,
            'failed_jobs_new_after_baseline' => $baselineCount,
            'provider_cooldown' => $providerCooldown,
        ];
    }

    private function failedJobsNewAfterBaseline(int $stopOnFailedAfter): int
    {
        return $stopOnFailedAfter > 0
            ? (int) DB::table('failed_jobs')->where('id', '>', $stopOnFailedAfter)->count()
            : 0;
    }

    /**
     * @return array{0: bool, 1: string, 2: array<string, mixed>, 3: array<string, mixed>}
     */
    private function buildPayloadForState(AiAnalysisDispatchState $state): array
    {
        if ($state->status !== 'queued') {
            return [false, 'not_queued', [], []];
        }

        if ((int) $state->attempts !== 0) {
            return [false, 'attempts_not_zero', [], []];
        }

        $article = Article::query()->find($state->analyzable_id);
        if (! $article) {
            return [false, 'missing_analyzable', [], []];
        }

        if ($state->analyzable_type === 'social') {
            $canonicalUrl = $article->canonical_url ?: $article->url;
            $social = SocialMediaItem::query()
                ->where(function ($query) use ($canonicalUrl, $article) {
                    $query->where('post_url', $canonicalUrl);
                    if ($article->url) {
                        $query->orWhere('post_url', $article->url);
                    }
                })
                ->first();

            if (! $social) {
                return [false, 'missing_social_item', [], []];
            }

            if (trim((string) $social->content) === '') {
                return [false, 'empty_content', [], []];
            }

            if ($social->aiAnalysisResult) {
                return [false, 'ai_result_exists', [], []];
            }

            return [
                true,
                'eligible',
                [
                    'type' => 'social',
                    'id' => $article->id,
                    'item_id' => $social->id,
                    'project_id' => $state->project_id,
                    'title' => $article->title ?: ($social->author_name ? "Post dari {$social->platform} oleh {$social->author_name}" : 'Post sosial'),
                    'content' => $social->content,
                    'url' => $social->post_url,
                    'source_name' => $social->platform,
                    'published_at' => optional($social->posted_at)?->toIso8601String() ?? optional($article->published_at)?->toIso8601String(),
                    'no_telegram' => true,
                    'prompt_template_id' => $state->prompt_template_id,
                    'provider_context_hash' => $state->provider_context_hash,
                ],
                [
                    'source' => $social->platform,
                ],
            ];
        }

        if (trim((string) $article->content) === '') {
            return [false, 'empty_content', [], []];
        }

        if ($article->aiAnalysisResult) {
            return [false, 'ai_result_exists', [], []];
        }

        return [
            true,
            'eligible',
            [
                'type' => 'article',
                'id' => $article->id,
                'project_id' => $state->project_id,
                'title' => $article->title,
                'content' => $article->content,
                'url' => $article->url,
                'source_name' => $article->source_name,
                'published_at' => optional($article->published_at)?->toIso8601String(),
                'no_telegram' => true,
                'prompt_template_id' => $state->prompt_template_id,
                'provider_context_hash' => $state->provider_context_hash,
            ],
            [
                'source' => $article->source_name ?: 'article',
            ],
        ];
    }
}
