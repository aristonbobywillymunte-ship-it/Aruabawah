<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use App\Models\Article;
use App\Models\AiAnalysisResult;
use App\Models\NewsSource;
use App\Models\Project;
use App\Services\NewsSourceIconResolver;
use Livewire\WithPagination;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

new class extends Component
{
    use WithPagination;

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

    // Tab state ('penyebutan' or 'analisis')
    #[Url(as: 'tab')]
    public $activeTab = 'cGVueWVidXRhbg==';

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
        $this->activeTab = base64_encode($name);
        $this->js('window.scrollTo(0, 0);');
    }

    public $search = '';
    public $selectedSentiment = ['positive', 'neutral', 'negative'];
    public $selectedSources = ['Instagram', 'Tiktok', 'Facebook', 'News'];
    public $selectedCategory = '';
    public $sortBy = 'newest';
    public int $limit = 5;

    public function loadMore()
    {
        $this->limit += 5;
    }

    // Interactive datepicker states
    public $startDate;
    public $endDate;



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

        $decodedId = $this->getDecodedProjectId();
        return \App\Models\Article::withCompleteOfficialAiResult()
            ->with(['aiAnalysisResult'])
            ->whereHas('projects', function($q) use ($decodedId) {
                $q->where('projects.id', $decodedId);
            });
    }

    public function mount($projectId = null)
    {
        $project = $this->resolveProjectOrFail($projectId);
        $this->projectId = base64_encode($project->id);
        $this->projectName = $project->name;

        // Atur agar default terfilter berdasarkan bulan berjalan (tanggal 1 hingga hari ini)
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');

        // Parse tab if present in query parameter
        $tabFromUrl = request()->query('tab');
        if ($tabFromUrl) {
            $decoded = base64_decode($tabFromUrl, true);
            if ($decoded !== false && preg_match('/^[a-z]+$/', $decoded)) {
                if ($decoded === 'wawasav') {
                    $tabFromUrl = base64_encode('wawasan');
                }
                $this->activeTab = $tabFromUrl;
            } else {
                $this->activeTab = base64_encode($tabFromUrl);
            }
        }

        // Initialize keywords list based on active project name and topics
        $this->primaryKeywords = $project->topics ?? [$this->projectName];
        $this->supportKeywords = [];
        $this->excludeKeywords = [];

        $this->rebuildKeywordsTable();
    }

    /**
     * Rebuild the keywords table with count respecting current date filter.
     * Called on mount AND whenever startDate/endDate changes.
     */
    public function rebuildKeywordsTable(): void
    {
        $projectIdDecoded = $this->getDecodedProjectId();
        $cacheKey = "project_keywords_{$projectIdDecoded}_" . 
            md5($this->startDate . '_' . $this->endDate . '_' . implode(',', $this->primaryKeywords));

        $this->keywordsTable = Cache::remember($cacheKey, 120, function () {
            $keywordsTable = [];
            $now = now();
            foreach ($this->primaryKeywords as $kw) {
                // Base: project articles mentioning the keyword, with date filter applied
                $baseKwQuery = $this->applyActiveFilters(clone $this->projectArticlesQuery())
                    ->where(function($q) use ($kw) {
                        $q->where('title', 'like', '%' . $kw . '%')
                          ->orWhere('content', 'like', '%' . $kw . '%');
                    });
                $totalCount = (clone $baseKwQuery)->count();

                // Trend: compare last 30 days vs prior 30 days (always relative to now, not the date filter)
                $allKwQuery = (clone $this->projectArticlesQuery())->where(function($q) use ($kw) {
                    $q->where('title', 'like', '%' . $kw . '%')
                      ->orWhere('content', 'like', '%' . $kw . '%');
                });
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
        $this->resetPage();
    }

    public function generateAiInsights()
    {
        $project = $this->resolveProjectOrFail($this->projectId);
        \App\Jobs\GenerateProjectAiInsightJob::dispatchSync($project->id);
        $project->refresh();
        session()->flash('message', 'Wawasan AI berhasil diperbarui!');
    }

    public function updatedStartDate()
    {
        $this->resetPage();
        $this->rebuildKeywordsTable();
    }

    public function updatedEndDate()
    {
        $this->resetPage();
        $this->rebuildKeywordsTable();
    }

    public function updatedSelectedSentiment()
    {
        $this->resetPage();
    }

    public function updatedSelectedSources()
    {
        $this->resetPage();
    }

    public function updatedSelectedCategory()
    {
        $this->resetPage();
    }

    public function updatedStartDay()
    {
        $this->resetPage();
    }

    public function updatedEndDay()
    {
        $this->resetPage();
    }

    public function setSort($sort)
    {
        $this->sortBy = $sort;
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

            return $resolved ?: $this->defaultPortalLogoUrl($domain);
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

    protected function defaultPortalLogoUrl(string $domain): string
    {
        return "https://www.google.com/s2/favicons?sz=64&domain=" . urlencode($domain) . "&default=404";
    }

    public function getProjectArticleCount(): int
    {
        return (int) $this->projectArticlesQuery()->count();
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
        $srcLower = strtolower($article->source_name);
        $isSocial = str_contains($srcLower, 'facebook') || str_contains($srcLower, 'fb') || 
                    str_contains($srcLower, 'instagram') || $srcLower === 'ig' || 
                    str_contains($srcLower, 'tiktok') || $srcLower === 'tk' || 
                    str_contains($srcLower, 'twitter') || $srcLower === 'x.com';
        
        $likes = 0;
        $comments = 0;
        
        if ($isSocial) {
            if ($this->socialMediaItemsCache === null) {
                // Pre-fetch all social media items for this project's articles in one query to avoid N+1 query
                $urls = $this->projectArticlesQuery()->pluck('canonical_url')->merge(
                    $this->projectArticlesQuery()->pluck('url')
                )->filter()->unique()->toArray();
                
                if (!empty($urls)) {
                    $this->socialMediaItemsCache = \App\Models\SocialMediaItem::whereIn('post_url', $urls)
                        ->get()
                        ->keyBy('post_url');
                } else {
                    $this->socialMediaItemsCache = collect();
                }
            }
            
            $item = $this->socialMediaItemsCache->get($article->canonical_url) 
                ?? $this->socialMediaItemsCache->get($article->url);
                
            if ($item) {
                $likes = $item->like_count ?? 0;
                $comments = $item->comment_count ?? 0;
            }
        }
        
        return [$likes, $comments];
    }

    public function getViralArticles()
    {
        return $this->applyActiveFilters($this->projectArticlesQuery())
            ->where('published_at', '>=', now()->subDays(7))
            ->orderByDesc('published_at')
            ->limit(30)
            ->get();
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
        }

        return trim($title) !== '' ? trim($title) : 'Penyebutan sosial';
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

        $this->resolveProjectOrFail($this->projectId)
            ->articles()
            ->syncWithoutDetaching([$article->id]);

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
        
        $newKw = trim($this->newKeywordText);
        $totalCount = clone $this->projectArticlesQuery()->where(function($q) use ($newKw) {
            $q->where('title', 'like', '%' . $newKw . '%')
              ->orWhere('content', 'like', '%' . $newKw . '%');
        })->count();
        
        $this->keywordsTable[] = [
            'keyword' => '# ' . strtoupper($newKw),
            'total'   => $totalCount,
            'trend'   => 'Stabil'
        ];

        if ($this->newKeywordType == 'primary') {
            $this->primaryKeywords[] = trim($this->newKeywordText);
        } elseif ($this->newKeywordType == 'support') {
            $this->supportKeywords[] = trim($this->newKeywordText);
        } else {
            $this->excludeKeywords[] = trim($this->newKeywordText);
        }
        
        $this->newKeywordText = '';
        $this->showAddKeywordModal = false;
        session()->flash('message', 'Kata kunci berhasil ditambahkan.');
    }

    public function removeKeywordTable($index)
    {
        abort_unless($this->isAdmin(), 403, 'Hanya admin yang dapat menghapus kata kunci.');

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

            unset($this->keywordsTable[$index]);
            $this->keywordsTable = array_values($this->keywordsTable);
        }
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
        session()->flash('message', 'Kata kunci berhasil dihapus.');
    }

    public function applyActiveFilters($query, $exclude = [])
    {
        if ($this->search) {
            $query->where(function($q) {
                $q->where('title', 'like', '%' . $this->search . '%')
                  ->orWhere('content', 'like', '%' . $this->search . '%')
                  ->orWhere('source_name', 'like', '%' . $this->search . '%');
            });
        }

        if (!in_array('sentiment', $exclude) && !empty($this->selectedSentiment)) {
            $query->whereHas('aiAnalysisResult', function($q) {
                $q->whereIn('sentiment', $this->selectedSentiment);
            });
        }

        if (!in_array('sources', $exclude) && !empty($this->selectedSources)) {
            $query->where(function($q) {
                $socials = ['Twitter', 'Twitter/X', 'x.com', 'Instagram', 'Youtube', 'Tiktok', 'Facebook', 'Threads'];
                if (in_array('News', $this->selectedSources)) {
                    $selectedSocials = array_diff($this->selectedSources, ['News']);
                    $q->whereNotIn('source_name', $socials);
                    if (!empty($selectedSocials)) {
                        $q->orWhereIn('source_name', $selectedSocials);
                    }
                } else {
                    $q->whereIn('source_name', $this->selectedSources);
                }
            });
        }

        if ($this->startDate) {
            $start = \Carbon\Carbon::parse($this->startDate)->startOfDay();
            if ($this->endDate) {
                $end = \Carbon\Carbon::parse($this->endDate)->endOfDay();
                $query->whereBetween('published_at', [$start, $end]);
            } else {
                $end = \Carbon\Carbon::parse($this->startDate)->endOfDay();
                $query->whereBetween('published_at', [$start, $end]);
            }
        }

        return $query;
    }

    public function getCounts()
    {
        $baseQuery = $this->projectArticlesQuery();
        $socials = ['Twitter', 'Twitter/X', 'x.com', 'Instagram', 'Youtube', 'Tiktok', 'Facebook', 'Threads'];

        $sourceQuery = $this->applyActiveFilters(clone $baseQuery, ['sources']);
        $sources = ['Twitter', 'Instagram', 'Youtube', 'Tiktok', 'Facebook', 'Threads'];
        $sourceCounts = [];
        foreach ($sources as $source) {
            $sourceCounts[$source] = (clone $sourceQuery)->where('source_name', $source)->count();
        }
        $sourceCounts['News'] = (clone $sourceQuery)->whereNotIn('source_name', $socials)->count();

        $sentimentQuery = $this->applyActiveFilters(clone $baseQuery, ['sentiment']);
        $sentimentQueryWithAI = (clone $sentimentQuery)->join('ai_analysis_results as ai', 'articles.id', '=', 'ai.article_id')
            ->where('ai.analysis_status', 'success')
            ->whereNotNull('ai.summary')
            ->whereNotNull('ai.sentiment')
            ->whereNotNull('ai.risk_level');
            
        $sentimentCounts = [
            'positive' => (clone $sentimentQueryWithAI)->where('ai.sentiment', 'positive')->count(),
            'neutral'  => (clone $sentimentQueryWithAI)->where('ai.sentiment', 'neutral')->count(),
            'negative' => (clone $sentimentQueryWithAI)->where('ai.sentiment', 'negative')->count(),
        ];

        $riskCounts = [
            'low' => (clone $sentimentQueryWithAI)->where('ai.risk_level', 'low')->count(),
            'medium' => (clone $sentimentQueryWithAI)->where('ai.risk_level', 'medium')->count(),
            'high' => (clone $sentimentQueryWithAI)->where('ai.risk_level', 'high')->count(),
            'critical' => (clone $sentimentQueryWithAI)->where('ai.risk_level', 'critical')->count(),
        ];

        return [
            'sources'    => $sourceCounts,
            'sentiments' => $sentimentCounts,
            'risks'      => $riskCounts,
        ];
    }

    public function getArticles()
    {
        // Selalu scoped ke projectId yang sedang aktif
        $query = $this->projectArticlesQuery()->with('aiAnalysisResult');
        $query = $this->applyActiveFilters($query);

        if ($this->sortBy == 'popular') {
            $reachSubquery = AiAnalysisResult::selectRaw('COALESCE(project_estimated_readers, 0)')
                ->whereColumn('article_id', 'articles.id')
                ->where('analysis_status', 'success')
                ->where('reach_method', 'ai_reader_estimate_v1')
                ->limit(1);

            $query->orderByRaw(
                '(
                    COALESCE((
                        SELECT COALESCE(project_estimated_readers, 0)
                        FROM ai_analysis_results
                        WHERE ai_analysis_results.article_id = articles.id
                          AND analysis_status = \'success\'
                          AND reach_method = \'ai_reader_estimate_v1\'
                        LIMIT 1
                    ), 0) * 100
                ) DESC'
            )->orderByDesc($reachSubquery)->orderBy('published_at', 'desc');
        } else {
            $query->orderBy('published_at', 'desc');
        }

        $fetchLimit = max($this->limit * 4, $this->limit);

        return $this->dedupeSocialArticles($query->limit($fetchLimit)->get())
            ->take($this->limit)
            ->values();
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

    public function getTrendPoints(string $mode, string $metric = 'penyebutan', ?int $forceMax = null): array
    {
        $projectIdDecoded = $this->getDecodedProjectId();
        $cacheKey = "project_trend_{$projectIdDecoded}_{$mode}_{$metric}_{$forceMax}_" . 
            md5($this->startDate . '_' . $this->endDate . '_' . $this->selectedKeyword);

        return Cache::remember($cacheKey, 120, function () use ($mode, $metric, $forceMax) {
            $baseQuery = $this->projectArticlesQuery();
            
            if (!empty($this->selectedKeyword)) {
                $baseQuery->where(function($q) {
                    $q->where('title', 'like', '%' . $this->selectedKeyword . '%')
                      ->orWhere('content', 'like', '%' . $this->selectedKeyword . '%');
                });
            }
            
            $start_date = $this->startDate;
            if (!$start_date) {
                $oldest = (clone $baseQuery)->orderBy('published_at', 'asc')->value('published_at');
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
                $periodQuery = (clone $baseQuery)->whereBetween('published_at', [
                    $dateStart->format('Y-m-d H:i:s'),
                    $dateEnd->format('Y-m-d H:i:s')
                ]);
                if ($metric === 'jangkauan') {
                    $articleIds = (clone $periodQuery)->select('articles.id')->pluck('id');
                    if ($articleIds->isEmpty()) return 0;
                    return (int) \App\Models\AiAnalysisResult::whereIn('article_id', $articleIds)
                        ->where('analysis_status', 'success')
                        ->whereNotNull('summary')->whereNotNull('sentiment')->whereNotNull('risk_level')
                        ->where('reach_method', 'ai_reader_estimate_v1')
                        ->whereNotNull('project_estimated_readers')
                        ->sum('project_estimated_readers');
                } elseif ($metric === 'sentimen_positif') {
                    $articleIds = (clone $periodQuery)->select('articles.id')->pluck('id');
                    if ($articleIds->isEmpty()) return 0;
                    return (int) \App\Models\AiAnalysisResult::whereIn('article_id', $articleIds)
                        ->where('analysis_status', 'success')
                        ->where('sentiment', 'positive')
                        ->count();
                } elseif ($metric === 'sentimen_netral') {
                    $articleIds = (clone $periodQuery)->select('articles.id')->pluck('id');
                    if ($articleIds->isEmpty()) return 0;
                    return (int) \App\Models\AiAnalysisResult::whereIn('article_id', $articleIds)
                        ->where('analysis_status', 'success')
                        ->where('sentiment', 'neutral')
                        ->count();
                } elseif ($metric === 'sentimen_negatif') {
                    $articleIds = (clone $periodQuery)->select('articles.id')->pluck('id');
                    if ($articleIds->isEmpty()) return 0;
                    return (int) \App\Models\AiAnalysisResult::whereIn('article_id', $articleIds)
                        ->where('analysis_status', 'success')
                        ->where('sentiment', 'negative')
                        ->count();
                } elseif ($metric === 'sentimen') {
                    $articleIds = (clone $periodQuery)->select('articles.id')->pluck('id');
                    if ($articleIds->isEmpty()) return 0;
                    $pos = \App\Models\AiAnalysisResult::whereIn('article_id', $articleIds)
                        ->where('analysis_status', 'success')->whereNotNull('sentiment')
                        ->where('sentiment', 'positive')->count();
                    $neg = \App\Models\AiAnalysisResult::whereIn('article_id', $articleIds)
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
    }

    public function getWawasan()
    {
        $project = $this->resolveProjectOrFail($this->projectId);
        $baseQuery = $this->applyActiveFilters(clone $this->projectArticlesQuery());
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

        $baseQueryWithAI = (clone $baseQuery)->join('ai_analysis_results as ai', 'articles.id', '=', 'ai.article_id')
            ->where('ai.analysis_status', 'success')
            ->whereNotNull('ai.summary')
            ->whereNotNull('ai.sentiment')
            ->whereNotNull('ai.risk_level');
        $total = (clone $baseQuery)->count();

        $sentimentCounts = (clone $baseQueryWithAI)
            ->selectRaw("
                SUM(CASE WHEN ai.sentiment = 'positive' THEN 1 ELSE 0 END) as positive,
                SUM(CASE WHEN ai.sentiment = 'neutral' THEN 1 ELSE 0 END) as neutral,
                SUM(CASE WHEN ai.sentiment = 'negative' THEN 1 ELSE 0 END) as negative
            ")
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
        $categories = (clone $baseQuery)->select('category', \DB::raw('count(*) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->toArray();

        // Top sources
        $sources = (clone $baseQuery)->select('source_name', \DB::raw('count(*) as total'))
            ->groupBy('source_name')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->toArray();

        $negativeIssues = (clone $baseQueryWithAI)
            ->where('ai.sentiment', 'negative')
            ->selectRaw('COALESCE(ai.main_issue, articles.category, articles.title) as issue, COUNT(*) as total')
            ->groupBy('issue')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'issue' => Str::limit((string) $row->issue, 90),
                'total' => (int) $row->total,
                'pct' => $neg > 0 ? round(((int) $row->total / $neg) * 100) : 0,
            ])
            ->toArray();

        $riskTriggers = (clone $baseQueryWithAI)
            ->whereIn('ai.risk_level', ['high', 'critical'])
            ->orderByRaw("CASE ai.risk_level WHEN 'critical' THEN 2 WHEN 'high' THEN 1 ELSE 0 END DESC")
            ->orderByDesc('ai.project_estimated_readers')
            ->orderByDesc('articles.published_at')
            ->limit(5)
            ->get([
                'articles.id',
                'articles.title',
                'articles.source_name',
                'articles.url',
                'articles.published_at',
                'ai.risk_level',
                'ai.risk_reason',
                'ai.sentiment',
                'ai.project_estimated_readers',
            ])
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

        $currentHalf = (clone $baseQueryWithAI)->whereBetween('articles.published_at', [$midpoint, $rangeEnd]);
        $previousHalf = (clone $baseQueryWithAI)->whereBetween('articles.published_at', [$rangeStart, $midpoint->copy()->subSecond()]);
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
        $recent7d = (clone $baseQuery)->where('published_at', '>=', now()->subDays(7))->count();
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
    }
};
?>

@php
    $w = $this->getWawasan();
@endphp

<div>
<div class="min-h-screen bg-[#f7f9ff] text-slate-800 flex flex-col font-sans"
     x-data="{
         detailModalOpen: false,
         showViralModal: false,
         openedFromViral: false,
         showAiSummaryModal: false,
         scrolledDown: false,
         detailTitle: '',
         detailSource: '',
         detailDate: '',
         detailUrl: '',
         detailContent: '',
         detailAiSummary: '',
         detailAiRecommendation: '',
         detailSentiment: '',
         detailCategory: '',
         detailReach: '',
         detailLevel: '',
         detailScore: '',
         detailFormattedDate: '',
         detailLikes: 0,
         detailComments: 0,
         openDetail(title, source, date, url, content, summary, rec, sentiment, category, reach, level, score, formattedDate, likes = 0, comments = 0) {
             this.detailTitle = title;
             this.detailSource = source;
             this.detailDate = date;
             this.detailUrl = url;
             this.detailContent = content;
             this.detailAiSummary = summary;
             this.detailAiRecommendation = rec;
             this.detailSentiment = sentiment;
             this.detailCategory = category;
             this.detailReach = reach;
             this.detailLevel = level;
             this.detailScore = score;
             this.detailFormattedDate = formattedDate;
             this.detailLikes = likes;
             this.detailComments = comments;
             this.showAiSummaryModal = false;
             this.detailModalOpen = true;
         },
         scrollToTop() {
             window.scrollTo({ top: 0, behavior: 'smooth' });
         }
     }"
     x-effect="document.body.style.overflow = (detailModalOpen || showViralModal) ? 'hidden' : ''"
     x-init="window.addEventListener('scroll', () => { scrolledDown = window.scrollY > 700 }, { passive: true })"
>
    
    <!-- Top Header -->
    <header class="w-full bg-white border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-[1400px] mx-auto px-6 h-20 flex flex-row flex-nowrap items-center justify-between gap-6">
            <!-- Brand -->
            <div class="flex items-center gap-6 h-full justify-self-start">
                <!-- Brand Logo Arusbawah -->
                <div class="flex items-center gap-2 font-sans cursor-pointer" onclick="window.location.href='/'">
                    @if($customLogo = \App\Helpers\AppBrandingHelper::getAppLogoPath())
                        <img src="{{ asset('storage/' . $customLogo) }}" class="h-8 max-w-[120px] object-contain transition-transform hover:scale-105 duration-300">
                    @else
                        <svg width="28" height="28" viewBox="0 0 42 42" fill="none" xmlns="http://www.w3.org/2000/svg" class="transition-transform hover:scale-105 duration-300">
                            <polygon points="21,4 39,38 3,38" fill="none" stroke="#c0392b" stroke-width="4" stroke-linejoin="round"/>
                            <line x1="11" y1="28" x2="31" y2="28" stroke="#c0392b" stroke-width="4" stroke-linecap="round"/>
                        </svg>
                    @endif
                    <div class="flex flex-col text-left">
                        <span class="text-sm font-black tracking-wider leading-none text-slate-800 uppercase">{{ \App\Helpers\AppBrandingHelper::getAppName() }}</span>
                        <span class="text-[7.5px] font-bold text-slate-400 uppercase tracking-widest leading-none mt-0.5">Media Intelligence</span>
                    </div>
                </div>

            </div>

            <!-- Navigation Links -->
            <nav class="hidden md:flex items-center justify-center gap-6 h-full justify-self-center">
                    <button 
                        type="button"
                        wire:click="setTab('penyebutan')"
                        class="font-bold text-sm px-1 py-5 h-full flex items-center transition-all cursor-pointer border-b-2 {{ $this->isTab('penyebutan') ? 'text-[#1fa387] border-[#1fa387]' : 'text-slate-500 border-transparent hover:text-slate-800' }}"
                    >
                        Penyebutan
                    </button>
                    <button 
                        type="button"
                        wire:click="setTab('analisis')"
                        class="font-bold text-sm px-1 py-5 h-full flex items-center transition-all cursor-pointer border-b-2 {{ $this->isTab('analisis') ? 'text-[#1fa387] border-[#1fa387]' : 'text-slate-500 border-transparent hover:text-slate-800' }}"
                    >
                        Analisis
                    </button>
                    <button 
                        type="button"
                        wire:click="setTab('katakunci')"
                        class="font-bold text-sm px-1 py-5 h-full flex items-center transition-all cursor-pointer border-b-2 {{ $this->isTab('katakunci') ? 'text-[#1fa387] border-[#1fa387]' : 'text-slate-500 border-transparent hover:text-slate-800' }}"
                    >
                        Kata Kunci
                    </button>
                    <button 
                        type="button"
                        wire:click="setTab('wawasan')"
                        class="font-bold text-sm px-1 py-5 h-full flex items-center transition-all cursor-pointer border-b-2 {{ $this->isTab('wawasan') ? 'text-[#1fa387] border-[#1fa387]' : 'text-slate-500 border-transparent hover:text-slate-800' }}"
                    >
                        Wawasan
                    </button>
                    <button 
                        type="button"
                        wire:click="setTab('konten')"
                        class="font-bold text-sm px-1 py-5 h-full flex items-center transition-all cursor-pointer border-b-2 {{ $this->isTab('konten') ? 'text-[#1fa387] border-[#1fa387]' : 'text-slate-500 border-transparent hover:text-slate-800' }}"
                    >
                        Konten
                    </button>
                    <button 
                        type="button"
                        wire:click="setTab('sumber')"
                        class="font-bold text-sm px-1 py-5 h-full flex items-center transition-all cursor-pointer border-b-2 {{ $this->isTab('sumber') ? 'text-[#1fa387] border-[#1fa387]' : 'text-slate-500 border-transparent hover:text-slate-800' }}"
                    >
                        Sumber
                    </button>
                    <button 
                        type="button"
                        wire:click="setTab('laporan')"
                        class="font-bold text-sm px-1 py-5 h-full flex items-center transition-all cursor-pointer border-b-2 {{ $this->isTab('laporan') ? 'text-[#1fa387] border-[#1fa387]' : 'text-slate-500 border-transparent hover:text-slate-800' }}"
                    >
                        Laporan
                    </button>
            </nav>

            <!-- User Profile & Add Notification -->
            <div class="flex shrink-0 items-center justify-self-end gap-4">
                <div class="flex items-center gap-3">
                    <livewire:notification-dropdown />
                    <div class="relative" x-data="{ openProfile: false }">
                        <button
                            type="button"
                            @click="openProfile = !openProfile"
                            class="flex items-center gap-3 bg-slate-50 border border-slate-200 rounded-full pl-1 pr-3 py-1 cursor-pointer hover:bg-slate-100 transition-colors active:scale-95"
                        >
                            <div class="w-7 h-7 rounded-full bg-slate-200 flex items-center justify-center">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-medium text-slate-600">{{ auth()->user()?->email ?? 'Guest' }}</span>
                            <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M19 9l-7 7-7-7" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                            </svg>
                        </button>

                        <div 
                            x-show="openProfile" 
                            @click.away="openProfile = false"
                            style="display: none;"
                            class="absolute right-0 mt-2 w-56 bg-white rounded-xl border border-slate-100 shadow-lg z-[60] py-2"
                        >
                            <a wire:navigate class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition-colors" href="{{ route('password.change') }}">
                                <span class="material-symbols-outlined text-slate-400 text-lg">lock</span>
                                <span>Ganti Password</span>
                            </a>
                            <div class="my-1 border-t border-slate-100"></div>
                            <form method="POST" action="{{ url('/logout') }}">
                                @csrf
                                <button type="submit" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-red-500 hover:bg-red-50 transition-colors text-left">
                                    <span class="material-symbols-outlined text-lg">logout</span>
                                    <span>Logout</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Sub-header -->
    <div class="w-full bg-[#f0f3f8] border-b border-slate-200 py-2.5">
        <div class="max-w-[1400px] mx-auto px-6 flex items-center justify-between text-xs text-slate-500 font-medium">
            <div class="flex items-center gap-2">
                <span>Filter Aktif:</span>
                <span class="px-2 py-0.5 bg-white border border-slate-200 rounded text-[#1fa387] font-bold">
                    {{ $startDate ? \Carbon\Carbon::parse($startDate)->format('d/m/Y') . ($endDate && $endDate !== $startDate ? ' - ' . \Carbon\Carbon::parse($endDate)->format('d/m/Y') : '') : 'Semua Waktu' }}
                </span>
            </div>
            <div class="text-right">
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <span class="text-slate-500 text-sm font-medium">Proyek:</span> 
                    <span class="text-[#1fa387] uppercase tracking-wide">{{ $projectName }}</span>
                </h1>
                <p class="mt-1 text-[10px] font-semibold text-slate-500">
                    Total berita: <span class="text-slate-800">{{ number_format($this->getProjectArticleCount(), 0, ',', '.') }}</span>
                </p>
            </div>
        </div>
    </div>



    <!-- Main Workspace Layout with Real Full-Height Left Sidebar -->
    <div class="w-full flex-grow flex">
        
        <!-- Left Sidebar -->
        <aside class="w-16 bg-white border-r border-slate-200 flex flex-col items-center py-6 gap-5 flex-shrink-0 h-[calc(100vh-4rem)] sticky top-16">
            <!-- Kembali ke Daftar Proyek (Home) -->
            <a 
                href="/" 
                class="w-8 h-8 rounded-full border border-slate-200 text-slate-500 hover:text-slate-800 hover:bg-slate-50 flex items-center justify-center transition-all"
                title="Kembali ke Daftar Proyek"
            >
                <span class="material-symbols-outlined text-lg">arrow_back</span>
            </a>
        </aside>

        @php
            $counts = $this->getCounts();
            $socials = ['Twitter', 'Twitter/X', 'x.com', 'Instagram', 'Youtube', 'Tiktok', 'Facebook', 'Threads'];
            
            // Calculate actual database numbers
            $totalMentions = array_sum($counts['sentiments']);
            $posCount = $counts['sentiments']['positive'] ?? 0;
            $neuCount = $counts['sentiments']['neutral'] ?? 0;
            $negCount = $counts['sentiments']['negative'] ?? 0;

            $socialCount = 0;
            foreach ($counts['sources'] as $src => $c) {
                if (in_array($src, $socials)) {
                    $socialCount += $c;
                }
            }
            $fbCount = $counts['sources']['Facebook'] ?? 0;
            $igCount = $counts['sources']['Instagram'] ?? 0;
            $ttCount = $counts['sources']['Tiktok'] ?? 0;
            $newsCount = $counts['sources']['News'] ?? 0;
            
            $baseActiveQuery = $this->applyActiveFilters(clone $this->projectArticlesQuery());

            $platformStats = \App\Models\SocialMediaItem::query()
                ->join('articles', 'articles.canonical_url', '=', 'social_media_items.post_url')
                ->whereIn('articles.id', (clone $baseActiveQuery)->select('articles.id'))
                ->selectRaw('social_media_items.platform, SUM(social_media_items.like_count) as likes, SUM(social_media_items.comment_count) as comments')
                ->groupBy('social_media_items.platform')
                ->get()
                ->keyBy(fn($item) => strtolower($item->platform));

            $fbStats = $platformStats->get('facebook');
            $igStats = $platformStats->get('instagram');
            $ttStats = $platformStats->get('tiktok');

            $fbLikes = (int) ($fbStats->likes ?? 0);
            $fbComments = (int) ($fbStats->comments ?? 0);
            $igLikes = (int) ($igStats->likes ?? 0);
            $igComments = (int) ($igStats->comments ?? 0);
            $ttLikes = (int) ($ttStats->likes ?? 0);
            $ttComments = (int) ($ttStats->comments ?? 0);

            // Get REAL reach estimates from AI analysis results
            $socials = ['Twitter', 'Instagram', 'Youtube', 'Tiktok', 'Facebook', 'Threads'];
            $aiReachSum = function ($builder) {
                return (int) $builder
                    ->where('analysis_status', 'success')
                    ->whereNotNull('summary')
                    ->whereNotNull('sentiment')
                    ->whereNotNull('risk_level')
                    ->where('reach_method', 'ai_reader_estimate_v1')
                    ->whereNotNull('project_estimated_readers')
                    ->sum('project_estimated_readers');
            };

            $socialReach = $aiReachSum(
                \App\Models\AiAnalysisResult::query()
                    ->whereIn('article_id', (clone $baseActiveQuery)->whereIn('source_name', $socials)->select('articles.id'))
            );

            $fbReach = $aiReachSum(
                \App\Models\AiAnalysisResult::query()
                    ->whereIn('article_id', (clone $baseActiveQuery)->where('source_name', 'Facebook')->select('articles.id'))
            );

            $igReach = $aiReachSum(
                \App\Models\AiAnalysisResult::query()
                    ->whereIn('article_id', (clone $baseActiveQuery)->where('source_name', 'Instagram')->select('articles.id'))
            );

            $ttReach = $aiReachSum(
                \App\Models\AiAnalysisResult::query()
                    ->whereIn('article_id', (clone $baseActiveQuery)->where('source_name', 'Tiktok')->select('articles.id'))
            );

            $newsReach = $aiReachSum(
                \App\Models\AiAnalysisResult::query()
                    ->whereIn('article_id', (clone $baseActiveQuery)->whereNotIn('source_name', $socials)->select('articles.id'))
            );

            $totalReach = $socialReach + $newsReach;

            $interactionCount = $socialCount * 1.5;
            $prValue = $totalReach * 24.5; // IDR

            $canonicalAiFilter = function($q) {
                $q->where('analysis_status', 'success')
                  ->whereNotNull('summary')
                  ->whereNotNull('sentiment')
                  ->whereNotNull('risk_level');
            };

            // Social sentiments
            $socPos = (int) \App\Models\AiAnalysisResult::query()
                ->whereIn('article_id', (clone $baseActiveQuery)->whereIn('source_name', $socials)->select('articles.id'))
                ->where('sentiment', 'positive')
                ->where($canonicalAiFilter)
                ->count();
            $socNeu = (int) \App\Models\AiAnalysisResult::query()
                ->whereIn('article_id', (clone $baseActiveQuery)->whereIn('source_name', $socials)->select('articles.id'))
                ->where('sentiment', 'neutral')
                ->where($canonicalAiFilter)
                ->count();
            $socNeg = (int) \App\Models\AiAnalysisResult::query()
                ->whereIn('article_id', (clone $baseActiveQuery)->whereIn('source_name', $socials)->select('articles.id'))
                ->where('sentiment', 'negative')
                ->where($canonicalAiFilter)
                ->count();

            // News sentiments
            $newsPos = (int) \App\Models\AiAnalysisResult::query()
                ->whereIn('article_id', (clone $baseActiveQuery)->whereNotIn('source_name', $socials)->select('articles.id'))
                ->where('sentiment', 'positive')
                ->where($canonicalAiFilter)
                ->count();
            $newsNeu = (int) \App\Models\AiAnalysisResult::query()
                ->whereIn('article_id', (clone $baseActiveQuery)->whereNotIn('source_name', $socials)->select('articles.id'))
                ->where('sentiment', 'neutral')
                ->where($canonicalAiFilter)
                ->count();
            $newsNeg = (int) \App\Models\AiAnalysisResult::query()
                ->whereIn('article_id', (clone $baseActiveQuery)->whereNotIn('source_name', $socials)->select('articles.id'))
                ->where('sentiment', 'negative')
                ->where($canonicalAiFilter)
                ->count();

            // Formatter helper
            $fmt = function($num, $suffix = '') {
                return number_format($num, 0, ',', '.') . $suffix;
            };

            // Dynamic Network Analysis calculation logic
            // 1. Use real project topics as keywords
            $networkProject = $this->resolveProjectOrFail($this->projectId);
            $keywords = $networkProject->topics ?? [$this->projectName];
            
            // Also add the top words from word frequency as extra topics if fewer than 3 project topics
            if (count($keywords) < 3) {
                $extraTitles = (clone $this->projectArticlesQuery())->limit(100)->pluck('title');
                $extraStopWords = ['dan', 'di', 'ke', 'dari', 'yang', 'untuk', 'dengan', 'ini', 'itu', 'pada', 'dalam', 'adalah', 'akan', 'juga', 'sudah', 'ada', 'bisa', 'atau', 'tidak', 'lebih', 'saat', 'oleh', 'para', 'telah', 'agar', 'atas', 'jika', 'karena', 'maka', 'namun', 'pun', 'serta', 'tentang', 'setelah', 'antara', 'hingga', 'tahun', 'baru', 'terkait', 'pihak', 'sebuah', 'satu', 'tersebut', 'pemerintah', 'gubernur', 'jalan', 'jadi', 'masa'];
                $extraFreq = [];
                foreach ($extraTitles as $t) {
                    $clean = strtolower(preg_replace('/[^a-zA-Z0-9\s]/u', ' ', html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8')));
                    $ws = array_filter(explode(' ', $clean), fn($w) => strlen($w) > 4 && !in_array($w, $extraStopWords) && !in_array(strtolower($w), array_map('strtolower', $keywords)));
                    foreach ($ws as $wordItem) { $extraFreq[$wordItem] = ($extraFreq[$wordItem] ?? 0) + 1; }
                }
                arsort($extraFreq);
                $topExtra = array_slice(array_keys($extraFreq), 0, 5 - count($keywords));
                $keywords = array_merge($keywords, $topExtra);
            }
            
            $dynamicTopics = [];
            foreach ($keywords as $kw) {
                $kwQuery = $this->projectArticlesQuery()
                    ->where(function($q) use ($kw) {
                        $q->where('title', 'like', '%' . $kw . '%')
                          ->orWhere('content', 'like', '%' . $kw . '%');
                    });
                $this->applyActiveFilters($kwQuery);
                $count = $kwQuery->count();
                if ($count > 0) {
                    // Use AI analysis results for more accurate sentiment
                    $artIds = (clone $kwQuery)->select('articles.id')->pluck('id');
                    $pos = \App\Models\AiAnalysisResult::whereIn('article_id', $artIds)->where('sentiment', 'positive')->count();
                    $neg = \App\Models\AiAnalysisResult::whereIn('article_id', $artIds)->where('sentiment', 'negative')->count();
                    $sent = 'Netral';
                    if ($pos > $neg) $sent = 'Positif';
                    elseif ($neg > $pos) $sent = 'Negatif';

                    $dynamicTopics[] = ['name' => $kw, 'count' => $count, 'sentiment' => $sent];
                }
            }
            usort($dynamicTopics, fn($a, $b) => $b['count'] <=> $a['count']);

            // 2. Dynamic Actors handles
            $actorsQuery = $this->projectArticlesQuery();
            $this->applyActiveFilters($actorsQuery);
            $dynamicActors = [];
            $uniqueSources = $actorsQuery->select('source_name')->distinct()->take(5)->pluck('source_name');
            foreach ($uniqueSources as $idx => $src) {
                $srcQuery = $this->projectArticlesQuery()->where('source_name', $src);
                $this->applyActiveFilters($srcQuery);
                $count = $srcQuery->count();
                
                $pos = (clone $srcQuery)->where('sentiment', 'positive')->count();
                $neg = (clone $srcQuery)->where('sentiment', 'negative')->count();
                $sent = 'Netral';
                if ($pos > $neg) $sent = 'Positif';
                elseif ($neg > $pos) $sent = 'Negatif';

                $dynamicActors[] = [
                    'handle' => '@' . strtolower(str_replace(' ', '', $src)) . '_user' . ($idx + 1),
                    'count' => $count,
                    'sentiment' => $sent
                ];
            }

            // 3. Dynamic Sentiments
            $totalActive = $totalMentions;
            $dynamicSentiments = [
                ['name' => 'Sentimen Positif', 'ratio' => $totalActive > 0 ? round(($posCount / $totalActive) * 100) : 0, 'sentiment' => 'Positif'],
                ['name' => 'Sentimen Netral', 'ratio' => $totalActive > 0 ? round(($neuCount / $totalActive) * 100) : 0, 'sentiment' => 'Netral'],
                ['name' => 'Sentimen Negatif', 'ratio' => $totalActive > 0 ? round(($negCount / $totalActive) * 100) : 0, 'sentiment' => 'Negatif'],
            ];
        @endphp

        <!-- Main Workspace (Center feed & Right Filter) -->
        <div class="flex-grow flex gap-6 px-8 py-6 items-start">
            
            @if($this->isTab('penyebutan'))
                <!-- TAB 1: Penyebutan (Mentions Feed View) -->
                <section class="flex-1 min-w-0 space-y-6">
                    <!-- Section Title & Sort Selector -->
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-bold text-slate-900 mb-0.5">Penyebutan</h2>
                            <p class="text-xs text-slate-500 flex items-center gap-1">
                                Pantau percakapan media untuk proyek 
                                <span class="font-bold text-[#1fa387]">{{ $projectName }}</span>
                            </p>
                        </div>
                        <div>
                            <!-- Sort Dropdown -->
                            <div class="relative" x-data="{ openSort: false }">
                                <button 
                                    @click="openSort = !openSort"
                                    class="bg-white border border-slate-200 rounded-full px-4 py-1.5 text-xs font-semibold text-slate-700 flex items-center gap-2 shadow-sm hover:bg-slate-50 transition"
                                >
                                    <span class="material-symbols-outlined text-sm text-slate-400">sort</span>
                                    <span>{{ $sortBy == 'newest' ? 'Yang Terbaru' : 'Paling Populer' }}</span>
                                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                </button>

                                <!-- Dropdown Box -->
                                <div 
                                    x-show="openSort" 
                                    @click.away="openSort = false" 
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="absolute right-0 mt-2 w-44 bg-white rounded-xl border border-slate-200 shadow-lg z-50 py-1.5 text-left"
                                    style="display: none;"
                                >
                                    <button 
                                        wire:click="setSort('newest')" 
                                        @click="openSort = false"
                                        class="w-full px-4 py-2.5 text-xs flex justify-between items-center hover:bg-slate-50 transition-colors {{ $sortBy == 'newest' ? 'text-[#1fa387] font-bold' : 'text-slate-700 font-medium' }}"
                                    >
                                        <span>Yang Terbaru</span>
                                        @if($sortBy == 'newest')
                                            <svg class="w-3.5 h-3.5 text-[#1fa387]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        @endif
                                    </button>
                                    <button 
                                        wire:click="setSort('popular')" 
                                        @click="openSort = false"
                                        class="w-full px-4 py-2.5 text-xs flex justify-between items-center hover:bg-slate-50 transition-colors {{ $sortBy == 'popular' ? 'text-[#1fa387] font-bold' : 'text-slate-700 font-medium' }}"
                                    >
                                        <span>Paling Populer</span>
                                        @if($sortBy == 'popular')
                                            <svg class="w-3.5 h-3.5 text-[#1fa387]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        @endif
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    @php 
                        $articlesList = $this->getArticles();
                    @endphp

                    <!-- Mentions Cards Feed -->
                    @if($articlesList->isEmpty())
                        <div class="bg-white border border-slate-200 rounded-2xl p-12 text-center space-y-4 shadow-sm">
                            <svg class="w-12 h-12 text-slate-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-sm font-semibold text-slate-600">Belum ada penyebutan media ditemukan untuk proyek ini.</p>
                        </div>
                    @else
                        <div class="space-y-4">
                                @foreach($articlesList as $article)
                                    @php
                                        $analysis = $article->aiAnalysisResult;
                                        $hasReadableAiReach = (bool) ($analysis && $analysis->hasCompleteOfficialAiResult());
                                    @endphp
                                @php
                                    $sentimentColor = '#64748b'; // Neutral default
                                    $sentimentBg = 'bg-slate-50 border-slate-200/80 text-slate-700';
                                    $sentimentLabel = $hasReadableAiReach ? 'Netral' : 'Belum dianalisis AI';
                                    $hoverGlowClass = 'hover:border-slate-300 hover:shadow-[0_20px_50px_rgba(99,102,241,0.06)]';
                                    if ($this->getValidAiResult($article)?->sentiment === 'positive') {
                                        $sentimentColor = '#10b981';
                                        $sentimentBg = 'bg-emerald-50/80 border-emerald-100/50 text-emerald-700';
                                        $sentimentLabel = 'Positif';
                                        $hoverGlowClass = 'hover:border-emerald-300 hover:shadow-[0_20px_50px_rgba(16,185,129,0.08)]';
                                    } elseif ($this->getValidAiResult($article)?->sentiment === 'negative') {
                                        $sentimentColor = '#ef4444';
                                        $sentimentBg = 'bg-rose-50/70 border-rose-100/50 text-rose-700';
                                        $sentimentLabel = 'Negatif';
                                        $hoverGlowClass = 'hover:border-rose-300 hover:shadow-[0_20px_50px_rgba(239,68,68,0.08)]';
                                    }
                                @endphp
                                <article 
                                    class="bg-white rounded-3xl border border-slate-200/80 p-6 shadow-[0_4px_24px_rgba(0,0,0,0.015)] flex flex-col justify-between transition-all duration-300 hover:shadow-[0_12px_32px_rgba(0,0,0,0.04)] hover:-translate-y-0.5 border-l-4"
                                    style="border-left-color: {{ $sentimentColor }}"
                                >
                                    @php
                                        $isFacebook = str_contains(strtolower($article->source_name), 'facebook');
                                        $projectReachDisplay = $this->getProjectReachDisplayData($article);
                                    @endphp
                                    <!-- Platform & Category Header Row -->
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="flex items-center gap-2.5">
                                            @php
                                                $srcLowerMain = strtolower($article->source_name);
                                                
                                                // Determine background color/gradient matching platform
                                                if (str_contains($srcLowerMain, 'instagram') || $srcLowerMain === 'ig') {
                                                    $logoBgMain = 'bg-gradient-to-br from-purple-600 via-pink-500 to-orange-400';
                                                } elseif (str_contains($srcLowerMain, 'tiktok') || $srcLowerMain === 'tk') {
                                                    $logoBgMain = 'bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800';
                                                } elseif (str_contains($srcLowerMain, 'facebook') || $srcLowerMain === 'fb') {
                                                    $logoBgMain = 'bg-gradient-to-br from-blue-600 to-blue-700';
                                                } else {
                                                    $logoBgMain = 'bg-transparent';
                                                }
                                            @endphp
                                            <div class="w-10 h-10 rounded-xl flex items-center justify-center overflow-hidden shadow-sm flex-shrink-0 {{ $logoBgMain }}">
                                                @if(str_contains($srcLowerMain, 'facebook') || $srcLowerMain === 'fb')
                                                    <svg class="w-5 h-5 fill-current text-white" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"></path></svg>
                                                @elseif(str_contains($srcLowerMain, 'instagram') || $srcLowerMain === 'ig')
                                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5" stroke-linecap="round"></line></svg>
                                                @elseif(str_contains($srcLowerMain, 'tiktok') || $srcLowerMain === 'tk')
                                                    <svg class="w-5 h-5 fill-current text-white" viewBox="0 0 24 24"><path d="M12.525.01c1.306-.022 2.615-.011 3.921-.012.08 1.836 1.011 3.5 2.501 4.485.006 1.341-.004 2.683-.004 4.024-1.57-.107-3.067-.932-3.955-2.247-.008 2.827-.003 5.657-.005 8.486-.098 3.546-3.13 6.643-6.726 6.467-3.526-.067-6.523-3.18-6.241-6.722.215-3.327 3.012-6.104 6.347-5.992v4.06c-1.393-.16-2.775.76-3.085 2.112-.397 1.488.583 3.125 2.1 3.328 1.455.234 2.924-.766 3.14-2.224.048-2.617.02-5.237.03-7.856.002-3.834-.002-7.67.002-11.504z"></path></svg>
                                                @else
                                                    <div class="relative w-full h-full flex items-center justify-center" x-data="{ imgFailedMainList: false }">
                                                        <img x-show="!imgFailedMainList" 
                                                             src="{{ $this->resolveArticleLogoUrl($article) }}" 
                                                             x-on:error="imgFailedMainList = true"
                                                             class="w-full h-full object-cover" 
                                                             alt="{{ $article->source_name }}" />
                                                        <div x-show="imgFailedMainList" class="absolute inset-0 w-full h-full bg-transparent flex items-center justify-center" style="display: none;">
                                                            <svg class="w-5 h-5 text-[#1fa387]" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path></svg>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="text-left">
                                                <h4 class="text-sm font-extrabold text-slate-800 tracking-tight">
                                                    @if($isFacebook)
                                                        facebook
                                                    @elseif(strtolower($article->source_name) == 'twitter')
                                                        x.com
                                                    @elseif(str_contains($article->source_name, '.'))
                                                        {{ strtolower($article->source_name) }}
                                                    @else
                                                        {{ strtolower($article->source_name) }}.com
                                                    @endif
                                                </h4>
                                                <p class="text-[10px] font-semibold text-slate-400 mt-0.5">{{ $article->published_at ? \Carbon\Carbon::parse($article->published_at)->format('d M Y, H:i') . ' (' . \Carbon\Carbon::parse($article->published_at)->diffForHumans() . ')' : 'Baru saja' }}</p>
                                            </div>
                                        </div>
                                    @php
                                        $isSocial = $article->category === 'social' || $isFacebook || strtolower($article->source_name) == 'tiktok' || strtolower($article->source_name) == 'instagram' || strtolower($article->source_name) == 'twitter';
                                        $likesCount = 0;
                                        $commentsCount = 0;
                                        if ($isSocial) {
                                            $socialItem = \App\Models\SocialMediaItem::where('post_url', $article->canonical_url)
                                                ->orWhere('post_url', $article->url)
                                                ->first();
                                            if ($socialItem) {
                                                $likesCount = $socialItem->like_count ?? 0;
                                                $commentsCount = $socialItem->comment_count ?? 0;
                                            }
                                        }
                                    @endphp
                                    <!-- Header Badges -->
                                    <div class="flex items-center gap-1.5">
                                        <span class="px-2.5 py-1 text-[9px] font-bold rounded-xl border {{ $sentimentBg }}">{{ $sentimentLabel }}</span>
                                        
                                    </div>
                                </div>
                                    <!-- Metrics Grid (Cleaned & Modernized) -->
                                    <div class="grid gap-2 bg-slate-50/60 rounded-2xl p-3 border border-slate-100 mb-4 text-left {{ $isSocial ? 'grid-cols-5' : 'grid-cols-3' }}">
                                        <div class="px-1.5 py-0.5">
                                            <span class="text-[8px] font-bold text-slate-400 uppercase tracking-widest block mb-0.5">Jangkauan</span>
                                            <div class="flex items-start gap-1 text-slate-800 text-[11px] md:text-xs font-black">
                                                <span class="material-symbols-outlined text-[#1fa387] text-[14px] md:text-[15px] mt-0.5">insights</span>
                                                <div class="flex flex-col leading-tight">
                                                    <span>
                                                        @if($projectReachDisplay['hasOfficialProjectReach'])
                                                            {{ number_format($projectReachDisplay['reachValue'], 0, ',', '.') }}
                                                        @elseif($projectReachDisplay['hasReadableAiReach'])
                                                            Belum tersedia
                                                        @else
                                                            Belum dinilai AI
                                                        @endif
                                                    </span>
                                                    <span class="text-[9px] font-semibold text-slate-400">
                                                        @if($projectReachDisplay['hasOfficialProjectReach'])
                                                            {{ $projectReachDisplay['levelLabel'] }}
                                                        @elseif($projectReachDisplay['hasReadableAiReach'])
                                                            Belum tersedia
                                                        @endif
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="px-1.5 py-0.5 border-l border-slate-200">
                                            <span class="text-[8px] font-bold text-slate-400 uppercase tracking-widest block mb-0.5">Skor</span>
                                            <div class="flex items-center gap-1 text-slate-800 text-[11px] md:text-xs font-black">
                                                <span class="material-symbols-outlined text-[#1fa387] text-[14px] md:text-[15px]">analytics</span>
                                                <span>
                                                    @if($projectReachDisplay['hasOfficialProjectReach'])
                                                        {{ $projectReachDisplay['scoreValue'] . '/10' }}
                                                    @elseif($projectReachDisplay['hasReadableAiReach'])
                                                        Belum tersedia
                                                    @else
                                                        Belum dinilai AI
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                        <div class="px-1.5 py-0.5 border-l border-slate-200">
                                            <span class="text-[8px] font-bold text-slate-400 uppercase tracking-widest block mb-0.5">Tanggal</span>
                                            <div class="flex items-center gap-1 text-slate-800 text-[11px] md:text-xs font-black">
                                                <span class="material-symbols-outlined text-[#1fa387] text-[14px] md:text-[15px]">calendar_month</span>
                                                <span class="truncate" title="{{ $article->published_at ? \Carbon\Carbon::parse($article->published_at)->format('d M Y, H:i') : 'Baru saja' }}">
                                                    {{ $article->published_at ? \Carbon\Carbon::parse($article->published_at)->format('d/m/y') : 'Baru saja' }}
                                                </span>
                                            </div>
                                        </div>
                                        @if($isSocial)
                                        <div class="px-1.5 py-0.5 border-l border-slate-200">
                                            <span class="text-[8px] font-bold text-slate-400 uppercase tracking-widest block mb-0.5">{{ strtolower($article->source_name) === 'tiktok' ? 'Love' : 'Like' }}</span>
                                            <div class="flex items-center gap-1 text-slate-800 text-[11px] md:text-xs font-black">
                                                <span class="material-symbols-outlined text-[#1fa387] text-[14px] md:text-[15px]">{{ strtolower($article->source_name) === 'tiktok' ? 'favorite' : 'thumb_up' }}</span>
                                                <span>{{ number_format($likesCount, 0, ',', '.') }}</span>
                                            </div>
                                        </div>
                                        <div class="px-1.5 py-0.5 border-l border-slate-200">
                                            <span class="text-[8px] font-bold text-slate-400 uppercase tracking-widest block mb-0.5">Komen</span>
                                            <div class="flex items-center gap-1 text-slate-800 text-[11px] md:text-xs font-black">
                                                <span class="material-symbols-outlined text-[#1fa387] text-[14px] md:text-[15px]">comment</span>
                                                <span>{{ number_format($commentsCount, 0, ',', '.') }}</span>
                                            </div>
                                        </div>
                                        @endif
                                    </div>

                                    <!-- Content Body (Sleek teaser layout) -->
                                    <div class="space-y-2.5 mb-5 text-left flex-grow">
                                        <h3 class="text-base md:text-[17px] font-extrabold text-slate-900 leading-snug tracking-tight hover:text-[#1fa387] transition-colors duration-150">
                                            {{ $this->displayArticleTitle($article) }}
                                        </h3>
                                        <p class="text-sm text-slate-600 leading-relaxed line-clamp-3">
                                            {{ $this->formatArticleExcerpt($article, 200) }}
                                        </p>

                                        @php
                                            $aiResult = $this->getValidAiResult($article);
                                            $aiSummary = $aiResult?->summary;
                                        @endphp

                                        @if($aiSummary)
                                            <div x-data="{ isOpen: false }" class="mt-4 mb-2 text-left">
                                                <!-- Collapsible Trigger Button (Icon Only) -->
                                                <button 
                                                    type="button"
                                                    @click="isOpen = !isOpen"
                                                    class="w-8 h-8 rounded-xl bg-[#1fa387]/5 hover:bg-[#1fa387]/10 text-[#1fa387] border border-[#1fa387]/15 flex items-center justify-center transition-all duration-200 cursor-pointer shadow-sm"
                                                    title="Ringkasan AI"
                                                >
                                                    <span class="material-symbols-outlined text-[15px] transition-transform duration-200" :class="isOpen ? 'rotate-45 scale-110' : ''">auto_awesome</span>
                                                </button>

                                                <!-- Animated Summary Box -->
                                                <div 
                                                    x-show="isOpen"
                                                    x-transition:enter="transition ease-out duration-250"
                                                    x-transition:enter-start="opacity-0 transform -translate-y-2 scale-95"
                                                    x-transition:enter-end="opacity-100 transform translate-y-0 scale-100"
                                                    x-transition:leave="transition ease-in duration-200"
                                                    x-transition:leave-start="opacity-100 transform translate-y-0 scale-100"
                                                    x-transition:leave-end="opacity-0 transform -translate-y-2 scale-95"
                                                    class="mt-3 p-4 bg-gradient-to-r from-[#1fa387]/5 to-emerald-50/20 border border-[#1fa387]/10 rounded-2xl flex items-start gap-3.5 shadow-sm"
                                                    style="display: none;"
                                                >
                                                    <div class="w-8 h-8 rounded-xl bg-white border border-[#1fa387]/15 flex items-center justify-center flex-shrink-0 shadow-sm">
                                                        <span class="material-symbols-outlined text-[#1fa387] text-[16px] font-bold">auto_awesome</span>
                                                    </div>
                                                    <div class="space-y-1 min-w-0 flex-grow">
                                                        <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Ringkasan AI</span>
                                                        <p class="text-xs md:text-sm text-slate-500 leading-relaxed font-medium">
                                                            {{ $aiSummary }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Card Actions -->
                                    <div class="flex items-center justify-between border-t border-slate-100 pt-4 mt-auto">
                                        <div class="flex items-center gap-3">
                                            @if($this->isAdmin())
                                                <button 
                                                    wire:click="deleteArticle({{ $article->id }})" 
                                                    wire:confirm="Hapus mention ini?"
                                                    class="text-slate-350 hover:text-red-500 p-1.5 transition-colors flex-shrink-0"
                                                    title="Hapus"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                </button>
                                            @endif

                                            @php
                                                $srcLowerCheck = strtolower($article->source_name);
                                                $isSocialMediaCheck = str_contains($srcLowerCheck, 'facebook') || str_contains($srcLowerCheck, 'fb') || 
                                                                      str_contains($srcLowerCheck, 'instagram') || $srcLowerCheck === 'ig' || 
                                                                      str_contains($srcLowerCheck, 'tiktok') || $srcLowerCheck === 'tk' || 
                                                                      str_contains($srcLowerCheck, 'twitter') || $srcLowerCheck === 'x.com';
                                            @endphp

                                            @if(!$isSocialMediaCheck)
                                                @if(str_contains($article->source_name, ' '))
                                                    <span class="px-2.5 py-1 text-[10px] font-bold bg-amber-50 text-amber-700 rounded-lg border border-amber-200 uppercase tracking-wide">Hasil Google News</span>
                                                @else
                                                    <span class="px-2.5 py-1 text-[10px] font-bold bg-sky-50 text-sky-700 rounded-lg border border-sky-200 uppercase tracking-wide">Portal Manual</span>
                                                @endif
                                            @endif
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <!-- Topic Category Badge (Enlarged & Redesigned) -->
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-bold bg-slate-50 border border-slate-200/80 text-slate-600 rounded-xl whitespace-nowrap" title="{{ $article->category }}">
                                                <span class="material-symbols-outlined text-[13px] text-slate-400">local_offer</span>
                                                {{ $article->category }}
                                            </span>

                                            @if($article->url)
                                                <button 
                                                    type="button"
                                                    @click="openDetail(
                                                        {{ Js::from($article->title) }},
                                                        {{ Js::from($article->source_name) }},
                                                        {{ Js::from($article->published_at ? \Carbon\Carbon::parse($article->published_at)->format('d F Y, H:i') : 'Just now') }},
                                                        {{ Js::from($article->url) }},
                                                        {{ Js::from($this->cleanNoiseText($article->content)) }},
                                                        {{ Js::from($this->getValidAiResult($article)?->summary ?? 'Belum ada analisis ringkasan AI.') }},
                                                        {{ Js::from($this->getValidAiResult($article)?->recommendation ?? 'Tidak ada rekomendasi khusus.') }},
                                                        {{ Js::from($this->getValidAiResult($article)?->sentiment) }},
                                                        {{ Js::from($article->category) }},
                                                        {{ Js::from($projectReachDisplay['hasOfficialProjectReach'] ? number_format($projectReachDisplay['reachValue'], 0, ',', '.') : ($projectReachDisplay['hasReadableAiReach'] ? 'Belum tersedia' : 'Belum dinilai AI')) }},
                                                        {{ Js::from($projectReachDisplay['hasOfficialProjectReach'] ? $projectReachDisplay['levelLabel'] : ($projectReachDisplay['hasReadableAiReach'] ? 'Belum tersedia' : '')) }},
                                                        {{ Js::from($projectReachDisplay['hasOfficialProjectReach'] ? $projectReachDisplay['scoreValue'] . '/10' : ($projectReachDisplay['hasReadableAiReach'] ? 'Belum tersedia' : 'Belum dinilai AI')) }},
                                                        {{ Js::from($article->published_at ? \Carbon\Carbon::parse($article->published_at)->format('d/m/y') : 'Baru saja') }},
                                                        {{ Js::from($likesCount) }},
                                                        {{ Js::from($commentsCount) }}
                                                    )" 
                                                    class="px-4 py-2 border border-slate-200/80 text-slate-700 hover:bg-slate-50 font-bold text-xs rounded-xl transition flex items-center gap-1 bg-white cursor-pointer hover:border-slate-300"
                                                >
                                                    <span>Lihat Detail</span>
                                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        <!-- Infinite Scroll / Load More -->
                        @php
                            $totalArticlesCount = $this->applyActiveFilters($this->projectArticlesQuery())->count();
                        @endphp

                        @if($articlesList->count() < $totalArticlesCount)
                            <div x-intersect="$wire.loadMore()" class="py-6 text-center text-xs text-slate-500 font-medium flex items-center justify-center gap-2">
                                <svg class="animate-spin h-4 w-4 text-[#1fa387]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>Memuat data lainnya...</span>
                            </div>
                        @else
                            <div class="py-6 mt-4 border-t border-slate-100 text-center text-xs text-slate-400 font-medium">
                                <p class="text-slate-500 font-semibold">Semua data telah dimuat</p>
                                <p class="text-[10px] text-slate-400 mt-0.5">Tidak ada data tambahan yang tersedia</p>
                            </div>
                        @endif
                    @endif
                </section>
            @elseif($this->isTab('analisis'))
                <!-- TAB 2: Analisis (Redesigned matching screenshots) -->
                <section class="flex-1 min-w-0 space-y-6">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900 mb-0.5 text-left flex items-center gap-2"><span class="material-symbols-outlined text-[#1fa387] text-[22px]">analytics</span>Analisis</h2>
                        <p class="text-xs text-slate-500 text-left">Pantau ringkasan performa dan wawasan data untuk proyek <span class="text-[#1fa387] font-bold uppercase">{{ $projectName }}</span></p>
                    </div>

                    <!-- Gambaran Umum Card Grid -->
                    <div class="bg-white rounded-3xl border border-slate-200 p-8 shadow-sm text-left space-y-6">
                        <h3 class="text-sm font-bold text-slate-800">Gambaran Umum</h3>                        <!-- Row 1: KPI metrics cards -->
                        <!-- Row 1: KPI metrics cards -->
                        <div class="grid grid-cols-3 gap-4">
                            <!-- Card 1: Total Artikel Ditemukan -->
                            <div class="border border-slate-200 bg-white rounded-3xl p-6 shadow-sm hover:shadow-md transition-all duration-200 flex items-center justify-between h-[120px]">
                                <div class="space-y-1.5 text-left">
                                    <span class="text-xs font-extrabold tracking-wider text-slate-400 uppercase block">TOTAL ARTIKEL DITEMUKAN</span>
                                    <h2 class="text-5xl font-black text-slate-900 tracking-tight leading-none">{{ $fmt($totalMentions) }}</h2>
                                </div>
                                <div class="w-12 h-12 rounded-2xl overflow-hidden bg-emerald-50 flex items-center justify-center flex-shrink-0 text-[#1fa387]">
                                    <span class="material-symbols-outlined text-[24px]">article</span>
                                </div>
                            </div>
                            <!-- Card 2: Total Jangkauan -->
                            <div class="border border-slate-200 bg-white rounded-3xl p-6 shadow-sm hover:shadow-md transition-all duration-200 flex items-center justify-between h-[120px]">
                                <div class="space-y-1.5 text-left">
                                    <span class="text-xs font-extrabold tracking-wider text-slate-400 uppercase block">TOTAL JANGKAUAN</span>
                                    <h2 class="text-5xl font-black text-slate-900 tracking-tight leading-none">{{ $fmt($totalReach) }}</h2>
                                </div>
                                <div class="w-12 h-12 rounded-2xl overflow-hidden bg-indigo-50 flex items-center justify-center flex-shrink-0 text-indigo-600">
                                    <span class="material-symbols-outlined text-[24px]">hub</span>
                                </div>
                            </div>
                            <!-- Card 3: Interaksi Sosial Media -->
                            <div class="border border-slate-200 bg-white rounded-3xl p-6 shadow-sm hover:shadow-md transition-all duration-200 flex items-center justify-between h-[120px]">
                                <div class="space-y-1.5 text-left">
                                    <span class="text-xs font-extrabold tracking-wider text-slate-400 uppercase block">INTERAKSI SOSIAL MEDIA</span>
                                    <h2 class="text-5xl font-black text-slate-900 tracking-tight leading-none">{{ $fmt($interactionCount) }}</h2>
                                </div>
                                <div class="w-12 h-12 rounded-2xl overflow-hidden bg-purple-50 flex items-center justify-center flex-shrink-0 text-purple-600">
                                    <span class="material-symbols-outlined text-[24px]">forum</span>
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: Publication Channels breakdown metrics cards (Instagram, TikTok, Facebook, Portal Berita) -->
                        <div class="grid grid-cols-4 gap-4">
                            <!-- Instagram -->
                            <div class="border border-slate-200 bg-white rounded-3xl p-6 shadow-sm hover:shadow-md transition-all duration-200 flex flex-col justify-between h-[190px]">
                                <div class="flex items-center justify-between w-full">
                                    <div class="flex items-center gap-3 text-left">
                                        <!-- Icon wrapper -->
                                        <div class="relative w-[52px] h-[52px] rounded-2xl overflow-hidden flex items-center justify-center shrink-0 shadow-lg shadow-pink-500/20 group-hover:scale-105 transition-all duration-300" style="background: linear-gradient(135deg, #833ab4, #fd1d1d, #fcb045);">
                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5" stroke-linecap="round"></line></svg>
                                        </div>
                                        <div class="flex flex-col text-left">
                                            <span class="text-xs font-extrabold tracking-wider text-slate-400 uppercase">INSTAGRAM</span>
                                            <span class="text-[10px] font-bold text-slate-400 mt-1 uppercase tracking-wider">Penyebutan</span>
                                        </div>
                                    </div>
                                    <h2 class="text-5xl font-black text-slate-900 tracking-tight leading-none">
                                        {{ $fmt($igCount) }}
                                    </h2>
                                </div>
                                
                                <div class="flex items-center justify-between gap-2 pt-3 border-t border-slate-100 mt-auto text-[10px]">
                                    <div class="inline-flex min-w-0 items-center gap-1 rounded-full bg-slate-50 px-2 py-1 text-slate-600 ring-1 ring-slate-100">
                                        <span class="material-symbols-outlined text-[11px] text-slate-400">insights</span>
                                        <span class="font-extrabold leading-none">{{ $fmt($igReach) }}</span>
                                        <span class="hidden xl:inline text-[8px] font-bold uppercase tracking-wide text-slate-400">Jangkauan</span>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1">
                                        <div class="inline-flex items-center gap-0.5 rounded-full bg-slate-50 px-2 py-1 text-slate-600 ring-1 ring-slate-100">
                                            <span class="material-symbols-outlined text-[11px] text-slate-400">thumb_up</span>
                                            <span class="font-bold leading-none">{{ $fmt($igLikes) }}</span>
                                        </div>
                                        <div class="inline-flex items-center gap-0.5 rounded-full bg-slate-50 px-2 py-1 text-slate-600 ring-1 ring-slate-100">
                                            <span class="material-symbols-outlined text-[11px] text-slate-400">comment</span>
                                            <span class="font-bold leading-none">{{ $fmt($igComments) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- TikTok -->
                            <div class="border border-slate-200 bg-white rounded-3xl p-6 shadow-sm hover:shadow-md transition-all duration-200 flex flex-col justify-between h-[190px]">
                                <div class="flex items-center justify-between w-full">
                                    <div class="flex items-center gap-3 text-left">
                                        <!-- Icon wrapper -->
                                        <div class="relative w-[52px] h-[52px] rounded-2xl overflow-hidden flex items-center justify-center shrink-0 shadow-lg shadow-slate-900/20 group-hover:scale-105 transition-all duration-300" style="background: linear-gradient(135deg, #111111, #333333);">
                                            <svg class="w-6 h-6 fill-current text-white" viewBox="0 0 24 24"><path d="M12.525.01c1.306-.022 2.615-.011 3.921-.012.08 1.836 1.011 3.5 2.501 4.485.006 1.341-.004 2.683-.004 4.024-1.57-.107-3.067-.932-3.955-2.247-.008 2.827-.003 5.657-.005 8.486-.098 3.546-3.13 6.643-6.726 6.467-3.526-.067-6.523-3.18-6.241-6.722.215-3.327 3.012-6.104 6.347-5.992v4.06c-1.393-.16-2.775.76-3.085 2.112-.397 1.488.583 3.125 2.1 3.328 1.455.234 2.924-.766 3.14-2.224.048-2.617.02-5.237.03-7.856.002-3.834-.002-7.67.002-11.504z"></path></svg>
                                        </div>
                                        <div class="flex flex-col text-left">
                                            <span class="text-xs font-extrabold tracking-wider text-slate-400 uppercase">TIKTOK</span>
                                            <span class="text-[10px] font-bold text-slate-400 mt-1 uppercase tracking-wider">Penyebutan</span>
                                        </div>
                                    </div>
                                    <h2 class="text-5xl font-black text-slate-900 tracking-tight leading-none">
                                        {{ $fmt($ttCount) }}
                                    </h2>
                                </div>
                                
                                <div class="flex items-center justify-between gap-2 pt-3 border-t border-slate-100 mt-auto text-[10px]">
                                    <div class="inline-flex min-w-0 items-center gap-1 rounded-full bg-slate-50 px-2 py-1 text-slate-600 ring-1 ring-slate-100">
                                        <span class="material-symbols-outlined text-[11px] text-slate-400">insights</span>
                                        <span class="font-extrabold leading-none">{{ $fmt($ttReach) }}</span>
                                        <span class="hidden xl:inline text-[8px] font-bold uppercase tracking-wide text-slate-400">Jangkauan</span>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1">
                                        <div class="inline-flex items-center gap-0.5 rounded-full bg-slate-50 px-2 py-1 text-slate-600 ring-1 ring-slate-100">
                                            <span class="material-symbols-outlined text-[11px] text-slate-400">thumb_up</span>
                                            <span class="font-bold leading-none">{{ $fmt($ttLikes) }}</span>
                                        </div>
                                        <div class="inline-flex items-center gap-0.5 rounded-full bg-slate-50 px-2 py-1 text-slate-600 ring-1 ring-slate-100">
                                            <span class="material-symbols-outlined text-[11px] text-slate-400">comment</span>
                                            <span class="font-bold leading-none">{{ $fmt($ttComments) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Facebook -->
                            <div class="border border-slate-200 bg-white rounded-3xl p-6 shadow-sm hover:shadow-md transition-all duration-200 flex flex-col justify-between h-[190px]">
                                <div class="flex items-center justify-between w-full">
                                    <div class="flex items-center gap-3 text-left">
                                        <!-- Icon wrapper -->
                                        <div class="relative w-[52px] h-[52px] rounded-2xl overflow-hidden flex items-center justify-center shrink-0 shadow-lg shadow-blue-500/20 group-hover:scale-105 transition-all duration-300" style="background: linear-gradient(135deg, #1877f2, #3b82f6);">
                                            <svg class="w-6 h-6 fill-current text-white" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"></path></svg>
                                        </div>
                                        <div class="flex flex-col text-left">
                                            <span class="text-xs font-extrabold tracking-wider text-slate-400 uppercase">FACEBOOK</span>
                                            <span class="text-[10px] font-bold text-slate-400 mt-1 uppercase tracking-wider">Penyebutan</span>
                                        </div>
                                    </div>
                                    <h2 class="text-5xl font-black text-slate-900 tracking-tight leading-none">
                                        {{ $fmt($fbCount) }}
                                    </h2>
                                </div>
                                
                                <div class="flex items-center justify-between gap-2 pt-3 border-t border-slate-100 mt-auto text-[10px]">
                                    <div class="inline-flex min-w-0 items-center gap-1 rounded-full bg-slate-50 px-2 py-1 text-slate-600 ring-1 ring-slate-100">
                                        <span class="material-symbols-outlined text-[11px] text-slate-400">insights</span>
                                        <span class="font-extrabold leading-none">{{ $fmt($fbReach) }}</span>
                                        <span class="hidden xl:inline text-[8px] font-bold uppercase tracking-wide text-slate-400">Jangkauan</span>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1">
                                        <div class="inline-flex items-center gap-0.5 rounded-full bg-slate-50 px-2 py-1 text-slate-600 ring-1 ring-slate-100">
                                            <span class="material-symbols-outlined text-[11px] text-slate-400">thumb_up</span>
                                            <span class="font-bold leading-none">{{ $fmt($fbLikes) }}</span>
                                        </div>
                                        <div class="inline-flex items-center gap-0.5 rounded-full bg-slate-50 px-2 py-1 text-slate-600 ring-1 ring-slate-100">
                                            <span class="material-symbols-outlined text-[11px] text-slate-400">comment</span>
                                            <span class="font-bold leading-none">{{ $fmt($fbComments) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Portal Berita -->
                            <div class="border border-slate-200 bg-white rounded-3xl p-6 shadow-sm hover:shadow-md transition-all duration-200 flex flex-col justify-between h-[190px]">
                                <div class="flex items-center justify-between w-full">
                                    <div class="flex items-center gap-3 text-left">
                                        <!-- Icon wrapper -->
                                        <div class="relative w-[52px] h-[52px] rounded-2xl overflow-hidden bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shrink-0 shadow-lg shadow-emerald-500/20 group-hover:scale-105 transition-all duration-300" style="background: linear-gradient(135deg, #10b981, #14b8a6);">
                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6m-6 4h3"></path></svg>
                                        </div>
                                        <div class="flex flex-col text-left">
                                            <span class="text-xs font-extrabold tracking-wider text-slate-400 uppercase">PORTAL BERITA</span>
                                            <span class="text-[10px] font-bold text-slate-400 mt-1 uppercase tracking-wider">Penyebutan</span>
                                        </div>
                                    </div>
                                    <h2 class="text-5xl font-black text-slate-900 tracking-tight leading-none">
                                        {{ $fmt($newsCount) }}
                                    </h2>
                                </div>
                                
                                <div class="flex items-center justify-between gap-2 pt-3 border-t border-slate-100 mt-auto text-[10px]">
                                    <div class="inline-flex min-w-0 items-center gap-1 rounded-full bg-slate-50 px-2 py-1 text-slate-600 ring-1 ring-slate-100">
                                        <span class="material-symbols-outlined text-[11px] text-slate-400">insights</span>
                                        <span class="font-extrabold leading-none">{{ $fmt($newsReach) }}</span>
                                        <span class="hidden xl:inline text-[8px] font-bold uppercase tracking-wide text-slate-400">Jangkauan</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <span class="text-[9px] bg-slate-100 text-slate-400 font-extrabold px-2.5 py-0.5 rounded-full border border-slate-200">PORTAL BERITA</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 3: Sentiment breakdown cards (Full width 2 columns) -->
                        <div class="grid grid-cols-2 gap-4 mt-4">
                            <!-- Sentimen Sosmed -->
                            <div class="border border-slate-200 bg-white rounded-3xl p-6 shadow-sm hover:shadow-md transition-all duration-200 space-y-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                                            <span class="material-symbols-outlined text-[18px]">sentiment_satisfied</span>
                                        </div>
                                        <span class="text-xs font-extrabold tracking-wider text-slate-400 uppercase">SENTIMEN SOSIAL MEDIA</span>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-3 gap-2">
                                    <!-- Positif -->
                                    <div class="bg-emerald-50/40 rounded-2xl p-3 border border-emerald-100/50 text-left">
                                        <span class="text-[10px] font-extrabold text-emerald-600 block uppercase tracking-wider">Positif</span>
                                        <h3 class="text-3xl font-black text-emerald-700 mt-1">{{ $fmt($socPos) }}</h3>
                                    </div>
                                    <!-- Netral -->
                                    <div class="bg-slate-50 rounded-2xl p-3 border border-slate-100 text-left">
                                        <span class="text-[10px] font-extrabold text-slate-500 block uppercase tracking-wider">Netral</span>
                                        <h3 class="text-3xl font-black text-slate-700 mt-1">{{ $fmt($socNeu) }}</h3>
                                    </div>
                                    <!-- Negatif -->
                                    <div class="bg-rose-50/40 rounded-2xl p-3 border border-rose-100/50 text-left">
                                        <span class="text-[10px] font-extrabold text-rose-600 block uppercase tracking-wider">Negatif</span>
                                        <h3 class="text-3xl font-black text-rose-700 mt-1">{{ $fmt($socNeg) }}</h3>
                                    </div>
                                </div>
                                
                                <!-- Visual Bar -->
                                @php
                                    $socTotal = $socPos + $socNeu + $socNeg;
                                    $socPosPct = $socTotal > 0 ? round(($socPos / $socTotal) * 100) : 0;
                                    $socNeuPct = $socTotal > 0 ? round(($socNeu / $socTotal) * 100) : 0;
                                    $socNegPct = $socTotal > 0 ? round(($socNeg / $socTotal) * 100) : 0;
                                @endphp
                                <div class="space-y-1.5">
                                    <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden flex">
                                        <div class="h-full bg-emerald-500 rounded-l-full" style="width: {{ $socPosPct }}%"></div>
                                        <div class="h-full bg-slate-300" style="width: {{ $socNeuPct }}%"></div>
                                        <div class="h-full bg-rose-500 rounded-r-full" style="width: {{ $socNegPct }}%"></div>
                                    </div>
                                    <div class="flex items-center justify-between text-[9px] font-bold text-slate-400 uppercase">
                                        <span>Pos: {{ $socPosPct }}%</span>
                                        <span>Net: {{ $socNeuPct }}%</span>
                                        <span>Neg: {{ $socNegPct }}%</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Sentimen Berita -->
                            <div class="border border-slate-200 bg-white rounded-3xl p-6 shadow-sm hover:shadow-md transition-all duration-200 space-y-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
                                            <span class="material-symbols-outlined text-[18px]">newspaper</span>
                                        </div>
                                        <span class="text-xs font-extrabold tracking-wider text-slate-400 uppercase">SENTIMEN BERITA</span>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-3 gap-2">
                                    <!-- Positif -->
                                    <div class="bg-emerald-50/40 rounded-2xl p-3 border border-emerald-100/50 text-left">
                                        <span class="text-[10px] font-extrabold text-emerald-600 block uppercase tracking-wider">Positif</span>
                                        <h3 class="text-3xl font-black text-emerald-700 mt-1">{{ $fmt($newsPos) }}</h3>
                                    </div>
                                    <!-- Netral -->
                                    <div class="bg-slate-50 rounded-2xl p-3 border border-slate-100 text-left">
                                        <span class="text-[10px] font-extrabold text-slate-500 block uppercase tracking-wider">Netral</span>
                                        <h3 class="text-3xl font-black text-slate-700 mt-1">{{ $fmt($newsNeu) }}</h3>
                                    </div>
                                    <!-- Negatif -->
                                    <div class="bg-rose-50/40 rounded-2xl p-3 border border-rose-100/50 text-left">
                                        <span class="text-[10px] font-extrabold text-rose-600 block uppercase tracking-wider">Negatif</span>
                                        <h3 class="text-3xl font-black text-rose-700 mt-1">{{ $fmt($newsNeg) }}</h3>
                                    </div>
                                </div>
                                
                                <!-- Visual Bar -->
                                @php
                                    $newsTotal = $newsPos + $newsNeu + $newsNeg;
                                    $newsPosPct = $newsTotal > 0 ? round(($newsPos / $newsTotal) * 100) : 0;
                                    $newsNeuPct = $newsTotal > 0 ? round(($newsNeu / $newsTotal) * 100) : 0;
                                    $newsNegPct = $newsTotal > 0 ? round(($newsNeg / $newsTotal) * 100) : 0;
                                @endphp
                                <div class="space-y-1.5">
                                    <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden flex">
                                        <div class="h-full bg-emerald-500 rounded-l-full" style="width: {{ $newsPosPct }}%"></div>
                                        <div class="h-full bg-slate-300" style="width: {{ $newsNeuPct }}%"></div>
                                        <div class="h-full bg-rose-500 rounded-r-full" style="width: {{ $newsNegPct }}%"></div>
                                    </div>
                                    <div class="flex items-center justify-between text-[9px] font-bold text-slate-400 uppercase">
                                        <span>Pos: {{ $newsPosPct }}%</span>
                                        <span>Net: {{ $newsNeuPct }}%</span>
                                        <span>Neg: {{ $newsNegPct }}%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SVGs Line Chart (Daily trend) -->
                    <div class="bg-white rounded-3xl border border-slate-200 p-8 shadow-sm text-left space-y-4" x-data="{ trendMode: 'harian', activePoint: null }">
                            <div class="flex justify-between items-center pb-2 border-b border-slate-100/85">
                                <div class="space-y-0.5 text-left">
                                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-[18px] text-[#1fa387]">show_chart</span>
                                        Grafik Tren Kinerja Proyek
                                    </h3>
                                    <p class="text-[11px] text-slate-400">Memantau fluktuasi volume data artikel yang berhasil dihimpun oleh scraper.</p>
                                </div>
                                <div class="bg-slate-100 p-0.5 rounded-xl flex gap-1 text-[10px] font-bold text-slate-500 shadow-inner">
                                    <button @click="trendMode = 'harian'" class="px-4 py-1.5 rounded-lg transition cursor-pointer" :class="trendMode == 'harian' ? 'bg-[#1fa387] text-white shadow-sm' : 'hover:text-slate-800'">Harian</button>
                                    <button @click="trendMode = 'mingguan'" class="px-4 py-1.5 rounded-lg transition cursor-pointer" :class="trendMode == 'mingguan' ? 'bg-[#1fa387] text-white shadow-sm' : 'hover:text-slate-800'">Mingguan</button>
                                    <button @click="trendMode = 'bulanan'" class="px-4 py-1.5 rounded-lg transition cursor-pointer" :class="trendMode == 'bulanan' ? 'bg-[#1fa387] text-white shadow-sm' : 'hover:text-slate-800'">Bulanan</button>
                                </div>
                            </div>
                            
                            <!-- Beautiful Vector Wave Line Chart -->
                            <div class="relative w-full h-[200px] bg-gradient-to-b from-emerald-50/10 to-transparent rounded-2xl p-2 border border-slate-50">
                                <svg class="w-full h-full" viewBox="0 0 1000 170" preserveAspectRatio="none">
                                    <!-- Gradient fill under path -->
                                    <defs>
                                        <linearGradient id="chartGrad" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="#1fa387" stop-opacity="0.22"/>
                                            <stop offset="100%" stop-color="#1fa387" stop-opacity="0.0"/>
                                        </linearGradient>
                                        <filter id="shadow" x="-5%" y="-5%" width="110%" height="110%">
                                            <feDropShadow dx="0" dy="4" stdDeviation="4" flood-color="#1fa387" flood-opacity="0.15" />
                                        </filter>
                                    </defs>
                                    
                                    <!-- Horizontal Grid Lines -->
                                    <line x1="40" y1="30" x2="960" y2="30" stroke="#f1f5f9" stroke-width="1" stroke-dasharray="4 4"/>
                                    <line x1="40" y1="85" x2="960" y2="85" stroke="#f1f5f9" stroke-width="1" stroke-dasharray="4 4"/>
                                    <line x1="40" y1="140" x2="960" y2="140" stroke="#e2e8f0" stroke-width="1"/>
                                    
                                    @php
                                        $harianPoints = $this->getTrendPoints('harian');
                                        $mingguanPoints = $this->getTrendPoints('mingguan');
                                        $bulananPoints = $this->getTrendPoints('bulanan');
                                        
                                        // Smooth Cubic Bezier Spline path generator
                                        $getCurvePath = function($pts) {
                                            if (empty($pts)) return 'M 50 140';
                                            $d = 'M ' . $pts[0]['x'] . ' ' . $pts[0]['y'];
                                            $count = count($pts);
                                            if ($count < 2) return $d;
                                            if ($count == 2) {
                                                return $d . ' L ' . $pts[1]['x'] . ' ' . $pts[1]['y'];
                                            }
                                            for ($i = 0; $i < $count - 1; $i++) {
                                                $p0 = $pts[$i];
                                                $p1 = $pts[$i + 1];
                                                $cpX1 = $p0['x'] + ($p1['x'] - $p0['x']) / 3;
                                                $cpY1 = $p0['y'];
                                                $cpX2 = $p0['x'] + 2 * ($p1['x'] - $p0['x']) / 3;
                                                $cpY2 = $p1['y'];
                                                $d .= " C $cpX1 $cpY1, $cpX2 $cpY2, {$p1['x']} {$p1['y']}";
                                            }
                                            return $d;
                                        };
                                        
                                        $getCurveFillPath = function($pts) use ($getCurvePath) {
                                            if (empty($pts)) return 'M 50 140 L 950 140 Z';
                                            $d = $getCurvePath($pts);
                                            $d .= ' L ' . $pts[count($pts)-1]['x'] . ' 140 L ' . $pts[0]['x'] . ' 140 Z';
                                            return $d;
                                        };
                                    @endphp
                                    
                                    <!-- Harian Path -->
                                    <g x-show="trendMode === 'harian'" 
                                       x-transition:enter="transition opacity duration-300 ease-out"
                                       x-transition:enter-start="opacity-0"
                                       x-transition:enter-end="opacity-100"
                                       x-transition:leave="transition opacity duration-150 ease-in"
                                       x-transition:leave-start="opacity-100"
                                       x-transition:leave-end="opacity-0"
                                       style="transition: opacity 0.3s ease-out;">
                                        <path d="{{ $getCurveFillPath($harianPoints) }}" fill="url(#chartGrad)"/>
                                        <path d="{{ $getCurvePath($harianPoints) }}" fill="none" stroke="#1fa387" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" filter="url(#shadow)"/>
                                        @foreach($harianPoints as $pt)
                                            <circle cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="5" fill="#fff" stroke="#1fa387" stroke-width="2.5" 
                                                @mouseenter="activePoint = { x: {{ $pt['x'] }}, y: {{ $pt['y'] }}, label: '{{ $pt['label'] }}', value: {{ $pt['count'] }} }"
                                                @mouseleave="activePoint = null"
                                                class="transition-all hover:r-7 duration-200 cursor-pointer"/>
                                        @endforeach
                                        <!-- Labels -->
                                        @foreach($harianPoints as $pt)
                                            <text x="{{ $pt['x'] }}" y="160" font-size="9" font-weight="bold" fill="#94a3b8" text-anchor="middle">{{ $pt['label'] }}</text>
                                        @endforeach
                                    </g>
 
                                    <!-- Mingguan Path -->
                                    <g x-show="trendMode === 'mingguan'" 
                                       x-transition:enter="transition opacity duration-300 ease-out"
                                       x-transition:enter-start="opacity-0"
                                       x-transition:enter-end="opacity-100"
                                       x-transition:leave="transition opacity duration-150 ease-in"
                                       x-transition:leave-start="opacity-100"
                                       x-transition:leave-end="opacity-0"
                                       style="transition: opacity 0.3s ease-out;">
                                        <path d="{{ $getCurveFillPath($mingguanPoints) }}" fill="url(#chartGrad)"/>
                                        <path d="{{ $getCurvePath($mingguanPoints) }}" fill="none" stroke="#1fa387" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" filter="url(#shadow)"/>
                                        @foreach($mingguanPoints as $pt)
                                            <circle cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="5" fill="#fff" stroke="#1fa387" stroke-width="2.5" 
                                                @mouseenter="activePoint = { x: {{ $pt['x'] }}, y: {{ $pt['y'] }}, label: '{{ $pt['label'] }}', value: {{ $pt['count'] }} }"
                                                @mouseleave="activePoint = null"
                                                class="transition-all hover:r-7 duration-200 cursor-pointer"/>
                                        @endforeach
                                        <!-- Labels -->
                                        @foreach($mingguanPoints as $pt)
                                            <text x="{{ $pt['x'] }}" y="160" font-size="9" font-weight="bold" fill="#94a3b8" text-anchor="middle">{{ $pt['label'] }}</text>
                                        @endforeach
                                    </g>
 
                                    <!-- Bulanan Path -->
                                    <g x-show="trendMode === 'bulanan'" 
                                       x-transition:enter="transition opacity duration-300 ease-out"
                                       x-transition:enter-start="opacity-0"
                                       x-transition:enter-end="opacity-100"
                                       x-transition:leave="transition opacity duration-150 ease-in"
                                       x-transition:leave-start="opacity-100"
                                       x-transition:leave-end="opacity-0"
                                       style="transition: opacity 0.3s ease-out;">
                                        <path d="{{ $getCurveFillPath($bulananPoints) }}" fill="url(#chartGrad)"/>
                                        <path d="{{ $getCurvePath($bulananPoints) }}" fill="none" stroke="#1fa387" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" filter="url(#shadow)"/>
                                        @foreach($bulananPoints as $pt)
                                            <circle cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="5" fill="#fff" stroke="#1fa387" stroke-width="2.5" 
                                                @mouseenter="activePoint = { x: {{ $pt['x'] }}, y: {{ $pt['y'] }}, label: '{{ $pt['label'] }}', value: {{ $pt['count'] }} }"
                                                @mouseleave="activePoint = null"
                                                class="transition-all hover:r-7 duration-200 cursor-pointer"/>
                                        @endforeach
                                        <!-- Labels -->
                                        @foreach($bulananPoints as $pt)
                                            <text x="{{ $pt['x'] }}" y="160" font-size="9" font-weight="bold" fill="#94a3b8" text-anchor="middle">{{ $pt['label'] }}</text>
                                        @endforeach
                                    </g>
                                </svg>
                                
                                <!-- Floating Premium Tooltip -->
                                <div x-show="activePoint" 
                                     x-transition:enter="transition ease-out duration-150"
                                     x-transition:enter-start="opacity-0 scale-95"
                                     x-transition:enter-end="opacity-100 scale-100"
                                     x-transition:leave="transition ease-in duration-100"
                                     x-transition:leave-start="opacity-100 scale-100"
                                     x-transition:leave-end="opacity-0 scale-95"
                                     class="absolute bg-slate-900/95 text-white text-[10px] rounded-xl px-3 py-2 shadow-xl pointer-events-none font-sans border border-slate-700/50 backdrop-blur-sm"
                                     :style="`left: ${activePoint ? (activePoint.x / 10) : 0}%; top: ${activePoint ? (activePoint.y * 200 / 170) : 0}px; transform: translate(-50%, -125%); z-index: 50;`"
                                >
                                    <div class="font-bold text-slate-300" x-text="activePoint ? activePoint.label : ''"></div>
                                    <div class="text-[11px] font-black text-emerald-400 mt-0.5" x-text="`${activePoint ? activePoint.value.toLocaleString('id-ID') : 0} Artikel`"></div>
                                </div>
                            </div>
                            
                            <!-- Legend and explanation -->
                            <div class="flex items-center justify-between text-[10px] text-slate-400 font-medium pt-2 px-1">
                                <div class="flex items-center gap-4">
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-3 h-3 rounded-full bg-[#1fa387] inline-block opacity-80 border-2 border-white shadow-sm"></span>
                                        <span class="text-slate-600 font-bold">Total Artikel</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[12px] text-slate-400">info</span>
                                        <span>Arahkan kursor ke titik grafik untuk detail angka</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span>Skala grafik: Otomatis</span>
                                </div>
                            </div>
                        </div>

                    <!-- Row 3: Word Cloud & Category Breakdowns side by side -->
                    <div class="grid grid-cols-2 gap-6">
                        <!-- Word Cloud -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm flex flex-col text-left h-[360px] relative overflow-hidden transition-all duration-300 hover:shadow-md">
                            <div class="flex justify-between items-center mb-3.5 relative z-10">
                                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Topik Utama</h3>
                                <span class="text-[10px] bg-slate-100 text-slate-500 font-bold px-2 py-0.5 rounded-full border border-slate-200">Awan Kata</span>
                            </div>
                            @php
                                $stopWords = ['dan', 'di', 'ke', 'dari', 'yang', 'untuk', 'dengan', 'ini', 'itu', 'pada', 'dalam', 'adalah', 'akan', 'juga', 'sudah', 'ada', 'bisa', 'atau', 'tidak', 'lebih', 'saat', 'oleh', 'para', 'telah', 'agar', 'atas', 'jika', 'karena', 'maka', 'namun', 'pun', 'serta', 'tentang', 'setelah', 'antara', 'hingga', 'ia', 'kami', 'kita', 'mereka', 'anda', 'bagi', 'dua', 'tiga', 'lain', 'hal', 'tahun', 'baru', 'terkait', 'pihak', 'sebuah', 'satu', 'tersebut', 'the', 'a', 'an', 'is', 'in', 'of', 'and', 'to', 'for', 'masa', 'jalan', 'jadi', 'pemerintah', 'gubernur'];
                                
                                // Get articles with their AI sentiment
                                $articles = (clone $this->projectArticlesQuery())
                                    ->leftJoin('ai_analysis_results as ai', 'articles.id', '=', 'ai.article_id')
                                    ->select('articles.title', \DB::raw("COALESCE(ai.sentiment, articles.sentiment, 'neutral') as word_sentiment"))
                                    ->limit(200)
                                    ->get();
                                
                                // Track word frequency AND sentiment votes
                                $wordData = []; // word => ['freq' => N, 'pos' => N, 'neu' => N, 'neg' => N]
                                foreach ($articles as $art) {
                                    $cleanTitle = strtolower(preg_replace('/[^a-zA-Z0-9\s]/u', ' ', html_entity_decode(strip_tags($art->title), ENT_QUOTES, 'UTF-8')));
                                    $words = array_filter(explode(' ', $cleanTitle), function($w) use ($stopWords) {
                                        return strlen($w) > 3 && !in_array($w, $stopWords);
                                    });
                                    $sent = $art->word_sentiment ?? 'neutral';
                                    foreach ($words as $word) {
                                        if (!isset($wordData[$word])) $wordData[$word] = ['freq' => 0, 'pos' => 0, 'neu' => 0, 'neg' => 0];
                                        $wordData[$word]['freq']++;
                                        if ($sent === 'positive') $wordData[$word]['pos']++;
                                        elseif ($sent === 'negative') $wordData[$word]['neg']++;
                                        else $wordData[$word]['neu']++;
                                    }
                                }
                                
                                // Sort by frequency
                                uasort($wordData, fn($a, $b) => $b['freq'] - $a['freq']);
                                $topWords = array_slice($wordData, 0, 20, true);
                                $maxFreq = !empty($topWords) ? max(array_column($topWords, 'freq')) : 1;
                                
                                // Sentiment colors for light background matching the app's clean palette
                                $sentColors = [
                                    'positive' => '#059669', // emerald-600
                                    'neutral'  => '#475569', // slate-600
                                    'negative' => '#dc2626', // red-600
                                ];
                                
                                // Determine dominant sentiment per word
                                $getWordSentiment = function($d) {
                                    if ($d['pos'] > $d['neg'] && $d['pos'] > $d['neu']) return 'positive';
                                    if ($d['neg'] > $d['pos'] && $d['neg'] > $d['neu']) return 'negative';
                                    return 'neutral';
                                };
                                
                                // Ring-based layout
                                $positions = [];
                                $cx = 50; $cy = 48;
                                $rings = [
                                    ['rx' => 0,  'ry' => 0,  'start' => 0],
                                    ['rx' => 18, 'ry' => 13, 'start' => -30],
                                    ['rx' => 34, 'ry' => 24, 'start' => 15],
                                    ['rx' => 46, 'ry' => 36, 'start' => -10],
                                ];
                                
                                $wordKeys = array_keys($topWords);
                                $ringAssign = [
                                    array_slice($wordKeys, 0, 1),
                                    array_slice($wordKeys, 1, 4),
                                    array_slice($wordKeys, 5, 6),
                                    array_slice($wordKeys, 11),
                                ];
                                
                                foreach ($ringAssign as $ringIdx => $words) {
                                    $ring = $rings[$ringIdx];
                                    $count = count($words);
                                    foreach ($words as $j => $word) {
                                        $d = $topWords[$word];
                                        $ratio = $d['freq'] / $maxFreq;
                                        $sent = $getWordSentiment($d);
                                        
                                        if ($ringIdx === 0) { $x = $cx; $y = $cy; }
                                        else {
                                            $angle = deg2rad($ring['start'] + ($j * (360 / max($count, 1))));
                                            $x = max(6, min(94, $cx + $ring['rx'] * cos($angle)));
                                            $y = max(6, min(94, $cy + $ring['ry'] * sin($angle)));
                                        }
                                        
                                        if ($ringIdx === 0) $rot = 0;
                                        elseif ($ringIdx <= 1) $rot = ($j % 3 === 2) ? -90 : 0;
                                        else $rot = ($j % 4 === 1) ? 90 : (($j % 4 === 3) ? -90 : 0);
                                        
                                        if ($ringIdx === 0) { $fontSize = '2.2rem'; $weight = '900'; }
                                        elseif ($ringIdx === 1) { $fontSize = $ratio >= 0.6 ? '1.2rem' : '1rem'; $weight = '800'; }
                                        elseif ($ringIdx === 2) { $fontSize = '0.82rem'; $weight = '700'; }
                                        else { $fontSize = '0.7rem'; $weight = '600'; }
                                        
                                        $color = $sentColors[$sent];
                                        $opacity = $ringIdx === 0 ? 1 : round(0.6 + $ratio * 0.4, 2);
                                        $freq = $d['freq'];
                                        
                                        $positions[] = compact('word', 'freq', 'sent', 'x', 'y', 'rot', 'fontSize', 'weight', 'opacity', 'color');
                                    }
                                }
                            @endphp
                            <div class="flex-grow relative overflow-hidden rounded-2xl z-10 w-full h-full p-4" style="background: radial-gradient(circle at 50% 50%, #ffffff 0%, #f1f5f9 100%); min-height: 220px; border: 1px solid #e2e8f0;">
                                <!-- Subtle glowing accent orbs suitable for light background -->
                                <div class="absolute top-0 right-0 w-36 h-36 rounded-full bg-emerald-500/[0.05] blur-3xl pointer-events-none"></div>
                                <div class="absolute bottom-0 left-0 w-28 h-28 rounded-full bg-rose-500/[0.03] blur-3xl pointer-events-none"></div>
                                
                                @forelse($positions as $p)
                                    <button
                                        type="button"
                                        wire:click="$set('search', '{{ $p['word'] }}')"
                                        class="absolute whitespace-nowrap hover:scale-125 hover:!opacity-100 transition-all duration-300 cursor-pointer select-none font-extrabold tracking-tight hover:z-20"
                                        style="left: {{ max(12, min(88, $p['x'])) }}%; top: {{ max(12, min(88, $p['y'])) }}%; transform: translate(-50%, -50%) rotate({{ $p['rot'] }}deg); font-size: {{ $p['fontSize'] }}; font-weight: {{ $p['weight'] }}; color: {{ $p['color'] }}; opacity: {{ $p['opacity'] }}; letter-spacing: {{ $p['rot'] !== 0 ? '0.04em' : '0' }};"
                                        title="{{ $p['word'] }} — {{ $p['freq'] }} penyebutan ({{ $p['sent'] === 'positive' ? '✓ Positif' : ($p['sent'] === 'negative' ? '✗ Negatif' : '● Netral') }})"
                                    >{{ $p['word'] }}</button>
                                @empty
                                    <p class="text-xs text-slate-400 italic text-center w-full absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2">Belum ada data untuk ditampilkan.</p>
                                @endforelse
                            </div>
                            
                            <!-- Premium Legend on white card background -->
                            <div class="flex items-center justify-between mt-3.5 relative z-10 px-1 border-t border-slate-100 pt-3">
                                <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">Sentimen AI</span>
                                <div class="flex items-center gap-4">
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500 shadow-sm shadow-emerald-500/30"></span>
                                        <span class="text-[10px] text-slate-650 font-bold">Positif</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-slate-400 shadow-sm shadow-slate-400/25"></span>
                                        <span class="text-[10px] text-slate-650 font-bold">Netral</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-rose-500 shadow-sm shadow-rose-500/30"></span>
                                        <span class="text-[10px] text-slate-650 font-bold">Negatif</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Category Grid Boxes -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm text-left flex flex-col justify-between h-[360px]">
                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider block mb-4">Penyebutan Berdasarkan Kategori</h3>
                            <div class="grid grid-cols-2 gap-3 flex-grow overflow-y-auto pr-1">
                                <!-- News -->
                                <div class="group relative rounded-2xl p-[1px] bg-gradient-to-br from-emerald-400/40 via-emerald-200/20 to-teal-400/30 hover:-translate-y-1 hover:shadow-[0_8px_30px_-6px_rgba(16,185,129,0.25)] transition-all duration-300 cursor-pointer">
                                    <div class="relative bg-white rounded-[15px] p-3.5 flex items-center gap-3.5 h-[80px] overflow-hidden">
                                        <!-- Subtle background glow -->
                                        <div class="absolute -right-6 -top-6 w-24 h-24 bg-emerald-400/[0.04] rounded-full blur-2xl group-hover:bg-emerald-400/[0.08] transition-all duration-500"></div>
                                        <!-- Icon -->
                                        <div class="relative w-[52px] h-[52px] rounded-xl flex items-center justify-center shrink-0 shadow-lg shadow-emerald-500/20 group-hover:shadow-emerald-500/35 group-hover:scale-105 transition-all duration-300" style="background: linear-gradient(135deg, #10b981, #14b8a6);">
                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6m-6 4h3"></path></svg>
                                        </div>
                                        <!-- Content -->
                                        <div class="relative flex flex-col text-left min-w-0">
                                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.12em] leading-none">Portal News</span>
                                            <span class="text-[26px] font-black text-slate-900 mt-1 leading-none tracking-tight">{{ $fmt($counts['sources']['News'] ?? 0) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <!-- Instagram -->
                                <div class="group relative rounded-2xl p-[1px] bg-gradient-to-br from-purple-400/40 via-pink-400/30 to-orange-300/30 hover:-translate-y-1 hover:shadow-[0_8px_30px_-6px_rgba(219,39,119,0.25)] transition-all duration-300 cursor-pointer">
                                    <div class="relative bg-white rounded-[15px] p-3.5 flex items-center gap-3.5 h-[80px] overflow-hidden">
                                        <div class="absolute -right-6 -top-6 w-24 h-24 bg-pink-400/[0.04] rounded-full blur-2xl group-hover:bg-pink-400/[0.08] transition-all duration-500"></div>
                                        <!-- Icon -->
                                        <div class="relative w-[52px] h-[52px] rounded-xl flex items-center justify-center shrink-0 shadow-lg shadow-pink-500/20 group-hover:shadow-pink-500/35 group-hover:scale-105 transition-all duration-300" style="background: linear-gradient(135deg, #833ab4, #fd1d1d, #fcb045);">
                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5" stroke-linecap="round"></line></svg>
                                        </div>
                                        <!-- Content -->
                                        <div class="relative flex flex-col text-left min-w-0">
                                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.12em] leading-none">Instagram</span>
                                            <span class="text-[26px] font-black text-slate-900 mt-1 leading-none tracking-tight">{{ $fmt($counts['sources']['Instagram'] ?? 0) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <!-- X / Twitter (Hidden) -->
                                @if(false)
                                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 flex flex-col justify-between h-[76px]">
                                    <span class="text-[10px] font-bold text-slate-700 flex items-center gap-1.5">
                                        <svg class="w-[16px] h-[16px] fill-current text-slate-800" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"></path></svg>
                                        <span>X / Twitter</span>
                                    </span>
                                    <h4 class="text-2xl font-black text-slate-850 mt-1">
                                        {{ $fmt($counts['sources']['Twitter'] ?? $counts['sources']['Twitter/X'] ?? 0) }}
                                    </h4>
                                </div>
                                @endif
                                <!-- TikTok -->
                                <div class="group relative rounded-2xl p-[1px] bg-gradient-to-br from-slate-700/40 via-pink-500/20 to-cyan-400/30 hover:-translate-y-1 hover:shadow-[0_8px_30px_-6px_rgba(15,23,42,0.25)] transition-all duration-300 cursor-pointer">
                                    <div class="relative bg-white rounded-[15px] p-3.5 flex items-center gap-3.5 h-[80px] overflow-hidden">
                                        <div class="absolute -right-6 -top-6 w-24 h-24 bg-slate-800/[0.03] rounded-full blur-2xl group-hover:bg-slate-800/[0.06] transition-all duration-500"></div>
                                        <!-- Icon -->
                                        <div class="relative w-[52px] h-[52px] rounded-xl flex items-center justify-center shrink-0 shadow-lg shadow-slate-900/25 group-hover:shadow-slate-900/40 group-hover:scale-105 transition-all duration-300" style="background: linear-gradient(135deg, #111111, #333333);">
                                            <svg class="w-6 h-6 fill-current text-white" viewBox="0 0 24 24"><path d="M12.525.01c1.306-.022 2.615-.011 3.921-.012.08 1.836 1.011 3.5 2.501 4.485.006 1.341-.004 2.683-.004 4.024-1.57-.107-3.067-.932-3.955-2.247-.008 2.827-.003 5.657-.005 8.486-.098 3.546-3.13 6.643-6.726 6.467-3.526-.067-6.523-3.18-6.241-6.722.215-3.327 3.012-6.104 6.347-5.992v4.06c-1.393-.16-2.775.76-3.085 2.112-.397 1.488.583 3.125 2.1 3.328 1.455.234 2.924-.766 3.14-2.224.048-2.617.02-5.237.03-7.856.002-3.834-.002-7.67.002-11.504z"></path></svg>
                                        </div>
                                        <!-- Content -->
                                        <div class="relative flex flex-col text-left min-w-0">
                                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.12em] leading-none">TikTok</span>
                                            <span class="text-[26px] font-black text-slate-900 mt-1 leading-none tracking-tight">{{ $fmt($counts['sources']['Tiktok'] ?? 0) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <!-- Facebook -->
                                <div class="group relative rounded-2xl p-[1px] bg-gradient-to-br from-blue-500/40 via-blue-300/20 to-indigo-400/30 hover:-translate-y-1 hover:shadow-[0_8px_30px_-6px_rgba(37,99,235,0.25)] transition-all duration-300 cursor-pointer">
                                    <div class="relative bg-white rounded-[15px] p-3.5 flex items-center gap-3.5 h-[80px] overflow-hidden">
                                        <div class="absolute -right-6 -top-6 w-24 h-24 bg-blue-400/[0.04] rounded-full blur-2xl group-hover:bg-blue-400/[0.08] transition-all duration-500"></div>
                                        <!-- Icon -->
                                        <div class="relative w-[52px] h-[52px] rounded-xl flex items-center justify-center shrink-0 shadow-lg shadow-blue-600/20 group-hover:shadow-blue-600/35 group-hover:scale-105 transition-all duration-300" style="background: linear-gradient(135deg, #1877f2, #3b82f6);">
                                            <svg class="w-6 h-6 fill-current text-white" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"></path></svg>
                                        </div>
                                        <!-- Content -->
                                        <div class="relative flex flex-col text-left min-w-0">
                                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.12em] leading-none">Facebook</span>
                                            <span class="text-[26px] font-black text-slate-900 mt-1 leading-none tracking-tight">{{ $counts['counts']['sources']['Facebook'] ?? $counts['sources']['Facebook'] ?? 0 }}</span>
                                        </div>
                                    </div>
                                </div>
                                <!-- YouTube (Hidden) -->
                                @if(false)
                                <div class="bg-red-500 border border-red-600 rounded-2xl p-4 flex flex-col justify-between h-[76px]">
                                    <span class="text-[10px] font-bold text-red-100 flex items-center gap-1.5">
                                        <svg class="w-[16px] h-[16px] fill-current text-white" viewBox="0 0 24 24"><path d="M23.498 6.163a3.003 3.003 0 00-2.11-2.11C19.518 3.545 12 3.545 12 3.545s-7.518 0-9.388.508a3.003 3.003 0 00-2.11 2.11C0 8.033 0 12 0 12s0 3.967.502 5.837a3.003 3.003 0 002.11 2.11c1.87.508 9.388.508 9.388.508s7.518 0 9.388-.508a3.003 3.003 0 002.11-2.11C24 15.967 24 12 24 12s0-3.967-.502-5.837zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"></path></svg>
                                        <span>YouTube</span>
                                    </span>
                                    <h4 class="text-2xl font-black text-white mt-1">
                                        {{ $fmt($counts['sources']['Youtube'] ?? 0) }}
                                    </h4>
                                </div>
                                @endif
                                <!-- Threads (Hidden) -->
                                @if(false)
                                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 flex flex-col justify-between h-[76px] col-span-2 md:col-span-1">
                                    <span class="text-[10px] font-bold text-slate-700 flex items-center gap-1.5">
                                        <svg class="w-[16px] h-[16px] fill-current text-slate-800" viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm3.845 14.887c-.57.848-1.396 1.272-2.482 1.272-1.077 0-1.895-.424-2.456-1.272-.258-.394-.438-.858-.538-1.393h5.992c-.09 1.055-.386 1.77-.972 2.215v.215zm-.972-4.06H9.123c.1-.536.28-.999.538-1.393.561-.848 1.38-1.272 2.456-1.272 1.086 0 1.912.424 2.482 1.272.257.394.437.857.537 1.393z"></path></svg>
                                        <span>Threads</span>
                                    </span>
                                    <h4 class="text-2xl font-black text-slate-900 mt-1">
                                        {{ $fmt($counts['sources']['Threads'] ?? 0) }}
                                    </h4>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Row 4: Network Analysis Diagram section -->
                    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider block">Network Analysis</h3>
                            <div class="flex items-center gap-1 bg-slate-100 p-0.5 rounded-lg border border-slate-200" x-data="{}" x-init="">
                                <span class="text-[9px] text-slate-500 font-bold px-2 py-0.5">Analisis Relasi</span>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-6 items-start" x-data="{ netTab: 'topik' }">
                            <!-- Visual Network SVG Graph (Left 2 cols) -->
                            <div class="col-span-2 border border-slate-200 rounded-2xl p-4 flex flex-col items-center justify-between bg-slate-50 h-[350px] relative overflow-hidden select-none shadow-sm"
                                 x-data="{
                                     scale: 1,
                                     translateX: 0,
                                     translateY: 0,
                                     isDragging: false,
                                     startX: 0,
                                     startY: 0,
                                     zoom(factor) {
                                         let newScale = this.scale * factor;
                                         if (newScale < 0.4) newScale = 0.4;
                                         if (newScale > 4) newScale = 4;
                                         this.scale = newScale;
                                     },
                                     startDrag(e) {
                                         if (e.target.closest('.no-drag')) return;
                                         this.isDragging = true;
                                         let clientX = e.clientX || (e.touches && e.touches[0].clientX);
                                         let clientY = e.clientY || (e.touches && e.touches[0].clientY);
                                         this.startX = clientX - this.translateX;
                                         this.startY = clientY - this.translateY;
                                     },
                                     drag(e) {
                                         if (!this.isDragging) return;
                                         let clientX = e.clientX || (e.touches && e.touches[0].clientX);
                                         let clientY = e.clientY || (e.touches && e.touches[0].clientY);
                                         this.translateX = clientX - this.startX;
                                         this.translateY = clientY - this.startY;
                                     },
                                     endDrag() {
                                         this.isDragging = false;
                                     },
                                     reset() {
                                         this.scale = 1;
                                         this.translateX = 0;
                                         this.translateY = 0;
                                     }
                                 }"
                                 @mousedown="startDrag"
                                 @mousemove="drag"
                                 @mouseup="endDrag"
                                 @mouseleave="endDrag"
                                 @touchstart="startDrag"
                                 @touchmove="drag"
                                 @touchend="endDrag"
                                 @wheel.prevent="zoom($event.deltaY < 0 ? 1.15 : 0.85)"
                                 :class="isDragging ? 'cursor-grabbing' : 'cursor-grab'"
                            >
                                <!-- Floating Controls (Zoom +/- / Reset) -->
                                <div class="absolute right-4 top-4 flex flex-col gap-1.5 no-drag z-10">
                                    <button @click="zoom(1.25)" class="w-8 h-8 bg-white hover:bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-center shadow-sm cursor-pointer transition" title="Zoom In">
                                        <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"></path></svg>
                                    </button>
                                    <button @click="zoom(0.8)" class="w-8 h-8 bg-white hover:bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-center shadow-sm cursor-pointer transition" title="Zoom Out">
                                        <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15"></path></svg>
                                    </button>
                                    <button @click="reset()" class="w-8 h-8 bg-white hover:bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-center shadow-sm cursor-pointer transition" title="Reset View">
                                        <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"></path></svg>
                                    </button>
                                </div>

                                @php
                                    // Positions for up to 5 nodes in a pentagon layout centered in 500x280 viewbox
                                    $nodePositions = [
                                        ['cx' => 250, 'cy' => 55],
                                        ['cx' => 410, 'cy' => 145],
                                        ['cx' => 350, 'cy' => 255],
                                        ['cx' => 150, 'cy' => 255],
                                        ['cx' => 90,  'cy' => 145],
                                    ];
                                    $networkNodes = array_slice($dynamicTopics, 0, 5);
                                    $nodeCount = count($networkNodes);
                                    $maxCount = $nodeCount > 0 ? max(array_column($networkNodes, 'count')) : 1;
                                @endphp
                                <svg class="w-full h-[270px] pointer-events-none" viewBox="0 0 500 280">
                                    <defs>
                                        <!-- Pola grid titik-titik seperti map board / Figma canvas -->
                                        <pattern id="mapGrid" width="20" height="20" patternUnits="userSpaceOnUse">
                                            <circle cx="2" cy="2" r="0.8" fill="#e2e8f0" />
                                        </pattern>
                                    </defs>
                                                               <!-- Transformable group containing the visual graph -->
                                    <g :style="`transform: translate(${translateX}px, ${translateY}px) scale(${scale}); transform-origin: center; transition: ${isDragging ? 'none' : 'transform 0.15s ease-out'}`" class="origin-center">
                                        <!-- Background grid map (luas agar tidak terpotong saat digeser) -->
                                        <rect x="-1000" y="-1000" width="3000" height="3000" fill="url(#mapGrid)" />
 
                                        <!-- Connecting Lines between all nodes (Rute Map style) -->
                                        @for($i = 0; $i < $nodeCount; $i++)
                                            @for($j = $i + 1; $j < $nodeCount; $j++)
                                                <line
                                                    x1="{{ $nodePositions[$i]['cx'] }}"
                                                    y1="{{ $nodePositions[$i]['cy'] }}"
                                                    x2="{{ $nodePositions[$j]['cx'] }}"
                                                    y2="{{ $nodePositions[$j]['cy'] }}"
                                                    stroke="#cbd5e1" stroke-width="1.2" stroke-dasharray="3,4"
                                                />
                                            @endfor
                                        @endfor
 
                                        <!-- Dynamic Nodes -->
                                        @foreach($networkNodes as $nIdx => $node)
                                            @php
                                                $nx = $nodePositions[$nIdx]['cx'];
                                                $ny = $nodePositions[$nIdx]['cy'];
                                                $radius = 28 + round(($node['count'] / $maxCount) * 16);
                                                $label = strlen($node['name']) > 11 ? substr($node['name'], 0, 11) . '…' : $node['name'];
                                                $nodeColor = $node['sentiment'] === 'Positif' ? '#059669' : ($node['sentiment'] === 'Negatif' ? '#dc2626' : '#475569');
                                            @endphp
                                            <g class="cursor-pointer">
                                                <!-- Outer Glow / Background Ring -->
                                                <circle cx="{{ $nx }}" cy="{{ $ny }}" r="{{ $radius + 4 }}" fill="{{ $nodeColor }}" opacity="0.06" />
                                                <!-- Node Circle -->
                                                <circle cx="{{ $nx }}" cy="{{ $ny }}" r="{{ $radius }}" fill="#ffffff" stroke="{{ $nodeColor }}" stroke-width="2.5" />
                                                <!-- Node Labels -->
                                                <text x="{{ $nx }}" y="{{ $ny - 3 }}" font-size="8.5" font-weight="900" text-anchor="middle" fill="#0f172a">{{ $label }}</text>
                                                <text x="{{ $nx }}" y="{{ $ny + 8 }}" font-size="6" font-weight="extrabold" text-anchor="middle" fill="{{ $nodeColor }}">{{ $node['count'] }} posts</text>
                                                
                                                <!-- Cluster Dot Accents -->
                                                <circle cx="{{ $nx - 10 }}" cy="{{ $ny + $radius - 4 }}" r="2.5" fill="{{ $nodeColor }}" opacity="0.5"/>
                                                <circle cx="{{ $nx + 10 }}" cy="{{ $ny + $radius - 6 }}" r="3" fill="{{ $nodeColor }}" opacity="0.7"/>
                                                <circle cx="{{ $nx + 2 }}" cy="{{ $ny + $radius - 1 }}" r="2" fill="{{ $nodeColor }}" opacity="0.4"/>
                                            </g>
                                        @endforeach
 
                                        @if($nodeCount === 0)
                                            <text x="250" y="140" font-size="11" font-weight="bold" text-anchor="middle" fill="#94a3b8">Belum ada data topik.</text>
                                        @endif
                                    </g>
                                </svg>
 
                                <!-- Bottom Legend inside visual graph card -->
                                <div class="flex flex-wrap items-center justify-between text-[10px] font-bold text-slate-500 border-t border-slate-200 pt-3 w-full px-1 no-drag bg-slate-50 z-10">
                                    <div class="flex items-center gap-4">
                                        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shadow-sm shadow-emerald-500/20"></span> <span>Positif</span></div>
                                        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-rose-500 shadow-sm shadow-rose-500/20"></span> <span>Negatif</span></div>
                                        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-slate-500 shadow-sm shadow-slate-500/20"></span> <span>Netral</span></div>
                                    </div>
                                    <div class="flex items-center gap-4 text-slate-400">
                                        <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full border-2 border-slate-400 bg-white inline-block"></span> <span>Topik</span></div>
                                        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-slate-400"></span> <span>Cluster</span></div>
                                        <div class="flex items-center gap-1.5"><span class="w-6 border-b-2 border-dashed border-slate-300"></span> <span>Koneksi</span></div>
                                    </div>
                                </div>
                            </div>
 
                            <!-- List Categories (Right 1 col) -->
                            <div class="space-y-4">
                                <!-- Pill Tabs Container -->
                                <div class="bg-slate-100 p-1 rounded-xl flex gap-1 border border-slate-200">
                                    <button @click="netTab = 'topik'" class="flex-1 py-2 text-[11px] font-bold rounded-lg text-center transition cursor-pointer" :class="netTab == 'topik' ? 'bg-[#1fa387] text-white shadow-sm' : 'text-slate-500 hover:text-slate-800 hover:bg-slate-200/50'">Topik</button>
                                    <button @click="netTab = 'aktor'" class="flex-1 py-2 text-[11px] font-bold rounded-lg text-center transition cursor-pointer" :class="netTab == 'aktor' ? 'bg-[#1fa387] text-white shadow-sm' : 'text-slate-500 hover:text-slate-800 hover:bg-slate-200/50'">Aktor</button>
                                    <button @click="netTab = 'sentimen'" class="flex-1 py-2 text-[11px] font-bold rounded-lg text-center transition cursor-pointer" :class="netTab == 'sentimen' ? 'bg-[#1fa387] text-white shadow-sm' : 'text-slate-500 hover:text-slate-800 hover:bg-slate-200/50'">Sentimen</button>
                                </div>
                                                     <!-- Topik Tab Content (Dynamic) -->
                                <div x-show="netTab == 'topik'" class="space-y-3 max-h-[285px] overflow-y-auto pr-1">
                                    @forelse($dynamicTopics as $topic)
                                        <div class="flex justify-between items-center text-xs p-3 border border-slate-100 rounded-xl bg-white shadow-[0_2px_8px_rgba(0,0,0,0.02)] hover:border-slate-200 transition">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center font-bold text-teal-700 text-xs shadow-sm">#</div>
                                                <div>
                                                    <h5 class="font-extrabold text-slate-800">{{ $topic['name'] }}</h5>
                                                    <p class="text-[10px] text-slate-500 font-medium">{{ $topic['count'] }} posts</p>
                                                </div>
                                            </div>
                                            @if($topic['sentiment'] == 'Positif')
                                                <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-100 rounded-lg text-[9px] font-black uppercase tracking-wider">Positif</span>
                                            @elseif($topic['sentiment'] == 'Negatif')
                                                <span class="px-2.5 py-1 bg-rose-50 text-rose-700 border border-rose-100 rounded-lg text-[9px] font-black uppercase tracking-wider">Negatif</span>
                                            @else
                                                <span class="px-2.5 py-1 bg-slate-55 text-slate-700 border border-slate-200 rounded-lg text-[9px] font-black uppercase tracking-wider">Netral</span>
                                            @endif
                                        </div>
                                    @empty
                                        <p class="text-xs text-slate-400 p-8 text-center italic">Tidak ada topik ditemukan.</p>
                                    @endforelse
                                </div>

                                <!-- Aktor Tab Content (Dynamic) -->
                                <div x-show="netTab == 'aktor'" class="space-y-3 max-h-[285px] overflow-y-auto pr-1" style="display: none;">
                                    @forelse($dynamicActors as $actor)
                                        <div class="flex justify-between items-center text-xs p-3 border border-slate-100 rounded-xl bg-white shadow-[0_2px_8px_rgba(0,0,0,0.02)] hover:border-slate-200 transition">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center font-bold text-indigo-700 text-xs shadow-sm">@</div>
                                                <div>
                                                    <h5 class="font-extrabold text-slate-800">{{ $actor['handle'] }}</h5>
                                                    <p class="text-[10px] text-slate-500 font-medium">{{ $actor['count'] }} mentions</p>
                                                </div>
                                            </div>
                                            @if($actor['sentiment'] == 'Positif')
                                                <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-100 rounded-lg text-[9px] font-black uppercase tracking-wider">Positif</span>
                                            @elseif($actor['sentiment'] == 'Negatif')
                                                <span class="px-2.5 py-1 bg-rose-50 text-rose-700 border border-rose-100 rounded-lg text-[9px] font-black uppercase tracking-wider">Negatif</span>
                                            @else
                                                <span class="px-2.5 py-1 bg-slate-55 text-slate-700 border border-slate-200 rounded-lg text-[9px] font-black uppercase tracking-wider">Netral</span>
                                            @endif
                                        </div>
                                    @empty
                                        <p class="text-xs text-slate-400 p-8 text-center italic">Tidak ada aktor ditemukan.</p>
                                    @endforelse
                                </div>

                                <!-- Sentimen Tab Content (Dynamic) -->
                                <div x-show="netTab == 'sentimen'" class="space-y-3 max-h-[285px] overflow-y-auto pr-1" style="display: none;">
                                    @foreach($dynamicSentiments as $sentInfo)
                                        <div class="flex justify-between items-center text-xs p-3 border border-slate-100 rounded-xl bg-white shadow-[0_2px_8px_rgba(0,0,0,0.02)] hover:border-slate-200 transition">
                                            <div class="flex items-center gap-3">
                                                @if($sentInfo['sentiment'] == 'Positif')
                                                    <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center font-bold text-emerald-700 text-xs shadow-sm">✓</div>
                                                @elseif($sentInfo['sentiment'] == 'Negatif')
                                                    <div class="w-8 h-8 rounded-lg bg-rose-50 flex items-center justify-center font-bold text-rose-700 text-xs shadow-sm">✗</div>
                                                @else
                                                    <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center font-bold text-slate-700 text-xs shadow-sm">●</div>
                                                @endif
                                                <div>
                                                    <h5 class="font-extrabold text-slate-800">{{ $sentInfo['name'] }}</h5>
                                                    <p class="text-[10px] text-slate-500 font-medium">{{ $sentInfo['ratio'] }}% dari jangkauan</p>
                                                </div>
                                            </div>
                                            @if($sentInfo['sentiment'] == 'Positif')
                                                <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-100 rounded-lg text-[9px] font-black uppercase tracking-wider">Positif</span>
                                            @elseif($sentInfo['sentiment'] == 'Negatif')
                                                <span class="px-2.5 py-1 bg-rose-50 text-rose-700 border border-rose-100 rounded-lg text-[9px] font-black uppercase tracking-wider">Negatif</span>
                                            @else
                                                <span class="px-2.5 py-1 bg-slate-55 text-slate-700 border border-slate-200 rounded-lg text-[9px] font-black uppercase tracking-wider">Netral</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 5: Popular vs New Mentions side by side -->
                    <div class="grid grid-cols-2 gap-6">
                        <!-- Left Column: Penyebutan Populer -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm text-left space-y-4">
                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Penyebutan Populer</h3>
                            <div class="space-y-3">
                                @php
                                    $popQuery = $this->projectArticlesQuery();
                                    $this->applyActiveFilters($popQuery);
                                    $popArticles = $popQuery->with('aiAnalysisResult')->whereHas('aiAnalysisResult', function($q) {
                                        $q->where('sentiment', 'positive')
                                          ->where('analysis_status', 'success')
                                          ->where('reach_method', 'ai_reader_estimate_v1');
                                    })->take(3)->get();
                                @endphp
                                @foreach($popArticles as $popArt)
                                    @php
                                        $accentColor = '#059669'; // positive emerald-600
                                        if ($this->getValidAiResult($popArt)?->sentiment == 'negative') $accentColor = '#dc2626';
                                        elseif ($this->getValidAiResult($popArt)?->sentiment == 'neutral') $accentColor = '#475569';
                                        $popReachDisp = $this->getProjectReachDisplayData($popArt);
                                    @endphp
                                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-[0_2px_12px_rgba(0,0,0,0.02)] flex flex-col justify-between transition-all hover:shadow-[0_6px_18px_rgba(0,0,0,0.04)] border-l-4 h-[290px]" style="border-left-color: {{ $accentColor }}">
                                        <!-- Top header row -->
                                        <div class="flex items-center gap-3.5 flex-shrink-0">
                                            @php
                                                $srcLower = strtolower($popArt->source_name);
                                                if (str_contains($srcLower, 'instagram') || $srcLower === 'ig') {
                                                    $logoBg = 'bg-gradient-to-br from-purple-600 via-pink-500 to-orange-400';
                                                } elseif (str_contains($srcLower, 'tiktok') || $srcLower === 'tk') {
                                                    $logoBg = 'bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800';
                                                } elseif (str_contains($srcLower, 'facebook') || $srcLower === 'fb') {
                                                    $logoBg = 'bg-gradient-to-br from-blue-600 to-blue-700';
                                                } else {
                                                    $logoBg = 'bg-transparent';
                                                }
                                            @endphp
                                            <div class="w-10 h-10 rounded-xl flex items-center justify-center overflow-hidden shadow-sm flex-shrink-0 {{ $logoBg }}">
                                                @if(str_contains($srcLower, 'facebook') || $srcLower === 'fb')
                                                    <svg class="w-5 h-5 fill-current text-white" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"></path></svg>
                                                @elseif(str_contains($srcLower, 'instagram') || $srcLower === 'ig')
                                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5" stroke-linecap="round"></line></svg>
                                                @elseif(str_contains($srcLower, 'tiktok') || $srcLower === 'tk')
                                                    <svg class="w-5 h-5 fill-current text-white" viewBox="0 0 24 24"><path d="M12.525.01c1.306-.022 2.615-.011 3.921-.012.08 1.836 1.011 3.5 2.501 4.485.006 1.341-.004 2.683-.004 4.024-1.57-.107-3.067-.932-3.955-2.247-.008 2.827-.003 5.657-.005 8.486-.098 3.546-3.13 6.643-6.726 6.467-3.526-.067-6.523-3.18-6.241-6.722.215-3.327 3.012-6.104 6.347-5.992v4.06c-1.393-.16-2.775.76-3.085 2.112-.397 1.488.583 3.125 2.1 3.328 1.455.234 2.924-.766 3.14-2.224.048-2.617.02-5.237.03-7.856.002-3.834-.002-7.67.002-11.504z"></path></svg>
                                                @else
                                                    <div class="relative w-full h-full flex items-center justify-center" x-data="{ imgFailed: false }">
                                                        <img x-show="!imgFailed" 
                                                             src="{{ $this->resolveArticleLogoUrl($popArt) }}" 
                                                             x-on:error="imgFailed = true"
                                                             class="w-full h-full object-cover" 
                                                             alt="{{ $popArt->source_name }}" />
                                                        <div x-show="imgFailed" class="absolute inset-0 w-full h-full bg-transparent flex items-center justify-center" style="display: none;">
                                                            <svg class="w-5 h-5 text-[#1fa387]" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path></svg>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <h4 class="text-sm font-extrabold text-slate-800 truncate">
                                                    @if(strtolower($popArt->source_name) == 'twitter')
                                                        x.com
                                                    @elseif(str_contains($popArt->source_name, '.'))
                                                        {{ strtolower($popArt->source_name) }}
                                                    @else
                                                        {{ strtolower($popArt->source_name) }}.com
                                                    @endif
                                                </h4>
                                                <p class="text-[10px] font-semibold text-slate-400 mt-0.5">{{ $popArt->published_at ? \Carbon\Carbon::parse($popArt->published_at)->format('d M Y, H:i') : 'Baru saja' }}</p>
                                            </div>
                                        </div>
                                        
                                        <!-- Content text (Excerpts) -->
                                        <div class="flex-grow flex items-center my-3 text-left">
                                            <p class="text-sm text-slate-700 leading-relaxed font-semibold line-clamp-4">{{ $this->formatArticleExcerpt($popArt, 160) }}</p>
                                        </div>

                                        <!-- Footer tags & details button -->
                                        <div class="flex items-center justify-between pt-3 border-t border-slate-100 flex-shrink-0">
                                            <div class="flex items-center gap-2 min-w-0">
                                                @if($this->getValidAiResult($popArt)?->sentiment == 'positive')
                                                    <span class="px-2.5 py-1 text-[10px] font-black bg-emerald-50 text-emerald-700 rounded-lg border border-emerald-100 uppercase tracking-wide flex-shrink-0">Positif</span>
                                                @elseif($this->getValidAiResult($popArt)?->sentiment == 'negative')
                                                    <span class="px-2.5 py-1 text-[10px] font-black bg-rose-50 text-rose-700 rounded-lg border border-rose-100 uppercase tracking-wide flex-shrink-0">Negatif</span>
                                                @else
                                                    <span class="px-2.5 py-1 text-[10px] font-black bg-slate-50 text-slate-700 rounded-lg border border-slate-200 uppercase tracking-wide flex-shrink-0">Netral</span>
                                                @endif
                                                <span class="px-2.5 py-1 text-[10px] font-bold bg-slate-150 text-slate-500 rounded-lg border border-slate-200 uppercase truncate max-w-[100px]" title="{{ $projectName }}">{{ $projectName }}</span>
                                            </div>
                                            @if($popArt->url)
                                                <button 
                                                    type="button"
                                                    @click="openDetail(
                                                        {{ Js::from($popArt->title) }},
                                                        {{ Js::from($popArt->source_name) }},
                                                        {{ Js::from($popArt->published_at ? \Carbon\Carbon::parse($popArt->published_at)->format('d M Y, H:i') : 'Just now') }},
                                                        {{ Js::from($popArt->url) }},
                                                        {{ Js::from($this->cleanNoiseText($popArt->content)) }},
                                                        {{ Js::from($this->getValidAiResult($popArt)?->summary ?? 'Belum ada analisis ringkasan AI.') }},
                                                        {{ Js::from($this->getValidAiResult($popArt)?->recommendation ?? 'Tidak ada rekomendasi khusus.') }},
                                                        {{ Js::from($this->getValidAiResult($popArt)?->sentiment) }},
                                                        {{ Js::from($popArt->category) }},
                                                        {{ Js::from($popReachDisp['hasOfficialProjectReach'] ? number_format($popReachDisp['reachValue'], 0, ',', '.') : ($popReachDisp['hasReadableAiReach'] ? 'Belum tersedia' : 'Belum dinilai AI')) }},
                                                        {{ Js::from($popReachDisp['hasOfficialProjectReach'] ? $popReachDisp['levelLabel'] : ($popReachDisp['hasReadableAiReach'] ? 'Belum tersedia' : '')) }},
                                                        {{ Js::from($popReachDisp['hasOfficialProjectReach'] ? $popReachDisp['scoreValue'] . '/10' : ($popReachDisp['hasReadableAiReach'] ? 'Belum tersedia' : 'Belum dinilai AI')) }},
                                                        {{ Js::from($popArt->published_at ? \Carbon\Carbon::parse($popArt->published_at)->format('d/m/y') : 'Baru saja') }}
                                                     )" 
                                                    class="px-3.5 py-2 border border-slate-200 text-slate-700 hover:bg-slate-50 font-black text-xs rounded-xl transition flex items-center gap-1.5 bg-white cursor-pointer hover:border-slate-300 flex-shrink-0"
                                                >
                                                    <span>Detail</span>
                                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Right Column: Penyebutan Terbaru -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm text-left space-y-4">
                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Penyebutan Terbaru</h3>
                            <div class="space-y-3">
                                @php
                                    $newQuery = $this->projectArticlesQuery();
                                    $this->applyActiveFilters($newQuery);
                                    $newArticles = $newQuery->with('aiAnalysisResult')->orderBy('published_at', 'desc')->take(3)->get();
                                @endphp
                                @foreach($newArticles as $newArt)
                                    @php
                                        $accentColor = '#475569'; // neutral slate-600
                                        if ($this->getValidAiResult($newArt)?->sentiment == 'positive') $accentColor = '#059669';
                                        elseif ($this->getValidAiResult($newArt)?->sentiment == 'negative') $accentColor = '#dc2626';
                                        $newReachDisp = $this->getProjectReachDisplayData($newArt);
                                    @endphp
                                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-[0_2px_12px_rgba(0,0,0,0.02)] flex flex-col justify-between transition-all hover:shadow-[0_6px_18px_rgba(0,0,0,0.04)] border-l-4 h-[290px]" style="border-left-color: {{ $accentColor }}">
                                        <!-- Top header row -->
                                        <div class="flex items-center gap-3.5 flex-shrink-0">
                                            @php
                                                $srcLowerNew = strtolower($newArt->source_name);
                                                if (str_contains($srcLowerNew, 'instagram') || $srcLowerNew === 'ig') {
                                                    $logoBgNew = 'bg-gradient-to-br from-purple-600 via-pink-500 to-orange-400';
                                                } elseif (str_contains($srcLowerNew, 'tiktok') || $srcLowerNew === 'tk') {
                                                    $logoBgNew = 'bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800';
                                                } elseif (str_contains($srcLowerNew, 'facebook') || $srcLowerNew === 'fb') {
                                                    $logoBgNew = 'bg-gradient-to-br from-blue-600 to-blue-700';
                                                } else {
                                                    $logoBgNew = 'bg-transparent';
                                                }
                                            @endphp
                                            <div class="w-10 h-10 rounded-xl flex items-center justify-center overflow-hidden shadow-sm flex-shrink-0 {{ $logoBgNew }}">
                                                @if(str_contains($srcLowerNew, 'facebook') || $srcLowerNew === 'fb')
                                                    <svg class="w-5 h-5 fill-current text-white" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"></path></svg>
                                                @elseif(str_contains($srcLowerNew, 'instagram') || $srcLowerNew === 'ig')
                                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5" stroke-linecap="round"></line></svg>
                                                @elseif(str_contains($srcLowerNew, 'tiktok') || $srcLowerNew === 'tk')
                                                    <svg class="w-5 h-5 fill-current text-white" viewBox="0 0 24 24"><path d="M12.525.01c1.306-.022 2.615-.011 3.921-.012.08 1.836 1.011 3.5 2.501 4.485.006 1.341-.004 2.683-.004 4.024-1.57-.107-3.067-.932-3.955-2.247-.008 2.827-.003 5.657-.005 8.486-.098 3.546-3.13 6.643-6.726 6.467-3.526-.067-6.523-3.18-6.241-6.722.215-3.327 3.012-6.104 6.347-5.992v4.06c-1.393-.16-2.775.76-3.085 2.112-.397 1.488.583 3.125 2.1 3.328 1.455.234 2.924-.766 3.14-2.224.048-2.617.02-5.237.03-7.856.002-3.834-.002-7.67.002-11.504z"></path></svg>
                                                @else
                                                    <div class="relative w-full h-full flex items-center justify-center" x-data="{ imgFailedNew: false }">
                                                        <img x-show="!imgFailedNew" 
                                                             src="{{ $this->resolveArticleLogoUrl($newArt) }}" 
                                                             x-on:error="imgFailedNew = true"
                                                             class="w-full h-full object-cover" 
                                                             alt="{{ $newArt->source_name }}" />
                                                        <div x-show="imgFailedNew" class="absolute inset-0 w-full h-full bg-transparent flex items-center justify-center" style="display: none;">
                                                            <svg class="w-5 h-5 text-[#1fa387]" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path></svg>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <h4 class="text-sm font-extrabold text-slate-800 truncate">
                                                    @if(strtolower($newArt->source_name) == 'twitter')
                                                        x.com
                                                    @elseif(str_contains($newArt->source_name, '.'))
                                                        {{ strtolower($newArt->source_name) }}
                                                    @else
                                                        {{ strtolower($newArt->source_name) }}.com
                                                    @endif
                                                </h4>
                                                <p class="text-[10px] font-semibold text-slate-400 mt-0.5">{{ $newArt->published_at ? \Carbon\Carbon::parse($newArt->published_at)->format('d M Y, H:i') : 'Baru saja' }}</p>
                                            </div>
                                        </div>
                                        
                                        <!-- Content text (Excerpts) -->
                                        <div class="flex-grow flex items-center my-3 text-left">
                                            <p class="text-sm text-slate-700 leading-relaxed font-semibold line-clamp-4">{{ $this->formatArticleExcerpt($newArt, 160) }}</p>
                                        </div>

                                        <!-- Footer tags & details button -->
                                        <div class="flex items-center justify-between pt-3 border-t border-slate-100 flex-shrink-0">
                                            <div class="flex items-center gap-2 min-w-0">
                                                @if($this->getValidAiResult($newArt)?->sentiment == 'positive')
                                                    <span class="px-2.5 py-1 text-[10px] font-black bg-emerald-50 text-emerald-700 rounded-lg border border-emerald-100 uppercase tracking-wide flex-shrink-0">Positif</span>
                                                @elseif($this->getValidAiResult($newArt)?->sentiment == 'negative')
                                                    <span class="px-2.5 py-1 text-[10px] font-black bg-rose-50 text-rose-700 rounded-lg border border-rose-100 uppercase tracking-wide flex-shrink-0">Negatif</span>
                                                @else
                                                    <span class="px-2.5 py-1 text-[10px] font-black bg-slate-50 text-slate-700 rounded-lg border border-slate-200 uppercase tracking-wide flex-shrink-0">Netral</span>
                                                @endif
                                                <span class="px-2.5 py-1 text-[10px] font-bold bg-slate-150 text-slate-500 rounded-lg border border-slate-200 uppercase truncate max-w-[100px]" title="{{ $projectName }}">{{ $projectName }}</span>
                                            </div>
                                            @if($newArt->url)
                                                <button 
                                                    type="button"
                                                    @click="openDetail(
                                                        {{ Js::from($newArt->title) }},
                                                        {{ Js::from($newArt->source_name) }},
                                                        {{ Js::from($newArt->published_at ? \Carbon\Carbon::parse($newArt->published_at)->format('d M Y, H:i') : 'Just now') }},
                                                        {{ Js::from($newArt->url) }},
                                                        {{ Js::from($this->cleanNoiseText($newArt->content)) }},
                                                        {{ Js::from($this->getValidAiResult($newArt)?->summary ?? 'Belum ada analisis ringkasan AI.') }},
                                                        {{ Js::from($this->getValidAiResult($newArt)?->recommendation ?? 'Tidak ada rekomendasi khusus.') }},
                                                        {{ Js::from($this->getValidAiResult($newArt)?->sentiment) }},
                                                        {{ Js::from($newArt->category) }},
                                                        {{ Js::from($newReachDisp['hasOfficialProjectReach'] ? number_format($newReachDisp['reachValue'], 0, ',', '.') : ($newReachDisp['hasReadableAiReach'] ? 'Belum tersedia' : 'Belum dinilai AI')) }},
                                                        {{ Js::from($newReachDisp['hasOfficialProjectReach'] ? $newReachDisp['levelLabel'] : ($newReachDisp['hasReadableAiReach'] ? 'Belum tersedia' : '')) }},
                                                        {{ Js::from($newReachDisp['hasOfficialProjectReach'] ? $newReachDisp['scoreValue'] . '/10' : ($newReachDisp['hasReadableAiReach'] ? 'Belum tersedia' : 'Belum dinilai AI')) }},
                                                        {{ Js::from($newArt->published_at ? \Carbon\Carbon::parse($newArt->published_at)->format('d/m/y') : 'Baru saja') }}
                                                    )" 
                                                    class="px-3.5 py-2 border border-slate-200 text-slate-700 hover:bg-slate-50 font-black text-xs rounded-xl transition flex items-center gap-1.5 bg-white cursor-pointer hover:border-slate-300 flex-shrink-0"
                                                >
                                                    <span>Detail</span>
                                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </section>
            @elseif($this->isTab('katakunci'))
                <!-- TAB 3: Kata Kunci Configuration Page -->
                <section class="flex-1 min-w-0 space-y-6">
                    <div class="flex items-center justify-between text-left">
                        <div>
                            <h2 class="text-xl font-bold text-slate-900 mb-0.5 font-sans flex items-center gap-2"><span class="material-symbols-outlined text-[#1fa387] text-[22px]">vpn_key</span>Pengaturan dan Analisis Kata Kunci</h2>
                            <p class="text-xs text-slate-500">Pantau performa tren pencarian untuk proyek <span class="text-[#1fa387] font-bold uppercase">{{ $projectName }}</span></p>
                        </div>
                    </div>

                    @if (session()->has('message'))
                        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-2xl text-xs font-bold text-left flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span>{{ session('message') }}</span>
                        </div>
                    @endif

                    <!-- Manajemen Kata Kunci Card -->
                    <div class="bg-white rounded-3xl border border-slate-200 p-8 shadow-sm text-left">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-sm font-bold text-slate-800 flex items-center gap-1.5"><span class="material-symbols-outlined text-[18px] text-[#1fa387]">vpn_key</span>Manajemen Kata Kunci</h3>
                            @if($this->isAdmin())
                                <button 
                                    type="button"
                                    wire:click="$set('showAddKeywordModal', true)"
                                    class="bg-[#1fa387] hover:bg-[#1fa387]/90 text-white font-bold text-xs px-5 py-2.5 rounded-xl transition flex items-center gap-1.5 cursor-pointer"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                                    <span>Tambah Kata Kunci</span>
                                </button>
                            @endif
                        </div>

                        <!-- Top filter row -->
                        <div class="flex gap-2 mb-6">
                            <div class="relative flex-grow max-w-xs flex gap-2">
                                <input 
                                    type="text" 
                                    wire:model="keywordSearch" 
                                    placeholder="Cari kata kunci..." 
                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-xs font-semibold text-slate-800 placeholder-slate-400 focus:outline-none focus:border-[#1fa387] focus:bg-white transition"
                                />
                                <button class="bg-[#1fa387] hover:bg-[#1fa387]/90 text-white p-2 rounded-xl flex items-center justify-center cursor-pointer transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="overflow-hidden border border-slate-100 rounded-2xl">
                            <table class="w-full border-collapse text-left text-xs text-slate-700">
                                <thead class="bg-slate-50/75 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                    <tr>
                                        <th class="px-6 py-3.5 font-bold flex items-center gap-1">
                                            <span>Kata Kunci</span>
                                            <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path></svg>
                                        </th>
                                        <th class="px-6 py-3.5 font-bold">
                                            <div class="flex items-center gap-1">
                                                <span>Total Pencarian</span>
                                                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path></svg>
                                            </div>
                                        </th>
                                        <th class="px-6 py-3.5 font-bold">TREN</th>
                                        <th class="px-6 py-3.5 font-bold text-center">OPSI</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @php
                                        $filteredTable = array_filter($keywordsTable, function($item) {
                                            return empty($this->keywordSearch) || str_contains(strtolower($item['keyword']), strtolower($this->keywordSearch));
                                        });
                                    @endphp
                                    @forelse($filteredTable as $idx => $row)
                                        @php
                                            $cleanKw = trim(str_replace('#', '', $row['keyword']));
                                        @endphp
                                        <tr 
                                            wire:key="kw-row-{{ $cleanKw }}"
                                            wire:click="toggleKeyword('{{ $cleanKw }}')"
                                            class="hover:bg-[#1fa387]/5 transition cursor-pointer {{ $selectedKeyword === $cleanKw ? 'bg-[#1fa387]/10' : '' }}"
                                        >
                                            <td class="px-6 py-4 font-bold {{ $selectedKeyword === $cleanKw ? 'text-[#1fa387]' : 'text-slate-900' }}">{{ $row['keyword'] }}</td>
                                            <td class="px-6 py-4 font-bold text-slate-700">{{ number_format($row['total']) }}</td>
                                            <td class="px-6 py-4">
                                                @php
                                                    $trendColor = match($row['trend']) {
                                                        'Naik'  => 'text-emerald-600',
                                                        'Turun' => 'text-rose-500',
                                                        default => 'text-slate-400',
                                                    };
                                                    $trendIcon = match($row['trend']) {
                                                        'Naik'  => 'M5 10l7-7m0 0l7 7m-7-7v18',
                                                        'Turun' => 'M19 14l-7 7m0 0l-7-7m7 7V3',
                                                        default => 'M5 12h14',
                                                    };
                                                @endphp
                                                <span class="inline-flex items-center gap-1 font-bold {{ $trendColor }}">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="{{ $trendIcon }}"></path></svg>
                                                    <span>{{ $row['trend'] }}</span>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                @if($this->isAdmin())
                                                    <button 
                                                        type="button"
                                                        wire:click="removeKeywordTable({{ $idx }})"
                                                        class="text-rose-500 hover:text-rose-700 transition cursor-pointer"
                                                    >
                                                        <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    </button>
                                                @else
                                                    <span class="text-[10px] text-slate-400 font-medium">Read-only</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-6 py-8 text-center text-slate-400 italic">Tidak ada data kata kunci ditemukan.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Footer / Pagination row matching layout exactly -->
                        <div class="flex items-center justify-between mt-6 text-xs text-slate-400 font-semibold">
                            <span>Menampilkan 1-{{ count($filteredTable) }} dari {{ count($filteredTable) }} data</span>
                            <div class="flex items-center gap-1.5">
                                <button class="px-2 py-1 bg-slate-50 border border-slate-200 rounded-lg text-slate-400 hover:bg-slate-100 transition cursor-pointer">«</button>
                                <button class="px-2 py-1 bg-slate-50 border border-slate-200 rounded-lg text-slate-400 hover:bg-slate-100 transition cursor-pointer">‹</button>
                                <span class="w-6 h-6 bg-emerald-600 text-white rounded-lg flex items-center justify-center font-bold">1</span>
                                <button class="px-2 py-1 bg-slate-50 border border-slate-200 rounded-lg text-slate-400 hover:bg-slate-100 transition cursor-pointer">›</button>
                                <button class="px-2 py-1 bg-slate-50 border border-slate-200 rounded-lg text-slate-400 hover:bg-slate-100 transition cursor-pointer">»</button>
                            </div>
                        </div>
                    </div>

                    <!-- Grafik Tren Card -->
                    <div class="bg-white rounded-3xl border border-slate-200 p-8 shadow-sm text-left space-y-6" x-data="{ trendInterval: 'harian', trendMetric: 'penyebutan', activePoint: null }">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-sm font-bold text-slate-800 flex items-center gap-1.5"><span class="material-symbols-outlined text-[18px] text-[#1fa387]">show_chart</span>Grafik Tren</h3>
                                <p class="text-xs text-slate-400 mt-1 flex items-center flex-wrap gap-1">
                                    Pantau performa kata kunci 
                                    <strong class="text-[#1fa387] ml-1">{{ $selectedKeyword ? strtoupper($selectedKeyword) : 'Semua Kata Kunci' }}</strong>
                                    @if($selectedKeyword)
                                        <button wire:click="toggleKeyword('{{ $selectedKeyword }}')" class="ml-2 px-2 py-0.5 rounded-full bg-red-50 text-red-500 hover:bg-red-100 transition text-[9px] font-bold uppercase tracking-wide">
                                            Hapus Filter
                                        </button>
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <!-- Interval Button Toggle -->
                                <div class="bg-slate-100 p-0.5 rounded-full flex gap-1 text-[10px] font-bold text-slate-500">
                                    <button @click="trendInterval = 'harian'" class="px-3.5 py-1 rounded-full transition cursor-pointer" :class="trendInterval == 'harian' ? 'bg-blue-600 text-white' : 'hover:text-slate-800'">Harian</button>
                                    <button @click="trendInterval = 'mingguan'" class="px-3.5 py-1 rounded-full transition cursor-pointer" :class="trendInterval == 'mingguan' ? 'bg-blue-600 text-white' : 'hover:text-slate-800'">Mingguan</button>
                                    <button @click="trendInterval = 'bulanan'" class="px-3.5 py-1 rounded-full transition cursor-pointer" :class="trendInterval == 'bulanan' ? 'bg-blue-600 text-white' : 'hover:text-slate-800'">Bulanan</button>
                                </div>
                                <!-- Metric Button Toggle -->
                                <div class="bg-slate-100 p-0.5 rounded-full flex gap-1 text-[10px] font-bold text-slate-500">
                                    <button @click="trendMetric = 'penyebutan'" class="px-3.5 py-1 rounded-full transition cursor-pointer" :class="trendMetric == 'penyebutan' ? 'bg-[#1fa387] text-white' : 'hover:text-slate-800'">Penyebutan</button>
                                    <button @click="trendMetric = 'jangkauan'" class="px-3.5 py-1 rounded-full transition cursor-pointer" :class="trendMetric == 'jangkauan' ? 'bg-[#1fa387] text-white' : 'hover:text-slate-800'">Jangkauan</button>
                                    <button @click="trendMetric = 'sentimen'" class="px-3.5 py-1 rounded-full transition cursor-pointer" :class="trendMetric == 'sentimen' ? 'bg-[#1fa387] text-white' : 'hover:text-slate-800'">Sentimen</button>
                                </div>
                            </div>
                        </div>

                        <!-- Custom trend vector line curve inside SVG -->
                        <div class="relative w-full h-[200px] bg-gradient-to-b from-emerald-50/10 to-transparent rounded-2xl p-2 border border-slate-50">
                            <svg class="w-full h-full" viewBox="0 0 1000 170" preserveAspectRatio="none">
                                <!-- Gradient fill under path -->
                                <defs>
                                    <linearGradient id="trendCardGrad" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#1fa387" stop-opacity="0.22"/>
                                        <stop offset="100%" stop-color="#1fa387" stop-opacity="0.0"/>
                                    </linearGradient>
                                    <filter id="trendShadow" x="-5%" y="-5%" width="110%" height="110%">
                                        <feDropShadow dx="0" dy="4" stdDeviation="4" flood-color="#1fa387" flood-opacity="0.15" />
                                    </filter>
                                    <filter id="posShadow" x="-5%" y="-5%" width="110%" height="110%">
                                        <feDropShadow dx="0" dy="4" stdDeviation="4" flood-color="#10b981" flood-opacity="0.12" />
                                    </filter>
                                    <filter id="neuShadow" x="-5%" y="-5%" width="110%" height="110%">
                                        <feDropShadow dx="0" dy="4" stdDeviation="4" flood-color="#94a3b8" flood-opacity="0.12" />
                                    </filter>
                                    <filter id="negShadow" x="-5%" y="-5%" width="110%" height="110%">
                                        <feDropShadow dx="0" dy="4" stdDeviation="4" flood-color="#f43f5e" flood-opacity="0.12" />
                                    </filter>
                                </defs>

                                <!-- Horizontal Grid Lines -->
                                <line x1="40" y1="30" x2="960" y2="30" stroke="#f1f5f9" stroke-width="1" stroke-dasharray="4 4"/>
                                <line x1="40" y1="85" x2="960" y2="85" stroke="#f1f5f9" stroke-width="1" stroke-dasharray="4 4"/>
                                <line x1="40" y1="140" x2="960" y2="140" stroke="#e2e8f0" stroke-width="1"/>
                                
                                @php
                                    // Pre-compute combinations for penyebutan and jangkauan
                                    $allPts = [];
                                    foreach (['penyebutan', 'jangkauan'] as $m) {
                                        $allPts[$m] = [
                                            'harian'   => $this->getTrendPoints('harian', $m),
                                            'mingguan' => $this->getTrendPoints('mingguan', $m),
                                            'bulanan'  => $this->getTrendPoints('bulanan', $m),
                                        ];
                                    }

                                    // Pre-compute three sentiment lines with shared scaling maximum
                                    $sentimenPts = [];
                                    foreach (['harian', 'mingguan', 'bulanan'] as $iv) {
                                        $rawPos = $this->getTrendPoints($iv, 'sentimen_positif');
                                        $rawNeu = $this->getTrendPoints($iv, 'sentimen_netral');
                                        $rawNeg = $this->getTrendPoints($iv, 'sentimen_negatif');

                                        $maxPos = collect($rawPos)->max('count');
                                        $maxNeu = collect($rawNeu)->max('count');
                                        $maxNeg = collect($rawNeg)->max('count');
                                        $sharedMax = max(1, $maxPos, $maxNeu, $maxNeg);

                                        $sentimenPts[$iv] = [
                                            'positif' => $this->getTrendPoints($iv, 'sentimen_positif', $sharedMax),
                                            'netral'  => $this->getTrendPoints($iv, 'sentimen_netral', $sharedMax),
                                            'negatif' => $this->getTrendPoints($iv, 'sentimen_negatif', $sharedMax),
                                        ];
                                    }

                                    // Smooth Cubic Bezier Spline path generator
                                    $getCurvePath = function($pts) {
                                        if (empty($pts)) return 'M 50 140';
                                        $d = 'M ' . $pts[0]['x'] . ' ' . $pts[0]['y'];
                                        $count = count($pts);
                                        if ($count < 2) return $d;
                                        if ($count == 2) {
                                            return $d . ' L ' . $pts[1]['x'] . ' ' . $pts[1]['y'];
                                        }
                                        for ($i = 0; $i < $count - 1; $i++) {
                                            $p0 = $pts[$i];
                                            $p1 = $pts[$i + 1];
                                            $cpX1 = $p0['x'] + ($p1['x'] - $p0['x']) / 3;
                                            $cpY1 = $p0['y'];
                                            $cpX2 = $p0['x'] + 2 * ($p1['x'] - $p0['x']) / 3;
                                            $cpY2 = $p1['y'];
                                            $d .= " C $cpX1 $cpY1, $cpX2 $cpY2, {$p1['x']} {$p1['y']}";
                                        }
                                        return $d;
                                    };
                                    
                                    $getCurveFillPath = function($pts) use ($getCurvePath) {
                                        if (empty($pts)) return 'M 50 140 L 950 140 Z';
                                        $d = $getCurvePath($pts);
                                        $d .= ' L ' . $pts[count($pts)-1]['x'] . ' 140 L ' . $pts[0]['x'] . ' 140 Z';
                                        return $d;
                                    };
                                @endphp

                                <!-- Penyebutan & Jangkauan Paths -->
                                @foreach(['penyebutan', 'jangkauan'] as $m)
                                    @foreach(['harian', 'mingguan', 'bulanan'] as $iv)
                                        @php $pts = $allPts[$m][$iv]; @endphp
                                        <g :class="(trendInterval === '{{ $iv }}' && trendMetric === '{{ $m }}') ? '' : 'hidden'">
                                            <path d="{{ $getCurveFillPath($pts) }}" fill="url(#trendCardGrad)"/>
                                            <path d="{{ $getCurvePath($pts) }}" fill="none" stroke="#1fa387" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" filter="url(#trendShadow)"/>
                                            @foreach($pts as $pt)
                                                <circle cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="5" fill="#fff" stroke="#1fa387" stroke-width="2.5" class="transition-all hover:r-7 duration-200 cursor-pointer"
                                                    @mouseenter="activePoint = { x: {{ $pt['x'] }}, y: {{ $pt['y'] }}, label: '{{ $pt['label'] }}', value: {{ $pt['count'] }} }"
                                                    @mouseleave="activePoint = null"
                                                />
                                            @endforeach
                                            @foreach($pts as $index => $pt)
                                                @if(count($pts) <= 10 || $index % ceil(count($pts) / 7) === 0 || $index === count($pts) - 1)
                                                    <text x="{{ $pt['x'] }}" y="165" font-size="10" font-weight="bold" fill="#94a3b8" text-anchor="middle">{{ $pt['label'] }}</text>
                                                @endif
                                            @endforeach
                                        </g>
                                    @endforeach
                                @endforeach

                                <!-- Sentimen Paths (Three separate lines) -->
                                @foreach(['harian', 'mingguan', 'bulanan'] as $iv)
                                    @php 
                                        $posPts = $sentimenPts[$iv]['positif']; 
                                        $neuPts = $sentimenPts[$iv]['netral']; 
                                        $negPts = $sentimenPts[$iv]['negatif']; 
                                    @endphp
                                    <g :class="(trendInterval === '{{ $iv }}' && trendMetric === 'sentimen') ? '' : 'hidden'">
                                        <!-- Positive line (emerald-500) -->
                                        <path d="{{ $getCurvePath($posPts) }}" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" filter="url(#posShadow)"/>
                                        @foreach($posPts as $pt)
                                            <circle cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="4.5" fill="#fff" stroke="#10b981" stroke-width="2" class="transition-all hover:r-6.5 duration-200 cursor-pointer"
                                                @mouseenter="activePoint = { x: {{ $pt['x'] }}, y: {{ $pt['y'] }}, label: '{{ $pt['label'] }}', value: {{ $pt['count'] }}, labelSuffix: 'Positif' }"
                                                @mouseleave="activePoint = null"
                                            />
                                        @endforeach

                                        <!-- Neutral line (slate-400) -->
                                        <path d="{{ $getCurvePath($neuPts) }}" fill="none" stroke="#94a3b8" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" filter="url(#neuShadow)"/>
                                        @foreach($neuPts as $pt)
                                            <circle cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="4.5" fill="#fff" stroke="#94a3b8" stroke-width="2" class="transition-all hover:r-6.5 duration-200 cursor-pointer"
                                                @mouseenter="activePoint = { x: {{ $pt['x'] }}, y: {{ $pt['y'] }}, label: '{{ $pt['label'] }}', value: {{ $pt['count'] }}, labelSuffix: 'Netral' }"
                                                @mouseleave="activePoint = null"
                                            />
                                        @endforeach

                                        <!-- Negative line (rose-500) -->
                                        <path d="{{ $getCurvePath($negPts) }}" fill="none" stroke="#f43f5e" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" filter="url(#negShadow)"/>
                                        @foreach($negPts as $pt)
                                            <circle cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="4.5" fill="#fff" stroke="#f43f5e" stroke-width="2" class="transition-all hover:r-6.5 duration-200 cursor-pointer"
                                                @mouseenter="activePoint = { x: {{ $pt['x'] }}, y: {{ $pt['y'] }}, label: '{{ $pt['label'] }}', value: {{ $pt['count'] }}, labelSuffix: 'Negatif' }"
                                                @mouseleave="activePoint = null"
                                            />
                                        @endforeach

                                        <!-- Labels -->
                                        @foreach($posPts as $index => $pt)
                                            @if(count($posPts) <= 10 || $index % ceil(count($posPts) / 7) === 0 || $index === count($posPts) - 1)
                                                <text x="{{ $pt['x'] }}" y="165" font-size="10" font-weight="bold" fill="#94a3b8" text-anchor="middle">{{ $pt['label'] }}</text>
                                            @endif
                                        @endforeach
                                    </g>
                                @endforeach
                            </svg>

                            <!-- Dynamic Tooltip -->
                            <div 
                                x-show="activePoint !== null" 
                                class="absolute bg-slate-900/95 backdrop-blur-sm border border-slate-700/80 px-3 py-2 rounded-xl shadow-xl transition-all duration-200 pointer-events-none text-left min-w-[100px]"
                                :style="`left: ${activePoint ? (activePoint.x / 10) : 0}%; top: ${activePoint ? (activePoint.y * 200 / 170) : 0}px; transform: translate(-50%, -125%); z-index: 50;`"
                                style="display: none;"
                            >
                                <div class="font-bold text-slate-300 text-[10px]" x-text="activePoint ? activePoint.label : ''"></div>
                                <div class="text-[11px] font-black text-emerald-400 mt-0.5" x-text="`${activePoint ? activePoint.value.toLocaleString('id-ID') : 0} ${activePoint && activePoint.labelSuffix ? activePoint.labelSuffix : (trendMetric === 'penyebutan' ? 'Penyebutan' : 'Jangkauan')}`"></div>
                            </div>
                        </div>

                        <!-- Legend and explanation -->
                        <div class="flex items-center justify-between text-[10px] text-slate-400 font-medium pt-3 px-1 border-t border-slate-100/50 mt-4">
                            <div class="flex items-center gap-5">
                                <div x-show="trendMetric !== 'sentimen'" class="flex items-center gap-1.5">
                                    <span class="w-3 h-3 rounded-full inline-block opacity-80 border-2 border-white shadow-sm" style="background-color: #1fa387;"></span>
                                    <span class="text-slate-600 font-bold" x-text="trendMetric === 'penyebutan' ? 'Total Penyebutan' : 'Total Jangkauan'"></span>
                                </div>
                                <div x-show="trendMetric === 'sentimen'" class="flex items-center gap-5" style="display: none;">
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-3 h-3 rounded-full inline-block opacity-85 border-2 border-white shadow-sm" style="background-color: #10b981;"></span>
                                        <span class="text-slate-600 font-bold">Sentimen Positif</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-3 h-3 rounded-full inline-block opacity-85 border-2 border-white shadow-sm" style="background-color: #94a3b8;"></span>
                                        <span class="text-slate-600 font-bold">Sentimen Netral</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-3 h-3 rounded-full inline-block opacity-85 border-2 border-white shadow-sm" style="background-color: #f43f5e;"></span>
                                        <span class="text-slate-600 font-bold">Sentimen Negatif</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-[12px] text-slate-400">info</span>
                                <span>Nilai grafik terhitung berdasarkan filter aktif</span>
                            </div>
                        </div>
                    </div>

                    <!-- Add Keyword Modal -->
                    @if($showAddKeywordModal && $this->isAdmin())
                        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
                            <div class="bg-white rounded-3xl border border-slate-200 max-w-md w-full p-6 shadow-2xl space-y-4 text-left">
                                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                    <h3 class="text-sm font-bold text-slate-800">Tambah Kata Kunci Baru</h3>
                                    <button @click="$wire.set('showAddKeywordModal', false)" class="text-slate-400 hover:text-slate-650 cursor-pointer">✕</button>
                                </div>
                                <div class="space-y-4">
                                    <div class="space-y-1.5">
                                        <label class="text-[10px] font-bold text-slate-500 uppercase">Teks Kata Kunci</label>
                                        <input 
                                            type="text" 
                                            wire:model.defer="newKeywordText"
                                            placeholder="Masukkan kata kunci baru..." 
                                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-xs font-semibold text-slate-800 focus:outline-none focus:border-[#1fa387] focus:bg-white transition"
                                        />
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="text-[10px] font-bold text-slate-500 uppercase">Tipe</label>
                                        <select 
                                            wire:model.defer="newKeywordType"
                                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-semibold text-slate-800 focus:outline-none focus:border-[#1fa387] focus:bg-white transition"
                                        >
                                            <option value="primary">Kata Kunci Utama</option>
                                            <option value="support">Kata Kunci Pendukung</option>
                                            <option value="exclude">Kata Kunci Eksklusi</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                                    <button 
                                        type="button"
                                        wire:click="$set('showAddKeywordModal', false)"
                                        class="px-4 py-2 border border-slate-200 text-slate-600 hover:bg-slate-50 font-bold text-xs rounded-xl transition cursor-pointer"
                                    >
                                        Batal
                                    </button>
                                    <button 
                                        type="button"
                                        wire:click="addKeyword"
                                        class="px-5 py-2 bg-[#1fa387] hover:bg-[#1fa387]/90 text-white font-bold text-xs rounded-xl transition cursor-pointer"
                                    >
                                        Tambah
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                </section>
            @elseif($this->isTab('wawasan'))
                @php
                    $project = $this->resolveProjectOrFail($this->projectId);
                    
                    // Resolve crisis color classes statically to prevent compilation issues
                    $crisisTextClass = 'text-slate-600';
                    $crisisBgClass = 'bg-slate-500';
                    $crisisPingClass = 'bg-slate-400';
                    if ($w['crisis_color'] === 'rose' || $w['crisis_color'] === 'red') {
                        $crisisTextClass = 'text-red-600';
                        $crisisBgClass = 'bg-red-500';
                        $crisisPingClass = 'bg-red-400';
                    } elseif ($w['crisis_color'] === 'amber' || $w['crisis_color'] === 'yellow' || $w['crisis_color'] === 'orange') {
                        $crisisTextClass = 'text-amber-600';
                        $crisisBgClass = 'bg-amber-500';
                        $crisisPingClass = 'bg-amber-400';
                    } elseif ($w['crisis_color'] === 'emerald' || $w['crisis_color'] === 'green') {
                        $crisisTextClass = 'text-emerald-600';
                        $crisisBgClass = 'bg-emerald-500';
                        $crisisPingClass = 'bg-emerald-400';
                    }
                @endphp
                <section class="flex-1 min-w-0 space-y-6 text-left">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-xl font-bold text-slate-900 mb-0.5 font-sans flex items-center gap-2">
                                <span class="material-symbols-outlined text-indigo-600 text-[22px]">psychology</span>Wawasan & Ringkasan AI
                                @if(!empty($project->ai_insight_updated_at))
                                    <span class="text-[10px] font-medium px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded-full border border-indigo-100 uppercase tracking-wider">Murni AI</span>
                                @endif
                            </h2>
                            <p class="text-xs text-slate-500">Analisis cerdas berdasarkan agregasi data sentimen terkini.</p>
                        </div>
                        
                        <button 
                            type="button"
                            wire:click="generateAiInsights"
                            wire:loading.attr="disabled"
                            wire:target="generateAiInsights"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-[11px] px-4 py-2 rounded-xl transition flex items-center gap-1.5 cursor-pointer shadow-sm shadow-indigo-600/20 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg wire:loading.remove wire:target="generateAiInsights" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            <svg wire:loading wire:target="generateAiInsights" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span wire:loading.remove wire:target="generateAiInsights">Perbarui Wawasan AI</span>
                            <span wire:loading wire:target="generateAiInsights">Memproses AI...</span>
                        </button>
                    </div>

                    <!-- Top Analytics KPI Grid -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <!-- Card 1: Reputation Index -->
                        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm hover:shadow-md transition-all duration-200 flex items-center justify-between h-[110px]">
                            <div class="space-y-1.5 text-left">
                                <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest block">Indeks Reputasi</span>
                                <h3 class="text-3xl font-black text-slate-900 tracking-tight leading-none">{{ $w['reputation_score'] }}/100</h3>
                                <p class="text-[11px] font-semibold text-slate-400">Berdasarkan rasio sentimen</p>
                            </div>
                            <div class="w-14 h-14 flex items-center justify-center relative flex-shrink-0" style="width: 56px; height: 56px; min-width: 56px; min-height: 56px;">
                                <svg class="w-full h-full transform -rotate-90" viewBox="0 0 64 64" style="width: 56px; height: 56px; display: block;">
                                    <circle cx="32" cy="32" r="28" stroke="#f1f5f9" stroke-width="4" fill="transparent" />
                                    <circle cx="32" cy="32" r="28" stroke="#1fa387" stroke-width="4" fill="transparent" 
                                            stroke-dasharray="175.93" 
                                            stroke-dashoffset="{{ 175.93 - (175.93 * $w['reputation_score'] / 100) }}" 
                                            stroke-linecap="round" />
                                </svg>
                                <span class="absolute text-[11px] font-black text-slate-800" style="top: 50%; left: 50%; transform: translate(-50%, -50%);">{{ $w['reputation_score'] }}%</span>
                            </div>
                        </div>

                        <!-- Card 2: Sentiment Health -->
                        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm hover:shadow-md transition-all duration-200 flex flex-col justify-between h-[110px]">
                            <div class="flex justify-between items-center w-full">
                                <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest block">Kesehatan Sentimen</span>
                                <span class="text-[9px] font-bold text-emerald-650 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-100 uppercase tracking-wider">{{ $w['positive_pct'] }}% Positif</span>
                            </div>
                            <div class="space-y-2 w-full">
                                <div class="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden flex">
                                    <div class="h-full bg-emerald-500" style="width: {{ $w['positive_pct'] }}%"></div>
                                    <div class="h-full bg-slate-300" style="width: {{ $w['neutral_pct'] }}%"></div>
                                    <div class="h-full bg-rose-500" style="width: {{ $w['negative_pct'] }}%"></div>
                                </div>
                                <div class="flex items-center justify-between text-[9px] font-black text-slate-400 tracking-wide">
                                    <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> P: {{ $w['positive_pct'] }}%</span>
                                    <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> N: {{ $w['neutral_pct'] }}%</span>
                                    <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Neg: {{ $w['negative_pct'] }}%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Card 3: Crisis Signal -->
                        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm hover:shadow-md transition-all duration-200 flex items-center justify-between h-[110px]">
                            <div class="space-y-1.5 text-left">
                                <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest block">Sinyal Krisis</span>
                                <h3 class="text-3xl font-black uppercase tracking-tight leading-none {{ $crisisTextClass }}">{{ $w['crisis_signal'] }}</h3>
                                <p class="text-[11px] font-semibold text-slate-400">Tingkat ancaman negatif</p>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center flex-shrink-0 relative">
                                <span class="animate-ping absolute inline-flex h-3 w-3 rounded-full opacity-75 {{ $crisisPingClass }}"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 {{ $crisisBgClass }}"></span>
                            </div>
                        </div>

                        <!-- Card 4: Viral Condition -->
                        <div 
                            @click="showViralModal = true"
                            class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm hover:shadow-md hover:bg-slate-50/50 transition-all duration-200 active:scale-[0.98] cursor-pointer flex items-center justify-between h-[110px] group"
                        >
                            <div class="space-y-1.5 text-left">
                                <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest block group-hover:text-indigo-650 transition-colors">Kondisi Viral</span>
                                <h3 class="text-3xl font-black tracking-tight leading-none text-{{ $w['viral_color'] }}-600">{{ $w['viral_status'] }}</h3>
                                <p class="text-[11px] font-semibold text-slate-400 truncate max-w-[170px]" title="{{ $w['viral_desc'] }}">{{ $w['viral_desc'] }}</p>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-{{ $w['viral_color'] }}-50 flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition-transform duration-200">
                                <svg class="w-5 h-5 text-{{ $w['viral_color'] }}-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                            </div>
                        </div>
                    </div>

                                    <!-- Main Columns (Unified 2-Column Masonry Stack) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 items-start">
                        <!-- Left Column: Summary, Recs, Negative Issues, Sentiment Shift -->
                        <div class="space-y-5">
                            <!-- Executive Summary -->
                            <div class="bg-gradient-to-br from-slate-50 to-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-[#1fa387]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                        Ringkasan Eksekutif AI
                                    </h4>
                                    <span class="text-[9px] font-bold text-[#1fa387] bg-[#1fa387]/10 px-2 py-0.5 rounded-full uppercase tracking-wider">AI Generated</span>
                                </div>
                                <div class="text-slate-600 text-xs leading-relaxed space-y-2">
                                    {!! preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', nl2br(e($w['summary']))) !!}
                                </div>
                            </div>

                            <!-- Strategic Recommendations -->
                            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                                <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg>
                                    Rekomendasi Tindakan Strategis
                                </h4>
                                <ul class="space-y-3">
                                    @foreach($w['recommendations'] as $rec)
                                        <li class="flex items-start gap-2.5 text-xs text-slate-600">
                                            <svg class="w-4 h-4 text-emerald-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                            <span>{{ $rec }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>

                            <!-- Top Isu Negatif -->
                            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                        <span class="material-symbols-outlined text-rose-500 text-[18px]">priority_high</span>
                                        Top Isu Negatif
                                    </h4>
                                    <span class="text-[9px] font-bold text-rose-600 bg-rose-50 px-2 py-0.5 rounded-full border border-rose-100 uppercase">Prioritas</span>
                                </div>
                                <div class="space-y-3.5">
                                    @forelse($w['negative_issues'] as $issue)
                                        <div class="space-y-2 pb-3 border-b border-slate-100 last:border-0 last:pb-0">
                                            <div class="flex items-start justify-between gap-3">
                                                <p class="text-xs font-bold text-slate-700 leading-relaxed">{{ $issue['issue'] }}</p>
                                                <span class="text-[10px] font-black text-rose-600 bg-rose-50 border border-rose-100 rounded-lg px-2 py-0.5 whitespace-nowrap">{{ $issue['total'] }} item</span>
                                            </div>
                                            <div class="h-1.5 w-full bg-slate-50 rounded-full overflow-hidden border border-slate-100">
                                                <div class="h-full bg-rose-500 rounded-full" style="width: {{ $issue['pct'] }}%"></div>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-xs text-slate-400 italic">Belum ada isu negatif dominan pada filter ini.</p>
                                    @endforelse
                                </div>
                            </div>

                            <!-- Perubahan Sentimen -->
                            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                                @php
                                    $shift = $w['sentiment_shift'];
                                    $shiftBadge = match ($shift['tone']) {
                                        'rose' => 'bg-rose-50 text-rose-700 border-rose-100',
                                        'emerald' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                                        default => 'bg-slate-50 text-slate-700 border-slate-100',
                                    };
                                @endphp
                                <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                    <span class="material-symbols-outlined text-indigo-500 text-[18px]">trending_up</span>
                                    Perubahan Sentimen
                                </h4>
                                <div class="rounded-2xl bg-slate-50/70 border border-slate-100 p-4">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <p class="text-xs font-bold text-slate-700">{{ $shift['label'] }}</p>
                                            <p class="mt-1 text-[11px] text-slate-500">Membandingkan paruh awal dan paruh akhir dari rentang tanggal aktif.</p>
                                        </div>
                                        <span class="text-[10px] font-black rounded-lg px-2.5 py-1 border {{ $shiftBadge }}">
                                            {{ $shift['delta'] > 0 ? '+' : '' }}{{ $shift['delta'] }}%
                                        </span>
                                    </div>
                                    <div class="mt-4 grid grid-cols-2 gap-3">
                                        <div class="rounded-xl bg-white border border-slate-100 p-3">
                                            <p class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Paruh Awal</p>
                                            <p class="mt-1 text-xl font-extrabold text-slate-800">{{ $shift['previous_negative_pct'] }}%</p>
                                            <p class="text-[10px] text-slate-400">sentimen negatif</p>
                                        </div>
                                        <div class="rounded-xl bg-white border border-slate-100 p-3">
                                            <p class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Paruh Akhir</p>
                                            <p class="mt-1 text-xl font-extrabold text-slate-800">{{ $shift['current_negative_pct'] }}%</p>
                                            <p class="text-[10px] text-slate-400">sentimen negatif</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Breakdown, Sources, Risk Triggers, Response Actions -->
                        <div class="space-y-5">
                            <!-- Top Categories -->
                            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                                <h4 class="text-sm font-bold text-slate-800">Distribusi Kategori Isu</h4>
                                <div class="space-y-4">
                                    @forelse($w['categories'] as $cat)
                                        @php
                                            $catPct = $w['total'] > 0 ? round(($cat['total'] / $w['total']) * 100) : 0;
                                        @endphp
                                        <div class="space-y-2 pb-3.5 border-b border-slate-100/60 last:border-0 last:pb-0">
                                            <div class="flex items-start justify-between gap-3 text-xs">
                                                <span class="text-slate-700 font-bold leading-normal text-left flex-1">{{ $cat['category'] }}</span>
                                                <span class="text-slate-500 font-black whitespace-nowrap bg-slate-50 px-2 py-0.5 rounded-lg text-[10px] border border-slate-200">{{ $cat['total'] }} ({{ $catPct }}%)</span>
                                            </div>
                                            <div class="h-1.5 w-full bg-slate-50 border border-slate-100 rounded-full overflow-hidden">
                                                <div class="h-full bg-[#1fa387] rounded-full" style="width: {{ $catPct }}%"></div>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-xs text-slate-400 italic">Belum ada kategori terdeteksi.</p>
                                    @endforelse
                                </div>
                            </div>

                            <!-- Top Sources -->
                            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                                <h4 class="text-sm font-bold text-slate-800">Kanal Media Terpopuler</h4>
                                <div class="space-y-3.5">
                                    @forelse($w['sources'] as $src)
                                        @php
                                            $srcPct = $w['total'] > 0 ? round(($src['total'] / $w['total']) * 100) : 0;
                                        @endphp
                                        <div class="flex items-center justify-between text-xs pb-2 border-b border-slate-50 last:border-0 last:pb-0">
                                            <span class="font-bold text-slate-700 flex items-center gap-1.5">
                                                <span class="w-1.5 h-1.5 rounded-full bg-[#1fa387]"></span>
                                                {{ $src['source_name'] }}
                                            </span>
                                            <span class="text-slate-500 font-bold whitespace-nowrap bg-slate-50 px-2 py-0.5 rounded-lg border border-slate-100 text-[10px]">{{ $src['total'] }} penyebutan ({{ $srcPct }}%)</span>
                                        </div>
                                    @empty
                                        <p class="text-xs text-slate-400 italic">Belum ada data media.</p>
                                    @endforelse
                                </div>
                            </div>

                            <!-- Pemicu Risiko -->
                            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                        <span class="material-symbols-outlined text-amber-500 text-[18px]">crisis_alert</span>
                                        Pemicu Risiko
                                    </h4>
                                    <span class="text-[9px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-100 uppercase">High Risk</span>
                                </div>
                                <div class="space-y-3.5">
                                    @forelse($w['risk_triggers'] as $trigger)
                                        <div class="rounded-2xl border border-slate-100 bg-slate-50/60 p-3.5 space-y-2">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <p class="text-xs font-extrabold text-slate-800 leading-snug">{{ $trigger['title'] }}</p>
                                                    <p class="mt-1 text-[10px] font-bold text-slate-400">{{ $trigger['source'] }} • {{ $trigger['published_at'] }} • Jangkauan {{ $trigger['reach'] }}</p>
                                                </div>
                                                <span class="text-[9px] font-black rounded-lg px-2 py-0.5 border {{ $trigger['risk_level'] === 'Kritis' ? 'bg-purple-50 text-purple-700 border-purple-100' : 'bg-rose-50 text-rose-700 border-rose-100' }}">{{ $trigger['risk_level'] }}</span>
                                            </div>
                                            <p class="text-[11px] text-slate-500 leading-relaxed">{{ $trigger['risk_reason'] }}</p>
                                            @if(!empty($trigger['url']))
                                                <a href="{{ $trigger['url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-[10px] font-bold text-[#1fa387] hover:text-[#167c68] transition">
                                                    Buka sumber
                                                    <span class="material-symbols-outlined text-[13px]">open_in_new</span>
                                                </a>
                                            @endif
                                        </div>
                                    @empty
                                        <p class="text-xs text-slate-400 italic">Belum ada pemicu risiko tinggi pada filter ini.</p>
                                    @endforelse
                                </div>
                            </div>

                            <!-- Rekomendasi Respons -->
                            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                                <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                    <span class="material-symbols-outlined text-[#1fa387] text-[18px]">task_alt</span>
                                    Rekomendasi Respons
                                </h4>
                                <div class="space-y-3">
                                    @foreach($w['response_actions'] as $action)
                                        <div class="flex items-start gap-3 rounded-2xl border border-slate-100 bg-slate-50/60 p-3.5">
                                            <span class="text-[9px] font-black text-[#1fa387] bg-[#1fa387]/10 border border-[#1fa387]/15 rounded-lg px-2 py-1 whitespace-nowrap">{{ $action['level'] }}</span>
                                            <p class="text-xs text-slate-600 leading-relaxed">{{ $action['text'] }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            @elseif($this->isTab('laporan'))
                <!-- TAB 4: Laporan (Report configuration page matching screenshots) -->
                <section class="flex-1 min-w-0 space-y-6" x-data="{
                    reportType: 'pdf',
                    pdfToggles: {
                        wawasan: true,
                        statistik: true,
                        grafikPenyebutan: true,
                        grafikSentimen: true,
                        konteks: true,
                        perKataKunci: true,
                        beritaPopuler: true,
                        beritaTerbaru: true,
                        sumberBerita: true,
                        sumberMedsos: true,
                        rekomendasi: true
                    },
                    excelToggles: {
                        ringkasan: true,
                        terbaru: true,
                        kategori: true,
                        konteks: true,
                        situsBerpengaruh: true,
                        populer: true,
                        influencer: true,
                        situsAktif: true,
                        rekomendasi: true
                    },
                    pilihSemua() {
                        if (this.reportType === 'pdf') {
                            for (let key in this.pdfToggles) this.pdfToggles[key] = true;
                        } else {
                            for (let key in this.excelToggles) this.excelToggles[key] = true;
                        }
                    }
                }">
                    <!-- Header -->
                    <div class="flex justify-between items-start text-left">
                        <div>
                            <h2 class="text-xl font-bold text-slate-900 mb-0.5">Konfigurasi Laporan</h2>
                            <p class="text-xs text-slate-500">Pilih komponen data yang akan disertakan dalam dokumen.</p>
                        </div>
                    </div>

                    <!-- Main Config Card -->
                    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-8 space-y-6 text-left">
                        <!-- Tab Toggles -->
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                            <div class="flex gap-3">
                                <!-- PDF Tab -->
                                <button 
                                    type="button"
                                    @click="reportType = 'pdf'"
                                    class="flex items-center gap-2 px-5 py-2.5 text-xs font-bold rounded-xl border transition cursor-pointer"
                                    :class="reportType === 'pdf' ? 'bg-[#1fa387]/5 border-[#1fa387] text-[#1fa387]' : 'bg-slate-50 border-slate-200 text-slate-500 hover:text-slate-700'"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                    <span>Laporan PDF</span>
                                </button>
                                <!-- Excel Tab -->
                                <button 
                                    type="button"
                                    @click="reportType = 'excel'"
                                    class="flex items-center gap-2 px-5 py-2.5 text-xs font-bold rounded-xl border transition cursor-pointer"
                                    :class="reportType === 'excel' ? 'bg-[#1fa387]/5 border-[#1fa387] text-[#1fa387]' : 'bg-slate-50 border-slate-200 text-slate-500 hover:text-slate-700'"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    <span>Laporan Excel</span>
                                </button>
                            </div>

                            <button 
                                type="button"
                                @click="pilihSemua()"
                                class="bg-[#1fa387] hover:bg-[#1fa387]/90 text-white font-bold text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cursor-pointer shadow-sm"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                <span>Pilih Semua</span>
                            </button>
                        </div>

                        <!-- PDF Option List -->
                        <div x-show="reportType === 'pdf'" class="space-y-6">
                            <!-- Group 1 -->
                            <div class="space-y-4">
                                <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Ringkasan & Statistik</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <!-- Rangkuman & Wawasan -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 min-w-0 flex-1">
                                            <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold">📄</div>
                                            <div class="min-w-0 flex-1">
                                                <h5 class="text-xs font-bold text-slate-800">Rangkuman & Wawasan</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-snug">Ringkasan otomatis dari insight dan temuan kunci</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="pdfToggles.wawasan = !pdfToggles.wawasan" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="pdfToggles.wawasan ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="pdfToggles.wawasan ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- Statistik Umum -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 min-w-0 flex-1">
                                            <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">📊</div>
                                            <div class="min-w-0 flex-1">
                                                <h5 class="text-xs font-bold text-slate-800">Statistik Umum</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-snug">Metrik performa media dan statistik penting</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="pdfToggles.statistik = !pdfToggles.statistik" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="pdfToggles.statistik ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="pdfToggles.statistik ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- Grafik Penyebutan -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 min-w-0 flex-1">
                                            <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">📈</div>
                                            <div class="min-w-0 flex-1">
                                                <h5 class="text-xs font-bold text-slate-800">Grafik Penyebutan</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-snug">Visualisasi tren penyebutan sepanjang waktu</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="pdfToggles.grafikPenyebutan = !pdfToggles.grafikPenyebutan" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="pdfToggles.grafikPenyebutan ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="pdfToggles.grafikPenyebutan ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- Grafik Sentimen -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 min-w-0 flex-1">
                                            <div class="w-8 h-8 rounded-xl bg-pink-50 text-pink-600 flex items-center justify-center font-bold">😊</div>
                                            <div class="min-w-0 flex-1">
                                                <h5 class="text-xs font-bold text-slate-800">Grafik Sentimen</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-snug">Analisis sentimen dari percakapan media</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="pdfToggles.grafikSentimen = !pdfToggles.grafikSentimen" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="pdfToggles.grafikSentimen ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="pdfToggles.grafikSentimen ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- Konteks Percakapan -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 min-w-0 flex-1">
                                            <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">💬</div>
                                            <div class="min-w-0 flex-1">
                                                <h5 class="text-xs font-bold text-slate-800">Konteks Percakapan</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-snug">Topik dan konteks percakapan yang paling banyak</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="pdfToggles.konteks = !pdfToggles.konteks" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="pdfToggles.konteks ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="pdfToggles.konteks ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- Per Kata Kunci -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 min-w-0 flex-1">
                                            <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">🏷️</div>
                                            <div class="min-w-0 flex-1">
                                                <h5 class="text-xs font-bold text-slate-800">Per Kata Kunci</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-snug">Analisis berdasarkan kata kunci yang dipantau</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="pdfToggles.perKataKunci = !pdfToggles.perKataKunci" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="pdfToggles.perKataKunci ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="pdfToggles.perKataKunci ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Group 2 -->
                            <div class="space-y-4 pt-4 border-t border-slate-100">
                                <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Media & Konten</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <!-- Berita Terpopuler -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 min-w-0 flex-1">
                                            <div class="w-8 h-8 rounded-xl bg-red-50 text-red-600 flex items-center justify-center font-bold">🔥</div>
                                            <div class="min-w-0 flex-1">
                                                <h5 class="text-xs font-bold text-slate-800">Berita Terpopuler</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-snug">Artikel berita dengan engagement tertinggi</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="pdfToggles.beritaPopuler = !pdfToggles.beritaPopuler" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="pdfToggles.beritaPopuler ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="pdfToggles.beritaPopuler ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- Berita Terbaru -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 min-w-0 flex-1">
                                            <div class="w-8 h-8 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center font-bold">📅</div>
                                            <div class="min-w-0 flex-1">
                                                <h5 class="text-xs font-bold text-slate-800">Berita Terbaru</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-snug">Artikel berita terbaru dari sumber terpercaya</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="pdfToggles.beritaTerbaru = !pdfToggles.beritaTerbaru" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="pdfToggles.beritaTerbaru ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="pdfToggles.beritaTerbaru ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- Sumber Berita -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 min-w-0 flex-1">
                                            <div class="w-8 h-8 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center font-bold">📰</div>
                                            <div class="min-w-0 flex-1">
                                                <h5 class="text-xs font-bold text-slate-800">Sumber Berita</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-snug">Sumber berita dengan kontribusi paling banyak</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="pdfToggles.sumberBerita = !pdfToggles.sumberBerita" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="pdfToggles.sumberBerita ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="pdfToggles.sumberBerita ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- Sumber Medsos -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 min-w-0 flex-1">
                                            <div class="w-8 h-8 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center font-bold">📸</div>
                                            <div class="min-w-0 flex-1">
                                                <h5 class="text-xs font-bold text-slate-800">Sumber Medsos</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-snug">Influencer dan akun paling aktif</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="pdfToggles.sumberMedsos = !pdfToggles.sumberMedsos" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="pdfToggles.sumberMedsos ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="pdfToggles.sumberMedsos ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- Rekomendasi -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 min-w-0 flex-1">
                                            <div class="w-8 h-8 rounded-xl bg-yellow-50 text-yellow-600 flex items-center justify-center font-bold">💡</div>
                                            <div class="min-w-0 flex-1">
                                                <h5 class="text-xs font-bold text-slate-800">Rekomendasi</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-snug">Saran konten berdasarkan analisis data</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="pdfToggles.rekomendasi = !pdfToggles.rekomendasi" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="pdfToggles.rekomendasi ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="pdfToggles.rekomendasi ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end pt-6 border-t border-slate-100">
                                <a
                                    :href="`{{ route('report.pdf', ['project_id' => $this->getDecodedProjectId()]) }}&toggles=` + encodeURIComponent(JSON.stringify(pdfToggles))"
                                    target="_blank"
                                    class="bg-[#c0392b] hover:bg-[#a93226] text-white font-bold text-xs px-6 py-3 rounded-xl transition flex items-center gap-1.5 cursor-pointer shadow-sm"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8.5 7.5c0 .83-.67 1.5-1.5 1.5H9v2H7.5V7H10c.83 0 1.5.67 1.5 1.5v1zm5 2c0 .83-.67 1.5-1.5 1.5h-2.5V7H15c.83 0 1.5.67 1.5 1.5v3zm4-3H19v1h1.5V11H19v2h-1.5V7h3v1.5zM9 9.5h1v-1H9v1zM4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm10 5.5h1v-3h-1v3z"/></svg>
                                    <span>⬇ Unduh Laporan PDF</span>
                                </a>
                            </div>
                        </div>

                        <!-- Excel Option List -->
                        <div x-show="reportType === 'excel'" class="space-y-6" style="display: none;">
                            <!-- Group 1 -->
                            <div class="space-y-4">
                                <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Data Mentah & Ringkasan</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <!-- Ringkasan Penyebutan -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold">📝</div>
                                            <div>
                                                <h5 class="text-xs font-bold text-slate-800">Ringkasan Penyebutan</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-none">Tabel ringkasan semua penyebutan berdasarkan sumber</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="excelToggles.ringkasan = !excelToggles.ringkasan" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="excelToggles.ringkasan ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="excelToggles.ringkasan ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- Penyebutan Terbaru -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">🕒</div>
                                            <div>
                                                <h5 class="text-xs font-bold text-slate-800">Penyebutan Terbaru</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-none">Daftar penyebutan terbaru dengan detail lengkap</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="excelToggles.terbaru = !excelToggles.terbaru" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="excelToggles.terbaru ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="excelToggles.terbaru ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- Penyebutan per Kategori -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">🗂️</div>
                                            <div>
                                                <h5 class="text-xs font-bold text-slate-800">Penyebutan per Kategori</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-none">Pengelompokan penyebutan berdasarkan kategori</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="excelToggles.kategori = !excelToggles.kategori" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="excelToggles.kategori ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="excelToggles.kategori ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- Konteks Percakapan -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">💬</div>
                                            <div>
                                                <h5 class="text-xs font-bold text-slate-800">Konteks Percakapan</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-none">Topik dan konteks yang muncul dari percakapan</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="excelToggles.konteks = !excelToggles.konteks" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="excelToggles.konteks ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="excelToggles.konteks ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Group 2 -->
                            <div class="space-y-4 pt-4 border-t border-slate-100">
                                <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Analisis & Rekomendasi</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <!-- Situs Berpengaruh -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center font-bold">🌐</div>
                                            <div>
                                                <h5 class="text-xs font-bold text-slate-800">Situs Berpengaruh</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-none">Situs dengan skor pengaruh tertinggi</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="excelToggles.situsBerpengaruh = !excelToggles.situsBerpengaruh" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="excelToggles.situsBerpengaruh ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="excelToggles.situsBerpengaruh ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- Penyebutan Populer -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center font-bold">🔥</div>
                                            <div>
                                                <h5 class="text-xs font-bold text-slate-800">Penyebutan Populer</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-none">Penyebutan dengan engagement tertinggi</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="excelToggles.populer = !excelToggles.populer" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="excelToggles.populer ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="excelToggles.populer ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- Influencer Berpengaruh -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-xl bg-red-50 text-red-600 flex items-center justify-center font-bold">👥</div>
                                            <div>
                                                <h5 class="text-xs font-bold text-slate-800">Influencer Berpengaruh</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-none">Influencer dengan dampak terbesar</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="excelToggles.influencer = !excelToggles.influencer" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="excelToggles.influencer ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="excelToggles.influencer ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- Situs Paling Aktif -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center font-bold">🎙️</div>
                                            <div>
                                                <h5 class="text-xs font-bold text-slate-800">Situs Paling Aktif</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-none">Situs dengan aktivitas tertinggi</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="excelToggles.situsAktif = !excelToggles.situsAktif" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="excelToggles.situsAktif ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="excelToggles.situsAktif ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- Rekomendasi Konten -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-xl bg-yellow-50 text-yellow-600 flex items-center justify-center font-bold">💡</div>
                                            <div>
                                                <h5 class="text-xs font-bold text-slate-800">Rekomendasi Konten</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-none">Saran konten berdasarkan analisis data</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="excelToggles.rekomendasi = !excelToggles.rekomendasi" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="excelToggles.rekomendasi ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="excelToggles.rekomendasi ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end pt-6 border-t border-slate-100">
                                <a
                                    :href="`{{ route('report.excel', ['project_id' => $this->getDecodedProjectId()]) }}&toggles=` + encodeURIComponent(JSON.stringify(excelToggles))"
                                    class="bg-[#1fa387] hover:bg-[#178a70] text-white font-bold text-xs px-6 py-3 rounded-xl transition flex items-center gap-1.5 cursor-pointer shadow-sm"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                                    <span>⬇ Unduh Laporan Excel</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </section>

            @elseif($this->isTab('konten'))
                <section class="flex-1 min-w-0 space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-bold text-slate-900 mb-0.5">Manajemen Konten</h2>
                            <p class="text-xs text-slate-500">Galeri konten artikel dan postingan yang berhasil dikumpulkan.</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                        @php
                            $articlesList = $this->getArticles();
                        @endphp
                        @forelse($articlesList as $article)
                            @php
                                $articleReachDisp = $this->getProjectReachDisplayData($article);
                            @endphp
                            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-[0_4px_15px_-3px_rgba(0,0,0,0.03)] hover:shadow-[0_8px_25px_-5px_rgba(31,163,135,0.1)] hover:border-[#1fa387]/30 transition-all flex flex-col group">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] font-extrabold px-3 py-1.5 rounded-lg uppercase tracking-wider {{ $this->getValidAiResult($article)?->sentiment_score ?? 0 >= 0.3 ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : ($this->getValidAiResult($article)?->sentiment_score ?? 0 <= -0.3 ? 'bg-rose-50 text-rose-600 border border-rose-100' : 'bg-slate-50 text-slate-600 border border-slate-100') }}">
                                            {{ $this->getValidAiResult($article)?->sentiment_score ?? 0 >= 0.3 ? 'Positif' : ($this->getValidAiResult($article)?->sentiment_score ?? 0 <= -0.3 ? 'Negatif' : 'Netral') }}
                                        </span>
                                        
                                        <!-- Ringkasan AI Button -->
                                        <button 
                                            type="button"
                                            @click="openDetail(
                                                {{ Js::from($article->title) }},
                                                {{ Js::from($article->source_name) }},
                                                {{ Js::from($article->published_at ? \Carbon\Carbon::parse($article->published_at)->format('d F Y, H:i') : 'Baru saja') }},
                                                {{ Js::from($article->url) }},
                                                {{ Js::from($article->content) }},
                                                {{ Js::from($this->getValidAiResult($article)?->summary ?? 'Belum ada analisis ringkasan AI.') }},
                                                {{ Js::from($this->getValidAiResult($article)?->recommendation ?? 'Tidak ada rekomendasi khusus.') }},
                                                {{ Js::from($this->getValidAiResult($article)?->sentiment) }},
                                                {{ Js::from($article->category) }},
                                                {{ Js::from($articleReachDisp['hasOfficialProjectReach'] ? number_format($articleReachDisp['reachValue'], 0, ',', '.') : ($articleReachDisp['hasReadableAiReach'] ? 'Belum tersedia' : 'Belum dinilai AI')) }},
                                                {{ Js::from($articleReachDisp['hasOfficialProjectReach'] ? $articleReachDisp['levelLabel'] : ($articleReachDisp['hasReadableAiReach'] ? 'Belum tersedia' : '')) }},
                                                {{ Js::from($articleReachDisp['hasOfficialProjectReach'] ? $articleReachDisp['scoreValue'] . '/10' : ($articleReachDisp['hasReadableAiReach'] ? 'Belum tersedia' : 'Belum dinilai AI')) }},
                                                {{ Js::from($article->published_at ? \Carbon\Carbon::parse($article->published_at)->format('d/m/y') : 'Baru saja') }}
                                            ); showAiSummaryModal = true;"
                                            class="inline-flex items-center gap-1 text-[9px] font-bold px-2.5 py-1 bg-emerald-55 text-emerald-600 hover:bg-emerald-100 border border-emerald-200 rounded-lg uppercase tracking-wider transition-colors cursor-pointer"
                                            style="background-color: #ecfdf5; border-color: #a7f3d0;"
                                        >
                                            <span class="material-symbols-outlined text-[12px] text-emerald-600">auto_awesome</span>
                                            <span>Ringkasan AI</span>
                                        </button>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-xs font-bold text-slate-400 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100">
                                        <span class="material-symbols-outlined text-[14px]">schedule</span>
                                        {{ \Carbon\Carbon::parse($article->published_at)->format('d M Y, H:i') }} ({{ \Carbon\Carbon::parse($article->published_at)->diffForHumans() }})
                                    </div>
                                </div>
                                <h3 class="text-sm font-black text-slate-900 leading-snug mb-3 line-clamp-2 group-hover:text-[#1fa387] transition-colors">
                                    <a href="{{ $article->url }}" target="_blank">{{ $article->title }}</a>
                                </h3>
                                <p class="text-[13px] text-slate-500 line-clamp-3 mb-5 leading-relaxed flex-grow font-medium">
                                    {{ Str::limit(strip_tags($article->content), 120) }}
                                </p>
                                <div class="flex items-center justify-between pt-4 border-t border-slate-100 mt-auto">
                                    <div class="flex items-center gap-2.5 bg-slate-50 pl-1 pr-3 py-1 rounded-full border border-slate-100">
                                        @php
                                            $srcLower = strtolower($article->source_name);
                                            if (str_contains($srcLower, 'instagram') || $srcLower === 'ig') {
                                                $logoBg = 'bg-gradient-to-br from-purple-600 via-pink-500 to-orange-400';
                                            } elseif (str_contains($srcLower, 'tiktok') || $srcLower === 'tk') {
                                                $logoBg = 'bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800';
                                            } elseif (str_contains($srcLower, 'facebook') || $srcLower === 'fb') {
                                                $logoBg = 'bg-gradient-to-br from-blue-600 to-blue-700';
                                            } else {
                                                $logoBg = 'bg-transparent';
                                            }
                                        @endphp
                                        <div class="w-6 h-6 rounded-full overflow-hidden flex items-center justify-center shadow-sm flex-shrink-0 {{ $logoBg }} border border-slate-200">
                                            @if(str_contains($srcLower, 'facebook') || $srcLower === 'fb')
                                                <svg class="w-3.5 h-3.5 fill-current text-white" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"></path></svg>
                                            @elseif(str_contains($srcLower, 'instagram') || $srcLower === 'ig')
                                                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5" stroke-linecap="round"></line></svg>
                                            @elseif(str_contains($srcLower, 'tiktok') || $srcLower === 'tk')
                                                <svg class="w-3.5 h-3.5 fill-current text-white" viewBox="0 0 24 24"><path d="M12.525.01c1.306-.022 2.615-.011 3.921-.012.08 1.836 1.011 3.5 2.501 4.485.006 1.341-.004 2.683-.004 4.024-1.57-.107-3.067-.932-3.955-2.247-.008 2.827-.003 5.657-.005 8.486-.098 3.546-3.13 6.643-6.726 6.467-3.526-.067-6.523-3.18-6.241-6.722.215-3.327 3.012-6.104 6.347-5.992v4.06c-1.393-.16-2.775.76-3.085 2.112-.397 1.488.583 3.125 2.1 3.328 1.455.234 2.924-.766 3.14-2.224.048-2.617.02-5.237.03-7.856.002-3.834-.002-7.67.002-11.504z"></path></svg>
                                            @else
                                                <div class="relative w-full h-full flex items-center justify-center" x-data="{ imgFailed: false }">
                                                    <img x-show="!imgFailed" 
                                                         src="{{ $this->resolveArticleLogoUrl($article) }}" 
                                                         x-on:error="imgFailed = true"
                                                         class="w-full h-full object-cover animate-fade-in" 
                                                         alt="{{ $article->source_name }}" />
                                                    <div x-show="imgFailed" class="absolute inset-0 w-full h-full bg-transparent flex items-center justify-center" style="display: none;">
                                                        <svg class="w-3.5 h-3.5 text-[#1fa387]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path></svg>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                        <span class="text-[11px] font-extrabold text-slate-700 tracking-wide">{{ $article->source_name }}</span>
                                    </div>
                                    <button 
                                        type="button"
                                        @click="openDetail(
                                            {{ Js::from($article->title) }},
                                            {{ Js::from($article->source_name) }},
                                            {{ Js::from($article->published_at ? \Carbon\Carbon::parse($article->published_at)->format('d F Y, H:i') : 'Baru saja') }},
                                            {{ Js::from($article->url) }},
                                            {{ Js::from($article->content) }},
                                            {{ Js::from($this->getValidAiResult($article)?->summary ?? 'Belum ada analisis ringkasan AI.') }},
                                            {{ Js::from($this->getValidAiResult($article)?->recommendation ?? 'Tidak ada rekomendasi khusus.') }},
                                            {{ Js::from($this->getValidAiResult($article)?->sentiment) }},
                                            {{ Js::from($article->category) }},
                                            {{ Js::from($articleReachDisp['hasOfficialProjectReach'] ? number_format($articleReachDisp['reachValue'], 0, ',', '.') : ($articleReachDisp['hasReadableAiReach'] ? 'Belum tersedia' : 'Belum dinilai AI')) }},
                                            {{ Js::from($articleReachDisp['hasOfficialProjectReach'] ? $articleReachDisp['levelLabel'] : ($articleReachDisp['hasReadableAiReach'] ? 'Belum tersedia' : '')) }},
                                            {{ Js::from($articleReachDisp['hasOfficialProjectReach'] ? $articleReachDisp['scoreValue'] . '/10' : ($articleReachDisp['hasReadableAiReach'] ? 'Belum tersedia' : 'Belum dinilai AI')) }},
                                            {{ Js::from($article->published_at ? \Carbon\Carbon::parse($article->published_at)->format('d/m/y') : 'Baru saja') }}
                                        )" 
                                        class="text-[11px] font-bold text-white bg-[#1fa387] hover:bg-[#178a70] px-4 py-2 rounded-xl transition-colors shadow-sm flex items-center gap-1 cursor-pointer"
                                    >
                                        Selengkapnya
                                        <span class="material-symbols-outlined text-[12px]">arrow_forward</span>
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="col-span-full flex flex-col items-center justify-center py-20 bg-slate-50/50 rounded-3xl border border-dashed border-slate-200">
                                <div class="w-16 h-16 rounded-2xl bg-white border border-slate-100 flex items-center justify-center shadow-sm mb-4">
                                    <span class="material-symbols-outlined text-3xl text-slate-300">article</span>
                                </div>
                                <h3 class="text-sm font-bold text-slate-700 mb-1">Belum Ada Konten</h3>
                                <p class="text-xs text-slate-500 font-medium">Data konten untuk proyek ini belum tersedia.</p>
                            </div>
                        @endforelse
                    </div>

                    <!-- Infinite Scroll / Load More -->
                    @php
                        $totalArticlesCount = $this->applyActiveFilters($this->projectArticlesQuery())->count();
                    @endphp

                    @if($articlesList->count() < $totalArticlesCount)
                        <div x-intersect="$wire.loadMore()" class="py-6 text-center text-xs text-slate-500 font-medium flex items-center justify-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-[#1fa387]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Memuat data lainnya...</span>
                        </div>
                    @else
                        @if($articlesList->count() > 0)
                        <div class="py-6 mt-4 border-t border-slate-100 text-center text-xs text-slate-400 font-medium">
                            <p class="text-slate-500 font-semibold">Semua konten telah dimuat</p>
                        </div>
                        @endif
                    @endif
                </section>

            @elseif($this->isTab('sumber'))
                <section class="flex-1 min-w-0 space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-bold text-slate-900 mb-0.5">Sumber Data</h2>
                            <p class="text-xs text-slate-500">Statistik sumber portal dan media sosial yang terkumpul.</p>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-3xl border border-slate-200 p-12 text-center shadow-[0_8px_30px_-5px_rgba(0,0,0,0.03)] flex flex-col items-center justify-center min-h-[400px]">
                        <div class="w-24 h-24 rounded-full bg-blue-50 border-8 border-white shadow-md flex items-center justify-center mb-6">
                            <span class="material-symbols-outlined text-4xl text-blue-500">database</span>
                        </div>
                        <h3 class="text-lg font-black text-slate-900 mb-2">Manajemen Sumber Global</h3>
                        <p class="text-sm text-slate-500 font-medium mb-8 max-w-md mx-auto leading-relaxed">
                            Pantau dan kelola seluruh portal berita serta akun media sosial yang sedang dilacak oleh sistem.
                        </p>
                        @if($this->isAdmin())
                            <a href="{{ route('admin.news-sources') }}" wire:navigate class="inline-flex items-center justify-center gap-2.5 px-8 py-3.5 bg-blue-600 text-white font-extrabold text-sm rounded-xl hover:bg-blue-700 transition shadow-[0_4px_15px_rgba(37,99,235,0.2)] hover:shadow-[0_6px_20px_rgba(37,99,235,0.3)] hover:-translate-y-0.5">
                                <span class="material-symbols-outlined text-[18px]">settings</span>
                                Kelola Sumber Sekarang
                            </a>
                        @else
                            <div class="bg-slate-50 border border-slate-200 text-slate-600 px-6 py-4 rounded-xl font-bold text-sm flex items-center justify-center gap-3 w-full max-w-sm shadow-sm">
                                <span class="material-symbols-outlined text-amber-500 text-xl">lock</span>
                                Diatur oleh Administrator
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            <!-- Right Side Filter Panel -->
            <aside 
                x-show="!showViralModal && !detailModalOpen"
                class="w-80 bg-white border border-slate-200 rounded-2xl p-6 shadow-[0_4px_20px_-2px_rgba(0,0,0,0.03)] space-y-6 sticky top-24 z-30 self-start max-h-[calc(100vh-7rem)] overflow-y-auto"
            >
                <h4 class="text-sm font-bold text-slate-950 uppercase tracking-wider border-b border-slate-100 pb-3">Filter Panel</h4>

                @if($this->isTab('laporan'))
                    <!-- Laporan Filter Panel (matching screenshot) -->
                    <div class="space-y-1.5 text-left">
                        <label class="text-xs font-bold text-slate-650">Periode</label>
                        <div class="relative">
                            <select 
                                class="w-full bg-[#f8f9fa] border border-slate-200 rounded-xl px-4 py-2.5 text-xs text-slate-800 focus:outline-none focus:border-[#1fa387] focus:bg-white transition cursor-pointer appearance-none font-semibold"
                            >
                                <option value="daily">Harian</option>
                                <option value="weekly">Mingguan</option>
                                <option value="monthly">Bulanan</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- Search Panel -->
                    <div class="space-y-1.5">
                        <label class="text-sm font-bold text-slate-700">Pencarian</label>
                        <div class="relative">
                            <input 
                                wire:model.live.debounce.300ms="search" 
                                type="text" 
                                placeholder="Cari..."
                                class="w-full bg-[#f8f9fa] border border-slate-200 focus:border-primary focus:ring-1 focus:ring-primary rounded-xl pl-3 pr-9 py-2.5 text-xs text-slate-800 placeholder-[#727785] transition"
                            >
                            <svg class="w-4 h-4 text-slate-400 absolute right-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>

                    <!-- Date Range Selector (Triggers Custom DatePicker Modal) -->
                    <div class="space-y-1.5">
                        <label class="text-sm font-bold text-slate-700">Rentang Tanggal</label>
                        <div class="relative">
                            <button 
                                type="button"
                                wire:click="$set('showDatePicker', true)"
                                class="w-full bg-[#f8f9fa] border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-700 hover:bg-slate-50 transition flex items-center justify-between font-semibold"
                            >
                                <span>{{ $startDate ? \Carbon\Carbon::parse($startDate)->format('d/m/Y') . ($endDate && $endDate !== $startDate ? ' - ' . \Carbon\Carbon::parse($endDate)->format('d/m/Y') : '') : 'Semua Waktu' }}</span>
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Sumber Checklist -->
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <label class="text-sm font-bold text-slate-700">Sumber Data</label>
                            <button wire:click="$set('selectedSources', ['Instagram', 'Tiktok', 'Facebook', 'News'])" class="text-xs text-[#1fa387] hover:underline font-bold">Pilih Semua</button>
                        </div>
                        <div class="space-y-2.5">
                            <!-- Instagram -->
                            <label class="flex items-center justify-between cursor-pointer group py-0.5 gap-3">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <input wire:model.live="selectedSources" value="Instagram" type="checkbox" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387] w-4 h-4">
                                    <div class="w-7 h-7 bg-gradient-to-br from-purple-600 via-pink-500 to-orange-400 rounded-lg flex items-center justify-center shadow-sm shadow-pink-500/15">
                                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5" stroke-linecap="round"></line></svg>
                                    </div>
                                    <span class="text-sm text-slate-700 group-hover:text-slate-900 font-semibold transition truncate">Instagram</span>
                                </div>
                                <span class="text-xs font-bold text-slate-400 tabular-nums w-6 text-right flex-shrink-0">{{ $counts['sources']['Instagram'] ?? 0 }}</span>
                            </label>

                            <!-- Tiktok -->
                            <label class="flex items-center justify-between cursor-pointer group py-0.5 gap-3">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <input wire:model.live="selectedSources" value="Tiktok" type="checkbox" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387] w-4 h-4">
                                    <div class="w-7 h-7 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 rounded-lg flex items-center justify-center shadow-sm shadow-slate-900/15">
                                        <svg class="w-3.5 h-3.5 fill-current text-white" viewBox="0 0 24 24"><path d="M12.525.01c1.306-.022 2.615-.011 3.921-.012.08 1.836 1.011 3.5 2.501 4.485.006 1.341-.004 2.683-.004 4.024-1.57-.107-3.067-.932-3.955-2.247-.008 2.827-.003 5.657-.005 8.486-.098 3.546-3.13 6.643-6.726 6.467-3.526-.067-6.523-3.18-6.241-6.722.215-3.327 3.012-6.104 6.347-5.992v4.06c-1.393-.16-2.775.76-3.085 2.112-.397 1.488.583 3.125 2.1 3.328 1.455.234 2.924-.766 3.14-2.224.048-2.617.02-5.237.03-7.856.002-3.834-.002-7.67.002-11.504z"></path></svg>
                                    </div>
                                    <span class="text-sm text-slate-700 group-hover:text-slate-900 font-semibold transition truncate">TikTok</span>
                                </div>
                                <span class="text-xs font-bold text-slate-400 tabular-nums w-6 text-right flex-shrink-0">{{ $counts['sources']['Tiktok'] ?? 0 }}</span>
                            </label>

                            <!-- Facebook -->
                            <label class="flex items-center justify-between cursor-pointer group py-0.5 gap-3">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <input wire:model.live="selectedSources" value="Facebook" type="checkbox" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387] w-4 h-4">
                                    <div class="w-7 h-7 bg-gradient-to-br from-blue-600 to-blue-700 rounded-lg flex items-center justify-center shadow-sm shadow-blue-600/15">
                                        <svg class="w-3.5 h-3.5 fill-current text-white" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"></path></svg>
                                    </div>
                                    <span class="text-sm text-slate-700 group-hover:text-slate-900 font-semibold transition truncate">Facebook</span>
                                </div>
                                <span class="text-xs font-bold text-slate-400 tabular-nums w-6 text-right flex-shrink-0">{{ $counts['sources']['Facebook'] ?? 0 }}</span>
                            </label>

                            <!-- News -->
                            <label class="flex items-center justify-between cursor-pointer group py-0.5 gap-3">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <input wire:model.live="selectedSources" value="News" type="checkbox" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387] w-4 h-4">
                                    <div class="w-7 h-7 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-lg flex items-center justify-center shadow-sm shadow-emerald-500/15">
                                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path></svg>
                                    </div>
                                    <span class="text-sm text-slate-700 group-hover:text-slate-900 font-semibold transition truncate">Portal News</span>
                                </div>
                                <span class="text-xs font-bold text-slate-400 tabular-nums w-6 text-right flex-shrink-0">{{ $counts['sources']['News'] ?? 0 }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Sentimen Checklist -->
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <label class="text-sm font-bold text-slate-700">Sentimen</label>
                            <button wire:click="$set('selectedSentiment', ['positive', 'neutral', 'negative'])" class="text-xs text-[#1fa387] hover:underline font-bold">Pilih Semua</button>
                        </div>
                        <div class="space-y-2.5">
                            <!-- Positive -->
                            <label class="flex items-center justify-between cursor-pointer group py-0.5 gap-3">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <input wire:model.live="selectedSentiment" value="positive" type="checkbox" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387] w-4 h-4">
                                    <span class="w-3 h-3 rounded-full inline-block bg-emerald-500 shadow-sm shadow-emerald-500/30"></span>
                                    <span class="text-sm text-slate-700 group-hover:text-slate-900 font-semibold transition truncate">Positif</span>
                                </div>
                                <span class="text-xs font-bold text-slate-400 tabular-nums w-6 text-right flex-shrink-0">{{ $counts['sentiments']['positive'] ?? 0 }}</span>
                            </label>

                            <!-- Neutral -->
                            <label class="flex items-center justify-between cursor-pointer group py-0.5 gap-3">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <input wire:model.live="selectedSentiment" value="neutral" type="checkbox" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387] w-4 h-4">
                                    <span class="w-3 h-3 rounded-full inline-block bg-slate-400 shadow-sm shadow-slate-400/30"></span>
                                    <span class="text-sm text-slate-700 group-hover:text-slate-900 font-semibold transition truncate">Netral</span>
                                </div>
                                <span class="text-xs font-bold text-slate-400 tabular-nums w-6 text-right flex-shrink-0">{{ $counts['sentiments']['neutral'] ?? 0 }}</span>
                            </label>

                            <!-- Negative -->
                            <label class="flex items-center justify-between cursor-pointer group py-0.5 gap-3">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <input wire:model.live="selectedSentiment" value="negative" type="checkbox" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387] w-4 h-4">
                                    <span class="w-3 h-3 rounded-full inline-block bg-red-500 shadow-sm shadow-red-500/30"></span>
                                    <span class="text-sm text-slate-700 group-hover:text-slate-900 font-semibold transition truncate">Negatif</span>
                                </div>
                                <span class="text-xs font-bold text-slate-400 tabular-nums w-6 text-right flex-shrink-0">{{ $counts['sentiments']['negative'] ?? 0 }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="space-y-3 pt-4 border-t border-slate-100">
                        <div class="flex justify-between items-center">
                            <label class="text-sm font-bold text-slate-700">Risiko AI <span class="text-[10px] font-normal text-slate-400 ml-1">(Risk global sementara)</span></label>
                        </div>
                        <div class="space-y-2.5">
                            <label class="flex items-center justify-between group py-0.5 gap-3">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <span class="w-3 h-3 rounded-full inline-block bg-slate-300 shadow-sm shadow-slate-300/30"></span>
                                    <span class="text-sm text-slate-700 font-semibold truncate">Rendah</span>
                                </div>
                                <span class="text-xs font-bold text-slate-400 tabular-nums w-6 text-right flex-shrink-0">{{ $counts['risks']['low'] ?? 0 }}</span>
                            </label>
                            <label class="flex items-center justify-between group py-0.5 gap-3">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <span class="w-3 h-3 rounded-full inline-block bg-amber-400 shadow-sm shadow-amber-400/30"></span>
                                    <span class="text-sm text-slate-700 font-semibold truncate">Sedang</span>
                                </div>
                                <span class="text-xs font-bold text-slate-400 tabular-nums w-6 text-right flex-shrink-0">{{ $counts['risks']['medium'] ?? 0 }}</span>
                            </label>
                            <label class="flex items-center justify-between group py-0.5 gap-3">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <span class="w-3 h-3 rounded-full inline-block bg-rose-500 shadow-sm shadow-rose-500/30"></span>
                                    <span class="text-sm text-slate-700 font-semibold truncate">Tinggi</span>
                                </div>
                                <span class="text-xs font-bold text-slate-400 tabular-nums w-6 text-right flex-shrink-0">{{ $counts['risks']['high'] ?? 0 }}</span>
                            </label>
                            <label class="flex items-center justify-between group py-0.5 gap-3">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <span class="w-3 h-3 rounded-full inline-block bg-purple-600 shadow-sm shadow-purple-600/30"></span>
                                    <span class="text-sm text-slate-700 font-semibold truncate">Kritis</span>
                                </div>
                                <span class="text-xs font-bold text-slate-400 tabular-nums w-6 text-right flex-shrink-0">{{ $counts['risks']['critical'] ?? 0 }}</span>
                            </label>
                        </div>
                    </div>
                @endif
            </aside>

            <button
                type="button"
                x-show="scrolledDown"
                x-transition.opacity
                @click="scrollToTop()"
                class="fixed bottom-6 right-6 z-50 inline-flex items-center gap-2 rounded-full bg-[#1fa387] px-4 py-3 text-xs font-black text-white shadow-[0_10px_30px_rgba(31,163,135,0.28)] hover:bg-[#178a70] hover:shadow-[0_12px_34px_rgba(31,163,135,0.36)] transition"
                style="display: none;"
                aria-label="Kembali ke atas"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"></path>
                </svg>
                Kembali ke atas
            </button>
        </div>
    </div>

    <!-- Date Range Picker Modal -->
    <div 
        x-data="{ 
            show: @entangle('showDatePicker'),
            localStart: @entangle('startDate'), 
            localEnd: @entangle('endDate'),
            month: new Date().getMonth(),
            year: new Date().getFullYear(),
            monthNames: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'],
            init() {
                if (this.localStart) {
                    let d = new Date(this.localStart);
                    this.month = d.getMonth();
                    this.year = d.getFullYear();
                }
            },
            get no_of_days() {
                let daysInMonth = new Date(this.year, this.month + 1, 0).getDate();
                return Array.from({length: daysInMonth}, (_, i) => i + 1);
            },
            get blankdays() {
                let blankdays = new Date(this.year, this.month, 1).getDay();
                return Array.from({length: blankdays}, (_, i) => i + 1);
            },
            formatDate(dateObj) {
                let d = dateObj.getDate();
                let m = dateObj.getMonth() + 1;
                let y = dateObj.getFullYear();
                return y + '-' + (m <= 9 ? '0' + m : m) + '-' + (d <= 9 ? '0' + d : d);
            },
            formatDisplayDate(dateStr) {
                if (!dateStr) return '--';
                let parts = dateStr.split('-');
                return parts[2] + '/' + parts[1] + '/' + parts[0];
            },
            selectDate(day) {
                if (this.isFuture(day)) return;
                let selected = new Date(this.year, this.month, day);
                let selectedStr = this.formatDate(selected);
                if (!this.localStart || (this.localStart && this.localEnd)) {
                    this.localStart = selectedStr;
                    this.localEnd = null;
                } else if (this.localStart && !this.localEnd) {
                    if (selectedStr < this.localStart) {
                        this.localEnd = this.localStart;
                        this.localStart = selectedStr;
                    } else {
                        this.localEnd = selectedStr;
                    }
                }
            },
            isStart(day) {
                return this.localStart === this.formatDate(new Date(this.year, this.month, day));
            },
            isEnd(day) {
                return this.localEnd === this.formatDate(new Date(this.year, this.month, day));
            },
            isInRange(day) {
                if (this.localStart && this.localEnd) {
                    let d = this.formatDate(new Date(this.year, this.month, day));
                    return d > this.localStart && d < this.localEnd;
                }
                return false;
            },
            isFuture(day) {
                let d = new Date(this.year, this.month, day);
                let today = new Date();
                today.setHours(0,0,0,0);
                return d > today;
            },
            applyFilter() {
                $wire.set('startDate', this.localStart);
                $wire.set('endDate', this.localEnd ? this.localEnd : this.localStart);
                $wire.set('showDatePicker', false);
            },
            setPeriod(days) {
                let end = new Date();
                let start = new Date(end);
                if (days === 'year') {
                    start = new Date(end.getFullYear() - 1, 0, 1);
                    end = new Date(end.getFullYear() - 1, 11, 31);
                } else {
                    start.setDate(end.getDate() - days + 1);
                }
                this.localEnd = this.formatDate(end);
                this.localStart = this.formatDate(start);
                this.month = start.getMonth();
                this.year = start.getFullYear();
            }
        }"
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[60] overflow-y-auto flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
        style="display: none;"
    >
        <div 
            @click.away="$wire.set('showDatePicker', false)" 
            class="bg-white w-full max-w-[700px] rounded-3xl overflow-hidden shadow-2xl flex border border-slate-200"
        >
            <!-- Left Panel (PERIODE Presets) -->
            <div class="w-[200px] border-r border-slate-100 bg-[#FAFBFD] p-6 text-left space-y-4">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-2">PERIODE</span>
                <div class="flex flex-col gap-2.5">
                    <button type="button" @click="setPeriod(1)" class="text-xs text-slate-500 hover:text-[#1fa387] hover:font-bold text-left font-semibold">Hari ini</button>
                    <button type="button" @click="setPeriod(2); localEnd = localStart" class="text-xs text-slate-500 hover:text-[#1fa387] hover:font-bold text-left font-semibold">Kemarin</button>
                    <button type="button" @click="setPeriod(7)" class="text-xs text-slate-500 hover:text-[#1fa387] hover:font-bold text-left font-semibold">7 hari terakhir</button>
                    <button type="button" @click="setPeriod(30)" class="text-xs text-slate-500 hover:text-[#1fa387] hover:font-[#1fa387] hover:font-bold text-left font-semibold">30 hari terakhir</button>
                    <button type="button" @click="setPeriod(90)" class="text-xs text-slate-500 hover:text-[#1fa387] hover:font-bold text-left font-semibold">3 bulan terakhir</button>
                    <button type="button" @click="setPeriod('year')" class="text-xs text-slate-500 hover:text-[#1fa387] hover:font-bold text-left font-semibold">Tahun lalu</button>
                </div>
            </div>

            <!-- Right Panel (Calendar Grid) -->
            <div class="flex-grow p-6 flex flex-col justify-between">
                <div>
                    <!-- Calendar Header -->
                    <div class="flex justify-between items-center mb-6">
                        <h4 class="text-sm font-bold text-slate-800">Tanggal khusus</h4>
                        <span class="px-3 py-1 bg-[#FAFBFD] text-xs font-semibold text-slate-650 rounded-full border border-slate-200">
                            <span x-text="formatDisplayDate(localStart)"></span>
                            <span x-show="localEnd && localEnd !== localStart" x-text="' - ' + formatDisplayDate(localEnd)"></span>
                        </span>
                    </div>

                    <!-- Calendar Body (Juni 2026) -->
                    <div class="space-y-4">
                        <div class="flex justify-between items-center px-2">
                            <span class="text-xs font-bold text-slate-700" x-text="monthNames[month] + ' ' + year"></span>
                            <div class="flex gap-2 text-slate-400">
                                <span @click="if(month===0){year--;month=11}else{month--}" class="material-symbols-outlined text-sm cursor-pointer hover:text-slate-750">chevron_left</span>
                                <span @click="if(month===11){year++;month=0}else{month++}" class="material-symbols-outlined text-sm cursor-pointer hover:text-slate-750">chevron_right</span>
                            </div>
                        </div>

                        <!-- Days of Week Headers -->
                        <div class="grid grid-cols-7 text-center text-[10px] font-bold text-slate-400">
                            <span>M</span><span>S</span><span>S</span><span>R</span><span>K</span><span>J</span><span>S</span>
                        </div>

                        <!-- Interactive Days Grid -->
                        <div class="grid grid-cols-7 gap-y-2 text-center text-xs font-semibold text-slate-750">
                            <template x-for="blankday in blankdays">
                                <div class="w-8 h-8"></div>
                            </template>
                            <template x-for="day in no_of_days" :key="day">
                                <div class="flex items-center justify-center">
                                    <button 
                                        type="button"
                                        @click="selectDate(day)"
                                        :disabled="isFuture(day)"
                                        class="w-8 h-8 rounded-full flex items-center justify-center transition-all font-bold text-xs"
                                        :class="{
                                            'bg-[#1fa387] text-white': isStart(day) || isEnd(day),
                                            'bg-[#1fa387]/15 text-[#1fa387]': isInRange(day),
                                            'hover:bg-slate-100 text-slate-700 cursor-pointer': !isStart(day) && !isEnd(day) && !isInRange(day) && !isFuture(day),
                                            'opacity-30 cursor-not-allowed text-slate-400': isFuture(day)
                                        }"
                                    >
                                        <span x-text="day"></span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Footer Action Controls -->
                <div class="flex justify-between items-center gap-3 pt-6 border-t border-slate-100">
                    <button 
                        type="button" 
                        @click="$wire.set('startDate', null); $wire.set('endDate', null); $wire.set('showDatePicker', false);" 
                        class="px-4 py-2 text-slate-500 hover:text-[#1fa387] font-bold text-xs transition underline-offset-2 hover:underline"
                    >
                        Semua Waktu
                    </button>
                    <div class="flex gap-3">
                        <button 
                            type="button" 
                            @click="$wire.set('showDatePicker', false)" 
                            class="px-5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-full text-xs transition"
                        >
                            Batal
                        </button>
                        <button 
                            type="button" 
                            @click="applyFilter()" 
                            class="px-5 py-2 bg-[#1fa387] hover:bg-[#1a8b73] text-white font-bold rounded-full text-xs transition"
                        >
                            Terapkan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Viral Articles Modal (Alpine.js) -->
    <div 
        x-show="showViralModal" 
        class="fixed inset-0 z-[99] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-cloak
        @keydown.escape.window="showViralModal = false"
        style="display: none;"
    >
        <div 
            class="bg-slate-50 rounded-3xl border border-slate-200 max-w-2xl w-full p-6 shadow-2xl text-left relative flex flex-col h-[75vh] max-h-[75vh]"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-4"
            @click.away="showViralModal = false"
        >
            <!-- Header of modal -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-200 flex-shrink-0">
                <div>
                    <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                        <svg class="w-5 h-5 text-{{ $w['viral_color'] }}-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                        Penyebab Kondisi: <span class="text-{{ $w['viral_color'] }}-600 font-extrabold">{{ $w['viral_status'] }}</span>
                    </h3>
                </div>
                <button 
                    @click="showViralModal = false" 
                    class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-650 flex items-center justify-center transition-colors cursor-pointer"
                >
                    ✕
                </button>
            </div>

            <!-- Modal Content (Articles List) -->
            <div class="flex-grow overflow-y-auto py-6 pr-2 space-y-4">
                @php $viralArticles = $this->getViralArticles(); @endphp
                @if($viralArticles->isEmpty())
                    <div class="text-center py-12 text-slate-400 font-medium">
                        Belum ada berita/penyebutan dalam 7 hari terakhir.
                    </div>
                @else
                    @foreach($viralArticles as $article)
                        @php
                            $analysis = $article->aiAnalysisResult;
                            $hasReadableAiReach = (bool) ($analysis && $analysis->hasCompleteOfficialAiResult());
                            $sentimentColor = '#64748b'; // Neutral default
                            $sentimentLabel = 'Netral';
                            if ($this->getValidAiResult($article)?->sentiment === 'positive') {
                                $sentimentColor = '#10b981';
                                $sentimentLabel = 'Positif';
                            } elseif ($this->getValidAiResult($article)?->sentiment === 'negative') {
                                $sentimentColor = '#ef4444';
                                $sentimentLabel = 'Negatif';
                            }
                            $srcLowerMain = strtolower($article->source_name);
                            $projectReachDisplay = $this->getProjectReachDisplayData($article);
                        @endphp
                        <div 
                            class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm transition-all hover:shadow-md cursor-pointer border-l-4"
                            style="border-left-color: {{ $sentimentColor }}"
                            @click="showViralModal = false; openedFromViral = true; openDetail(
                                '{{ addslashes($article->title) }}',
                                '{{ addslashes($article->source_name) }}',
                                '{{ $article->published_at ? \Carbon\Carbon::parse($article->published_at)->format('d M Y, H:i') . ' (' . \Carbon\Carbon::parse($article->published_at)->diffForHumans() . ')' : 'Baru saja' }}',
                                '{{ addslashes($article->url) }}',
                                '{{ addslashes($article->content) }}',
                                '{{ $analysis ? addslashes($analysis->ai_summary) : '' }}',
                                '{{ $analysis ? addslashes($analysis->ai_recommendation) : '' }}',
                                '{{ $article->sentiment }}',
                                '{{ $article->category }}',
                                '{{ $projectReachDisplay['reachValue'] ?? '0' }}',
                                '{{ $projectReachDisplay['levelLabel'] ?? '-' }}',
                                '{{ $projectReachDisplay['scoreValue'] ?? '0' }}',
                                '{{ $article->published_at ? \Carbon\Carbon::parse($article->published_at)->format('d/m/y') : 'Baru saja' }}'
                            )"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-bold uppercase tracking-widest text-[#1fa387]">{{ $article->source_name }}</span>
                                <div class="flex items-center gap-2">
                                    <a 
                                        href="{{ $article->url }}" 
                                        target="_blank" 
                                        @click.stop 
                                        class="inline-flex items-center gap-1.5 text-[9px] font-bold text-blue-600 hover:text-blue-800 hover:underline bg-blue-50/80 px-2 py-0.5 rounded-lg border border-blue-100 transition"
                                    >
                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                        <span>Buka Berita</span>
                                    </a>
                                    <span class="text-[8px] font-bold px-2 py-0.5 rounded-full {{ $article->sentiment === 'positive' ? 'bg-emerald-50 text-emerald-700' : ($article->sentiment === 'negative' ? 'bg-rose-50 text-rose-700' : 'bg-slate-50 text-slate-700') }}">{{ $sentimentLabel }}</span>
                                </div>
                            </div>
                            <h4 class="text-sm font-bold text-slate-800 leading-snug">{{ $article->title }}</h4>
                            <p class="text-xs text-slate-500 mt-2 line-clamp-2">{{ $this->formatArticleExcerpt($article, 140) }}</p>
                        </div>
                    @endforeach
                @endif
            </div>

            <!-- Footer of modal -->
            <div class="pt-4 border-t border-slate-200 flex justify-end flex-shrink-0">
                <button 
                    @click="showViralModal = false" 
                    class="px-5 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-full text-xs transition"
                >
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- Beautiful Detail Modal (Alpine.js) -->
    <div 
        x-show="detailModalOpen" 
        class="fixed inset-0 z-[100] overflow-y-auto flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-cloak
        @keydown.escape.window="detailModalOpen = false; if (openedFromViral) { showViralModal = true; openedFromViral = false; }"
        style="display: none;"
    >
        <div 
            class="bg-white rounded-3xl border border-slate-200 max-w-7xl w-full p-6 md:p-10 shadow-2xl text-left relative flex flex-col h-[90vh] max-h-[90vh]"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-4"
            @click.away="detailModalOpen = false; if (openedFromViral) { showViralModal = true; openedFromViral = false; }"
            style="position: relative;"
        >
            <!-- Close Button (Positioned absolute with robust inline style) -->
            <button 
                @click="detailModalOpen = false; if (openedFromViral) { showViralModal = true; openedFromViral = false; }" 
                class="bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition-colors cursor-pointer shadow-sm rounded-full"
                style="position: absolute; right: 24px; top: 24px; width: 36px; height: 36px; font-weight: bold; border: none; font-size: 14px; z-index: 50;"
            >
                ✕
            </button>

            <!-- Header (Premium Profile Layout) -->
            <div class="border-b border-slate-100 pb-6 mb-2 flex-shrink-0" style="padding-right: 48px;">
                <div class="flex items-center gap-4 mb-4">
                    <!-- Source Icon (Dynamic Favicon/Fallback in Modal) -->
                    <div class="w-10 h-10 rounded-2xl bg-slate-50 flex items-center justify-center border border-slate-200 overflow-hidden shadow-sm shrink-0">
                        <img 
                            :src="'https://www.google.com/s2/favicons?sz=64&domain=' + (
                                detailSource.toLowerCase().includes('facebook') || detailSource.toLowerCase() === 'fb' ? 'facebook.com' :
                                (detailSource.toLowerCase().includes('instagram') || detailSource.toLowerCase() === 'ig' ? 'instagram.com' :
                                (detailSource.toLowerCase().includes('tiktok') || detailSource.toLowerCase() === 'tk' ? 'tiktok.com' :
                                (detailSource.toLowerCase().includes('twitter') || detailSource.toLowerCase() === 'x.com' ? 'x.com' :
                                (detailSource.toLowerCase().includes('portal berau') || detailSource.toLowerCase().includes('portalberau') ? 'portalberau.online' :
                                (detailSource.includes('.') ? detailSource : detailSource + '.com')))))
                            )"
                            x-on:error="$el.src = 'data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 stroke=%22%231fa387%22 stroke-width=%222.2%22 viewBox=%220 0 24 24%22><path stroke-linecap=%22round%22 stroke-linejoin=%22round%22 d=%22M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z%22></path></svg>'"
                            class="w-5 h-5 object-contain"
                            alt="Logo"
                        />
                    </div>
                    
                    <div class="flex flex-col">
                        <div class="flex items-center gap-2">
                            <h4 class="text-sm font-bold text-slate-800 tracking-tight" x-text="detailSource"></h4>
                            <span class="w-1 h-1 rounded-full bg-slate-300"></span>
                            <p class="text-[10px] font-semibold text-slate-400" x-text="detailDate"></p>
                        </div>
                        <div class="flex items-center gap-1.5 mt-1.5 flex-wrap">
                            <span 
                                class="px-2.5 py-1 text-[9px] font-bold rounded-xl border"
                                :class="{
                                    'bg-emerald-50 border-emerald-100 text-emerald-600': detailSentiment === 'positive',
                                    'bg-rose-50 border-rose-100 text-rose-600': detailSentiment === 'negative',
                                    'bg-slate-50 border-slate-200/80 text-slate-600': detailSentiment !== 'positive' && detailSentiment !== 'negative'
                                }"
                                x-text="detailSentiment === 'positive' ? 'Positif' : (detailSentiment === 'negative' ? 'Negatif' : 'Netral')"
                            ></span>
                            <span x-show="detailCategory" class="px-2.5 py-1 text-[9px] font-bold bg-slate-50 border border-slate-200/80 text-slate-500 rounded-xl max-w-[150px] truncate" title="Kategori" x-text="detailCategory"></span>
                        </div>
                    </div>
                </div>

                <h3 class="text-xl md:text-2xl font-black text-slate-900 leading-tight mt-1 mb-4" x-text="detailTitle"></h3>

                <!-- Metrics Grid in Modal (Clean 3/5-Column horizontal bar) -->
                <div class="grid gap-2 bg-slate-50/60 rounded-2xl p-4 border border-slate-200/40 mb-5 w-full text-left flex-shrink-0" :class="detailCategory === 'social' ? 'grid-cols-5' : 'grid-cols-3'">
                    <div class="px-1.5 py-0.5">
                        <span class="text-[8px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Jangkauan</span>
                        <div class="flex items-start gap-1 text-slate-800 text-[11px] md:text-xs font-black">
                            <span class="material-symbols-outlined text-[#1fa387] text-[14px] md:text-[15px] mt-0.5">insights</span>
                            <div class="flex flex-col leading-tight">
                                <span x-text="detailReach"></span>
                                <span class="text-[9px] font-semibold text-slate-400 mt-0.5" x-text="detailLevel"></span>
                            </div>
                        </div>
                    </div>
                    <div class="px-1.5 py-0.5 border-l border-slate-200">
                        <span class="text-[8px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Skor</span>
                        <div class="flex items-center gap-1 text-slate-800 text-[11px] md:text-xs font-black">
                            <span class="material-symbols-outlined text-[#1fa387] text-[14px] md:text-[15px]">analytics</span>
                            <span x-text="detailScore"></span>
                        </div>
                    </div>
                    <div class="px-1.5 py-0.5 border-l border-slate-200">
                        <span class="text-[8px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Tanggal</span>
                        <div class="flex items-center gap-1 text-slate-800 text-[11px] md:text-xs font-black">
                            <span class="material-symbols-outlined text-[#1fa387] text-[14px] md:text-[15px]">calendar_month</span>
                            <span x-text="detailFormattedDate"></span>
                        </div>
                    </div>
                    <template x-if="detailCategory === 'social'">
                        <div class="px-1.5 py-0.5 border-l border-slate-200">
                            <span class="text-[8px] font-bold text-slate-400 uppercase tracking-widest block mb-1" x-text="detailSource.toLowerCase() === 'tiktok' ? 'Love' : 'Like'"></span>
                            <div class="flex items-center gap-1 text-slate-800 text-[11px] md:text-xs font-black">
                                <span class="material-symbols-outlined text-[#1fa387] text-[14px] md:text-[15px]" x-text="detailSource.toLowerCase() === 'tiktok' ? 'favorite' : 'thumb_up'"></span>
                                <span x-text="detailLikes"></span>
                            </div>
                        </div>
                    </template>
                    <template x-if="detailCategory === 'social'">
                        <div class="px-1.5 py-0.5 border-l border-slate-200">
                            <span class="text-[8px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Komen</span>
                            <div class="flex items-center gap-1 text-slate-800 text-[11px] md:text-xs font-black">
                                <span class="material-symbols-outlined text-[#1fa387] text-[14px] md:text-[15px]">comment</span>
                                <span x-text="detailComments"></span>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="flex items-center gap-3 mt-1.5 flex-wrap">
                    <a :href="detailUrl" target="_blank" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#1fa387] hover:text-[#17856e] transition-colors hover:underline bg-[#1fa387]/10 px-3 py-1.5 rounded-lg">
                        <span>Baca Artikel Asli</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                    </a>

                    <button 
                        type="button"
                        @click="showAiSummaryModal = !showAiSummaryModal"
                        class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-bold bg-[#1fa387] hover:bg-[#1fa387]/90 text-white rounded-lg transition-all duration-200 cursor-pointer shadow-sm"
                    >
                        <span class="material-symbols-outlined text-[15px] transition-transform duration-200" :class="showAiSummaryModal ? 'rotate-45' : ''">auto_awesome</span>
                        <span>Ringkasan AI</span>
                        <svg class="w-2.5 h-2.5 text-white transition-transform duration-200" :class="showAiSummaryModal ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                </div>
            </div>

            <!-- Collapsible Top AI Summary Panel (Full Width) -->
            <div 
                x-show="showAiSummaryModal"
                x-transition:enter="transition ease-out duration-250"
                x-transition:enter-start="opacity-0 transform -translate-y-3 scale-98"
                x-transition:enter-end="opacity-100 transform translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 transform translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 transform -translate-y-3 scale-98"
                class="mt-4 p-5 bg-gradient-to-r from-[#1fa387]/5 to-emerald-50/20 border border-[#1fa387]/10 rounded-2xl flex flex-col md:flex-row gap-6 shadow-inner flex-shrink-0"
                style="display: none;"
            >
                <div class="w-full md:w-1/2">
                    <h4 class="text-[11px] font-black text-emerald-800 uppercase tracking-widest flex items-center gap-1.5 mb-2">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg>
                        <span>Ringkasan AI</span>
                    </h4>
                    <p class="text-xs md:text-sm text-slate-700 leading-relaxed font-semibold whitespace-pre-line" x-text="detailAiSummary"></p>
                </div>
                <div class="w-full md:w-1/2 border-t md:border-t-0 md:border-l border-emerald-100/40 pt-4 md:pt-0 md:pl-6">
                    <h4 class="text-[11px] font-black text-emerald-800 uppercase tracking-widest flex items-center gap-1.5 mb-2">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        <span>Rekomendasi Tindakan</span>
                    </h4>
                    <p class="text-xs text-slate-650 leading-relaxed whitespace-pre-line" x-text="detailAiRecommendation"></p>
                </div>
            </div>

            <!-- Content Area (Full Page Layout) -->
            <div class="flex flex-col gap-4 mt-6 overflow-hidden flex-grow" style="min-height: 0;">
                <div class="w-full flex flex-col gap-3 overflow-hidden flex-grow">
                    <h4 class="text-[11px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5 flex-shrink-0">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path></svg>
                        <span>Isi Konten Berita</span>
                    </h4>
                    <div class="text-sm md:text-base text-slate-800 leading-relaxed space-y-5 whitespace-pre-line overflow-y-auto flex-grow pr-3 pb-8 font-sans" x-text="detailContent"></div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
