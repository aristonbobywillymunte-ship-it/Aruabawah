<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class ApifyFinancialReport extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    // Filter project
    public ?int $projectId = null;
    
    // Filter dates
    public ?string $startDate = null;
    public ?string $endDate = null;

    // Reset pagination on filter update
    public function updatedProjectId()
    {
        $this->resetPage();
    }

    public function updatedStartDate()
    {
        $this->resetPage();
    }

    public function updatedEndDate()
    {
        $this->resetPage();
    }

    protected function adminOnly(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function render()
    {
        $this->adminOnly();

        // Query projects listing for the select option dropdown
        $projects = DB::table('projects')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // Load costs per run with pagination (20 per page)
        $recentRuns = DB::table('apify_dispatch_states')
            ->whereNotNull('actual_cost_usd')
            ->when($this->startDate, function($q) {
                $q->whereDate('completed_at', '>=', $this->startDate);
            }, function($q) {
                $q->where('completed_at', '>=', now()->subDays(30));
            })
            ->when($this->endDate, function($q) {
                $q->whereDate('completed_at', '<=', $this->endDate);
            })
            ->when($this->projectId, function($q) {
                $q->where('project_id', $this->projectId);
            })
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
            'projects' => $projects,
        ]);
    }

    private function loadCostSummary(): array
    {
        $rows = DB::table('apify_dispatch_states')
            ->whereNotNull('actual_cost_usd')
            ->when($this->startDate, function($q) {
                $q->whereDate('completed_at', '>=', $this->startDate);
            }, function($q) {
                $q->where('completed_at', '>=', now()->subDays(30));
            })
            ->when($this->endDate, function($q) {
                $q->whereDate('completed_at', '<=', $this->endDate);
            })
            ->when($this->projectId, function($q) {
                $q->where('project_id', $this->projectId);
            })
            ->orderBy('completed_at', 'desc')
            ->select('platform', 'actual_cost_usd', 'items_collected', 'run_duration_secs', 'completed_at', 'actor_id', 'project_id')
            ->get();

        // Ambil data fungsionalitas aktor untuk pemetaan tipe
        $actorTypes = DB::table('apify_actors')
            ->pluck('function_type', 'id')
            ->toArray();

        // Kelompokkan berdasarkan Platform + Tipe Scraper (Post vs Komen)
        $grouped = [];
        foreach ($rows as $r) {
            $rawType = $actorTypes[$r->actor_id] ?? 'Search Post';
            // Terjemahkan tipe ke 'Post' atau 'Komen'
            $typeLabel = (str_contains(strtolower($rawType), 'comment') || str_contains(strtolower($rawType), 'komen')) ? 'Komentar' : 'Post';
            
            $key = $r->platform . ' (' . $typeLabel . ')';

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'platform' => $r->platform,
                    'type' => $typeLabel,
                    'costs' => [],
                ];
            }
            $grouped[$key]['costs'][] = (float) $r->actual_cost_usd;
        }

        $byPlatform = [];
        foreach ($grouped as $name => $g) {
            $costs = $g['costs'];
            $count = count($costs);
            $total = array_sum($costs);
            $byPlatform[$name] = [
                'platform'   => $g['platform'],
                'type'       => $g['type'],
                'total_cost' => round($total, 4),
                'run_count'  => $count,
                'avg_cost'   => $count > 0 ? round($total / $count, 4) : 0,
            ];
        }

        // Urutkan kelompok agar konsisten (Instagram, Facebook, TikTok)
        ksort($byPlatform);

        return [
            'by_platform' => $byPlatform,
            'total_all'   => round($rows->sum('actual_cost_usd'), 4),
            'has_data'    => $rows->isNotEmpty(),
        ];
    }
}
