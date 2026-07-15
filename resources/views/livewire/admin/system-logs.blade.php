<div class="mx-auto w-full max-w-7xl space-y-6 font-sans">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-200 pb-5">
        <div class="text-left">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Panel Administrator</p>
            <h1 class="text-2xl font-black text-slate-900 mt-1">Log & Aktivitas Sistem</h1>
            <p class="text-xs text-slate-500 mt-1">Pantau catatan eksekusi perayapan portal berita, scraping Apify, dan error scheduler.</p>
        </div>

        <div class="flex items-center gap-3">
            <button 
                onclick="confirm('Apakah Anda yakin ingin mengosongkan seluruh log pada file ini? Tindakan ini tidak dapat dibatalkan.') || event.stopImmediatePropagation()"
                wire:click="clearLog" 
                class="inline-flex h-10 items-center justify-center gap-1.5 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white px-5 text-xs font-bold transition shadow-sm cursor-pointer"
            >
                <span class="material-symbols-outlined text-[18px]">delete_sweep</span>
                <span>Bersihkan Log</span>
            </button>
        </div>
    </div>

    <!-- Log Filters Panel -->
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm font-sans text-left">
        <div class="grid gap-4 lg:grid-cols-6 items-end">
            <div class="lg:col-span-2 text-left">
                <label class="mb-1.5 block text-xs font-bold text-slate-700">Jenis Log</label>
                <select wire:model.live="selectedFile" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                    @foreach($logFiles as $file => $label)
                        <option value="{{ $file }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="lg:col-span-1 text-left">
                <label class="mb-1.5 block text-xs font-bold text-slate-700">Status</label>
                <select wire:model.live="statusFilter" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="lg:col-span-1 text-left">
                <label class="mb-1.5 block text-xs font-bold text-slate-700">Sumber Log</label>
                <select wire:model.live="sourceFilter" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                    @foreach($sourceOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="lg:col-span-1 text-left">
                <label class="mb-1.5 block text-xs font-bold text-slate-700">Jumlah Baris</label>
                <select wire:model.live="maxLines" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                    <option value="50">50 Baris</option>
                    <option value="100">100 Baris</option>
                    <option value="200">200 Baris</option>
                    <option value="500">500 Baris</option>
                    <option value="1000">1000 Baris</option>
                </select>
            </div>
            <div class="lg:col-span-1 text-left">
                <button 
                    wire:click="$refresh" 
                    class="inline-flex h-10 w-full items-center justify-center gap-1.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold transition shadow-sm cursor-pointer"
                >
                    <span class="material-symbols-outlined text-[18px]">refresh</span>
                    <span>Refresh</span>
                </button>
            </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-4">
            <div class="relative text-left">
                <label class="mb-1.5 block text-xs font-bold text-slate-700">Pencarian Umum</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-400 text-[18px]">search</span>
                    <input 
                        wire:model.live.debounce.300ms="searchTerm" 
                        type="text" 
                        placeholder="Cari sumber, proyek, status, atau pesan..." 
                        class="h-10 w-full rounded-xl border border-slate-200 pr-4 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition"
                        style="padding-left: 2.5rem;"
                    />
                </div>
            </div>
            <div class="text-left">
                <label class="mb-1.5 block text-xs font-bold text-slate-700">Proyek Terkait</label>
                <select wire:model.live="projectFilter" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition bg-white">
                    <option value="all">Semua Proyek</option>
                    @foreach($projectOptions as $project)
                        <option value="{{ $project['id'] }}">{{ $project['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="text-left">
                <label class="mb-1.5 block text-xs font-bold text-slate-700">Kata Kunci Terkait</label>
                <input 
                    wire:model.live.debounce.300ms="keywordFilter"
                    type="text"
                    placeholder="gubernur kaltim, wagub, dll"
                    class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition"
                />
            </div>
            <div class="text-left">
                <label class="mb-1.5 block text-xs font-bold text-slate-700">Error Code</label>
                <input 
                    wire:model.live.debounce.300ms="errorCodeFilter"
                    type="text"
                    placeholder="401, 403, timeout..."
                    class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition"
                />
            </div>
        </div>
    </div>

    <!-- Terminal Output -->
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden text-left flex flex-col">
        <!-- Terminal Top Bar -->
        <div class="flex items-center justify-between bg-slate-50 px-5 py-3 border-b border-slate-200 select-none">
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-rose-400"></span>
                <span class="w-3 h-3 rounded-full bg-amber-400"></span>
                <span class="w-3 h-3 rounded-full bg-emerald-400"></span>
                <span class="text-[11px] font-mono font-bold text-slate-600 ml-2">{{ $logFiles[$selectedFile] ?? $selectedFile }}</span>
            </div>
            <div class="text-[10px] font-mono text-slate-400">LOG TERBARU DI ATAS</div>
        </div>

        <!-- Scrollable content -->
        <div class="p-0 max-h-[680px] overflow-y-auto bg-white">
            <table class="min-w-full table-fixed border-collapse">
                <thead class="sticky top-0 bg-white z-10 border-b border-slate-200">
                    <tr class="text-[10px] uppercase tracking-wider text-slate-400 font-bold">
                        <th class="w-12 px-4 py-3 text-left">No</th>
                        <th class="w-40 px-4 py-3 text-left">Waktu</th>
                        <th class="w-28 px-4 py-3 text-left">Sumber</th>
                        <th class="w-36 px-4 py-3 text-left">Proyek</th>
                        <th class="w-24 px-4 py-3 text-left">Status</th>
                        <th class="w-32 px-4 py-3 text-left">Error</th>
                        <th class="px-4 py-3 text-left">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($logs as $entry)
                        <tr class="align-top hover:bg-slate-50 transition">
                            <td class="px-4 py-3 text-xs font-semibold text-slate-400">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3 text-[11px] font-mono text-slate-500 whitespace-nowrap">
                                {{ $entry['timestamp_label'] ?? '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-xs font-bold text-slate-800">{{ $entry['source_label'] ?? '-' }}</div>
                                <div class="text-[10px] text-slate-400">{{ $entry['file_label'] ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-xs font-semibold text-slate-800">{{ $entry['project_label'] ?? '-' }}</div>
                                <div class="text-[10px] text-slate-400">{{ $entry['keyword_label'] ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $status = $entry['status_key'] ?? 'success';
                                    $statusClass = match($status) {
                                        'success' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/10',
                                        'failed' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-600/10',
                                        'retry' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/10',
                                        'started' => 'bg-sky-50 text-sky-700 ring-1 ring-sky-600/10',
                                        'processing' => 'bg-blue-50 text-blue-700 ring-1 ring-blue-600/10',
                                        'blocked' => 'bg-slate-100 text-slate-700 ring-1 ring-slate-600/10',
                                        default => 'bg-slate-100 text-slate-700 ring-1 ring-slate-600/10',
                                    };
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-bold {{ $statusClass }}">
                                    {{ $entry['status_label'] ?? '-' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-[11px] font-mono text-slate-500">
                                {{ $entry['error_code'] ?? '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-xs text-slate-800 leading-relaxed">
                                    {{ $entry['message'] ?? '-' }}
                                </div>
                                <div class="mt-1 text-[10px] text-slate-400 break-all">
                                    {{ $entry['raw_line'] ?? '' }}
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-400 italic">Tidak ada log untuk ditampilkan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
