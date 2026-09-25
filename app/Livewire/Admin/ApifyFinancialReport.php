<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class ApifyFinancialReport extends Component
{
    use WithPagination;

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

    public function openItems(int $dispatchStateId)
    {
        $this->adminOnly();

        $dispatch = DB::table('apify_dispatch_states')
            ->leftJoin('projects', 'apify_dispatch_states.project_id', '=', 'projects.id')
            ->where('apify_dispatch_states.id', $dispatchStateId)
            ->select('apify_dispatch_states.*', 'projects.name as project_name')
            ->first();

        if (! $dispatch) {
            return;
        }

        $this->selectedPlatform = (string) $dispatch->platform;
        $this->selectedKeyword = (string) $dispatch->keyword;
        $this->selectedRunId = (string) ($dispatch->run_id ?: '-');
        $this->selectedProjectName = (string) ($dispatch->project_name ?: '-');
        $this->showItemsModal = true;
        
        // Deteksi apakah keyword ini adalah URL (indikasi perayapan komentar)
        $isCommentRun = filter_var($dispatch->keyword, FILTER_VALIDATE_URL) !== false;
        $this->isCommentModal = $isCommentRun;
        $this->modalLoading = true;
        $this->selectedItems = [];

        if ($isCommentRun) {
            $urls = [$dispatch->keyword];
            if (!empty($dispatch->normalized_keyword)) {
                $urls = array_filter(array_map('trim', explode('|', $dispatch->normalized_keyword)));
            }

            $mainPosts = DB::table('social_media_items')
                ->where('platform', $dispatch->platform)
                ->where(function($q) use ($urls) {
                    foreach ($urls as $url) {
                        $q->orWhere('post_url', $url)
                          ->orWhere('post_url', 'ilike', '%' . $url . '%');
                    }
                })
                ->get();

            if ($mainPosts->isNotEmpty()) {
                $postIds = $mainPosts->pluck('id')->toArray();
                
                $comments = DB::table('social_media_comments')
                    ->whereIn('social_media_item_id', $postIds)
                    ->orderBy('posted_at', 'desc')
                    ->orderBy('id', 'desc')
                    ->get();

                $this->selectedItems = $comments->map(function($c) use ($mainPosts) {
                    $relatedPost = $mainPosts->firstWhere('id', $c->social_media_item_id);
                    return [
                        'post_url'       => $relatedPost ? $relatedPost->post_url : '',
                        'author_name'    => $c->author_name ?? 'Pengguna',
                        'content'        => $c->content ?? '[tanpa teks]',
                        'likes'          => (int) $c->like_count,
                        'posted_at'      => $c->posted_at ? \Carbon\Carbon::parse($c->posted_at)->isoFormat('D MMM YYYY, HH:mm') : '-',
                        'parent_author'  => $relatedPost ? $relatedPost->author_name : null,
                        'parent_content' => $relatedPost ? Str::limit($relatedPost->content, 60) : null,
                    ];
                })->toArray();
            }
        } else {
            $queryKeyword = trim((string) $dispatch->keyword);
            $keywordsList = array_filter(array_map('trim', explode(',', $queryKeyword)));
            if (empty($keywordsList)) {
                $keywordsList = [$queryKeyword];
            }

            $projectId = $dispatch->project_id;
            // ponytail: cek kecocokan project_id langsung atau melalui pivot project_social_media_items
            $rawItems = DB::table('social_media_items')
                ->where('platform', $dispatch->platform)
                ->when($projectId, function ($q) use ($projectId) {
                    $q->where(function ($sub) use ($projectId) {
                        $sub->where('social_media_items.project_id', $projectId)
                            ->orWhereExists(function ($sq) use ($projectId) {
                                $sq->select(DB::raw(1))
                                   ->from('project_social_media_items')
                                   ->whereColumn('project_social_media_items.social_media_item_id', 'social_media_items.id')
                                   ->where('project_social_media_items.project_id', $projectId);
                            });
                    });
                }, function ($q) use ($keywordsList) {
                    $q->where(function ($sub) use ($keywordsList) {
                        foreach ($keywordsList as $kw) {
                            $sub->orWhere('content', 'ilike', '%' . $kw . '%')
                                ->orWhere('author_name', 'ilike', '%' . $kw . '%');
                        }
                    });
                })
                ->orderBy('posted_at', 'desc')
                ->orderBy('id', 'desc')
                ->limit(150)
                ->get();

            $this->selectedItems = $rawItems->map(function($item) {
                return [
                    'post_url'    => $item->post_url,
                    'author_name' => $item->author_name ?? 'N/A',
                    'content'     => Str::limit($item->content, 150),
                    'likes'       => (int) $item->like_count,
                    'comments'    => (int) $item->comment_count,
                    'posted_at'   => $item->posted_at ? \Carbon\Carbon::parse($item->posted_at)->isoFormat('D MMM YYYY, HH:mm') : '-',
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
                $q->whereDate(DB::raw('COALESCE(apify_dispatch_states.completed_at, apify_dispatch_states.updated_at)'), '>=', $this->startDate);
            }, function($q) {
                $q->where(DB::raw('COALESCE(apify_dispatch_states.completed_at, apify_dispatch_states.updated_at)'), '>=', now()->subDays(30));
            })
            ->when($this->endDate, function($q) {
                $q->whereDate(DB::raw('COALESCE(apify_dispatch_states.completed_at, apify_dispatch_states.updated_at)'), '<=', $this->endDate);
            })
            ->when($this->projectId, function($q) {
                $q->where('apify_dispatch_states.project_id', $this->projectId);
            })
            ->orderBy(DB::raw('COALESCE(apify_dispatch_states.completed_at, apify_dispatch_states.updated_at)'), 'desc')
            ->select(
                'apify_dispatch_states.id',
                'apify_dispatch_states.platform', 
                'apify_dispatch_states.actual_cost_usd', 
                'apify_dispatch_states.items_collected', 
                'apify_dispatch_states.run_duration_secs', 
                'apify_dispatch_states.completed_at', 
                'apify_dispatch_states.updated_at',
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

            $completedTime = $r->completed_at ?: $r->updated_at;

            return [
                'id'           => $r->id,
                'platform'     => $r->platform,
                'actor_name'   => $r->actor_name ?? '-',
                'cost_limit'   => $r->package_cost_limit !== null ? number_format((float) $r->package_cost_limit, 4) : '-',
                'cost'         => number_format((float) $r->actual_cost_usd, 4),
                'items'        => $r->items_collected ?? 0,
                'run_status'   => $statusObj,
                'duration'     => $r->run_duration_secs ? $r->run_duration_secs . 's' : '-',
                'completed_at' => $completedTime ? \Carbon\Carbon::parse($completedTime)->isoFormat('D MMM, HH:mm') : '-',
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
                $q->whereDate(DB::raw('COALESCE(completed_at, updated_at)'), '>=', $this->startDate);
            }, function($q) {
                $q->where(DB::raw('COALESCE(completed_at, updated_at)'), '>=', now()->subDays(30));
            })
            ->when($this->endDate, function($q) {
                $q->whereDate(DB::raw('COALESCE(completed_at, updated_at)'), '<=', $this->endDate);
            })
            ->when($this->projectId, function($q) {
                $q->where('project_id', $this->projectId);
            })
            ->orderBy(DB::raw('COALESCE(completed_at, updated_at)'), 'desc')
            ->select('platform', 'actual_cost_usd', 'items_collected', 'run_duration_secs', 'completed_at', 'updated_at', 'actor_id', 'project_id')
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

        // A. Kredit / Kuota Apify Habis (HTTP 402, not-enough-usage, billing limit)
        if (str_contains($msgLower, 'not-enough-usage') 
            || str_contains($msgLower, 'exceed your remaining usage') 
            || str_contains($msgLower, 'http 402') 
            || str_contains($msgLower, 'maximum usage for your current billing cycle')
            || str_contains($msgLower, 'monthly usage hard limit exceeded') 
            || str_contains($msgLower, 'platform-feature-disabled')) {
            return [
                'label' => 'Kredit/Saldo Habis',
                'tone' => 'danger',
                'message' => 'Saldo atau batas penggunaan akun Apify telah habis ($0.00).',
            ];
        }

        // B. Semua token habis
        if (str_contains($msgLower, 'apify_all_tokens_exhausted') || str_contains($code, 'APIFY_ALL_TOKENS_EXHAUSTED') || str_contains($msgLower, 'semua token apify tidak tersedia')) {
            return [
                'label' => 'Token Tidak Tersedia',
                'tone' => 'danger',
                'message' => 'Semua token Apify tidak siap digunakan atau mencapai batas kuota.',
            ];
        }

        // C. Cost limit (Jika items > 0 maka partial, kalau 0 batas tercapai)
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
                'tone' => 'danger',
                'message' => 'Apify tidak menyelesaikan run dalam batas waktu yang ditentukan.',
            ];
        }

        // E. Dataset gagal
        if (str_contains($msgLower, 'dataset fetch failed') || str_contains($msgLower, 'failed to fetch dataset')) {
            return [
                'label' => 'Gagal Ambil Hasil',
                'tone' => 'danger',
                'message' => 'Dataset hasil scraper di cloud Apify gagal diunduh.',
            ];
        }

        // F. Failed umum
        if ($status === 'failed') {
            $friendly = \App\Models\ApifyActor::friendlyRunMessage($errorMsg);
            return [
                'label' => 'Gagal',
                'tone' => 'danger',
                'message' => Str::limit($friendly ?: 'Terjadi kesalahan eksekusi scraper.', 120),
            ];
        }

        // G. Nol tanpa error
        if ($actualCost == 0 && $items == 0) {
            return [
                'label' => 'Tidak ada hasil',
                'tone' => 'warning',
                'message' => 'Scraper selesai namun tidak menghasilkan item baru.',
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
