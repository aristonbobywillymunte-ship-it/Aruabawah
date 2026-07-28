<div class="w-full">
<div class="w-full bg-[#f7f9ff] text-slate-800 flex flex-col font-sans"
     x-data="{
         isMobile: window.innerWidth < 900,
         openMobileMenu: false,
         scrollToTop() {
             window.scrollTo({ top: 0, behavior: 'smooth' });
         }
     }"
     x-effect="
         const shouldLock = !isMobile || (typeof detailModalOpen !== 'undefined' && detailModalOpen) || (typeof showViralModal !== 'undefined' && showViralModal) || openMobileMenu;
         document.body.style.overflow = shouldLock ? 'hidden' : '';
         document.documentElement.style.overflow = shouldLock ? 'hidden' : '';
     "
     x-init="window.addEventListener('scroll', () => { scrolledDown = window.scrollY > 700 }, { passive: true }); window.addEventListener('resize', () => { isMobile = window.innerWidth < 900; }); window.addEventListener('open-report-pdf', event => { if (event.detail?.url) window.open(event.detail.url, '_blank'); }); isMobile = window.innerWidth < 900;"
>
    <!-- Top Header -->
    <header class="w-full bg-white border-b border-slate-200 sticky top-0 z-50 flex-shrink-0">
        <div class="max-w-[1400px] mx-auto px-4 sm:px-6 h-16 sm:h-20 flex flex-row flex-nowrap items-center justify-between gap-2 sm:gap-6">
            <!-- Brand -->
            <div class="flex items-center gap-2 sm:gap-6 h-full justify-self-start">
                <!-- Brand Logo Arusbawah -->
                <div class="flex items-center gap-1.5 sm:gap-2.5 font-sans">
                    <!-- Back Arrow on Mobile -->
                    <a href="/" class="md:hidden flex items-center justify-center w-8 h-8 rounded-full bg-slate-50 border border-slate-200 text-slate-600 mr-0.5 sm:mr-1" title="Kembali">
                        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                    </a>
                    
                    <a href="{{ route('home') }}" class="flex items-center gap-1.5 sm:gap-2 cursor-pointer">
                        @if($customLogo = \App\Helpers\AppBrandingHelper::getAppLogoPath())
                            <img src="{{ asset('storage/' . $customLogo) }}" class="h-7 sm:h-8 max-w-[100px] sm:max-w-[120px] object-contain transition-transform hover:scale-105 duration-300">
                        @else
                            <svg width="24" height="24" viewBox="0 0 42 42" fill="none" xmlns="http://www.w3.org/2000/svg" class="transition-transform hover:scale-105 duration-300">
                                <polygon points="21,4 39,38 3,38" fill="none" stroke="#c0392b" stroke-width="4" stroke-linejoin="round"/>
                                <line x1="11" y1="28" x2="31" y2="28" stroke="#c0392b" stroke-width="4" stroke-linecap="round"/>
                            </svg>
                        @endif
                        <div class="flex flex-col text-left">
                            <span class="text-xs sm:text-sm font-black tracking-wider leading-none text-slate-800 uppercase">{{ \App\Helpers\AppBrandingHelper::getAppName() }}</span>
                            <span class="text-[7px] sm:text-[7.5px] font-bold text-slate-400 uppercase tracking-widest leading-none mt-0.5">Media Intelligence</span>
                        </div>
                    </a>
                </div>

            </div>

            <!-- Navigation Links -->
            <nav class="hidden md:flex items-center justify-center gap-6 h-full justify-self-center">
                    <a 
                        href="{{ route('home', ['project' => $this->projectId, 'tab' => base64_encode('penyebutan')]) }}"
                        class="font-bold text-sm px-1 py-5 h-full flex items-center transition-all cursor-pointer border-b-2 {{ $this->isTab('penyebutan') ? 'text-[#1fa387] border-[#1fa387]' : 'text-slate-500 border-transparent hover:text-slate-800' }}"
                    >
                        Penyebutan
                    </a>
                    <a 
                        href="{{ route('home', ['project' => $this->projectId, 'tab' => base64_encode('analisis')]) }}"
                        class="font-bold text-sm px-1 py-5 h-full flex items-center transition-all cursor-pointer border-b-2 {{ $this->isTab('analisis') ? 'text-[#1fa387] border-[#1fa387]' : 'text-slate-500 border-transparent hover:text-slate-800' }}"
                    >
                        Analisis
                    </a>
                    <a 
                        href="{{ route('home', ['project' => $this->projectId, 'tab' => base64_encode('katakunci')]) }}"
                        class="font-bold text-sm px-1 py-5 h-full flex items-center transition-all cursor-pointer border-b-2 {{ $this->isTab('katakunci') ? 'text-[#1fa387] border-[#1fa387]' : 'text-slate-500 border-transparent hover:text-slate-800' }}"
                    >
                        Kata Kunci
                    </a>
                    <a 
                        href="{{ route('home', ['project' => $this->projectId, 'tab' => base64_encode('wawasan')]) }}"
                        class="font-bold text-sm px-1 py-5 h-full flex items-center transition-all cursor-pointer border-b-2 {{ $this->isTab('wawasan') ? 'text-[#1fa387] border-[#1fa387]' : 'text-slate-500 border-transparent hover:text-slate-800' }}"
                    >
                        Wawasan
                    </a>
                    <a 
                        href="{{ route('home', ['project' => $this->projectId, 'tab' => base64_encode('konten')]) }}"
                        class="font-bold text-sm px-1 py-5 h-full flex items-center transition-all cursor-pointer border-b-2 {{ $this->isTab('konten') ? 'text-[#1fa387] border-[#1fa387]' : 'text-slate-500 border-transparent hover:text-slate-800' }}"
                    >
                        Konten
                    </a>
                    <a 
                        href="{{ route('home', ['project' => $this->projectId, 'tab' => base64_encode('sumber')]) }}"
                        class="font-bold text-sm px-1 py-5 h-full flex items-center transition-all cursor-pointer border-b-2 {{ $this->isTab('sumber') ? 'text-[#1fa387] border-[#1fa387]' : 'text-slate-500 border-transparent hover:text-slate-800' }}"
                    >
                        Sumber
                    </a>
                    <a 
                        href="{{ route('home', ['project' => $this->projectId, 'tab' => base64_encode('laporan')]) }}"
                        class="font-bold text-sm px-1 py-5 h-full flex items-center transition-all cursor-pointer border-b-2 {{ $this->isTab('laporan') ? 'text-[#1fa387] border-[#1fa387]' : 'text-slate-500 border-transparent hover:text-slate-800' }}"
                    >
                        Laporan
                    </a>
            </nav>
            <!-- User Profile & Add Notification -->
            <div class="flex shrink-0 items-center justify-self-end gap-3">
                <livewire:notification-dropdown :project-id="$this->getDecodedProjectId()" />
                
                <!-- Desktop Profile -->
                <div class="hidden md:block relative" x-data="{ openProfile: false }">
                    <button
                        type="button"
                        @click="openProfile = !openProfile"
                        class="flex items-center gap-1.5 sm:gap-3 bg-slate-50 border border-slate-200 rounded-full p-1 sm:pr-3 cursor-pointer hover:bg-slate-100 transition-colors active:scale-95"
                    >
                        <div class="w-7 h-7 rounded-full bg-slate-200 flex items-center justify-center">
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                            </svg>
                        </div>
                        <span class="hidden sm:inline text-xs font-medium text-slate-600">{{ auth()->user()?->email ?? 'Guest' }}</span>
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

                <!-- Burger Button (Mobile Menu Trigger) -->
                <button 
                    type="button" 
                    @click="openMobileMenu = !openMobileMenu"
                    class="md:hidden flex items-center justify-center w-10 h-10 rounded-full bg-slate-50 border border-slate-200 text-slate-600 hover:bg-slate-100 hover:text-slate-800 transition active:scale-95 cursor-pointer"
                    title="Menu Navigasi"
                >
                    <span class="material-symbols-outlined text-[20px]" x-text="openMobileMenu ? 'close' : 'menu'">menu</span>
                </button>
            </div>
        </div>
    </header>

    <!-- Mobile Sidebar Drawer Menu -->
    <div 
        x-show="openMobileMenu"
        class="fixed inset-0 z-50 md:hidden flex justify-end"
        style="display: none;"
    >
        <!-- Backdrop -->
        <div 
            x-show="openMobileMenu"
            x-transition:enter="transition-opacity ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="openMobileMenu = false"
            class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm"
        ></div>

        <!-- Sidebar Drawer Content -->
        <div 
            x-show="openMobileMenu"
            x-transition:enter="transition-transform ease-out duration-300"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition-transform ease-in duration-200"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="relative w-80 max-w-xs bg-white h-full shadow-2xl flex flex-col p-6 space-y-6 z-10 text-left border-l border-slate-100"
        >
            <!-- Drawer Header -->
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <span class="font-extrabold text-sm text-slate-800 uppercase tracking-wider">Navigasi Proyek</span>
                <button 
                    type="button" 
                    @click="openMobileMenu = false"
                    class="w-8 h-8 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center text-slate-500 hover:text-slate-800 transition cursor-pointer"
                >
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>

            <!-- Tabs Navigation -->
            <nav class="flex-grow flex flex-col gap-2 overflow-y-auto">
                @foreach([
                    ['key' => 'penyebutan', 'label' => 'Penyebutan', 'icon' => 'feed'],
                    ['key' => 'analisis', 'label' => 'Analisis', 'icon' => 'analytics'],
                    ['key' => 'katakunci', 'label' => 'Kata Kunci', 'icon' => 'key'],
                    ['key' => 'wawasan', 'label' => 'Wawasan', 'icon' => 'auto_awesome'],
                    ['key' => 'konten', 'label' => 'Konten', 'icon' => 'description'],
                    ['key' => 'sumber', 'label' => 'Sumber', 'icon' => 'source'],
                    ['key' => 'laporan', 'label' => 'Laporan', 'icon' => 'assignment']
                ] as $tab)
                    <a 
                        href="{{ route('home', ['project' => $this->projectId, 'tab' => base64_encode($tab['key'])]) }}"
                        class="flex items-center gap-3.5 rounded-2xl px-4 py-3.5 text-xs font-bold transition-all cursor-pointer {{ $this->isTab($tab['key']) ? 'bg-[#1fa387]/10 text-[#1fa387]' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}"
                        @click="openMobileMenu = false"
                    >
                        <span class="material-symbols-outlined text-[18px]">{{ $tab['icon'] }}</span>
                        <span>{{ $tab['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <!-- Mobile Profile / Logout -->
            <div class="border-t border-slate-100 pt-4 space-y-3.5">
                <div class="flex items-center gap-3 bg-slate-50 rounded-2xl p-3 border border-slate-200/50">
                    <div class="w-8 h-8 rounded-full bg-slate-200 flex items-center justify-center">
                        <span class="material-symbols-outlined text-slate-500 text-base">person</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Pengguna</div>
                        <div class="text-xs font-bold text-slate-700 truncate">{{ auth()->user()?->email ?? 'Guest' }}</div>
                    </div>
                </div>
                <a wire:navigate href="{{ route('password.change') }}" class="flex items-center gap-3 px-2 py-2 text-xs font-bold text-slate-600 hover:text-slate-900 transition-colors">
                    <span class="material-symbols-outlined text-slate-400 text-[18px]">lock</span>
                    <span>Ganti Password</span>
                </a>
                <form method="POST" action="{{ url('/logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center gap-3 px-2 py-2 text-xs font-bold text-red-500 hover:text-red-750 transition-colors text-left cursor-pointer">
                        <span class="material-symbols-outlined text-[18px]">logout</span>
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </div>
    </div></div>

    <!-- Sub-header -->
    <div class="w-full bg-[#f8fafc] border-b border-slate-200 py-1.5 flex-shrink-0">
        <div class="max-w-[1400px] mx-auto px-4 sm:px-6 flex flex-col sm:flex-row sm:items-center justify-between gap-1.5 text-xs text-slate-500 font-medium text-left">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-[10px] text-slate-400 uppercase tracking-wider">Filter:</span>
                <span class="px-1.5 py-0.5 bg-white border border-slate-200 rounded text-[10px] sm:text-xs text-[#1fa387] font-bold">
                    {{ $startDate ? \Carbon\Carbon::parse($startDate)->format('d/m/Y') . ($endDate && $endDate !== $startDate ? ' - ' . \Carbon\Carbon::parse($endDate)->format('d/m/Y') : '') : 'Semua Waktu' }}
                </span>
            </div>
            <div class="flex items-baseline gap-2 flex-wrap sm:justify-end">
                <h1 class="text-xs sm:text-sm font-bold flex items-center gap-1">
                    <span class="text-slate-400 text-[10px] uppercase tracking-wider font-semibold">Proyek:</span> 
                    <span class="text-[#1fa387] uppercase tracking-wide truncate max-w-[150px] sm:max-w-none">{{ $projectName }}</span>
                </h1>
                <span class="text-[9px] sm:text-[10px] text-slate-400 font-semibold before:content-[''] sm:before:content-['|'] sm:before:mr-2 sm:before:text-slate-200">
                    Berita: 
                    @if($dashboardLoaded)
                        <span class="text-slate-800 font-bold">{{ number_format($this->getProjectArticleCount(), 0, ',', '.') }}</span>
                    @else
                        <span class="inline-block w-8 h-2 bg-slate-200 animate-pulse align-middle"></span>
                    @endif
                </span>
            </div>
        </div>
    </div>

    <footer class="dashboard-fixed-footer md:order-[50] px-6 border-t border-slate-200 items-center justify-between gap-4 py-3" wire:key="dashboard-fixed-footer-shell">
        <p class="text-xs text-slate-400 font-medium">© 2026 Arusbawah Media Intelligence. All rights reserved.</p>
        <p class="text-[10px] text-slate-400 font-semibold uppercase tracking-[0.18em]">Media Intelligence Dashboard</p>
    </footer>

    @php
        $counts = $this->getCounts();
    @endphp

    <!-- Desktop filter is fixed outside the lazy workspace so Livewire refreshes cannot remove it. -->
    <aside class="desktop-filter-panel md:order-[50] shadow-[0_4px_20px_-2px_rgba(0,0,0,0.03)] border border-slate-200 rounded-2xl p-6 bg-white flex-shrink-0" wire:key="desktop-filter-panel-shell">
        <h4 class="text-sm font-bold text-slate-950 uppercase tracking-wider border-b border-slate-100 pb-3 flex-shrink-0">Filter Panel</h4>
        @include('components.⚡filter-items', ['filterContext' => 'desktop'])
    </aside>

    <!-- Main Workspace Layout with Real Full-Height Left Sidebar -->
    <div class="w-full flex-grow flex flex-col md:flex-row min-w-0 min-h-0 overflow-visible md:order-[10]" wire:init="loadDashboard">

        <!-- Left Sidebar — always rendered (static, no data needed) -->
        <aside class="hidden md:flex w-16 bg-white border-r border-slate-200 flex-col items-center py-6 gap-5 flex-shrink-0 h-full">
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
            $socials = ['Twitter', 'Twitter/X', 'x.com', 'Instagram', 'Youtube', 'TikTok', 'Facebook', 'Threads'];
            
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
            $ttCount = $counts['sources']['TikTok'] ?? 0;
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
            $socials = ['Twitter', 'Instagram', 'Youtube', 'TikTok', 'Facebook', 'Threads'];
            $aiReachSum = function ($builder) {
                return (int) $builder
                    ->where('analysis_status', 'success')
                    ->whereNotNull('ai_analysis_results.summary')
                    ->whereNotNull('sentiment')
                    ->whereNotNull('risk_level')
                    ->where('reach_method', 'ai_reader_estimate_v1')
                    ->whereNotNull('project_estimated_readers')
                    ->sum('project_estimated_readers');
            };

            $sourceArticleIds = function (string $sourceLabel) use ($baseActiveQuery) {
                $sourceSql = $this->buildSourceLabelSql($sourceLabel);

                return (clone $baseActiveQuery)
                    ->whereRaw($sourceSql['sql'], $sourceSql['bindings'])
                    ->select('articles.id');
            };

            $socialReach = $aiReachSum(
                \App\Models\AiAnalysisResult::query()
                    ->whereIn('article_id', $sourceArticleIds('Instagram')->union(
                        $sourceArticleIds('TikTok')
                    )->union(
                        $sourceArticleIds('Facebook')
                    )->union(
                        $sourceArticleIds('Threads')
                    )->union(
                        $sourceArticleIds('Youtube')
                    )->union(
                        $sourceArticleIds('Twitter')
                    ))
            );

            $fbReach = $aiReachSum(
                \App\Models\AiAnalysisResult::query()
                    ->whereIn('article_id', $sourceArticleIds('Facebook'))
            );

            $igReach = $aiReachSum(
                \App\Models\AiAnalysisResult::query()
                    ->whereIn('article_id', $sourceArticleIds('Instagram'))
            );

            $ttReach = $aiReachSum(
                \App\Models\AiAnalysisResult::query()
                    ->whereIn('article_id', $sourceArticleIds('TikTok'))
            );

            $newsReach = $aiReachSum(
                \App\Models\AiAnalysisResult::query()
                    ->whereIn('article_id', $sourceArticleIds('News'))
            );

            $totalReach = $socialReach + $newsReach;

            $interactionCount = $fbLikes + $fbComments + $igLikes + $igComments + $ttLikes + $ttComments;
            $prValue = $totalReach * 24.5; // IDR

            $canonicalAiFilter = function($q) {
                $q->where('analysis_status', 'success')
                  ->whereNotNull('ai_analysis_results.summary')
                  ->whereNotNull('sentiment')
                  ->whereNotNull('risk_level');
            };

            // Social sentiments
            $socPos = (int) \App\Models\AiAnalysisResult::query()
                ->whereIn('article_id', $sourceArticleIds('Instagram')->union(
                    $sourceArticleIds('TikTok')
                )->union(
                    $sourceArticleIds('Facebook')
                )->union(
                    $sourceArticleIds('Threads')
                )->union(
                    $sourceArticleIds('Youtube')
                )->union(
                    $sourceArticleIds('Twitter')
                ))
                ->where('sentiment', 'positive')
                ->where($canonicalAiFilter)
                ->count();
            $socNeu = (int) \App\Models\AiAnalysisResult::query()
                ->whereIn('article_id', $sourceArticleIds('Instagram')->union(
                    $sourceArticleIds('TikTok')
                )->union(
                    $sourceArticleIds('Facebook')
                )->union(
                    $sourceArticleIds('Threads')
                )->union(
                    $sourceArticleIds('Youtube')
                )->union(
                    $sourceArticleIds('Twitter')
                ))
                ->where('sentiment', 'neutral')
                ->where($canonicalAiFilter)
                ->count();
            $socNeg = (int) \App\Models\AiAnalysisResult::query()
                ->whereIn('article_id', $sourceArticleIds('Instagram')->union(
                    $sourceArticleIds('TikTok')
                )->union(
                    $sourceArticleIds('Facebook')
                )->union(
                    $sourceArticleIds('Threads')
                )->union(
                    $sourceArticleIds('Youtube')
                )->union(
                    $sourceArticleIds('Twitter')
                ))
                ->where('sentiment', 'negative')
                ->where($canonicalAiFilter)
                ->count();

            // News sentiments
            $newsPos = (int) \App\Models\AiAnalysisResult::query()
                ->whereIn('article_id', $sourceArticleIds('News'))
                ->where('sentiment', 'positive')
                ->where($canonicalAiFilter)
                ->count();
            $newsNeu = (int) \App\Models\AiAnalysisResult::query()
                ->whereIn('article_id', $sourceArticleIds('News'))
                ->where('sentiment', 'neutral')
                ->where($canonicalAiFilter)
                ->count();
            $newsNeg = (int) \App\Models\AiAnalysisResult::query()
                ->whereIn('article_id', $sourceArticleIds('News'))
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

        <!-- Main Workspace (Center feed only; filter panel stays in the fixed shell above) -->
        <div class="desktop-workspace-area flex-1 min-w-0 flex flex-col lg:flex-row gap-6 px-4 sm:px-8 py-6 items-stretch pb-20 lg:pb-20" wire:key="desktop-workspace-area">
            
            @if($this->isTab('penyebutan'))
                <!-- TAB 1: Penyebutan (Mentions Feed View) -->
                <section class="flex-1 min-w-0 mentions-section-wrapper pr-1" wire:key="dashboard-mentions-section">
                    <!-- Section Title & Sort Selector -->
                    <!-- Section Title & Sort Selector -->
                    <div class="flex items-center justify-between gap-3 pb-2.5 border-b border-slate-100">
                        <div>
                            <h2 class="text-lg sm:text-xl font-bold text-slate-900 leading-none flex items-center gap-1.5 text-left">
                                <span class="material-symbols-outlined text-[#1fa387] text-[20px] sm:text-[22px]">forum</span>Penyebutan
                            </h2>
                            <p class="text-[10px] sm:text-xs text-slate-400 mt-1.5 text-left leading-relaxed">
                                Pantau percakapan media proyek <span class="hidden sm:inline font-bold text-[#1fa387]">{{ $projectName }}</span>
                            </p>
                        </div>
                        <div>
                            <!-- Sort Dropdown -->
                            <div class="relative" x-data="{ openSort: false }">
                                <button
                                    @click="openSort = !openSort"
                                    class="bg-white border border-slate-200 rounded-full px-3 py-1.5 text-xs font-semibold text-slate-700 flex items-center gap-1.5 shadow-sm hover:bg-slate-50 transition"
                                    style="padding-top: 4px; padding-bottom: 4px;"
                                >
                                    <span class="material-symbols-outlined text-sm text-slate-400">sort</span>
                                    <span class="text-[11px]">{{ $sortBy == 'newest' ? 'Yang Terbaru' : 'Paling Populer' }}</span>
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
                                    <button wire:click="setSort('newest')" @click="openSort = false" class="w-full px-4 py-2.5 text-xs flex justify-between items-center hover:bg-slate-50 transition-colors {{ $sortBy == 'newest' ? 'text-[#1fa387] font-bold' : 'text-slate-700 font-medium' }}">
                                        <span>Yang Terbaru</span>
                                        @if($sortBy == 'newest')<svg class="w-3.5 h-3.5 text-[#1fa387]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>@endif
                                    </button>
                                    <button wire:click="setSort('popular')" @click="openSort = false" class="w-full px-4 py-2.5 text-xs flex justify-between items-center hover:bg-slate-50 transition-colors {{ $sortBy == 'popular' ? 'text-[#1fa387] font-bold' : 'text-slate-700 font-medium' }}">
                                        <span>Paling Populer</span>
                                        @if($sortBy == 'popular')<svg class="w-3.5 h-3.5 text-[#1fa387]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>@endif
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mentions Cards Feed -->
                    @php
                        $mentionsArticlesList = $this->getArticles();
                        $mentionsArticlesCount = $mentionsArticlesList->count();
                        $mentionsTotalArticlesCount = $this->getTotalArticlesCount();
                        $mentionsFeedSignature = md5(json_encode([
                            'project' => $projectId,
                            'sources' => $selectedSources,
                            'sentiment' => $selectedSentiment,
                            'search' => $search,
                            'start' => $startDate,
                            'end' => $endDate,
                            'sort' => $sortBy,
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    @endphp
                    <div
                        style="height: calc(100vh - 250px);"
                        class="overflow-y-auto pr-2 sm:pr-4 space-y-4 min-h-0 mt-4 text-left"
                        wire:key="mentions-scroll-shell-{{ $mentionsFeedSignature }}"
                        id="mentions-feed-scroll"
                        data-total-count="{{ $mentionsTotalArticlesCount }}"
                        x-data="{ lastLoadMoreAt: 0, loadMoreTimer: null }"
                        x-init="
                            const el = $el;
                            const triggerLoadMore = () => {
                                const total = parseInt(el.getAttribute('data-total-count') || '0', 10);
                                const loaded = el.querySelectorAll('[data-mention-card]').length;
                                if (loaded >= total) return;
                                const usesWindowScroll = el.scrollHeight <= (el.clientHeight + 4);
                                const nearBottom = usesWindowScroll
                                    ? (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 220)
                                    : (el.scrollTop + el.clientHeight >= el.scrollHeight - 220);
                                if (!nearBottom) return;
                                if (Date.now() - lastLoadMoreAt < 1200) return;
                                lastLoadMoreAt = Date.now();
                                Promise.resolve($wire.loadMore())
                                    .catch(() => {})
                                    .finally(() => setTimeout(() => { lastLoadMoreAt = 0; }, 900));
                            };
                            const onScroll = () => requestAnimationFrame(triggerLoadMore);
                            el.addEventListener('scroll', onScroll, { passive: true });
                            window.addEventListener('scroll', onScroll, { passive: true });
                            window.addEventListener('resize', onScroll);
                            if (loadMoreTimer) {
                                clearInterval(loadMoreTimer);
                            }
                            loadMoreTimer = setInterval(triggerLoadMore, 900);
                            triggerLoadMore();
                        "
                    >
                        @php
                            $articlesList = $mentionsArticlesList;
                            $mentionsFilterSignature = md5(json_encode([
                                'project' => $projectId,
                                'sources' => $selectedSources,
                                'sentiment' => $selectedSentiment,
                                'search' => $search,
                                'start' => $startDate,
                                'end' => $endDate,
                                'sort' => $sortBy,
                            ]));
                        @endphp

                        <div
                            class="space-y-4 hidden"
                            wire:loading.block
                            wire:target="search,selectedSources,selectedSentiment,startDate,endDate,sortBy,selectedCategory,selectedKeyword,setSort"
                            wire:key="mentions-filter-skeleton-{{ $mentionsFilterSignature }}"
                        >
                            @for($i = 0; $i < 4; $i++)
                                <div class="bg-white border border-slate-200 rounded-3xl p-4 sm:p-6 shadow-[0_4px_24px_rgba(0,0,0,0.015)] animate-pulse space-y-4">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-10 h-10 rounded-xl bg-slate-100"></div>
                                        <div class="space-y-2">
                                            <div class="h-4 w-28 rounded bg-slate-100"></div>
                                            <div class="h-3 w-40 rounded bg-slate-100"></div>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 bg-slate-50/60 rounded-2xl p-3 border border-slate-100">
                                        <div class="h-14 rounded-2xl bg-slate-100"></div>
                                        <div class="h-14 rounded-2xl bg-slate-100"></div>
                                        <div class="h-14 rounded-2xl bg-slate-100"></div>
                                        <div class="h-14 rounded-2xl bg-slate-100 sm:col-span-1"></div>
                                        <div class="h-14 rounded-2xl bg-slate-100 sm:col-span-1"></div>
                                    </div>
                                    <div class="space-y-3">
                                        <div class="h-5 w-3/4 rounded bg-slate-100"></div>
                                        <div class="h-4 w-full rounded bg-slate-100"></div>
                                        <div class="h-4 w-11/12 rounded bg-slate-100"></div>
                                    </div>
                                    <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-4">
                                        <div class="h-8 w-28 rounded-xl bg-slate-100"></div>
                                        <div class="h-8 w-36 rounded-xl bg-slate-100"></div>
                                    </div>
                                </div>
                            @endfor
                        </div>

                        <div
                            class="space-y-4"
                            wire:target="search,selectedSources,selectedSentiment,startDate,endDate,sortBy,selectedCategory,selectedKeyword,setSort"
                            wire:key="mentions-feed-{{ $mentionsFeedSignature }}"
                        >
                            @if($articlesList->isEmpty())
                            <div class="bg-white border border-slate-200 rounded-2xl p-12 text-center space-y-4 shadow-sm">
                                <svg class="w-12 h-12 text-slate-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 0 0 0118 0z"></path>
                                </svg>
                                <p class="text-sm font-semibold text-slate-600">Belum ada penyebutan media ditemukan untuk proyek ini.</p>
                            </div>
                            @else
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
                                    wire:key="mention-card-{{ $article->id }}-{{ md5((string) $article->source_name) }}"
                                    data-mention-card
                                    class="bg-white rounded-[24px] sm:rounded-3xl border border-slate-200/80 p-3.5 sm:p-6 shadow-[0_4px_24px_rgba(0,0,0,0.015)] flex flex-col justify-between transition-all duration-300 hover:shadow-[0_12px_32px_rgba(0,0,0,0.04)] hover:-translate-y-0.5 border-l-4"
                                    style="border-left-color: {{ $sentimentColor }}"
                                >
                                    @php
                                        $isFacebook = str_contains(strtolower($article->source_name), 'facebook');
                                        $projectReachDisplay = $this->getProjectReachDisplayData($article);
                                    @endphp
                                    <!-- Platform & Category Header Row -->
                                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 mb-4">
                                        <div class="flex items-center gap-2.5 min-w-0">
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
                                        <div class="flex-shrink-0">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-[10px] font-extrabold uppercase tracking-wider {{ $sentimentBg }}">
                                                <span class="w-2 h-2 rounded-full {{ $this->getValidAiResult($article)?->sentiment === 'positive' ? 'bg-emerald-500' : ($this->getValidAiResult($article)?->sentiment === 'negative' ? 'bg-rose-500' : 'bg-slate-400') }}"></span>
                                                {{ $sentimentLabel === 'Belum dianalisis AI' ? 'Netral' : $sentimentLabel }}
                                            </span>
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
                                    <!-- Metrics Grid (Cleaned & Modernized) -->
                                    <div class="grid grid-cols-2 sm:grid-cols-3 {{ $isSocial ? 'lg:grid-cols-5' : 'lg:grid-cols-3' }} gap-y-3 gap-x-2 bg-slate-50/60 rounded-2xl p-3 border border-slate-100 mb-4 text-left">
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
                                        <div class="px-1.5 py-0.5 border-t sm:border-t-0 sm:border-l border-slate-200/60 pt-2 sm:pt-0.5">
                                            <span class="text-[8px] font-bold text-slate-400 uppercase tracking-widest block mb-0.5">Tanggal</span>
                                            <div class="flex items-center gap-1 text-slate-800 text-[11px] md:text-xs font-black">
                                                <span class="material-symbols-outlined text-[#1fa387] text-[14px] md:text-[15px]">calendar_month</span>
                                                <span class="truncate animate-none" title="{{ $article->published_at ? \Carbon\Carbon::parse($article->published_at)->format('d M Y, H:i') : 'Baru saja' }}">
                                                    {{ $article->published_at ? \Carbon\Carbon::parse($article->published_at)->format('d/m/y') : 'Baru saja' }}
                                                </span>
                                            </div>
                                        </div>
                                        @if($isSocial)
                                        <div class="px-1.5 py-0.5 border-l border-slate-200/60">
                                            <span class="text-[8px] font-bold text-slate-400 uppercase tracking-widest block mb-0.5">{{ strtolower($article->source_name) === 'tiktok' ? 'Love' : 'Like' }}</span>
                                            <div class="flex items-center gap-1 text-slate-800 text-[11px] md:text-xs font-black">
                                                <span class="material-symbols-outlined text-[#1fa387] text-[14px] md:text-[15px]">{{ strtolower($article->source_name) === 'tiktok' ? 'favorite' : 'thumb_up' }}</span>
                                                <span>{{ number_format($likesCount, 0, ',', '.') }}</span>
                                            </div>
                                        </div>
                                        <div class="px-1.5 py-0.5 border-t sm:border-t-0 sm:border-l border-slate-200/60 pt-2 sm:pt-0.5">
                                            <span class="text-[8px] font-bold text-slate-400 uppercase tracking-widest block mb-0.5">Komen</span>
                                            <div class="flex items-center gap-1 text-slate-800 text-[11px] md:text-xs font-black">
                                                <span class="material-symbols-outlined text-[#1fa387] text-[14px] md:text-[15px]">comment</span>
                                                <span>{{ number_format($commentsCount, 0, ',', '.') }}</span>
                                            </div>
                                        </div>
                                        @endif
                                    </div>

                                    <!-- Content Body (Sleek teaser layout) -->
                                    <div class="space-y-2 mb-4 text-left flex-grow">
                                        <h3 class="text-xs sm:text-sm md:text-[17px] font-extrabold text-slate-900 leading-snug tracking-tight hover:text-[#1fa387] transition-colors duration-150">
                                            {{ $this->displayArticleTitle($article) }}
                                        </h3>
                                        <p class="text-[11px] sm:text-sm text-slate-600 leading-relaxed line-clamp-3">
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
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-t border-slate-100 pt-4 mt-auto w-full">
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

                                        <div class="flex items-center justify-between sm:justify-end gap-2 w-full sm:w-auto">
                                            <!-- Topic Category Badge (Enlarged & Redesigned) -->
                                            <span class="inline-flex items-center gap-1 px-2 py-1 sm:px-3 sm:py-1.5 text-[9px] sm:text-[11px] font-bold bg-slate-50 border border-slate-200/80 text-slate-600 rounded-xl" title="{{ $article->category }}">
                                                <span class="material-symbols-outlined text-[11px] sm:text-[13px] text-slate-400">local_offer</span>
                                                {{ Str::limit($article->category, 30) }}
                                            </span>

                                            @if($article->url)
                                                <button 
                                                    type="button"
                                                    @click="window.openDashboardDetail(
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
                                                    class="px-2.5 py-1.5 sm:px-4 sm:py-2 border border-slate-200/80 text-slate-700 hover:bg-slate-50 font-bold text-[10px] sm:text-xs rounded-xl transition flex items-center gap-1 bg-white cursor-pointer hover:border-slate-300"
                                                >
                                                    <span>Lihat Detail</span>
                                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                            @endif
                        </div>
                        <!-- Load More -->
                        @php
                            $totalArticlesCount = $this->getTotalArticlesCount();
                        @endphp

                        @if($articlesList->count() < $totalArticlesCount)
                            <div wire:key="mentions-load-more-{{ $mentionsFeedSignature }}-{{ $articlesList->count() }}" class="py-6 flex items-center justify-center w-full">
                                <div
                                    wire:loading.flex
                                    wire:target="loadMore"
                                    class="hidden items-center justify-center gap-2 text-[#1fa387]"
                                >
                                    <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span class="text-xs font-bold">Memuat lebih banyak...</span>
                                </div>
                            </div>

                        @else
                            <div class="py-6 mt-4 border-t border-slate-100 text-center text-xs text-slate-400 font-medium">
                                <p class="text-slate-500 font-semibold">Semua data telah dimuat</p>
                                <p class="text-[10px] text-slate-400 mt-0.5">{{ $articlesList->count() }}/{{ $totalArticlesCount }} artikel ditampilkan</p>
                            </div>
                        @endif

                    </div>
                </section>
            @elseif($this->isTab('analisis'))
                <!-- TAB 2: Analisis (Redesigned matching screenshots) -->
                <section class="flex-1 min-w-0 flex flex-col h-full overflow-hidden space-y-4 pr-1" wire:key="dashboard-analysis-section">
                    <div>
                        <h2 class="text-lg sm:text-xl font-bold text-slate-900 leading-none flex items-center gap-1.5 text-left">
                            <span class="material-symbols-outlined text-[#1fa387] text-[20px] sm:text-[22px]">analytics</span>Analisis
                        </h2>
                        <p class="text-[10px] sm:text-xs text-slate-400 mt-1.5 text-left leading-relaxed">Pantau ringkasan performa dan wawasan data yang relevan untuk proyek aktif.</p>
                    </div>
                    @php
                        $analysisArticlesList = $this->getArticles();
                        $analysisArticlesCount = $analysisArticlesList->count();
                        $analysisTotalArticlesCount = $this->getTotalArticlesCount();
                    @endphp
                    <div
                        style="height: calc(100vh - 250px);"
                        class="overflow-y-auto pr-4 space-y-6"
                        x-data="{ lastLoadMoreAt: 0, loadMoreTimer: null }"
                        x-init="
                            const feedEl = $el;
                            const triggerLoadMore = () => {
                                if (feedEl.scrollTop + feedEl.clientHeight < feedEl.scrollHeight - 200) return;
                                if ({{ $analysisArticlesCount }} >= {{ $analysisTotalArticlesCount }}) return;
                                if (Date.now() - lastLoadMoreAt < 1200) return;
                                lastLoadMoreAt = Date.now();
                                $wire.loadMore();
                            };
                            feedEl.addEventListener('scroll', triggerLoadMore, { passive: true });
                            if (feedEl.loadMoreTimer) {
                                clearInterval(feedEl.loadMoreTimer);
                            }
                            feedEl.loadMoreTimer = setInterval(triggerLoadMore, 900);
                            triggerLoadMore();
                        "
                    >
                        <!-- Gambaran Umum Card Grid -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-8 shadow-sm text-left space-y-6">
                            <div class="flex justify-between items-center pb-3 border-b border-slate-100/85 mb-6">
                                <div class="space-y-0.5 text-left">
                                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-[18px] text-[#1fa387]">equalizer</span>
                                        GAMBARAN UMUM
                                    </h3>
                                    <p class="text-[10px] text-slate-400">Kinerja metrik utama dan distribusi penyebutan pada setiap saluran media.</p>
                                </div>
                            </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                            <!-- Card 1: Total Artikel Ditemukan -->
                            <div class="border border-slate-200 bg-white rounded-2xl p-5 shadow-sm hover:shadow-md transition-all duration-200 flex items-center justify-between h-[100px]">
                                <div class="space-y-1.5 text-left">
                                    <span class="text-xs font-extrabold tracking-wider text-slate-400 uppercase block">TOTAL ARTIKEL DITEMUKAN</span>
                                    <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight leading-none">{{ $fmt($totalMentions) }}</h2>
                                </div>
                                <div class="w-12 h-12 rounded-2xl overflow-hidden bg-emerald-50 flex items-center justify-center flex-shrink-0 text-[#1fa387]">
                                    <span class="material-symbols-outlined text-[24px]">article</span>
                                </div>
                            </div>
                            <!-- Card 2: Total Jangkauan -->
                            <div class="border border-slate-200 bg-white rounded-2xl p-5 shadow-sm hover:shadow-md transition-all duration-200 flex items-center justify-between h-[100px]">
                                <div class="space-y-1.5 text-left">
                                    <span class="text-xs font-extrabold tracking-wider text-slate-400 uppercase block">TOTAL JANGKAUAN</span>
                                    <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight leading-none">{{ $fmt($totalReach) }}</h2>
                                </div>
                                <div class="w-12 h-12 rounded-2xl overflow-hidden bg-indigo-50 flex items-center justify-center flex-shrink-0 text-indigo-600">
                                    <span class="material-symbols-outlined text-[24px]">hub</span>
                                </div>
                            </div>
                            <!-- Card 3: Interaksi Sosial Media -->
                            <div class="border border-slate-200 bg-white rounded-2xl p-5 shadow-sm hover:shadow-md transition-all duration-200 flex items-center justify-between h-[100px]">
                                <div class="space-y-1.5 text-left">
                                    <span class="text-xs font-extrabold tracking-wider text-slate-400 uppercase block">INTERAKSI SOSIAL MEDIA</span>
                                    <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight leading-none">{{ $fmt($interactionCount) }}</h2>
                                </div>
                                <div class="w-12 h-12 rounded-2xl overflow-hidden bg-purple-50 flex items-center justify-center flex-shrink-0 text-purple-600">
                                    <span class="material-symbols-outlined text-[24px]">forum</span>
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: Publication Channels breakdown metrics cards (Instagram, TikTok, Facebook, Portal Berita) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 2xl:grid-cols-4 gap-4">
                            <!-- Instagram -->
                            <div class="border border-slate-200 bg-white rounded-2xl p-5 shadow-sm hover:shadow-md transition-all duration-200 flex flex-col justify-between h-[155px]">
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
                                    <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight leading-none">
                                        {{ $fmt($igCount) }}
                                    </h2>
                                </div>
                                
                                <div class="flex items-center justify-between pt-3 border-t border-slate-100 mt-auto text-xs text-slate-500 font-medium">
                                    <div class="flex items-center gap-1.5" title="Jangkauan">
                                        <span class="material-symbols-outlined text-[16px] text-slate-400">insights</span>
                                        <span class="font-bold leading-none">{{ $fmt($igReach) }}</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center gap-1" title="Suka (Likes)">
                                            <span class="material-symbols-outlined text-[16px] text-slate-400">thumb_up</span>
                                            <span class="font-bold leading-none">{{ $fmt($igLikes) }}</span>
                                        </div>
                                        <div class="flex items-center gap-1" title="Komentar (Comments)">
                                            <span class="material-symbols-outlined text-[16px] text-slate-400">comment</span>
                                            <span class="font-bold leading-none">{{ $fmt($igComments) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- TikTok -->
                            <div class="border border-slate-200 bg-white rounded-2xl p-5 shadow-sm hover:shadow-md transition-all duration-200 flex flex-col justify-between h-[155px]">
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
                                    <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight leading-none">
                                        {{ $fmt($ttCount) }}
                                    </h2>
                                </div>
                                
                                <div class="flex items-center justify-between pt-3 border-t border-slate-100 mt-auto text-xs text-slate-500 font-medium">
                                    <div class="flex items-center gap-1.5" title="Jangkauan">
                                        <span class="material-symbols-outlined text-[16px] text-slate-400">insights</span>
                                        <span class="font-bold leading-none">{{ $fmt($ttReach) }}</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center gap-1" title="Suka (Likes)">
                                            <span class="material-symbols-outlined text-[16px] text-slate-400">thumb_up</span>
                                            <span class="font-bold leading-none">{{ $fmt($ttLikes) }}</span>
                                        </div>
                                        <div class="flex items-center gap-1" title="Komentar (Comments)">
                                            <span class="material-symbols-outlined text-[16px] text-slate-400">comment</span>
                                            <span class="font-bold leading-none">{{ $fmt($ttComments) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Facebook -->
                            <div class="border border-slate-200 bg-white rounded-2xl p-5 shadow-sm hover:shadow-md transition-all duration-200 flex flex-col justify-between h-[155px]">
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
                                    <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight leading-none">
                                        {{ $fmt($fbCount) }}
                                    </h2>
                                </div>
                                
                                <div class="flex items-center justify-between pt-3 border-t border-slate-100 mt-auto text-xs text-slate-500 font-medium">
                                    <div class="flex items-center gap-1.5" title="Jangkauan">
                                        <span class="material-symbols-outlined text-[16px] text-slate-400">insights</span>
                                        <span class="font-bold leading-none">{{ $fmt($fbReach) }}</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center gap-1" title="Suka (Likes)">
                                            <span class="material-symbols-outlined text-[16px] text-slate-400">thumb_up</span>
                                            <span class="font-bold leading-none">{{ $fmt($fbLikes) }}</span>
                                        </div>
                                        <div class="flex items-center gap-1" title="Komentar (Comments)">
                                            <span class="material-symbols-outlined text-[16px] text-slate-400">comment</span>
                                            <span class="font-bold leading-none">{{ $fmt($fbComments) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Portal Berita -->
                            <div class="border border-slate-200 bg-white rounded-2xl p-5 shadow-sm hover:shadow-md transition-all duration-200 flex flex-col justify-between h-[155px]">
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
                                    <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight leading-none">
                                        {{ $fmt($newsCount) }}
                                    </h2>
                                </div>
                                
                                <div class="flex items-center justify-between pt-3 border-t border-slate-100 mt-auto text-xs text-slate-500 font-medium">
                                    <div class="flex items-center gap-1.5" title="Jangkauan">
                                        <span class="material-symbols-outlined text-[16px] text-slate-400">insights</span>
                                        <span class="font-bold leading-none">{{ $fmt($newsReach) }}</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <span class="text-[9px] bg-slate-100 text-slate-500 font-extrabold px-2 py-0.5 rounded border border-slate-200">PORTAL NEWS</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 3: Sentiment breakdown cards (Full width 2 columns) -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
                            <!-- Sentimen Sosmed -->
                            <div class="border border-slate-200 bg-white rounded-2xl p-5 shadow-sm hover:shadow-md transition-all duration-200 space-y-3">
                                <div class="flex justify-between items-center pb-2 border-b border-slate-100/85">
                                    <div class="space-y-0.5 text-left">
                                        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[18px] text-[#1fa387]">sentiment_satisfied</span>
                                            SENTIMEN SOSIAL MEDIA
                                        </h3>
                                        <p class="text-[10px] text-slate-400">Analisis respon dan persepsi emosi publik di media sosial.</p>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-3 gap-2">
                                    <!-- Positif -->
                                    <div class="bg-emerald-50/40 rounded-xl p-2.5 border border-emerald-100/50 text-left">
                                        <span class="text-[10px] font-extrabold text-emerald-600 block uppercase tracking-wider">Positif</span>
                                        <h3 class="text-xl font-extrabold text-emerald-700 mt-1">{{ $fmt($socPos) }}</h3>
                                    </div>
                                    <!-- Netral -->
                                    <div class="bg-slate-50 rounded-xl p-2.5 border border-slate-100 text-left">
                                        <span class="text-[10px] font-extrabold text-slate-500 block uppercase tracking-wider">Netral</span>
                                        <h3 class="text-xl font-extrabold text-slate-700 mt-1">{{ $fmt($socNeu) }}</h3>
                                    </div>
                                    <!-- Negatif -->
                                    <div class="bg-rose-50/40 rounded-xl p-2.5 border border-rose-100/50 text-left">
                                        <span class="text-[10px] font-extrabold text-rose-600 block uppercase tracking-wider">Negatif</span>
                                        <h3 class="text-xl font-extrabold text-rose-700 mt-1">{{ $fmt($socNeg) }}</h3>
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
                            <div class="border border-slate-200 bg-white rounded-2xl p-5 shadow-sm hover:shadow-md transition-all duration-200 space-y-3">
                                <div class="flex justify-between items-center pb-2 border-b border-slate-100/85">
                                    <div class="space-y-0.5 text-left">
                                        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[18px] text-[#1fa387]">newspaper</span>
                                            SENTIMEN BERITA
                                        </h3>
                                        <p class="text-[10px] text-slate-400">Distribusi sentimen pemberitaan pada media berita online.</p>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-3 gap-2">
                                    <!-- Positif -->
                                    <div class="bg-emerald-50/40 rounded-xl p-2.5 border border-emerald-100/50 text-left">
                                        <span class="text-[10px] font-extrabold text-emerald-600 block uppercase tracking-wider">Positif</span>
                                        <h3 class="text-xl font-extrabold text-emerald-700 mt-1">{{ $fmt($newsPos) }}</h3>
                                    </div>
                                    <!-- Netral -->
                                    <div class="bg-slate-50 rounded-xl p-2.5 border border-slate-100 text-left">
                                        <span class="text-[10px] font-extrabold text-slate-500 block uppercase tracking-wider">Netral</span>
                                        <h3 class="text-xl font-extrabold text-slate-700 mt-1">{{ $fmt($newsNeu) }}</h3>
                                    </div>
                                    <!-- Negatif -->
                                    <div class="bg-rose-50/40 rounded-xl p-2.5 border border-rose-100/50 text-left">
                                        <span class="text-[10px] font-extrabold text-rose-600 block uppercase tracking-wider">Negatif</span>
                                        <h3 class="text-xl font-extrabold text-rose-700 mt-1">{{ $fmt($newsNeg) }}</h3>
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

                    <!-- SVGs Line Chart (Daily trend) -->
                    <div class="bg-white rounded-3xl border border-slate-200 p-8 shadow-sm text-left space-y-4" x-data="{ trendMode: 'harian', activePoint: null }">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-2.5 border-b border-slate-100/85">
                                <div class="space-y-0.5 text-left">
                                    <h3 class="text-xs sm:text-sm font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-[18px] text-[#1fa387]">show_chart</span>
                                        Grafik Tren Kinerja Proyek
                                    </h3>
                                    <p class="text-[10px] text-slate-450 mt-1 leading-snug">Memantau fluktuasi volume data artikel yang berhasil dihimpun oleh scraper.</p>
                                </div>
                                <div class="bg-slate-100 p-0.5 rounded-xl flex gap-1 text-[10px] font-bold text-slate-500 shadow-inner w-full sm:w-auto justify-center">
                                    <button @click="trendMode = 'harian'" class="flex-1 sm:flex-none px-4 py-1.5 rounded-lg transition cursor-pointer" :class="trendMode == 'harian' ? 'bg-[#1fa387] text-white shadow-sm' : 'hover:text-slate-800'">Harian</button>
                                    <button @click="trendMode = 'mingguan'" class="flex-1 sm:flex-none px-4 py-1.5 rounded-lg transition cursor-pointer" :class="trendMode == 'mingguan' ? 'bg-[#1fa387] text-white shadow-sm' : 'hover:text-slate-800'">Mingguan</button>
                                    <button @click="trendMode = 'bulanan'" class="flex-1 sm:flex-none px-4 py-1.5 rounded-lg transition cursor-pointer" :class="trendMode == 'bulanan' ? 'bg-[#1fa387] text-white shadow-sm' : 'hover:text-slate-800'">Bulanan</button>
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
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Word Cloud -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm flex flex-col text-left h-[360px] relative overflow-hidden transition-all duration-300 hover:shadow-md">
                            <div class="flex justify-between items-center pb-2 border-b border-slate-100/85 mb-3.5 relative z-10 w-full">
                                <div class="space-y-0.5 text-left">
                                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-[18px] text-[#1fa387]">cloud</span>
                                        TOPIK UTAMA
                                    </h3>
                                    <p class="text-[10px] text-slate-400">Kata kunci yang paling sering muncul dalam pemberitaan.</p>
                                </div>
                                <span class="text-[10px] bg-slate-100 text-slate-500 font-bold px-2 py-0.5 rounded border border-slate-200">Awan Kata</span>
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
                            <div class="flex justify-between items-center pb-2 border-b border-slate-100/85 mb-4">
                                <div class="space-y-0.5 text-left">
                                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-[18px] text-[#1fa387]">category</span>
                                        PENYEBUTAN BERDASARKAN KATEGORI
                                    </h3>
                                    <p class="text-[10px] text-slate-400">Pengelompokan penyebutan berita berdasarkan klasifikasi topik.</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 flex-grow overflow-y-auto pr-1">
                                <!-- News -->
                                <div class="group relative rounded-2xl p-[1px] bg-gradient-to-br from-emerald-400/40 via-emerald-200/20 to-teal-400/30 hover:-translate-y-1 hover:shadow-[0_8px_30px_-6px_rgba(16,185,129,0.25)] transition-all duration-300 cursor-pointer">
                                    <div class="relative bg-white rounded-[15px] p-2.5 xs:p-3.5 flex items-center gap-2 xs:gap-3.5 h-[80px] overflow-hidden">
                                        <!-- Subtle background glow -->
                                        <div class="absolute -right-6 -top-6 w-24 h-24 bg-emerald-400/[0.04] rounded-full blur-2xl group-hover:bg-emerald-400/[0.08] transition-all duration-500"></div>
                                        <!-- Icon -->
                                        <div class="relative w-10 h-10 xs:w-[52px] xs:h-[52px] rounded-xl flex items-center justify-center shrink-0 shadow-lg shadow-emerald-500/20 group-hover:shadow-emerald-500/35 group-hover:scale-105 transition-all duration-300" style="background: linear-gradient(135deg, #10b981, #14b8a6);">
                                            <svg class="w-5 h-5 xs:w-6 xs:h-6 text-white" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6m-6 4h3"></path></svg>
                                        </div>
                                        <!-- Content -->
                                        <div class="relative flex flex-col text-left min-w-0">
                                            <span class="text-[9px] xs:text-[10px] font-bold text-slate-400 uppercase tracking-[0.12em] leading-none">Portal News</span>
                                            <span class="text-xl xs:text-2xl sm:text-[26px] font-black text-slate-900 mt-0.5 xs:mt-1 leading-none tracking-tight">{{ $fmt($counts['sources']['News'] ?? 0) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <!-- Instagram -->
                                <div class="group relative rounded-2xl p-[1px] bg-gradient-to-br from-purple-400/40 via-pink-400/30 to-orange-300/30 hover:-translate-y-1 hover:shadow-[0_8px_30px_-6px_rgba(219,39,119,0.25)] transition-all duration-300 cursor-pointer">
                                    <div class="relative bg-white rounded-[15px] p-2.5 xs:p-3.5 flex items-center gap-2 xs:gap-3.5 h-[80px] overflow-hidden">
                                        <div class="absolute -right-6 -top-6 w-24 h-24 bg-pink-400/[0.04] rounded-full blur-2xl group-hover:bg-pink-400/[0.08] transition-all duration-500"></div>
                                        <!-- Icon -->
                                        <div class="relative w-10 h-10 xs:w-[52px] xs:h-[52px] rounded-xl flex items-center justify-center shrink-0 shadow-lg shadow-pink-500/20 group-hover:shadow-pink-500/35 group-hover:scale-105 transition-all duration-300" style="background: linear-gradient(135deg, #833ab4, #fd1d1d, #fcb045);">
                                            <svg class="w-5 h-5 xs:w-6 xs:h-6 text-white" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5" stroke-linecap="round"></line></svg>
                                        </div>
                                        <!-- Content -->
                                        <div class="relative flex flex-col text-left min-w-0">
                                            <span class="text-[9px] xs:text-[10px] font-bold text-slate-400 uppercase tracking-[0.12em] leading-none">Instagram</span>
                                            <span class="text-xl xs:text-2xl sm:text-[26px] font-black text-slate-900 mt-0.5 xs:mt-1 leading-none tracking-tight">{{ $fmt($counts['sources']['Instagram'] ?? 0) }}</span>
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
                                    <div class="relative bg-white rounded-[15px] p-2.5 xs:p-3.5 flex items-center gap-2 xs:gap-3.5 h-[80px] overflow-hidden">
                                        <div class="absolute -right-6 -top-6 w-24 h-24 bg-slate-800/[0.03] rounded-full blur-2xl group-hover:bg-slate-800/[0.06] transition-all duration-500"></div>
                                        <!-- Icon -->
                                        <div class="relative w-10 h-10 xs:w-[52px] xs:h-[52px] rounded-xl flex items-center justify-center shrink-0 shadow-lg shadow-slate-900/25 group-hover:shadow-slate-900/40 group-hover:scale-105 transition-all duration-300" style="background: linear-gradient(135deg, #111111, #333333);">
                                            <svg class="w-5 h-5 xs:w-6 xs:h-6 fill-current text-white" viewBox="0 0 24 24"><path d="M12.525.01c1.306-.022 2.615-.011 3.921-.012.08 1.836 1.011 3.5 2.501 4.485.006 1.341-.004 2.683-.004 4.024-1.57-.107-3.067-.932-3.955-2.247-.008 2.827-.003 5.657-.005 8.486-.098 3.546-3.13 6.643-6.726 6.467-3.526-.067-6.523-3.18-6.241-6.722.215-3.327 3.012-6.104 6.347-5.992v4.06c-1.393-.16-2.775.76-3.085 2.112-.397 1.488.583 3.125 2.1 3.328 1.455.234 2.924-.766 3.14-2.224.048-2.617.02-5.237.03-7.856.002-3.834-.002-7.67.002-11.504z"></path></svg>
                                        </div>
                                        <!-- Content -->
                                        <div class="relative flex flex-col text-left min-w-0">
                                            <span class="text-[9px] xs:text-[10px] font-bold text-slate-400 uppercase tracking-[0.12em] leading-none">TikTok</span>
                                            <span class="text-xl xs:text-2xl sm:text-[26px] font-black text-slate-900 mt-0.5 xs:mt-1 leading-none tracking-tight">{{ $fmt($counts['sources']['TikTok'] ?? 0) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <!-- Facebook -->
                                <div class="group relative rounded-2xl p-[1px] bg-gradient-to-br from-blue-500/40 via-blue-300/20 to-indigo-400/30 hover:-translate-y-1 hover:shadow-[0_8px_30px_-6px_rgba(37,99,235,0.25)] transition-all duration-300 cursor-pointer">
                                    <div class="relative bg-white rounded-[15px] p-2.5 xs:p-3.5 flex items-center gap-2 xs:gap-3.5 h-[80px] overflow-hidden">
                                        <div class="absolute -right-6 -top-6 w-24 h-24 bg-blue-400/[0.04] rounded-full blur-2xl group-hover:bg-blue-400/[0.08] transition-all duration-500"></div>
                                        <!-- Icon -->
                                        <div class="relative w-10 h-10 xs:w-[52px] xs:h-[52px] rounded-xl flex items-center justify-center shrink-0 shadow-lg shadow-blue-600/20 group-hover:shadow-blue-600/35 group-hover:scale-105 transition-all duration-300" style="background: linear-gradient(135deg, #1877f2, #3b82f6);">
                                            <svg class="w-5 h-5 xs:w-6 xs:h-6 fill-current text-white" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"></path></svg>
                                        </div>
                                        <!-- Content -->
                                        <div class="relative flex flex-col text-left min-w-0">
                                            <span class="text-[9px] xs:text-[10px] font-bold text-slate-400 uppercase tracking-[0.12em] leading-none">Facebook</span>
                                            <span class="text-xl xs:text-2xl sm:text-[26px] font-black text-slate-900 mt-0.5 xs:mt-1 leading-none tracking-tight">{{ $counts['counts']['sources']['Facebook'] ?? $counts['sources']['Facebook'] ?? 0 }}</span>
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
                        <div class="flex justify-between items-center pb-2 border-b border-slate-100/85 mb-6 w-full">
                            <div class="space-y-0.5 text-left">
                                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[18px] text-[#1fa387]">hub</span>
                                    NETWORK ANALYSIS
                                </h3>
                                <p class="text-[10px] text-slate-400">Visualisasi hubungan keterkaitan antar entitas, kata kunci, dan topik berita.</p>
                            </div>
                            <span class="text-[10px] bg-slate-100 text-slate-500 font-bold px-2 py-0.5 rounded border border-slate-200">Analisis Relasi</span>
                        </div>
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start" x-data="{ netTab: 'topik' }">
                            <!-- Visual Network SVG Graph (Left 2 cols) -->
                            <div class="lg:col-span-2 border border-slate-200 rounded-2xl p-4 flex flex-col items-center justify-between bg-slate-50 h-[350px] relative overflow-hidden select-none shadow-sm"
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
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Left Column: Penyebutan Populer -->
                        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm text-left space-y-4">
                            <div class="flex justify-between items-center pb-2 border-b border-slate-100/85 mb-4">
                                <div class="space-y-0.5 text-left">
                                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-[18px] text-[#1fa387]">trending_up</span>
                                        PENYEBUTAN POPULER
                                    </h3>
                                    <p class="text-[10px] text-slate-400">Artikel/postingan dengan jangkauan dan interaksi tertinggi.</p>
                                </div>
                            </div>
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
                                                    @click="window.openDashboardDetail(
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
                            <div class="flex justify-between items-center pb-2 border-b border-slate-100/85 mb-4">
                                <div class="space-y-0.5 text-left">
                                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-[18px] text-[#1fa387]">schedule</span>
                                        PENYEBUTAN TERBARU
                                    </h3>
                                    <p class="text-[10px] text-slate-400">Daftar postingan/artikel terbaru yang berhasil masuk sistem.</p>
                                </div>
                            </div>
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
                                                    @click="window.openDashboardDetail(
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
                    </div>
                </section>
            @elseif($this->isTab('katakunci'))
                <!-- TAB 3: Kata Kunci Configuration Page -->
                <section class="flex-1 min-w-0 pr-1 relative z-10" wire:key="dashboard-keyword-section">
                    <div class="flex items-center justify-between text-left shrink-0">
                        <div>
                            <h2 class="text-lg sm:text-xl font-bold text-slate-900 leading-none flex items-center gap-1.5 text-left">
                                <span class="material-symbols-outlined text-[#1fa387] text-[20px] sm:text-[22px]">vpn_key</span>Pengaturan dan Analisis Kata Kunci
                            </h2>
                            <p class="text-[10px] sm:text-xs text-slate-400 mt-1.5 text-left leading-relaxed">Pantau performa tren pencarian untuk proyek <span class="text-[#1fa387] font-bold uppercase">{{ $projectName }}</span></p>
                        </div>
                    </div>

                    @if (session()->has('message'))
                        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-2xl text-xs font-bold text-left flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span>{{ session('message') }}</span>
                        </div>
                    @endif

                    <div style="height: calc(100vh - 270px) !important; overflow-y: auto !important;" class="flex-1 min-h-0 pr-4 pb-24 space-y-6 mt-5">

                    <!-- Manajemen Kata Kunci Card -->
                    <!-- Manajemen Kata Kunci Card -->
                    <div class="bg-white rounded-3xl border border-slate-200 p-4 sm:p-8 shadow-sm text-left">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                            <h3 class="text-sm font-bold text-slate-800 flex items-center gap-1.5"><span class="material-symbols-outlined text-[18px] text-[#1fa387]">vpn_key</span>Manajemen Kata Kunci</h3>
                            @if($this->isAdmin())
                                <button 
                                    type="button"
                                    wire:click="$set('showAddKeywordModal', true)"
                                    class="bg-[#1fa387] hover:bg-[#1fa387]/90 text-white font-bold text-xs px-5 py-2.5 rounded-xl transition flex items-center justify-center gap-1.5 cursor-pointer w-full sm:w-auto"
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

                        @php
                            $filteredTable = array_filter($keywordsTable, function($item) {
                                return empty($this->keywordSearch) || str_contains(strtolower($item['keyword']), strtolower($this->keywordSearch));
                            });
                        @endphp

                        <!-- Mobile Card List View (Visible on Mobile only) -->
                        <div class="block sm:hidden space-y-3 mb-6">
                            @forelse($filteredTable as $idx => $row)
                                @php
                                    $cleanKw = trim(str_replace('#', '', $row['keyword']));
                                    $trendColor = match($row['trend']) {
                                        'Naik'  => 'bg-emerald-50 text-emerald-600 border border-emerald-100',
                                        'Turun' => 'bg-rose-50 text-rose-500 border border-rose-100',
                                        default => 'bg-slate-50 text-slate-500 border border-slate-100',
                                    };
                                    $trendIcon = match($row['trend']) {
                                        'Naik'  => 'M5 10l7-7m0 0l7 7m-7-7v18',
                                        'Turun' => 'M19 14l-7 7m0 0l-7-7m7 7V3',
                                        default => 'M5 12h14',
                                    };
                                @endphp
                                <div 
                                    wire:key="kw-mobile-card-{{ $cleanKw }}"
                                    wire:click="toggleKeyword('{{ $cleanKw }}')"
                                    class="p-4 bg-slate-50/50 hover:bg-[#1fa387]/5 border rounded-2xl transition cursor-pointer flex flex-col gap-3 {{ $selectedKeyword === $cleanKw ? 'border-[#1fa387] bg-[#1fa387]/5' : 'border-slate-100' }}"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex flex-col min-w-0">
                                            <span class="font-extrabold text-[13px] {{ $selectedKeyword === $cleanKw ? 'text-[#1fa387]' : 'text-slate-900' }} truncate">{{ $cleanKw }}</span>
                                            <span class="text-[10px] font-bold text-slate-400 mt-0.5 truncate">
                                                #{{ preg_replace('/\s+/u', '', preg_replace('/^#+/u', '', $cleanKw) ?? $cleanKw) }}
                                            </span>
                                        </div>
                                        @if($this->isAdmin())
                                            <button 
                                                type="button"
                                                wire:click.stop="removeKeywordTable({{ $idx }})"
                                                class="text-rose-500 hover:text-rose-700 p-1.5 transition cursor-pointer shrink-0"
                                                title="Hapus Kata Kunci"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        @endif
                                    </div>
                                    <div class="flex items-center justify-between border-t border-slate-100/70 pt-2.5 mt-0.5">
                                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                            Total: <span class="text-slate-700 font-extrabold ml-1">{{ number_format($row['total']) }}</span>
                                        </div>
                                        <span class="inline-flex items-center gap-1 text-[9px] font-extrabold px-2 py-0.5 rounded-lg uppercase tracking-wider {{ $trendColor }}">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="{{ $trendIcon }}"></path></svg>
                                            <span>{{ $row['trend'] }}</span>
                                        </span>
                                    </div>
                                </div>
                            @empty
                                <div class="p-8 text-center text-slate-400 italic text-xs bg-slate-50/50 rounded-2xl border border-dashed border-slate-200">
                                    Tidak ada data kata kunci ditemukan.
                                </div>
                            @endforelse
                        </div>

                        <!-- Table (Visible on Desktop only) -->
                        <div class="hidden sm:block overflow-x-auto border border-slate-100 rounded-2xl">
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
                                    @forelse($filteredTable as $idx => $row)
                                        @php
                                            $cleanKw = trim(str_replace('#', '', $row['keyword']));
                                        @endphp
                                        <tr 
                                            wire:key="kw-row-{{ $cleanKw }}"
                                            wire:click="toggleKeyword('{{ $cleanKw }}')"
                                            class="hover:bg-[#1fa387]/5 transition cursor-pointer {{ $selectedKeyword === $cleanKw ? 'bg-[#1fa387]/10' : '' }}"
                                        >
                                            <td class="px-6 py-4 {{ $selectedKeyword === $cleanKw ? 'text-[#1fa387]' : 'text-slate-900' }}">
                                                <div class="flex flex-col gap-1">
                                                    <span class="font-bold">{{ $cleanKw }}</span>
                                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-slate-400">
                                                        <span class="uppercase tracking-wider">Hashtag</span>
                                                        <span class="text-[#1fa387]">#{{ preg_replace('/\s+/u', '', preg_replace('/^#+/u', '', $cleanKw) ?? $cleanKw) }}</span>
                                                    </span>
                                                </div>
                                            </td>
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
                        <div class="flex flex-col gap-3 sm:flex-row items-center justify-between mt-6 text-xs text-slate-400 font-semibold">
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
                    <div class="bg-white rounded-3xl border border-slate-200 p-4 sm:p-8 shadow-sm text-left space-y-6" x-data="{ trendInterval: 'harian', trendMetric: 'penyebutan', activePoint: null }">
                        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
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
                            <div class="flex flex-col xs:flex-row items-stretch xs:items-center gap-2.5 w-full lg:w-auto">
                                <!-- Interval Button Toggle -->
                                <div class="bg-slate-100 p-0.5 rounded-full flex gap-1 text-[10px] font-bold text-slate-500 w-full xs:w-auto justify-center">
                                    <button @click="trendInterval = 'harian'" class="flex-1 xs:flex-initial px-3 py-1 rounded-full transition cursor-pointer text-center" :class="trendInterval == 'harian' ? 'bg-blue-600 text-white' : 'hover:text-slate-800'">Harian</button>
                                    <button @click="trendInterval = 'mingguan'" class="flex-1 xs:flex-initial px-3 py-1 rounded-full transition cursor-pointer text-center" :class="trendInterval == 'mingguan' ? 'bg-blue-600 text-white' : 'hover:text-slate-800'">Mingguan</button>
                                    <button @click="trendInterval = 'bulanan'" class="flex-1 xs:flex-initial px-3 py-1 rounded-full transition cursor-pointer text-center" :class="trendInterval == 'bulanan' ? 'bg-blue-600 text-white' : 'hover:text-slate-800'">Bulanan</button>
                                </div>
                                <!-- Metric Button Toggle -->
                                <div class="bg-slate-100 p-0.5 rounded-full flex gap-1 text-[10px] font-bold text-slate-500 w-full xs:w-auto justify-center">
                                    <button @click="trendMetric = 'penyebutan'" class="flex-1 xs:flex-initial px-3 py-1 rounded-full transition cursor-pointer text-center" :class="trendMetric == 'penyebutan' ? 'bg-[#1fa387] text-white' : 'hover:text-slate-800'">Penyebutan</button>
                                    <button @click="trendMetric = 'jangkauan'" class="flex-1 xs:flex-initial px-3 py-1 rounded-full transition cursor-pointer text-center" :class="trendMetric == 'jangkauan' ? 'bg-[#1fa387] text-white' : 'hover:text-slate-800'">Jangkauan</button>
                                    <button @click="trendMetric = 'sentimen'" class="flex-1 xs:flex-initial px-3 py-1 rounded-full transition cursor-pointer text-center" :class="trendMetric == 'sentimen' ? 'bg-[#1fa387] text-white' : 'hover:text-slate-800'">Sentimen</button>
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
                    </div>
                </section>
            @elseif($this->isTab('wawasan'))
                @php
                    $w = $this->getWawasan();
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
                <section class="flex-1 min-w-0 flex flex-col h-full overflow-hidden space-y-4 pr-1">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 shrink-0 text-left">
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
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-[11px] px-4 py-2 rounded-xl transition flex items-center justify-center gap-1.5 cursor-pointer shadow-sm shadow-indigo-600/20 disabled:opacity-50 disabled:cursor-not-allowed w-full sm:w-auto"
                        >
                            <svg wire:loading.remove wire:target="generateAiInsights" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            <svg wire:loading wire:target="generateAiInsights" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span wire:loading.remove wire:target="generateAiInsights">Perbarui Wawasan AI</span>
                            <span wire:loading wire:target="generateAiInsights">Memproses AI...</span>
                        </button>
                    </div>

                    <div style="height: calc(100vh - 250px);" class="overflow-y-auto pr-4 space-y-6">

                    <!-- Top Analytics KPI Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
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
                                <h3 class="text-3xl font-black tracking-tight leading-none text-{{ $this->viralMeta['viral_color'] }}-600">{{ $this->viralMeta['viral_status'] }}</h3>
                                <p class="text-[11px] font-semibold text-slate-400 truncate max-w-[170px]" title="{{ $this->viralMeta['viral_desc'] }}">{{ $this->viralMeta['viral_desc'] }}</p>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-{{ $this->viralMeta['viral_color'] }}-50 flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition-transform duration-200">
                                <svg class="w-5 h-5 text-{{ $this->viralMeta['viral_color'] }}-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                            </div>
                        </div>
                    </div>

                                    <!-- Main Columns (Unified 2-Column Masonry Stack) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 items-start">
                        <!-- Left Column: Summary, Recs, Negative Issues, Sentiment Shift -->
                        <div class="space-y-5">
                            <!-- Executive Summary -->
                            <div class="bg-gradient-to-br from-slate-50 to-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-4">
                                <div class="flex justify-between items-center pb-2 border-b border-slate-100/85 mb-4 relative z-10 w-full">
                                    <div class="space-y-0.5 text-left">
                                        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[18px] text-[#1fa387]">psychology</span>
                                            RINGKASAN EKSEKUTIF AI
                                        </h3>
                                        <p class="text-[10px] text-slate-400">Ringkasan wawasan cerdas dari AI berdasarkan data proyek terkini.</p>
                                    </div>
                                    <span class="text-[10px] font-bold text-[#1fa387] bg-[#1fa387]/10 px-2 py-0.5 rounded border border-[#1fa387]/20 uppercase tracking-wider">AI Generated</span>
                                </div>
                                <div class="text-slate-600 text-xs leading-relaxed space-y-2">
                                    {!! preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', nl2br(e($w['summary']))) !!}
                                </div>
                            </div>

                            <!-- Strategic Recommendations -->
                            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-4">
                                <div class="flex justify-between items-center pb-2 border-b border-slate-100/85 mb-4 w-full">
                                    <div class="space-y-0.5 text-left">
                                        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[18px] text-[#1fa387]">lightbulb</span>
                                            REKOMENDASI TINDAKAN STRATEGIS
                                        </h3>
                                        <p class="text-[10px] text-slate-400">Usulan langkah taktis dan keputusan strategis berbasis data.</p>
                                    </div>
                                </div>
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
                            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-4">
                                <div class="flex justify-between items-center pb-2 border-b border-slate-100/85 mb-4 relative z-10 w-full">
                                    <div class="space-y-0.5 text-left">
                                        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[18px] text-[#1fa387]">priority_high</span>
                                            TOP ISU NEGATIF
                                        </h3>
                                        <p class="text-[10px] text-slate-400">Sentimen negatif teratas yang perlu diantisipasi dan ditangani segera.</p>
                                    </div>
                                    <span class="text-[10px] font-bold text-rose-600 bg-rose-50 px-2 py-0.5 rounded border border-rose-100 uppercase">Prioritas</span>
                                </div>
                                <div class="space-y-3.5">
                                    @forelse($w['negative_issues'] as $issue)
                                        <div class="space-y-2 pb-3 border-b border-slate-100 last:border-0 last:pb-0">
                                            <div class="flex items-start justify-between gap-3">
                                                @if(!empty($issue['url']))
                                                    <a href="{{ $issue['url'] }}" target="_blank" rel="noopener noreferrer" class="text-xs font-bold text-slate-700 leading-relaxed hover:text-[#1fa387] hover:underline transition">
                                                        {{ $issue['issue'] }}
                                                    </a>
                                                @else
                                                    <p class="text-xs font-bold text-slate-700 leading-relaxed">{{ $issue['issue'] }}</p>
                                                @endif
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
                            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-4">
                                @php
                                    $shift = $w['sentiment_shift'];
                                    $shiftBadge = match ($shift['tone']) {
                                        'rose' => 'bg-rose-50 text-rose-700 border-rose-100',
                                        'emerald' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                                        default => 'bg-slate-50 text-slate-700 border-slate-100',
                                    };
                                @endphp
                                <div class="flex justify-between items-center pb-2 border-b border-slate-100/85 mb-4 w-full">
                                    <div class="space-y-0.5 text-left">
                                        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[18px] text-[#1fa387]">compare_arrows</span>
                                            PERUBAHAN SENTIMEN
                                        </h3>
                                        <p class="text-[10px] text-slate-400">Analisis tren pergeseran nada sentimen dibandingkan periode awal.</p>
                                    </div>
                                </div>
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

                            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-4">
                                <div class="flex justify-between items-center pb-2 border-b border-slate-100/85 mb-4 w-full">
                                    <div class="space-y-0.5 text-left">
                                        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[18px] text-[#1fa387]">task_alt</span>
                                            REKOMENDASI RESPONS
                                        </h3>
                                        <p class="text-[10px] text-slate-400">Usulan template respon dan cara bersikap di saluran media.</p>
                                    </div>
                                </div>
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

                        <!-- Right Column: Breakdown, Sources, Risk Triggers -->
                        <div class="space-y-5">
                            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-4">
                                <div class="flex justify-between items-center pb-2 border-b border-slate-100/85 mb-4 w-full">
                                    <div class="space-y-0.5 text-left">
                                        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[18px] text-[#1fa387]">donut_small</span>
                                            DISTRIBUSI KATEGORI ISU
                                        </h3>
                                        <p class="text-[10px] text-slate-400">Persentase sebaran artikel berdasarkan pengelompokan kategori.</p>
                                    </div>
                                </div>
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

                            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-4">
                                <div class="flex justify-between items-center pb-2 border-b border-slate-100/85 mb-4 w-full">
                                    <div class="space-y-0.5 text-left">
                                        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[18px] text-[#1fa387]">newspaper</span>
                                            KANAL MEDIA TERPOPULER
                                        </h3>
                                        <p class="text-[10px] text-slate-400">Statistik sebaran sentimen pada portal berita online teraktif.</p>
                                    </div>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-xs border-separate border-spacing-y-1">
                                        <thead>
                                            <tr class="bg-slate-50/75 rounded-xl text-slate-500 font-semibold text-[11px]">
                                                <th class="py-2.5 px-3 rounded-l-xl font-bold tracking-wide">Nama Portal</th>
                                                <th class="py-2.5 px-2 text-center font-bold tracking-wide">Total</th>
                                                <th class="py-2.5 px-3 text-center rounded-r-xl font-bold tracking-wide">Sentimen</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($w['sources'] as $src)
                                                @php
                                                    $srcPct = $w['total'] > 0 ? round(($src['total'] / $w['total']) * 100) : 0;
                                                    
                                                    $sPos = (int) ($src['positive'] ?? 0);
                                                    $sNeu = (int) ($src['neutral'] ?? 0);
                                                    $sNeg = (int) ($src['negative'] ?? 0);
                                                    $sTotal = $sPos + $sNeu + $sNeg;
                                                    
                                                    $posPct = $sTotal > 0 ? round(($sPos / $sTotal) * 100) : 0;
                                                    $neuPct = $sTotal > 0 ? round(($sNeu / $sTotal) * 100) : 0;
                                                    $negPct = $sTotal > 0 ? round(($sNeg / $sTotal) * 100) : 0;
                                                @endphp
                                                <tr class="hover:bg-slate-50/50 transition-all duration-150 group">
                                                    <td class="py-2.5 px-3 rounded-l-xl border-y border-l border-slate-100/50 group-hover:border-slate-200/50 font-bold text-slate-700">
                                                        <div class="flex items-center gap-2">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-[#1fa387] shrink-0"></span>
                                                            <span class="truncate max-w-[100px]">{{ $src['source_name'] ?: 'Portal' }}</span>
                                                        </div>
                                                    </td>
                                                    <td class="py-2.5 px-2 text-center border-y border-slate-100/50 group-hover:border-slate-200/50">
                                                        <span class="font-extrabold text-slate-800">{{ $src['total'] }}</span>
                                                        <span class="text-slate-400 text-[9px] block font-medium mt-0.5">({{ $srcPct }}%)</span>
                                                    </td>
                                                    <td class="py-2.5 px-3 rounded-r-xl border-y border-r border-slate-100/50 group-hover:border-slate-200/50">
                                                        <div class="flex flex-col gap-1 items-center justify-center">
                                                            <!-- Percentage Text Badges -->
                                                            <div class="flex items-center gap-1 text-[8.5px] font-bold">
                                                                <span class="text-emerald-700 bg-emerald-50 px-1 rounded">{{ $posPct }}%</span>
                                                                <span class="text-slate-600 bg-slate-50 px-1 rounded">±{{ $neuPct }}%</span>
                                                                <span class="text-rose-700 bg-rose-50 px-1 rounded">-{{ $negPct }}%</span>
                                                            </div>
                                                            <!-- Visual Stacked Bar -->
                                                            <div class="w-20 h-1.5 rounded-full bg-slate-100 overflow-hidden flex">
                                                                <div class="bg-emerald-500 h-full" style="width: {{ $posPct }}%"></div>
                                                                <div class="bg-slate-400 h-full" style="width: {{ $neuPct }}%"></div>
                                                                <div class="bg-rose-500 h-full" style="width: {{ $negPct }}%"></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="3" class="py-4 text-center text-xs text-slate-400 italic">Belum ada data media.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
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
                        </div>
                    </div>
                </section>
            @elseif($this->isTab('laporan'))
                <!-- TAB 4: Laporan (Report configuration page matching screenshots) -->
                <section class="flex-1 min-w-0 flex flex-col h-full overflow-hidden space-y-4 pr-1" x-data="{
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
                            <h2 class="text-lg sm:text-xl font-bold text-slate-900 leading-none flex items-center gap-1.5 text-left">
                                <span class="material-symbols-outlined text-[#1fa387] text-[20px] sm:text-[22px]">assignment</span>Konfigurasi Laporan
                            </h2>
                            <p class="text-[10px] sm:text-xs text-slate-400 mt-1.5 text-left leading-relaxed">Pilih komponen data yang akan disertakan dalam dokumen.</p>
                        </div>
                    </div>

                    <div style="height: calc(100vh - 250px);" class="overflow-y-auto pr-4 space-y-6">

                    <!-- Main Config Card -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-6 text-left">
                        <!-- Tab Toggles -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
                            <div class="flex gap-3 w-full sm:w-auto">
                                <!-- PDF Tab -->
                                <button 
                                    type="button"
                                    @click="reportType = 'pdf'"
                                    class="flex-1 sm:flex-none flex items-center justify-center gap-2 px-5 py-2.5 text-xs font-bold rounded-xl border transition cursor-pointer"
                                    :class="reportType === 'pdf' ? 'bg-[#1fa387]/5 border-[#1fa387] text-[#1fa387]' : 'bg-slate-50 border-slate-200 text-slate-500 hover:text-slate-700'"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                    <span>Laporan PDF</span>
                                </button>
                                <!-- Excel Tab -->
                                <button 
                                    type="button"
                                    @click="reportType = 'excel'"
                                    class="flex-1 sm:flex-none flex items-center justify-center gap-2 px-5 py-2.5 text-xs font-bold rounded-xl border transition cursor-pointer"
                                    :class="reportType === 'excel' ? 'bg-[#1fa387]/5 border-[#1fa387] text-[#1fa387]' : 'bg-slate-50 border-slate-200 text-slate-500 hover:text-slate-700'"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    <span>Laporan Excel</span>
                                </button>
                            </div>

                            <button 
                                type="button"
                                @click="pilihSemua()"
                                class="bg-[#1fa387] hover:bg-[#1fa387]/90 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition flex items-center justify-center gap-1.5 cursor-pointer shadow-sm w-full sm:w-auto"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                <span>Pilih Semua</span>
                            </button>
                        </div>

                    <!-- PDF Option List -->
                    <div x-show="reportType === 'pdf'" class="space-y-6">
                        <!-- Group 1 -->
                        <div class="space-y-4">
                                <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Isi Laporan PDF</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <!-- Kesimpulan AI -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 min-w-0 flex-1">
                                            <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                                                <span class="material-symbols-outlined text-[18px]">auto_graph</span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <h5 class="text-xs font-bold text-slate-800">Kesimpulan AI</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-snug">Ringkasan otomatis dari insight dan temuan utama</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="pdfToggles.wawasan = !pdfToggles.wawasan" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="pdfToggles.wawasan ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="pdfToggles.wawasan ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- Ringkasan Statistik -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 min-w-0 flex-1">
                                            <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                                                <span class="material-symbols-outlined text-[18px]">bar_chart</span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <h5 class="text-xs font-bold text-slate-800">Ringkasan Statistik</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-snug">Distribusi sumber dan metrik performa utama</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="pdfToggles.statistik = !pdfToggles.statistik" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="pdfToggles.statistik ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="pdfToggles.statistik ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- Grafik Penyebutan -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 min-w-0 flex-1">
                                            <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                                                <span class="material-symbols-outlined text-[18px]">query_stats</span>
                                            </div>
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
                                            <div class="w-8 h-8 rounded-xl bg-pink-50 text-pink-600 flex items-center justify-center">
                                                <span class="material-symbols-outlined text-[18px]">sentiment_satisfied</span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <h5 class="text-xs font-bold text-slate-800">Grafik Sentimen</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-snug">Analisis sentimen dari percakapan media</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="pdfToggles.grafikSentimen = !pdfToggles.grafikSentimen" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="pdfToggles.grafikSentimen ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="pdfToggles.grafikSentimen ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- Potensi Viral -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 min-w-0 flex-1">
                                            <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                                <span class="material-symbols-outlined text-[18px]">local_fire_department</span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <h5 class="text-xs font-bold text-slate-800">Potensi Viral</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-snug">Konten dengan potensi interaksi atau jangkauan tertinggi</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="pdfToggles.konteks = !pdfToggles.konteks" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="pdfToggles.konteks ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="pdfToggles.konteks ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- Analisis Kata Kunci -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 min-w-0 flex-1">
                                            <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                                                <span class="material-symbols-outlined text-[18px]">tag</span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <h5 class="text-xs font-bold text-slate-800">Analisis Kata Kunci</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-snug">Top keyword yang paling sering muncul di laporan</p>
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
                                <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Penyebutan & Sumber</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <!-- 5 Terpopuler -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 min-w-0 flex-1">
                                            <div class="w-8 h-8 rounded-xl bg-red-50 text-red-600 flex items-center justify-center">
                                                <span class="material-symbols-outlined text-[18px]">trending_up</span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <h5 class="text-xs font-bold text-slate-800">5 Terpopuler</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-snug">Daftar penyebutan dengan estimasi jangkauan tertinggi</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="pdfToggles.beritaPopuler = !pdfToggles.beritaPopuler" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="pdfToggles.beritaPopuler ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="pdfToggles.beritaPopuler ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- 5 Berita Terbaru -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 min-w-0 flex-1">
                                            <div class="w-8 h-8 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center">
                                                <span class="material-symbols-outlined text-[18px]">calendar_today</span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <h5 class="text-xs font-bold text-slate-800">5 Berita Terbaru</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-snug">Penyebutan terbaru yang masuk ke laporan</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="pdfToggles.beritaTerbaru = !pdfToggles.beritaTerbaru" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="pdfToggles.beritaTerbaru ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="pdfToggles.beritaTerbaru ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- 5 Portal Negatif -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 min-w-0 flex-1">
                                            <div class="w-8 h-8 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center">
                                                <span class="material-symbols-outlined text-[18px]">newspaper</span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <h5 class="text-xs font-bold text-slate-800">5 Portal Negatif</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-snug">Portal berita dengan sentimen negatif tertinggi</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="pdfToggles.sumberBerita = !pdfToggles.sumberBerita" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="pdfToggles.sumberBerita ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="pdfToggles.sumberBerita ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- 5 Besar Medsos Negatif -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 min-w-0 flex-1">
                                            <div class="w-8 h-8 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center">
                                                <span class="material-symbols-outlined text-[18px]">groups</span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <h5 class="text-xs font-bold text-slate-800">5 Besar Medsos Negatif</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-snug">Akun media sosial dengan sentimen negatif tertinggi</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="pdfToggles.sumberMedsos = !pdfToggles.sumberMedsos" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="pdfToggles.sumberMedsos ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="pdfToggles.sumberMedsos ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- Rekomendasi AI -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 min-w-0 flex-1">
                                            <div class="w-8 h-8 rounded-xl bg-yellow-50 text-yellow-600 flex items-center justify-center">
                                                <span class="material-symbols-outlined text-[18px]">lightbulb</span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <h5 class="text-xs font-bold text-slate-800">Rekomendasi AI</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-snug">Saran tindak lanjut berdasarkan analisis data</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="pdfToggles.rekomendasi = !pdfToggles.rekomendasi" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="pdfToggles.rekomendasi ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="pdfToggles.rekomendasi ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end pt-6 border-t border-slate-100">
                                <button
                                    type="button"
                                    @click="$wire.preparePdfReport(JSON.stringify(pdfToggles))"
                                    wire:loading.attr="disabled"
                                    wire:target="preparePdfReport"
                                    class="bg-[#c0392b] hover:bg-[#a93226] disabled:opacity-60 disabled:cursor-not-allowed text-white font-bold text-xs px-6 py-3 rounded-xl transition flex items-center gap-1.5 cursor-pointer shadow-sm"
                                >
                                    <svg wire:loading.remove wire:target="preparePdfReport" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    <svg wire:loading wire:target="preparePdfReport" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M20 2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8.5 7.5c0 .83-.67 1.5-1.5 1.5H9v2H7.5V7H10c.83 0 1.5.67 1.5 1.5v1zm5 2c0 .83-.67 1.5-1.5 1.5h-2.5V7H15c.83 0 1.5.67 1.5 1.5v3zm4-3H19v1h1.5V11H19v2h-1.5V7h3v1.5zM9 9.5h1v-1H9v1zM4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm10 5.5h1v-3h-1v3z"/></svg>
                                    <span>⬇ Unduh Laporan PDF</span>
                                </button>
                            </div>

                            <div wire:loading.flex wire:target="preparePdfReport" class="fixed inset-0 z-[90] items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
                                <div class="w-full max-w-md rounded-3xl bg-white shadow-2xl border border-slate-200 p-6 text-center">
                                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-[#1fa387]/10 text-[#1fa387]">
                                        <svg class="h-7 w-7 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </div>
                                    <h3 class="text-base font-extrabold text-slate-900">Menyusun AI Report</h3>
                                    <p class="mt-2 text-sm text-slate-500">AI sedang menyiapkan kesimpulan dan rekomendasi berdasarkan isu berita terbaru. Mohon tunggu sebentar.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Excel Option List -->
                        <div x-show="reportType === 'excel'" class="space-y-6" style="display: none;">
                            <!-- Group 1 -->
                            <div class="space-y-4">
                                <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Isi Laporan Excel</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <!-- Ringkasan Statistik -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                                                <span class="material-symbols-outlined text-[18px]">summarize</span>
                                            </div>
                                            <div>
                                                <h5 class="text-xs font-bold text-slate-800">Ringkasan Statistik</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-none">Ringkasan isi laporan dan metrik utama</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="excelToggles.ringkasan = !excelToggles.ringkasan" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="excelToggles.ringkasan ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="excelToggles.ringkasan ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- Data Penyebutan -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                                                <span class="material-symbols-outlined text-[18px]">article</span>
                                            </div>
                                            <div>
                                                <h5 class="text-xs font-bold text-slate-800">Data Penyebutan</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-none">Daftar penyebutan terbaru dengan detail lengkap</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="excelToggles.terbaru = !excelToggles.terbaru" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="excelToggles.terbaru ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="excelToggles.terbaru ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- Analisis Kata Kunci -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                                                <span class="material-symbols-outlined text-[18px]">topic</span>
                                            </div>
                                            <div>
                                                <h5 class="text-xs font-bold text-slate-800">Analisis Kata Kunci</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-none">Pengelompokan kata kunci yang sering muncul</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="excelToggles.kategori = !excelToggles.kategori" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="excelToggles.kategori ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="excelToggles.kategori ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- Grafik Penyebutan -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                                <span class="material-symbols-outlined text-[18px]">query_stats</span>
                                            </div>
                                            <div>
                                                <h5 class="text-xs font-bold text-slate-800">Grafik Penyebutan</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-none">Visualisasi tren penyebutan dari laporan</p>
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
                                <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Analisis & Sumber</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <!-- Penyebutan Per Sumber -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center">
                                                <span class="material-symbols-outlined text-[18px]">hub</span>
                                            </div>
                                            <div>
                                                <h5 class="text-xs font-bold text-slate-800">Penyebutan Per Sumber</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-none">Sumber paling aktif di laporan</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="excelToggles.situsBerpengaruh = !excelToggles.situsBerpengaruh" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="excelToggles.situsBerpengaruh ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="excelToggles.situsBerpengaruh ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- 5 Terpopuler -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center">
                                                <span class="material-symbols-outlined text-[18px]">whatshot</span>
                                            </div>
                                            <div>
                                                <h5 class="text-xs font-bold text-slate-800">5 Terpopuler</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-none">Penyebutan dengan jangkauan tertinggi</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="excelToggles.populer = !excelToggles.populer" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="excelToggles.populer ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="excelToggles.populer ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- 5 Besar Medsos Negatif -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-xl bg-red-50 text-red-600 flex items-center justify-center">
                                                <span class="material-symbols-outlined text-[18px]">groups</span>
                                            </div>
                                            <div>
                                                <h5 class="text-xs font-bold text-slate-800">5 Besar Medsos Negatif</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-none">Akun media sosial dengan sentimen negatif tertinggi</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="excelToggles.influencer = !excelToggles.influencer" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="excelToggles.influencer ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="excelToggles.influencer ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- 5 Portal Negatif -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center">
                                                <span class="material-symbols-outlined text-[18px]">news</span>
                                            </div>
                                            <div>
                                                <h5 class="text-xs font-bold text-slate-800">5 Portal Negatif</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-none">Portal berita dengan sentimen negatif tertinggi</p>
                                            </div>
                                        </div>
                                        <button type="button" @click="excelToggles.situsAktif = !excelToggles.situsAktif" class="relative inline-flex h-5 w-9 items-center rounded-full transition cursor-pointer" :class="excelToggles.situsAktif ? 'bg-[#1fa387]' : 'bg-slate-200'">
                                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white transition duration-200" :class="excelToggles.situsAktif ? 'translate-x-4.5' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                    <!-- Rekomendasi AI -->
                                    <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-xl bg-yellow-50 text-yellow-600 flex items-center justify-center">
                                                <span class="material-symbols-outlined text-[18px]">lightbulb</span>
                                            </div>
                                            <div>
                                                <h5 class="text-xs font-bold text-slate-800">Rekomendasi AI</h5>
                                                <p class="text-[9.5px] text-slate-400 mt-0.5 leading-none">Saran tindak lanjut berdasarkan analisis data</p>
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
                                    :href="`{{ route('report.excel', ['project_id' => $this->getDecodedProjectId()]) }}&start_date={{ $startDate }}&end_date={{ $endDate }}&toggles=` + encodeURIComponent(JSON.stringify(excelToggles))"
                                    class="bg-[#1fa387] hover:bg-[#178a70] text-white font-bold text-xs px-6 py-3 rounded-xl transition flex items-center gap-1.5 cursor-pointer shadow-sm"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                                    <span>⬇ Unduh Laporan Excel</span>
                                </a>
                            </div>
                            </div>
                        </div>
                    </div>
                    </div>
                </section>

            @elseif($this->isTab('konten'))
                <section class="flex-1 min-w-0 flex flex-col h-full overflow-hidden space-y-4 pr-1">
                    <div class="flex items-center justify-between shrink-0">
                        <div>
                            <h2 class="text-lg sm:text-xl font-bold text-slate-900 leading-none flex items-center gap-1.5 text-left">
                                <span class="material-symbols-outlined text-[#1fa387] text-[20px] sm:text-[22px]">article</span>Manajemen Konten
                            </h2>
                            <p class="text-[10px] sm:text-xs text-slate-400 mt-1.5 text-left leading-relaxed">Galeri konten artikel dan postingan yang berhasil dikumpulkan.</p>
                        </div>
                    </div>
                    
                    @php
                        $contentArticlesList = $this->getArticles();
                        $contentArticlesCount = $contentArticlesList->count();
                        $contentTotalArticlesCount = $this->getTotalArticlesCount();
                        $contentFeedSignature = md5(json_encode([
                            'project' => $projectId,
                            'sources' => $selectedSources,
                            'sentiment' => $selectedSentiment,
                            'search' => $search,
                            'start' => $startDate,
                            'end' => $endDate,
                            'sort' => $sortBy,
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    @endphp

                    <div
                        style="height: calc(100vh - 250px);"
                        class="overflow-y-auto pr-4 space-y-6"
                        wire:key="content-feed-scroll-shell-{{ $contentFeedSignature }}"
                        data-total-count="{{ $contentTotalArticlesCount }}"
                        x-data="{ lastLoadMoreAt: 0, loadMoreTimer: null }"
                        x-init="
                            const feedEl = $el;
                            const triggerLoadMore = () => {
                                const total = parseInt(feedEl.getAttribute('data-total-count') || '0', 10);
                                const loaded = feedEl.querySelectorAll('[data-content-card]').length;
                                if (loaded >= total) return;
                                const usesWindowScroll = feedEl.scrollHeight <= (feedEl.clientHeight + 4);
                                const nearBottom = usesWindowScroll
                                    ? (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 220)
                                    : (feedEl.scrollTop + feedEl.clientHeight >= feedEl.scrollHeight - 220);
                                if (!nearBottom) return;
                                if (Date.now() - lastLoadMoreAt < 1200) return;
                                lastLoadMoreAt = Date.now();
                                Promise.resolve($wire.loadMore())
                                    .catch(() => {})
                                    .finally(() => setTimeout(() => { lastLoadMoreAt = 0; }, 900));
                            };
                            const onScroll = () => requestAnimationFrame(triggerLoadMore);
                            feedEl.addEventListener('scroll', onScroll, { passive: true });
                            window.addEventListener('scroll', onScroll, { passive: true });
                            window.addEventListener('resize', onScroll);
                            if (loadMoreTimer) {
                                clearInterval(loadMoreTimer);
                            }
                            loadMoreTimer = setInterval(triggerLoadMore, 900);
                            triggerLoadMore();
                        "
                    >
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                        @forelse($contentArticlesList as $article)
                            @php
                                $articleReachDisp = $this->getProjectReachDisplayData($article);
                            @endphp
                            <div data-content-card class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 shadow-[0_4px_15px_-3px_rgba(0,0,0,0.03)] hover:shadow-[0_8px_25px_-5px_rgba(31,163,135,0.1)] hover:border-[#1fa387]/30 transition-all flex flex-col group">
                                <div class="flex items-start sm:items-center justify-between gap-2 mb-3 sm:mb-4">
                                    <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap">
                                        <span class="text-[8.5px] sm:text-[10px] font-extrabold px-2 py-0.5 sm:px-3 sm:py-1.5 rounded-lg uppercase tracking-wider {{ $this->getValidAiResult($article)?->sentiment_score ?? 0 >= 0.3 ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : ($this->getValidAiResult($article)?->sentiment_score ?? 0 <= -0.3 ? 'bg-rose-50 text-rose-600 border border-rose-100' : 'bg-slate-50 text-slate-600 border border-slate-100') }}">
                                            {{ $this->getValidAiResult($article)?->sentiment_score ?? 0 >= 0.3 ? 'Positif' : ($this->getValidAiResult($article)?->sentiment_score ?? 0 <= -0.3 ? 'Negatif' : 'Netral') }}
                                        </span>
                                        
                                        <!-- Ringkasan AI Button -->
                                        <button 
                                            type="button"
                                            @click="window.openDashboardDetail(
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
                                            class="inline-flex items-center gap-1 text-[8.5px] sm:text-[9px] font-bold px-2 py-0.5 sm:px-2.5 sm:py-1 bg-emerald-55 text-emerald-600 hover:bg-emerald-100 border border-emerald-200 rounded-lg uppercase tracking-wider transition-colors cursor-pointer"
                                            style="background-color: #ecfdf5; border-color: #a7f3d0;"
                                        >
                                            <span class="material-symbols-outlined text-[10px] sm:text-[12px] text-emerald-600">auto_awesome</span>
                                            <span>Ringkasan AI</span>
                                        </button>
                                    </div>
                                    <div class="flex items-center gap-1 text-[8.5px] xs:text-[9.5px] sm:text-xs font-bold text-slate-400 bg-slate-50 px-1.5 xs:px-2.5 py-1 rounded-lg border border-slate-100 leading-tight">
                                        <span class="material-symbols-outlined text-[12px] sm:text-[14px]">schedule</span>
                                        {{ \Carbon\Carbon::parse($article->published_at)->format('d M Y, H:i') }}
                                    </div>
                                </div>
                                <h3 class="text-xs sm:text-sm md:text-[17px] font-extrabold text-slate-900 leading-snug mb-2 sm:mb-3 line-clamp-2 group-hover:text-[#1fa387] transition-colors tracking-tight">
                                    <a href="{{ $article->url }}" target="_blank">{{ $article->title }}</a>
                                </h3>
                                <p class="text-[11px] sm:text-sm text-slate-600 line-clamp-3 mb-4 sm:mb-5 leading-relaxed flex-grow font-medium">
                                    {{ Str::limit(strip_tags($article->content), 120) }}
                                </p>
                                <div class="flex items-center justify-between gap-3 pt-3.5 border-t border-slate-100 mt-auto min-w-0">
                                    <div class="flex items-center gap-1.5 sm:gap-2.5 bg-slate-55 pl-1 pr-2.5 py-0.5 sm:py-1 rounded-full border border-slate-100 min-w-0 max-w-[55%] xs:max-w-none">
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
                                        <div class="w-5 h-5 sm:w-6 sm:h-6 rounded-full overflow-hidden flex items-center justify-center shadow-sm flex-shrink-0 {{ $logoBg }} border border-slate-200/60 p-0.5">
                                            @if(str_contains($srcLower, 'facebook') || $srcLower === 'fb')
                                                <svg class="w-3 h-3 fill-current text-white" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"></path></svg>
                                            @elseif(str_contains($srcLower, 'instagram') || $srcLower === 'ig')
                                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5" stroke-linecap="round"></line></svg>
                                            @elseif(str_contains($srcLower, 'tiktok') || $srcLower === 'tk')
                                                <svg class="w-3 h-3 fill-current text-white" viewBox="0 0 24 24"><path d="M12.525.01c1.306-.022 2.615-.011 3.921-.012.08 1.836 1.011 3.5 2.501 4.485.006 1.341-.004 2.683-.004 4.024-1.57-.107-3.067-.932-3.955-2.247-.008 2.827-.003 5.657-.005 8.486-.098 3.546-3.13 6.643-6.726 6.467-3.526-.067-6.523-3.18-6.241-6.722.215-3.327 3.012-6.104 6.347-5.992v4.06c-1.393-.16-2.775.76-3.085 2.112-.397 1.488.583 3.125 2.1 3.328 1.455.234 2.924-.766 3.14-2.224.048-2.617.02-5.237.03-7.856.002-3.834-.002-7.67.002-11.504z"></path></svg>
                                            @else
                                                <div class="relative w-full h-full flex items-center justify-center" x-data="{ imgFailed: false }">
                                                    <img x-show="!imgFailed" 
                                                         src="{{ $this->resolveArticleLogoUrl($article) }}" 
                                                         x-on:error="imgFailed = true"
                                                         class="w-full h-full object-cover" 
                                                         alt="{{ $article->source_name }}" />
                                                    <div x-show="imgFailed" class="absolute inset-0 w-full h-full bg-transparent flex items-center justify-center" style="display: none;">
                                                        <svg class="w-3 h-3 text-[#1fa387]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path></svg>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                        <span class="text-[10px] sm:text-[11px] font-extrabold text-slate-700 tracking-wide truncate">{{ $article->source_name }}</span>
                                    </div>
                                    <button 
                                        type="button"
                                        @click="window.openDashboardDetail(
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
                                        class="text-[9px] sm:text-xs font-bold text-white bg-[#1fa387] hover:bg-[#178a70] px-2 py-1 sm:px-4 sm:py-2 rounded-xl transition-colors shadow-sm flex items-center gap-0.5 cursor-pointer shrink-0"
                                    >
                                        Selengkapnya
                                        <span class="material-symbols-outlined text-[10px] sm:text-[12px]">arrow_forward</span>
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
                    @if($contentArticlesCount < $contentTotalArticlesCount)
                        <div class="py-6 text-center text-xs text-slate-500 font-medium flex items-center justify-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-[#1fa387]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Memuat data lainnya...</span>
                        </div>
                    @else
                        @if($contentArticlesCount > 0)
                        <div class="py-6 mt-4 border-t border-slate-100 text-center text-xs text-slate-400 font-medium">
                            <p class="text-slate-500 font-semibold">Semua konten telah dimuat</p>
                        </div>
                        @endif
                    @endif
                    </div>
                    </div>
                </section>

            @elseif($this->isTab('sumber'))
                <section class="flex-1 min-w-0 flex flex-col h-full overflow-hidden space-y-4 pr-1">
                    <div class="flex items-center justify-between shrink-0">
                        <div>
                            <h2 class="text-lg sm:text-xl font-bold text-slate-900 leading-none flex items-center gap-1.5 text-left">
                                <span class="material-symbols-outlined text-[#1fa387] text-[20px] sm:text-[22px]">public</span>Sumber Data
                            </h2>
                            <p class="text-[10px] sm:text-xs text-slate-400 mt-1.5 text-left leading-relaxed">Statistik dan daftar sumber portal yang menyebut proyek ini.</p>
                        </div>
                    </div>
                    
                    @php
                        $sourceArticlesList = $this->getArticles();
                        $sourceArticlesCount = $sourceArticlesList->count();
                        $sourceTotalArticlesCount = $this->getTotalArticlesCount();
                        $sourceFeedSignature = md5(json_encode([
                            'project' => $projectId,
                            'sources' => $selectedSources,
                            'sentiment' => $selectedSentiment,
                            'search' => $search,
                            'start' => $startDate,
                            'end' => $endDate,
                            'sort' => $sortBy,
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    @endphp
                    <div
                        style="height: calc(100vh - 250px);"
                        class="overflow-y-auto pr-4 space-y-6"
                        wire:key="source-feed-scroll-shell-{{ $sourceFeedSignature }}"
                        data-total-count="{{ $sourceTotalArticlesCount }}"
                        x-data="{ lastLoadMoreAt: 0, loadMoreTimer: null }"
                        x-init="
                            const feedEl = $el;
                            const triggerLoadMore = () => {
                                const total = parseInt(feedEl.getAttribute('data-total-count') || '0', 10);
                                const loaded = feedEl.querySelectorAll('[data-source-card]').length;
                                if (loaded >= total) return;
                                const usesWindowScroll = feedEl.scrollHeight <= (feedEl.clientHeight + 4);
                                const nearBottom = usesWindowScroll
                                    ? (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 220)
                                    : (feedEl.scrollTop + feedEl.clientHeight >= feedEl.scrollHeight - 220);
                                if (!nearBottom) return;
                                if (Date.now() - lastLoadMoreAt < 1200) return;
                                lastLoadMoreAt = Date.now();
                                Promise.resolve($wire.loadMore())
                                    .catch(() => {})
                                    .finally(() => setTimeout(() => { lastLoadMoreAt = 0; }, 900));
                            };
                            feedEl.addEventListener('scroll', triggerLoadMore, { passive: true });
                            window.addEventListener('scroll', triggerLoadMore, { passive: true });
                            window.addEventListener('resize', triggerLoadMore);
                            if (feedEl.loadMoreTimer) {
                                clearInterval(feedEl.loadMoreTimer);
                            }
                            feedEl.loadMoreTimer = setInterval(triggerLoadMore, 900);
                            triggerLoadMore();
                        "
                    >

                    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm space-y-6">
                        <div class="flex justify-between items-center pb-2 border-b border-slate-100/85 mb-4 w-full">
                            <div class="space-y-0.5 text-left">
                                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[18px] text-[#1fa387]">list_alt</span>
                                    DAFTAR PORTAL & KANAL PEMBUAT BERITA
                                </h3>
                                <p class="text-[10px] text-slate-400">Situs web berita online dan media sosial yang mempublikasikan konten terkait kata kunci Anda.</p>
                            </div>
                        </div>

                        @php
                            $projectSourcesList = $this->getProjectSources();
                            $grandTotal = collect($projectSourcesList)->sum('total');
                        @endphp
                        <div class="block sm:hidden space-y-3" wire:key="source-mobile-list">
                            @forelse($projectSourcesList as $src)
                                @php
                                    $srcName = data_get($src, 'source_name', 'Sumber tidak diketahui') ?: 'Sumber tidak diketahui';
                                    $srcLower = strtolower($srcName);
                                    
                                    $isSocial = in_array($srcLower, self::SOCIAL_SOURCE_NAMES, true) 
                                        || str_contains($srcLower, 'instagram') 
                                        || str_contains($srcLower, 'tiktok') 
                                        || str_contains($srcLower, 'facebook') 
                                        || str_contains($srcLower, 'twitter')
                                        || str_contains($srcLower, 'youtube')
                                        || str_contains($srcLower, 'threads');
                                        
                                    $mediaType = $isSocial ? 'Media Sosial' : 'Portal Berita';
                                    $typeColor = $isSocial ? 'bg-indigo-50 text-indigo-700 border-indigo-100/60' : 'bg-sky-50 text-sky-700 border-sky-100/60';
                                    
                                    $srcTotal = (int) data_get($src, 'total', 0);
                                    $sovPct = $grandTotal > 0 ? round(($srcTotal / $grandTotal) * 100) : 0;
                                    
                                    $sPos = (int) data_get($src, 'positive', 0);
                                    $sNeu = (int) data_get($src, 'neutral', 0);
                                    $sNeg = (int) data_get($src, 'negative', 0);
                                    $sTotal = $sPos + $sNeu + $sNeg;
                                    
                                    $posPct = $sTotal > 0 ? round(($sPos / $sTotal) * 100) : 0;
                                    $neuPct = $sTotal > 0 ? round(($sNeu / $sTotal) * 100) : 0;
                                    $negPct = $sTotal > 0 ? round(($sNeg / $sTotal) * 100) : 0;
                                    
                                    if (str_contains($srcLower, 'instagram') || $srcLower === 'ig') {
                                        $logoBg = 'bg-gradient-to-br from-purple-600 via-pink-500 to-orange-400';
                                    } elseif (str_contains($srcLower, 'tiktok') || $srcLower === 'tk') {
                                        $logoBg = 'bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800';
                                    } elseif (str_contains($srcLower, 'facebook') || $srcLower === 'fb') {
                                        $logoBg = 'bg-gradient-to-br from-blue-600 to-blue-700';
                                    } else {
                                        $logoBg = 'bg-white';
                                    }

                                    $faviconDomain = str_replace(' ', '', $srcLower);
                                    if (!str_contains($faviconDomain, '.')) {
                                        $faviconDomain .= '.com';
                                    }
                                @endphp
                                <div class="bg-slate-50/50 border border-slate-100 rounded-2xl p-4 space-y-3.5 text-left">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <div class="w-8 h-8 rounded-lg overflow-hidden flex items-center justify-center shadow-sm flex-shrink-0 {{ $logoBg }} border border-slate-200/40 p-0.5">
                                                @if(str_contains($srcLower, 'facebook') || $srcLower === 'fb')
                                                    <svg class="w-4 h-4 fill-current text-white" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"></path></svg>
                                                @elseif(str_contains($srcLower, 'instagram') || $srcLower === 'ig')
                                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5" stroke-linecap="round"></line></svg>
                                                @elseif(str_contains($srcLower, 'tiktok') || $srcLower === 'tk')
                                                    <svg class="w-4 h-4 fill-current text-white" viewBox="0 0 24 24"><path d="M12.525.01c1.306-.022 2.615-.011 3.921-.012.08 1.836 1.011 3.5 2.501 4.485.006 1.341-.004 2.683-.004 4.024-1.57-.107-3.067-.932-3.955-2.247-.008 2.827-.003 5.657-.005 8.486-.098 3.546-3.13 6.643-6.726 6.467-3.526-.067-6.523-3.18-6.241-6.722.215-3.327 3.012-6.104 6.347-5.992v4.06c-1.393-.16-2.775.76-3.085 2.112-.397 1.488.583 3.125 2.1 3.328 1.455.234 2.924-.766 3.14-2.224.048-2.617.02-5.237.03-7.856.002-3.834-.002-7.67.002-11.504z"></path></svg>
                                                @else
                                                    <div class="relative w-full h-full flex items-center justify-center" x-data="{ imgFailedMob: false }">
                                                        <img x-show="!imgFailedMob" 
                                                             src="{{ 'https://www.google.com/s2/favicons?domain=' . $faviconDomain }}&sz=64" 
                                                             x-on:error="imgFailedMob = true"
                                                             class="w-4.5 h-4.5 object-contain" 
                                                             alt="{{ $srcName }}" />
                                                        <div x-show="imgFailedMob" class="absolute inset-0 w-full h-full bg-slate-50 flex items-center justify-center">
                                                            <span class="material-symbols-outlined text-[13px] text-slate-400">feed</span>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="flex flex-col text-left min-w-0">
                                                <span class="text-xs font-bold text-slate-800 truncate leading-snug">{{ $srcName }}</span>
                                                <span class="text-[9px] text-slate-400 truncate mt-0.5">{{ $isSocial ? '@' . $srcLower : $srcLower }}</span>
                                            </div>
                                        </div>
                                        <span class="px-2 py-0.5 text-[8.5px] font-bold rounded border {{ $typeColor }} uppercase tracking-wider scale-90">
                                            {{ $mediaType }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between border-t border-slate-100 pt-2.5">
                                        <div class="flex flex-col">
                                            <span class="text-[11px] font-black text-slate-800">{{ number_format($srcTotal, 0, ',', '.') }}</span>
                                            <span class="text-[8px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">Penyebutan (SOV: {{ $sovPct }}%)</span>
                                        </div>
                                        <div class="flex items-center gap-1 text-[8.5px] font-bold">
                                            <span class="text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-100/40">+{{ $posPct }}%</span>
                                            <span class="text-slate-600 bg-slate-50 px-1.5 py-0.5 rounded border border-slate-150">±{{ $neuPct }}%</span>
                                            <span class="text-rose-700 bg-rose-50 px-1.5 py-0.5 rounded border border-rose-100/40">-{{ $negPct }}%</span>
                                        </div>
                                    </div>
                                    <div class="border-t border-slate-100 pt-2.5 space-y-1">
                                        <div class="w-full h-1.5 rounded-full bg-slate-100 overflow-hidden flex shadow-inner">
                                            <div class="bg-emerald-500 h-full" style="width: {{ $posPct }}%"></div>
                                            <div class="bg-slate-400 h-full" style="width: {{ $neuPct }}%"></div>
                                            <div class="bg-rose-500 h-full" style="width: {{ $negPct }}%"></div>
                                        </div>
                                        <div class="flex justify-between text-[7.5px] text-slate-400 font-bold px-0.5">
                                            <span>{{ $sPos }} Positif</span>
                                            <span>{{ $sNeu }} Netral</span>
                                            <span>{{ $sNeg }} Negatif</span>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="py-8 text-center text-slate-450 text-xs font-semibold italic">Belum ada portal berita atau akun media sosial yang melacak proyek ini.</div>
                            @endforelse
                        </div>

                        <div class="hidden sm:block overflow-x-auto">
                            <table class="w-full text-left text-sm border-separate border-spacing-y-1">
                                <thead>
                                    <tr class="bg-slate-50/75 rounded-2xl text-slate-500 text-xs font-semibold">
                                        <th class="py-3.5 px-4 rounded-l-2xl font-bold tracking-wide">Logo & Nama Portal / Media</th>
                                        <th class="py-3.5 px-3 text-center font-bold tracking-wide">Kategori</th>
                                        <th class="py-3.5 px-3 text-center font-bold tracking-wide">Penyebutan</th>
                                        <th class="py-3.5 px-3 text-center font-bold tracking-wide">Analisis Sentimen</th>
                                        <th class="py-3.5 px-4 text-center rounded-r-2xl font-bold tracking-wide w-[180px]">Rasio Visual</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($projectSourcesList as $src)
                                        @php
                                            $srcName = data_get($src, 'source_name', 'Sumber tidak diketahui') ?: 'Sumber tidak diketahui';
                                            $srcLower = strtolower($srcName);
                                            
                                            // Determine media type
                                            $isSocial = in_array($srcLower, self::SOCIAL_SOURCE_NAMES, true) 
                                                || str_contains($srcLower, 'instagram') 
                                                || str_contains($srcLower, 'tiktok') 
                                                || str_contains($srcLower, 'facebook') 
                                                || str_contains($srcLower, 'twitter')
                                                || str_contains($srcLower, 'youtube')
                                                || str_contains($srcLower, 'threads');
                                                
                                            $mediaType = $isSocial ? 'Media Sosial' : 'Portal Berita';
                                            $typeColor = $isSocial ? 'bg-indigo-50 text-indigo-700 border-indigo-100/60' : 'bg-sky-50 text-sky-700 border-sky-100/60';
                                            
                                            // Share of voice percentage
                                            $srcTotal = (int) data_get($src, 'total', 0);
                                            $sovPct = $grandTotal > 0 ? round(($srcTotal / $grandTotal) * 100) : 0;
                                            
                                            // Sentiment percentage
                                            $sPos = (int) data_get($src, 'positive', 0);
                                            $sNeu = (int) data_get($src, 'neutral', 0);
                                            $sNeg = (int) data_get($src, 'negative', 0);
                                            $sTotal = $sPos + $sNeu + $sNeg;
                                            
                                            $posPct = $sTotal > 0 ? round(($sPos / $sTotal) * 100) : 0;
                                            $neuPct = $sTotal > 0 ? round(($sNeu / $sTotal) * 100) : 0;
                                            $negPct = $sTotal > 0 ? round(($sNeg / $sTotal) * 100) : 0;
                                            
                                            // Logo/favicon bg/styling
                                            if (str_contains($srcLower, 'instagram') || $srcLower === 'ig') {
                                                $logoBg = 'bg-gradient-to-br from-purple-600 via-pink-500 to-orange-400';
                                            } elseif (str_contains($srcLower, 'tiktok') || $srcLower === 'tk') {
                                                $logoBg = 'bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800';
                                            } elseif (str_contains($srcLower, 'facebook') || $srcLower === 'fb') {
                                                $logoBg = 'bg-gradient-to-br from-blue-600 to-blue-700';
                                            } else {
                                                $logoBg = 'bg-white';
                                            }

                                            // Extract clean domain for favicon
                                            $faviconDomain = str_replace(' ', '', $srcLower);
                                            if (!str_contains($faviconDomain, '.')) {
                                                $faviconDomain .= '.com';
                                            }
                                        @endphp
                                        <tr data-source-card class="hover:bg-slate-50/60 transition-all duration-200 group">
                                            <!-- Logo & Name -->
                                            <td class="py-3 px-4 rounded-l-2xl border-y border-l border-slate-100/60 group-hover:border-slate-200/80">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-8 h-8 rounded-xl overflow-hidden flex items-center justify-center shadow-[0_2px_8px_rgba(0,0,0,0.04)] flex-shrink-0 {{ $logoBg }} border border-slate-200/50 p-1">
                                                        @if(str_contains($srcLower, 'facebook') || $srcLower === 'fb')
                                                            <svg class="w-4 h-4 fill-current text-white" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"></path></svg>
                                                        @elseif(str_contains($srcLower, 'instagram') || $srcLower === 'ig')
                                                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5" stroke-linecap="round"></line></svg>
                                                        @elseif(str_contains($srcLower, 'tiktok') || $srcLower === 'tk')
                                                            <svg class="w-4 h-4 fill-current text-white" viewBox="0 0 24 24"><path d="M12.525.01c1.306-.022 2.615-.011 3.921-.012.08 1.836 1.011 3.5 2.501 4.485.006 1.341-.004 2.683-.004 4.024-1.57-.107-3.067-.932-3.955-2.247-.008 2.827-.003 5.657-.005 8.486-.098 3.546-3.13 6.643-6.726 6.467-3.526-.067-6.523-3.18-6.241-6.722.215-3.327 3.012-6.104 6.347-5.992v4.06c-1.393-.16-2.775.76-3.085 2.112-.397 1.488.583 3.125 2.1 3.328 1.455.234 2.924-.766 3.14-2.224.048-2.617.02-5.237.03-7.856.002-3.834-.002-7.67.002-11.504z"></path></svg>
                                                        @else
                                                            <div class="relative w-full h-full flex items-center justify-center" x-data="{ imgFailed: false }">
                                                                <img x-show="!imgFailed" 
                                                                     src="{{ 'https://www.google.com/s2/favicons?domain=' . $faviconDomain }}&sz=64" 
                                                                     x-on:error="imgFailed = true"
                                                                     class="w-5 h-5 object-contain animate-fade-in" 
                                                                     alt="{{ $srcName }}" />
                                                                <div x-show="imgFailed" class="absolute inset-0 w-full h-full bg-slate-50 flex items-center justify-center">
                                                                    <span class="material-symbols-outlined text-[15px] text-slate-400">feed</span>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <div class="flex flex-col text-left">
                                                        <span class="text-sm font-bold text-slate-800 leading-snug">{{ $srcName }}</span>
                                                        <span class="text-[10px] text-slate-400 mt-0.5 font-medium">{{ $isSocial ? '@' . $srcLower : $srcLower }}</span>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <!-- Media Type -->
                                            <td class="py-3 px-3 text-center border-y border-slate-100/60 group-hover:border-slate-200/80">
                                                <span class="inline-block px-2.5 py-1 text-[10px] font-semibold rounded-lg border {{ $typeColor }} uppercase tracking-wider">
                                                    {{ $mediaType }}
                                                </span>
                                            </td>
                                            
                                            <!-- Total mentions -->
                                            <td class="py-3 px-3 text-center border-y border-slate-100/60 group-hover:border-slate-200/80">
                                                <span class="font-extrabold text-slate-800 text-sm">{{ number_format((int) data_get($src, 'total', 0), 0, ',', '.') }}</span>
                                                <span class="text-slate-400 text-[10px] block mt-0.5 font-medium">SOV: {{ $sovPct }}%</span>
                                            </td>
                                            
                                            <!-- Sentiment breakdown -->
                                            <td class="py-3 px-3 text-center border-y border-slate-100/60 group-hover:border-slate-200/80">
                                                <div class="flex items-center justify-center gap-1.5 text-[10px] font-bold">
                                                    <span class="text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100/40">+{{ $posPct }}%</span>
                                                    <span class="text-slate-600 bg-slate-50 px-2 py-0.5 rounded border border-slate-150">±{{ $neuPct }}%</span>
                                                    <span class="text-rose-700 bg-rose-50 px-2 py-0.5 rounded border border-rose-100/40">-{{ $negPct }}%</span>
                                                </div>
                                            </td>
                                            
                                            <!-- Stacked bar -->
                                            <td class="py-3 px-4 text-center rounded-r-2xl border-y border-r border-slate-100/60 group-hover:border-slate-200/80">
                                                <div class="flex flex-col items-center justify-center">
                                                    <div class="w-full max-w-[130px] h-2 rounded-full bg-slate-100 overflow-hidden flex shadow-inner">
                                                        <div class="bg-emerald-500 h-full" style="width: {{ $posPct }}%" title="Positif: {{ $posPct }}%"></div>
                                                        <div class="bg-slate-400 h-full" style="width: {{ $neuPct }}%" title="Netral: {{ $neuPct }}%"></div>
                                                        <div class="bg-rose-500 h-full" style="width: {{ $negPct }}%" title="Negatif: {{ $negPct }}%"></div>
                                                    </div>
                                                    <div class="flex justify-between w-full max-w-[130px] text-[8px] text-slate-400 font-bold mt-1.5 px-0.5 leading-none">
                                                        <span>{{ $sPos }} P</span>
                                                        <span>{{ $sNeu }} N</span>
                                                        <span>{{ $sNeg }} M</span>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="py-8 text-center text-slate-450 text-sm font-semibold italic">Belum ada portal berita atau akun media sosial yang melacak proyek ini.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
            @endif

            <!-- Mobile Filter Backdrop -->
            <div 
                x-show="mobileFilterOpen"
                @click="mobileFilterOpen = false"
                x-transition:opacity
                class="lg:hidden fixed inset-0 z-45 bg-slate-900/40 backdrop-blur-sm"
                style="display: none;"
            ></div>

            <!-- Floating Filter Button (Mobile Only) -->
            <button 
                type="button"
                @click="mobileFilterOpen = !mobileFilterOpen"
                class="lg:hidden fixed bottom-6 right-6 z-[60] w-12 h-12 rounded-full bg-[#1fa387] text-white shadow-lg flex items-center justify-center cursor-pointer hover:scale-105 active:scale-95 transition-transform"
            >
                <span class="material-symbols-outlined text-2xl" x-text="mobileFilterOpen ? 'close' : 'tune'">tune</span>
            </button>

            <!-- MOBILE FILTER PANEL DRAWER -->
            <aside 
                x-show="mobileFilterOpen"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="transform translate-x-full"
                x-transition:enter-end="transform translate-x-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="transform translate-x-0"
                x-transition:leave-end="transform translate-x-full"
                class="lg:hidden fixed inset-y-0 right-0 z-50 w-80 bg-white p-6 shadow-2xl border-l border-slate-200 overflow-y-auto space-y-6"
                style="display: none;"
            >
                <!-- Filter Panel Header with Close Button -->
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                    <h4 class="text-sm font-bold text-slate-950 uppercase tracking-wider">Filter Panel</h4>
                    <button 
                        type="button"
                        @click="mobileFilterOpen = false"
                        class="text-slate-400 hover:text-slate-600 p-1 flex items-center justify-center rounded-lg bg-slate-50 border border-slate-200"
                    >
                        <span class="material-symbols-outlined text-xl">close</span>
                    </button>
                </div>
                @include('components.⚡filter-items', ['filterContext' => 'mobile'])
            </aside>

            <button
                type="button"
                x-show="scrolledDown"
                x-transition.opacity
                @click="scrollToTop()"
                class="fixed bottom-6 left-6 md:left-auto md:right-6 z-50 inline-flex items-center gap-2 rounded-full bg-[#1fa387] px-4 py-3 text-xs font-black text-white shadow-[0_10px_30px_rgba(31,163,135,0.28)] hover:bg-[#178a70] hover:shadow-[0_12px_34px_rgba(31,163,135,0.36)] transition"
                style="display: none;"
                aria-label="Kembali ke atas"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"></path>
                </svg>
                Kembali ke atas
            </button>
        </div>
    <!-- Date Range Picker Modal -->
    <div 
        x-data="{ 
            show: @entangle('showDatePicker'),
            localStart: @entangle('startDate'), 
            localEnd: @entangle('endDate'),
            periodMode: 'daily',
            month: new Date().getMonth(),
            year: new Date().getFullYear(),
            monthNames: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'],
            init() {
                if (this.localStart) {
                    let d = new Date(this.localStart);
                    this.month = d.getMonth();
                    this.year = d.getFullYear();
                }
                this.syncPeriodMode();
            },
            syncPeriodMode() {
                if (!this.localStart || !this.localEnd) {
                    this.periodMode = 'custom';
                    return;
                }

                const start = new Date(this.localStart + 'T00:00:00');
                const end = new Date(this.localEnd + 'T23:59:59');
                const today = new Date();
                today.setHours(0,0,0,0);

                const sameDay = start.toDateString() === end.toDateString();
                const monday = new Date(start);
                monday.setDate(start.getDate() - ((start.getDay() + 6) % 7));
                monday.setHours(0,0,0,0);
                const sunday = new Date(monday);
                sunday.setDate(monday.getDate() + 6);
                const monthStart = new Date(start.getFullYear(), start.getMonth(), 1);
                const yearStart = new Date(start.getFullYear(), 0, 1);

                if (sameDay) {
                    this.periodMode = 'daily';
                    return;
                }

                if (start.toDateString() === monday.toDateString() && end.toDateString() === (today < sunday ? today : sunday).toDateString()) {
                    this.periodMode = 'weekly';
                    return;
                }

                if (start.toDateString() === monthStart.toDateString()) {
                    this.periodMode = 'monthly';
                    return;
                }

                if (start.toDateString() === yearStart.toDateString()) {
                    this.periodMode = 'yearly';
                    return;
                }

                this.periodMode = 'custom';
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
                this.periodMode = 'daily';
                this.localStart = selectedStr;
                this.localEnd = selectedStr;
                this.month = selected.getMonth();
                this.year = selected.getFullYear();
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
            setPeriod(mode) {
                const today = new Date();
                today.setHours(0,0,0,0);
                let start = new Date(today);
                let end = new Date(today);

                if (mode === 'daily') {
                    this.periodMode = 'daily';
                } else if (mode === 'weekly') {
                    this.periodMode = 'weekly';
                    const offset = (today.getDay() + 6) % 7;
                    start = new Date(today);
                    start.setDate(today.getDate() - offset);
                } else if (mode === 'monthly') {
                    this.periodMode = 'monthly';
                    start = new Date(today.getFullYear(), today.getMonth(), 1);
                } else if (mode === 'yearly') {
                    this.periodMode = 'yearly';
                    start = new Date(today.getFullYear(), 0, 1);
                } else {
                    this.periodMode = 'custom';
                    return;
                }

                this.localStart = this.formatDate(start);
                this.localEnd = this.formatDate(end);
                this.month = start.getMonth();
                this.year = start.getFullYear();
            },
            clearPeriod() {
                this.periodMode = 'custom';
                this.localStart = null;
                this.localEnd = null;
            },
            prevMonth() {
                if (this.month === 0) {
                    this.month = 11;
                    this.year--;
                } else {
                    this.month--;
                }
            },
            nextMonth() {
                const next = new Date(this.year, this.month + 1, 1);
                const today = new Date();
                today.setHours(0,0,0,0);
                if (next > today) return;
                if (this.month === 11) {
                    this.month = 0;
                    this.year++;
                } else {
                    this.month++;
                }
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
            class="datepicker-modal-container bg-white w-full max-w-[700px] rounded-3xl overflow-hidden shadow-2xl border border-slate-200"
        >
            <!-- Left Panel (PERIODE Presets) -->
            <div class="datepicker-left-panel bg-[#FAFBFD] p-6 text-left space-y-4 flex-shrink-0">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-2">MODE PERIODE</span>
                <div class="grid grid-cols-2 gap-2.5">
                    <button type="button" @click="setPeriod('daily')" :class="periodMode === 'daily' ? 'border-[#1fa387] bg-[#1fa387]/5 text-[#1fa387]' : 'border-slate-200 bg-white text-slate-600'" class="rounded-2xl border px-3 py-3 text-left transition">
                        <div class="text-[10px] font-black uppercase tracking-widest">Harian</div>
                        <div class="text-[11px] mt-1 font-semibold">Pilih 1 tanggal</div>
                    </button>
                    <button type="button" @click="setPeriod('weekly')" :class="periodMode === 'weekly' ? 'border-[#1fa387] bg-[#1fa387]/5 text-[#1fa387]' : 'border-slate-200 bg-white text-slate-600'" class="rounded-2xl border px-3 py-3 text-left transition">
                        <div class="text-[10px] font-black uppercase tracking-widest">Mingguan</div>
                        <div class="text-[11px] mt-1 font-semibold">Senin sampai hari ini</div>
                    </button>
                    <button type="button" @click="setPeriod('monthly')" :class="periodMode === 'monthly' ? 'border-[#1fa387] bg-[#1fa387]/5 text-[#1fa387]' : 'border-slate-200 bg-white text-slate-600'" class="rounded-2xl border px-3 py-3 text-left transition">
                        <div class="text-[10px] font-black uppercase tracking-widest">Bulanan</div>
                        <div class="text-[11px] mt-1 font-semibold">Awal bulan sampai hari ini</div>
                    </button>
                    <button type="button" @click="setPeriod('yearly')" :class="periodMode === 'yearly' ? 'border-[#1fa387] bg-[#1fa387]/5 text-[#1fa387]' : 'border-slate-200 bg-white text-slate-600'" class="rounded-2xl border px-3 py-3 text-left transition">
                        <div class="text-[10px] font-black uppercase tracking-widest">Tahunan</div>
                        <div class="text-[11px] mt-1 font-semibold">Awal tahun sampai hari ini</div>
                    </button>
                </div>
                
                <button 
                    type="button" 
                    @click="clearPeriod()" 
                    class="w-full text-xs text-slate-500 hover:text-[#1fa387] hover:font-bold text-left font-semibold pt-2 border-t border-slate-200 mt-1"
                >
                    Semua Waktu
                </button>
            </div>

            <!-- Right Panel (Calendar Grid) -->
            <div class="flex-1 min-h-0 p-6 flex flex-col justify-between">
                <div class="min-h-0">
                    <!-- Calendar Header -->
                    <div class="flex justify-between items-center mb-6">
                        <h4 class="text-sm font-bold text-slate-800">Tanggal khusus</h4>
                        <span class="px-3 py-1 bg-[#FAFBFD] text-xs font-semibold text-slate-650 rounded-full border border-slate-200">
                            <span x-text="periodMode === 'daily' ? 'Harian' : (periodMode === 'weekly' ? 'Mingguan' : (periodMode === 'monthly' ? 'Bulanan' : (periodMode === 'yearly' ? 'Tahunan' : 'Khusus')))"></span>
                            <span class="ml-2 font-bold text-slate-500" x-text="formatDisplayDate(localStart)"></span>
                            <span x-show="localEnd && localEnd !== localStart" x-text="' - ' + formatDisplayDate(localEnd)"></span>
                        </span>
                    </div>

                    <!-- Calendar Body (Juni 2026) -->
                    <div class="space-y-4">
                        <div class="flex justify-between items-center px-2">
                            <div class="flex flex-col">
                                <span class="text-xs font-bold text-slate-700" x-text="monthNames[month] + ' ' + year"></span>
                                <span class="text-[10px] text-slate-400 font-semibold mt-0.5">
                                    Mode: <span class="text-[#1fa387]" x-text="periodMode === 'daily' ? 'Harian' : (periodMode === 'weekly' ? 'Mingguan' : (periodMode === 'monthly' ? 'Bulanan' : (periodMode === 'yearly' ? 'Tahunan' : 'Khusus')))"></span>
                                </span>
                            </div>
                            <div class="flex gap-2 text-slate-400">
                                <span @click="prevMonth()" class="material-symbols-outlined text-sm cursor-pointer hover:text-slate-750">chevron_left</span>
                                <span @click="nextMonth()" class="material-symbols-outlined text-sm cursor-pointer hover:text-slate-750">chevron_right</span>
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
                <div class="flex justify-end items-center gap-3 pt-6 border-t border-slate-100 flex-shrink-0">
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
                        <svg class="w-5 h-5 text-{{ $this->viralMeta['viral_color'] }}-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                        Penyebab Kondisi: <span class="text-{{ $this->viralMeta['viral_color'] }}-600 font-extrabold">{{ $this->viralMeta['viral_status'] }}</span>
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
                            @click="showViralModal = false; openedFromViral = true; window.openDashboardDetail(
                                {{ Js::from($article->title) }},
                                {{ Js::from($article->source_name) }},
                                {{ Js::from($article->published_at ? \Carbon\Carbon::parse($article->published_at)->format('d M Y, H:i') . ' (' . \Carbon\Carbon::parse($article->published_at)->diffForHumans() . ')' : 'Baru saja') }},
                                {{ Js::from($article->url) }},
                                {{ Js::from($article->content) }},
                                {{ Js::from($analysis ? $analysis->ai_summary : '') }},
                                {{ Js::from($analysis ? $analysis->ai_recommendation : '') }},
                                {{ Js::from($article->sentiment) }},
                                {{ Js::from($article->category) }},
                                {{ Js::from($projectReachDisplay['reachValue'] ?? '0') }},
                                {{ Js::from($projectReachDisplay['levelLabel'] ?? '-') }},
                                {{ Js::from($projectReachDisplay['scoreValue'] ?? '0') }},
                                {{ Js::from($article->published_at ? \Carbon\Carbon::parse($article->published_at)->format('d/m/y') : 'Baru saja') }}
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
        @keydown.escape.window="window.closeDashboardDetail()"
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
            @click.away="window.closeDashboardDetail()"
            style="position: relative;"
        >
            <!-- Close Button (Positioned absolute with robust inline style) -->
            <button 
                @click="window.closeDashboardDetail()" 
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
                        <div x-data="{ iconFailed: false }" class="w-full h-full">
                            <img 
                                :src="'https://www.google.com/s2/favicons?sz=64&domain=' + (
                                    detailSource.toLowerCase().includes('facebook') || detailSource.toLowerCase() === 'fb' ? 'facebook.com' :
                                    (detailSource.toLowerCase().includes('instagram') || detailSource.toLowerCase() === 'ig' ? 'instagram.com' :
                                    (detailSource.toLowerCase().includes('tiktok') || detailSource.toLowerCase() === 'tk' ? 'tiktok.com' :
                                    (detailSource.toLowerCase().includes('twitter') || detailSource.toLowerCase() === 'x.com' ? 'x.com' :
                                    (detailSource.toLowerCase().includes('portal berau') || detailSource.toLowerCase().includes('portalberau') ? 'portalberau.online' :
                                    (detailSource.includes('.') ? detailSource : detailSource + '.com')))))
                                )"
                                x-on:error="iconFailed = true"
                                x-show="!iconFailed"
                                class="w-5 h-5 object-contain"
                                alt="Logo"
                            />
                            <div x-show="iconFailed" class="w-full h-full flex items-center justify-center" style="display: none;">
                                <svg class="w-5 h-5 text-[#1fa387]" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path></svg>
                            </div>
                        </div>
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
