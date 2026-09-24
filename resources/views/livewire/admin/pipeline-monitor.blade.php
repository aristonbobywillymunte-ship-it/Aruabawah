<div class="space-y-5"
     x-data="{
         confirmOpen: false,
         confirmTitle: '',
         confirmMessage: '',
         confirmType: 'info',
         confirmAction: null,
         triggerConfirm(title, message, type, actionCallback) {
             this.confirmTitle = title;
             this.confirmMessage = message;
             this.confirmType = type;
             this.confirmAction = actionCallback;
             this.confirmOpen = true;
         },
         executeConfirm() {
             this.confirmOpen = false;
             if (this.confirmAction) {
                 this.confirmAction();
             }
         }
     }"
     x-on:scroll-top.window="window.scrollTo({ top: 0, behavior: 'smooth' })"
>
    {{-- ══════════════════════════════════════════════ --}}
    {{-- SUMMARY PILLS                                  --}}
    {{-- ══════════════════════════════════════════════ --}}
    <div class="flex justify-end gap-2">
        <div class="flex items-center gap-1.5 rounded-xl bg-emerald-50 border border-emerald-200/60 px-3 py-1.5 text-[11px] font-bold text-emerald-700">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
            {{ number_format($aiSuccess) }} AI Sukses
        </div>
        @if ($failedJobs > 0)
        <div class="flex items-center gap-1.5 rounded-xl bg-red-50 border border-red-200/60 px-3 py-1.5 text-[11px] font-bold text-red-700">
            <span class="h-1.5 w-1.5 rounded-full bg-red-500 animate-pulse"></span>
            {{ number_format($failedJobs) }} Queue Gagal
        </div>
            
@endif
        @if ($aiPending > 0)
        <div class="flex items-center gap-1.5 rounded-xl bg-amber-50 border border-amber-200/60 px-3 py-1.5 text-[11px] font-bold text-amber-700">
            <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
            {{ number_format($aiPending) }} Menunggu
        </div>
            
@endif
    </div>

    {{-- ══════════════════════════════════════════════ --}}
    {{-- TAB NAVIGATION                                 --}}
    {{-- ══════════════════════════════════════════════ --}}
    <div class="overflow-x-auto -mx-1 px-1 pb-0.5">
        <div class="flex gap-1.5 min-w-max rounded-2xl bg-slate-100/70 p-1.5 border border-slate-200/40">
            @foreach ([
                ['key' => 'scraping',      'label' => 'Artikel Proyek', 'icon' => 'feed',       'count' => $portalItems,  'color' => 'teal'],
                ['key' => 'social',        'label' => 'Sosial Media',   'icon' => 'public',     'count' => $globalSocial, 'color' => 'indigo'],
                ['key' => 'ai',            'label' => 'Analisis AI',    'icon' => 'psychology', 'count' => $aiTotal,      'color' => 'purple'],
                ['key' => 'notifications', 'label' => 'Notifikasi',     'icon' => 'send',       'count' => $notifTotal,   'color' => 'sky'],
                ['key' => 'queue-pending', 'label' => 'Antrian AI',     'icon' => 'pending',    'count' => $aiPending,    'color' => 'amber'],
                ['key' => 'queue-failed',  'label' => 'Queue Gagal',    'icon' => 'report',     'count' => $failedJobs,   'color' => 'red'],
            ] as $tab)
            @php
                $isActive = $activeTab === $tab['key'];
                $badgeColors = [
                    'teal'   => $isActive ? 'bg-[#1fa387]/15 text-[#1fa387]' : 'bg-slate-200 text-slate-500',
                    'indigo' => $isActive ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-200 text-slate-500',
                    'purple' => $isActive ? 'bg-purple-100 text-purple-700' : 'bg-slate-200 text-slate-500',
                    'sky'    => $isActive ? 'bg-sky-100 text-sky-700' : 'bg-slate-200 text-slate-500',
                    'amber'  => $isActive ? 'bg-amber-100 text-amber-700' : 'bg-slate-200 text-slate-500',
                    'red'    => $isActive ? 'bg-red-100 text-red-700' : 'bg-slate-200 text-slate-500',
                ];
                $iconColors = [
                    'teal'   => $isActive ? 'text-[#1fa387]' : 'text-slate-400',
                    'indigo' => $isActive ? 'text-indigo-600' : 'text-slate-400',
                    'purple' => $isActive ? 'text-purple-600' : 'text-slate-400',
                    'sky'    => $isActive ? 'text-sky-600' : 'text-slate-400',
                    'amber'  => $isActive ? 'text-amber-600' : 'text-slate-400',
                    'red'    => $isActive ? 'text-red-600' : 'text-slate-400',
                ];
            @endphp
            <button
                wire:click="setTab('{{ $tab['key'] }}')"
                id="tab-{{ $tab['key'] }}"
                class="flex shrink-0 items-center gap-2 rounded-xl px-3.5 py-2.5 text-xs font-semibold transition-all duration-200
                    {{ $isActive ? 'bg-white text-slate-800 shadow-sm border border-slate-200/50' : 'text-slate-500 hover:text-slate-700 hover:bg-white/60' }}"
            >
                <span class="material-symbols-outlined text-[17px] {{ $iconColors[$tab['color']] }} transition-colors">{{ $tab['icon'] }}</span>
                <span class="leading-none">{{ $tab['label'] }}</span>
                @if ($tab['count'] > 0)
                <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $badgeColors[$tab['color']] }} tabular-nums min-w-[20px] text-center">
                    {{ number_format($tab['count']) }}
                </span>
                @endif
            </button>
            @endforeach
        </div>
    </div>

    {{-- ══════════════════════════════════════════════ --}}
    {{-- FILTER BAR                                     --}}
    {{-- ══════════════════════════════════════════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white border border-slate-200/60 rounded-2xl p-3 shadow-sm">
        <div class="flex flex-wrap items-center gap-2 flex-1">

            {{-- Search Input --}}
            <div class="relative w-full sm:w-72">
                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 flex items-center justify-center w-4 h-4">
                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="7"/>
                        <path stroke-linecap="round" d="M21 21l-4.35-4.35"/>
                    </svg>
                </span>
                <input
                    wire:model.live.debounce.400ms="search"
                    type="text"
                    placeholder="{{ match($activeTab) {
                        'scraping'      => 'Cari judul atau sumber...',
                        'social'        => 'Cari konten atau author...',
                        'ai'            => 'Cari hasil analisis...',
                        'notifications' => 'Cari notifikasi...',
                        'queue-pending' => 'Cari antrian...',
                        'queue-failed'  => 'Cari job gagal...',
                        default         => 'Cari...'
                    } }}"
                    class="block w-full h-[38px] rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-10 text-xs font-medium text-slate-800 placeholder-slate-400 outline-none transition-all focus:border-[#1fa387] focus:bg-white focus:ring-2 focus:ring-[#1fa387]/15"
                />
                <div wire:loading wire:target="search" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">
                    <svg class="animate-spin h-3.5 w-3.5 text-[#1fa387]" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                @if ($search)
                <button wire:click="$set('search', '')" class="pointer-events-auto absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition" wire:loading.remove wire:target="search">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
                @endif
            </div>

            <div class="hidden sm:block h-6 w-px bg-slate-200"></div>

            {{-- Filter Proyek --}}
            @if (!in_array($activeTab, ['queue-pending','queue-failed']))
            <div class="relative">
                <select wire:model.live="filterProject" class="h-[38px] appearance-none rounded-xl border border-slate-200 bg-slate-50 pl-3 pr-8 text-xs font-semibold text-slate-700 outline-none focus:border-[#1fa387] focus:bg-white focus:ring-2 focus:ring-[#1fa387]/15 transition-all cursor-pointer">
                    <option value="">Semua Proyek</option>
                    @foreach ($projects as $proj)
                    <option value="{{ $proj->id }}">{{ $proj->name }}</option>
                    @endforeach
                </select>
                <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center">
                    <svg class="w-3.5 h-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                </span>
            </div>
                
