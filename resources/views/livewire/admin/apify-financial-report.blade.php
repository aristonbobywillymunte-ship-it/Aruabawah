<div class="w-full">
    <!-- Header Page -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6 text-left">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Laporan Keuangan</p>
            <h1 class="text-2xl font-hanken font-extrabold text-slate-800 mt-1">Apify Billing & Usage</h1>
            <p class="text-xs text-slate-400 mt-1 font-medium">Pantau tagihan riil dan penggunaan saldo Apify dari setiap proses penarikan data selama 30 hari terakhir.</p>
        </div>
        <span class="inline-flex items-center gap-1.5 px-4 py-2 rounded-2xl bg-emerald-50 border border-emerald-100 text-xs font-black text-emerald-700 shadow-sm self-start md:self-auto">
            <span class="material-symbols-outlined text-[16px]">payments</span>
            Total Penggunaan: ${{ $costSummary['total_all'] ?? '0.0000' }}
        </span>
    </div>

    <!-- Filter Section per Proyek & Tanggal -->
    <div class="rounded-[20px] border border-slate-200 bg-white p-4 sm:p-5 shadow-sm text-left mb-6 flex flex-col xl:flex-row xl:items-center justify-between gap-5">
        <div class="flex items-center gap-2 shrink-0">
            <div class="w-8 h-8 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center">
                <span class="material-symbols-outlined text-[18px] text-slate-500">tune</span>
            </div>
            <span class="text-xs font-black text-slate-700 uppercase tracking-wider">Filter Laporan</span>
        </div>
        
        <div class="flex flex-col sm:flex-row items-center gap-3 w-full xl:justify-end">
            <!-- Proyek Dropdown -->
            <div class="w-full sm:w-64">
                <select wire:model.live="projectId" class="w-full bg-slate-50 hover:bg-slate-100 border border-slate-200 focus:bg-white focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/20 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-700 transition outline-none cursor-pointer shadow-sm appearance-none" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2394a3b8%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 1rem top 50%; background-size: 0.65rem auto;">
                    <option value="">Semua Proyek (Semua Data)</option>
                    @foreach($projects as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Date Range Inputs -->
            <div class="flex flex-row items-center gap-2 w-full sm:w-auto">
                <div class="relative w-full sm:w-auto">
                    <input type="date" wire:model.live="startDate" class="w-full bg-slate-50 hover:bg-slate-100 border border-slate-200 focus:bg-white focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/20 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-700 transition outline-none cursor-pointer shadow-sm" title="Tanggal Mulai">
                </div>
                <span class="text-[10px] text-slate-400 font-extrabold uppercase shrink-0 px-1">s/d</span>
                <div class="relative w-full sm:w-auto">
                    <input type="date" wire:model.live="endDate" class="w-full bg-slate-50 hover:bg-slate-100 border border-slate-200 focus:bg-white focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/20 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-700 transition outline-none cursor-pointer shadow-sm" title="Tanggal Selesai">
                </div>
            </div>
        </div>
    </div>

    <!-- Ringkasan per Platform -->
    @if(!$costSummary['has_data'])
        <div class="flex flex-col items-center justify-center py-16 text-center bg-white rounded-3xl border border-slate-200 shadow-sm">
            <span class="material-symbols-outlined text-[48px] text-slate-300 mb-3">receipt_long</span>
            <h3 class="text-sm font-bold text-slate-600">Belum ada data pengeluaran</h3>
            <p class="text-xs text-slate-400 mt-1.5 max-w-[320px] leading-relaxed">Data biaya aktual akan muncul secara otomatis setelah run scraping berikutnya selesai dijalankan.</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 mb-8 text-left">
            @foreach($costSummary['by_platform'] as $name => $stat)
            @php
                $platform = $stat['platform'];
                $type = $stat['type'];
                
                $icon = match($platform) {
                    'Facebook'  => '<svg viewBox="0 0 24 24" fill="currentColor" class="w-[18px] h-[18px]"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.469h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.469h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
                    'Instagram' => '<svg viewBox="0 0 24 24" fill="currentColor" class="w-[18px] h-[18px]"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.07M12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>',
                    'TikTok'    => '<svg viewBox="0 0 24 24" fill="currentColor" class="w-[18px] h-[18px]"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-5.2 1.74 2.89 2.89 0 012.31-4.64 2.93 2.93 0 01.88.13V9.4a6.84 6.84 0 00-1-.05A6.33 6.33 0 005 15.66a6.34 6.34 0 0010.86 4.43v-7a8.16 8.16 0 004.77 1.52v-3.4a4.85 4.85 0 01-1.04-.11z"/></svg>',
                    default     => '<span class="material-symbols-outlined text-[18px]">language</span>',
                };
                $colors = match($platform) {
                    'Facebook'  => ['bg-gradient-to-br from-[#f0f4f9] to-[#e4eaf4]','border-[#d2def0]','text-[#0866ff]','text-[#0866ff]/70', 'bg-[#0866ff]', 'text-white'],
                    'Instagram' => ['bg-gradient-to-br from-[#fef0f4] to-[#fce4ea]','border-[#f9d2dc]','text-[#d62976]','text-[#d62976]/70', 'bg-gradient-to-tr from-[#f09433] via-[#dc2743] to-[#bc1888]', 'text-white'],
                    'TikTok'    => ['bg-gradient-to-br from-slate-50 to-slate-100','border-slate-200','text-black','text-slate-500', 'bg-black', 'text-white'],
                    default     => ['bg-slate-50','border-slate-200','text-slate-700','text-slate-400', 'bg-slate-500', 'text-white'],
                };
            @endphp
            <div class="relative overflow-hidden rounded-[24px] border {{ $colors[1] }} {{ $colors[0] }} p-5 shadow-sm group hover:shadow-md transition-all duration-300 transform hover:-translate-y-1">
                <!-- Decorative blurred circle in the background -->
                <div class="absolute -right-8 -top-8 w-32 h-32 {{ $colors[4] }} opacity-[0.06] rounded-full blur-2xl group-hover:opacity-[0.1] transition-opacity duration-300 pointer-events-none"></div>
                
                <div class="relative z-10 flex items-start justify-between gap-2 mb-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-full {{ $colors[4] }} flex items-center justify-center {{ $colors[5] }} shadow-sm">
                            {!! $icon !!}
                        </div>
                        <span class="text-xs font-black uppercase tracking-wider {{ $colors[2] }}">{{ $platform }}</span>
                    </div>
                    <span class="px-2.5 py-1 rounded-lg text-[9px] font-bold uppercase tracking-widest bg-white border border-slate-200/80 text-slate-500 shadow-sm mt-0.5">
                        {{ $type }}
                    </span>
                </div>
                
                <div class="relative z-10">
                    <p class="text-[10px] font-extrabold uppercase tracking-widest {{ $colors[3] }} mb-0.5">Total Tagihan</p>
                    <p class="text-3xl font-black {{ $colors[2] }}">${{ $stat['total_cost'] }}</p>
                    
                    <div class="flex items-center gap-4 mt-4 pt-4 border-t border-slate-200/70">
                        <div class="flex-1">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Total Proses</p>
                            <p class="text-sm font-black text-slate-700">{{ $stat['run_count'] }} <span class="text-[10px] font-semibold text-slate-400">Run</span></p>
                        </div>
                        <div class="w-px h-7 bg-slate-200/80"></div>
                        <div class="flex-1 text-right">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Rata-rata</p>
                            <p class="text-sm font-black text-slate-700">${{ $stat['avg_cost'] }} <span class="text-[10px] font-semibold text-slate-400">/ Run</span></p>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Tabel Run Detail -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm text-left">
            <h3 class="text-sm font-black text-slate-800 mb-4">Rincian Penagihan per Proses (Run)</h3>
            
            <div class="border border-slate-200 rounded-2xl overflow-x-auto shadow-inner bg-slate-50/30">
                <table class="w-full min-w-[700px] text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200">
                            <th class="px-4 py-3 font-bold text-slate-600">Platform</th>
                            <th class="px-4 py-3 font-bold text-slate-600">Aktor</th>
                            <th class="px-4 py-3 font-bold text-slate-600">Proyek</th>
                            <th class="px-4 py-3 font-bold text-slate-600 text-right">Biaya (USD)</th>
                            <th class="px-4 py-3 font-bold text-slate-600 text-center">Item</th>
                            <th class="px-4 py-3 font-bold text-slate-600 text-center">Durasi</th>
                            <th class="px-4 py-3 font-bold text-slate-600">Selesai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($recentRuns as $run)
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-4 py-3 font-bold text-slate-700">{{ $run['platform'] }}</td>
                            <td class="px-4 py-3 text-slate-500 truncate max-w-[180px]">{{ $run['actor_name'] }}</td>
                            <td class="px-4 py-3 font-bold text-[#1fa387] truncate max-w-[140px]" title="{{ $run['project_name'] }}">{{ $run['project_name'] }}</td>
                            <td class="px-4 py-3 font-bold text-emerald-600 text-right">${{ $run['cost'] }}</td>
                            <td class="px-4 py-3 text-slate-500 text-center">
                                @if($run['items'] > 0)
                                    <button 
                                        type="button"
                                        wire:click="openItems({{ $run['project_id'] ? $run['project_id'] : 'null' }}, '{{ $run['platform'] }}', '{{ addslashes($run['keyword']) }}', '{{ $run['run_id'] }}', '{{ addslashes($run['project_name']) }}')"
                                        wire:loading.attr="disabled"
                                        wire:loading.class="opacity-50 cursor-not-allowed"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-[#1fa387]/10 hover:bg-[#1fa387]/20 text-[#1fa387] font-black tracking-wide transition active:scale-95 cursor-pointer shadow-sm text-[10px]"
                                    >
                                        <span wire:loading.remove wire:target="openItems({{ $run['project_id'] ? $run['project_id'] : 'null' }}, '{{ $run['platform'] }}', '{{ addslashes($run['keyword']) }}', '{{ $run['run_id'] }}', '{{ addslashes($run['project_name']) }}')" class="material-symbols-outlined text-[13px] font-bold">visibility</span>
                                        <svg wire:loading wire:target="openItems({{ $run['project_id'] ? $run['project_id'] : 'null' }}, '{{ $run['platform'] }}', '{{ addslashes($run['keyword']) }}', '{{ $run['run_id'] }}', '{{ addslashes($run['project_name']) }}')" class="animate-spin h-3 w-3 text-[#1fa387]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span>{{ $run['items'] }} Item</span>
                                    </button>
                                @else
                                    <span class="text-slate-400 font-semibold text-[10px] bg-slate-50 border border-slate-100 rounded-lg px-2 py-1 select-none">0 Item</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-400 text-center">{{ $run['duration'] }}</td>
                            <td class="px-4 py-3 text-slate-400 whitespace-nowrap">{{ $run['completed_at'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Modal Display Hasil Scraping --}}
            @if($showItemsModal)
            <template x-teleport="body">
                <div wire:key="apify-financial-items-modal" x-data x-init="document.body.classList.add('overflow-hidden'); document.documentElement.classList.add('overflow-hidden'); return () => { document.body.classList.remove('overflow-hidden'); document.documentElement.classList.remove('overflow-hidden'); }" style="position: fixed; inset: 0px; z-index: 99999; display: flex; align-items: center; justify-content: center; background-color: rgba(15, 23, 42, 0.6); overscroll-behavior: none;" class="backdrop-blur-sm px-4 py-6 font-sans" @touchmove.prevent @wheel.prevent>
                    <div class="w-11/12 sm:w-auto max-w-7xl w-full mx-4 sm:mx-auto bg-white shadow-2xl text-left flex flex-col rounded-[24px] overflow-hidden max-h-[95vh]" style="max-height: calc(100dvh - 40px);">
                        
                        {{-- Modal Header --}}
                        <div class="flex items-start sm:items-center justify-between border-b border-slate-100 px-4 sm:px-6 py-3 sm:py-4 shrink-0 bg-slate-50/50 gap-4">
                            <div class="min-w-0 flex-1">
                                <p class="text-[9px] font-bold uppercase tracking-wider text-[#1fa387] truncate">Platform: {{ $selectedPlatform }} <span class="hidden sm:inline">&nbsp;&bull;&nbsp; Proyek: {{ $selectedProjectName }} &nbsp;&bull;&nbsp; Run ID: {{ $selectedRunId }}</span></p>
                                <h2 class="text-sm font-black text-slate-900 mt-0.5 leading-tight">Hasil Pengambilan {{ $isCommentModal ? 'Komentar' : 'Postingan' }} <span class="text-slate-400 font-semibold block sm:inline">({{ $isCommentModal ? 'Scraped Comments' : 'Scraped Posts' }})</span></h2>
                            </div>
                            <button type="button" wire:click="closeItemsModal" class="rounded-full p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition cursor-pointer shrink-0 mt-[-4px] sm:mt-0">
                                <span class="material-symbols-outlined text-[18px] block">close</span>
                            </button>
                        </div>

                        {{-- Modal Body (Scrollable & loading state, fixed height) --}}
                        <div class="flex-1 overflow-y-auto p-4 sm:p-6 relative" style="min-height: 300px; overscroll-behavior: contain;" @touchmove.stop @wheel.stop>
                            
                            {{-- Loading State --}}
                            @if($modalLoading)
                                <div class="absolute inset-0 flex flex-col items-center justify-center bg-white/80 z-10">
                                    <svg class="animate-spin h-8 w-8 text-[#1fa387]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span class="text-xs font-bold text-slate-600 mt-3">Mengambil data hasil scraping...</span>
                                </div>
                            @endif

                            @if(empty($selectedItems) && !$modalLoading)
                                <div class="flex flex-col items-center justify-center py-16 text-slate-400">
                                    <span class="material-symbols-outlined text-[48px] text-slate-300">database_off</span>
                                    <p class="text-xs font-bold mt-2">Tidak ada data item tersimpan yang cocok dengan filter pencarian.</p>
                                    <p class="text-[10px] text-slate-400 mt-1 max-w-md text-center">Keyword: {{ $selectedKeyword }}</p>
                                </div>
                            @elseif(!$modalLoading)
                                <div class="border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
                                    <div class="overflow-x-auto">
                                        <table class="min-w-[800px] w-full text-left text-xs border-collapse">
                                        <thead>
                                            <tr class="bg-slate-50 border-b border-slate-200">
                                                <th class="px-4 py-3 font-bold text-slate-600">Pembuat ({{ $isCommentModal ? 'Komentator' : 'Author' }})</th>
                                                <th class="px-4 py-3 font-bold text-slate-600">{{ $isCommentModal ? 'Isi Komentar' : 'Konten/Isi Postingan' }}</th>
                                                <th class="px-4 py-3 font-bold text-slate-600 text-center">Statistik</th>
                                                <th class="px-4 py-3 font-bold text-slate-600 text-center">{{ $isCommentModal ? 'Waktu Komen' : 'Tanggal Post' }}</th>
                                                <th class="px-4 py-3 font-bold text-slate-600 text-center">Tautan</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            @foreach($selectedItems as $item)
                                                <tr class="hover:bg-slate-50/40 transition-colors">
                                                    <td class="px-4 py-3 font-bold text-slate-800 whitespace-nowrap">{{ $item['author_name'] }}</td>
                                                                                                        <td class="px-4 py-3 text-slate-600 leading-relaxed font-medium min-w-[280px]">
                                                        @if($isCommentModal && !empty($item['parent_author']))
                                                            <div class="mb-1 text-[10px] text-slate-400 font-semibold truncate bg-slate-50 border border-slate-100/80 rounded-md px-2 py-0.5 inline-block max-w-full">
                                                                Komentar di video &#64;{{ $item['parent_author'] }}: "{{ $item['parent_content'] }}"
                                                            </div>
                                                        @endif
                                                        <div>{{ $item['content'] }}</div>
                                                    </td>
                                                    <td class="px-4 py-3 text-slate-500 whitespace-nowrap text-center leading-normal">
                                                        <div class="font-bold text-[#1fa387]">{{ $item['likes'] }} Likes</div>
                                                        @if(!$isCommentModal)
                                                            <div class="text-[10px] text-slate-400 font-semibold">{{ $item['comments'] }} Komentar</div>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3 text-slate-400 whitespace-nowrap text-center font-semibold">{{ $item['posted_at'] }}</td>
                                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                                        @if(filter_var($item['post_url'], FILTER_VALIDATE_URL))
                                                            <a href="{{ $item['post_url'] }}" target="_blank" class="inline-flex items-center justify-center h-7 w-7 rounded-lg bg-slate-100 hover:bg-[#1fa387]/10 text-slate-500 hover:text-[#1fa387] transition shadow-sm active:scale-90" title="Buka Link Postingan">
                                                                <span class="material-symbols-outlined text-[16px] block">open_in_new</span>
                                                            </a>
                                                        @else
                                                            <span class="text-slate-350 italic text-[10px]">-</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Modal Footer --}}
                        <div class="bg-slate-50 border-t border-slate-100 px-6 py-4 flex items-center justify-between shrink-0">
                            <span class="text-[10px] text-slate-400 font-semibold">Total: {{ count($selectedItems) }} {{ $isCommentModal ? 'komentar' : 'postingan' }} berhasil ditarik.</span>
                            <button type="button" wire:click="closeItemsModal" class="h-9 px-5 bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 rounded-xl text-xs font-bold transition active:scale-95 cursor-pointer shadow-sm">Tutup</button>
                        </div>
                    </div>
                </div>
                </template>
@endif

            {{-- Pagination Links --}}
            @if($recentRuns->hasPages())
            <div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4 text-xs text-slate-500 font-sans">
                <div>
                    Menampilkan <span class="font-extrabold text-slate-700">{{ $recentRuns->firstItem() }}</span> sampai <span class="font-extrabold text-slate-700">{{ $recentRuns->lastItem() }}</span> dari <span class="font-extrabold text-slate-700">{{ $recentRuns->total() }}</span> riwayat run
                </div>
                <div class="flex items-center gap-1">
                    {{-- Previous Page Link --}}
                    @if($recentRuns->onFirstPage())
                        <span class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-slate-300 cursor-not-allowed text-[11px] font-bold select-none">Sebelumnya</span>
                    @else
                        <button type="button" wire:click="previousPage('page')" wire:loading.attr="disabled" class="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-[11px] font-bold transition shadow-sm active:scale-95">Sebelumnya</button>
                    @endif

                    {{-- Next Page Link --}}
                    @if($recentRuns->hasMorePages())
                        <button type="button" wire:click="nextPage('page')" wire:loading.attr="disabled" class="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-[11px] font-bold transition shadow-sm active:scale-95">Berikutnya</button>
                    @else
                        <span class="px-2.5 py-1.5 rounded-lg border border-slate-200 text-slate-300 cursor-not-allowed text-[11px] font-bold select-none">Berikutnya</span>
                    @endif
                </div>
            </div>
            @endif
        </div>
    @endif
</div>
