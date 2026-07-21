<?php

namespace App\Console\Commands;

use App\Models\AiAnalysisResult;
use App\Models\Project;
use App\Services\ContentMatchingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillDisplayReach extends Command
{
    protected $signature = 'ai:backfill-display-reach
                            {--project-id= : Project ID target}
                            {--ids= : Comma-separated exact article IDs}
                            {--apply : Apply updates instead of dry-run}';

    protected $description = 'Backfill canonical display reach fields for exact article IDs without AI retries or scraping.';

    public function handle(): int
    {
        $projectId = (int) $this->option('project-id');
        $ids = array_values(array_filter(array_map(
            static fn ($value) => (int) trim((string) $value),
            explode(',', (string) $this->option('ids'))
        )));
        $apply = (bool) $this->option('apply');

        if ($projectId < 1 || $ids === []) {
            $this->error('project-id dan ids wajib diisi.');
            return self::FAILURE;
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        $project = Project::query()->find($projectId);
        if (! $project) {
            $this->error("Project {$projectId} tidak ditemukan.");
            return self::FAILURE;
        }

        $matchingService = app(ContentMatchingService::class);

        $rows = DB::table('articles')
            ->leftJoin('ai_analysis_results as ai', 'articles.id', '=', 'ai.article_id')
            ->whereIn('articles.id', $ids)
            ->where('ai.analysis_status', 'success')
            ->where('ai.reach_method', 'ai_reader_estimate_v1')
            ->orderBy('articles.id')
            ->get([
                'articles.id as article_id',
                'articles.title',
                'articles.url',
                'articles.content',
                'ai.id as ai_id',
                'ai.project_estimated_readers',
                'ai.project_reach_score',
                'ai.project_reach_level',
                'ai.project_reach_band',
                'ai.potential_estimated_readers',
                'ai.potential_reach_score',
                'ai.potential_reach_level',
                'ai.potential_reach_band',
                'ai.summary',
                'ai.sentiment',
                'ai.risk_level',
            ]);

        $foundIds = $rows->pluck('article_id')->map(fn ($value) => (int) $value)->all();
        $missingIds = array_values(array_diff($ids, $foundIds));

        $this->info('Dry run: ' . ($apply ? 'Tidak' : 'Ya'));
        $this->info('Target exact IDs: ' . implode(',', $ids));
        $this->info('Project target: ' . $project->name);

        foreach ($rows as $row) {
            if (! $matchingService->matchesProjectContent($project, (string) ($row->content ?? ''))) {
                $this->warn("Skipped article_id={$row->article_id}: tidak cocok dengan filter project.");
                continue;
            }

            $hasComplete = $this->hasDisplayReachData($row);
            $currentReaders = (int) ($row->project_estimated_readers ?? 0);
            $effectiveReaders = $currentReaders >= 1
                ? $currentReaders
                : (int) ($row->potential_estimated_readers ?? 0);

            $proposedScore = null;
            $proposedLevel = null;
            $proposedBand = null;

            if ($effectiveReaders >= 1) {
                $proposedScore = AiAnalysisResult::officialProjectReachScoreForReaders($effectiveReaders);
                $proposedLevel = AiAnalysisResult::officialProjectReachLevelForScore($proposedScore);
                $proposedBand = AiAnalysisResult::officialProjectReachBandForReaders($effectiveReaders);
            }

            $this->line(sprintf(
                'article_id=%d | current_readers=%s | effective_readers=%s | score=%s | level=%s | band=%s | status=%s',
                $row->article_id,
                $this->displayValue($row->project_estimated_readers),
                $effectiveReaders >= 1 ? (string) $effectiveReaders : 'n/a',
                $proposedScore !== null ? (string) $proposedScore : 'n/a',
                $proposedLevel !== null ? $proposedLevel : 'n/a',
                $proposedBand !== null ? $proposedBand : 'n/a',
                $hasComplete ? 'already_valid' : 'needs_backfill'
            ));

            if (! $apply) {
                continue;
            }

            if ($effectiveReaders < 1) {
                $this->warn("Skipped article_id={$row->article_id}: metadata reach belum cukup.");
                continue;
            }

            DB::table('ai_analysis_results')
                ->where('id', $row->ai_id)
                ->update([
                    'project_estimated_readers' => $effectiveReaders,
                    'project_reach_score' => $proposedScore,
                    'project_reach_level' => $proposedLevel,
                    'project_reach_band' => $proposedBand,
                    'reach_method' => 'ai_reader_estimate_v1',
                    'updated_at' => now(),
                ]);

            $this->info("Updated article_id={$row->article_id} successfully.");
        }

        if ($missingIds !== []) {
            $this->warn('Missing exact IDs from project scope: ' . implode(',', $missingIds));
        }

        $this->info($apply ? 'Apply selesai.' : 'Dry-run selesai.');

        return self::SUCCESS;
    }

    private function hasDisplayReachData(object $row): bool
    {
        return $row->project_estimated_readers !== null
            && (int) $row->project_estimated_readers >= 1
            && $row->project_reach_score !== null
            && $row->project_reach_level !== null
            && trim((string) $row->project_reach_level) !== '';
    }

    private function displayValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if ($value === '') {
            return '""';
        }

        return (string) $value;
    }
}
