<div class="space-y-6 text-left" wire:poll.5s>
    <!-- Grid Card Status Health -->
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <!-- Card 1: AI Provider Status -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">AI Engine</span>
                    @php
                        $colorClass = match($aiStatus['color']) {
                            'green' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                            'yellow' => 'bg-amber-50 text-amber-700 border-amber-100',
                            default => 'bg-rose-50 text-rose-700 border-rose-100'
                        };
                    @endphp
                    <span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-bold border {{ $colorClass }}">
                        {{ $aiStatus['status'] }}
                    </span>
                </div>
                <h3 class="text-sm font-black text-slate-900 mt-1">AI Provider</h3>
                <div class="space-y-1 text-xs">
                    <div class="flex justify-between text-slate-500">
                        <span>Aktif Utama:</span>
                        <strong class="text-slate-800">{{ $aiStatus['default'] }}</strong>
                    </div>
                    <div class="flex justify-between text-slate-500">
                        <span>Fallback:</span>
                        <strong class="text-slate-800">{{ $aiStatus['fallback'] }}</strong>
                    </div>
                    <div 
                        wire:click="openQueueModal" 
                        class="flex justify-between text-slate-500 hover:text-primary transition-all cursor-pointer group pt-0.5"
                        title="Klik untuk melihat detail antrean berjalan"
                    >
                        <span>Antrean Berjalan:</span>
                        <strong class="text-slate-800 {{ $aiStatus['queue_count'] > 0 ? 'text-amber-600 animate-pulse font-bold' : '' }} group-hover:underline flex items-center gap-0.5">
                            {{ $aiStatus['queue_count'] }} antrean
                            <span class="material-symbols-outlined text-[14px]">visibility</span>
                        </strong>
                    </div>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-1.5 text-[10px] font-bold text-[#1fa387] pt-2 border-t border-slate-100">
                <span class="material-symbols-outlined text-[13px]">check_circle</span>
                <span>Analisis Sentiment & Wawasan Ready</span>
            </div>
        </div>

        <!-- Card 2: Apify Scrapers -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Social Scrapers</span>
                    @php
                        $colorClass = match($apifyStatus['color']) {
                            'green' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                            'yellow' => 'bg-amber-50 text-amber-700 border-amber-100',
                            default => 'bg-rose-50 text-rose-700 border-rose-100'
                        };
                    @endphp
                    <span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-bold border {{ $colorClass }}">
                        {{ $apifyStatus['status'] }}
                    </span>
                </div>
                <h3 class="text-sm font-black text-slate-900 mt-1">Apify Settings</h3>
                <div class="space-y-1 text-xs">
                    <div class="flex justify-between text-slate-500">
                        <span>Aktor Aktif:</span>
                        <strong class="text-slate-800">{{ $apifyStatus['active_actors'] }}</strong>
                    </div>
                    <div class="flex justify-between text-slate-500">
                        <span>Token Akses:</span>
                        <strong class="text-slate-800">{{ $apifyStatus['token'] }}</strong>
                    </div>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-1.5 text-[10px] font-bold pt-2 border-t border-slate-100 {{ $apifyStatus['color'] === 'red' ? 'text-rose-600' : 'text-slate-500' }}">
                @if($apifyStatus['color'] === 'red')
                    <span class="material-symbols-outlined text-[13px] text-rose-500">warning</span>
                    <span>Scraper Bermasalah: {{ $apifyStatus['failed_message'] }}</span>
                @else
                    <span class="material-symbols-outlined text-[13px]">smart_toy</span>
                    <span>{{ $apifyStatus['inactive_actors'] }} aktor nonaktif</span>
                @endif
            </div>
        </div>

        <!-- Card 3: Scraping Queue -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Queue & Crawler</span>
                    @php
                        $colorClass = match($scrapingStatus['color']) {
                            'green' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                            'yellow' => 'bg-amber-50 text-amber-700 border-amber-100',
                            default => 'bg-rose-50 text-rose-700 border-rose-100'
                        };
                    @endphp
                    <span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-bold border {{ $colorClass }}">
                        {{ $scrapingStatus['status'] }}
                    </span>
                </div>
                <h3 class="text-sm font-black text-slate-900 mt-1">Scraping Queue</h3>
                <div class="space-y-1 text-xs">
                    <div class="flex justify-between text-slate-500">
                        <span>Pending Tasks:</span>
                        <strong class="text-slate-800">{{ $scrapingStatus['pending'] }} antrean</strong>
                    </div>
                    <div class="flex justify-between text-slate-500">
                        <span>Failed Tasks:</span>
                        <strong class="text-slate-800">{{ $scrapingStatus['failed'] }} gagal</strong>
                    </div>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-1.5 text-[10px] font-bold text-slate-500 pt-2 border-t border-slate-100">
                <span class="material-symbols-outlined text-[13px]">sync</span>
                <span>Worker Rayap Otomatis Ready</span>
            </div>
        </div>

        <!-- Card 4: Telegram Notification -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Crisis Alert</span>
                    @php
                        $colorClass = match($telegramStatus['color']) {
                            'green' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                            'yellow' => 'bg-amber-50 text-amber-700 border-amber-100',
                            default => 'bg-rose-50 text-rose-700 border-rose-100'
                        };
                    @endphp
                    <span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-bold border {{ $colorClass }}">
                        {{ $telegramStatus['status'] }}
                    </span>
                </div>
                <h3 class="text-sm font-black text-slate-900 mt-1">Telegram Bot</h3>
                <div class="space-y-1 text-xs">
                    <div class="flex justify-between text-slate-500">
                        <span>Status Bot:</span>
                        <strong class="text-slate-800">{{ $telegramStatus['active'] }}</strong>
                    </div>
                    <div class="flex justify-between text-slate-500">
                        <span>Kirim Terakhir:</span>
                        <strong class="text-slate-800">{{ $telegramStatus['last_sent'] }}</strong>
                    </div>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-1.5 text-[10px] font-bold text-[#1fa387] pt-2 border-t border-slate-100">
                <span class="material-symbols-outlined text-[13px]">send</span>
                <span>Bot Telegram Siaga Krisis</span>
            </div>
        </div>

        <!-- Card 5: PostgreSQL Database -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Database Utama</span>
                    @php
                        $colorClass = match($dbStatus['color']) {
                            'green' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                            default => 'bg-rose-50 text-rose-700 border-rose-100'
                        };
                    @endphp
                    <span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-bold border {{ $colorClass }}">
                        {{ $dbStatus['status'] }}
                    </span>
                </div>
                <h3 class="text-sm font-black text-slate-900 mt-1">PostgreSQL</h3>
                <div class="space-y-1 text-xs">
                    <div class="flex justify-between text-slate-500">
                        <span>Driver DB:</span>
                        <strong class="text-slate-800">{{ $dbStatus['connection'] }}</strong>
                    </div>
                    <div class="flex justify-between text-slate-500">
                        <span>Status Relasi:</span>
                        <strong class="text-slate-800">Tersambung</strong>
                    </div>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-1.5 text-[10px] font-bold text-[#1fa387] pt-2 border-t border-slate-100">
                <span class="material-symbols-outlined text-[13px]">database</span>
                <span>Repositori Artikel & Medsos OK</span>
            </div>
        </div>

        <!-- Card 6: Redis Service -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Queue Manager</span>
                    @php
                        $colorClass = match($redisStatus['color']) {
                            'green' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                            default => 'bg-amber-50 text-amber-700 border-amber-100'
                        };
                    @endphp
                    <span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-bold border {{ $colorClass }}">
                        {{ $redisStatus['status'] }}
                    </span>
                </div>
                <h3 class="text-sm font-black text-slate-900 mt-1">Redis Service</h3>
                <div class="space-y-1 text-xs">
                    <div class="flex justify-between text-slate-500">
                        <span>Driver Redis:</span>
                        <strong class="text-slate-800">{{ $redisStatus['connection'] }}</strong>
                    </div>
                    <div class="flex justify-between text-slate-500">
                        <span>Queue Status:</span>
                        <strong class="text-slate-800">Ready</strong>
                    </div>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-1.5 text-[10px] font-bold text-slate-500 pt-2 border-t border-slate-100">
                <span class="material-symbols-outlined text-[13px]">lock</span>
                <span>Redis Locks & Guards Active</span>
            </div>
        </div>
        <!-- Card 7: Scheduler / Cron Job -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Background Jobs</span>
                    @php
                        $colorClass = match($schedulerStatus['color']) {
                            'green' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                            default => 'bg-rose-50 text-rose-700 border-rose-100'
                        };
                    @endphp
                    <span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-bold border {{ $colorClass }}">
                        {{ $schedulerStatus['status'] }}
                    </span>
                </div>
                <h3 class="text-sm font-black text-slate-900 mt-1">Scheduler (Cron)</h3>
                <div class="space-y-1 text-xs">
                    <div class="flex justify-between text-slate-500">
                        <span>Otomatisasi:</span>
                        <strong class="text-slate-800">Scraping & Analysis</strong>
                    </div>
                    <div class="flex justify-between text-slate-500"
                         x-data="{ 
                             timestamp: {{ $schedulerStatus['timestamp'] ?? 'null' }}, 
                             diffText: '{{ $schedulerStatus['last_seen'] }}',
                             updateDiff() {
                                 if (!this.timestamp) {
                                     this.diffText = 'Never';
                                     return;
                                 }
                                 let diff = Math.floor(Date.now() / 1000) - this.timestamp;
                                 if (diff < 0) diff = 0;
                                 if (diff < 60) {
                                     this.diffText = diff + ' detik lalu';
                                 } else {
                                     let mins = Math.floor(diff / 60);
                                     this.diffText = mins + ' menit lalu';
                                 }
                             }
                         }"
                         x-init="
                             updateDiff();
                             setInterval(() => { updateDiff(); }, 1000);
                         "
                         :key="'heartbeat-' + {{ $schedulerStatus['timestamp'] ?? 0 }}"
                    >
                        <span>Heartbeat:</span>
                        <strong class="text-slate-800" x-text="diffText"></strong>
                    </div>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-1.5 text-[10px] font-bold {{ $schedulerStatus['color'] == 'green' ? 'text-[#1fa387]' : 'text-rose-500' }} pt-2 border-t border-slate-100">
                <span class="material-symbols-outlined text-[13px]">{{ $schedulerStatus['color'] == 'green' ? 'schedule' : 'warning' }}</span>
                <span>{{ $schedulerStatus['color'] == 'green' ? 'Otomatisasi Berjalan Normal' : 'Scheduler Berhenti/Mati!' }}</span>
            </div>
        </div>
    </div>

    <!-- Error logs display if available -->
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-sm font-bold text-slate-800 mb-3 flex items-center justify-between">
            <div class="flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[18px] text-rose-500">error</span>
                <span>Log Error / Kegagalan Terkini</span>
            </div>
            @if(count($latestErrors) > 0)
                <button wire:click="clearErrors" class="text-[10px] font-bold text-slate-400 hover:text-rose-600 px-2.5 py-1 border border-slate-200 hover:border-rose-100 hover:bg-rose-50/50 rounded-lg transition cursor-pointer">
                    Bersihkan Log
                </button>
            @endif
        </h3>
        @if(count($latestErrors) > 0)
            <div class="space-y-2 max-h-40 overflow-y-auto pr-1">
                @foreach($latestErrors as $err)
                    <div class="text-[11px] font-mono p-2.5 bg-rose-50 border border-rose-100 rounded-xl text-rose-700 leading-relaxed">
                        {{ $err }}
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-xs text-slate-400 italic">Tidak ada log error terkini. Seluruh sistem berjalan normal.</p>
        @endif
    </div>

    <!-- Modal Detail Antrean AI (Sesuai Konsep Modal Admin Apify) -->
    @if($showQueueModal)
        <div wire:key="ai-queue-details-modal" x-data x-init="document.body.style.overflow = 'hidden'; document.documentElement.style.overflow = 'hidden'; return () => { document.body.style.overflow = ''; document.documentElement.style.overflow = ''; }" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm px-4 py-6 font-sans">
            <div class="w-full max-w-2xl h-[450px] overflow-hidden rounded-[24px] bg-white shadow-2xl text-left overscroll-contain flex flex-col">
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 shrink-0 bg-slate-50/50">
                    <div class="min-w-0 flex-1 pr-4">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-[#1fa387]">Sistem Kesehatan AI</p>
                        <h2 class="text-base font-black text-slate-900 mt-0.5">Daftar Antrean Berjalan (AI Pipeline)</h2>
                        <p class="text-[10px] text-slate-400 mt-0.5">Menampilkan status antrean yang sedang diproses maupun menunggu dicoba ulang.</p>
                    </div>
                    <button type="button" wire:click="closeQueueModal" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition cursor-pointer shrink-0">
                        <span class="material-symbols-outlined text-[20px] block">close</span>
                    </button>
                </div>

                <!-- Modal Body (Table dengan Spinner Loading) -->
                <div class="flex-1 min-h-0 overflow-y-auto p-6 relative">
                    <!-- Loading overlay jika ada aksi di background -->
                    <div wire:loading wire:target="openQueueModal" class="absolute inset-0 bg-white/70 backdrop-blur-sm z-10 flex items-center justify-center">
                        <div class="flex flex-col items-center gap-3">
                            <svg class="animate-spin h-8 w-8 text-[#1fa387]" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-xs font-bold text-slate-600">Memuat data antrean...</span>
                        </div>
                    </div>

                    @if(empty($queueDetails))
                        <div class="flex flex-col items-center justify-center py-16 text-center">
                            <div class="w-16 h-16 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mb-4">
                                <span class="material-symbols-outlined text-[32px]">check_circle</span>
                            </div>
                            <h4 class="text-sm font-bold text-slate-800 mb-1">Seluruh Antrean Kosong</h4>
                            <p class="text-xs text-slate-450 max-w-[280px] leading-relaxed">Seluruh proses analisis sentimen AI telah selesai dikerjakan.</p>
                        </div>
                    @else
                        <div class="border border-slate-200 rounded-2xl overflow-hidden shadow-sm bg-white">
                            <table class="w-full text-left border-collapse text-xs table-fixed">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-200">
                                        <th class="px-3 py-3 font-bold text-slate-700 w-10 text-center">No</th>
                                        <th class="px-3 py-3 font-bold text-slate-700 w-44">Judul / Konten</th>
                                        <th class="px-3 py-3 font-bold text-slate-700 w-28">Proyek</th>
                                        <th class="px-3 py-3 font-bold text-slate-700 w-24">Status</th>
                                        <th class="px-3 py-3 font-bold text-slate-700 w-14 text-center">Retry</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($queueDetails as $idx => $item)
                                        @php
                                            $statusColor = match($item['status']) {
                                                'queued' => 'bg-slate-50 text-slate-600 border-slate-200/80',
                                                'processing' => 'bg-cyan-50 text-cyan-700 border-cyan-100 animate-pulse',
                                                'retry_wait' => 'bg-amber-50 text-amber-700 border-amber-100',
                                                default => 'bg-rose-50 text-rose-700 border-rose-100'
                                            };
                                            $statusLabel = match($item['status']) {
                                                'queued' => 'Mengantre',
                                                'processing' => 'Diproses AI',
                                                'retry_wait' => 'Tunda',
                                                default => 'Gagal'
                                            };
                                        @endphp
                                        <tr class="hover:bg-slate-50/50 transition">
                                            <td class="px-3 py-3 text-center text-slate-400 font-bold align-top">{{ $idx + 1 }}</td>
                                            <td class="px-3 py-3 text-slate-800 align-top">
                                                <div class="line-clamp-2 leading-relaxed" title="{{ $item['title'] }}">{{ $item['title'] }}</div>
                                                <div class="text-[9px] text-slate-400 font-medium mt-0.5">{{ $item['type'] }}</div>
                                                @if($item['status'] === 'retry_wait' && $item['error_message'])
                                                    <div class="text-[9px] text-rose-500 font-semibold mt-1 bg-rose-50/40 p-1 px-2 rounded-lg border border-rose-100/50 break-words whitespace-normal">
                                                        Error: {{ $item['error_message'] }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-3 py-3 font-bold text-[#1fa387] align-top truncate" title="{{ $item['project'] }}">
                                                {{ $item['project'] }}
                                            </td>
                                            <td class="px-3 py-3 align-top">
                                                <span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-bold border {{ $statusColor }}">
                                                    {{ $statusLabel }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-3 text-center font-bold text-slate-600 align-top">{{ $item['attempts'] }}x</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end shrink-0">
                    <button
                        type="button"
                        wire:click="closeQueueModal"
                        class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 active:scale-[0.98] text-slate-600 font-bold rounded-xl text-xs transition duration-150 cursor-pointer shadow-sm"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
