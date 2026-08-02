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

    <!-- Ringkasan per Platform -->
    @if(!$costSummary['has_data'])
        <div class="flex flex-col items-center justify-center py-16 text-center bg-white rounded-3xl border border-slate-200 shadow-sm">
            <span class="material-symbols-outlined text-[48px] text-slate-300 mb-3">receipt_long</span>
            <h3 class="text-sm font-bold text-slate-600">Belum ada data pengeluaran</h3>
            <p class="text-xs text-slate-400 mt-1.5 max-w-[320px] leading-relaxed">Data biaya aktual akan muncul secara otomatis setelah run scraping berikutnya selesai dijalankan.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 text-left">
            @foreach($costSummary['by_platform'] as $platform => $stat)
            @php
                $icon = match($platform) {
                    'Facebook'  => 'thumb_up',
                    'Instagram' => 'photo_camera',
                    'TikTok'    => 'music_video',
                    default     => 'language',
                };
                $colors = match($platform) {
                    'Facebook'  => ['bg-blue-50/70','border-blue-100','text-blue-700','text-blue-500'],
                    'Instagram' => ['bg-pink-50/70','border-pink-100','text-pink-700','text-pink-500'],
                    'TikTok'    => ['bg-slate-900','border-slate-800','text-white','text-slate-400'],
                    default     => ['bg-slate-50/70','border-slate-200','text-slate-700','text-slate-455'],
                };
            @endphp
            <div class="rounded-3xl border {{ $colors[1] }} {{ $colors[0] }} p-5 shadow-sm">
                <div class="flex items-center gap-2 mb-3">
                    <span class="material-symbols-outlined text-[16px] {{ $colors[3] }}">{{ $icon }}</span>
                    <span class="text-xs font-black uppercase tracking-wider {{ $colors[2] }}">{{ $platform }}</span>
                </div>
                <p class="text-2xl font-black {{ $colors[2] }}">${{ $stat['total_cost'] }}</p>
                <p class="text-xs {{ $colors[3] }} mt-1 font-semibold">{{ $stat['run_count'] }} run · rata ${{ $stat['avg_cost'] }}/run</p>
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
                            <td class="px-4 py-3 text-slate-500 text-center">{{ $run['items'] }}</td>
                            <td class="px-4 py-3 text-slate-400 text-center">{{ $run['duration'] }}</td>
                            <td class="px-4 py-3 text-slate-400 whitespace-nowrap">{{ $run['completed_at'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

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
