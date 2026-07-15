<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\AiAnalysisDispatchState;
use App\Models\Article;

class ReconcileDispatchStates extends Command
{
    protected $signature = 'ai:reconcile-dispatch-states {--ids=} {--limit=10} {--apply}';
    protected $description = 'Reconcile stale AI dispatch states and close orphans';

    public function handle(): int
    {
        $idsOption = $this->option('ids');
        $limit = (int) $this->option('limit');
        $apply = $this->option('apply');

        $query = AiAnalysisDispatchState::query();

        if ($idsOption) {
            $ids = array_map('intval', explode(',', $idsOption));
            $query->whereIn('id', $ids);
        } else {
            $query->where('status', 'queued')
                  ->limit($limit);
        }

        $states = $query->get();

        if ($states->isEmpty()) {
            $this->info('No dispatch states matched the criteria.');
            return 0;
        }

        $this->info("Found {$states->count()} dispatch states to evaluate.");
        $this->info($apply ? 'APPLY MODE: Database modifications will be made.' : 'DRY-RUN MODE: No database changes will be made.');

        foreach ($states as $state) {
            $this->line("--------------------------------------------------");
            $this->line("Evaluating Dispatch State ID: {$state->id}");
            $this->line("Current status: {$state->status} | Type: {$state->analyzable_type} | ID: {$state->analyzable_id} | Project ID: {$state->project_id}");

            // Check if analysis result exists
            $resultExists = DB::table('ai_analysis_results')
                ->where('article_id', $state->analyzable_id)
                ->exists();

            // Check if target model exists
            $targetExists = false;
            if ($state->analyzable_id > 0) {
                $targetExists = Article::where('id', $state->analyzable_id)->exists();
            }

            if ($state->status === 'queued' && $resultExists) {
                $this->info("-> Action: Reconcile queued state to success (result exists).");
                if ($apply) {
                    $state->forceFill([
                        'status' => 'success',
                        'completed_at' => now(),
                        'failure_category' => null,
                        'last_error_code' => null,
                        'error_message' => null,
                        'last_failed_at' => null,
                        'next_retry_at' => null,
                    ])->save();
                    $this->info("   SUCCESS: Reconciled State ID {$state->id} to success.");
                }
            } elseif ($state->status === 'queued' && ($state->analyzable_id == 0 || !$targetExists)) {
                $this->warn("-> Action: Close orphan/stale dispatch state.");
                if ($apply) {
                    $state->forceFill([
                        'status' => 'failed',
                        'last_error_code' => 'orphan_dispatch_state',
                        'failure_category' => 'non_retryable_orphan',
                        'error_message' => 'Orphan dispatch state with invalid or missing analyzable target.',
                        'completed_at' => now(),
                        'last_failed_at' => now(),
                        'next_retry_at' => null,
                    ])->save();
                    $this->info("   SUCCESS: Closed State ID {$state->id} as failed (orphan).");
                }
            } else {
                $this->line("-> Action: None. State does not match reconciliation rules.");
            }
        }

        $this->line("--------------------------------------------------");
        $this->info("Evaluation complete.");
        return 0;
    }
}