@endif

            {{-- Tab: Scraping Filters --}}
            @if ($activeTab === 'scraping')
            <div class="relative">
                <select wire:model.live="filterPlatform" class="h-[38px] appearance-none rounded-xl border border-slate-200 bg-slate-50 pl-3 pr-8 text-xs font-semibold text-slate-700 outline-none focus:border-[#1fa387] focus:bg-white focus:ring-2 focus:ring-[#1fa387]/15 transition-all cursor-pointer">
                    <option value="">Semua Sumber</option>
                    @foreach ($sources as $src)
                    <option value="{{ $src }}">{{ $src }}</option>
                    @endforeach
                </select>
                <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center">
                    <svg class="w-3.5 h-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                </span>
            </div>
            <div class="relative">
                <select wire:model.live="filterAiState" class="h-[38px] appearance-none rounded-xl border border-slate-200 bg-slate-50 pl-3 pr-8 text-xs font-semibold text-slate-700 outline-none focus:border-[#1fa387] focus:bg-white focus:ring-2 focus:ring-[#1fa387]/15 transition-all cursor-pointer">
                    <option value="">Semua Status AI</option>
                    <option value="success">Selesai</option>
                    <option value="failed">Gagal</option>
                    <option value="pending">Menunggu</option>
                </select>
                <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center">
                    <svg class="w-3.5 h-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                </span>
            </div>
                
@endif

            {{-- Tab: Social Filters --}}
            @if ($activeTab === 'social')
            <div class="relative">
                <select wire:model.live="filterPlatform" class="h-[38px] appearance-none rounded-xl border border-slate-200 bg-slate-50 pl-3 pr-8 text-xs font-semibold text-slate-700 outline-none focus:border-[#1fa387] focus:bg-white focus:ring-2 focus:ring-[#1fa387]/15 transition-all cursor-pointer">
                    <option value="">Semua Platform</option>
                    @foreach ($platforms as $p)
                    <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>
                <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center">
                    <svg class="w-3.5 h-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                </span>
            </div>
                
@endif

            {{-- Tab: AI Filters --}}
            @if ($activeTab === 'ai')
            <div class="relative">
                <select wire:model.live="filterStatus" class="h-[38px] appearance-none rounded-xl border border-slate-200 bg-slate-50 pl-3 pr-8 text-xs font-semibold text-slate-700 outline-none focus:border-[#1fa387] focus:bg-white focus:ring-2 focus:ring-[#1fa387]/15 transition-all cursor-pointer">
                    <option value="">Semua Status</option>
                    <option value="success">Sukses</option>
                    <option value="failed">Gagal</option>
                </select>
                <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center">
                    <svg class="w-3.5 h-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                </span>
            </div>
            <div class="relative">
                <select wire:model.live="filterRisk" class="h-[38px] appearance-none rounded-xl border border-slate-200 bg-slate-50 pl-3 pr-8 text-xs font-semibold text-slate-700 outline-none focus:border-[#1fa387] focus:bg-white focus:ring-2 focus:ring-[#1fa387]/15 transition-all cursor-pointer">
                    <option value="">Semua Risiko</option>
                    <option value="high">Tinggi</option>
                    <option value="medium">Sedang</option>
                    <option value="low">Rendah</option>
                </select>
                <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center">
                    <svg class="w-3.5 h-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                </span>
            </div>
                
@endif

            {{-- Tab: Notifications Filters --}}
            @if ($activeTab === 'notifications')
            <div class="relative">
                <select wire:model.live="filterStatus" class="h-[38px] appearance-none rounded-xl border border-slate-200 bg-slate-50 pl-3 pr-8 text-xs font-semibold text-slate-700 outline-none focus:border-[#1fa387] focus:bg-white focus:ring-2 focus:ring-[#1fa387]/15 transition-all cursor-pointer">
                    <option value="">Semua Status</option>
                    <option value="sent">Terkirim</option>
                    <option value="failed">Gagal</option>
                    <option value="skipped">Dilewati</option>
                </select>
                <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center">
                    <svg class="w-3.5 h-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                </span>
            </div>
                
