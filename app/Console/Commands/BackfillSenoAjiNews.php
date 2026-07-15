<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class BackfillSenoAjiNews extends Command
{
    protected $signature = 'news:backfill-seno-aji
                            {--project-id=7 : Project ID to backfill}
                            {--days=30 : Backfill window in days}
                            {--max-unique=20 : Maximum unique articles to collect}
                            {--no-telegram : Suppress Telegram notifications}';

    protected $description = 'Controlled 30-day Seno Aji backfill using the existing news pipeline';

    public function handle(): int
    {
        $projectId = (int) $this->option('project-id');
        $days = max(1, (int) $this->option('days'));
        $maxUnique = max(1, (int) $this->option('max-unique'));
        $noTelegram = (bool) $this->option('no-telegram');

        $project = Project::query()->whereKey($projectId)->first();
        if (! $project) {
            $this->error("Project {$projectId} not found.");
            return self::FAILURE;
        }

        $keywords = collect($project->scrapeKeywords())
            ->merge([$project->name, 'seno aji', 'wagub kaltim'])
            ->filter(fn ($keyword) => is_string($keyword) && trim($keyword) !== '')
            ->map(fn ($keyword) => trim((string) $keyword))
            ->unique()
            ->values();

        $beforeCount = $this->countProjectArticles($projectId);
        $this->info("Backfill start for project [{$project->name}] (id={$projectId})");
        $this->info("Window: {$days} days | Max unique articles: {$maxUnique} | Existing project articles: {$beforeCount}");

        $processedKeywords = 0;
        foreach ($keywords as $keyword) {
            if (($this->countProjectArticles($projectId) - $beforeCount) >= $maxUnique) {
                break;
            }

            $processedKeywords++;
            $query = trim($keyword . ' when:' . $days . 'd');
            $remaining = max(1, $maxUnique - ($this->countProjectArticles($projectId) - $beforeCount));

            $this->line("→ Running scraping:run-news for keyword=\"{$query}\" (limit={$remaining})");
            $exitCode = Artisan::call('scraping:run-news', [
                '--project-id' => $projectId,
                '--keyword' => $query,
                '--limit' => $remaining,
                '--no-telegram' => $noTelegram,
            ]);

            $this->output->write(Artisan::output());

            if ($exitCode !== 0) {
                Log::warning('[BackfillSenoAjiNews] scraping:run-news returned non-zero exit code.', [
                    'project_id' => $projectId,
                    'keyword' => $query,
                    'exit_code' => $exitCode,
                ]);
            }
        }

        $afterCount = $this->countProjectArticles($projectId);
        $added = max(0, $afterCount - $beforeCount);

        $this->info("Backfill done. Processed keywords: {$processedKeywords}. Unique articles added: {$added}. Current total: {$afterCount}.");

        return self::SUCCESS;
    }

    private function countProjectArticles(int $projectId): int
    {
        return (int) Article::query()
            ->whereHas('projects', fn ($query) => $query->where('projects.id', $projectId))
            ->count();
    }
}
