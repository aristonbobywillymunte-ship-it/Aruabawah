<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class ApifyFinancialReport extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    protected function adminOnly(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function render()
    {
        $this->adminOnly();

        // Load costs per run with pagination (20 per page)
        $recentRuns = DB::table('apify_dispatch_states')
            ->whereNotNull('actual_cost_usd')
            ->where('completed_at', '>=', now()->subDays(30))
            ->orderBy('completed_at', 'desc')
            ->select('platform', 'actual_cost_usd', 'items_collected', 'run_duration_secs', 'completed_at', 'actor_id', 'project_id')
            ->paginate(20);

        // Transform collections items
        $recentRuns->getCollection()->transform(function ($r) {
            $actor = DB::table('apify_actors')->where('id', $r->actor_id)->value('actor_name');
            $projectName = 'N/A';
            if ($r->project_id) {
                $projectName = DB::table('projects')->where('id', $r->project_id)->value('name') ?? 'N/A';
            }
            return [
                'platform'     => $r->platform,
                'actor_name'   => $actor ?? '-',
                'cost'         => number_format((float) $r->actual_cost_usd, 4),
                'items'        => $r->items_collected ?? '-',
                'duration'     => $r->run_duration_secs ? $r->run_duration_secs . 's' : '-',
                'completed_at' => $r->completed_at ? \Carbon\Carbon::parse($r->completed_at)->isoFormat('D MMM, HH:mm') : '-',
                'project_name' => $projectName,
            ];
        });

        return view('livewire.admin.apify-financial-report', [
            'recentRuns' => $recentRuns,
            'costSummary' => $this->loadCostSummary(),
        ]);
    }

    private function loadCostSummary(): array
    {
        $rows = DB::table('apify_dispatch_states')
            ->whereNotNull('actual_cost_usd')
            ->where('completed_at', '>=', now()->subDays(30))
            ->orderBy('completed_at', 'desc')
            ->select('platform', 'actual_cost_usd', 'items_collected', 'run_duration_secs', 'completed_at', 'actor_id', 'project_id')
            ->get();

        $byPlatform = $rows->groupBy('platform')->map(fn($g) => [
            'total_cost' => round($g->sum('actual_cost_usd'), 4),
            'run_count'  => $g->count(),
            'avg_cost'   => round($g->avg('actual_cost_usd'), 4),
        ])->toArray();

        return [
            'by_platform' => $byPlatform,
            'total_all'   => round($rows->sum('actual_cost_usd'), 4),
            'has_data'    => $rows->isNotEmpty(),
        ];
    }
}