@endif
        </div>

        {{-- Bulk Action Buttons --}}
        <div class="flex items-center gap-2 shrink-0">
            @if ($activeTab === 'queue-failed' && $failedJobs > 0)
            <button
                type="button"
                @click="triggerConfirm(
                    'Retry Semua AI yang Gagal',
                    'Yakin ingin melakukan retry massal untuk semua {{ $failedJobs }} analisis AI yang gagal?',
                    'info',
                    () => $wire.retryAllFailedAiStates()
                )"
                class="flex items-center gap-1.5 h-[38px] rounded-xl bg-[#1fa387] hover:bg-[#17856d] px-4 text-xs font-bold text-white transition-all shadow-md shadow-[#1fa387]/20"
            >
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                Retry Semua
            </button>
            @endif

            @if ($activeTab === 'queue-pending' && $aiPending > 0)
            <button
                type="button"
                @click="triggerConfirm(
                    'Bersihkan Semua Antrean',
                    'Yakin ingin menghapus seluruh antrean AI yang sedang menunggu? Proses AI untuk semua item akan dihentikan.',
                    'danger',
                    () => $wire.clearAllPendingAiStates()
                )"
                class="flex items-center gap-1.5 h-[38px] rounded-xl bg-red-50 hover:bg-red-100 px-4 text-xs font-bold text-red-700 transition-all border border-red-200/70"
            >
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                Bersihkan
            </button>
            @endif
        </div>
    </div>

    {{-- ══════════════════════════════════════════════ --}}
    {{-- DATA TABLE                                     --}}
    {{-- ══════════════════════════════════════════════ --}}
    <div class="relative bg-white border border-slate-200/60 rounded-2xl shadow-sm overflow-hidden min-h-[300px]">

        {{-- Loading State --}}
        <div wire:loading wire:target="setTab, retryAiState, retryAllFailedAiStates, deleteAiState, retryNotification, deleteNotification, clearAllPendingAiStates, filterProject, filterPlatform, filterAiState, filterStatus, filterRisk, search"
             class="absolute inset-0 z-20 flex flex-col items-center justify-center bg-white py-20 text-center">
            <div class="flex flex-col items-center gap-3">
                <svg class="animate-spin h-9 w-9 text-[#1fa387]" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-80" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-[11px] font-bold text-slate-500 tracking-wide">Memuat data...</span>
            </div>
        </div>

        <div wire:loading.class="opacity-0" wire:target="setTab, retryAiState, retryAllFailedAiStates, deleteAiState, retryNotification, deleteNotification, clearAllPendingAiStates, filterProject, filterPlatform, filterAiState, filterStatus, filterRisk, search" class="overflow-x-auto">

            {{-- ═══════════════════════════ --}}
            {{-- TAB: ARTIKEL PROYEK        --}}
            {{-- ═══════════════════════════ --}}
            @if ($activeTab === 'scraping')
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b-2 border-slate-100 bg-gradient-to-r from-slate-50 to-slate-50/60">
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-10">#</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-36">Proyek</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Sumber / Judul</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-20 text-center">Sentimen</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-28 text-center">Status AI</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-40">Waktu Terbit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/70">
                    @forelse ($items as $item)
                    @php
                        $ai = $item->aiAnalysisResult;
                        $sentScore = $ai->sentiment_score ?? 0;
                        $sentPositive = $sentScore >= 0.3;
                        $sentNegative = $sentScore <= -0.4;
                    @endphp
                    <tr class="group transition-all duration-150 hover:bg-[#1fa387]/[0.025]">
                        <td class="px-4 py-3.5 text-xs font-semibold text-slate-300 tabular-nums">{{ $items->firstItem() + $loop->index }}</td>
                        <td class="px-4 py-3.5">
                            <div class="flex flex-col gap-1">
                                @foreach($item->projects as $proj)
                                <span class="inline-flex items-center gap-1 self-start rounded-lg {{ $proj->trashed() ? 'bg-slate-100 text-slate-500 border border-slate-200' : 'bg-[#1fa387]/8 text-[#1fa387] border border-[#1fa387]/15' }} px-2 py-0.5 text-[10px] font-semibold">
                                    <svg class="w-2.5 h-2.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
                                    {{ Str::limit($proj->name, 16) }}
                                    @if($proj->trashed())
                                    <span class="text-[9px] text-slate-400 font-normal">(deleted)</span>
                                    @endif
                                </span>
                                @endforeach
                                @if($item->projects->isEmpty())
                                <span class="text-xs text-slate-300">—</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3.5 max-w-md">
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ $item->source_name }}</div>
                            <p class="mt-0.5 text-xs font-semibold text-slate-800 leading-snug line-clamp-2">{{ $item->title }}</p>
                            @if ($item->url)
                            <div class="mt-1 flex items-center gap-2">
                                <a href="{{ $item->url }}" target="_blank" class="inline-flex items-center gap-1 text-[10px] text-[#1fa387] font-bold hover:underline transition">
                                    <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                    Buka Artikel
                                </a>
                                <span class="text-slate-200">·</span>
                                <button type="button" wire:click="viewArticle('article', {{ $item->id }})" wire:loading.attr="disabled" class="inline-flex items-center gap-1 text-[10px] text-slate-500 font-bold hover:text-slate-800 transition cursor-pointer disabled:opacity-50">
                                    <span wire:loading.remove wire:target="viewArticle('article', {{ $item->id }})" class="inline-flex items-center gap-1">
                                        <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                        Lihat Konten
                                    </span>
                                    <span wire:loading wire:target="viewArticle('article', {{ $item->id }})" class="inline-flex items-center gap-1 text-[#1fa387]">
                                        <svg class="w-2.5 h-2.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        Memuat...
                                    </span>
                                </button>
                            </div>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-center">
                            @if ($sentPositive)
                            <svg class="w-5 h-5 text-emerald-500 mx-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                            @elseif ($sentNegative)
                            <svg class="w-5 h-5 text-red-500 mx-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M16 16s-1.5-2-4-2-4 2-4 2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                            @else
                            <svg class="w-5 h-5 text-slate-400 mx-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="8" y1="15" x2="16" y2="15"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                            @endif
                            <div class="text-[10px] font-bold text-slate-400 mt-0.5 tabular-nums">{{ number_format($sentScore, 2) }}</div>
                        </td>
                        <td class="px-4 py-3.5 text-center">
                            @if ($ai)
                                @php $riskColors = ['high'=>'bg-red-50 text-red-700 border-red-200/60','medium'=>'bg-orange-50 text-orange-700 border-orange-200/60','low'=>'bg-emerald-50 text-emerald-700 border-emerald-200/60','critical'=>'bg-red-100 text-red-800 border-red-300/60'] @endphp
                                <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-bold {{ $riskColors[$ai->risk_level] ?? 'bg-slate-50 text-slate-500 border-slate-200/50' }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $ai->risk_level === 'high' || $ai->risk_level === 'critical' ? 'bg-red-500' : ($ai->risk_level === 'medium' ? 'bg-orange-400' : 'bg-emerald-500') }}"></span>
                                    {{ ucfirst($ai->risk_level) }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 border border-amber-200/60 px-2 py-0.5 text-[10px] font-bold text-amber-600">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-400 animate-pulse"></span>Belum
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="text-[11px] font-semibold text-slate-700">{{ $item->published_at ? \Carbon\Carbon::parse($item->published_at)->format('d M Y H:i') : '—' }}</div>
                            <div class="text-[10px] text-slate-400 mt-0.5">Scraping: {{ $item->created_at ? \Carbon\Carbon::parse($item->created_at)->setTimezone(config('app.timezone', 'Asia/Makassar'))->format('d M H:i') : '—' }}</div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-6 py-20 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 mb-4">
                                <svg class="w-7 h-7 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h7"/></svg>
                            </div>
                            <h3 class="text-sm font-bold text-slate-700">Belum Ada Artikel</h3>
                            <p class="text-xs text-slate-400 mt-1 max-w-xs leading-normal">Belum ada artikel yang masuk dalam antrean proyek saat ini.</p>
                        </div>
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
            @endif

            {{-- ═══════════════════════════ --}}
            {{-- TAB: SOSIAL MEDIA          --}}
            {{-- ═══════════════════════════ --}}
            @if ($activeTab === 'social')
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b-2 border-slate-100 bg-gradient-to-r from-slate-50 to-slate-50/60">
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-10">#</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-36">Proyek</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-28">Platform</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-32">Author</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Konten</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-28 text-center">Engagement</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-36">Waktu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/70">
                    @forelse ($items as $item)
                    @php
                        $platformColors = [
                            'Facebook'  => 'bg-blue-50 text-blue-700 border-blue-200/50',
                            'Instagram' => 'bg-pink-50 text-pink-700 border-pink-200/50',
                            'TikTok'    => 'bg-slate-800 text-white border-slate-700',
                            'Twitter'   => 'bg-sky-50 text-sky-700 border-sky-200/50',
                            'X'         => 'bg-slate-900 text-white border-slate-800',
                            'Threads'   => 'bg-slate-100 text-slate-800 border-slate-200/50',
                            'YouTube'   => 'bg-red-50 text-red-700 border-red-200/50',
                        ];
                        $pColor = $platformColors[$item->platform] ?? 'bg-indigo-50 text-indigo-700 border-indigo-200/50';
                    @endphp
                    <tr class="group transition-all duration-150 hover:bg-[#1fa387]/[0.025]">
                        <td class="px-4 py-3.5 text-xs font-semibold text-slate-300 tabular-nums">{{ $items->firstItem() + $loop->index }}</td>
                        <td class="px-4 py-3.5">
                            <div class="flex flex-col gap-1">
                                @foreach($item->projects as $proj)
                                <span class="inline-flex items-center gap-1 self-start rounded-lg {{ $proj->trashed() ? 'bg-slate-100 text-slate-500 border border-slate-200' : 'bg-indigo-50 text-indigo-700 border border-indigo-200/50' }} px-2 py-0.5 text-[10px] font-semibold">
                                    <svg class="w-2.5 h-2.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
                                    {{ Str::limit($proj->name, 14) }}
                                    @if($proj->trashed())
                                    <span class="text-[9px] text-slate-400 font-normal">(deleted)</span>
                                    @endif
                                </span>
                                @endforeach
                                @if($item->projects->isEmpty())
                                <span class="text-xs text-slate-300">—</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="inline-flex items-center rounded-lg border px-2.5 py-1 text-[10px] font-bold {{ $pColor }}">
                                {{ $item->platform ?? '—' }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="text-xs font-bold text-slate-800 line-clamp-1">{{ $item->author_name ?? '—' }}</div>
                        </td>
                        <td class="px-4 py-3.5 max-w-sm">
                            <p class="text-xs text-slate-600 leading-relaxed line-clamp-2">{{ $item->content ?? '—' }}</p>
                            @if ($item->post_url)
                            <a href="{{ $item->post_url }}" target="_blank" class="mt-1 inline-flex items-center gap-1 text-[10px] text-[#1fa387] font-bold hover:underline transition">
                                <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                Buka Post
                            </a>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-center">
                            <div class="flex flex-col items-center gap-1">
                                @if ($item->like_count > 0)
                                <span class="flex items-center gap-1 text-[10px] font-bold text-rose-600">
                                    <svg class="w-3 h-3 fill-current" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
                                    {{ number_format($item->like_count) }}
                                </span>
                                @endif
                                @if ($item->view_count > 0)
                                <span class="flex items-center gap-1 text-[10px] font-bold text-slate-500">
                                    <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    {{ number_format($item->view_count) }}
                                </span>
                                @endif
                                @if ($item->like_count == 0 && $item->view_count == 0)
                                <span class="text-xs text-slate-300">—</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="text-[11px] font-semibold text-slate-700">{{ $item->posted_at ? \Carbon\Carbon::parse($item->posted_at)->setTimezone(config('app.timezone', 'Asia/Makassar'))->format('d M Y H:i') : '—' }}</div>
                            <div class="text-[10px] text-slate-400 mt-0.5">Scraping: {{ $item->created_at ? \Carbon\Carbon::parse($item->created_at)->setTimezone(config('app.timezone', 'Asia/Makassar'))->format('d M H:i') : '—' }}</div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-6 py-20 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 mb-4">
                                <svg class="w-7 h-7 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>
                            </div>
                            <h3 class="text-sm font-bold text-slate-700">Belum Ada Konten Sosial</h3>
                            <p class="text-xs text-slate-400 mt-1 max-w-xs leading-normal">Jalankan Apify Actor untuk memulai sinkronisasi media sosial.</p>
                        </div>
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
            @endif

            {{-- ═══════════════════════════ --}}
            {{-- TAB: ANALISIS AI           --}}
            {{-- ═══════════════════════════ --}}
            @if ($activeTab === 'ai')
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b-2 border-slate-100 bg-gradient-to-r from-slate-50 to-slate-50/60">
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-10">#</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-36">Proyek</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Sumber / Ringkasan</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-24 text-center">Risiko</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-20 text-center">Sentimen</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-24 text-center">Status</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-36">Waktu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/70">
                    @forelse ($items as $item)
                    @php
                        $riskMap = ['high'=>['bg-red-50 text-red-700 border-red-200/60','bg-red-500'],'medium'=>['bg-orange-50 text-orange-700 border-orange-200/60','bg-orange-400'],'low'=>['bg-emerald-50 text-emerald-700 border-emerald-200/60','bg-emerald-500'],'critical'=>['bg-red-100 text-red-800 border-red-300/60','bg-red-600']];
                        [$riskColor, $riskDot] = $riskMap[$item->risk_level] ?? ['bg-slate-50 text-slate-500 border-slate-200/50','bg-slate-400'];
                        $sentScore = $item->sentiment_score ?? 0;
                        $sentPositive = $sentScore >= 0.3;
                        $sentNegative = $sentScore <= -0.4;
                        $projList = collect();
                        if ($item->article && $item->article->projects) { $projList = $item->article->projects; }
                        elseif ($item->socialMediaItem && $item->socialMediaItem->projects) { $projList = $item->socialMediaItem->projects; }
                        $sourceUrl = $item->article ? ($item->article->url ?? null) : ($item->socialMediaItem ? ($item->socialMediaItem->post_url ?? null) : null);
                    @endphp
                    <tr class="group transition-all duration-150 hover:bg-[#1fa387]/[0.025]">
                        <td class="px-4 py-3.5 text-xs font-semibold text-slate-300 tabular-nums">{{ $items->firstItem() + $loop->index }}</td>
                        <td class="px-4 py-3.5">
                            <div class="flex flex-col gap-1">
                                @foreach($projList as $proj)
                                <span class="inline-flex items-center gap-1 self-start rounded-lg {{ $proj->trashed() ? 'bg-slate-100 text-slate-500 border border-slate-200' : 'bg-[#1fa387]/8 text-[#1fa387] border border-[#1fa387]/15' }} px-2 py-0.5 text-[10px] font-semibold">
                                    <svg class="w-2.5 h-2.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
                                    {{ Str::limit($proj->name, 14) }}
                                    @if($proj->trashed())
                                    <span class="text-[9px] text-slate-400 font-normal">(deleted)</span>
                                    @endif
                                </span>
                                @endforeach
                                @if($projList->isEmpty())<span class="text-xs text-slate-300">—</span>@endif
                            </div>
                        </td>
                        <td class="px-4 py-3.5 max-w-md">
                            @if ($item->article)
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ $item->article->source_name }}</div>
                            <p class="mt-0.5 text-xs font-semibold text-slate-800 line-clamp-1">
                                @if($sourceUrl)<a href="{{ $sourceUrl }}" target="_blank" class="hover:text-[#1fa387] transition">{{ $item->article->title }}</a>
                                @else{{ $item->article->title }}@endif
                            </p>
                            @elseif ($item->socialMediaItem)
                            <div class="text-[10px] font-bold text-indigo-500 uppercase tracking-wider">{{ $item->socialMediaItem->platform }}</div>
                            <p class="mt-0.5 text-xs font-semibold text-slate-800 line-clamp-1">
                                @if($sourceUrl)<a href="{{ $sourceUrl }}" target="_blank" class="hover:text-[#1fa387] transition">{{ $item->socialMediaItem->author_name }}</a>
                                @else{{ $item->socialMediaItem->author_name }}@endif
                            </p>
                            @endif
                            @if ($item->summary)
                            <p class="mt-1.5 text-[11px] text-slate-500 leading-relaxed line-clamp-2 bg-slate-50/80 px-2.5 py-1.5 rounded-lg border border-slate-100/80">{{ $item->summary }}</p>
                            @endif
                            <div class="mt-1.5 flex items-center gap-2">
                                @if($sourceUrl)
                                <a href="{{ $sourceUrl }}" target="_blank" class="inline-flex items-center gap-1 text-[10px] text-[#1fa387] font-bold hover:underline transition">
                                    <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                    Buka Sumber
                                </a>
                                <span class="text-slate-200">·</span>
                                @endif
                                <button type="button"
                                        wire:click="viewArticle('{{ $item->article_id ? 'article' : 'social' }}', {{ $item->article_id ?: $item->social_media_item_id }})"
                                        wire:loading.attr="disabled"
                                        class="inline-flex items-center gap-1 text-[10px] text-slate-500 font-bold hover:text-slate-800 transition cursor-pointer disabled:opacity-50">
                                    <span wire:loading.remove wire:target="viewArticle('{{ $item->article_id ? 'article' : 'social' }}', {{ $item->article_id ?: $item->social_media_item_id }})" class="inline-flex items-center gap-1">
                                        <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                        Lihat Konten
                                    </span>
                                    <span wire:loading wire:target="viewArticle('{{ $item->article_id ? 'article' : 'social' }}', {{ $item->article_id ?: $item->social_media_item_id }})" class="inline-flex items-center gap-1 text-[#1fa387]">
                                        <svg class="w-2.5 h-2.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        Memuat...
                                    </span>
                                </button>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 text-center">
                            <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-bold {{ $riskColor }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $riskDot }}"></span>{{ ucfirst($item->risk_level ?? '—') }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-center">
                            @if ($sentPositive)
                            <svg class="w-5 h-5 text-emerald-500 mx-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                            @elseif ($sentNegative)
                            <svg class="w-5 h-5 text-red-500 mx-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M16 16s-1.5-2-4-2-4 2-4 2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                            @else
                            <svg class="w-5 h-5 text-slate-400 mx-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="8" y1="15" x2="16" y2="15"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                            @endif
                            <div class="text-[10px] font-bold text-slate-400 tabular-nums">{{ number_format($sentScore, 2) }}</div>
                        </td>
                        <td class="px-4 py-3.5 text-center">
                            @if ($item->analysis_status === 'success')
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200/60 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Sukses
                            </span>
                            @elseif ($item->analysis_status === 'failed')
                            <span class="inline-flex items-center gap-1 rounded-full bg-red-50 border border-red-200/60 px-2 py-0.5 text-[10px] font-bold text-red-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>Gagal
                            </span>
                            @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 border border-slate-200/50 px-2 py-0.5 text-[10px] font-bold text-slate-500">
                                {{ ucfirst($item->analysis_status ?? '—') }}
                            </span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="text-[11px] font-semibold text-slate-700">{{ $item->created_at ? \Carbon\Carbon::parse($item->created_at)->setTimezone(config('app.timezone', 'Asia/Makassar'))->format('d M Y H:i') : '—' }}</div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-6 py-20 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 mb-4">
                                <svg class="w-7 h-7 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                            </div>
                            <h3 class="text-sm font-bold text-slate-700">Belum Ada Analisis AI</h3>
                            <p class="text-xs text-slate-400 mt-1 max-w-xs leading-normal">Hasil analisis AI dari portal berita maupun media sosial akan ditampilkan di sini.</p>
                        </div>
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
            @endif

            {{-- ═══════════════════════════ --}}
            {{-- TAB: NOTIFIKASI            --}}
            {{-- ═══════════════════════════ --}}
            @if ($activeTab === 'notifications')
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b-2 border-slate-100 bg-gradient-to-r from-slate-50 to-slate-50/60">
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Sumber / Konten Terkait</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-24 text-center">Risiko</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-28 text-center">Status Notif</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-56">Pesan Error</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-36">Waktu</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-20 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/70">
                    @forelse ($items as $item)
                    <tr class="group transition-all duration-150 hover:bg-[#1fa387]/[0.025]">
                        <td class="px-4 py-3.5 max-w-xs">
                            @if ($item->aiAnalysisResult?->article)
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ $item->aiAnalysisResult->article->source_name }}</div>
                            <p class="mt-0.5 text-xs font-semibold text-slate-800 line-clamp-2">{{ $item->aiAnalysisResult->article->title }}</p>
                            @elseif ($item->aiAnalysisResult?->socialMediaItem)
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ $item->aiAnalysisResult->socialMediaItem->platform }}</div>
                            <p class="mt-0.5 text-xs font-semibold text-slate-800 line-clamp-2">Post oleh {{ $item->aiAnalysisResult->socialMediaItem->author_name }}</p>
                            @else
                            <span class="text-xs font-semibold text-slate-400">Analisis #{{ $item->ai_analysis_result_id }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-center">
                            @php $risk = $item->aiAnalysisResult->risk_level ?? null @endphp
                            @if ($risk)
                            <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-bold
                                {{ $risk === 'high' || $risk === 'critical' ? 'bg-red-50 text-red-700 border-red-200/60' : ($risk === 'medium' ? 'bg-orange-50 text-orange-700 border-orange-200/60' : 'bg-emerald-50 text-emerald-700 border-emerald-200/60') }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $risk === 'high' || $risk === 'critical' ? 'bg-red-500' : ($risk === 'medium' ? 'bg-orange-400' : 'bg-emerald-500') }}"></span>
                                {{ ucfirst($risk) }}
                            </span>
                            @else
                            <span class="text-xs text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 text-center">
                            @if ($item->status === 'sent')
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200/60 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700">
                                <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                Terkirim
                            </span>
                            @elseif ($item->status === 'failed')
                            <span class="inline-flex items-center gap-1 rounded-full bg-red-50 border border-red-200/60 px-2.5 py-0.5 text-[10px] font-bold text-red-700">
                                <svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                                Gagal
                            </span>
                            @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 border border-slate-200/50 px-2.5 py-0.5 text-[10px] font-bold text-slate-500">
                                {{ ucfirst($item->status ?? '—') }}
                            </span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5">
                            <p class="text-xs text-red-600/80 font-medium leading-relaxed line-clamp-2">{{ $item->error_message ?? '—' }}</p>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="text-[11px] font-semibold text-slate-700">{{ $item->created_at ? \Carbon\Carbon::parse($item->created_at)->setTimezone(config('app.timezone', 'Asia/Makassar'))->format('d M Y H:i') : '—' }}</div>
                        </td>
                        <td class="px-4 py-3.5 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                @if ($item->status === 'failed')
                                <button type="button"
                                    @click="triggerConfirm('Kirim Ulang Notifikasi', 'Yakin ingin mengirim ulang notifikasi krisis Telegram ini?', 'info', () => $wire.retryNotification({{ $item->id }}))"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-[#1fa387]/10 text-[#1fa387] hover:bg-[#1fa387]/20 transition" title="Kirim Ulang">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                                </button>
                                @endif
                                <button type="button"
                                    @click="triggerConfirm('Hapus Notifikasi', 'Yakin ingin menghapus riwayat notifikasi ini? Tindakan tidak dapat dibatalkan.', 'danger', () => $wire.deleteNotification({{ $item->id }}))"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition" title="Hapus">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-6 py-20 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 mb-4">
                                <svg class="w-7 h-7 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                            </div>
                            <h3 class="text-sm font-bold text-slate-700">Belum Ada Notifikasi</h3>
                            <p class="text-xs text-slate-400 mt-1 max-w-xs leading-normal">Riwayat notifikasi krisis Telegram akan ditampilkan di sini.</p>
                        </div>
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
            @endif

            {{-- ═══════════════════════════ --}}
            {{-- TAB: ANTRIAN AI            --}}
            {{-- ═══════════════════════════ --}}
            @if ($activeTab === 'queue-pending')
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b-2 border-slate-100 bg-gradient-to-r from-slate-50 to-slate-50/60">
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-10">#</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Tipe & Proyek</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-28 text-center">Status</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-20 text-center">Percobaan</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-36">Coba Lagi Pada</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-20 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/70">
                    @forelse ($items as $item)
                    <tr class="group transition-all duration-150 hover:bg-[#1fa387]/[0.025]">
                        <td class="px-4 py-3.5 text-xs font-semibold text-slate-300 tabular-nums">{{ $items->firstItem() + $loop->index }}</td>
                        <td class="px-4 py-3.5">
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-amber-50 border border-amber-200/50 px-2.5 py-1 text-[10px] font-bold text-amber-700">
                                <svg class="w-3 h-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                {{ ucfirst($item->analyzable_type) }} #{{ $item->analyzable_id }}
                            </span>
                            @if($item->project)
                            <div class="mt-1 text-[11px] font-semibold text-slate-700">{{ $item->project->name }}</div>
                                
@endif
                            <div class="mt-0.5 text-[10px] text-slate-400">{{ $item->created_at ? \Carbon\Carbon::parse($item->created_at)->setTimezone(config('app.timezone', 'Asia/Makassar'))->format('d M Y H:i') : '—' }}</div>
                        </td>
                        <td class="px-4 py-3.5 text-center">
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold
                                {{ $item->status === 'queued' ? 'bg-blue-50 text-blue-700 border border-blue-200/50' : '' }}
                                {{ $item->status === 'retry_wait' ? 'bg-amber-50 text-amber-700 border border-amber-200/50' : '' }}
                                {{ $item->status === 'processing' ? 'bg-purple-50 text-purple-700 border border-purple-200/50' : '' }}">
                                <span class="h-1.5 w-1.5 rounded-full
                                    {{ $item->status === 'queued' ? 'bg-blue-500' : '' }}
                                    {{ $item->status === 'retry_wait' ? 'bg-amber-500 animate-pulse' : '' }}
                                    {{ $item->status === 'processing' ? 'bg-purple-500' : '' }}"></span>
                                {{ ucfirst(str_replace('_', ' ', $item->status)) }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-center text-xs font-bold text-slate-600 tabular-nums">{{ $item->attempts }}</td>
                        <td class="px-4 py-3.5 text-[11px] font-semibold text-slate-500">{{ $item->next_retry_at ? $item->next_retry_at->format('d M H:i') : '—' }}</td>
                        <td class="px-4 py-3.5 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <button type="button" wire:click="viewArticle('{{ $item->analyzable_type }}', {{ $item->analyzable_id }})" wire:loading.attr="disabled"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 transition cursor-pointer disabled:opacity-50" title="Lihat Konten">
                                    <span wire:loading.remove wire:target="viewArticle('{{ $item->analyzable_type }}', {{ $item->analyzable_id }})">
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </span>
                                    <span wire:loading wire:target="viewArticle('{{ $item->analyzable_type }}', {{ $item->analyzable_id }})">
                                        <svg class="w-3.5 h-3.5 animate-spin text-[#1fa387]" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    </span>
                                </button>
                                <button type="button"
                                    @click="triggerConfirm('Hapus Antrean', 'Yakin ingin membatalkan dan menghapus antrean analisis AI ini?', 'danger', () => $wire.deleteAiState({{ $item->id }}))"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition" title="Hapus">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-6 py-20 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 mb-4">
                                <svg class="w-7 h-7 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            </div>
                            <h3 class="text-sm font-bold text-slate-700">Antrean Kosong</h3>
                            <p class="text-xs text-slate-400 mt-1 max-w-xs leading-normal">Tidak ada antrean analisis AI aktif saat ini.</p>
                        </div>
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
            @endif

            {{-- ═══════════════════════════ --}}
            {{-- TAB: QUEUE GAGAL           --}}
            {{-- ═══════════════════════════ --}}
            @if ($activeTab === 'queue-failed')
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b-2 border-slate-100 bg-gradient-to-r from-slate-50 to-slate-50/60">
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-10">#</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-40">Tipe & Proyek</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-80">Kategori Error</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Pesan Error</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-36">Waktu Gagal</th>
                        <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400 w-24 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/70">
                    @forelse ($items as $item)
                    <tr class="group transition-all duration-150 hover:bg-red-50/20">
                        <td class="px-4 py-3.5 text-xs font-semibold text-slate-300 tabular-nums">{{ $items->firstItem() + $loop->index }}</td>
                        <td class="px-4 py-3.5">
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-red-50 border border-red-200/50 px-2 py-0.5 text-[10px] font-bold text-red-700">
                                <svg class="w-3 h-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                {{ ucfirst($item->analyzable_type) }} #{{ $item->analyzable_id }}
                            </span>
                            @if($item->project)
                            <div class="mt-1 text-[11px] font-semibold text-slate-700">{{ $item->project->name }}</div>
                            
@endif
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="text-[10px] font-bold text-red-600 uppercase tracking-wider">{{ $item->last_error_code ?? '—' }}</div>
                            @if($item->last_error_code)
                            @php $desc = $this->failureCodeDescription($item->last_error_code); @endphp
                            <div class="mt-1.5 space-y-1">
                                <div class="text-[10px] font-medium text-slate-700 leading-snug flex items-start gap-1.5">
                                    <span class="px-1 py-0.2 text-[8.5px] font-black bg-slate-200/70 text-slate-700 rounded shrink-0">ID</span>
                                    <span>{{ $desc['id'] }}</span>
                                </div>
                                <div class="text-[9.5px] text-slate-400 leading-snug flex items-start gap-1.5">
                                    <span class="px-1 py-0.2 text-[8.5px] font-bold bg-slate-100 text-slate-400 rounded shrink-0">EN</span>
                                    <span>{{ $desc['en'] }}</span>
                                </div>
                            </div>
                            
@endif
                        </td>
                        <td class="px-4 py-3.5 max-w-xs">
                            <p class="text-xs text-red-500/90 font-medium leading-relaxed line-clamp-3 bg-red-50/60 px-2.5 py-1.5 rounded-xl border border-red-100/60">{{ $item->error_message ?? '—' }}</p>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="text-[11px] font-semibold text-slate-700">{{ $item->completed_at ? $item->completed_at->setTimezone(config('app.timezone', 'Asia/Makassar'))->format('d M Y H:i') : ($item->updated_at ? $item->updated_at->setTimezone(config('app.timezone', 'Asia/Makassar'))->format('d M Y H:i') : '—') }}</div>
                        </td>
                        <td class="px-4 py-3.5 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <button type="button" wire:click="viewArticle('{{ $item->analyzable_type }}', {{ $item->analyzable_id }})" wire:loading.attr="disabled"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 transition cursor-pointer disabled:opacity-50" title="Lihat Konten">
                                    <span wire:loading.remove wire:target="viewArticle('{{ $item->analyzable_type }}', {{ $item->analyzable_id }})">
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </span>
                                    <span wire:loading wire:target="viewArticle('{{ $item->analyzable_type }}', {{ $item->analyzable_id }})">
                                        <svg class="w-3.5 h-3.5 animate-spin text-[#1fa387]" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    </span>
                                </button>
                                @if($this->isRetryableFailure($item->last_error_code, $item->failure_category))
                                <button type="button"
                                    @click="triggerConfirm('Coba Ulang AI', 'Yakin ingin memproses ulang item yang gagal ini?', 'info', () => $wire.retryAiState({{ $item->id }}))"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-100 transition" title="Retry">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg>
                                </button>
                                @endif
                                <button type="button"
                                    @click="triggerConfirm('Tutup Laporan Gagal', 'Yakin ingin menutup riwayat kegagalan analisis ini?', 'danger', () => $wire.deleteAiState({{ $item->id }}))"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition" title="Tutup">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-6 py-20 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 mb-4">
                                <svg class="w-7 h-7 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            </div>
                            <h3 class="text-sm font-bold text-slate-700">Semua Berjalan Lancar</h3>
                            <p class="text-xs text-slate-400 mt-1 max-w-xs leading-normal">Tidak ada antrean AI yang gagal permanen saat ini.</p>
                        </div>
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
            @endif

        </div>{{-- /overflow-x-auto --}}
    </div>{{-- /data table --}}

    {{-- ══════════════════════════════════════════════ --}}
    {{-- PAGINATION                                     --}}
    {{-- ══════════════════════════════════════════════ --}}
    @if (method_exists($items, 'hasPages') && $items->hasPages())
    <div class="bg-white border border-slate-200/60 rounded-2xl px-4 py-3 shadow-sm">
        <div class="scale-[0.95] origin-right select-none">
            {{ $items->onEachSide(1)->links('vendor.livewire.tailwind', data: ['scrollTo' => false]) }}
        </div>
    </div>
        
@endif

    {{-- ══════════════════════════════════════════════ --}}
    {{-- MODAL: LIHAT ARTIKEL / KONTEN (DESAIN DASHBOARD) --}}
    {{-- ══════════════════════════════════════════════ --}}
    @if($showArticleModal)
        <div wire:key="pipeline-article-modal"
             x-data="{ showAiSummary: false }"
             x-init="
                 document.body.style.overflow = 'hidden';
                 document.documentElement.style.overflow = 'hidden';
                 document.body.classList.add('overflow-hidden');
                 document.documentElement.classList.add('overflow-hidden');
                 return () => {
                     document.body.style.overflow = '';
                     document.documentElement.style.overflow = '';
                     document.body.classList.remove('overflow-hidden');
                     document.documentElement.classList.remove('overflow-hidden');
                 };
             "
             @wheel.prevent
             @touchmove.prevent
             style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; width: 100vw; height: 100vh; min-height: 100dvh; z-index: 999999; display: flex; align-items: center; justify-content: center; padding: 1rem; background-color: rgba(15, 23, 42, 0.65);"
             class="backdrop-blur-sm"
             wire:click.self="closeArticleModal"
             @keydown.escape.window="$wire.closeArticleModal()">
            
            <div class="bg-white rounded-3xl border border-slate-200 max-w-5xl w-full p-6 md:p-8 shadow-2xl text-left relative flex flex-col h-[90vh] max-h-[90vh] overflow-hidden"
                 style="animation: fadeInScale 0.2s ease-out;"
                 @click.stop>
                
                <!-- Close Button -->
                <button 
                    type="button"
                    wire:click="closeArticleModal" 
                    class="absolute right-6 top-6 bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition-colors cursor-pointer shadow-sm rounded-full w-9 h-9 font-bold text-sm z-50"
                >
                    ✕
                </button>

                <!-- Header Modal (Persis Layout Media Dashboard) -->
                <div class="border-b border-slate-100 pb-5 mb-3 shrink-0 pr-12">
                    <div class="flex items-center gap-3.5 mb-3">
                        <!-- Source Favicon / Platform Icon -->
                        <div class="w-10 h-10 rounded-2xl bg-slate-50 flex items-center justify-center border border-slate-200 overflow-hidden shadow-sm shrink-0">
                            @php
                                $srcLower = strtolower($viewingArticleSource);
                                $domain = match(true) {
                                    str_contains($srcLower, 'facebook') || $srcLower === 'fb' => 'facebook.com',
                                    str_contains($srcLower, 'instagram') || $srcLower === 'ig' => 'instagram.com',
                                    str_contains($srcLower, 'tiktok') || $srcLower === 'tk' => 'tiktok.com',
                                    str_contains($srcLower, 'twitter') || $srcLower === 'x.com' => 'x.com',
                                    str_contains($srcLower, '.') => $viewingArticleSource,
                                    default => $viewingArticleSource . '.com',
                                };
                            @endphp
                            <img src="https://www.google.com/s2/favicons?sz=64&domain={{ $domain }}"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                 class="w-full h-full object-cover"
                                 alt="{{ $viewingArticleSource }}">
                            <div style="display:none;" class="w-full h-full items-center justify-center text-[#1fa387]">
                                <span class="material-symbols-outlined text-[18px]">feed</span>
                            </div>
                        </div>

                        <div class="flex flex-col">
                            <div class="flex items-center gap-2">
                                <h4 class="text-sm font-bold text-slate-800 tracking-tight">{{ $viewingArticleSource }}</h4>
                                <span class="w-1 h-1 rounded-full bg-slate-300"></span>
                                <p class="text-[11px] font-semibold text-slate-400">{{ $viewingArticleDate }}</p>
                            </div>
                            <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                                @php
                                    $sentColor = match($viewingArticleSentiment) {
                                        'positive' => 'bg-emerald-50 border-emerald-150 text-emerald-700',
                                        'negative' => 'bg-rose-50 border-rose-150 text-rose-700',
                                        default => 'bg-slate-50 border-slate-200 text-slate-600',
                                    };
                                    $sentLabel = match($viewingArticleSentiment) {
                                        'positive' => 'Positif',
                                        'negative' => 'Negatif',
                                        default => 'Netral',
                                    };
                                    $riskColor = match($viewingArticleRisk) {
                                        'high', 'critical' => 'bg-red-50 text-red-700 border-red-200',
                                        'medium' => 'bg-amber-50 text-amber-700 border-amber-200',
                                        default => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    };
                                @endphp
                                <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-xl border {{ $sentColor }}">
                                    {{ $sentLabel }} {{ $viewingArticleScore !== null ? '(' . number_format($viewingArticleScore, 2) . ')' : '' }}
                                </span>
                                <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-xl border {{ $riskColor }}">
                                    Risiko: {{ ucfirst($viewingArticleRisk) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <h3 class="text-lg md:text-xl font-black text-slate-900 leading-snug mt-1 mb-3">
                        {{ $viewingArticleTitle }}
                    </h3>

                    <!-- Metrics Grid (Persis Dashboard: Jangkauan, Skor, Tanggal, Likes/Komen) -->
                    <div class="grid gap-2 bg-slate-50/60 rounded-2xl p-3.5 border border-slate-200/40 mb-3 w-full text-left shrink-0 {{ $viewingArticleCategory === 'social' ? 'grid-cols-2 sm:grid-cols-5' : 'grid-cols-3' }}">
                        <div class="px-1.5 py-0.5">
                            <span class="text-[8px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Jangkauan</span>
                            <div class="flex items-start gap-1 text-slate-800 text-[11px] md:text-xs font-black">
                                <span class="material-symbols-outlined text-[15px] mt-0.5 text-[#1fa387]">insights</span>
                                <div class="flex flex-col leading-tight">
                                    <span>{{ $viewingArticleReach }}</span>
                                    <span class="text-[9px] font-semibold text-slate-400 mt-0.5">{{ $viewingArticleLevel }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="px-1.5 py-0.5 border-l border-slate-200">
                            <span class="text-[8px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Skor</span>
                            <div class="flex items-center gap-1 text-slate-800 text-[11px] md:text-xs font-black">
                                <span class="material-symbols-outlined text-[15px] text-[#1fa387]">analytics</span>
                                <span>{{ $viewingArticleScore !== null ? number_format($viewingArticleScore, 2) : '0.00' }}</span>
                            </div>
                        </div>
                        <div class="px-1.5 py-0.5 border-l border-slate-200">
                            <span class="text-[8px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Tanggal</span>
                            <div class="flex items-center gap-1 text-slate-800 text-[11px] md:text-xs font-black">
                                <span class="material-symbols-outlined text-[15px] text-[#1fa387]">calendar_month</span>
                                <span>{{ $viewingArticleDate }}</span>
                            </div>
                        </div>
                        @if($viewingArticleCategory === 'social')
                        <div class="px-1.5 py-0.5 border-l border-slate-200">
                            <span class="text-[8px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Likes</span>
                            <div class="flex items-center gap-1 text-slate-800 text-[11px] md:text-xs font-black">
                                <span class="material-symbols-outlined text-[15px] text-[#1fa387]">thumb_up</span>
                                <span>{{ number_format($viewingArticleLikes, 0, ',', '.') }}</span>
                            </div>
                        </div>
                        <div class="px-1.5 py-0.5 border-l border-slate-200">
                            <span class="text-[8px] font-bold text-slate-400 uppercase tracking-widest block mb-1">Komentar</span>
                            <div class="flex items-center gap-1 text-slate-800 text-[11px] md:text-xs font-black">
                                <span class="material-symbols-outlined text-[15px] text-[#1fa387]">chat</span>
                                <span>{{ number_format($viewingArticleComments, 0, ',', '.') }}</span>
                            </div>
                        </div>
                        @endif
                    </div>

                    <!-- Action Bar (Baca Asli & Toggle Ringkasan AI) -->
                    <div class="flex items-center gap-2 pt-1">
                        @if($viewingArticleUrl)
                        <a href="{{ $viewingArticleUrl }}" target="_blank"
                           class="inline-flex items-center gap-1.5 text-xs font-bold text-[#1fa387] bg-[#1fa387]/8 hover:bg-[#1fa387]/15 px-3 py-1.5 rounded-xl transition-colors">
                            <span>Baca Artikel Asli</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                        </a>
                        @endif

                        @if($viewingArticleAiSummary)
                        <button type="button" @click="showAiSummary = !showAiSummary"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-white bg-[#1fa387] hover:bg-[#188a72] rounded-xl transition-all shadow-sm cursor-pointer">
                            <span class="material-symbols-outlined text-[15px]" :class="showAiSummary ? 'rotate-45' : ''">auto_awesome</span>
                            <span>Ringkasan AI</span>
                        </button>
                        @endif
                    </div>
                </div>

                <!-- Collapsible AI Summary Panel -->
                @if($viewingArticleAiSummary)
                <div x-show="showAiSummary"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 -translate-y-2"
                     class="mb-3 p-4 bg-emerald-50/50 border border-emerald-100 rounded-2xl shrink-0"
                     style="display: none;">
                    <h4 class="text-[11px] font-black text-emerald-800 uppercase tracking-widest flex items-center gap-1.5 mb-1.5">
                        <span class="material-symbols-outlined text-[15px] text-emerald-600">auto_awesome</span>
                        <span>Ringkasan AI</span>
                    </h4>
                    <p class="text-xs md:text-sm text-slate-700 leading-relaxed font-medium whitespace-pre-line">{{ $viewingArticleAiSummary }}</p>
                </div>
                @endif

                <!-- Content Area: Body Artikel (Scrollable, Full Native) -->
                <div class="flex flex-col overflow-hidden flex-1 min-h-0">
                    <h4 class="text-[11px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5 pb-2 shrink-0">
                        <span class="material-symbols-outlined text-[16px] text-slate-400">subject</span>
                        <span>Isi Konten Berita</span>
                    </h4>
                    <div class="text-sm md:text-base text-slate-800 leading-relaxed whitespace-pre-line overflow-y-auto flex-1 pr-3 pb-6 font-sans select-text overscroll-contain"
                         @wheel.stop
                         @touchmove.stop
                         style="-webkit-overflow-scrolling: touch; overscroll-behavior: contain;">
                        @if(empty($viewingArticleContent))
                        <div class="flex flex-col items-center justify-center py-16 text-center">
                            <span class="material-symbols-outlined text-[36px] text-slate-300 mb-2">article_shortcut</span>
                            <p class="text-sm text-slate-400 font-medium">Konten tidak tersedia</p>
                            <p class="text-xs text-slate-300 mt-1">Konten kosong atau tidak ditemukan pada database.</p>
                        </div>
                        @else
                            {{ $viewingArticleContent }}
                        @endif
                    </div>
                </div>

                <!-- Footer Modal -->
                <div class="border-t border-slate-100 pt-3 flex justify-end shrink-0">
                    <button type="button" wire:click="closeArticleModal"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 px-4 py-2 text-xs font-bold text-slate-700 transition cursor-pointer">
                        Tutup
                    </button>
                </div>

            </div>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════ --}}
    {{-- MODAL: KONFIRMASI AKSI (AlpineJS)              --}}
    {{-- ══════════════════════════════════════════════ --}}
    <div x-show="confirmOpen"
         wire:key="pipeline-monitor-confirm-modal"
         x-cloak
         x-init="$watch('confirmOpen', value => {
              if (value) {
                  document.body.classList.add('overflow-hidden');
                  document.documentElement.classList.add('overflow-hidden');
              } else {
                  document.body.classList.remove('overflow-hidden');
                  document.documentElement.classList.remove('overflow-hidden');
              }
          })"
         @keydown.escape.window="confirmOpen = false"
         style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; width: 100vw; height: 100vh; min-height: 100dvh; z-index: 999999; display: flex; align-items: center; justify-content: center; background-color: rgba(15, 23, 42, 0.65); overscroll-behavior: none;"
         class="backdrop-blur-sm px-4 font-sans"
         @touchmove.prevent
         @wheel.prevent
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
    >
        <div class="w-full max-w-sm bg-white shadow-2xl rounded-2xl overflow-hidden text-center p-6 border border-slate-200"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-4"
             @click.away="confirmOpen = false"
        >
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl"
                 :class="confirmType === 'danger' ? 'bg-red-50 text-red-600' : 'bg-[#1fa387]/10 text-[#1fa387]'">
                 <svg class="w-6 h-6" :class="confirmType === 'danger' ? 'text-red-600' : 'text-[#1fa387]'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
            </div>
            <h3 class="text-base font-black text-slate-900 tracking-tight" x-text="confirmTitle">Konfirmasi</h3>
            <p class="text-xs text-slate-500 mt-2 leading-relaxed" x-text="confirmMessage">Apakah Anda yakin?</p>

            <div class="mt-6 flex flex-col gap-2">
                <button type="button" @click="executeConfirm()"
                        class="w-full inline-flex justify-center items-center gap-2 px-4 py-2.5 text-sm font-bold text-white transition rounded-xl shadow-sm"
                        :class="confirmType === 'danger' ? 'bg-red-600 hover:bg-red-700' : 'bg-[#1fa387] hover:bg-[#15876f]'">
                    <span x-text="confirmType === 'danger' ? 'Ya, Lanjutkan' : 'Konfirmasi'">Konfirmasi</span>
                </button>
                <button type="button" @click="confirmOpen = false"
                        class="w-full inline-flex justify-center items-center px-4 py-2.5 text-xs font-bold text-slate-600 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 transition rounded-xl">
                    Batal
                </button>
            </div>
        </div>
    </div>

    <style>
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.97) translateY(8px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }
        
        /* ── Mobile Layout Optimization for Pipeline Monitor ── */
        @media (max-width: 1023px) {
            /* Perkecil dan atur grid filter bar agar tidak terlalu memakan ruang tinggi */
            .flex-col.sm\:flex-row.sm\:items-center.justify-between.gap-3.bg-white {
                padding: 0.75rem !important;
                border-radius: 14px !important;
                gap: 0.5rem !important;
            }
            .flex.flex-wrap.items-center.gap-2.flex-1 {
                display: flex !important;
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 0.5rem !important;
            }
            .flex.flex-wrap.items-center.gap-2.flex-1 > div,
            .flex.flex-wrap.items-center.gap-2.flex-1 input,
            .flex.flex-wrap.items-center.gap-2.flex-1 select {
                width: 100% !important;
                height: 34px !important;
                font-size: 11px !important;
            }
            .flex.flex-wrap.items-center.gap-2.flex-1 input {
                padding-left: 2.25rem !important;
            }
            
            /* Sembunyikan divider garis vertical di mobile */
            .hidden.sm\:block.h-6.w-px.bg-slate-200 {
                display: none !important;
            }
            
            /* Menyesuaikan susunan tab button */
            .overflow-x-auto.-mx-1.px-1.pb-0\.5 {
                margin-left: 0 !important;
                margin-right: 0 !important;
                padding: 0 !important;
            }
            .flex.gap-1\.5.min-w-max.rounded-2xl {
                padding: 0.25rem !important;
                border-radius: 12px !important;
                gap: 4px !important;
            }
            .flex.gap-1\.5.min-w-max.rounded-2xl button {
                padding: 6px 10px !important;
                border-radius: 8px !important;
                font-size: 11px !important;
                height: 30px !important;
                gap: 4px !important;
            }
            .flex.gap-1\.5.min-w-max.rounded-2xl button span.material-symbols-outlined {
                font-size: 14px !important;
            }
            .flex.gap-1\.5.min-w-max.rounded-2xl button span.rounded-full {
                font-size: 8.5px !important;
                padding: 1px 4px !important;
                min-w: 16px !important;
            }

            /* Atur summary pills di bawah title */
            .flex.justify-end.gap-2 {
                justify-content: flex-start !important;
                flex-wrap: wrap !important;
                margin-bottom: 0.25rem !important;
            }
            .flex.justify-end.gap-2 div {
                padding: 4px 8px !important;
                font-size: 10px !important;
                border-radius: 8px !important;
            }
        }
    </style>

</div>
