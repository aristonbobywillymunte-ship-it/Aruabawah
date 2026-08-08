<?php

namespace App\Livewire;


use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use App\Models\Article;
use App\Models\AiAnalysisResult;
use App\Models\NewsSource;
use App\Models\SocialMediaComment;
use App\Models\SocialMediaItem;
use App\Models\Project;
use App\Services\NewsSourceIconResolver;
use Livewire\WithPagination;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class MediaDashboard extends Component
{
    use WithPagination;

    private const DASHBOARD_CACHE_VERSION = 'v3';

    private const SOCIAL_SOURCE_NAMES = [
        'facebook',
        'instagram',
        'tiktok',
        'twitter',
        'twitter/x',
        'x.com',
        'threads',
        'youtube',
    ];

    #[On('echo:system-alerts,RealtimeNotificationEvent')]
    public function handleRealtimeNotification($event): void
    {
        // Pemicu refresh data otomatis ketika ada analisis baru atau detak scheduler
        if (isset($event['type']) && $event['type'] === 'article_analyzed') {
            // Livewire otomatis re-render dan me-query ulang database untuk memperbarui metrics
            $this->dispatch('$refresh');
        }
    }

    public $projectId;
    public $projectName = 'Dashboard';
    public array $viralMeta = [];

    // Tab state ('penyebutan' or 'analisis')
    #[Url(as: 'tab')]
    public $activeTab = 'cGVueWVidXRhbg==';
    public bool $analysisLoaded = false;
    public bool $analysisChartsLoaded = false;
    public bool $keywordTabRequested = false;

    public function getDecodedProjectId()
    {
        if (is_numeric($this->projectId)) {
            return (int) $this->projectId;
        }
        $decoded = base64_decode($this->projectId, true);
        if ($decoded !== false && is_numeric($decoded)) {
            return (int) $decoded;
        }
        return $this->projectId;
    }

    protected function dashboardCacheSignature(): string
    {
        $project = $this->resolveProjectOrFail($this->projectId);
        return md5(json_encode([
            'cacheVersion' => self::DASHBOARD_CACHE_VERSION,
            'project_updated_at' => $project->updated_at?->timestamp ?? 0,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'sources' => $this->selectedSources,
            'search' => $this->search,
            'keyword' => $this->selectedKeyword,
            'sentiment' => $this->selectedSentiment,
            'category' => $this->selectedCategory,
            'sortBy' => $this->sortBy,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function isTab($name)
    {
        if (empty($this->activeTab)) {
            return false;
        }
        $decoded = base64_decode($this->activeTab, true);
        if ($decoded !== false && preg_match('/^[a-z]+$/', $decoded)) {
            return $decoded === $name;
        }
        return $this->activeTab === $name;
    }

    public function setTab($name)
    {
        $this->resetCommentModals();
        $this->activeTab = base64_encode($name);
        if ($name === 'analisis') {
            $this->analysisLoaded = false;
            $this->analysisChartsLoaded = false;
        }
        if ($name === 'konten') {
            $this->kontenLoaded = false;
        }
        if ($name === 'penyebutan') {
            $this->penyebutanLoaded = false;
        }
        if ($name === 'wawasan') {
            $this->wawasanLoaded = false;
        }
        $this->js('window.scrollTo(0, 0);');
    }

    public $search = '';
    public $selectedSentiment = [];
    public $selectedSources = [];
    public $selectedCategory = '';
    public $sortBy = 'newest';
    public int $limit = 5;

    public $kontenLoaded = false;
    public function loadKonten() {
        $this->kontenLoaded = true;
    }

    public $penyebutanLoaded = false;
    public function loadPenyebutan() {
        $this->penyebutanLoaded = true;
    }

    public $wawasanLoaded = false;
    public function loadWawasan() {
        $this->wawasanLoaded = true;
    }

    public function loadMore()
    {
        $this->limit += 5;
    }

    // Interactive datepicker states
    public $startDate;
    public $endDate;

    public function getPeriodLabel(): string
    {
        if (!$this->startDate && !$this->endDate) {
            return 'Semua Waktu';
        }

        try {
            $start = $this->startDate ? \Carbon\Carbon::parse($this->startDate)->startOfDay() : null;
            $end = $this->endDate ? \Carbon\Carbon::parse($this->endDate)->endOfDay() : null;
            $today = now();

            if ($start && $end) {
                if ($start->isSameDay($end)) {
                    return 'Harian';
                }

                if ($start->copy()->startOfWeek()->isSameDay($start) && $end->copy()->endOfWeek()->isSameDay($end)) {
                    return 'Mingguan';
                }

                if ($start->copy()->startOfMonth()->isSameDay($start) && $end->isSameDay($today->copy()->endOfMonth())) {
                    return 'Bulanan';
                }

                if ($start->copy()->startOfYear()->isSameDay($start) && $end->isSameDay($today->copy()->endOfYear())) {
                    return 'Tahunan';
                }
            }
        } catch (\Throwable $e) {
            // Fall back to formatted range below
        }

        return 'Periode';
    }



    // Form fields for adding articles
    public $title = '';
    public $content = '';
    public $url = '';
    public $source_name = 'Twitter';
    public $category = 'Technology';

    // Keywords management properties
    public $primaryKeywords = [];
    public $supportKeywords = [];
    public $excludeKeywords = [];
    public $keywordsTable = [];
    public $keywordSearch = '';
    public $selectedKeyword = null;
    public bool $dashboardLoaded = true;
    public bool $mentionsLoaded = false;
    protected array $trendPointsMemo = [];
    protected array $articlesMemo = [];
    protected array $totalArticlesCountMemo = [];
    protected array $countsMemo = [];
    protected array $projectArticleCountMemo = [];
    protected array $wawasanMemo = [];
    protected array $projectSourcesMemo = [];

    protected function dashboardCacheKeyPrefix(): string
    {
        return $this->getDecodedProjectId() . ':' . $this->dashboardCacheSignature();
    }

    protected function projectArticleCountCacheKey(): string
    {
        return 'media_dashboard_project_count:' . $this->dashboardCacheKeyPrefix();
    }

    protected function countsCacheKey(): string
    {
        return 'media_dashboard_counts:' . $this->dashboardCacheKeyPrefix();
    }

    protected function articlesCacheKey(): string
    {
        return 'media_dashboard_articles:v2:' . $this->dashboardCacheKeyPrefix() . ':' . md5(json_encode([
            'sortBy' => $this->sortBy,
            'limit' => $this->limit,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    protected function totalArticlesCountCacheKey(): string
    {
        return 'media_dashboard_total_articles:' . $this->dashboardCacheKeyPrefix();
    }

    protected function keywordsTableCacheKey(): string
    {
        return 'project_keywords_' . $this->getDecodedProjectId() . '_' .
            md5($this->startDate . '_' . $this->endDate . '_' . implode(',', $this->primaryKeywords));
    }

    public function toggleKeyword($keyword)
    {
        if ($this->selectedKeyword === $keyword) {
            $this->selectedKeyword = null;
        } else {
            $this->selectedKeyword = $keyword;
        }
    }
    public $newKeywordText = '';
    public $newKeywordType = 'primary';

    // UI state
    public $showAddModal = false;
    public $showDatePicker = false;
    public $showAddKeywordModal = false;
    public $socialMediaItemsCache = null;
    public bool $showTikTokCommentsModal = false;
    public bool $loadingTikTokComments = false;
    public array $tikTokCommentsModalMeta = [];
    public array $tikTokCommentsModalItems = [];

    public bool $showInstagramCommentsModal = false;
    public bool $loadingInstagramComments = false;
    public array $instagramCommentsModalMeta = [];
    public array $instagramCommentsModalItems = [];

    public bool $showFacebookCommentsModal = false;
    public bool $loadingFacebookComments = false;
    public array $facebookCommentsModalMeta = [];
    public array $facebookCommentsModalItems = [];

    protected function currentUser()
    {
        return auth()->user();
    }

    protected function isAdmin(): bool
    {
        return (bool) $this->currentUser()?->isAdmin();
    }

    protected function accessibleProjectQuery()
    {
        $user = $this->currentUser();

        abort_unless($user, 403, 'Autentikasi diperlukan.');

        return Project::accessibleBy($user);
    }

    private $resolvedProjectCache = null;

    protected function resolveProjectOrFail($projectId)
    {
        if (is_string($projectId)) {
            $decoded = base64_decode($projectId, true);
            if ($decoded !== false && is_numeric($decoded)) {
                $projectId = (int) $decoded;
            }
        }

        if ($this->resolvedProjectCache !== null && (int) $this->resolvedProjectCache->id === (int) $projectId) {
            return $this->resolvedProjectCache;
        }

        $query = $this->accessibleProjectQuery();

        if ($projectId) {
            $project = (clone $query)->find($projectId);
            abort_unless($project, 403, 'Anda tidak memiliki akses ke project ini.');

            $this->resolvedProjectCache = $project;
            return $project;
        }

        $project = (clone $query)->orderByDesc('created_at')->first();
        abort_unless($project, 403, 'Tidak ada project yang tersedia untuk akun ini.');

        $this->resolvedProjectCache = $project;
        return $project;
    }

    protected function projectArticlesQuery()
    {
        abort_unless($this->projectId, 403, 'Project belum dipilih.');

        $project = $this->resolveProjectOrFail($this->projectId);

        // Kueri 1: Berita Portal Asli dari tabel articles (kecualikan kategori social dan platform sosmed)
        $portalQuery = \Illuminate\Support\Facades\DB::table('articles')
            ->select([
                'articles.id as id',
                'articles.url as url',
                'articles.canonical_url as canonical_url',
                'articles.title as title',
                'articles.content as content',
                'articles.source_name as source_name',
                'articles.published_at as published_at',
                \Illuminate\Support\Facades\DB::raw("'portal' as item_type"),
                'articles.created_at as created_at',
                'articles.updated_at as updated_at',
                \Illuminate\Support\Facades\DB::raw("NULL as author_name"),
                \Illuminate\Support\Facades\DB::raw("NULL as author_url"),
                \Illuminate\Support\Facades\DB::raw("NULL as media_url"),
                \Illuminate\Support\Facades\DB::raw("NULL as thumbnail_url"),
                \Illuminate\Support\Facades\DB::raw("0 as like_count"),
                \Illuminate\Support\Facades\DB::raw("0 as comment_count"),
                \Illuminate\Support\Facades\DB::raw("0 as share_count"),
                \Illuminate\Support\Facades\DB::raw("0 as view_count"),
                \Illuminate\Support\Facades\DB::raw("0 as follower_count"),
                'articles.category as category',
            ])
            ->join('project_articles', 'articles.id', '=', 'project_articles.article_id')
            ->where('project_articles.project_id', $project->id)
            ->where(function ($q) {
                $q->whereNull('articles.category')
                  ->orWhere('articles.category', '!=', 'social');
            })
            ->whereNotIn(\Illuminate\Support\Facades\DB::raw('lower(coalesce(articles.source_name, \'\'))'), ['facebook', 'instagram', 'tiktok']);

        // Kueri 2: Media Sosial asli dari tabel social_media_items
        $socialQuery = \Illuminate\Support\Facades\DB::table('social_media_items')
            ->select([
                'social_media_items.id as id',
                'social_media_items.post_url as url',
                'social_media_items.post_url as canonical_url',
                \Illuminate\Support\Facades\DB::raw("('Post dari ' || social_media_items.platform || ' oleh ' || COALESCE(social_media_items.author_name, 'Pengguna')) as title"),
                'social_media_items.content as content',
                'social_media_items.platform as source_name',
                'social_media_items.posted_at as published_at',
                \Illuminate\Support\Facades\DB::raw("'social' as item_type"),
                'social_media_items.created_at as created_at',
                'social_media_items.updated_at as updated_at',
                'social_media_items.author_name as author_name',
                'social_media_items.author_url as author_url',
                \Illuminate\Support\Facades\DB::raw("NULL as media_url"),
                \Illuminate\Support\Facades\DB::raw("NULL as thumbnail_url"),
                'social_media_items.like_count as like_count',
                'social_media_items.comment_count as comment_count',
                'social_media_items.share_count as share_count',
                'social_media_items.view_count as view_count',
                'social_media_items.follower_count as follower_count',
                \Illuminate\Support\Facades\DB::raw("'social' as category"),
            ])
            ->join('project_social_media_items', 'social_media_items.id', '=', 'project_social_media_items.social_media_item_id')
            ->where('project_social_media_items.project_id', $project->id)
            ->where(function ($q) {
                $q->whereNull('social_media_items.post_url')
                  ->orWhere('social_media_items.post_url', 'not like', 'apify-%');
            })
            ->where('social_media_items.comments_checked', true); // Hanya tampilkan yang komentar pemeriksaannya sudah selesai

        // Gabungkan kedua kueri menggunakan union (dan jalankan query sebagai eloquent-compatible wrapper)
        return $portalQuery->union($socialQuery);
    }


    public function mount($projectId = null)
    {
        $this->resetCommentModals();
        $project = $this->resolveProjectOrFail($projectId);
        $this->projectId = base64_encode($project->id);
        $this->projectName = $project->name;
        $this->resolvedProjectCache = null;

        // Set default filter tanggal ke bulan ini (dari tanggal 1 hingga hari ini) agar data tidak terlalu berat dan relevan
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
        $this->search = '';
        $this->selectedSentiment = [];
        $this->selectedSources = [];
        $this->selectedCategory = '';
        $this->sortBy = 'newest';
        $this->limit = 5;
        $this->selectedKeyword = null;

        // Parse tab if present in query parameter
        $tabFromUrl = request()->query('tab');
        if ($tabFromUrl) {
            $decoded = base64_decode($tabFromUrl, true);
            if ($decoded !== false && preg_match('/^[a-z]+$/', $decoded)) {
                if ($decoded === 'wawasav') {
                    $tabFromUrl = base64_encode('wawasan');
                }
                $this->activeTab = $tabFromUrl;
                $this->keywordTabRequested = $decoded === 'katakunci';
            } else {
                $this->activeTab = base64_encode($tabFromUrl);
            }
        }

        // Initialize keywords list based on active project name and topics
        $this->primaryKeywords = $project->topics ?? [$this->projectName];
        $this->supportKeywords = [];
        $this->excludeKeywords = [];
        $this->dashboardLoaded = true;
        $this->viralMeta = $this->getViralMeta();

        // Tab Kata Kunci harus tampil segera saat dibuka langsung via URL.
        // Jika hanya mengandalkan wire:init, area utama bisa tetap kosong sebelum
        // request inisialisasi Livewire selesai.
        if ($this->keywordTabRequested || $this->isTab('katakunci')) {
            $this->dashboardLoaded = true;
        }

        // Selalu rebuild keywords table saat mount agar tab Kata Kunci tidak kosong
        // bahkan jika dashboardLoaded sudah true dari cache.
        $this->rebuildKeywordsTable();

    }

    protected function resetCommentModals(): void
    {
        $this->showTikTokCommentsModal = false;
        $this->loadingTikTokComments = false;
        $this->tikTokCommentsModalMeta = [];
        $this->tikTokCommentsModalItems = [];

        $this->showInstagramCommentsModal = false;
        $this->loadingInstagramComments = false;
        $this->instagramCommentsModalMeta = [];
        $this->instagramCommentsModalItems = [];

        $this->showFacebookCommentsModal = false;
        $this->loadingFacebookComments = false;
        $this->facebookCommentsModalMeta = [];
        $this->facebookCommentsModalItems = [];
    }

    public function loadDashboard(): void
    {
        if ($this->dashboardLoaded) {
            // Tetap rebuild table jika belum terisi (misal halaman kata kunci dibuka pertama kali)
            if (empty($this->keywordsTable)) {
                $this->rebuildKeywordsTable();
            }
            return;
        }

        $this->dashboardLoaded = true;
        $this->rebuildKeywordsTable();
    }

    public function loadMentions(): void
    {
        if ($this->mentionsLoaded) {
            return;
        }

        $this->mentionsLoaded = true;
    }

    public function loadAnalysis(): void
    {
        if ($this->analysisLoaded) {
            return;
        }

        $this->analysisLoaded = true;
        $this->analysisChartsLoaded = true;
    }

    public function loadAnalysisCharts(): void
    {
        if ($this->analysisChartsLoaded) {
            return;
        }

        $this->analysisChartsLoaded = true;
    }

    /**
     * Rebuild the keywords table with count respecting current date filter.
     * Called on mount AND whenever startDate/endDate changes.
     */
    public function rebuildKeywordsTable(): void
    {
        $cacheKey = $this->keywordsTableCacheKey();

        $this->keywordsTable = Cache::remember($cacheKey, 120, function () {
            $keywordsTable = [];
            $now = now();
            foreach ($this->primaryKeywords as $kw) {
                // Base: project articles mentioning the keyword, with date filter applied
                $baseKwQuery = clone $this->projectArticlesQuery();
                $this->applyActiveFilters($baseKwQuery);
                $this->applyKeywordSearch($baseKwQuery, $kw);
                $totalCount = (clone $baseKwQuery)->count();

                // Trend: compare last 30 days vs prior 30 days (always relative to now, not the date filter)
                $allKwQuery = clone $this->projectArticlesQuery();
                $this->applyKeywordSearch($allKwQuery, $kw);
                $recent = (clone $allKwQuery)->whereBetween('published_at', [$now->copy()->subDays(30), $now])->count();
                $prior  = (clone $allKwQuery)->whereBetween('published_at', [$now->copy()->subDays(60), $now->copy()->subDays(30)])->count();
                if ($prior === 0) {
                    $trend = $recent > 0 ? 'Naik' : 'Stabil';
                } elseif ($recent > $prior * 1.1) {
                    $trend = 'Naik';
                } elseif ($recent < $prior * 0.9) {
                    $trend = 'Turun';
                } else {
                    $trend = 'Stabil';
                }

                $keywordsTable[] = [
                    'keyword' => '# ' . strtoupper($kw),
                    'total'   => $totalCount,
                    'trend'   => $trend,
                ];
            }
            return $keywordsTable;
        });
    }

    public function updatedKeywordSearch()
    {
        $this->limit = 5;
        $this->resetPage();
    }

    public function generateAiInsights()
    {
        $project = $this->resolveProjectOrFail($this->projectId);
        \App\Jobs\GenerateProjectAiInsightJob::dispatchSync($project->id, $this->startDate, $this->endDate);
        $project->refresh();
        session()->flash('message', 'Wawasan AI berhasil diperbarui!');
    }

    public function preparePdfReport(string $togglesJson = '{}'): void
    {
        try {
            $project = $this->resolveProjectOrFail($this->projectId);
            \App\Jobs\GenerateProjectAiInsightJob::dispatchSync($project->id, $this->startDate, $this->endDate);
            $project->refresh();

            if (
                empty(trim((string) ($project->ai_insight_summary ?? ''))) ||
                empty($project->ai_insight_recommendations) ||
                empty(trim((string) ($project->ai_insight_viral_summary ?? '')))
            ) {
                throw new \RuntimeException('AI untuk laporan belum siap dipakai.');
            }

            $url = route('report.pdf', [
                'project_id' => $this->getDecodedProjectId(),
                'start_date' => $this->startDate,
                'end_date' => $this->endDate,
                'toggles' => $togglesJson,
            ]);

            $this->dispatch('open-report-pdf', url: $url);
            $this->dispatch('report-download-feedback',
                type: 'success',
                title: 'Laporan siap',
                message: 'Laporan PDF berhasil disiapkan dan akan dibuka.'
            );
        } catch (\Throwable $e) {
            report($e);

            $message = $this->simplifyPdfReportError($e);

            $this->dispatch('report-download-feedback',
                type: 'error',
                title: 'Laporan belum bisa dibuka',
                message: $message
            );
        }
    }

    protected function simplifyPdfReportError(\Throwable $e): string
    {
        $message = trim((string) $e->getMessage());
        $messageLower = strtolower($message);

        if (str_contains($messageLower, 'ai insight untuk laporan pdf belum tersedia') || str_contains($messageLower, 'ai untuk laporan belum siap')) {
            return 'Laporan belum siap karena AI masih memproses ringkasan. Silakan coba lagi sebentar.';
        }

        if (str_contains($messageLower, 'anda tidak memiliki akses') || str_contains($messageLower, 'autentikasi diperlukan')) {
            return 'Anda belum punya akses ke laporan ini.';
        }

        if ($message !== '') {
            return 'Laporan belum bisa dibuka. ' . $message;
        }

        return 'Laporan belum bisa dibuka. Silakan coba lagi sebentar.';
    }

    public function updatedStartDate()
    {
        $this->limit = 5;
        $this->resetPage();
        $this->rebuildKeywordsTable();
    }

    public function updatedEndDate()
    {
        $this->limit = 5;
        $this->resetPage();
        $this->rebuildKeywordsTable();
    }

    public function updatedSelectedSentiment()
    {
        $this->limit = 5;
        $this->resetPage();
    }

    public function updatedSelectedSources()
    {
        $this->limit = 5;
        $this->resetPage();
    }

    public function updatedSelectedCategory()
    {
        $this->limit = 5;
        $this->resetPage();
    }

    public function setPresetPeriod(string $mode): void
    {
        $today = now()->startOfDay();
        $start = clone $today;
        $end = now()->endOfDay();

        if ($mode === 'harian') {
            $start = clone $today;
            $end = clone $today;
        } elseif ($mode === 'mingguan') {
            $start = now()->subDays(6)->startOfDay();
        } elseif ($mode === 'bulanan') {
            $start = now()->subDays(29)->startOfDay();
        } elseif ($mode === 'tahunan') {
            $start = now()->subYear()->startOfDay();
        }

        $this->startDate = $start->format('Y-m-d');
        $this->endDate = $end->format('Y-m-d');

        $this->limit = 5;
        $this->resetPage();
        $this->rebuildKeywordsTable();
    }

    public function updatedStartDay()
    {
        $this->limit = 5;
        $this->resetPage();
    }

    public function updatedEndDay()
    {
        $this->limit = 5;
        $this->resetPage();
    }

    public function setSort($sort)
    {
        $this->sortBy = $sort;
        $this->limit = 5;
        $this->resetPage();
    }

    public function formatNumber($num)
    {
        if ($num >= 1000) {
            return round($num / 1000, 1) . 'K';
        }
        return $num;
    }

    public function normalizeReachLevelLabel($level = null): string
    {
        $normalized = strtolower(trim((string) $level));

        return match ($normalized) {
            'low' => 'Rendah',
            'local' => 'Lokal',
            'medium' => 'Sedang',
            'high' => 'Tinggi',
            'viral' => 'Viral',
            default => $level ? ucfirst($normalized) : 'Belum dinilai AI',
        };
    }

    public function getValidAiResult($article)
    {
        $analysis = $article->aiAnalysisResult;
        if ($analysis && $analysis->hasCompleteOfficialAiResult()) {
            return $analysis;
        }
        return null;
    }
    public function getProjectReachDisplayData($article): array
    {
        $analysis = $article->aiAnalysisResult;
        $hasReadableAiReach = (bool) (
            $analysis
            && $analysis->hasCompleteOfficialAiResult()
        );
        $officialReaders = $hasReadableAiReach ? $analysis->officialArticleEstimatedReaders() : null;
        $hasOfficialProjectReach = $officialReaders !== null;

        return [
            'hasReadableAiReach' => $hasReadableAiReach,
            'hasOfficialProjectReach' => $hasOfficialProjectReach,
            'reachValue' => $officialReaders,
            'scoreValue' => $hasOfficialProjectReach
                ? (int) $analysis->project_reach_score
                : null,
            'levelLabel' => $hasOfficialProjectReach
                ? $this->normalizeReachLevelLabel($analysis->project_reach_level)
                : 'Belum dinilai AI',
        ];
    }

    public function resolveArticleLogoUrl($article): string
    {
        $sourceName = trim((string) ($article->source_name ?? ''));
        if ($sourceName === '') {
            return $this->defaultPortalLogoUrl('unknown');
        }

        $cacheKey = 'article-logo-url:' . md5(strtolower($sourceName));

        return Cache::remember($cacheKey, now()->addDay(), function () use ($article, $sourceName) {
            $normalized = strtolower($sourceName);
            $source = NewsSource::query()
                ->whereRaw('LOWER(domain) = ?', [$normalized])
                ->orWhereRaw('LOWER(name) = ?', [$normalized])
                ->first();

            if ($source?->icon_url) {
                return $source->icon_url;
            }

            $domain = $this->guessSourceDomain($sourceName);
            $resolver = app(NewsSourceIconResolver::class);
            $resolved = $resolver->resolve($source?->base_url ?: ('https://' . $domain), $source?->domain ?: $domain, $source?->name ?: $sourceName);

            if ($resolved) {
                return $resolved;
            }

            // Jika diawali dengan Google News/RSS, berikan logo Google News resmi sebagai default fallback
            if (str_contains($normalized, 'google news') || str_contains($normalized, 'google')) {
                return $this->defaultPortalLogoUrl('news.google.com');
            }

            return $this->defaultPortalLogoUrl($domain);
        });
    }

    protected function guessSourceDomain(string $sourceName): string
    {
        $cleanName = str_replace(' ', '', strtolower($sourceName));

        if ($cleanName === 'portalberau.com' || $cleanName === 'portalberau') {
            return 'portalberau.online';
        }

        return str_contains($cleanName, '.') ? $cleanName : $cleanName . '.com';
    }

    protected function normalizeSourceLabel(?string $sourceName): string
    {
        $cleanName = strtolower(trim((string) $sourceName));

        if ($cleanName === '') {
            return '';
        }

        $compact = preg_replace('/[\s_\-]+/', '', $cleanName) ?? $cleanName;

        if (
            $compact === 'news'
            || $compact === 'portalnews'
            || str_contains($compact, 'portalberita')
            || str_contains($compact, 'newsportal')
        ) {
            return 'News';
        }

        if (
            str_starts_with($compact, 'instagram')
            || $compact === 'ig'
        ) {
            return 'Instagram';
        }

        if (
            str_starts_with($compact, 'tiktok')
            || $compact === 'tk'
        ) {
            return 'TikTok';
        }

        if (
            str_starts_with($compact, 'facebook')
            || $compact === 'fb'
        ) {
            return 'Facebook';
        }

        if (
            str_starts_with($compact, 'youtube')
            || $compact === 'yt'
        ) {
            return 'Youtube';
        }

        if (str_starts_with($compact, 'threads')) {
            return 'Threads';
        }

        if (
            str_starts_with($compact, 'twitter')
            || str_contains($compact, 'twitterx')
            || str_contains($compact, 'twitter/x')
            || $compact === 'x'
            || str_starts_with($compact, 'x.com')
        ) {
            return 'Twitter';
        }

        return ucfirst($cleanName);
    }

    protected function fallbackSourceLabelFromUrl(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        $host = (string) parse_url($url, PHP_URL_HOST);
        $host = strtolower(trim($host));
        if ($host === '') {
            return '';
        }

        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $host = preg_replace('/^m\./', '', $host) ?? $host;

        return $host;
    }

    protected function sourceMatchPatterns(string $sourceLabel): array
    {
        return match ($this->normalizeSourceLabel($sourceLabel)) {
            'Instagram' => ['instagram%'],
            'TikTok' => ['tiktok%'],
            'Facebook' => ['facebook%'],
            'Youtube' => ['youtube%'],
            'Threads' => ['threads%'],
            'Twitter' => ['twitter%', 'twitter/x%', 'x.com%', 'x'],
            default => [strtolower($sourceLabel)],
        };
    }

    protected function buildSourceMatchSql(array $patterns, string $column = 'source_name'): array
    {
        $sourceExpr = 'lower(coalesce(' . $column . ", ''))";
        $sqlParts = [];
        $bindings = [];

        foreach ($patterns as $pattern) {
            $pattern = strtolower(trim((string) $pattern));
            if ($pattern === '') {
                continue;
            }

            if (str_contains($pattern, '%')) {
                $sqlParts[] = $sourceExpr . ' like ?';
            } else {
                $sqlParts[] = $sourceExpr . ' = ?';
            }

            $bindings[] = $pattern;
        }

        if ($sqlParts === []) {
            return ['sql' => '1 = 0', 'bindings' => []];
        }

        return [
            'sql' => '(' . implode(' or ', $sqlParts) . ')',
            'bindings' => $bindings,
        ];
    }

    protected function buildSocialSourceSql(string $column = 'source_name'): array
    {
        $patterns = [];

        foreach (['Twitter', 'Instagram', 'Youtube', 'TikTok', 'Facebook', 'Threads'] as $label) {
            $patterns = array_merge($patterns, $this->sourceMatchPatterns($label));
        }

        $patterns = array_values(array_unique($patterns));

        $sourceSql = $this->buildSourceMatchSql($patterns, $column);

        return [
            'sql' => '(lower(coalesce(category, \'\')) = ? or ' . $sourceSql['sql'] . ')',
            'bindings' => array_merge(['social'], $sourceSql['bindings']),
        ];
    }

    protected function buildSourceLabelSql(string $sourceLabel, string $column = 'source_name'): array
    {
        if ($this->normalizeSourceLabel($sourceLabel) === 'News') {
            $socialSql = $this->buildSocialSourceSql($column);

            return [
                'sql' => 'not ' . $socialSql['sql'],
                'bindings' => $socialSql['bindings'],
            ];
        }

        return $this->buildSourceMatchSql($this->sourceMatchPatterns($sourceLabel), $column);
    }

    protected function buildNewsSourceSql(string $column = 'source_name'): array
    {
        $socialSql = $this->buildSocialSourceSql($column);

        return [
            'sql' => '(lower(coalesce(category, \'\')) <> ? and not ' . $socialSql['sql'] . ')',
            'bindings' => array_merge(['social'], $socialSql['bindings']),
        ];
    }

    protected function buildSourceAwareSearchSql(string $keyword): array
    {
        $term = $this->normalizeKeywordSearchTerm($keyword);

        if ($term === '') {
            return ['sql' => '1 = 0', 'bindings' => []];
        }

        $needle = mb_strtolower($term, 'UTF-8');
        $hashNeedle = mb_strtolower('#' . $term, 'UTF-8');

        $newsSourceSql = $this->buildNewsSourceSql();
        $socialSourceSql = $this->buildSocialSourceSql();

        // Untuk berita portal: cari keyword biasa DAN versi hashtag (#keyword).
        // Untuk sosial media (Facebook, Instagram, TikTok): cari keyword biasa SAJA —
        // konten sosmed disimpan tanpa hashtag dari Apify, sehingga filter #keyword tidak relevan.
        return [
            'sql' => '(((' . $newsSourceSql['sql'] . ') and (lower(coalesce(title, \'\')) like ? or lower(coalesce(content, \'\')) like ? or lower(coalesce(title, \'\')) like ? or lower(coalesce(content, \'\')) like ?)) or ((' . $socialSourceSql['sql'] . ') and lower(coalesce(content, \'\')) like ?))',
            'bindings' => array_merge(
                $newsSourceSql['bindings'],
                ['%' . $needle . '%', '%' . $needle . '%', '%' . $hashNeedle . '%', '%' . $hashNeedle . '%'],
                $socialSourceSql['bindings'],
                ['%' . $needle . '%']
            ),
        ];
    }

    protected function defaultPortalLogoUrl(string $domain): string
    {
        $domainLower = strtolower(trim($domain));
        if ($domainLower === 'google.com' || $domainLower === 'news.google.com' || str_contains($domainLower, 'google')) {
            // High quality Google News logo
            return "https://upload.wikimedia.org/wikipedia/commons/d/da/Google_News_icon.svg";
        }
        
        return "https://www.google.com/s2/favicons?sz=128&domain=" . urlencode($domain) . "&default=404";
    }

    public function getProjectArticleCount(): int
    {
        return $this->getTotalArticlesCount();
    }

    public function cleanNoiseText(?string $text): string
    {
        if (empty($text)) {
            return '';
        }
        
        // Decode escaped unicode \uXXXX
        $text = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
            return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
        }, $text) ?? $text;
        
        // Decode escaped slashes \/
        $text = str_replace('\\/', '/', $text);
        
        // Fix missing spacing before capitalize words like "diKalimantan" -> "di Kalimantan"
        $text = preg_replace('/\bdi([A-Z])/', 'di $1', $text) ?? $text;
        
        // Clean Google News SWG script injection leaks
        $text = preg_replace('/(async\s+)?src="https:\/\/news\.google\.com\/swg\/[^"]+"\s*>\s*/i', '', $text) ?? $text;
        $text = preg_replace('/(async\s+)?src="https:\/\/news\.google\.com\/swg\/[^"]+"\s*/i', '', $text) ?? $text;
        
        return $text;
    }

    public function getLikesAndComments($article): array
    {
        $isSocial = $this->isSocialArticle($article);
        
        $likes = 0;
        $comments = 0;
        
        if ($isSocial) {
            $item = $this->resolveSocialMediaItemForArticle($article);

            if ($item) {
                $likes = $item->like_count ?? 0;
                $comments = $item->comment_count ?? 0;
            }
        }
        
        return [$likes, $comments];
    }

    public function getStoredCommentCountForArticle($article): int
    {
        if (! $this->isSocialArticle($article)) {
            return 0;
        }

        $item = $this->resolveSocialMediaItemForArticle($article);
        if (! $item) {
            return 0;
        }

        return count($this->resolveCommentsForSocialItem($item));
    }

    public function shouldShowInstagramCommentsForArticle($article): bool
    {
        if (! $this->isInstagramArticle($article)) {
            return false;
        }

        return $this->getStoredCommentCountForArticle($article) > 0;
    }

    public function getViralArticles()
    {
        $unionQuery = $this->projectArticlesQuery();
        $query = \Illuminate\Support\Facades\DB::table(\Illuminate\Support\Facades\DB::raw("(" . $unionQuery->toSql() . ") as union_res"))
            ->setBindings($unionQuery->getBindings());

        $query = $this->applyActiveFilters($query);
        $articles = $query->where('union_res.published_at', '>=', now()->subDays(7))
            ->orderByDesc('union_res.published_at')
            ->limit(30)
            ->get();

        if ($articles->isEmpty()) {
            return collect();
        }

        $portalIds = $articles->where('item_type', 'portal')->pluck('id')->all();
        $socialIds = $articles->where('item_type', 'social')->pluck('id')->all();

        $portalsMap = collect();
        if (!empty($portalIds)) {
            $portalsMap = \App\Models\Article::query()
                ->with('aiAnalysisResult')
                ->whereIn('id', $portalIds)
                ->get()
                ->keyBy('id');
        }

        $socialsMap = collect();
        if (!empty($socialIds)) {
            $socialsMap = \App\Models\SocialMediaItem::query()
                ->with('aiAnalysisResult')
                ->whereIn('id', $socialIds)
                ->get()
                ->keyBy('id');
        }

        $orderedCollection = collect();
        foreach ($articles as $item) {
            if ($item->item_type === 'portal') {
                $model = $portalsMap->get($item->id);
                if ($model) {
                    $model->item_type = 'portal';
                    $orderedCollection->push($model);
                }
            } else {
                $model = $socialsMap->get($item->id);
                if ($model) {
                    $mock = new \App\Models\Article();
                    $mock->id = $model->id;
                    $mock->title = "Post dari {$model->platform} oleh " . ($model->author_name ?: 'Pengguna');
                    $mock->content = $model->content;
                    $mock->source_name = $model->platform;
                    $mock->published_at = $model->posted_at;
                    $mock->category = 'social';
                    $mock->url = $model->post_url;
                    $mock->canonical_url = $model->post_url;
                    $mock->item_type = 'social';
                    if ($model->relationLoaded('aiAnalysisResult')) {
                        $mock->setRelation('aiAnalysisResult', $model->aiAnalysisResult);
                    }
                    $orderedCollection->push($mock);
                }
            }
        }

        return $orderedCollection;
    }

    public function formatArticleExcerpt($article, int $limit = 210): string
    {
        $sourceText = trim((string) ($article->excerpt ?? $article->summary ?? ''));

        if ($sourceText !== '') {
            $sourceText = strip_tags($sourceText);
            $sourceText = html_entity_decode($sourceText, ENT_QUOTES, 'UTF-8');
            $sourceText = preg_replace('/\s+/u', ' ', $sourceText) ?? '';
        } else {
            $sourceText = trim((string) $article->content);
            $sourceText = strip_tags($sourceText);
            $sourceText = html_entity_decode($sourceText, ENT_QUOTES, 'UTF-8');
            $sourceText = preg_replace('/\s+/u', ' ', $sourceText) ?? '';
        }

        $sourceText = trim((string) $sourceText);
        $sourceText = $this->cleanNoiseText($sourceText);

        // Jika teks rill kosong, gunakan Summary dari AI sebagai fallback untuk deskripsi utama
        if ($sourceText === '') {
            $aiSummary = $article->aiAnalysisResult?->summary ?? '';
            if (trim($aiSummary) !== '') {
                $sourceText = trim($aiSummary);
            }
        }

        if ($sourceText === '') {
            return 'Belum ada ringkasan.';
        }

        $limit = max(120, min($limit, 220));

        return Str::limit($sourceText, $limit, '…');
    }

    public function displayArticleTitle($article): string
    {
        $title = html_entity_decode(strip_tags((string) ($article->title ?? '')), ENT_QUOTES, 'UTF-8');

        if ($this->isSocialArticle($article)) {
            $title = preg_replace('/^Post\s+dari\s+Facebook\s+oleh\s+/i', '', $title) ?? $title;
            $title = preg_replace('/^Post\s+dari\s+(Instagram|TikTok|Twitter|X)\s+oleh\s+/i', '', $title) ?? $title;
            
            // Jika judul mentah hanya berupa 'Post dari TikTok' atau 'Post dari Instagram' tanpa pengunggah spesifik
            if (preg_match('/^Post\s+dari\s+(TikTok|Instagram|Facebook|Twitter|X)$/i', trim($title))) {
                $title = trim($title);
            }
        }

        return trim($title) !== '' ? trim($title) : 'Penyebutan sosial';
    }


    public function openTikTokCommentsModal(int $articleId): void
    {
        // 1. Tampilkan modal seketika
        $this->showTikTokCommentsModal = true;
        $this->loadingTikTokComments = true;
        $this->tikTokCommentsModalMeta = [
            'article_id' => $articleId,
            'title' => 'Memuat data...',
            'source_name' => 'TikTok',
            'post_url' => '',
            'author_name' => '',
            'published_at' => '',
            'comment_count' => 0,
            'like_count' => 0,
        ];
        $this->tikTokCommentsModalItems = [];

        // 2. Dispatch event asinkron agar browser memicu load data di background
        $this->dispatch('load-tiktok-comments', articleId: $articleId);
    }

    // Listener Livewire untuk loading data asinkron
    #[on('load-tiktok-comments')]
    public function loadTikTokCommentsData(int $articleId): void
    {
        if (!$this->showTikTokCommentsModal || $this->tikTokCommentsModalMeta['article_id'] !== $articleId) {
            return;
        }

        $unionQuery = $this->projectArticlesQuery();
        $article = \Illuminate\Support\Facades\DB::table(\Illuminate\Support\Facades\DB::raw("(" . $unionQuery->toSql() . ") as union_res"))
            ->setBindings($unionQuery->getBindings())
            ->where('union_res.id', $articleId)
            ->where('union_res.item_type', 'social')
            ->first();

        if (! $article) {
            $article = Article::query()
                ->with(['aiAnalysisResult'])
                ->find($articleId);
        }

        if (! $article || ! $this->isTikTokArticle($article)) {
            $this->loadingTikTokComments = false;
            return;
        }

        $socialItem = $this->resolveSocialMediaItemForArticle($article);
        $comments = $this->resolveCommentsForSocialItem($socialItem);

        $this->tikTokCommentsModalMeta = [
            'article_id' => $article->id,
            'title' => $this->displayArticleTitle($article),
            'source_name' => (string) ($article->source_name ?? 'TikTok'),
            'post_url' => (string) ($socialItem?->post_url ?: $article->canonical_url ?: $article->url ?: ''),
            'author_name' => (string) ($socialItem?->author_name ?? ''),
            'published_at' => $article->published_at ? \Carbon\Carbon::parse($article->published_at)->translatedFormat('d M Y, H:i') : 'Baru saja',
            'comment_count' => (int) ($socialItem?->comment_count ?? 0),
            'like_count' => (int) ($socialItem?->like_count ?? 0),
        ];
        $this->tikTokCommentsModalItems = $comments;
        $this->loadingTikTokComments = false;
    }

    public function closeTikTokCommentsModal(): void
    {
        $this->showTikTokCommentsModal = false;
        $this->loadingTikTokComments = false;
        $this->tikTokCommentsModalMeta = [];
        $this->tikTokCommentsModalItems = [];
    }

    protected function isInstagramArticle($article): bool
    {
        $source = strtolower(trim((string) ($article->source_name ?? '')));

        return $source === 'instagram'
            || str_contains($source, 'instagram')
            || str_contains($source, 'ig');
    }

    public function openInstagramCommentsModal(int $articleId): void
    {
        // 1. Tampilkan modal seketika
        $this->showInstagramCommentsModal = true;
        $this->loadingInstagramComments = true;
        $this->instagramCommentsModalMeta = [
            'article_id' => $articleId,
            'title' => 'Memuat data...',
            'source_name' => 'Instagram',
            'post_url' => '',
            'author_name' => '',
            'published_at' => '',
            'comment_count' => 0,
            'like_count' => 0,
        ];
        $this->instagramCommentsModalItems = [];

        // 2. Dispatch event asinkron agar browser memicu load data di background
        $this->dispatch('load-instagram-comments', articleId: $articleId);
    }

    // Listener Livewire untuk loading data asinkron
    #[on('load-instagram-comments')]
    public function loadInstagramCommentsData(int $articleId): void
    {
        if (!$this->showInstagramCommentsModal || $this->instagramCommentsModalMeta['article_id'] !== $articleId) {
            return;
        }

        $unionQuery = $this->projectArticlesQuery();
        $article = \Illuminate\Support\Facades\DB::table(\Illuminate\Support\Facades\DB::raw("(" . $unionQuery->toSql() . ") as union_res"))
            ->setBindings($unionQuery->getBindings())
            ->where('union_res.id', $articleId)
            ->where('union_res.item_type', 'social') // Cegah tabrakan ID dengan menyaring khusus tipe sosial media
            ->first();

        if (! $article) {
            // Fallback aman jika query union terlewati
            $socialItem = SocialMediaItem::query()->find($articleId);
            if ($socialItem) {
                // Konversi social item ke mock object agar kompatibel dengan pemrosesan berikutnya
                $article = (object) [
                    'id' => $socialItem->id,
                    'url' => $socialItem->post_url,
                    'canonical_url' => $socialItem->post_url,
                    'title' => 'Post dari Instagram oleh ' . ($socialItem->author_name ?? 'Pengguna'),
                    'source_name' => $socialItem->platform,
                    'published_at' => $socialItem->posted_at,
                    'item_type' => 'social',
                ];
            }
        }

        if (! $article || ! $this->isInstagramArticle($article)) {
            $this->loadingInstagramComments = false;
            return;
        }

        $socialItem = $this->resolveSocialMediaItemForArticle($article);
        $comments = $this->resolveCommentsForSocialItem($socialItem);
        $storedCommentCount = count($comments);

        $this->instagramCommentsModalMeta = [
            'article_id' => $article->id,
            'title' => $this->displayArticleTitle($article),
            'source_name' => (string) ($article->source_name ?? 'Instagram'),
            'post_url' => (string) ($socialItem?->post_url ?: $article->canonical_url ?: $article->url ?: ''),
            'author_name' => (string) ($socialItem?->author_name ?? ''),
            'published_at' => $article->published_at ? \Carbon\Carbon::parse($article->published_at)->translatedFormat('d M Y, H:i') : 'Baru saja',
            'comment_count' => $storedCommentCount,
            'like_count' => (int) ($socialItem?->like_count ?? 0),
        ];
        $this->instagramCommentsModalItems = $comments;
        $this->loadingInstagramComments = false;
    }

    public function closeInstagramCommentsModal(): void
    {
        $this->showInstagramCommentsModal = false;
        $this->loadingInstagramComments = false;
        $this->instagramCommentsModalMeta = [];
        $this->instagramCommentsModalItems = [];
    }

    protected function isFacebookArticle($article): bool
    {
        $source = strtolower(trim((string) ($article->source_name ?? '')));

        return $source === 'facebook'
            || str_contains($source, 'facebook')
            || str_contains($source, 'fb');
    }

    public function openFacebookCommentsModal(int $articleId): void
    {
        // 1. Tampilkan modal seketika
        $this->showFacebookCommentsModal = true;
        $this->loadingFacebookComments = true;
        $this->facebookCommentsModalMeta = [
            'article_id' => $articleId,
            'title' => 'Memuat data...',
            'source_name' => 'Facebook',
            'post_url' => '',
            'author_name' => '',
            'published_at' => '',
            'comment_count' => 0,
            'like_count' => 0,
        ];
        $this->facebookCommentsModalItems = [];

        // 2. Dispatch event asinkron agar browser memicu load data di background
        $this->dispatch('load-facebook-comments', articleId: $articleId);
    }

    #[on('load-facebook-comments')]
    public function loadFacebookCommentsData(int $articleId): void
    {
        if (!$this->showFacebookCommentsModal || $this->facebookCommentsModalMeta['article_id'] !== $articleId) {
            return;
        }

        $unionQuery = $this->projectArticlesQuery();
        $article = \Illuminate\Support\Facades\DB::table(\Illuminate\Support\Facades\DB::raw("(" . $unionQuery->toSql() . ") as union_res"))
            ->setBindings($unionQuery->getBindings())
            ->where('union_res.id', $articleId)
            ->where('union_res.item_type', 'social')
            ->first();

        if (! $article) {
            $article = Article::query()
                ->with(['aiAnalysisResult'])
                ->find($articleId);
        }

        if (! $article || ! $this->isFacebookArticle($article)) {
            $this->loadingFacebookComments = false;
            return;
        }

        $socialItem = $this->resolveSocialMediaItemForArticle($article);
        $comments = $this->resolveCommentsForSocialItem($socialItem);

        $this->facebookCommentsModalMeta = [
            'article_id' => $article->id,
            'title' => $this->displayArticleTitle($article),
            'source_name' => (string) ($article->source_name ?? 'Facebook'),
            'post_url' => (string) ($socialItem?->post_url ?: $article->canonical_url ?: $article->url ?: ''),
            'author_name' => (string) ($socialItem?->author_name ?? ''),
            'published_at' => $article->published_at ? \Carbon\Carbon::parse($article->published_at)->translatedFormat('d M Y, H:i') : 'Baru saja',
            'comment_count' => count($comments),
            'like_count' => (int) ($socialItem?->like_count ?? 0),
        ];
        $this->facebookCommentsModalItems = $comments;
        $this->loadingFacebookComments = false;
    }

    public function closeFacebookCommentsModal(): void
    {
        $this->showFacebookCommentsModal = false;
        $this->loadingFacebookComments = false;
        $this->facebookCommentsModalMeta = [];
        $this->facebookCommentsModalItems = [];
    }

    protected function isTikTokArticle($article): bool
    {
        $source = strtolower(trim((string) ($article->source_name ?? '')));

        return $source === 'tiktok'
            || str_contains($source, 'tiktok')
            || str_contains($source, 'tk');
    }

    protected function resolveSocialMediaItemForArticle($article): ?SocialMediaItem
    {
        if (! $this->isSocialArticle($article)) {
            return null;
        }

        if ($this->socialMediaItemsCache === null) {
            try {
                $urls = $this->projectArticlesQuery()->pluck('canonical_url')->merge(
                    $this->projectArticlesQuery()->pluck('url')
                )->filter()->unique()->values()->all();

                if (! empty($urls)) {
                    $this->socialMediaItemsCache = SocialMediaItem::whereIn('post_url', $urls)
                        ->get()
                        ->keyBy('post_url');
                } else {
                    $this->socialMediaItemsCache = collect();
                }
            } catch (\Throwable $e) {
                $this->socialMediaItemsCache = collect();
            }
        }

        $rawCandidateUrls = array_values(array_filter(array_unique([
            trim((string) ($article->canonical_url ?? '')),
            trim((string) ($article->url ?? '')),
        ])));

        $candidateUrls = [];
        foreach ($rawCandidateUrls as $url) {
            if ($url !== '') {
                $candidateUrls[] = $url;
                $candidateUrls[] = rtrim($url, '/');
                $candidateUrls[] = $url . '/';
            }
        }
        $candidateUrls = array_values(array_filter(array_unique($candidateUrls)));

        $matches = collect();

        foreach ($candidateUrls as $candidateUrl) {
            if ($candidateUrl === '') {
                continue;
            }

            $directMatches = SocialMediaItem::query()
                ->where('post_url', $candidateUrl)
                ->get();

            foreach ($directMatches as $directMatch) {
                if ($directMatch instanceof SocialMediaItem) {
                    $matches->push($directMatch);
                }
            }

            $cached = $this->socialMediaItemsCache->get($candidateUrl);
            if ($cached instanceof SocialMediaItem) {
                $matches->push($cached);
            }
        }

        foreach ($this->socialMediaItemsCache as $cachedItem) {
            if (! $cachedItem instanceof SocialMediaItem) {
                continue;
            }

            if (in_array(trim((string) $cachedItem->post_url), $candidateUrls, true)) {
                $matches->push($cachedItem);
            }
        }

        return $matches
            ->unique('id')
            ->sortByDesc(function (SocialMediaItem $item) {
                $payload = $this->decodeSocialPayload($item->raw_json);
                $comments = data_get($payload, 'comments');
                $hasStoredComments = is_array($comments) && count($comments) > 0;

                return sprintf(
                    '%d-%010d-%010d',
                    $hasStoredComments ? 1 : 0,
                    (int) ($item->comment_count ?? 0),
                    (int) $item->id
                );
            })
            ->first();
    }

    protected function decodeSocialPayload(mixed $rawJson): array
    {
        if (is_array($rawJson)) {
            return $rawJson;
        }

        if (! is_string($rawJson) || trim($rawJson) === '') {
            return [];
        }

        $decoded = json_decode($rawJson, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function resolveCommentsForSocialItem(?SocialMediaItem $socialItem): array
    {
        if (! $socialItem) {
            return [];
        }

        $databaseComments = SocialMediaComment::query()
            ->where('social_media_item_id', $socialItem->id)
            ->orderByDesc('posted_at')
            ->orderByDesc('id')
            ->get()
            ->filter(function (SocialMediaComment $comment) {
                // Sembunyikan komentar yang berisi log error Apify (solusi 1)
                $rawJsonDecoded = json_decode($comment->raw_json, true);
                if (isset($rawJsonDecoded['error']) || isset($rawJsonDecoded['requestErrorMessages'])) {
                    return false;
                }
                
                // Tambahan filter jika content diisi tulisan 'Tidak ada teks komentar.' dari database
                if ($comment->content === 'Tidak ada teks komentar.') {
                    return false;
                }
                
                return true;
            })
            ->map(function (SocialMediaComment $comment) {
                return [
                    'author_name' => $comment->author_name ?: 'Pengguna',
                    'content' => $comment->content ?: 'Tidak ada teks komentar.',
                    'avatar_url' => $comment->avatar_url,
                    'posted_at' => $comment->posted_at?->translatedFormat('d M Y, H:i'),
                    'like_count' => (int) $comment->like_count,
                ];
            })
            ->values()
            ->all();

        if ($databaseComments !== []) {
            return $databaseComments;
        }

        $decodedPayload = $this->decodeSocialPayload($socialItem->raw_json);

        return $this->extractSocialComments($decodedPayload, (string) ($socialItem->platform ?? 'TikTok'));
    }

    protected function extractSocialComments(mixed $payload, string $platform = 'TikTok'): array
    {
        $normalizedPlatform = Str::lower(trim($platform));

        return match ($normalizedPlatform) {
            'facebook' => $this->extractFacebookComments($payload),
            'instagram' => $this->extractInstagramComments($payload),
            default => $this->extractTikTokComments($payload, $platform),
        };
    }

    protected function extractFacebookComments(mixed $payload): array
    {
        return $this->extractGenericSocialComments($payload, 'Facebook');
    }

    protected function extractInstagramComments(mixed $payload): array
    {
        return $this->extractGenericSocialComments($payload, 'Instagram');
    }

    protected function extractGenericSocialComments(mixed $payload, string $platform): array
    {
        if (! is_array($payload) || $payload === []) {
            return [];
        }

        $comments = [];

        if (array_is_list($payload)) {
            foreach ($payload as $item) {
                $normalized = $this->normalizeTikTokCommentItem($item, $platform);
                if ($normalized !== null) {
                    $comments[] = $normalized;
                }
            }

            return $this->deduplicateCommentItems($comments);
        }

        $this->walkTikTokCommentPayload($payload, $comments, 0, $platform);

        return $this->deduplicateCommentItems($comments);
    }

    public function getSocialHashtagsForArticle($article): array
    {
        if (! $this->isSocialArticle($article)) {
            return [];
        }

        $socialItem = $this->resolveSocialMediaItemForArticle($article);
        $payload = $this->decodeSocialPayload($socialItem?->raw_json);

        $candidates = [];

        if ($payload) {
            foreach ([
                data_get($payload, 'hashtags'),
                data_get($payload, 'hashTags'),
                data_get($payload, 'tags'),
                data_get($payload, 'topicTags'),
                data_get($payload, 'displayHashtags'),
            ] as $candidate) {
                if (is_array($candidate)) {
                    $candidates = array_merge($candidates, $candidate);
                }
            }
        }

        foreach ([
            data_get($payload, 'text'),
            data_get($payload, 'caption'),
            data_get($payload, 'description'),
            data_get($payload, 'desc'),
            data_get($payload, 'content'),
            $socialItem?->content,
            $article->title,
            $article->content,
            $article->excerpt,
        ] as $textSource) {
            if (is_string($textSource) && trim($textSource) !== '') {
                preg_match_all('/#([\p{L}\p{N}_\.]+)/u', $textSource, $matches);
                foreach (($matches[1] ?? []) as $tag) {
                    $candidates[] = $tag;
                }
            }
        }

        $hashtags = [];
        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                $candidate = data_get($candidate, 'name')
                    ?: data_get($candidate, 'tag')
                    ?: data_get($candidate, 'hashtag')
                    ?: data_get($candidate, 'title')
                    ?: null;
            }

            $tag = ltrim(trim((string) $candidate), '#');
            $tag = preg_replace('/[^\p{L}\p{N}_\.]/u', '', $tag) ?? '';

            if ($tag === '') {
                continue;
            }

            $normalized = Str::lower($tag);
            if (isset($hashtags[$normalized])) {
                continue;
            }

            $hashtags[$normalized] = '#' . $tag;
        }

        return array_values($hashtags);
    }

    protected function extractTikTokComments(mixed $payload, string $platform = 'TikTok'): array
    {
        if (! is_array($payload) || $payload === []) {
            return [];
        }

        $comments = [];

        if (array_is_list($payload)) {
            foreach ($payload as $item) {
                $normalized = $this->normalizeTikTokCommentItem($item, $platform);
                if ($normalized !== null) {
                    $comments[] = $normalized;
                }
            }
            return $this->deduplicateCommentItems($comments);
        }

        $this->walkTikTokCommentPayload($payload, $comments, 0, $platform);

        return $this->deduplicateCommentItems($comments);
    }

    protected function walkTikTokCommentPayload(array $node, array &$comments, int $depth = 0, string $platform = 'TikTok'): void
    {
        if ($depth > 6) {
            return;
        }

        foreach ($node as $key => $value) {
            $normalizedKey = strtolower(trim((string) $key));
            if (! is_array($value)) {
                continue;
            }

            if ($this->isTikTokCommentListKey($normalizedKey)) {
                foreach ($value as $commentItem) {
                    $normalized = $this->normalizeTikTokCommentItem($commentItem, $platform);
                    if ($normalized !== null) {
                        $comments[] = $normalized;
                    }
                }
            }

            $this->walkTikTokCommentPayload($value, $comments, $depth + 1, $platform);
        }
    }

    protected function isTikTokCommentListKey(string $key): bool
    {
        return in_array($key, [
            'comments',
            'comment',
            'commentlist',
            'comment_list',
            'commentdata',
            'comment_data',
            'commentitems',
            'comment_items',
            'aweme_comments',
            'replies',
            'reply_list',
            'replys',
            'items',
            'list',
            'data',
            'latestcomments',
            'latest_comments',
        ], true);
    }

    protected function normalizeTikTokCommentItem(mixed $item, string $platform = 'TikTok'): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        $authorName = trim((string) (
            data_get($item, 'from.name')
            ?: data_get($item, 'from.username')
            ?: data_get($item, 'from.full_name')
            ?: data_get($item, 'from.fullName')
            ?: data_get($item, 'from.profile_name')
            ?: data_get($item, 'from.profileName')
            ?: data_get($item, 'from.author_name')
            ?: data_get($item, 'author.name')
            ?: data_get($item, 'user.nickname')
            ?: data_get($item, 'user.uniqueId')
            ?: data_get($item, 'user.name')
            ?: data_get($item, 'user.username')
            ?: data_get($item, 'nickname')
            ?: data_get($item, 'userName')
            ?: data_get($item, 'authorName')
            ?: data_get($item, 'ownerUsername')
            ?: data_get($item, 'uniqueId')  // clockworks/tiktok-comments-scraper
            ?: ''
        ));

        $content = trim((string) (
            data_get($item, 'text')
            ?: data_get($item, 'content')
            ?: data_get($item, 'commentText')
            ?: data_get($item, 'desc')
            ?: data_get($item, 'message')
            ?: data_get($item, 'replyText')
            ?: data_get($item, 'textContent')
            ?: ''
        ));

        $avatarUrl = trim((string) (
            data_get($item, 'from.profilePicture')
            ?: data_get($item, 'from.profile_picture')
            ?: data_get($item, 'from.picture.data.url')
            ?: data_get($item, 'from.picture.url')
            ?: data_get($item, 'from.avatar')
            ?: data_get($item, 'from.avatarUrl')
            ?: data_get($item, 'author.avatarThumb')
            ?: data_get($item, 'author.avatar')
            ?: data_get($item, 'user.avatarThumb')
            ?: data_get($item, 'user.avatar')
            ?: data_get($item, 'avatarThumbnail')  // clockworks/tiktok-comments-scraper
            ?: data_get($item, 'avatar')
            ?: data_get($item, 'avatar_url')
            ?: data_get($item, 'profilePic')
            ?: data_get($item, 'ownerProfilePicUrl')  // Instagram (apify)
            ?: data_get($item, 'owner.profilePicUrl') // Instagram nested
            ?: ''
        ));

        $postedAtRaw = data_get($item, 'createTime')
            ?: data_get($item, 'created_time')
            ?: data_get($item, 'createdTime')
            ?: data_get($item, 'createTimeISO')
            ?: data_get($item, 'timestamp')
            ?: data_get($item, 'date')
            ?: data_get($item, 'createdAt')
            ?: data_get($item, 'postedAt')
            ?: data_get($item, 'time');

        $postedAt = $this->normalizeTikTokCommentDate($postedAtRaw);
        $rawLikeValue = data_get($item, 'likes');
        $likeCount = (int) (
            data_get($item, 'diggCount')
            ?: data_get($item, 'likeCount')
            ?: data_get($item, 'likes.summary.total_count')
            ?: data_get($item, 'likesCount')   // Instagram (apify)
            ?: (is_scalar($rawLikeValue) ? $rawLikeValue : 0)
            ?: data_get($item, 'like_count')
            ?: data_get($item, 'totalLikes')
            ?: 0
        );

        if ($authorName === '' && $content === '') {
            return null;
        }

        $platformLabel = trim($platform) !== '' ? $platform : 'TikTok';

        return [
            'author_name' => $authorName !== '' ? $authorName : 'Pengguna ' . $platformLabel,
            'content' => $content !== '' ? $content : 'Tidak ada teks komentar.',
            'avatar_url' => $avatarUrl,
            'posted_at' => $postedAt,
            'like_count' => $likeCount,
        ];
    }

    protected function normalizeTikTokCommentDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                $timestamp = (int) $value;
                $carbon = strlen((string) $value) >= 13
                    ? \Carbon\Carbon::createFromTimestampMs($timestamp)
                    : \Carbon\Carbon::createFromTimestamp($timestamp);

                return $carbon->translatedFormat('d M Y, H:i');
            }

            return \Carbon\Carbon::parse((string) $value)->translatedFormat('d M Y, H:i');
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function deduplicateCommentItems(array $comments): array
    {
        $seen = [];

        return array_values(array_filter($comments, function (array $comment) use (&$seen) {
            $signature = md5(implode('|', [
                strtolower(trim((string) ($comment['author_name'] ?? ''))),
                strtolower(trim((string) ($comment['content'] ?? ''))),
                strtolower(trim((string) ($comment['posted_at'] ?? ''))),
            ]));

            if (isset($seen[$signature])) {
                return false;
            }

            $seen[$signature] = true;
            return true;
        }));
    }

    public function analyzeSentiment($text)
    {
        $text = strtolower($text);
        
        $positiveWords = ['good', 'great', 'breakthrough', 'increase', 'success', 'growth', 'launch', 'gain', 'promising', 'unveils', 'pave', 'innovative', 'perfect', 'advances', 'solution', 'milestone', 'discover', 'smart', 'revolutionize', 'positive', 'outpaces', 'historic', 'kesiapsiagaan', 'kesiapan'];
        $negativeWords = ['downturn', 'fall', 'decline', 'drop', 'concern', 'risk', 'inflation', 'probe', 'block', 'breach', 'theft', 'investigation', 'caution', 'negative', 'worry', 'threat', 'scam', 'damage', 'fail', 'bad', 'tighten'];
        
        $posCount = 0;
        $negCount = 0;
        
        foreach ($positiveWords as $word) {
            $posCount += substr_count($text, $word);
        }
        foreach ($negativeWords as $word) {
            $negCount += substr_count($text, $word);
        }
        
        if ($posCount > $negCount) {
            $score = 0.3 + min(0.7, ($posCount - $negCount) * 0.15);
            return ['positive', round($score, 2)];
        } elseif ($negCount > $posCount) {
            $score = -0.3 - min(0.7, ($negCount - $posCount) * 0.15);
            return ['negative', round($score, 2)];
        } else {
            return ['neutral', 0.0];
        }
    }

    public function addArticle()
    {
        abort_unless($this->isAdmin(), 403, 'Hanya admin yang dapat menambah artikel.');
        abort_unless($this->projectId, 403, 'Project belum dipilih.');

        $this->validate([
            'title'       => 'required|min:5',
            'content'     => 'required|min:20',
            'source_name' => 'required',
            'category'    => 'required',
            'url'         => 'nullable|url',
        ]);

        list($sentiment, $score) = $this->analyzeSentiment($this->title . ' ' . $this->content);

        $article = Article::create([
            'title'           => $this->title,
            'content'         => $this->content,
            'canonical_url'   => $this->url ?: null,
            'source_name'     => $this->source_name,
            'category'        => $this->category,
            'url'             => $this->url,
            'sentiment'       => $sentiment,
            'sentiment_score' => $score,
            'published_at'    => now(),
        ]);

        $project = Project::find($this->projectId);
        if ($project) {
            $article->projects()->syncWithoutDetaching([$project->id]);
        }

        $this->reset(['title', 'content', 'url', 'source_name', 'category', 'showAddModal']);
        session()->flash('message', 'Mention analyzed and added successfully.');
    }

    public function deleteArticle($id)
    {
        abort_unless($this->isAdmin(), 403, 'Hanya admin yang dapat menghapus artikel.');

        // Pastikan artikel milik project yang sedang aktif
        $article = $this->projectArticlesQuery()
            ->where('id', $id)
            ->firstOrFail();

        $article->projects()->detach($this->getDecodedProjectId());
        session()->flash('message', 'Mention removed from project.');
    }

    public function addKeyword()
    {
        abort_unless($this->isAdmin(), 403, 'Hanya admin yang dapat menambah kata kunci.');

        if (trim($this->newKeywordText) == '') return;
        
        $oldCacheKey = $this->keywordsTableCacheKey();
        $newKw = trim($this->newKeywordText);
        $countQuery = clone $this->projectArticlesQuery();
        $this->applyActiveFilters($countQuery);
        $this->applyKeywordSearch($countQuery, $newKw);

        if ($this->newKeywordType == 'primary') {
            $this->primaryKeywords[] = trim($this->newKeywordText);
        } elseif ($this->newKeywordType == 'support') {
            $this->supportKeywords[] = trim($this->newKeywordText);
        } else {
            $this->excludeKeywords[] = trim($this->newKeywordText);
        }

        // Save to database
        $project = $this->resolveProjectOrFail($this->projectId);
        $project->update([
            'topics' => $this->primaryKeywords,
            'context_keywords' => $this->supportKeywords,
            'exclude_keywords' => $this->excludeKeywords,
        ]);

        // Evict old cache
        Cache::forget($oldCacheKey);
        Cache::forget($this->keywordsTableCacheKey());
        $this->rebuildKeywordsTable();
        
        $this->newKeywordText = '';
        $this->showAddKeywordModal = false;
        session()->flash('message', 'Kata kunci berhasil ditambahkan.');
    }

    public function removeKeywordTable($index)
    {
        abort_unless($this->isAdmin(), 403, 'Hanya admin yang dapat menghapus kata kunci.');

        $oldCacheKey = $this->keywordsTableCacheKey();

        if (isset($this->keywordsTable[$index])) {
            $kw = $this->keywordsTable[$index]['keyword'];
            
            if (($key = array_search($kw, $this->primaryKeywords)) !== false) {
                unset($this->primaryKeywords[$key]);
                $this->primaryKeywords = array_values($this->primaryKeywords);
            }
            if (($key = array_search(ltrim($kw, '# '), $this->primaryKeywords)) !== false) {
                unset($this->primaryKeywords[$key]);
                $this->primaryKeywords = array_values($this->primaryKeywords);
            }
        }

        // Save to database
        $project = $this->resolveProjectOrFail($this->projectId);
        $project->update([
            'topics' => $this->primaryKeywords,
            'context_keywords' => $this->supportKeywords,
            'exclude_keywords' => $this->excludeKeywords,
        ]);

        // Evict old cache
        Cache::forget($oldCacheKey);
        Cache::forget($this->keywordsTableCacheKey());
        $this->rebuildKeywordsTable();

        session()->flash('message', 'Kata kunci berhasil dihapus.');
    }

    public function removeKeyword($type, $index)
    {
        abort_unless($this->isAdmin(), 403, 'Hanya admin yang dapat menghapus kata kunci.');

        if ($type == 'primary') {
            unset($this->primaryKeywords[$index]);
            $this->primaryKeywords = array_values($this->primaryKeywords);
        } elseif ($type == 'support') {
            unset($this->supportKeywords[$index]);
            $this->supportKeywords = array_values($this->supportKeywords);
        } else {
            unset($this->excludeKeywords[$index]);
            $this->excludeKeywords = array_values($this->excludeKeywords);
        }

        // Save to database
        $project = $this->resolveProjectOrFail($this->projectId);
        $project->update([
            'topics' => $this->primaryKeywords,
            'context_keywords' => $this->supportKeywords,
            'exclude_keywords' => $this->excludeKeywords,
        ]);

        // Evict old cache
        $projectIdDecoded = $this->getDecodedProjectId();
        $cacheKey = "project_keywords_{$projectIdDecoded}_" . 
            md5($this->startDate . '_' . $this->endDate . '_' . implode(',', $this->primaryKeywords));
        Cache::forget($cacheKey);

        session()->flash('message', 'Kata kunci berhasil dihapus.');
    }

    public function applyActiveFilters($query, $exclude = [])
    {
        // Query pembungkus union adalah Query Builder biasa yang memiliki toSql() yang mengandung union_res
        $isUnionWrapper = false;
        if ($query instanceof \Illuminate\Database\Query\Builder) {
            $from = $query->from;
            // Jika dari DB::raw() atau raw SQL string dan mengandung alias 'union_res'
            if ($from instanceof \Illuminate\Database\Query\Expression) {
                // Gunakan getValue() untuk mengambil string SQL dari DB::raw() agar aman di Laravel 10/11
                $fromValue = method_exists($from, 'getValue') ? $from->getValue(\Illuminate\Support\Facades\DB::connection()->getQueryGrammar()) : (string) $from;
                $isUnionWrapper = str_contains((string) $fromValue, 'union_res');
            } elseif (is_string($from)) {
                $isUnionWrapper = str_contains($from, 'union_res');
            }
        }

        if ($this->search) {
            $matchSql = $this->buildSourceAwareSearchSql($this->search);

            $query->where(function($q) use ($matchSql, $isUnionWrapper) {
                $sql = $matchSql['sql'];
                if ($isUnionWrapper) {
                    // Petakan kolom penelusuran search agar sesuai dengan tabel alias union_res
                    $sql = str_replace('articles.content', 'union_res.content', $sql);
                    $sql = str_replace('articles.title', 'union_res.title', $sql);
                }
                $q->whereRaw($sql, $matchSql['bindings']);
            });
        }

        if (!in_array('sentiment', $exclude) && !empty($this->selectedSentiment)) {
            if ($isUnionWrapper) {
                $query->whereExists(function ($sub) {
                    $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                        ->from('ai_analysis_results as ai')
                        ->whereRaw("((union_res.item_type = 'portal' AND ai.article_id = union_res.id) OR (union_res.item_type = 'social' AND ai.social_media_item_id = union_res.id))")
                        ->whereIn('ai.sentiment', $this->selectedSentiment);
                });
            } else {
                $query->whereHas('aiAnalysisResult', function($q) {
                    $q->whereIn('sentiment', $this->selectedSentiment);
                });
            }
        }

        if (!in_array('sources', $exclude) && !empty($this->selectedSources)) {
            $query->where(function($q) use ($isUnionWrapper) {
                $selectedSources = array_values(array_unique(array_filter($this->selectedSources)));
                foreach ($selectedSources as $index => $sourceLabel) {
                    $sourceSql = $this->buildSourceLabelSql($sourceLabel);
                    $sql = $sourceSql['sql'];
                    if ($isUnionWrapper) {
                        $sql = str_replace('articles.source_name', 'union_res.source_name', $sql);
                        $sql = str_replace('articles.url', 'union_res.url', $sql);
                        $sql = str_replace('articles.canonical_url', 'union_res.canonical_url', $sql);
                    }
                    if ($index === 0) {
                        $q->whereRaw($sql, $sourceSql['bindings']);
                    } else {
                        $q->orWhereRaw($sql, $sourceSql['bindings']);
                    }
                }
            });
        }

        if ($this->startDate) {
            $start = \Carbon\Carbon::parse($this->startDate)->startOfDay();
            $dateField = $isUnionWrapper ? 'union_res.published_at' : 'published_at';
            if ($this->endDate) {
                $end = \Carbon\Carbon::parse($this->endDate)->endOfDay();
                $query->whereBetween($dateField, [$start, $end]);
            } else {
                $end = \Carbon\Carbon::parse($this->startDate)->endOfDay();
                $query->whereBetween($dateField, [$start, $end]);
            }
        }

        return $query;
    }    public function getCounts()
    {
        $cacheKey = 'media_dashboard_counts:' . $this->getDecodedProjectId() . ':' . $this->dashboardCacheSignature();

        if (isset($this->countsMemo[$cacheKey])) {
            return $this->countsMemo[$cacheKey];
        }

        return $this->countsMemo[$cacheKey] = Cache::remember($cacheKey, 300, function () {
            $unionQuery = $this->projectArticlesQuery();
            $baseQuery = \Illuminate\Support\Facades\DB::table(\Illuminate\Support\Facades\DB::raw("(" . $unionQuery->toSql() . ") as union_res"))
                ->setBindings($unionQuery->getBindings());

            // Counts for the filter panel should reflect the visible article pool
            $sourceQuery = $this->applyActiveFilters(clone $baseQuery, ['sources', 'sentiment']);

            // Hanya hitung data yang SUDAH dinilai AI secara sukses
            $sourceQuery->whereExists(function ($sub) {
                $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('ai_analysis_results as ai_check')
                    ->whereRaw("((union_res.item_type = 'portal' AND ai_check.article_id = union_res.id) OR (union_res.item_type = 'social' AND ai_check.social_media_item_id = union_res.id))")
                    ->where('ai_check.analysis_status', '=', 'success');
            });

            $rawRows = (clone $sourceQuery)
                ->select([
                    \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN lower(coalesce(source_name,'')) like 'instagram%' THEN 1 ELSE 0 END) as instagram_count"),
                    \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN lower(coalesce(source_name,'')) like 'tiktok%' THEN 1 ELSE 0 END) as tiktok_count"),
                    \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN lower(coalesce(source_name,'')) like 'youtube%' THEN 1 ELSE 0 END) as youtube_count"),
                    \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN lower(coalesce(source_name,'')) like 'facebook%' THEN 1 ELSE 0 END) as facebook_count"),
                    \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN lower(coalesce(source_name,'')) like 'threads%' THEN 1 ELSE 0 END) as threads_count"),
                    \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN lower(coalesce(source_name,'')) like 'twitter%' OR lower(coalesce(source_name,'')) like 'x.com%' OR lower(coalesce(source_name,'')) = 'x' THEN 1 ELSE 0 END) as twitter_count")
                ])
                ->first();

            $sourceCounts = [
                'Instagram' => (int) ($rawRows->instagram_count ?? 0),
                'TikTok'    => (int) ($rawRows->tiktok_count ?? 0),
                'Youtube'   => (int) ($rawRows->youtube_count ?? 0),
                'Facebook'  => (int) ($rawRows->facebook_count ?? 0),
                'Threads'   => (int) ($rawRows->threads_count ?? 0),
                'Twitter'   => (int) ($rawRows->twitter_count ?? 0),
            ];

            // News = total - social platforms
            $totalAll = (clone $sourceQuery)->count();
            $totalSocial = array_sum($sourceCounts);
            $sourceCounts['News'] = max(0, $totalAll - $totalSocial);

            $sentimentQuery = $this->applyActiveFilters(clone $baseQuery, ['sentiment']);
            $sentimentQueryWithAI = (clone $sentimentQuery)->join('ai_analysis_results as ai', function ($join) {
                $join->on(function ($q) {
                    $q->whereRaw("((union_res.item_type = 'portal' AND ai.article_id = union_res.id) OR (union_res.item_type = 'social' AND ai.social_media_item_id = union_res.id))");
                })
                ->whereRaw("ai.analysis_status = 'success'")
                ->whereNotNull('ai.summary')
                ->whereNotNull('ai.sentiment')
                ->whereNotNull('ai.risk_level');
            });

            $agg = (clone $sentimentQueryWithAI)
                ->select([
                    \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN ai.sentiment = 'positive' THEN 1 ELSE 0 END) as pos_count"),
                    \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN ai.sentiment = 'neutral' THEN 1 ELSE 0 END) as neu_count"),
                    \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN ai.sentiment = 'negative' THEN 1 ELSE 0 END) as neg_count"),
                    \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN ai.risk_level = 'low' THEN 1 ELSE 0 END) as low_count"),
                    \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN ai.risk_level = 'medium' THEN 1 ELSE 0 END) as med_count"),
                    \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN ai.risk_level = 'high' THEN 1 ELSE 0 END) as high_count"),
                    \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN ai.risk_level = 'critical' THEN 1 ELSE 0 END) as crit_count")
                ])
                ->first();

            $sentimentCounts = [
                'positive' => (int) ($agg->pos_count ?? 0),
                'neutral'  => (int) ($agg->neu_count ?? 0),
                'negative' => (int) ($agg->neg_count ?? 0),
            ];

            $riskCounts = [
                'low' => (int) ($agg->low_count ?? 0),
                'medium' => (int) ($agg->med_count ?? 0),
                'high' => (int) ($agg->high_count ?? 0),
                'critical' => (int) ($agg->crit_count ?? 0),
            ];
            return [
                'sources'    => $sourceCounts,
                'sentiments' => $sentimentCounts,
                'risks'      => $riskCounts,
            ];
        });
    }

    public function getArticles()
    {
        $cacheKey = 'media_dashboard_articles:v2:' . $this->getDecodedProjectId() . ':' . $this->dashboardCacheSignature() . ':' . md5(json_encode([
            'sortBy' => $this->sortBy,
            'limit' => $this->limit,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if (isset($this->articlesMemo[$cacheKey])) {
            return $this->articlesMemo[$cacheKey];
        }

        // Selalu hitung langsung agar loadMore tidak tertahan snapshot cache lama.
        $unionQuery = $this->projectArticlesQuery();
        
        // Bungkus kueri UNION agar sorting, filtering, dan join AI tetap aman di Postgresql
        $query = \Illuminate\Support\Facades\DB::table(\Illuminate\Support\Facades\DB::raw("(" . $unionQuery->toSql() . ") as union_res"))
            ->setBindings($unionQuery->getBindings());

        // Select kolom union_res saja agar kolom id asli tidak tertimpa kolom id dari leftJoin tabel AI
        $query->select('union_res.*');

        $query = $this->applyActiveFilters($query);

        // Hanya tampilkan data yang SUDAH dinilai AI secara sukses dan lengkap (sesuai scope CompleteOfficialAiResult)
        // Gunakan nilai literal langsung di raw SQL agar bindings parameter subquery UNION tidak bergeser
        $query->whereExists(function ($sub) {
            $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                ->from('ai_analysis_results as ai_check')
                ->whereRaw("((union_res.item_type = 'portal' AND ai_check.article_id = union_res.id) OR (union_res.item_type = 'social' AND ai_check.social_media_item_id = union_res.id))")
                ->whereRaw("ai_check.analysis_status = 'success'")
                ->whereRaw("ai_check.reach_method = 'ai_reader_estimate_v1'")
                ->whereNotNull('ai_check.project_estimated_readers')
                ->whereRaw("ai_check.project_estimated_readers >= 1")
                ->whereNotNull('ai_check.project_reach_score')
                ->whereNotNull('ai_check.project_reach_level')
                ->whereNotNull('ai_check.project_reach_band');
        });

        if ($this->sortBy == 'popular') {
            $query->leftJoin('ai_analysis_results as ai_pop', function ($join) {
                $join->on(function ($q) {
                    $q->whereRaw("((union_res.item_type = 'portal' AND ai_pop.article_id = union_res.id) OR (union_res.item_type = 'social' AND ai_pop.social_media_item_id = union_res.id))");
                })
                ->whereRaw("ai_pop.analysis_status = 'success'")
                ->whereRaw("ai_pop.reach_method = 'ai_reader_estimate_v1'");
            })
            ->orderByRaw('COALESCE(ai_pop.project_estimated_readers, 0) DESC')
            ->orderBy('union_res.published_at', 'desc');
        } else {
            $query->orderBy('union_res.published_at', 'desc');
        }

        $fetchLimit = max($this->limit * 4, $this->limit);
        $articles = $query->limit($fetchLimit)->get();

        if (empty($this->selectedSources)) {
            $articles = $this->dedupeSocialArticles($articles);
        }

        $articles = $articles->take($this->limit)->values();

        if ($articles->isEmpty()) {
            return $this->articlesMemo[$cacheKey] = collect();
        }

        // Pisahkan penarikan model riil untuk portal vs media sosial
        $portalIds = $articles->where('item_type', 'portal')->pluck('id')->all();
        $socialIds = $articles->where('item_type', 'social')->pluck('id')->all();

        $portalsMap = collect();
        if (!empty($portalIds)) {
            $portalsMap = Article::query()
                ->with('aiAnalysisResult')
                ->whereIn('id', $portalIds)
                ->get()
                ->keyBy('id');
        }

        $socialsMap = collect();
        if (!empty($socialIds)) {
            $socialsMap = SocialMediaItem::query()
                ->with('aiAnalysisResult')
                ->whereIn('id', $socialIds)
                ->get()
                ->keyBy('id');
        }

        // Gabungkan kembali sesuai urutan sorting query asli dan lengkapi properti mock agar kompatibel dengan view blade
        $orderedCollection = collect();
        foreach ($articles as $item) {
            if ($item->item_type === 'portal') {
                $model = $portalsMap->get($item->id);
                if ($model) {
                    $model->item_type = 'portal';
                    $orderedCollection->push($model);
                }
            } else {
                $model = $socialsMap->get($item->id);
                if ($model) {
                    // Buat object adapter/mock agar properti social compatible dengan pembacaan $article di view blade
                    $mock = new Article();
                    $mock->id = $model->id;
                    $mock->title = "Post dari {$model->platform} oleh " . ($model->author_name ?: 'Pengguna');
                    $mock->content = $model->content;
                    $mock->source_name = $model->platform;
                    $mock->published_at = $model->posted_at;
                    $mock->category = 'social';
                    $mock->url = $model->post_url;
                    $mock->canonical_url = $model->post_url;
                    $mock->item_type = 'social';
                    
                    // Salin relasi aiAnalysisResult
                    if ($model->relationLoaded('aiAnalysisResult')) {
                        $mock->setRelation('aiAnalysisResult', $model->aiAnalysisResult);
                    }
                    
                    $orderedCollection->push($mock);
                }
            }
        }

        return $this->articlesMemo[$cacheKey] = $orderedCollection;
    }

    public function getTotalArticlesCount(): int
    {
        $cacheKey = 'media_dashboard_total_articles:' . $this->getDecodedProjectId() . ':' . $this->dashboardCacheSignature();

        if (isset($this->totalArticlesCountMemo[$cacheKey])) {
            return $this->totalArticlesCountMemo[$cacheKey];
        }

        $unionQuery = $this->projectArticlesQuery();
        $query = \Illuminate\Support\Facades\DB::table(\Illuminate\Support\Facades\DB::raw("(" . $unionQuery->toSql() . ") as union_res"))
            ->setBindings($unionQuery->getBindings());

        $query = $this->applyActiveFilters($query);

        // Hanya hitung data yang SUDAH dinilai AI secara sukses dan lengkap (sesuai scope CompleteOfficialAiResult)
        // Gunakan nilai literal langsung di raw SQL agar bindings parameter subquery UNION tidak bergeser
        $query->whereExists(function ($sub) {
            $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                ->from('ai_analysis_results as ai_check')
                ->whereRaw("((union_res.item_type = 'portal' AND ai_check.article_id = union_res.id) OR (union_res.item_type = 'social' AND ai_check.social_media_item_id = union_res.id))")
                ->whereRaw("ai_check.analysis_status = 'success'")
                ->whereRaw("ai_check.reach_method = 'ai_reader_estimate_v1'")
                ->whereNotNull('ai_check.project_estimated_readers')
                ->whereRaw("ai_check.project_estimated_readers >= 1")
                ->whereNotNull('ai_check.project_reach_score')
                ->whereNotNull('ai_check.project_reach_level')
                ->whereNotNull('ai_check.project_reach_band');
        });

        return $this->totalArticlesCountMemo[$cacheKey] = (int) $query->count();
    }

    protected function dedupeSocialArticles($articles)
    {
        $seen = [];

        return $articles->filter(function ($article) use (&$seen) {
            if (! $this->isSocialArticle($article)) {
                return true;
            }

            $fingerprint = $this->socialArticleFingerprint($article);
            if (isset($seen[$fingerprint])) {
                return false;
            }

            $seen[$fingerprint] = true;
            return true;
        });
    }

    protected function isSocialArticle($article): bool
    {
        $source = strtolower(trim((string) ($article->source_name ?? '')));
        $category = strtolower(trim((string) ($article->category ?? '')));

        return $category === 'social'
            || in_array($source, self::SOCIAL_SOURCE_NAMES, true)
            || str_contains($source, 'facebook')
            || str_contains($source, 'instagram')
            || str_contains($source, 'tiktok');
    }

    protected function socialArticleFingerprint($article): string
    {
        $source = strtolower(trim((string) ($article->source_name ?? 'social')));
        $date = $article->published_at
            ? \Carbon\Carbon::parse($article->published_at)->format('Y-m-d H:i')
            : 'unknown-date';
        $author = strtolower(trim((string) ($article->author ?? '')));

        if ($author === '' && preg_match('/oleh\s+(.+)$/i', (string) $article->title, $matches)) {
            $author = strtolower(trim($matches[1]));
        }

        $content = strtolower(strip_tags((string) ($article->content ?? '')));
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        $content = preg_replace('/https?:\/\/\S+/i', '', $content) ?? $content;
        $content = preg_replace('/\s+/u', ' ', $content) ?? $content;
        $content = trim(Str::limit($content, 420, ''));

        return md5($source . '|' . $author . '|' . $date . '|' . $content);
    }

    protected function normalizeKeywordSearchTerm(?string $keyword): string
    {
        $keyword = trim((string) $keyword);
        $keyword = preg_replace('/^#+/u', '', $keyword) ?? $keyword;
        $keyword = preg_replace("/['’‘`]/u", '', $keyword) ?? $keyword;

        return trim($keyword);
    }

    protected function applyKeywordSearch($query, string $keyword): void
    {
        $matchSql = $this->buildSourceAwareSearchSql($keyword);

        if ($matchSql['sql'] === '1 = 0') {
            return;
        }

        $query->where(function ($q) use ($matchSql) {
            $q->whereRaw($matchSql['sql'], $matchSql['bindings']);
        });
    }

    public function getTrendPoints(string $mode, string $metric = 'penyebutan', ?int $forceMax = null): array
    {
        $projectIdDecoded = $this->getDecodedProjectId();
        $stateSignature = md5(json_encode([
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'keyword' => $this->selectedKeyword,
            'search' => $this->search,
            'sources' => $this->selectedSources,
            'sentiment' => $this->selectedSentiment,
            'category' => $this->selectedCategory,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $cacheKey = "project_trend_{$projectIdDecoded}_{$mode}_{$metric}_{$forceMax}_{$stateSignature}";

        if (isset($this->trendPointsMemo[$cacheKey])) {
            return $this->trendPointsMemo[$cacheKey];
        }

        $points = Cache::remember($cacheKey, 120, function () use ($mode, $metric, $forceMax) {
            $unionQuery = $this->projectArticlesQuery();
            $baseQuery = \Illuminate\Support\Facades\DB::table(\Illuminate\Support\Facades\DB::raw("(" . $unionQuery->toSql() . ") as union_res"))
                ->setBindings($unionQuery->getBindings());

            $baseQuery = $this->applyActiveFilters($baseQuery, ['date']);
            
            if (!empty($this->selectedKeyword)) {
                $this->applyKeywordSearch($baseQuery, $this->selectedKeyword);
            }
            
            $start_date = $this->startDate;
            if (!$start_date) {
                $oldest = (clone $baseQuery)->orderBy('union_res.published_at', 'asc')->value('published_at');
                $start_date = $oldest ? \Carbon\Carbon::parse($oldest)->format('Y-m-d') : now()->subDays(30)->format('Y-m-d');
            }
            
            $start = \Carbon\Carbon::parse($start_date)->startOfDay();
            $end = \Carbon\Carbon::parse($this->endDate ?? now()->format('Y-m-d'))->endOfDay();
            
            // Prevent massive N+1 loop for 'harian' mode if difference is too large (cap at 90 days)
            if ($mode === 'harian' && $start->diffInDays($end) > 90) {
                $start = (clone $end)->subDays(90)->startOfDay();
            }
            
            // Helper closure: compute value based on metric type within a date range
            $countForMetric = function($dateStart, $dateEnd) use ($baseQuery, $metric) {
                $periodQuery = (clone $baseQuery)->whereBetween('union_res.published_at', [
                    $dateStart->format('Y-m-d H:i:s'),
                    $dateEnd->format('Y-m-d H:i:s')
                ]);
                if ($metric === 'jangkauan') {
                    $items = (clone $periodQuery)->select(['union_res.id', 'union_res.item_type'])->get();
                    if ($items->isEmpty()) return 0;
                    
                    $portalIds = $items->where('item_type', 'portal')->pluck('id')->all();
                    $socialIds = $items->where('item_type', 'social')->pluck('id')->all();
                    
                    return (int) \App\Models\AiAnalysisResult::query()
                        ->where('analysis_status', 'success')
                        ->where(function ($q) use ($portalIds, $socialIds) {
                            $q->whereIn('article_id', $portalIds)
                              ->orWhereIn('social_media_item_id', $socialIds);
                        })
                        ->whereNotNull('project_estimated_readers')
                        ->sum('project_estimated_readers');
                } elseif ($metric === 'sentimen_positif') {
                    $items = (clone $periodQuery)->select(['union_res.id', 'union_res.item_type'])->get();
                    if ($items->isEmpty()) return 0;
                    
                    $portalIds = $items->where('item_type', 'portal')->pluck('id')->all();
                    $socialIds = $items->where('item_type', 'social')->pluck('id')->all();
                    
                    return (int) \App\Models\AiAnalysisResult::query()
                        ->where(function ($q) use ($portalIds, $socialIds) {
                            $q->whereIn('article_id', $portalIds)
                              ->orWhereIn('social_media_item_id', $socialIds);
                        })
                        ->where('analysis_status', 'success')
                        ->where('sentiment', 'positive')
                        ->count();
                } elseif ($metric === 'sentimen_netral') {
                    $items = (clone $periodQuery)->select(['union_res.id', 'union_res.item_type'])->get();
                    if ($items->isEmpty()) return 0;
                    
                    $portalIds = $items->where('item_type', 'portal')->pluck('id')->all();
                    $socialIds = $items->where('item_type', 'social')->pluck('id')->all();
                    
                    return (int) \App\Models\AiAnalysisResult::query()
                        ->where(function ($q) use ($portalIds, $socialIds) {
                            $q->whereIn('article_id', $portalIds)
                              ->orWhereIn('social_media_item_id', $socialIds);
                        })
                        ->where('analysis_status', 'success')
                        ->where('sentiment', 'neutral')
                        ->count();
                } elseif ($metric === 'sentimen_negatif') {
                    $items = (clone $periodQuery)->select(['union_res.id', 'union_res.item_type'])->get();
                    if ($items->isEmpty()) return 0;
                    
                    $portalIds = $items->where('item_type', 'portal')->pluck('id')->all();
                    $socialIds = $items->where('item_type', 'social')->pluck('id')->all();
                    
                    return (int) \App\Models\AiAnalysisResult::query()
                        ->where(function ($q) use ($portalIds, $socialIds) {
                            $q->whereIn('article_id', $portalIds)
                              ->orWhereIn('social_media_item_id', $socialIds);
                        })
                        ->where('analysis_status', 'success')
                        ->where('sentiment', 'negative')
                        ->count();
                } elseif ($metric === 'sentimen') {
                    $items = (clone $periodQuery)->select(['union_res.id', 'union_res.item_type'])->get();
                    if ($items->isEmpty()) return 50;
                    
                    $portalIds = $items->where('item_type', 'portal')->pluck('id')->all();
                    $socialIds = $items->where('item_type', 'social')->pluck('id')->all();
                    
                    $pos = \App\Models\AiAnalysisResult::query()
                        ->where(function ($q) use ($portalIds, $socialIds) {
                            $q->whereIn('article_id', $portalIds)
                              ->orWhereIn('social_media_item_id', $socialIds);
                        })
                        ->where('analysis_status', 'success')->whereNotNull('sentiment')
                        ->where('sentiment', 'positive')->count();
                        
                    $neg = \App\Models\AiAnalysisResult::query()
                        ->where(function ($q) use ($portalIds, $socialIds) {
                            $q->whereIn('article_id', $portalIds)
                              ->orWhereIn('social_media_item_id', $socialIds);
                        })
                        ->where('analysis_status', 'success')->whereNotNull('sentiment')
                        ->where('sentiment', 'negative')->count();
                        
                    $tot = $pos + $neg;
                    // Return a score: net-positive ratio -100 to +100, shifted to 0-100 for charting
                    return $tot > 0 ? (int) round((($pos - $neg) / $tot) * 50 + 50) : 50;
                } else {
                    return (clone $periodQuery)->count();
                }
            };

            $points = [];
            
            if ($mode === 'harian') {
                // Group by each day
                $current = clone $start;
                while ($current->lte($end)) {
                    $dayStart = (clone $current)->startOfDay();
                    $dayEnd = (clone $current)->endOfDay();
                    
                    $count = $countForMetric($dayStart, $dayEnd);
                    
                    $points[] = [
                        'count' => $count,
                        'label' => $current->format('d M'),
                    ];
                    
                    $current->addDay();
                }
            } elseif ($mode === 'mingguan') {
                // Group by weeks
                $current = clone $start;
                $weekNum = 1;
                while ($current->lte($end)) {
                    $weekStart = (clone $current)->startOfDay();
                    $weekEnd = (clone $current)->addDays(6)->endOfDay();
                    if ($weekEnd->gt($end)) {
                        $weekEnd = clone $end;
                    }
                    
                    $count = $countForMetric($weekStart, $weekEnd);
                    
                    $points[] = [
                        'count' => $count,
                        'label' => 'W' . $weekNum . ' (' . $weekStart->format('d M') . ')',
                    ];
                    
                    $current->addDays(7);
                    $weekNum++;
                }
            } elseif ($mode === 'bulanan') {
                // Group by months
                $current = (clone $start)->startOfMonth();
                while ($current->lte($end)) {
                    $monthStart = (clone $current)->startOfMonth();
                    $monthEnd = (clone $current)->endOfMonth();
                    
                    if ($monthStart->lt($start)) {
                        $monthStart = clone $start;
                    }
                    if ($monthEnd->gt($end)) {
                        $monthEnd = clone $end;
                    }
                    
                    $count = $countForMetric($monthStart, $monthEnd);
                    
                    $points[] = [
                        'count' => $count,
                        'label' => $current->format('M y'),
                    ];
                    
                    $current->addMonth();
                }
            }
            
            // Ensure at least 2 points for SVG drawing
            if (count($points) < 2) {
                if (empty($points)) {
                    $points[] = ['count' => 0, 'label' => $start->format('d M')];
                }
                $points[] = ['count' => $points[0]['count'], 'label' => $end->format('d M')];
            }

            // Scale to SVG viewport: X from 50 to 950, Y from 40 to 140
            $maxCount = $forceMax !== null ? $forceMax : collect($points)->max('count');
            $maxCount = $maxCount > 0 ? $maxCount : 1;
            
            $rendered = [];
            $totalPoints = count($points);
            for ($i = 0; $i < $totalPoints; $i++) {
                $x = 50 + ($i * (900 / ($totalPoints - 1)));
                
                $actualMax = $forceMax !== null ? $forceMax : collect($points)->max('count');
                if ($actualMax === 0) {
                    $y = 140;
                } else {
                    $y = 140 - (($points[$i]['count'] / $maxCount) * 100);
                }
                
                $rendered[] = [
                    'x' => (int) $x,
                    'y' => (int) $y,
                    'count' => $points[$i]['count'],
                    'label' => $points[$i]['label']
                ];
            }
            
            return $rendered;
        });

        $this->trendPointsMemo[$cacheKey] = $points;

        return $points;
    }

    public function getProjectSources()
    {
        $cacheKey = 'media_dashboard_project_sources:' . $this->getDecodedProjectId() . ':' . $this->dashboardCacheSignature();

        if (isset($this->projectSourcesMemo[$cacheKey])) {
            return $this->projectSourcesMemo[$cacheKey];
        }

        $unionQuery = $this->projectArticlesQuery();
        $baseQuery = \Illuminate\Support\Facades\DB::table(\Illuminate\Support\Facades\DB::raw("(" . $unionQuery->toSql() . ") as union_res"))
            ->setBindings($unionQuery->getBindings());

        $baseQuery = $this->applyActiveFilters($baseQuery);
        $rawSourcesClean = clone $baseQuery;
        
        if ($rawSourcesClean instanceof \Illuminate\Database\Query\Builder) {
            $rawSourcesClean->columns = [];
        } else {
            $rawSourcesClean->getQuery()->columns = [];
        }

        $rawSources = $rawSourcesClean
            ->join('ai_analysis_results as ai', function ($join) {
                $join->on(function ($q) {
                    $q->whereRaw("((union_res.item_type = 'portal' AND ai.article_id = union_res.id) OR (union_res.item_type = 'social' AND ai.social_media_item_id = union_res.id))");
                })
                ->whereRaw("ai.analysis_status = 'success'");
            })
            ->select([
                \Illuminate\Support\Facades\DB::raw("lower(coalesce(union_res.source_name, '')) as source_key"),
                \Illuminate\Support\Facades\DB::raw("MIN(COALESCE(NULLIF(union_res.canonical_url, ''), NULLIF(union_res.url, ''))) as sample_url"),
                \Illuminate\Support\Facades\DB::raw("count(union_res.id) as total"),
                \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN ai.sentiment = 'positive' THEN 1 ELSE 0 END) as positive"),
                \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN ai.sentiment = 'neutral' THEN 1 ELSE 0 END) as neutral"),
                \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN ai.sentiment = 'negative' THEN 1 ELSE 0 END) as negative")
            ])
            ->groupBy('source_key')
            ->orderByDesc('total')
            ->get();

        $aggregated = [];

        foreach ($rawSources as $row) {
            $sourceKey = (string) ($row->source_key ?? '');
            $sourceName = $this->normalizeSourceLabel($sourceKey);

            if ($sourceName === '') {
                $sourceName = $this->fallbackSourceLabelFromUrl((string) ($row->sample_url ?? ''));
            }

            if ($sourceName === '') {
                $sourceName = $sourceKey !== '' ? $sourceKey : 'Sumber tidak diketahui';
            }

            if (!isset($aggregated[$sourceName])) {
                $aggregated[$sourceName] = [
                    'source_name' => $sourceName,
                    'total' => 0,
                    'positive' => 0,
                    'neutral' => 0,
                    'negative' => 0,
                ];
            }

            $aggregated[$sourceName]['total'] += (int) $row->total;
            $aggregated[$sourceName]['positive'] += (int) ($row->positive ?? 0);
            $aggregated[$sourceName]['neutral'] += (int) ($row->neutral ?? 0);
            $aggregated[$sourceName]['negative'] += (int) ($row->negative ?? 0);
        }

        return $this->projectSourcesMemo[$cacheKey] = collect(array_values($aggregated))
            ->sortByDesc('total')
            ->values()
            ->map(static function (array $row): object {
                return (object) $row;
            });
    }

    public function getWawasan()
    {
        $cacheKey = 'media_dashboard_wawasan:' . $this->getDecodedProjectId() . ':' . $this->dashboardCacheSignature();

        if (isset($this->wawasanMemo[$cacheKey])) {
            return $this->wawasanMemo[$cacheKey];
        }

        return $this->wawasanMemo[$cacheKey] = Cache::remember($cacheKey, 120, function () {
            $project = $this->resolveProjectOrFail($this->projectId);
            $unionQuery = $this->projectArticlesQuery();
            $baseQuery = \Illuminate\Support\Facades\DB::table(\Illuminate\Support\Facades\DB::raw("(" . $unionQuery->toSql() . ") as union_res"))
                ->setBindings($unionQuery->getBindings());
            $baseQuery = $this->applyActiveFilters($baseQuery);
            $total = $baseQuery->count();
            if ($total === 0) {
                return [
                    'total' => 0,
                    'positive_pct' => 0,
                    'neutral_pct' => 0,
                    'negative_pct' => 0,
                    'reputation_score' => 100,
                    'crisis_signal' => 'Rendah',
                    'crisis_color' => 'emerald',
                    'summary' => 'Belum ada data artikel yang terkumpul untuk proyek ini. Silakan tambahkan artikel atau hubungkan dengan scraper untuk mendapatkan analisis wawasan otomatis.',
                    'recommendations' => [
                        'Mulai kumpulkan data dari media berita atau media sosial.',
                        'Definisikan kata kunci utama dan pendukung untuk memfokuskan pencarian.'
                    ],
                    'categories' => [],
                    'sources' => [],
                    'negative_issues' => [],
                    'risk_triggers' => [],
                    'sentiment_shift' => [
                        'label' => 'Belum ada data pembanding',
                        'tone' => 'slate',
                        'current_negative_pct' => 0,
                        'previous_negative_pct' => 0,
                        'delta' => 0,
                    ],
                    'response_actions' => [
                        ['level' => 'Pantau', 'text' => 'Kumpulkan data terlebih dahulu sebelum mengambil keputusan komunikasi.'],
                    ],
                    'viral_status' => 'Normal',
                    'viral_color' => 'slate',
                    'viral_desc' => 'Volume berita stabil',
                ];
            }

            $baseQueryWithAI = (clone $baseQuery)->join('ai_analysis_results as ai', function ($join) {
                $join->on(function ($q) {
                    $q->whereRaw("((union_res.item_type = 'portal' AND ai.article_id = union_res.id) OR (union_res.item_type = 'social' AND ai.social_media_item_id = union_res.id))");
                })
                ->whereRaw("ai.analysis_status = 'success'")
                ->whereNotNull('ai.summary')
                ->whereNotNull('ai.sentiment')
                ->whereNotNull('ai.risk_level');
            });
            $total = (clone $baseQuery)->count();

            $sentimentCountsClean = clone $baseQueryWithAI;
            if ($sentimentCountsClean instanceof \Illuminate\Database\Query\Builder) {
                $sentimentCountsClean->columns = [];
            } else {
                $sentimentCountsClean->getQuery()->columns = [];
            }
            $sentimentCounts = $sentimentCountsClean
                ->select([
                    \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN ai.sentiment = 'positive' THEN 1 ELSE 0 END) as positive"),
                    \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN ai.sentiment = 'neutral' THEN 1 ELSE 0 END) as neutral"),
                    \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN ai.sentiment = 'negative' THEN 1 ELSE 0 END) as negative")
                ])
                ->first();
            $pos = (int) ($sentimentCounts->positive ?? 0);
            $neu = (int) ($sentimentCounts->neutral ?? 0);
            $neg = (int) ($sentimentCounts->negative ?? 0);

            $pos_pct = round(($pos / $total) * 100);
            $neu_pct = round(($neu / $total) * 100);
            $neg_pct = round(($neg / $total) * 100);

            // Reputation score formula: (Pos + 0.5 * Neu) / Total * 100
            $reputation_score = round((($pos + ($neu * 0.5)) / $total) * 100);

            if ($neg_pct >= 30) {
                $crisis_signal = 'Tinggi';
                $crisis_color = 'rose';
            } elseif ($neg_pct >= 15) {
                $crisis_signal = 'Sedang';
                $crisis_color = 'amber';
            } else {
                $crisis_signal = 'Rendah';
                $crisis_color = 'emerald';
            }

            // Prioritaskan wawasan buatan AI (jika sudah di-generate)
            if (!empty($project->ai_insight_summary) && !empty($project->ai_insight_recommendations)) {
                $summary = $project->ai_insight_summary;
                $recs = $project->ai_insight_recommendations;
            } else {
                // Generate dynamic executive summary based on data (Fallback Template)
                $summary = "Berdasarkan analisis terhadap **{$total}** penyebutan, proyek **" . strtoupper($this->projectName) . "** memiliki reputasi media yang ";
                if ($reputation_score >= 75) {
                    $summary .= "sangat kuat (**{$reputation_score}/100**). Sentimen positif mendominasi perbincangan sebesar **{$pos_pct}%**, yang mencerminkan respons masyarakat yang sangat baik.";
                } elseif ($reputation_score >= 50) {
                    $summary .= "cukup stabil (**{$reputation_score}/100**). Sebagian besar perbincangan bersifat netral (**{$neu_pct}%**), menunjukkan liputan berita yang bersifat informatif tanpa opini yang kuat.";
                } else {
                    $summary .= "kurang kondusif (**{$reputation_score}/100**). Volume sentimen negatif mencapai **{$neg_pct}%**, mengindikasikan adanya isu sensitif atau kritik yang perlu segera direspon.";
                }

                // Recommendations based on sentiment
                $recs = [];
                if ($neg_pct >= 20) {
                    $recs[] = "Lakukan klarifikasi segera melalui siaran pers terkait isu negatif utama yang berkembang.";
                    $recs[] = "Tingkatkan frekuensi publikasi berita positif untuk menyeimbangkan sentimen di media online.";
                } else {
                    $recs[] = "Pertahankan kampanye komunikasi yang sedang berjalan dan perluas jangkauan ke media nasional terkemuka.";
                    $recs[] = "Optimalkan kata kunci pendukung untuk menangkap peluang publikasi yang lebih luas.";
                }
                $recs[] = "Gunakan influencer lokal untuk memperkuat pesan positif di kanal media sosial utama.";
            }

            // Top categories
            $categoriesQuery = clone $baseQuery;
            if ($categoriesQuery instanceof \Illuminate\Database\Query\Builder) {
                $categoriesQuery->columns = [];
            } else {
                $categoriesQuery->getQuery()->columns = [];
            }
            $categories = $categoriesQuery->select(['category', \DB::raw('count(*) as total')])
                ->groupBy('category')
                ->orderByDesc('total')
                ->limit(5)
                ->get()
                ->map(fn ($row) => (array) $row)
                ->toArray();

            // Top sources with sentiment breakdown
            $sourcesQuery = clone $baseQuery;
            if ($sourcesQuery instanceof \Illuminate\Database\Query\Builder) {
                $sourcesQuery->columns = [];
            } else {
                $sourcesQuery->getQuery()->columns = [];
            }
            $sources = $sourcesQuery->leftJoin('ai_analysis_results as ai', function ($join) {
                $join->on(function ($q) {
                    $q->whereRaw("((union_res.item_type = 'portal' AND ai.article_id = union_res.id) OR (union_res.item_type = 'social' AND ai.social_media_item_id = union_res.id))");
                })
                ->whereRaw("ai.analysis_status = 'success'");
            })
            ->select([
                'union_res.source_name',
                \DB::raw('count(union_res.id) as total'),
                \DB::raw("SUM(CASE WHEN ai.sentiment = 'positive' THEN 1 ELSE 0 END) as positive"),
                \DB::raw("SUM(CASE WHEN ai.sentiment = 'neutral' THEN 1 ELSE 0 END) as neutral"),
                \DB::raw("SUM(CASE WHEN ai.sentiment = 'negative' THEN 1 ELSE 0 END) as negative")
            ])
            ->groupBy('union_res.source_name')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();

            $negativeIssuesQuery = clone $baseQueryWithAI;
            if ($negativeIssuesQuery instanceof \Illuminate\Database\Query\Builder) {
                $negativeIssuesQuery->columns = [];
            } else {
                $negativeIssuesQuery->getQuery()->columns = [];
            }
            $negativeIssues = $negativeIssuesQuery
                ->where('ai.sentiment', 'negative')
                ->select([
                    \Illuminate\Support\Facades\DB::raw('COALESCE(ai.main_issue, union_res.category, union_res.title) as issue'),
                    \Illuminate\Support\Facades\DB::raw('COUNT(*) as total'),
                    \Illuminate\Support\Facades\DB::raw('MIN(union_res.url) as url')
                ])
                ->groupBy('issue')
                ->orderByDesc('total')
                ->limit(5)
                ->get()
                ->map(fn ($row) => [
                    'issue' => Str::limit((string) $row->issue, 90),
                    'total' => (int) $row->total,
                    'pct' => $neg > 0 ? round(((int) $row->total / $neg) * 100) : 0,
                    'url' => $row->url ?: null,
                ])
                ->toArray();

            $riskTriggersQuery = clone $baseQueryWithAI;
            if ($riskTriggersQuery instanceof \Illuminate\Database\Query\Builder) {
                $riskTriggersQuery->columns = [];
            } else {
                $riskTriggersQuery->getQuery()->columns = [];
            }
            $riskTriggers = $riskTriggersQuery
                ->whereIn('ai.risk_level', ['high', 'critical'])
                ->orderByRaw("CASE ai.risk_level WHEN 'critical' THEN 2 WHEN 'high' THEN 1 ELSE 0 END DESC")
                ->orderByDesc('ai.project_estimated_readers')
                ->orderByDesc('union_res.published_at')
            ->limit(5)
            ->select([
                'union_res.id',
                'union_res.title',
                'union_res.source_name',
                'union_res.url',
                'union_res.published_at',
                'ai.risk_level',
                'ai.risk_reason',
                'ai.sentiment',
                'ai.project_estimated_readers',
            ])
            ->get()
            ->map(fn ($row) => [
                'title' => Str::limit((string) $row->title, 86),
                'source' => $row->source_name ?: 'Sumber tidak diketahui',
                'url' => $row->url,
                'risk_level' => $row->risk_level === 'critical' ? 'Kritis' : 'Tinggi',
                'risk_reason' => Str::limit((string) ($row->risk_reason ?: 'Alasan risiko belum tersedia.'), 110),
                'reach' => number_format((int) ($row->project_estimated_readers ?? 0), 0, ',', '.'),
                'published_at' => $row->published_at ? \Carbon\Carbon::parse($row->published_at)->format('d/m/y') : 'Tanggal tidak tersedia',
            ])
            ->toArray();

        $rangeStart = $this->startDate ? \Carbon\Carbon::parse($this->startDate)->startOfDay() : now()->subDays(7)->startOfDay();
        $rangeEnd = $this->endDate ? \Carbon\Carbon::parse($this->endDate)->endOfDay() : now()->endOfDay();
        $midpoint = $rangeStart->copy()->addSeconds(max(1, (int) floor($rangeStart->diffInSeconds($rangeEnd) / 2)));

        $currentHalf = (clone $baseQueryWithAI)->whereBetween('union_res.published_at', [$midpoint, $rangeEnd]);
        $previousHalf = (clone $baseQueryWithAI)->whereBetween('union_res.published_at', [$rangeStart, $midpoint->copy()->subSecond()]);
        $currentTotal = (clone $currentHalf)->count();
        $previousTotal = (clone $previousHalf)->count();
        $currentNegativePct = $currentTotal > 0 ? round(((clone $currentHalf)->where('ai.sentiment', 'negative')->count() / $currentTotal) * 100) : 0;
        $previousNegativePct = $previousTotal > 0 ? round(((clone $previousHalf)->where('ai.sentiment', 'negative')->count() / $previousTotal) * 100) : 0;
        $negativeDelta = $currentNegativePct - $previousNegativePct;
        $sentimentShift = [
            'label' => $negativeDelta > 0
                ? 'Negatif naik dibanding paruh awal periode'
                : ($negativeDelta < 0 ? 'Negatif turun dibanding paruh awal periode' : 'Negatif relatif stabil'),
            'tone' => $negativeDelta > 5 ? 'rose' : ($negativeDelta < -5 ? 'emerald' : 'slate'),
            'current_negative_pct' => $currentNegativePct,
            'previous_negative_pct' => $previousNegativePct,
            'delta' => $negativeDelta,
        ];

        $responseActions = [];
        if ($neg_pct >= 15 || !empty($riskTriggers)) {
            $responseActions[] = ['level' => 'Segera jawab', 'text' => 'Siapkan klarifikasi singkat untuk isu berisiko tinggi dan arahkan ke data resmi.'];
        }
        if ($pos_pct >= 50) {
            $responseActions[] = ['level' => 'Perkuat', 'text' => 'Angkat kembali narasi positif yang paling banyak mendapat respons baik.'];
        }
        if (!empty($negativeIssues)) {
            $responseActions[] = ['level' => 'Pantau ketat', 'text' => 'Pantau isu negatif teratas agar tidak melebar menjadi percakapan krisis.'];
        }
        $responseActions[] = ['level' => 'Jaga ritme', 'text' => 'Teruskan publikasi rutin dan cek perubahan sentimen harian.'];

        // Kondisi Viral (Viral Status)
        $recent7d = (clone $baseQuery)->where('union_res.published_at', '>=', now()->subDays(7))->count();
        if ($recent7d >= 100) {
            $viral_status = 'Sangat Viral';
            $viral_color = 'purple';
            $viral_desc = 'Lonjakan percakapan sangat tinggi';
        } elseif ($recent7d >= 30) {
            $viral_status = 'Mulai Viral';
            $viral_color = 'blue';
            $viral_desc = 'Ada peningkatan atensi';
        } else {
            $viral_status = 'Normal';
            $viral_color = 'slate';
            $viral_desc = 'Volume berita stabil';
        }

            return [
            'total' => $total,
            'positive_pct' => $pos_pct,
            'neutral_pct' => $neu_pct,
            'negative_pct' => $neg_pct,
            'reputation_score' => $reputation_score,
            'crisis_signal' => $crisis_signal,
            'crisis_color' => $crisis_color,
            'summary' => $summary,
            'recommendations' => $recs,
            'categories' => $categories,
            'sources' => $sources,
            'negative_issues' => $negativeIssues,
            'risk_triggers' => $riskTriggers,
            'sentiment_shift' => $sentimentShift,
            'response_actions' => array_slice($responseActions, 0, 4),
            'viral_status' => $viral_status ?? 'Normal',
            'viral_color' => $viral_color ?? 'slate',
            'viral_desc' => $viral_desc ?? 'Volume berita stabil',
            ];
        });
    }

    public function getViralMeta(): array
    {
        $cacheKey = 'media_dashboard_viral_meta:' . $this->getDecodedProjectId() . ':' . $this->dashboardCacheSignature();

        return Cache::remember($cacheKey, 120, function () {
            $unionQuery = $this->projectArticlesQuery();
            $baseQuery = \Illuminate\Support\Facades\DB::table(\Illuminate\Support\Facades\DB::raw("(" . $unionQuery->toSql() . ") as union_res"))
                ->setBindings($unionQuery->getBindings());

            $baseQuery = $this->applyActiveFilters($baseQuery);
            $recent7d = (clone $baseQuery)->where('union_res.published_at', '>=', now()->subDays(7))->count();

            if ($recent7d >= 100) {
                return [
                    'viral_status' => 'Sangat Viral',
                    'viral_color' => 'purple',
                    'viral_desc' => 'Lonjakan percakapan sangat tinggi',
                ];
            }

            if ($recent7d >= 30) {
                return [
                    'viral_status' => 'Mulai Viral',
                    'viral_color' => 'blue',
                    'viral_desc' => 'Ada peningkatan atensi',
                ];
            }

            return [
                'viral_status' => 'Normal',
                'viral_color' => 'slate',
                'viral_desc' => 'Volume berita stabil',
            ];
        });
    }

    public function render()
    {
        return view('livewire.media-dashboard');
    }
}
