<div class="w-full">
    <!-- Header Page -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6 text-left">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Laporan Keuangan</p>
            <h1 class="text-2xl font-hanken font-extrabold text-slate-800 mt-1">Ringkasan Biaya Aktual Apify</h1>
            <p class="text-xs text-slate-400 mt-1 font-medium">Biaya nyata yang dikenakan Apify setelah setiap proses scraping selesai (30 hari terakhir).</p>
        </div>
        <span class="inline-flex items-center gap-1.5 px-4 py-2 rounded-2xl bg-emerald-50 border border-emerald-100 text-xs font-black text-emerald-700 shadow-sm self-start md:self-auto">
            <span class="material-symbols-outlined text-[16px]">payments</span>
            Total Penggunaan: ${{ $costSummary['total_all'] ?? '0.0000' }}
        </span>
    </div>

    <!-- Filter Section per Proyek & Tanggal -->
    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm text-left mb-6 flex flex-col lg:flex-row lg:items-center justify-between gap-5">
        <div class="flex items-center gap-2 shrink-0">
            <span class="material-symbols-outlined text-[18px] text-slate-400">filter_alt</span>
            <span class="text-xs font-black text-slate-700 uppercase tracking-wider">Filter Laporan</span>
        </div>
        
        <div class="flex flex-col sm:flex-row items-center gap-3 w-full lg:justify-end">
            <!-- Proyek Dropdown -->
            <div class="w-full sm:w-60">
                <select wire:model.live="projectId" class="w-full bg-[#F8F9FA] border border-slate-300 focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387] rounded-xl px-4 py-2 text-xs font-bold text-slate-750 transition cursor-pointer">
                    <option value="">Semua Proyek (Tanpa Filter)</option>
                    @foreach($projects as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Date Range Inputs -->
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <input type="date" wire:model.live="startDate" class="bg-[#F8F9FA] border border-slate-300 focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387] rounded-xl px-3 py-2 text-xs font-bold text-slate-700 transition cursor-pointer" placeholder="Mulai Tanggal">
                <span class="text-xs text-slate-400 font-bold">s/d</span>
                <input type="date" wire:model.live="endDate" class="bg-[#F8F9FA] border border-slate-300 focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387] rounded-xl px-3 py-2 text-xs font-bold text-slate-700 transition cursor-pointer" placeholder="Sampai Tanggal">
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
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6 text-left">
            @foreach($costSummary['by_platform'] as $name => $stat)
            @php
                $platform = $stat['platform'];
                $type = $stat['type'];
                
                $icon = match($platform) {
                    'Facebook'  => 'thumb_up',
                    'Instagram' => 'photo_camera',
                    'TikTok'    => 'music_video',
                    default     => 'language',
                };
                $colors = match($platform) {
                    'Facebook'  => ['bg-blue-50/60','border-blue-100/70','text-blue-700','text-blue-500'],
                    'Instagram' => ['bg-pink-50/60','border-pink-100/70','text-pink-700','text-pink-500'],
                    'TikTok'    => ['bg-slate-900','border-slate-800','text-white','text-slate-400'],
                    default     => ['bg-slate-50/60','border-slate-200','text-slate-700','text-slate-400'],
                };
            @endphp
            <div class="rounded-3xl border {{ $colors[1] }} {{ $colors[0] }} p-5 shadow-sm">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] {{ $colors[3] }}">{{ $icon }}</span>
                        <span class="text-xs font-black uppercase tracking-wider {{ $colors[2] }}">{{ $platform }}</span>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest bg-white/70 border border-slate-200/50 text-slate-600 shadow-sm">
                        {{ $type }}
                    </span>
                </div>
                <p class="text-2xl font-black {{ $colors[2] }}">${{ $stat['total_cost'] }}</p>
                <p class="text-xs {{ $colors[3] }} mt-1.5 font-semibold">{{ $stat['run_count'] }} run · rata ${{ $stat['avg_cost'] }}/run</p>
            </div>
            @endforeach
        </div>

        <!-- Tabel Run Detail -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm text-left">
            <h3 class="text-sm font-black text-slate-800 mb-4">Detail Biaya Aktual per Run</h3>
            
            <div class="border border-slate-200 rounded-2xl overflow-hidden">
                <table class="w-full text-left text-xs">
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
                                        wire:click="openItems({{ $run['project_id'] ? $run['project_id'] : 'null' }}, '{{ $run['platform'] }}', '{{ addslashes($run['keyword']) }}')"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-[#1fa387]/10 hover:bg-[#1fa387]/20 text-[#1fa387] font-black tracking-wide transition active:scale-95 cursor-pointer shadow-sm text-[10px]"
                                    >
                                        <span class="material-symbols-outlined text-[13px] font-bold">visibility</span>
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
                <div x-data x-init="document.body.style.overflow = 'hidden'; document.documentElement.style.overflow = 'hidden'; return () => { document.body.style.overflow = ''; document.documentElement.style.overflow = ''; }" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm px-4 py-6 font-sans">
                    <div class="w-full max-w-4xl overflow-hidden rounded-[24px] bg-white shadow-2xl text-left overscroll-contain flex flex-col max-h-[85vh]">
                        
                        {{-- Modal Header --}}
                        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 shrink-0">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-wider text-[#1fa387]">Platform: {{ $selectedPlatform }}</p>
                                <h2 class="text-base font-black text-slate-900 mt-0.5">Hasil Perayapan (Scraped Items)</h2>
                            </div>
                            <button type="button" wire:click="closeItemsModal" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition cursor-pointer">
                                <span class="material-symbols-outlined text-[20px] block">close</span>
                            </button>
                        </div>

                        {{-- Modal Body (Scrollable & loading state) --}}
                        <div class="flex-1 overflow-y-auto min-h-[300px] p-6 relative">
                            
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
                                    <table class="w-full text-left text-xs border-collapse">
                                        <thead>
                                            <tr class="bg-slate-50 border-b border-slate-200">
                                                <th class="px-4 py-3 font-bold text-slate-600">Pembuat (Author)</th>
                                                <th class="px-4 py-3 font-bold text-slate-600">Konten/Isi Postingan</th>
                                                <th class="px-4 py-3 font-bold text-slate-600 text-center">Statistik</th>
                                                <th class="px-4 py-3 font-bold text-slate-600 text-center">Tanggal Post</th>
                                                <th class="px-4 py-3 font-bold text-slate-600 text-center">Tautan</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            @foreach($selectedItems as $item)
                                                <tr class="hover:bg-slate-50/40 transition-colors">
                                                    <td class="px-4 py-3 font-bold text-slate-800 whitespace-nowrap">{{ $item['author_name'] }}</td>
                                                    <td class="px-4 py-3 text-slate-600 leading-relaxed font-medium min-w-[280px]">{{ $item['content'] }}</td>
                                                    <td class="px-4 py-3 text-slate-500 whitespace-nowrap text-center leading-normal">
                                                        <div class="font-bold text-[#1fa387]">{{ $item['likes'] }} Likes</div>
                                                        <div class="text-[10px] text-slate-400 font-semibold">{{ $item['comments'] }} Komentar</div>
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
                            @endif
                        </div>

                        {{-- Modal Footer --}}
                        <div class="bg-slate-50 border-t border-slate-100 px-6 py-4 flex items-center justify-between shrink-0">
                            <span class="text-[10px] text-slate-400 font-semibold">Total: {{ count($selectedItems) }} postingan berhasil ditarik.</span>
                            <button type="button" wire:click="closeItemsModal" class="h-9 px-5 bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 rounded-xl text-xs font-bold transition active:scale-95 cursor-pointer shadow-sm">Tutup</button>
                        </div>
                    </div>
                </div>
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
