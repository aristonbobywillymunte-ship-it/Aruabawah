<?php

namespace App\Console\Commands;

use App\Jobs\AiAnalysisJob;
use App\Models\AiAnalysisDispatchState;
use App\Models\Article;
use App\Models\Project;
use App\Services\ContentMatchingService;
use App\Services\AiAnalysisDispatchStateService;
use App\Services\SchedulerQueueGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;

class QueueUnscoredAiContent extends Command
{
    protected $signature = 'ai:queue-unscored-content
                            {--limit=20 : Maximum items to enqueue per run}
                            {--hours=48 : Look back window for content without AI analysis}';

    protected $description = 'Queue content that matches active project filters but has no AI result yet.';

    public function handle(
        ContentMatchingService $matchingService,
        AiAnalysisDispatchStateService $dispatchStateService,
        SchedulerQueueGuard $schedulerQueueGuard
    ): int
    {
        if ($schedulerQueueGuard->aiBusyReason() !== null) {
            $this->warn('AI queue masih sibuk. Queue unscored ditunda sampai worker idle.');
            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $hours = max(1, (int) $this->option('hours'));
        $cutoff = now()->subHours($hours);
        $queued = 0;
        $skipped = 0;

        $activeProjects = Project::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($activeProjects->isEmpty()) {
            $this->info('No active projects found.');
            return self::SUCCESS;
        }

        Article::query()
            ->with('aiAnalysisResult')
            ->whereDoesntHave('aiAnalysisResult')
            ->where(function ($query) use ($cutoff) {
                $query->where('published_at', '>=', $cutoff)
                    ->orWhere('created_at', '>=', $cutoff);
            })
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->chunkById(100, function ($articles) use ($activeProjects, $matchingService, $dispatchStateService, $limit, &$queued, &$skipped) {
                foreach ($articles as $article) {
                    if ($queued >= $limit) {
                        return false;
                    }

                    if ($schedulerQueueGuard->aiBusyReason() !== null) {
                        return false;
                    }

                    if (AiAnalysisDispatchState::withTrashed()
                        ->where('analyzable_type', 'article')
                        ->where('analyzable_id', $article->id)
                        ->exists()) {
                        $skipped++;
                        continue;
                    }

                    $matchedProjects = $matchingService->crossLinkToActiveProjects($article);
                    if ($matchedProjects === []) {
                        $skipped++;
                        continue;
                    }

                    $projectId = $matchedProjects[0];
                    $type = in_array(strtolower((string) $article->source_name), ['facebook', 'instagram', 'tiktok'], true)
                        || strtolower((string) $article->category) === 'social'
                        ? 'social'
                        : 'article';

                    $payload = [
                        'type' => $type,
                        'id' => $article->id,
                        'project_id' => $projectId,
                        'title' => $article->title,
                        'url' => $article->url,
                        'content' => $article->content,
                        'source_name' => $article->source_name,
                        'published_at' => optional($article->published_at)?->toIso8601String(),
                        'no_telegram' => true,
                    ];

                    $decision = $dispatchStateService->reserveQueuedStateAndDispatch($payload);
                    if ($decision['should_dispatch'] ?? false) {
                        $queued++;
                    }
                }

                return true;
            });

        $this->info("Queued {$queued} unscored item(s); skipped {$skipped} item(s).");

        return self::SUCCESS;
    }
}
