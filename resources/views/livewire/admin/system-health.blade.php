<div class="space-y-6 text-left">
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

        <!-- Card 8: Laravel Reverb -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">WebSocket Server</span>
                    @php
                        $reverbColorClass = match($reverbStatus['color']) {
                            'green' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                            default => 'bg-rose-50 text-rose-700 border-rose-100'
                        };
                    @endphp
                    <span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-bold border {{ $reverbColorClass }}">
                        {{ $reverbStatus['status'] }}
                    </span>
                </div>
                <h3 class="text-sm font-black text-slate-900 mt-1">Laravel Reverb</h3>
                <div class="space-y-1 text-xs">
                    <div class="flex justify-between text-slate-500">
                        <span>Pesan Instan:</span>
                        <strong class="text-slate-800">Real-time Push</strong>
                    </div>
                    <div class="flex justify-between text-slate-500">
                        <span>Port Server:</span>
                        <strong class="text-slate-800">8080 (WS)</strong>
                    </div>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-1.5 text-[10px] font-bold {{ $reverbStatus['color'] == 'green' ? 'text-[#1fa387]' : 'text-rose-500' }} pt-2 border-t border-slate-100">
                <span class="material-symbols-outlined text-[13px]">{{ $reverbStatus['color'] == 'green' ? 'sensors' : 'sensors_off' }}</span>
                <span>{{ $reverbStatus['color'] == 'green' ? 'WebSocket Aktif & Siaga' : 'WebSocket Offline / Mati' }}</span>
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
</div>
