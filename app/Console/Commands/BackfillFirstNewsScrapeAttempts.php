<?php

namespace App\Console\Commands;

use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BackfillFirstNewsScrapeAttempts extends Command
{
    protected $signature = 'news:backfill-first-scrape-attempts
                            {--project-id= : Optional project ID filter}
                            {--dry-run : Show changes without writing to the database}';

    protected $description = 'Backfill the first news scrape attempt timestamp from existing discovery history';

    public function handle(): int
    {
        $projectId = $this->option('project-id');
        $dryRun = (bool) $this->option('dry-run');

        $query = Project::query()->orderBy('id');
        if ($projectId !== null && $projectId !== '') {
            $query->whereKey((int) $projectId);
        }

        $projects = $query->get();
        if ($projects->isEmpty()) {
            $this->warn('No projects found.');
            return self::SUCCESS;
        }

        $before = $this->snapshotCounts($projects);
        $this->info('Backfill start: ' . json_encode([
            'dry_run' => $dryRun,
            'projects' => $projects->pluck('id')->all(),
        ], JSON_UNESCAPED_UNICODE));

        $changes = [];
        $now = now();

        DB::beginTransaction();
        try {
            foreach ($projects as $project) {
                if ($project->first_news_scrape_attempt_at) {
                    continue;
                }

                $attemptAt = $this->determineAttemptTimestamp($project);
                if (! $attemptAt) {
                    continue;
                }

                $changes[] = [
                    'project_id' => $project->id,
                    'name' => $project->name,
                    'attempt_at' => $attemptAt['value']->toIso8601String(),
                    'source' => $attemptAt['source'],
                ];

                if (! $dryRun) {
                    Project::query()
                        ->whereKey($project->id)
                        ->whereNull('first_news_scrape_attempt_at')
                        ->update([
                            'first_news_scrape_attempt_at' => $attemptAt['value'],
                            'updated_at' => $now,
                        ]);
                }
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        foreach ($changes as $change) {
            $this->line(json_encode($change, JSON_UNESCAPED_UNICODE));
        }

        $after = $this->snapshotCounts($projects->fresh());
        $this->info('Backfill summary: ' . json_encode([
            'before_with_attempt' => $before['with_attempt'],
            'after_with_attempt' => $after['with_attempt'],
            'changed_projects' => count($changes),
            'dry_run' => $dryRun,
        ], JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    private function determineAttemptTimestamp(Project $project): ?array
    {
        $latestScrapingAttempt = DB::table('scraping_items')
            ->join('candidate_links', 'candidate_links.id', '=', 'scraping_items.candidate_link_id')
            ->where('candidate_links.project_id', $project->id)
            ->whereNotNull('scraping_items.last_attempt_at')
            ->orderBy('scraping_items.last_attempt_at')
            ->value('scraping_items.last_attempt_at');

        if ($latestScrapingAttempt) {
            return [
                'source' => 'scraping_items.last_attempt_at',
                'value' => Carbon::parse($latestScrapingAttempt),
            ];
        }

        $firstCandidate = DB::table('candidate_links')
            ->where('project_id', $project->id)
            ->whereNotNull('created_at')
            ->orderBy('created_at')
            ->value('created_at');

        if ($firstCandidate) {
            return [
                'source' => 'candidate_links.created_at',
                'value' => Carbon::parse($firstCandidate),
            ];
        }

        return null;
    }

    private function snapshotCounts($projects): array
    {
        $ids = $projects->pluck('id')->all();

        return [
            'with_attempt' => Project::query()->whereIn('id', $ids)->whereNotNull('first_news_scrape_attempt_at')->count(),
        ];
    }
}
