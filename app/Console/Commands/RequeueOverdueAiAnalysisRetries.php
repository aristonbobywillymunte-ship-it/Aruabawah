<?php

namespace App\Console\Commands;

use App\Jobs\AiAnalysisJob;
use App\Models\AiAnalysisDispatchState;
use App\Services\AiAnalysisDispatchStateService;
use App\Services\SchedulerQueueGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;

class RequeueOverdueAiAnalysisRetries extends Command
{
    protected $signature = 'ai:requeue-overdue-retries {--limit=10 : Maximum retry_wait states to requeue per run}';

    protected $description = 'Requeue overdue AI retry_wait states selectively.';

    public function handle(
        AiAnalysisDispatchStateService $dispatchStateService,
        SchedulerQueueGuard $schedulerQueueGuard
    ): int
    {
        if ($schedulerQueueGuard->aiBusyReason() !== null) {
            $this->warn('AI queue masih sibuk. Requeue retry_wait ditunda sampai worker idle.');
            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $now = now();
        $count = 0;

        $states = AiAnalysisDispatchState::query()
            ->where('status', 'retry_wait')
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', $now)
            ->orderBy('next_retry_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($states as $state) {
            if ($schedulerQueueGuard->aiBusyReason() !== null) {
                break;
            }

            $payload = [
                'type' => $state->analyzable_type === 'social' ? 'social' : 'article',
                'id' => $state->analyzable_id,
                'project_id' => $state->project_id,
            ];

            $dispatchStateService->reserveQueuedStateAndDispatch(
                $payload,
                $state->prompt_template_id ? (int) $state->prompt_template_id : null,
                $state->provider_context_hash ?: null
            );
            $count++;
        }

        $this->info("Requeued {$count} overdue retry_wait state(s).");

        return self::SUCCESS;
    }
}
