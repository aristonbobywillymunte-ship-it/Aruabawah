<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MarkIncompleteForRescrape extends Command
{
    protected $signature = 'articles:mark-incomplete-for-rescrape {--ids=} {--project-id=} {--apply}';

    protected $description = 'Dry-run audit for incomplete articles/social items that should be rescraped later.';

    public function handle(): int
    {
        $ids = array_values(array_filter(array_map('intval', explode(',', (string) $this->option('ids')))));
        if ($ids === []) {
            $this->error('Please provide --ids=comma,separated,ids');
            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $projectId = $this->option('project-id');
        $projectId = is_numeric($projectId) ? (int) $projectId : null;
        $hasRescrapeColumns = Schema::hasColumn('project_articles', 'rescrape_status');

        $this->info($apply ? 'APPLY MODE' : 'DRY-RUN MODE');

        if ($apply && ! $hasRescrapeColumns) {
            $this->error('No rescrape columns exist yet on project_articles. Add the migration first, then re-run with --apply.');
            return self::FAILURE;
        }

        foreach ($ids as $id) {
            $article = DB::table('articles')->where('id', $id)->first();
            $social = DB::table('social_media_items')->where('id', $id)->first();
            $ai = DB::table('ai_analysis_results')->where('article_id', $id)->orWhere('social_media_item_id', $id)->first();
            $dispatch = DB::table('ai_analysis_dispatch_states')->where('analyzable_id', $id)->orderByDesc('updated_at')->first();
            $projectIdsQuery = DB::table('project_articles')->where('article_id', $id);
            if ($projectId !== null) {
                $projectIdsQuery->where('project_id', $projectId);
            }
            $projectIds = $projectIdsQuery->pluck('project_id')->unique()->values();
            $projectNames = DB::table('projects')->whereIn('id', $projectIds)->pluck('name')->values();

            $content = $article->content ?? $social->content ?? '';
            $reason = [];
            if (! $ai) {
                $reason[] = 'no_ai_result';
            } elseif (($ai->analysis_status ?? null) !== 'success') {
                $reason[] = 'ai_status=' . ($ai->analysis_status ?? 'null');
            }

            if ($ai && (($ai->reach_method ?? null) !== 'ai_reader_estimate_v1')) {
                $reason[] = 'reach_method';
            }
            if ($ai && (is_null($ai->project_estimated_readers) || (int) $ai->project_estimated_readers < 1)) {
                $reason[] = 'project_estimated_readers';
            }
            if ($ai && is_null($ai->project_reach_score)) {
                $reason[] = 'project_reach_score';
            }
            if ($ai && (is_null($ai->project_reach_level) || trim((string) $ai->project_reach_level) === '')) {
                $reason[] = 'project_reach_level';
            }
            if (! $article && ! $social) {
                $reason[] = 'missing_target';
            }

            $displayReady = $ai
                && ($ai->analysis_status ?? null) === 'success'
                && ($ai->reach_method ?? null) === 'ai_reader_estimate_v1'
                && ! is_null($ai->project_estimated_readers)
                && (int) $ai->project_estimated_readers >= 1
                && ! is_null($ai->project_reach_score)
                && ! is_null($ai->project_reach_level)
                && trim((string) ($ai->summary ?? '')) !== ''
                && trim((string) ($ai->sentiment ?? '')) !== ''
                && trim((string) ($ai->risk_level ?? '')) !== '';

            $safeRescrape = ! $displayReady
                && (
                    in_array('no_ai_result', $reason, true)
                    || in_array('reach_method', $reason, true)
                    || in_array('project_reach_score', $reason, true)
                    || in_array('project_reach_level', $reason, true)
                    || in_array('ai_status=invalid_ai_reach', $reason, true)
                );

            $shouldApply = $apply && $projectIds->isNotEmpty() && $id !== 80;

            if ($apply && $id === 80) {
                $this->warn('Skipping ID 80: reserved from rescrape clearing.');
            }

            $this->line(json_encode([
                'id' => $id,
                'article_exists' => (bool) $article,
                'social_exists' => (bool) $social,
                'project_ids' => $projectIds->all(),
                'project_names' => $projectNames->all(),
                'cross_project' => $projectIds->count() > 1,
                'source_or_platform' => $article->source_name ?? $social->platform ?? null,
                'url' => $article->url ?? $social->post_url ?? null,
                'content_len' => strlen(trim((string) $content)),
                'ai_result_status' => $ai->analysis_status ?? null,
                'display_ready' => $displayReady,
                'dispatch_status' => $dispatch->status ?? null,
                'dispatch_last_error_code' => $dispatch->last_error_code ?? null,
                'dispatch_failure_category' => $dispatch->failure_category ?? null,
                'reason' => implode(',', array_unique($reason)),
                'safe_rescrape' => $safeRescrape,
                'apply_supported' => $hasRescrapeColumns,
                'project_id_filter' => $projectId,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            if ($apply && $projectIds->isNotEmpty() && $id !== 80) {
                foreach ($projectIds as $pid) {
                    $pivotQuery = DB::table('project_articles')
                        ->where('project_id', (int) $pid)
                        ->where('article_id', $id);

                    if ($displayReady) {
                        $pivotQuery->update([
                            'rescrape_status' => null,
                            'rescrape_reason' => null,
                            'rescrape_requested_at' => null,
                            'rescrape_source' => null,
                            'rescrape_meta' => null,
                            'updated_at' => now(),
                        ]);
                        continue;
                    }

                    if ($safeRescrape) {
                        $pivotQuery->update([
                            'rescrape_status' => 'needs_rescrape',
                            'rescrape_reason' => implode(', ', array_unique($reason)),
                            'rescrape_requested_at' => now(),
                            'rescrape_source' => 'ai_display_gate_audit',
                            'rescrape_meta' => json_encode([
                                'audit_source' => 'articles:mark-incomplete-for-rescrape',
                                'article_id' => $id,
                                'project_id' => (int) $pid,
                            ]),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }

        if ($apply) {
            $this->info('Apply mode completed for eligible IDs.');
        }

        return self::SUCCESS;
    }
}
