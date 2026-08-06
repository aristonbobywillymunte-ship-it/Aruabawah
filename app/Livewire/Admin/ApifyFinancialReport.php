<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    // Modal state for items collected view
    public bool $showItemsModal = false;
    public bool $modalLoading = false;
    public array $selectedItems = [];
    public string $selectedPlatform = '';
    public string $selectedKeyword = '';
    public string $selectedRunId = '';
    public string $selectedProjectName = '';
    public bool $isCommentModal = false;

    protected function adminOnly(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function openItems($projectId, $platform, $keyword, $runId = '-', $projectName = '-')
    {
        $this->adminOnly();
        $this->selectedPlatform = $platform;
        $this->selectedKeyword = $keyword;
        $this->selectedRunId = $runId ?: '-';
        $this->selectedProjectName = $projectName ?: '-';
        $this->showItemsModal = true;
        $this->isCommentModal = $isCommentRun;
        $this->modalLoading = true;
        $this->selectedItems = [];

        // Deteksi apakah keyword ini adalah URL (biasanya indikasi perayapan komentar / Comment Scraper)
        $isCommentRun = filter_var($keyword, FILTER_VALIDATE_URL) !== false;

        if ($isCommentRun) {
            // Get all URLs from the dispatch state for this run if possible (to support batch comment scraping)
            $urls = [$keyword];
            if ($runId && $runId !== '-') {
                $dispatch = DB::table('apify_dispatch_states')->where('run_id', $runId)->first();
                if ($dispatch && !empty($dispatch->normalized_keyword)) {
                    $urls = array_filter(array_map('trim', explode('|', $dispatch->normalized_keyword)));
                }
            }

            // Ambil postingan utama terlebih dahulu (Tanpa batasan project_id karena audit log / data mentah)
            $mainPosts = DB::table('social_media_items')
                ->where('platform', $platform)
                ->where(function($q) use ($urls) {
                    foreach ($urls as $url) {
                        $q->orWhere('post_url', $url)
                          ->orWhere('post_url', 'ilike', '%' . $url . '%');
                    }
                })
                ->get();

            if ($mainPosts->isNotEmpty()) {
                $postIds = $mainPosts->pluck('id')->toArray();
                
                // Ambil daftar komentar untuk semua variasi postingan utama ini
                $comments = DB::table('social_media_comments')
                    ->whereIn('social_media_item_id', $postIds)
                    ->orderBy('posted_at', 'desc')
                    ->orderBy('id', 'desc')
                    ->get();

                $this->selectedItems = $comments->map(function($c) use ($mainPosts) {
                    // Cari main post yang bersangkutan untuk mendapatkan post_url aslinya
                    $relatedPost = $mainPosts->firstWhere('id', $c->social_media_item_id);
                    return [
                        'post_url' => $relatedPost ? $relatedPost->post_url : '',
                        'author_name' => $c->author_name ?? 'Pengguna',
                        'content' => $c->content ?? '[tanpa teks]',
                        'likes' => (int) $c->like_count,
                        'comments' => 0,
                        'shares' => 0,
                        'posted_at' => $c->posted_at ? \Carbon\Carbon::parse($c->posted_at)->isoFormat('D MMM YYYY, HH:mm') : '-',
                        'parent_author' => $relatedPost ? $relatedPost->author_name : null,
                        'parent_content' => $relatedPost ? Str::limit($relatedPost->content, 60) : null,
                    ];
                })->toArray();
            }
        } else {
            // Ambil data postingan utama (Search Post)
            // Memecah kata kunci (misal "bank kaltimtara,bank kaltim") untuk mencari kecocokan global
            $queryKeyword = trim($keyword);
            $keywordsList = array_filter(array_map('trim', explode(',', $queryKeyword)));
            if (empty($keywordsList)) {
                $keywordsList = [$queryKeyword];
            }

            $rawItems = DB::table('social_media_items')
                ->where('platform', $platform)
                ->where('project_id', $projectId)
                ->orderBy('posted_at', 'desc')
                ->orderBy('id', 'desc')
                ->limit(150);

            \Log::info("Apify SQL Debug", [
                'sql' => $rawItems->toSql(),
                'bindings' => $rawItems->getBindings(),
                'platform' => $platform,
            ]);

            $rawItems = $rawItems->get();

            $this->selectedItems = $rawItems->map(function($item) {
                return [
                    'post_url' => $item->post_url,
                    'author_name' => $item->author_name ?? 'N/A',
                    'content' => Str::limit($item->content, 150),
                    'likes' => (int) $item->like_count,
                    'comments' => (int) $item->comment_count,
                    'shares' => (int) $item->share_count,
                    'posted_at' => $item->posted_at ? \Carbon\Carbon::parse($item->posted_at)->isoFormat('D MMM YYYY, HH:mm') : '-',
                ];
            })->toArray();
        }

        \Log::info("ApifyFinancialReport openItems called", [
            'platform' => $platform, 
            'keyword' => $keyword, 
            'raw_items_count' => count($this->selectedItems)
        ]);

        $this->modalLoading = false;
    }

    public function closeItemsModal()
    {
        $this->showItemsModal = false;
        $this->selectedItems = [];
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
            ->select('platform', 'actual_cost_usd', 'items_collected', 'run_duration_secs', 'completed_at', 'actor_id', 'project_id', 'keyword', 'run_id')
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
                'items'        => $r->items_collected ?? 0,
                'duration'     => $r->run_duration_secs ? $r->run_duration_secs . 's' : '-',
                'completed_at' => $r->completed_at ? \Carbon\Carbon::parse($r->completed_at)->isoFormat('D MMM, HH:mm') : '-',
                'project_name' => $projectName,
                'project_id'   => $r->project_id,
                'keyword'      => $r->keyword,
                'run_id'       => $r->run_id ?? '-',
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
