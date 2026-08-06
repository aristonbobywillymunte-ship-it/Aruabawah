<div class="space-y-6" 
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
    {{-- Page Header --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Pipeline Monitor</h1>
            <p class="text-xs text-slate-500 mt-1">Pantau dan kelola antrean data scraping, status analisis AI, serta pengiriman notifikasi secara real-time.</p>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-2 overflow-x-auto rounded-2xl bg-slate-100/80 p-1.5 border border-slate-200/50 backdrop-blur-sm">
        @foreach ([
            ['key' => 'scraping',       'label' => 'Artikel Proyek',    'icon' => 'feed',             'count' => $portalItems, 'hint' => 'Terkait proyek'],
            ['key' => 'social',         'label' => 'Sosial Media',       'icon' => 'public',           'count' => $globalSocial, 'hint' => 'Global'],
            ['key' => 'ai',             'label' => 'Analisis AI',        'icon' => 'psychology',       'count' => $aiTotal, 'hint' => 'Selesai / total'],
            ['key' => 'notifications',  'label' => 'Notifikasi',         'icon' => 'send',             'count' => $notifTotal, 'hint' => 'Global'],
            ['key' => 'queue-pending',  'label' => 'Antrian AI',         'icon' => 'pending',          'count' => $aiPending, 'hint' => 'Queued aktif'],
            ['key' => 'queue-failed',   'label' => 'Queue Gagal',        'icon' => 'report',           'count' => $failedJobs, 'hint' => 'Riwayat gagal'],
        ] as $tab)
        <button
            wire:click="setTab('{{ $tab['key'] }}')"
            id="tab-{{ $tab['key'] }}"
            class="flex shrink-0 items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition-all duration-200
                {{ $activeTab === $tab['key'] ? 'bg-white text-[#1fa387] shadow-sm border border-slate-200/40' : 'text-slate-600 hover:bg-white/50 hover:text-slate-800' }}"
        >
            <span class="material-symbols-outlined text-[20px] {{ $activeTab === $tab['key'] ? 'text-[#1fa387]' : 'text-slate-400' }}">{{ $tab['icon'] }}</span>
            <span class="flex flex-col leading-tight text-left">
                <span class="text-xs tracking-tight">{{ $tab['label'] }}</span>
                @if(!empty($tab['hint']))
                    <span class="text-[9px] font-medium text-slate-400/90">{{ $tab['hint'] }}</span>
                @endif
            </span>
            @if ($tab['count'] > 0)
            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold transition-all duration-250
                {{ $tab['key'] === 'queue-failed' ? 'bg-red-550/10 text-red-650' : 'bg-slate-200 text-slate-600' }}">
                {{ number_format($tab['count']) }}
            </span>
            @endif
        </button>
        @endforeach
    </div>

    {{-- Filter Bar --}}
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-white p-4 rounded-2xl border border-slate-200/60 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 w-full lg:w-auto">
            <!-- Search Input -->
            <div class="w-full sm:w-80 relative">
                <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                <input
                    wire:model.live.debounce.400ms="search"
                    type="text"
                    placeholder="{{ match($activeTab) { 'scraping' => 'Cari Artikel...', 'social' => 'Cari Sosial Media...', 'ai' => 'Cari Hasil Analisis...', 'notifications' => 'Cari Notifikasi...', 'queue-pending' => 'Cari Antrian...', 'queue-failed' => 'Cari Job Gagal...', default => 'Cari...' } }}"
                    class="w-full h-[42px] rounded-xl border border-slate-200 bg-slate-50/50 pl-10 pr-4 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/15 focus:bg-white transition-all text-xs font-semibold text-slate-800"
                />
            </div>

            {{-- Filter Proyek (tersedia di semua tab kecuali queue) --}}
            @if (!in_array($activeTab, ['queue-pending','queue-failed']))
            <div class="relative w-full sm:w-auto">
                <select wire:model.live="filterProject" class="w-full sm:w-auto appearance-none h-[42px] rounded-xl border border-slate-200 bg-slate-50/50 pl-4 pr-10 text-xs font-semibold outline-none focus:border-[#1fa387] focus:bg-white focus:ring-2 focus:ring-[#1fa387]/15 transition-all text-slate-800">
                    <option value="">Semua Proyek</option>
                    @foreach ($projects as $proj)
                    <option value="{{ $proj->id }}">{{ $proj->name }}</option>
                    @endforeach
                </select>
                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-[18px]">expand_more</span>
            </div>
            @endif

            {{-- Tab Scraping Filters --}}
            @if ($activeTab === 'scraping')
            <div class="relative w-full sm:w-auto">
                <select wire:model.live="filterPlatform" class="w-full sm:w-auto appearance-none h-[42px] rounded-xl border border-slate-200 bg-slate-50/50 pl-4 pr-10 text-xs font-semibold outline-none focus:border-[#1fa387] focus:bg-white focus:ring-2 focus:ring-[#1fa387]/15 transition-all text-slate-800">
                    <option value="">Semua Sumber</option>
                    @foreach ($sources as $src)
                    <option value="{{ $src }}">{{ $src }}</option>
                    @endforeach
                </select>
                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-[18px]">expand_more</span>
            </div>
            <div class="relative w-full sm:w-auto">
                <select wire:model.live="filterAiState" class="w-full sm:w-auto appearance-none h-[42px] rounded-xl border border-slate-200 bg-slate-50/50 pl-4 pr-10 text-xs font-semibold outline-none focus:border-[#1fa387] focus:bg-white focus:ring-2 focus:ring-[#1fa387]/15 transition-all text-slate-800">
                    <option value="">Semua Status AI</option>
                    <option value="success">Selesai</option>
                    <option value="failed">Gagal</option>
                    <option value="pending">Menunggu Proses AI</option>
                </select>
                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-[18px]">expand_more</span>
            </div>
            @endif

            {{-- Tab Social Filters --}}
            @if ($activeTab === 'social')
            <div class="relative w-full sm:w-auto">
                <select wire:model.live="filterPlatform" class="w-full sm:w-auto appearance-none h-[42px] rounded-xl border border-slate-200 bg-slate-50/50 pl-4 pr-10 text-xs font-semibold outline-none focus:border-[#1fa387] focus:bg-white focus:ring-2 focus:ring-[#1fa387]/15 transition-all text-slate-800">
                    <option value="">Semua Platform</option>
                    @foreach ($platforms as $p)
                    <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>
                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-[18px]">expand_more</span>
            </div>
            @endif

            {{-- Tab AI Filters --}}
            @if ($activeTab === 'ai')
            <div class="relative w-full sm:w-auto">
                <select wire:model.live="filterStatus" class="w-full sm:w-auto appearance-none h-[42px] rounded-xl border border-slate-200 bg-slate-50/50 pl-4 pr-10 text-xs font-semibold outline-none focus:border-[#1fa387] focus:bg-white focus:ring-2 focus:ring-[#1fa387]/15 transition-all text-slate-800">
                    <option value="">Semua Status</option>
                    <option value="success">Sukses</option>
                    <option value="failed">Gagal</option>
                </select>
                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-[18px]">expand_more</span>
            </div>
            <div class="relative w-full sm:w-auto">
                <select wire:model.live="filterRisk" class="w-full sm:w-auto appearance-none h-[42px] rounded-xl border border-slate-200 bg-slate-50/50 pl-4 pr-10 text-xs font-semibold outline-none focus:border-[#1fa387] focus:bg-white focus:ring-2 focus:ring-[#1fa387]/15 transition-all text-slate-800">
                    <option value="">Semua Risiko</option>
                    <option value="high">Tinggi</option>
                    <option value="medium">Sedang</option>
                    <option value="low">Rendah</option>
                </select>
                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-[18px]">expand_more</span>
            </div>
            @endif

            {{-- Tab Notifications Filters --}}
            @if ($activeTab === 'notifications')
            <div class="relative w-full sm:w-auto">
                <select wire:model.live="filterStatus" class="w-full sm:w-auto appearance-none h-[42px] rounded-xl border border-slate-200 bg-slate-50/50 pl-4 pr-10 text-xs font-semibold outline-none focus:border-[#1fa387] focus:bg-white focus:ring-2 focus:ring-[#1fa387]/15 transition-all text-slate-800">
                    <option value="">Semua Status</option>
                    <option value="sent">Terkirim</option>
                    <option value="failed">Gagal</option>
                    <option value="skipped">Dilewati</option>
                </select>
                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-[18px]">expand_more</span>
            </div>
            @endif

            {{-- Tab Queue Failed Filters --}}
            @if ($activeTab === 'queue-failed')
            <div class="relative w-full sm:w-auto">
                <select wire:model.live="filterStatus" class="w-full sm:w-auto appearance-none h-[42px] rounded-xl border border-slate-200 bg-slate-50/50 pl-4 pr-10 text-xs font-semibold outline-none focus:border-[#1fa387] focus:bg-white focus:ring-2 focus:ring-[#1fa387]/15 transition-all text-slate-800">
                    <option value="">Semua Queue</option>
                    <option value="default">default</option>
                    <option value="ai-analysis">ai-analysis</option>
                    <option value="scraping">scraping</option>
                </select>
                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-[18px]">expand_more</span>
            </div>
            @endif
        </div>

        {{-- Action Buttons for Specific Tabs --}}
        @if ($activeTab === 'queue-failed' && $failedJobs > 0)
        <div class="w-full lg:w-auto">
            <button 
                type="button"
                @click="triggerConfirm(
                    'Retry Semua AI yang Gagal', 
                    'Apakah Anda yakin ingin melakukan retry massal untuk semua AI Analysis yang gagal?', 
                    'info', 
                    () => $wire.retryAllFailedAiStates()
                )"
                class="flex items-center justify-center gap-2 w-full sm:w-auto h-[42px] rounded-xl bg-[#1fa387] hover:bg-[#17856d] px-5 py-2.5 text-xs font-bold text-white transition-all shadow-md shadow-[#1fa387]/10"
            >
                <span class="material-symbols-outlined text-[18px]">replay</span>
                Retry Semua AI Gagal
            </button>
        </div>
        @endif

        @if ($activeTab === 'queue-pending' && $aiPending > 0)
        <div class="w-full lg:w-auto">
            <button
                type="button"
                @click="triggerConfirm(
                    'Bersihkan Semua Antrean', 
                    'Yakin ingin menghapus seluruh antrean AI (termasuk yang retry)? Tindakan ini akan menghentikan proses AI untuk semua item yang belum diproses.', 
                    'danger', 
                    () => $wire.clearAllPendingAiStates()
                )"
                class="flex items-center justify-center gap-2 w-full sm:w-auto h-[42px] rounded-xl bg-red-50 hover:bg-red-100 px-5 py-2.5 text-xs font-bold text-red-650 transition-all border border-red-200/50"
            >
                <span class="material-symbols-outlined text-[18px]">delete_sweep</span>
                Bersihkan Semua Antrean
            </button>
        </div>
        @endif
    </div>

    {{-- Content Table --}}
    <div class="relative overflow-x-auto rounded-2xl border border-slate-200/60 bg-white shadow-sm min-h-[250px]">
        {{-- Loading Overlay --}}
        <div wire:loading.delay class="absolute inset-0 z-10 flex items-center justify-center bg-white/60 backdrop-blur-[1px] transition-all duration-200">
            <div class="flex flex-col items-center justify-center bg-white/90 px-6 py-4 rounded-2xl shadow-lg border border-slate-100">
                <svg class="animate-spin h-7 w-7 text-[#1fa387]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-xs font-bold text-slate-700 mt-2.5 tracking-tight">Memuat Data...</span>
            </div>
        </div>

        {{-- ===== TAB: ARTIKEL PROYEK (Portal Berita) ===== --}}
        @if ($activeTab === 'scraping')
        <table class="w-full border-collapse text-left">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/75">
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-16">No</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-44">Proyek</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500">Sumber / Judul</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-28">Sentimen</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-36">Status AI</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-48">Waktu Terbit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($items as $item)
                @php
                    $ai = $item->aiAnalysisResult;
                    $sentScore = $item->sentiment_score ?? 0;
                    $sentColor = $sentScore <= -0.4 ? 'text-red-500' : ($sentScore >= 0.3 ? 'text-emerald-600' : 'text-slate-455');
                    $sentIcon  = $sentScore <= -0.4 ? 'sentiment_very_dissatisfied' : ($sentScore >= 0.3 ? 'sentiment_satisfied' : 'sentiment_neutral');
                @endphp
                <tr class="group transition hover:bg-slate-50/40">
                    <td class="px-6 py-4 text-xs font-semibold text-slate-400">{{ $loop->iteration }}</td>
                    <td class="px-6 py-4">
                        <div class="flex flex-wrap gap-1">
                            @foreach($item->projects as $proj)
                                <span class="inline-flex items-center gap-1 rounded-lg bg-[#1fa387]/10 px-2 py-0.5 text-[10px] font-semibold text-[#1fa387]">
                                    <span class="material-symbols-outlined text-[12px]">folder</span>
                                    {{ Str::limit($proj->name, 20) }}
                                </span>
                            @endforeach
                            @if($item->projects->isEmpty())
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 max-w-md">
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ $item->source_name }}</div>
                        <p class="mt-1 text-xs font-semibold text-slate-800 leading-relaxed line-clamp-2">{{ $item->title }}</p>
                        @if ($item->url)
                        <a href="{{ $item->url }}" target="_blank" class="mt-1.5 inline-flex items-center gap-1 text-[10px] text-[#1fa387] font-bold hover:underline transition">
                            <span class="material-symbols-outlined text-[12px]">open_in_new</span>Buka Artikel
                        </a>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[20px] {{ $sentColor }}">{{ $sentIcon }}</span>
                            <span class="text-xs font-bold text-slate-600">{{ number_format($sentScore, 2) }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        @if ($ai)
                            @php $riskColors = ['high'=>'bg-red-50 text-red-700 border-red-200/50','medium'=>'bg-orange-50 text-orange-700 border-orange-200/50','low'=>'bg-emerald-50 text-emerald-700 border-emerald-200/50'] @endphp
                            <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $riskColors[$ai->risk_level] ?? 'bg-slate-50 text-slate-500 border-slate-200/50' }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $ai->risk_level === 'high' ? 'bg-red-500' : ($ai->risk_level === 'medium' ? 'bg-orange-400' : 'bg-emerald-500') }}"></span>
                                {{ ucfirst($ai->risk_level) }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 border border-amber-200/50 px-2.5 py-0.5 text-[10px] font-bold text-amber-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>Belum dianalisis
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-[11px] text-slate-500 leading-normal">
                        <div>
                            <span class="font-medium text-slate-400">Terbit:</span> 
                            <span class="font-semibold text-slate-700">{{ $item->published_at ? \Carbon\Carbon::parse($item->published_at)->format('d M Y H:i') : '—' }}</span>
                        </div>
                        <div class="mt-1 text-[10px]">
                            <span class="font-medium text-slate-400">Scraping:</span> 
                            <span class="font-medium text-slate-500">{{ $item->created_at ? \Carbon\Carbon::parse($item->created_at)->setTimezone(config('app.timezone', 'Asia/Makassar'))->format('d M Y H:i') : '—' }}</span>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-16 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <span class="material-symbols-outlined text-[48px] text-slate-300">feed</span>
                            <h3 class="mt-3 text-sm font-bold text-slate-700">Belum Ada Artikel</h3>
                            <p class="text-xs text-slate-400 mt-1 max-w-xs leading-normal">Belum ada artikel portal berita yang masuk ke dalam antrean analisis proyek saat ini.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @endif

        {{-- ===== TAB: SOSIAL MEDIA ===== --}}
        @if ($activeTab === 'social')
        <table class="w-full border-collapse text-left">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/75">
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-16">No</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-44">Proyek</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-28">Platform</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-40">Author</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500">Konten</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-32">Engagement</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-48">Waktu Scraping</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($items as $item)
                <tr class="group transition hover:bg-slate-50/40">
                    <td class="px-6 py-4 text-xs font-semibold text-slate-400">{{ $loop->iteration }}</td>
                    <td class="px-6 py-4">
                        <div class="flex flex-wrap gap-1">
                            @foreach($item->projects as $proj)
                                <span class="inline-flex items-center gap-1 rounded-lg bg-[#1fa387]/10 px-2 py-0.5 text-[10px] font-semibold text-[#1fa387]">
                                    <span class="material-symbols-outlined text-[12px]">folder</span>
                                    {{ Str::limit($proj->name, 20) }}
                                </span>
                            @endforeach
                            @if($item->projects->isEmpty())
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 border border-indigo-100/50 px-2.5 py-0.5 text-[10px] font-bold text-indigo-700">
                            <span class="material-symbols-outlined text-[13px] fill-1">public</span>
                            {{ $item->platform ?? '—' }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-xs font-bold text-slate-800">{{ $item->author_name ?? '—' }}</div>
                    </td>
                    <td class="px-6 py-4 max-w-sm">
                        <p class="text-xs text-slate-650 leading-relaxed line-clamp-2">{{ $item->content ?? '—' }}</p>
                        @if ($item->post_url)
                        <a href="{{ $item->post_url }}" target="_blank" class="mt-1 inline-flex items-center gap-1 text-[10px] text-[#1fa387] font-bold hover:underline transition">
                            <span class="material-symbols-outlined text-[12px]">open_in_new</span>Buka Post
                        </a>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex flex-wrap gap-2 text-[11px] text-slate-500">
                            @if ($item->like_count > 0)
                            <span class="flex items-center gap-1 bg-red-50 text-red-650 px-1.5 py-0.5 rounded-md font-semibold"><span class="material-symbols-outlined text-[13px] fill-1 text-red-500">favorite</span>{{ number_format($item->like_count) }}</span>
                            @endif
                            @if ($item->view_count > 0)
                            <span class="flex items-center gap-1 bg-slate-50 text-slate-600 px-1.5 py-0.5 rounded-md font-semibold"><span class="material-symbols-outlined text-[13px] text-slate-400">visibility</span>{{ number_format($item->view_count) }}</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 text-[11px] text-slate-500 leading-normal">
                        <div>
                            <span class="font-medium text-slate-400">Publikasi:</span> 
                            <span class="font-semibold text-slate-700">{{ $item->posted_at ? \Carbon\Carbon::parse($item->posted_at)->setTimezone(config('app.timezone', 'Asia/Makassar'))->format('d M Y H:i') : '—' }}</span>
                        </div>
                        <div class="mt-1 text-[10px]">
                            <span class="font-medium text-slate-400">Scraping:</span> 
                            <span class="font-medium text-slate-500">{{ $item->created_at ? \Carbon\Carbon::parse($item->created_at)->setTimezone(config('app.timezone', 'Asia/Makassar'))->format('d M Y H:i') : '—' }}</span>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-16 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <span class="material-symbols-outlined text-[48px] text-slate-300">public</span>
                            <h3 class="mt-3 text-sm font-bold text-slate-700">Belum Ada Sosial Media</h3>
                            <p class="text-xs text-slate-400 mt-1 max-w-xs leading-normal">Belum ada konten media sosial yang tersinkronisasi. Jalankan Apify Actor untuk memulai.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @endif

        {{-- ===== TAB: ANALISIS AI ===== --}}
        @if ($activeTab === 'ai')
        <table class="w-full border-collapse text-left">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/75">
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-16">No</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-44">Proyek</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500">Sumber / Ringkasan</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-28">Risiko</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-28">Sentimen</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-28">Status</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-48">Waktu Analisis</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($items as $item)
                @php
                    $riskColors = ['high'=>'bg-red-50 text-red-700 border-red-200/50','medium'=>'bg-orange-50 text-orange-700 border-orange-200/50','low'=>'bg-emerald-50 text-emerald-700 border-emerald-200/50'];
                    $riskColor = $riskColors[$item->risk_level] ?? 'bg-slate-50 text-slate-600 border-slate-200/50';
                    $riskDot   = ['high'=>'bg-red-500','medium'=>'bg-orange-400','low'=>'bg-emerald-500'][$item->risk_level] ?? 'bg-slate-400';
                    $sentScore = $item->sentiment_score ?? 0;
                    $sentColor = $sentScore <= -0.4 ? 'text-red-500' : ($sentScore >= 0.3 ? 'text-emerald-600' : 'text-slate-455');
                    $sentIcon  = $sentScore <= -0.4 ? 'sentiment_very_dissatisfied' : ($sentScore >= 0.3 ? 'sentiment_satisfied' : 'sentiment_neutral');
                @endphp
                <tr class="group transition hover:bg-slate-50/40">
                    <td class="px-6 py-4 text-xs font-semibold text-slate-400">
                        {{ $items->firstItem() + $loop->index }}
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex flex-wrap gap-1">
                            @php
                                $projList = collect();
                                if ($item->article && $item->article->projects) {
                                    $projList = $item->article->projects;
                                } elseif ($item->socialMediaItem && $item->socialMediaItem->projects) {
                                    $projList = $item->socialMediaItem->projects;
                                }
                            @endphp
                            @foreach($projList as $proj)
                                <span class="inline-flex items-center gap-1 rounded-lg bg-[#1fa387]/10 px-2.5 py-1 text-xs font-semibold text-[#1fa387]">
                                    <span class="material-symbols-outlined text-[13px]">folder</span>
                                    {{ Str::limit($proj->name, 18) }}
                                </span>
                            @endforeach
                            @if($projList->isEmpty())
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-4 max-w-md">
                        @php
                            $sourceUrl = null;
                            if ($item->article) {
                                $sourceUrl = $item->article->url ?? $item->article->canonical_url ?? null;
                            } elseif ($item->socialMediaItem) {
                                $sourceUrl = $item->socialMediaItem->post_url ?? null;
                            }
                        @endphp
                        @if ($item->article)
                        <div class="text-[10px] font-bold text-slate-450 uppercase tracking-wider">
                            @if($sourceUrl)
                                <a href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer" class="hover:text-[#1fa387] transition font-bold">
                                    {{ $item->article->source_name }}
                                </a>
                            @else
                                {{ $item->article->source_name }}
                            @endif
                        </div>
                        <p class="mt-1 text-xs font-semibold text-slate-800 leading-relaxed line-clamp-1">
                            @if($sourceUrl)
                                <a href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer" class="hover:text-[#1fa387] transition">
                                    {{ $item->article->title }}
                                </a>
                            @else
                                {{ $item->article->title }}
                            @endif
                        </p>
                        @elseif ($item->socialMediaItem)
                        <div class="text-[10px] font-bold text-indigo-500 uppercase tracking-wider">
                            @if($sourceUrl)
                                <a href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer" class="hover:text-[#1fa387] transition font-bold">
                                    {{ $item->socialMediaItem->platform }}
                                </a>
                            @else
                                {{ $item->socialMediaItem->platform }}
                            @endif
                        </div>
                        <p class="mt-1 text-xs font-semibold text-slate-800 leading-relaxed line-clamp-1">
                            @if($sourceUrl)
                                <a href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer" class="hover:text-[#1fa387] transition">
                                    {{ $item->socialMediaItem->author_name }}
                                </a>
                            @else
                                {{ $item->socialMediaItem->author_name }}
                            @endif
                        </p>
                        @endif
                        @if ($item->summary)
                        <p class="mt-1 text-slate-500 text-[11px] leading-relaxed line-clamp-2 bg-slate-50/50 p-2 rounded-lg border border-slate-100">{{ $item->summary }}</p>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[10px] font-bold {{ $riskColor }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $riskDot }}"></span>
                            {{ ucfirst($item->risk_level ?? '—') }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-1">
                            <span class="material-symbols-outlined text-[20px] {{ $sentColor }}">{{ $sentIcon }}</span>
                            <span class="text-xs font-bold text-slate-600">{{ number_format($sentScore, 2) }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        @if ($item->analysis_status === 'success')
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200/50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Sukses
                        </span>
                        @elseif ($item->analysis_status === 'failed')
                        <span class="inline-flex items-center gap-1 rounded-full bg-red-50 border border-red-200/50 px-2.5 py-0.5 text-[10px] font-bold text-red-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>Gagal
                        </span>
                        @else
                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 border border-slate-200/50 px-2.5 py-0.5 text-[10px] font-bold text-slate-600">
                            {{ ucfirst($item->analysis_status ?? '—') }}
                        </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-[11px] text-slate-500 leading-normal">
                        @php
                            $scrapingTime = null;
                            if ($item->article) {
                                $scrapingTime = $item->article->created_at;
                            } elseif ($item->socialMediaItem) {
                                $scrapingTime = $item->socialMediaItem->created_at;
                            }
                        @endphp
                        <div>
                            <span class="font-medium text-slate-400">Analisis:</span> 
                            <span class="font-semibold text-slate-700">{{ $item->created_at ? \Carbon\Carbon::parse($item->created_at)->setTimezone(config('app.timezone', 'Asia/Makassar'))->format('d M Y H:i') : '—' }}</span>
                        </div>
                        <div class="mt-1 text-[10px]">
                            <span class="font-medium text-slate-400">Scraping:</span> 
                            <span class="font-medium text-slate-500">{{ $scrapingTime ? \Carbon\Carbon::parse($scrapingTime)->setTimezone(config('app.timezone', 'Asia/Makassar'))->format('d M Y H:i') : '—' }}</span>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-16 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <span class="material-symbols-outlined text-[48px] text-slate-300">psychology</span>
                            <h3 class="mt-3 text-sm font-bold text-slate-700">Belum Ada Analisis AI</h3>
                            <p class="text-xs text-slate-400 mt-1 max-w-xs leading-normal">Hasil analisis AI dari portal berita maupun medsos akan ditampilkan di sini.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @endif

        {{-- ===== TAB: NOTIFIKASI ===== --}}
        @if ($activeTab === 'notifications')
        <table class="w-full border-collapse text-left">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/75">
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500">Artikel Terkait</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-28">Risiko</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-32">Status Notif</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-64">Pesan Error</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-44">Waktu</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-28 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($items as $item)
                <tr class="group transition hover:bg-slate-50/40">
                    <td class="px-6 py-4 max-w-xs">
                        @if ($item->aiAnalysisResult?->article)
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ $item->aiAnalysisResult->article->source_name }}</div>
                        <p class="mt-1 text-xs font-semibold text-slate-800 leading-relaxed line-clamp-2">{{ $item->aiAnalysisResult->article->title }}</p>
                        @else
                        <span class="text-xs font-semibold text-slate-400">Analisis #{{ $item->ai_analysis_result_id }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        @php $risk = $item->aiAnalysisResult->risk_level ?? null @endphp
                        @if ($risk)
                        <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[10px] font-bold
                            {{ $risk === 'high' ? 'bg-red-50 text-red-700 border-red-200/50' : ($risk === 'medium' ? 'bg-orange-50 text-orange-700 border-orange-200/50' : 'bg-emerald-50 text-emerald-700 border-emerald-200/50') }}">
                            {{ ucfirst($risk) }}
                        </span>
                        @else
                        <span class="text-xs text-slate-300">—</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        @if ($item->status === 'sent')
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200/50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700">
                            <span class="material-symbols-outlined text-[13px] fill-1">send</span>Terkirim
                        </span>
                        @elseif ($item->status === 'failed')
                        <span class="inline-flex items-center gap-1 rounded-full bg-red-50 border border-red-200/50 px-2.5 py-0.5 text-[10px] font-bold text-red-700">
                            <span class="material-symbols-outlined text-[13px]">error</span>Gagal
                        </span>
                        @else
                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 border border-slate-200/50 px-2.5 py-0.5 text-[10px] font-bold text-slate-600">
                            {{ ucfirst($item->status ?? '—') }}
                        </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 max-w-xs">
                        <p class="text-xs text-red-650/90 font-medium leading-relaxed line-clamp-2">{{ $item->error_message ?? '—' }}</p>
                    </td>
                    <td class="px-6 py-4 text-xs text-slate-550 font-semibold whitespace-nowrap">
                        {{ $item->created_at ? \Carbon\Carbon::parse($item->created_at)->setTimezone(config('app.timezone', 'Asia/Makassar'))->format('d M Y H:i') : '—' }}
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            @if ($item->status === 'failed')
                            <button 
                                type="button"
                                @click="triggerConfirm(
                                    'Kirim Ulang Notifikasi', 
                                    'Apakah Anda yakin ingin mengirim ulang notifikasi krisis Telegram ini?', 
                                    'info', 
                                    () => $wire.retryNotification({{ $item->id }})
                                )"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-[#1fa387]/10 text-[#1fa387] hover:bg-[#1fa387]/20 transition" 
                                title="Kirim Ulang"
                            >
                                <span class="material-symbols-outlined text-[18px]">replay</span>
                            </button>
                            @endif
                            <button 
                                type="button"
                                @click="triggerConfirm(
                                    'Hapus Riwayat Notifikasi', 
                                    'Yakin ingin menghapus riwayat notifikasi Telegram ini? Tindakan ini tidak dapat dibatalkan.', 
                                    'danger', 
                                    () => $wire.deleteNotification({{ $item->id }})
                                )"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-red-50 text-red-650 hover:bg-red-100 transition" 
                                title="Hapus"
                            >
                                <span class="material-symbols-outlined text-[18px]">delete</span>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-16 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <span class="material-symbols-outlined text-[48px] text-slate-300">send</span>
                            <h3 class="mt-3 text-sm font-bold text-slate-700">Belum Ada Notifikasi</h3>
                            <p class="text-xs text-slate-400 mt-1 max-w-xs leading-normal">Belum ada riwayat notifikasi krisis yang dikirimkan ke Telegram.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @endif

        {{-- ===== TAB: ANTRIAN QUEUE ===== --}}
        @if ($activeTab === 'queue-pending')
        <table class="w-full border-collapse text-left">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/75">
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-16">No</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500">Tipe & Proyek</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-32">Status</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-24">Attempts</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-36">Coba Lagi Pada</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-28 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($items as $item)
                <tr class="group transition hover:bg-slate-50/40">
                    <td class="px-6 py-4 text-xs font-semibold text-slate-400">{{ $loop->iteration }}</td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-amber-50 border border-amber-250/20 px-2 py-0.5 text-[10px] font-bold text-amber-700">
                            <span class="material-symbols-outlined text-[13px] fill-1">pending</span>{{ ucfirst($item->analyzable_type) }} ID: {{ $item->analyzable_id }}
                        </span>
                        @if($item->project)
                        <div class="mt-1 text-xs font-bold text-slate-700">Proyek: {{ $item->project->name }}</div>
                        @endif
                        <div class="mt-1 text-[10px] text-slate-400">
                            <span class="font-medium text-slate-400">Masuk Antrean:</span> 
                            <span class="font-medium text-slate-500">{{ $item->created_at ? \Carbon\Carbon::parse($item->created_at)->setTimezone(config('app.timezone', 'Asia/Makassar'))->format('d M Y H:i') : '—' }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-xs">
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[10px] font-bold
                            {{ $item->status === 'queued' ? 'bg-blue-50 text-blue-700 border border-blue-200/50' : '' }}
                            {{ $item->status === 'retry_wait' ? 'bg-amber-50 text-amber-700 border border-amber-200/50' : '' }}
                            {{ $item->status === 'processing' ? 'bg-purple-50 text-purple-700 border border-purple-200/50' : '' }}
                        ">
                            <span class="h-1.5 w-1.5 rounded-full 
                                {{ $item->status === 'queued' ? 'bg-blue-500' : '' }}
                                {{ $item->status === 'retry_wait' ? 'bg-amber-500 animate-pulse' : '' }}
                                {{ $item->status === 'processing' ? 'bg-purple-500 animate-spin' : '' }}
                            "></span>
                            {{ ucfirst(str_replace('_', ' ', $item->status)) }}
                        </span>
                        @if($item->last_error_code)
                            <div class="mt-1 text-[10px] text-red-500 font-semibold uppercase tracking-wider">{{ $item->last_error_code }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-xs font-bold text-slate-600">{{ $item->attempts }}</td>
                    <td class="px-6 py-4 text-xs text-slate-500 font-semibold">{{ $item->next_retry_at ? $item->next_retry_at->format('d M H:i') : '—' }}</td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <button
                                type="button"
                                wire:click="viewArticle('{{ $item->analyzable_type }}', {{ $item->analyzable_id }})"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition"
                                title="Lihat Isi Artikel"
                            >
                                <span class="material-symbols-outlined text-[18px]">visibility</span>
                            </button>
                            <button
                                type="button"
                                @click="triggerConfirm(
                                    'Hapus Antrean AI', 
                                    'Apakah Anda yakin ingin mematalkan dan menghapus antrean analisis AI ini?', 
                                    'danger', 
                                    () => $wire.deleteAiState({{ $item->id }})
                                )"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-red-50 text-red-655 hover:bg-red-100 transition"
                                title="Batalkan Antrean"
                            >
                                <span class="material-symbols-outlined text-[18px]">delete</span>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-16 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <span class="material-symbols-outlined text-[48px] text-slate-300">pending</span>
                            <h3 class="mt-3 text-sm font-bold text-slate-700">Antrean Kosong</h3>
                            <p class="text-xs text-slate-400 mt-1 max-w-xs leading-normal">Tidak ada antrean analisis AI aktif saat ini. Semua data telah selesai diproses.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @endif

        {{-- ===== TAB: QUEUE GAGAL ===== --}}
        @if ($activeTab === 'queue-failed')
        <table class="w-full border-collapse text-left">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50/75">
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-16">No</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-48">Tipe & Proyek</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-44">Kategori Gagal</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500">Detail Pesan Error</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-44">Waktu Gagal</th>
                    <th class="px-6 py-4 text-[10px] font-bold uppercase tracking-wider text-slate-500 w-28 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($items as $item)
                <tr class="group transition hover:bg-slate-50/40">
                    <td class="px-6 py-4 text-xs font-semibold text-slate-400">{{ $loop->iteration }}</td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-red-50 border border-red-200/50 px-2 py-0.5 text-[10px] font-bold text-red-700">
                            <span class="material-symbols-outlined text-[13px]">report</span>{{ ucfirst($item->analyzable_type) }} ID: {{ $item->analyzable_id }}
                        </span>
                        @if($item->project)
                        <div class="mt-1 text-xs font-bold text-slate-700">Proyek: {{ $item->project->name }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-xs">
                        <div class="font-bold text-red-650">{{ $item->last_error_code ?? '—' }}</div>
                        @if($item->last_error_code)
                            <div class="mt-1 inline-flex rounded bg-red-50 border border-red-200/40 px-2 py-0.5 text-[9px] font-extrabold uppercase tracking-wider text-red-655">
                                {{ $this->failureCodeLabel($item->last_error_code) }}
                            </div>
                            <div class="mt-1 text-[10px] text-slate-500 leading-normal">
                                {{ $this->failureCodeDescription($item->last_error_code) }}
                            </div>
                        @endif
                    </td>
                    <td class="px-6 py-4 max-w-xs">
                        <p class="text-xs text-red-500 font-medium leading-relaxed line-clamp-3 bg-red-50/40 p-2.5 rounded-xl border border-red-100/50">{{ $item->error_message ?? '—' }}</p>
                    </td>
                    <td class="px-6 py-4 text-xs text-slate-550 font-semibold">
                        {{ $item->completed_at ? $item->completed_at->setTimezone(config('app.timezone', 'Asia/Makassar'))->format('d M Y H:i') : ($item->updated_at ? $item->updated_at->setTimezone(config('app.timezone', 'Asia/Makassar'))->format('d M Y H:i') : '—') }}
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <button
                                type="button"
                                wire:click="viewArticle('{{ $item->analyzable_type }}', {{ $item->analyzable_id }})"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition"
                                title="Lihat Isi Artikel"
                            >
                                <span class="material-symbols-outlined text-[18px]">visibility</span>
                            </button>
                            @if($this->isRetryableFailure($item->last_error_code, $item->failure_category))
                            <button
                                type="button"
                                @click="triggerConfirm(
                                    'Coba Ulang Analisis AI', 
                                    'Apakah Anda yakin ingin memproses ulang (retry) item yang gagal ini?', 
                                    'info', 
                                    () => $wire.retryAiState({{ $item->id }})
                                )"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-100 transition"
                                title="Retry Analisis"
                            >
                                <span class="material-symbols-outlined text-[18px]">refresh</span>
                            </button>
                            @endif
                            <button
                                type="button"
                                @click="triggerConfirm(
                                    'Tutup Laporan Item Gagal', 
                                    'Apakah Anda yakin ingin menutup/menyembunyikan riwayat kegagalan analisis untuk item ini?', 
                                    'danger', 
                                    () => $wire.deleteAiState({{ $item->id }})
                                )"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-red-50 text-red-655 hover:bg-red-100 transition"
                                title="Tutup / Sembunyikan"
                            >
                                <span class="material-symbols-outlined text-[18px]">close</span>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-16 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <span class="material-symbols-outlined text-[48px] text-emerald-400">check_circle</span>
                            <h3 class="mt-3 text-sm font-bold text-slate-700">Semua Berjalan Lancar</h3>
                            <p class="text-xs text-slate-400 mt-1 max-w-xs leading-normal">Tidak ditemukan adanya antrean analisis AI yang gagal permanen pada server saat ini.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @endif
    </div>

    {{-- Pagination --}}
    @if (method_exists($items, 'hasPages') && $items->hasPages())
    <div class="bg-white border border-slate-200/60 rounded-2xl p-4 shadow-sm">
        <div class="scale-[0.95] origin-right select-none w-full">
            {{ $items->onEachSide(1)->links('vendor.livewire.tailwind', data: ['scrollTo' => false]) }}
        </div>
    </div>
    @endif

    {{-- Modal Lihat Artikel (Aesthetic Glassmorphism & Clean Typography) --}}
    @if($showArticleModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md transition-all duration-300">
        <div class="flex h-full max-h-[85vh] w-full max-w-3xl flex-col rounded-3xl bg-white shadow-2xl border border-slate-100 overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5 bg-slate-50/50">
                <div class="flex items-center gap-2 text-slate-800">
                    <span class="material-symbols-outlined text-[#1fa387]">article</span>
                    <h2 class="text-md font-extrabold tracking-tight leading-none">{{ $viewingArticleTitle }}</h2>
                </div>
                <button wire:click="closeArticleModal" class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            {{-- Content --}}
            <div class="flex-1 overflow-y-auto px-8 py-6">
                <div class="prose prose-slate max-w-none text-sm text-slate-600 leading-relaxed font-medium">
                    {!! nl2br(e($viewingArticleContent)) !!}
                </div>
                @if(empty($viewingArticleContent))
                    <div class="text-center text-slate-450 italic py-12">Konten kosong atau tidak ditemukan pada database.</div>
                @endif
            </div>

            {{-- Footer --}}
            <div class="border-t border-slate-100 bg-slate-50/70 px-6 py-4 flex justify-end">
                <button wire:click="closeArticleModal" class="rounded-xl bg-white px-5 py-2.5 text-xs font-bold text-slate-700 shadow-sm border border-slate-200 hover:bg-slate-50 transition">
                    Tutup Detail
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Premium AlpineJS Confirmation Modal --}}
    <div x-show="confirmOpen" 
         class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm transition-all duration-300"
         x-transition:enter="transition ease-out duration-350"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         style="display: none;"
    >
        <div class="w-full max-w-md bg-white rounded-3xl p-6 shadow-2xl border border-slate-100 text-center relative overflow-hidden" @click.away="confirmOpen = false">
            {{-- Icon Badge --}}
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full"
                 :class="confirmType === 'danger' ? 'bg-red-50 text-red-650' : 'bg-[#1fa387]/10 text-[#1fa387]'">
                <span class="material-symbols-outlined text-[32px]" x-text="confirmType === 'danger' ? 'warning' : 'help'">help</span>
            </div>
            
            {{-- Title & Desc --}}
            <h3 class="text-lg font-extrabold text-slate-900 tracking-tight" x-text="confirmTitle">Konfirmasi</h3>
            <p class="text-xs text-slate-500 mt-2 leading-relaxed" x-text="confirmMessage">Apakah Anda yakin ingin melakukan tindakan ini?</p>
            
            {{-- Action Buttons --}}
            <div class="mt-6 flex flex-col-reverse sm:flex-row gap-3">
                <button type="button" @click="confirmOpen = false" class="w-full rounded-xl bg-white px-4 py-3 text-xs font-bold text-slate-700 border border-slate-200 hover:bg-slate-50 transition">
                    Batalkan
                </button>
                <button type="button" @click="executeConfirm()" 
                        class="w-full rounded-xl px-4 py-3 text-xs font-bold text-white shadow-md transition"
                        :class="confirmType === 'danger' ? 'bg-red-600 hover:bg-red-700 shadow-red-200/50' : 'bg-[#1fa387] hover:bg-[#17856d] shadow-[#1fa387]/10'">
                    Lanjutkan
                </button>
            </div>
        </div>
    </div>
</div>
