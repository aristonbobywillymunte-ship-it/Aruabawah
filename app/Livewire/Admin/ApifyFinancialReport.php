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
        // Deteksi apakah keyword ini adalah URL (biasanya indikasi perayapan komentar / Comment Scraper)
        $isCommentRun = filter_var($keyword, FILTER_VALIDATE_URL) !== false;

        $this->isCommentModal = $isCommentRun;
        $this->modalLoading = true;
        $this->selectedItems = [];

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
            ->leftJoin('apify_actors', 'apify_dispatch_states.actor_id', '=', 'apify_actors.id')
            ->leftJoin('projects', 'apify_dispatch_states.project_id', '=', 'projects.id')
            ->leftJoin('package_actors', function ($join) {
                $join->on('projects.package_id', '=', 'package_actors.package_id')
                     ->on('apify_dispatch_states.actor_id', '=', 'package_actors.apify_actor_id');
            })
            ->whereNotNull('apify_dispatch_states.actual_cost_usd')
            ->when($this->startDate, function($q) {
                $q->whereDate('apify_dispatch_states.completed_at', '>=', $this->startDate);
            }, function($q) {
                $q->where('apify_dispatch_states.completed_at', '>=', now()->subDays(30));
            })
            ->when($this->endDate, function($q) {
                $q->whereDate('apify_dispatch_states.completed_at', '<=', $this->endDate);
            })
            ->when($this->projectId, function($q) {
                $q->where('apify_dispatch_states.project_id', $this->projectId);
            })
            ->orderBy('apify_dispatch_states.completed_at', 'desc')
            ->select(
                'apify_dispatch_states.platform', 
                'apify_dispatch_states.actual_cost_usd', 
                'apify_dispatch_states.items_collected', 
                'apify_dispatch_states.run_duration_secs', 
                'apify_dispatch_states.completed_at', 
                'apify_dispatch_states.project_id', 
                'apify_dispatch_states.keyword', 
                'apify_dispatch_states.run_id',
                'apify_dispatch_states.status',
                'apify_dispatch_states.last_error_code',
                'apify_dispatch_states.last_error_message',
                'apify_dispatch_states.actor_id',
                'apify_actors.actor_name',
                'projects.name as project_name',
                'package_actors.cost_per_run_usd as package_cost_limit'
            )
            ->paginate(20);

        // Transform collections items
        $recentRuns->getCollection()->transform(function ($r) {
            $statusObj = $this->financialRunStatus(
                $r->status,
                $r->last_error_code,
                $r->last_error_message,
                (float) $r->actual_cost_usd,
                (int) $r->items_collected
            );

            return [
                'platform'     => $r->platform,
                'actor_name'   => $r->actor_name ?? '-',
                'cost_limit'   => $r->package_cost_limit !== null ? number_format((float) $r->package_cost_limit, 4) : '-',
                'cost'         => number_format((float) $r->actual_cost_usd, 4),
                'items'        => $r->items_collected ?? 0,
                'run_status'   => $statusObj,
                'duration'     => $r->run_duration_secs ? $r->run_duration_secs . 's' : '-',
                'completed_at' => $r->completed_at ? \Carbon\Carbon::parse($r->completed_at)->isoFormat('D MMM, HH:mm') : '-',
                'project_name' => $r->project_name ?? 'N/A',
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

    private function financialRunStatus(?string $status, ?string $errorCode, ?string $errorMsg, float $actualCost, int $items): array
    {
        $msgLower = strtolower($errorMsg ?? '');
        $code = $errorCode ?? '';

        // A. Semua token habis
        if (str_contains($msgLower, 'apify_all_tokens_exhausted') || str_contains($code, 'APIFY_ALL_TOKENS_EXHAUSTED')) {
            return [
                'label' => 'Token/kuota tidak tersedia',
                'tone' => 'danger',
                'message' => 'Semua token Apify yang siap digunakan tidak tersedia atau mencapai limit.',
            ];
        }

        // B. Monthly quota / feature disabled
        if (str_contains($msgLower, 'monthly usage hard limit exceeded') || str_contains($msgLower, 'platform-feature-disabled')) {
            return [
                'label' => 'Kuota Apify habis',
                'tone' => 'danger',
                'message' => 'Run tidak dapat dijalankan karena batas penggunaan Apify tercapai.',
            ];
        }

        // C. Cost limit (Jika items > 0 maka partial, kalau 0 anggap juga cost limit tercapai)
        if (str_contains($msgLower, 'maximum cost') || str_contains($msgLower, 'max total charge') || str_contains($msgLower, 'maxtotalchargeusd') || str_contains($msgLower, 'partial: cost limit reached') || str_contains($msgLower, 'batas biaya apify')) {
            if ($items > 0) {
                return [
                    'label' => 'Selesai sebagian',
                    'tone' => 'warning',
                    'message' => 'Run berhenti pada batas biaya Paket; data parsial tetap diproses.',
                ];
            }
            return [
                'label' => 'Batas biaya tercapai',
                'tone' => 'warning',
                'message' => 'Run berhenti pada batas biaya Paket.',
            ];
        }

        // D. Timeout
        if (str_contains($msgLower, 'timeout') || str_contains($msgLower, 'poll timeout')) {
            return [
                'label' => 'Timeout',
                'tone' => 'danger', // Bisa danger/warning
                'message' => 'Apify tidak menyelesaikan run dalam waktu yang ditentukan.',
            ];
        }

        // E. Dataset gagal
        if (str_contains($msgLower, 'dataset fetch failed') || str_contains($msgLower, 'failed to fetch dataset')) {
            return [
                'label' => 'Gagal mengambil hasil',
                'tone' => 'danger',
                'message' => 'Run ada, tetapi dataset Apify gagal diambil.',
            ];
        }

        // F. Failed umum
        if ($status === 'failed') {
            return [
                'label' => 'Gagal',
                'tone' => 'danger',
                'message' => Str::limit($errorMsg ?: 'Terjadi kesalahan sistem yang tidak spesifik.', 120),
            ];
        }

        // I. Partial jika ada limit lain (opsional, sudah masuk C)

        // G. Nol tanpa error
        if ($actualCost == 0 && $items == 0) {
            return [
                'label' => 'Tidak ada hasil',
                'tone' => 'warning',
                'message' => 'Actor selesai tetapi tidak menghasilkan item atau biaya yang tercatat.',
            ];
        }

        // H. Berhasil
        return [
            'label' => 'Berhasil',
            'tone' => 'success',
            'message' => 'Run berhasil diselesaikan secara penuh.',
        ];
    }
}
