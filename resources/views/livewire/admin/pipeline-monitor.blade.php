<div class="space-y-6" x-data x-on:scroll-top.window="window.scrollTo({ top: 0, behavior: 'smooth' })">

    {{-- Page Header --}}
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Pipeline Monitor</h1>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-1 overflow-x-auto rounded-2xl bg-slate-100 p-1.5">
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
            class="flex shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition-all
                {{ $activeTab === $tab['key'] ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}"
        >
            <span class="material-symbols-outlined text-[18px]">{{ $tab['icon'] }}</span>
            <span class="flex flex-col leading-tight text-left">
                <span>{{ $tab['label'] }}</span>
                @if(!empty($tab['hint']))
                    <span class="text-[9px] font-medium text-slate-400">{{ $tab['hint'] }}</span>
                @endif
            </span>
            @if ($tab['count'] > 0)
            <span class="rounded-full px-2 py-0.5 text-xs font-bold
                {{ $tab['key'] === 'queue-failed' ? 'bg-red-100 text-red-600' : 'bg-slate-200 text-slate-600' }}">
                {{ number_format($tab['count']) }}
            </span>
            @endif
        </button>
        @endforeach
    </div>

    {{-- Filter Bar --}}
    <div class="flex flex-wrap items-center gap-3 w-full">
        <!-- Search Input -->
        <div style="width: 320px; max-width: 100%; display: inline-block;">
            <input
                wire:model.live.debounce.400ms="search"
                type="text"
                placeholder="{{ match($activeTab) { 'scraping' => 'Cari Artikel...', 'social' => 'Cari Sosial Media...', 'ai' => 'Cari Hasil Analisis...', 'notifications' => 'Cari Notifikasi...', 'queue-pending' => 'Cari Antrian...', 'queue-failed' => 'Cari Job Gagal...', default => 'Cari...' } }}"
                class="rounded-xl border border-slate-200 bg-white px-4 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/20 transition-all text-xs font-semibold text-slate-850"
                style="width: 100%; box-sizing: border-box; height: 42px;"
            />
        </div>

        {{-- Filter Proyek (tersedia di semua tab kecuali queue) --}}
        @if (!in_array($activeTab, ['queue-pending','queue-failed']))
        <select wire:model.live="filterProject" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-[#1fa387] transition-all">
            <option value="">Semua Proyek</option>
            @foreach ($projects as $proj)
            <option value="{{ $proj->id }}">{{ $proj->name }}</option>
            @endforeach
        </select>
        @endif

        {{-- Tab Scraping Filters --}}
        @if ($activeTab === 'scraping')
        <select wire:model.live="filterPlatform" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-[#1fa387] transition-all">
            <option value="">Semua Sumber</option>
            @foreach ($sources as $src)
            <option value="{{ $src }}">{{ $src }}</option>
            @endforeach
        </select>
        <select wire:model.live="filterAiState" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-[#1fa387] transition-all">
            <option value="">Semua Status AI</option>
            <option value="success">Selesai</option>
            <option value="failed">Gagal</option>
            <option value="pending">Menunggu Proses AI</option>
        </select>
        @endif

        {{-- Tab Social Filters --}}
        @if ($activeTab === 'social')
        <select wire:model.live="filterPlatform" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-[#1fa387] transition-all">
            <option value="">Semua Platform</option>
            @foreach ($platforms as $p)
            <option value="{{ $p }}">{{ $p }}</option>
            @endforeach
        </select>
        @endif

        {{-- Tab AI Filters --}}
        @if ($activeTab === 'ai')
        <select wire:model.live="filterStatus" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-[#1fa387] transition-all">
            <option value="">Semua Status</option>
            <option value="success">Sukses</option>
            <option value="failed">Gagal</option>
        </select>
        <select wire:model.live="filterRisk" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-[#1fa387] transition-all">
            <option value="">Semua Risiko</option>
            <option value="high">Tinggi</option>
            <option value="medium">Sedang</option>
            <option value="low">Rendah</option>
        </select>
        @endif

        {{-- Tab Notifications Filters --}}
        @if ($activeTab === 'notifications')
        <select wire:model.live="filterStatus" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-[#1fa387] transition-all">
            <option value="">Semua Status</option>
            <option value="sent">Terkirim</option>
            <option value="failed">Gagal</option>
            <option value="skipped">Dilewati</option>
        </select>
        @endif

        {{-- Tab Queue Failed Filters --}}
        @if ($activeTab === 'queue-failed')
        <select wire:model.live="filterStatus" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-[#1fa387] transition-all">
            <option value="">Semua Queue</option>
            <option value="default">default</option>
            <option value="ai-analysis">ai-analysis</option>
            <option value="scraping">scraping</option>
        </select>
        @endif
    </div>
    {{-- Table --}}
    <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">

        {{-- ===== TAB: ARTIKEL PROYEK (Portal Berita) ===== --}}
        @if ($activeTab === 'scraping')
        <table class="w-full text-sm">
            <thead class="border-b border-slate-250 bg-[#FAFBFD]">
                <tr>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">No</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Proyek</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Sumber / Judul</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Sentimen</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Status AI</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Terbit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse ($items as $item)
                @php
                    $ai = $item->aiAnalysisResult;
                    $sentScore = $item->sentiment_score ?? 0;
                    $sentColor = $sentScore <= -0.4 ? 'text-red-500' : ($sentScore >= 0.3 ? 'text-emerald-600' : 'text-slate-400');
                    $sentIcon  = $sentScore <= -0.4 ? 'sentiment_very_dissatisfied' : ($sentScore >= 0.3 ? 'sentiment_satisfied' : 'sentiment_neutral');
                @endphp
                <tr class="group transition hover:bg-slate-50/60">
                    <td class="px-5 py-4 text-xs font-semibold text-slate-400">{{ $loop->iteration }}</td>
                    <td class="px-5 py-4">
                        <div class="flex flex-wrap gap-1">
                            @foreach($item->projects as $proj)
                                <span class="inline-flex items-center gap-1 rounded-lg bg-[#1fa387]/10 px-2.5 py-1 text-xs font-semibold text-[#1fa387]">
                                    <span class="material-symbols-outlined text-[13px]">folder</span>
                                    {{ Str::limit($proj->name, 20) }}
                                </span>
                            @endforeach
                            @if($item->projects->isEmpty())
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </div>
                    </td>
                    <td class="max-w-xs px-5 py-4">
                        <div class="text-[11px] font-semibold text-slate-400">{{ $item->source_name }}</div>
                        <p class="mt-0.5 line-clamp-2 text-slate-800">{{ $item->title }}</p>
                        @if ($item->url)
                        <a href="{{ $item->url }}" target="_blank" class="mt-0.5 inline-flex items-center gap-1 text-[10px] text-[#1fa387] hover:underline">
                            <span class="material-symbols-outlined text-[11px]">open_in_new</span>Buka artikel
                        </a>
                        @endif
                    </td>
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-1">
                            <span class="material-symbols-outlined text-[22px] {{ $sentColor }}">{{ $sentIcon }}</span>
                            <span class="text-xs text-slate-500">{{ number_format($sentScore, 2) }}</span>
                        </div>
                    </td>
                    <td class="px-5 py-4">
                        @if ($ai)
                            @php $riskColors = ['high'=>'bg-red-100 text-red-700','medium'=>'bg-orange-100 text-orange-700','low'=>'bg-emerald-100 text-emerald-700'] @endphp
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold {{ $riskColors[$ai->risk_level] ?? 'bg-slate-100 text-slate-500' }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $ai->risk_level === 'high' ? 'bg-red-500' : ($ai->risk_level === 'medium' ? 'bg-orange-400' : 'bg-emerald-500') }}"></span>
                                {{ ucfirst($ai->risk_level) }}
                            </span>
                        @else
                            <span class="text-xs font-semibold text-amber-600">Belum dianalisis AI</span>
                        @endif
                    </td>
                    <td class="px-5 py-4 text-xs text-slate-500 whitespace-nowrap leading-relaxed">
                        <div class="flex flex-col">
                            <div>
                                <span class="font-semibold text-slate-700">Terbit:</span> 
                                {{ $item->published_at ? \Carbon\Carbon::parse($item->published_at)->format('d M Y H:i') : '—' }}
                            </div>
                            <div class="mt-1 text-[10px] text-slate-400">
                                <span class="font-medium text-slate-500">Scraping:</span> 
                                {{ $item->created_at ? \Carbon\Carbon::parse($item->created_at)->setTimezone(config('app.timezone', 'Asia/Makassar'))->format('d M Y H:i') : '—' }}
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-5 py-16 text-center text-slate-400">
                        <span class="material-symbols-outlined mb-2 block text-[48px] text-slate-200">feed</span>
                        Belum ada artikel yang masuk ke proyek
                        <p class="mt-1 text-xs">Gunakan filter AI untuk melihat artikel yang belum dianalisis.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @endif

        {{-- ===== TAB: SOSIAL MEDIA ===== --}}
        @if ($activeTab === 'social')
        <table class="w-full text-sm">
            <thead class="border-b border-slate-250 bg-[#FAFBFD]">
                <tr>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">No</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Proyek</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Platform</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Author</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Konten</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Engagement</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Waktu</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse ($items as $item)
                <tr class="group transition hover:bg-slate-50/60">
                    <td class="px-5 py-4 text-xs font-semibold text-slate-400">{{ $loop->iteration }}</td>
                    <td class="px-5 py-4">
                        <div class="flex flex-wrap gap-1">
                            @foreach($item->projects as $proj)
                                <span class="inline-flex items-center gap-1 rounded-lg bg-[#1fa387]/10 px-2.5 py-1 text-xs font-semibold text-[#1fa387]">
                                    <span class="material-symbols-outlined text-[13px]">folder</span>
                                    {{ Str::limit($proj->name, 20) }}
                                </span>
                            @endforeach
                            @if($item->projects->isEmpty())
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-5 py-4">
                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">
                            <span class="material-symbols-outlined text-[14px]">public</span>
                            {{ $item->platform ?? '—' }}
                        </span>
                    </td>
                    <td class="px-5 py-4">
                        <div class="font-medium text-slate-800">{{ $item->author_name ?? '—' }}</div>
                    </td>
                    <td class="max-w-xs px-5 py-4">
                        <p class="line-clamp-2 text-slate-700">{{ $item->content ?? '—' }}</p>
                        @if ($item->post_url)
                        <a href="{{ $item->post_url }}" target="_blank" class="mt-0.5 inline-flex items-center gap-1 text-xs text-[#1fa387] hover:underline">
                            <span class="material-symbols-outlined text-[12px]">open_in_new</span>Buka
                        </a>
                        @endif
                    </td>
                    <td class="px-5 py-4">
                        <div class="flex flex-wrap gap-2 text-xs text-slate-500">
                            @if ($item->like_count > 0)
                            <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[13px] text-red-400">favorite</span>{{ number_format($item->like_count) }}</span>
                            @endif
                            @if ($item->view_count > 0)
                            <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[13px] text-slate-400">visibility</span>{{ number_format($item->view_count) }}</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-5 py-4 text-xs text-slate-500 whitespace-nowrap leading-relaxed">
                        <div class="flex flex-col">
                            <div>
                                <span class="font-semibold text-slate-700">Publikasi:</span> 
                                {{ $item->posted_at ? \Carbon\Carbon::parse($item->posted_at)->setTimezone(config('app.timezone', 'Asia/Makassar'))->format('d M Y H:i') : '—' }}
                            </div>
                            <div class="mt-1 text-[10px] text-slate-400">
                                <span class="font-medium text-slate-500">Scraping:</span> 
                                {{ $item->created_at ? \Carbon\Carbon::parse($item->created_at)->setTimezone(config('app.timezone', 'Asia/Makassar'))->format('d M Y H:i') : '—' }}
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-5 py-16 text-center text-slate-400">
                        <span class="material-symbols-outlined mb-2 block text-[48px] text-slate-200">public</span>
                        Belum ada hasil scraping sosial media
                        <p class="mt-1 text-xs">Jalankan Apify Actor untuk mulai scraping</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @endif

        {{-- ===== TAB: ANALISIS AI ===== --}}
        @if ($activeTab === 'ai')
        <table class="w-full text-sm">
            <thead class="border-b border-slate-250 bg-[#FAFBFD]">
                <tr>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">No</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Proyek</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Sumber / Ringkasan</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Risiko</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Sentimen</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Status</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Waktu</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse ($items as $item)
                @php
                    $riskColors = ['high'=>'bg-red-100 text-red-700','medium'=>'bg-orange-100 text-orange-700','low'=>'bg-emerald-100 text-emerald-700'];
                    $riskColor = $riskColors[$item->risk_level] ?? 'bg-slate-100 text-slate-600';
                    $riskDot   = ['high'=>'bg-red-500','medium'=>'bg-orange-400','low'=>'bg-emerald-500'][$item->risk_level] ?? 'bg-slate-400';
                    $sentScore = $item->sentiment_score ?? 0;
                    $sentColor = $sentScore <= -0.4 ? 'text-red-500' : ($sentScore >= 0.3 ? 'text-emerald-600' : 'text-slate-400');
                    $sentIcon  = $sentScore <= -0.4 ? 'sentiment_very_dissatisfied' : ($sentScore >= 0.3 ? 'sentiment_satisfied' : 'sentiment_neutral');
                @endphp
                <tr class="group transition hover:bg-slate-50/60">
                    <td class="px-5 py-4 text-sm text-slate-500 font-medium">
                        {{ $items->firstItem() + $loop->index }}
                    </td>
                    <td class="px-5 py-4">
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
                    <td class="max-w-xs px-5 py-4">
                        @php
                            $sourceUrl = null;
                            if ($item->article) {
                                $sourceUrl = $item->article->url ?? $item->article->canonical_url ?? null;
                            } elseif ($item->socialMediaItem) {
                                $sourceUrl = $item->socialMediaItem->post_url ?? null;
                            }
                        @endphp
                        @if ($item->article)
                        <div class="text-[11px] font-semibold text-slate-400">
                            @if($sourceUrl)
                                <a href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer" class="hover:text-[#1fa387] transition">
                                    {{ $item->article->source_name }}
                                </a>
                            @else
                                {{ $item->article->source_name }}
                            @endif
                        </div>
                        <p class="mt-0.5 line-clamp-1 text-slate-800">
                            @if($sourceUrl)
                                <a href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer" class="hover:text-[#1fa387] transition">
                                    {{ $item->article->title }}
                                </a>
                            @else
                                {{ $item->article->title }}
                            @endif
                        </p>
                        @elseif ($item->socialMediaItem)
                        <div class="text-[11px] font-semibold text-indigo-400">
                            @if($sourceUrl)
                                <a href="{{ $sourceUrl }}" target="_blank" rel="noopener noreferrer" class="hover:text-[#1fa387] transition">
                                    {{ $item->socialMediaItem->platform }}
                                </a>
                            @else
                                {{ $item->socialMediaItem->platform }}
                            @endif
                        </div>
                        <p class="mt-0.5 line-clamp-1 text-slate-800">
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
                        <p class="mt-1 line-clamp-2 text-xs text-slate-500">{{ $item->summary }}</p>
                        @endif
                    </td>
                    <td class="px-5 py-4">
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold {{ $riskColor }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $riskDot }}"></span>
                            {{ ucfirst($item->risk_level ?? '—') }}
                        </span>
                    </td>
                    <td class="px-5 py-4">
                        <span class="material-symbols-outlined text-[22px] {{ $sentColor }}">{{ $sentIcon }}</span>
                        <div class="text-xs text-slate-500">{{ number_format($sentScore, 2) }}</div>
                    </td>
                    <td class="px-5 py-4">
                        @if ($item->analysis_status === 'success')
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Sukses
                        </span>
                        @elseif ($item->analysis_status === 'failed')
                        <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>Gagal
                        </span>
                        @else
                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                            {{ ucfirst($item->analysis_status ?? '—') }}
                        </span>
                        @endif
                    </td>
                    <td class="px-5 py-4 text-xs text-slate-500 whitespace-nowrap leading-relaxed">
                        @php
                            $scrapingTime = null;
                            if ($item->article) {
                                $scrapingTime = $item->article->created_at;
                            } elseif ($item->socialMediaItem) {
                                $scrapingTime = $item->socialMediaItem->created_at;
                            }
                        @endphp
                        <div class="flex flex-col">
                            <div>
                                <span class="font-semibold text-slate-700">Analisis:</span> 
                                {{ $item->created_at ? \Carbon\Carbon::parse($item->created_at)->setTimezone(config('app.timezone', 'Asia/Makassar'))->format('d M Y H:i') : '—' }}
                            </div>
                            <div class="mt-1 text-[10px] text-slate-400">
                                <span class="font-medium text-slate-500">Scraping:</span> 
                                {{ $scrapingTime ? \Carbon\Carbon::parse($scrapingTime)->setTimezone(config('app.timezone', 'Asia/Makassar'))->format('d M Y H:i') : '—' }}
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-5 py-16 text-center text-slate-400">
                        <span class="material-symbols-outlined mb-2 block text-[48px] text-slate-200">psychology</span>
                        Belum ada hasil analisis AI
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @endif

        {{-- ===== TAB: NOTIFIKASI ===== --}}
        @if ($activeTab === 'notifications')
        <table class="w-full text-sm">
            <thead class="border-b border-slate-250 bg-[#FAFBFD]">
                <tr>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Artikel Terkait</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Risiko</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Status Notif</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Pesan Error</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Waktu</th>
                    <th class="px-5 py-3.5 text-right text-[10px] font-bold uppercase tracking-wider text-slate-450">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse ($items as $item)
                <tr class="group transition hover:bg-slate-50/60">
                    <td class="max-w-xs px-5 py-4">
                        @if ($item->aiAnalysisResult?->article)
                        <div class="text-[11px] font-semibold text-slate-400">{{ $item->aiAnalysisResult->article->source_name }}</div>
                        <p class="mt-0.5 line-clamp-2 text-slate-800">{{ $item->aiAnalysisResult->article->title }}</p>
                        @else
                        <span class="text-xs text-slate-300">Analisis #{{ $item->ai_analysis_result_id }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-4">
                        @php $risk = $item->aiAnalysisResult->risk_level ?? null @endphp
                        @if ($risk)
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold
                            {{ $risk === 'high' ? 'bg-red-100 text-red-700' : ($risk === 'medium' ? 'bg-orange-100 text-orange-700' : 'bg-emerald-100 text-emerald-700') }}">
                            {{ ucfirst($risk) }}
                        </span>
                        @else
                        <span class="text-xs text-slate-300">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-4">
                        @if ($item->status === 'sent')
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                            <span class="material-symbols-outlined text-[14px]">send</span>Terkirim
                        </span>
                        @elseif ($item->status === 'failed')
                        <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700">
                            <span class="material-symbols-outlined text-[14px]">error</span>Gagal
                        </span>
                        @else
                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                            {{ ucfirst($item->status ?? '—') }}
                        </span>
                        @endif
                    </td>
                    <td class="max-w-xs px-5 py-4">
                        <p class="line-clamp-2 text-xs text-red-400">{{ $item->error_message ?? '—' }}</p>
                    </td>
                    <td class="px-5 py-4 text-xs text-slate-500">
                        {{ $item->created_at ? \Carbon\Carbon::parse($item->created_at)->setTimezone(config('app.timezone', 'Asia/Makassar'))->format('d M Y H:i') : 'Tanggal tidak tersedia' }}
                    </td>
                    <td class="px-5 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            @if ($item->status === 'failed')
                            <button wire:click="retryNotification({{ $item->id }})" class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-[#1fa387]/10 text-[#1fa387] hover:bg-[#1fa387]/20 transition" title="Kirim Ulang">
                                <span class="material-symbols-outlined text-[18px]">replay</span>
                            </button>
                            @endif
                            <button wire:click="deleteNotification({{ $item->id }})" onclick="confirm('Yakin ingin menghapus riwayat notifikasi ini?') || event.stopImmediatePropagation()" class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition" title="Hapus">
                                <span class="material-symbols-outlined text-[18px]">delete</span>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-5 py-16 text-center text-slate-400">
                        <span class="material-symbols-outlined mb-2 block text-[48px] text-slate-200">send</span>
                        Belum ada riwayat notifikasi Telegram
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @endif

        {{-- ===== TAB: ANTRIAN QUEUE ===== --}}
        @if ($activeTab === 'queue-pending')
        <div class="mb-4 flex items-center justify-between px-5 pt-4">
            <h3 class="text-sm font-semibold text-slate-800">Antrean Proses AI</h3>
            <button
                wire:click="clearAllPendingAiStates"
                wire:confirm="Yakin ingin menghapus seluruh antrean AI (termasuk yang retry)? Tindakan ini akan menghentikan proses AI untuk semua item yang belum diproses."
                class="flex items-center gap-1.5 rounded-lg bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-100"
            >
                <span class="material-symbols-outlined text-[16px]">delete_sweep</span>Bersihkan Semua Antrean
            </button>
        </div>
        <table class="w-full text-sm">
            <thead class="border-b border-slate-250 bg-[#FAFBFD]">
                <tr>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">No</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Tipe & Proyek</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Status</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Attempts</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Coba Lagi Pada</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse ($items as $item)
                <tr class="group transition hover:bg-slate-50/60">
                    <td class="px-5 py-4 text-xs font-semibold text-slate-400">{{ $loop->iteration }}</td>
                    <td class="px-5 py-4">
                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                            <span class="material-symbols-outlined text-[14px]">pending</span>{{ ucfirst($item->analyzable_type) }} ID: {{ $item->analyzable_id }}
                        </span>
                        @if($item->project)
                        <div class="mt-1 text-xs text-slate-500">Proyek: {{ $item->project->name }}</div>
                        @endif
                        <div class="mt-1 text-[10px] text-slate-400">
                            <span class="font-medium text-slate-500">Masuk Antrean:</span> 
                            {{ $item->created_at ? \Carbon\Carbon::parse($item->created_at)->setTimezone(config('app.timezone', 'Asia/Makassar'))->format('d M Y H:i') : '—' }}
                        </div>
                    </td>
                    <td class="px-5 py-4 text-xs text-slate-600">
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 font-semibold
                            {{ $item->status === 'queued' ? 'bg-blue-100 text-blue-700' : '' }}
                            {{ $item->status === 'retry_wait' ? 'bg-amber-100 text-amber-700' : '' }}
                            {{ $item->status === 'processing' ? 'bg-purple-100 text-purple-700' : '' }}
                        ">
                            {{ ucfirst(str_replace('_', ' ', $item->status)) }}
                        </span>
                        @if($item->last_error_code)
                            <div class="mt-1 text-[10px] text-red-500">{{ $item->last_error_code }}</div>
                        @endif
                    </td>
                    <td class="px-5 py-4 text-xs text-slate-600">{{ $item->attempts }}</td>
                    <td class="px-5 py-4 text-xs text-slate-500">{{ $item->next_retry_at ? $item->next_retry_at->format('d M H:i') : '—' }}</td>
                    <td class="px-5 py-4">
                        <div class="flex gap-2">
                            <button
                                wire:click="viewArticle('{{ $item->analyzable_type }}', {{ $item->analyzable_id }})"
                                class="flex items-center gap-1 rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-600 transition hover:bg-blue-100"
                                title="Lihat Isi Artikel"
                            >
                                <span class="material-symbols-outlined text-[14px]">visibility</span>
                            </button>
                            <button
                                wire:click="deleteAiState({{ $item->id }})"
                                wire:confirm="Hapus antrean ini?"
                                class="flex items-center gap-1 rounded-lg bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-100"
                            >
                                <span class="material-symbols-outlined text-[14px]">delete</span>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-5 py-16 text-center text-slate-400">
                        <span class="material-symbols-outlined mb-2 block text-[48px] text-slate-200">pending</span>
                        Tidak ada antrean proses AI
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @endif

        {{-- ===== TAB: QUEUE GAGAL ===== --}}
        @if ($activeTab === 'queue-failed')
        <table class="w-full text-sm">
            <thead class="border-b border-slate-250 bg-[#FAFBFD]">
                <tr>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">No</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Tipe & Proyek</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Error Code</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Error Message</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Gagal Pada</th>
                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-wider text-slate-450">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse ($items as $item)
                <tr class="group transition hover:bg-slate-50/60">
                    <td class="px-5 py-4 text-xs font-semibold text-slate-400">{{ $loop->iteration }}</td>
                    <td class="px-5 py-4">
                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">
                            <span class="material-symbols-outlined text-[14px]">report</span>{{ ucfirst($item->analyzable_type) }} ID: {{ $item->analyzable_id }}
                        </span>
                        @if($item->project)
                        <div class="mt-1 text-xs text-slate-500">Proyek: {{ $item->project->name }}</div>
                        @endif
                    </td>
                    <td class="px-5 py-4 text-xs font-semibold text-red-600">
                        <div>{{ $item->last_error_code ?? '—' }}</div>
                        @if($item->last_error_code)
                            <div class="mt-1 inline-flex rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-red-600">
                                {{ $this->failureCodeLabel($item->last_error_code) }}
                            </div>
                        @endif
                        @if($item->last_error_code)
                            <div class="mt-1 text-[10px] text-slate-500">
                                {{ $this->failureCodeDescription($item->last_error_code) }}
                            </div>
                        @endif
                    </td>
                    <td class="max-w-xs px-5 py-4">
                        <p class="line-clamp-3 text-xs text-red-500">{{ $item->error_message ?? '—' }}</p>
                    </td>
                    <td class="px-5 py-4 text-xs text-slate-500">
                        {{ $item->completed_at ? $item->completed_at->format('d M Y H:i') : ($item->updated_at ? $item->updated_at->format('d M Y H:i') : '—') }}
                    </td>
                    <td class="px-5 py-4">
                        <div class="flex gap-2">
                            <button
                                wire:click="viewArticle('{{ $item->analyzable_type }}', {{ $item->analyzable_id }})"
                                class="flex items-center gap-1 rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-600 transition hover:bg-blue-100"
                                title="Lihat Isi Artikel"
                            >
                                <span class="material-symbols-outlined text-[14px]">visibility</span>
                            </button>
                            @if($this->isRetryableFailure($item->last_error_code, $item->failure_category))
                            <button
                                wire:click="retryAiState({{ $item->id }})"
                                wire:confirm="Kembalikan ke antrean agar dicoba ulang?"
                                class="flex items-center gap-1 rounded-lg bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 transition hover:bg-amber-100"
                            >
                                <span class="material-symbols-outlined text-[14px]">refresh</span>Retry
                            </button>
                            @endif
                            <button
                                wire:click="deleteAiState({{ $item->id }})"
                                wire:confirm="Tutup atau sembunyikan report item ini?"
                                class="flex items-center gap-1 rounded-lg bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-100"
                                title="Tutup report item"
                            >
                                <span class="material-symbols-outlined text-[14px]">close</span>Tutup
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-5 py-16 text-center text-slate-400">
                        <span class="material-symbols-outlined mb-2 block text-[48px] text-slate-200">check_circle</span>
                        Tidak ada proses AI yang error / gagal permanen 🎉
                    </td>
                </tr>
        @endforelse
            </tbody>
        </table>

        @endif
    </div>

    {{-- Pagination --}}
    @if (method_exists($items, 'hasPages') && $items->hasPages())
    <div class="border-t border-slate-100 px-5 py-4">
        <div class="scale-[0.85] origin-right select-none w-full">
            {{ $items->onEachSide(1)->links('vendor.livewire.tailwind', data: ['scrollTo' => false]) }}
        </div>
    </div>
    @endif

    {{-- Modal Lihat Artikel --}}
    @if($showArticleModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
        <div class="flex h-full max-h-[90vh] w-full max-w-3xl flex-col rounded-2xl bg-white shadow-2xl">
            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h2 class="text-lg font-bold text-slate-800">{{ $viewingArticleTitle }}</h2>
                <button wire:click="closeArticleModal" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            {{-- Content --}}
            <div class="flex-1 overflow-y-auto px-6 py-6">
                <div class="prose prose-slate max-w-none text-sm text-slate-600">
                    {!! nl2br(e($viewingArticleContent)) !!}
                </div>
                @if(empty($viewingArticleContent))
                    <div class="text-center text-slate-400 italic">Konten kosong atau tidak ditemukan.</div>
                @endif
            </div>

            {{-- Footer --}}
            <div class="border-t border-slate-100 bg-slate-50 px-6 py-4 text-right">
                <button wire:click="closeArticleModal" class="rounded-lg bg-white px-5 py-2 text-sm font-semibold text-slate-600 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
                    Tutup
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
