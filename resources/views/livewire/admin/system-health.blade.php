<div class="space-y-6 text-left">
    <!-- Error logs display if available (hanya tampil jika ada error) -->
    @if(count($latestErrors) > 0)
    <div class="rounded-2xl border border-rose-100 bg-rose-50/40 px-4 py-2.5 shadow-sm">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[15px] text-rose-500">error</span>
                <span class="text-[11px] font-bold text-rose-700">Log Error / Kegagalan Terkini</span>
                <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-rose-500 text-white text-[9px] font-bold ml-1">{{ count($latestErrors) }}</span>
            </div>
            <button wire:click="clearErrors" class="text-[10px] font-bold text-rose-400 hover:text-rose-600 px-2 py-0.5 border border-rose-200 hover:bg-rose-50 rounded-lg transition cursor-pointer">
                Bersihkan
            </button>
        </div>
        <div class="mt-2 space-y-1 max-h-24 overflow-y-auto">
            @foreach($latestErrors as $err)
                <div class="text-[10px] font-mono px-2.5 py-1.5 bg-white border border-rose-100 rounded-lg text-rose-600 leading-relaxed">
                    {{ $err }}
                </div>
            @endforeach
        </div>
    </div>
    @endif

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
                    <div class="flex justify-between text-slate-500 pt-0.5 items-center">
                        <span>Jumlah Antrean:</span>
                        <button type="button" wire:click="openApifyQueueModal" class="inline-flex items-center gap-1 font-bold text-xs hover:underline decoration-dashed select-none cursor-pointer focus:outline-none {{ $apifyStatus['queue_count'] > 0 ? 'text-[#1fa387]' : 'text-slate-800' }}">
                            <span>{{ $apifyStatus['queue_count'] }} run</span>
                            <span class="material-symbols-outlined text-[14px]">visibility</span>
                        </button>
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
                    <div class="flex justify-between items-center text-slate-500 pt-0.5">
                        <span>Antrean Redis:</span>
                        <button 
                            type="button"
                            wire:click="openRedisQueueModal"
                            class="inline-flex items-center gap-1 text-[11px] font-black text-[#1fa387] hover:underline cursor-pointer focus:outline-none"
                        >
                            <span>{{ $redisStatus['queue_count'] ?? 0 }} antrean</span>
                            <span class="material-symbols-outlined text-[13px]">visibility</span>
                        </button>
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


    <!-- Modal Detail Antrean AI (Sesuai Konsep Modal Admin Apify) -->
    @if($showQueueModal)
                <div wire:key="ai-queue-details-modal" x-data x-init="document.body.classList.add('overflow-hidden'); document.documentElement.classList.add('overflow-hidden'); return () => { document.body.classList.remove('overflow-hidden'); document.documentElement.classList.remove('overflow-hidden'); }" style="position: fixed; inset: 0px; z-index: 99999; display: flex; align-items: center; justify-content: center; background-color: rgba(15, 23, 42, 0.6); overscroll-behavior: none;" class="backdrop-blur-sm px-2 py-4 font-sans">
                <div class="w-11/12 max-w-7xl bg-white shadow-2xl text-left flex flex-col rounded-[24px] overflow-hidden max-h-[calc(100vh-24px)]" style="height: calc(100vh - 24px);">
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-3 shrink-0 bg-slate-50/50">
                    <div class="min-w-0 flex-1 pr-4 flex items-center gap-3">
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-wider text-[#1fa387]">Sistem Kesehatan AI</p>
                            <h2 class="text-sm font-black text-slate-900 leading-tight">Daftar Antrean Berjalan <span class="text-slate-400 font-semibold">(AI Pipeline)</span></h2>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 mr-4">
                        <button type="button" wire:click="openConfirmModal('clean_ghosts')" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-200 text-slate-600 rounded-lg hover:bg-slate-50 hover:text-slate-900 hover:border-slate-300 transition text-[11px] font-bold shadow-sm">
                            <span class="material-symbols-outlined text-[14px]">cleaning_services</span>
                            Bersihkan Data
                        </button>
                        <button type="button" wire:click="openConfirmModal('purge_queue')" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-rose-200 text-rose-600 rounded-lg hover:bg-rose-50 hover:text-rose-700 hover:border-rose-300 transition text-[11px] font-bold shadow-sm">
                            <span class="material-symbols-outlined text-[14px]">delete_sweep</span>
                            Kosongkan Redis
                        </button>
                    </div>
                    <button type="button" wire:click="closeQueueModal" class="rounded-full p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition cursor-pointer shrink-0">
                        <span class="material-symbols-outlined text-[18px] block">close</span>
                    </button>
                </div>

                @php
                    $queueItems = $this->getQueueData();
                    $activeProjects = DB::table('projects')->whereNull('deleted_at')->get();
                @endphp

                <!-- Panel Filter & Pencarian Standar (Statis, diletakkan di bawah header, tidak ikut ter-scroll) -->
                <div class="px-5 py-2.5 bg-slate-50/30 border-b border-slate-100 shrink-0">
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
                        <!-- Pencarian -->
                        <div class="col-span-2 md:col-span-1">
                            <div class="relative">
                                <input
                                    type="text"
                                    wire:model.live.debounce.350ms="searchQuery"
                                    placeholder="Cari kata kunci / error..."
                                    class="w-full pl-3 pr-3 py-1.5 text-xs border border-slate-200 rounded-lg focus:outline-none focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387]/30 transition bg-white"
                                >
                            </div>
                        </div>

                        <!-- Filter Status -->
                        <div>
                            <select
                                wire:model.live="filterStatus"
                                class="w-full px-2 py-1.5 text-xs border border-slate-200 rounded-lg bg-white focus:outline-none focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387]/30 transition"
                            >
                                <option value="">Semua Status</option>
                                <option value="queued">Mengantre</option>
                                <option value="processing">Diproses AI</option>
                                <option value="retry_wait">Tunda (Retry)</option>
                            </select>
                        </div>

                        <!-- Filter Tipe Media -->
                        <div>
                            <select
                                wire:model.live="filterType"
                                class="w-full px-2 py-1.5 text-xs border border-slate-200 rounded-lg bg-white focus:outline-none focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387]/30 transition"
                            >
                                <option value="">Semua Tipe</option>
                                <option value="article">Portal Berita</option>
                                <option value="social">Media Sosial</option>
                            </select>
                        </div>

                        <!-- Filter Aktor -->
                        <div>
                            <select
                                wire:model.live="filterActor"
                                class="w-full px-2 py-1.5 text-xs border border-slate-200 rounded-lg bg-white focus:outline-none focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387]/30 transition"
                            >
                                <option value="">Semua Aktor</option>
                                <option value="TikTok Post">TikTok Post</option>
                                <option value="Instagram Post">Instagram Post</option>
                                <option value="Facebook Post">Facebook Post</option>
                                <option value="TikTok Comment">TikTok Comment</option>
                                <option value="Instagram Comment">Instagram Comment</option>
                                <option value="Facebook Comment">Facebook Comment</option>
                                <option value="Portal Berita">Portal News</option>
                            </select>
                        </div>

                        <!-- Filter Proyek -->
                        <div>
                            <select
                                wire:model.live="filterProject"
                                class="w-full px-2 py-1.5 text-xs border border-slate-200 rounded-lg bg-white focus:outline-none focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387]/30 transition"
                            >
                                <option value="">Semua Proyek</option>
                                @foreach($activeProjects as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Modal Body: overflow-y-auto dengan max-height eksplisit agar footer TIDAK ikut scroll -->
                <div class="overflow-y-auto p-4 relative" style="flex: 1 1 0; min-height: 0; overscroll-behavior: contain;">
                    <!-- Loading Overlay seluruh body saat filter/search berubah -->
                    <div wire:loading wire:target="searchQuery, filterStatus, filterType, filterActor, filterProject, gotoPage"
                         class="absolute inset-0 z-20 bg-white/80 backdrop-blur-[2px] flex flex-col items-center justify-center gap-3 rounded-b-[24px]">
                        <svg class="animate-spin h-7 w-7 text-[#1fa387]" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-xs font-bold text-slate-500">Memuat data...</span>
                    </div>
                    <!-- Container Tabel -->
                    <div class="relative">

                        @if($queueItems->isEmpty())
                            <div class="flex flex-col items-center justify-center py-16 text-center">
                                <div class="w-16 h-16 rounded-full bg-slate-50 text-slate-400 flex items-center justify-center mb-4 border border-slate-200/50 shadow-sm">
                                    <span class="material-symbols-outlined text-[32px]">find_in_page</span>
                                </div>
                                <h4 class="text-sm font-bold text-slate-800 mb-1">Tidak Ada Hasil Cocok</h4>
                                <p class="text-xs text-slate-450 max-w-[280px] leading-relaxed">Sesuaikan kata kunci pencarian atau matikan filter Anda.</p>
                            </div>
                        @else
                            <div class="border border-slate-200 rounded-xl overflow-hidden shadow-sm bg-white mb-3">
                                <table class="w-full text-left border-collapse text-[11px] table-fixed">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-200">
                                        <th class="px-3 py-2 font-bold text-slate-500 w-10 text-center">#</th>
                                        <th class="px-3 py-2 font-bold text-slate-500 w-24">Tipe</th>
                                        <th class="px-3 py-2 font-bold text-slate-500 w-32">Tgl Konten</th>
                                        <th class="px-3 py-2 font-bold text-slate-500">Judul / Konten</th>
                                        <th class="px-3 py-2 font-bold text-slate-500 w-32">Proyek</th>
                                        <th class="px-3 py-2 font-bold text-slate-500 w-24">Status</th>
                                        <th class="px-3 py-2 font-bold text-slate-500 w-14 text-center">Retry</th>
                                        <th class="px-3 py-2 font-bold text-slate-500 w-32">Dibuat</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($queueItems as $idx => $item)
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
                                        <tr class="hover:bg-slate-50/40 transition">
                                            <td class="px-3 py-2 text-center text-slate-400 font-bold align-middle">
                                                {{ ($queueItems->currentPage() - 1) * $queueItems->perPage() + $idx + 1 }}
                                            </td>
                                            <td class="px-3 py-2 align-middle">
                                                <span class="inline-flex items-center gap-1 font-semibold text-slate-600">
                                                    @if(str_contains(strtolower($item['type']), 'sosial') || str_contains(strtolower($item['type']), 'social') || str_contains(strtolower($item['type']), 'media'))
                                                        <span class="w-1.5 h-1.5 rounded-full bg-purple-400 shrink-0"></span>
                                                    @else
                                                        <span class="w-1.5 h-1.5 rounded-full bg-blue-400 shrink-0"></span>
                                                    @endif
                                                    {{ $item['type'] }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-slate-500 align-middle whitespace-nowrap">{{ $item['content_date'] }}</td>
                                            <td class="px-3 py-2 text-slate-800 align-middle">
                                                @if(!empty($item['url']))
                                                    <a href="{{ $item['url'] }}" target="_blank" class="line-clamp-1 font-semibold text-blue-600 hover:text-blue-800 hover:underline inline-flex items-center gap-0.5" title="{{ $item['title'] }}">
                                                        <span class="truncate">{{ $item['title'] }}</span>
                                                        <span class="material-symbols-outlined text-[12px] shrink-0">open_in_new</span>
                                                    </a>
                                                @else
                                                    <div class="line-clamp-1 text-slate-700" title="{{ $item['title'] }}">{{ $item['title'] }}</div>
                                                @endif
                                                @if($item['status'] === 'retry_wait' && $item['error_message'])
                                                    <div class="text-[9px] text-rose-500 font-medium mt-0.5 truncate" title="{{ $item['error_message'] }}">
                                                        ⚠ {{ Str::limit($item['error_message'], 60) }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 font-semibold text-[#1fa387] align-middle truncate" title="{{ $item['project'] }}">
                                                {{ $item['project'] }}
                                            </td>
                                            <td class="px-3 py-2 align-middle whitespace-nowrap">
                                                <span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-bold border {{ $statusColor }}">
                                                    {{ $statusLabel }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-center font-bold text-slate-500 align-middle">
                                                <button type="button" wire:click="openConfirmModal('force_requeue', {{ $item['id'] }})" class="inline-flex items-center justify-center w-6 h-6 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-md transition" title="Kirim Ulang">
                                                    <span class="material-symbols-outlined text-[14px]">refresh</span>
                                                </button>
                                                <span class="block text-[8px] font-normal mt-0.5">{{ $item['attempts'] }}x</span>
                                            </td>
                                            <td class="px-3 py-2 text-slate-400 align-middle whitespace-nowrap">{{ $item['created_at'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                </div> <!-- Tutup Modal Body -->

                <!-- Modal Footer: SELALU FIX di bawah, tidak pernah ikut scroll -->
                <div class="px-5 py-2.5 bg-white border-t border-slate-100 shrink-0 shadow-[0_-4px_10px_-5px_rgba(0,0,0,0.05)] z-10 relative">
                    <div class="flex items-center justify-between text-[11px] font-bold text-slate-500">
                        <div>
                            Menampilkan <strong class="text-slate-800">{{ $queueItems->firstItem() ?? 0 }}-{{ $queueItems->lastItem() ?? 0 }}</strong> dari <strong class="text-slate-800">{{ $queueItems->total() }}</strong> antrean
                        </div>

                        @if($queueItems->hasPages())
                            <div class="flex items-center gap-1 bg-white border border-slate-200 rounded-xl p-0.5 shadow-sm">
                                {{-- First --}}
                                @if($queueItems->onFirstPage())
                                    <span class="px-2.5 py-1.5 text-slate-300 font-bold bg-slate-50/50 cursor-not-allowed select-none rounded-l-[10px]">First</span>
                                @else
                                    <button type="button" wire:click="gotoPage(1, 'queuePage')" class="px-2.5 py-1.5 text-slate-600 hover:bg-slate-50 font-bold rounded-l-[10px] transition">First</button>
                                @endif

                                {{-- Prev --}}
                                @if($queueItems->onFirstPage())
                                    <span class="px-2 py-1.5 text-slate-300 cursor-not-allowed select-none inline-flex items-center"><span class="material-symbols-outlined text-[14px]">chevron_left</span></span>
                                @else
                                    <button type="button" wire:click="previousPage('queuePage')" class="px-2 py-1.5 text-slate-600 hover:bg-slate-50 transition inline-flex items-center"><span class="material-symbols-outlined text-[14px]">chevron_left</span></button>
                                @endif

                                {{-- Page Numbers --}}
                                @php
                                    $startPage = max(1, $queueItems->currentPage() - 1);
                                    $endPage = min($queueItems->lastPage(), $queueItems->currentPage() + 1);
                                @endphp
                                @for($p = $startPage; $p <= $endPage; $p++)
                                    @if($p == $queueItems->currentPage())
                                        <span class="px-3 py-1.5 bg-[#1fa387] text-white font-black select-none">{{ $p }}</span>
                                    @else
                                        <button type="button" wire:click="gotoPage({{ $p }}, 'queuePage')" class="px-3 py-1.5 text-slate-600 hover:bg-slate-50 font-bold transition">{{ $p }}</button>
                                    @endif
                                @endfor

                                {{-- Next --}}
                                @if($queueItems->hasMorePages())
                                    <button type="button" wire:click="nextPage('queuePage')" class="px-2 py-1.5 text-slate-600 hover:bg-slate-50 transition inline-flex items-center"><span class="material-symbols-outlined text-[14px]">chevron_right</span></button>
                                @else
                                    <span class="px-2 py-1.5 text-slate-300 cursor-not-allowed select-none inline-flex items-center"><span class="material-symbols-outlined text-[14px]">chevron_right</span></span>
                                @endif

                                {{-- Last --}}
                                @if($queueItems->hasMorePages())
                                    <button type="button" wire:click="gotoPage({{ $queueItems->lastPage() }}, 'queuePage')" class="px-2.5 py-1.5 text-slate-600 hover:bg-slate-50 font-bold rounded-r-[10px] transition">Last</button>
                                @else
                                    <span class="px-2.5 py-1.5 text-slate-300 font-bold bg-slate-50/50 cursor-not-allowed select-none rounded-r-[10px]">Last</span>
                                @endif
                            </div>
                        @else
                            <div></div>
                        @endif
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end shrink-0 rounded-b-[24px]">
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

    <!-- Modal Konfirmasi Error Handling -->
    @if($showConfirmModal)
                <div style="position: fixed; inset: 0px; z-index: 99999; display: flex; align-items: center; justify-content: center; background-color: rgba(15, 23, 42, 0.6); overscroll-behavior: none;" class="backdrop-blur-sm px-4 font-sans">
                <div class="w-full max-w-sm bg-white shadow-2xl rounded-2xl overflow-hidden text-center p-6 border border-slate-200">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full {{ $confirmActionType === 'purge_queue' ? 'bg-rose-100 text-rose-600' : 'bg-[#1fa387]/10 text-[#1fa387]' }} mb-4">
                        <span class="material-symbols-outlined text-[24px]">
                            {{ $confirmActionType === 'purge_queue' ? 'warning' : ($confirmActionType === 'clean_ghosts' ? 'cleaning_services' : 'refresh') }}
                        </span>
                    </div>
                    <h3 class="text-lg font-black text-slate-900 mb-2">Konfirmasi Tindakan</h3>
                    <p class="text-xs text-slate-500 mb-6 leading-relaxed">
                        @if($confirmActionType === 'clean_ghosts')
                            Apakah Anda yakin ingin membersihkan data antrean hantu (Legacy MD5)? Data ini akan ditandai sebagai batal secara permanen.
                        @elseif($confirmActionType === 'purge_queue')
                            Apakah Anda yakin ingin <strong>menghapus secara paksa</strong> seluruh antrean Redis AI? Tindakan ini akan membatalkan semua job yang belum diproses.
                        @elseif($confirmActionType === 'force_requeue')
                            Apakah Anda yakin ingin mengirim ulang data antrean ini ke AI secara paksa sekarang juga?
                        @endif
                    </p>
                    <div class="flex flex-col gap-2">
                        <button type="button" wire:click="executeConfirmAction" class="w-full inline-flex justify-center items-center gap-2 px-4 py-2 text-sm font-bold text-white {{ $confirmActionType === 'purge_queue' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-[#1fa387] hover:bg-[#15876f]' }} rounded-xl transition shadow-sm" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="executeConfirmAction">Ya, Lanjutkan</span>
                            <span wire:loading wire:target="executeConfirmAction" class="flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Memproses...
                            </span>
                        </button>
                        <button type="button" wire:click="closeConfirmModal" class="w-full px-4 py-2 text-sm font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 rounded-xl transition">
                            Batal
                        </button>
                    </div>
                </div>
            @endif

    <!-- Modal Antrean Redis (Large Modal, Body Scrollable) -->
    @if($showRedisQueueModal)
                <div style="position: fixed; inset: 0px; z-index: 99999; display: flex; align-items: center; justify-content: center; padding: 1rem; background-color: rgba(15, 23, 42, 0.6); overscroll-behavior: none;" class="backdrop-blur-sm" x-data x-init="document.body.classList.add('overflow-hidden'); document.documentElement.classList.add('overflow-hidden'); return () => { document.body.classList.remove('overflow-hidden'); document.documentElement.classList.remove('overflow-hidden'); }">
                <div class="bg-white w-full max-w-[840px] rounded-[28px] overflow-hidden shadow-[0_30px_80px_rgba(15,23,42,0.18)] border border-slate-200 flex flex-col my-8 max-h-[calc(100vh-32px)]">
                    
                    <!-- Modal Header -->
                    <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between shrink-0">
                        <div class="text-left">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-0.5">REDIS SERVICE MANAGER</span>
                            <h3 class="text-base font-black text-slate-900 leading-none">Rincian Antrean Redis (Queued & Active Jobs)</h3>
                        </div>
                        <button type="button" wire:click="closeRedisQueueModal" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition cursor-pointer shrink-0">
                            <span class="material-symbols-outlined text-[20px] block">close</span>
                        </button>
                    </div>

                    <!-- Modal Body (Halaman Body yang di-scroll, Tinggi Tetap) -->
                    <div class="flex-1 overflow-y-auto p-6 relative" style="min-height: 0;">
                        @if(empty($redisQueueDetails))
                            <div class="flex flex-col items-center justify-center py-20 text-center">
                                <div class="w-16 h-16 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mb-4">
                                    <span class="material-symbols-outlined text-[32px]">check_circle</span>
                                </div>
                                <h4 class="text-sm font-bold text-slate-800 mb-1">Antrean Redis Kosong</h4>
                                <p class="text-xs text-slate-450 max-w-[280px] leading-relaxed">Seluruh pekerjaan background worker (scraping & analysis) saat ini kosong.</p>
                            </div>
                        @else
                            <div class="border border-slate-200 rounded-2xl overflow-hidden shadow-sm bg-white">
                                <table class="w-full text-left border-collapse text-xs table-fixed">
                                    <thead>
                                        <tr class="bg-slate-50 border-b border-slate-200">
                                            <th class="px-4 py-3 font-bold text-slate-700 w-12 text-center">No</th>
                                            <th class="px-4 py-3 font-bold text-slate-700 w-24">Saluran</th>
                                            <th class="px-4 py-3 font-bold text-slate-700 w-44">Nama Pekerjaan (Job)</th>
                                            <th class="px-4 py-3 font-bold text-slate-700">Target Payload</th>
                                            <th class="px-4 py-3 font-bold text-slate-700 w-36">Proyek</th>
                                            <th class="px-4 py-3 font-bold text-slate-700 w-32">Status</th>
                                            <th class="px-4 py-3 font-bold text-slate-700 w-16 text-center">Coba</th>
                                            <th class="px-4 py-3 font-bold text-slate-700 w-36">Waktu Masuk</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach($redisQueueDetails as $idx => $item)
                                            @php
                                                $statusColor = match($item['status']) {
                                                    'Mengantre' => 'bg-slate-50 text-slate-600 border-slate-200/80',
                                                    'Diproses Worker' => 'bg-emerald-50 text-emerald-700 border-emerald-100 animate-pulse',
                                                    default => 'bg-amber-50 text-amber-700 border-amber-100'
                                                };
                                            @endphp
                                            <tr class="hover:bg-slate-50/50 transition">
                                                <td class="px-4 py-3 text-center text-slate-450 font-bold align-top">{{ $idx + 1 }}</td>
                                                <td class="px-4 py-3 font-bold text-slate-600 align-top uppercase tracking-wider text-[10px]">{{ $item['queue'] }}</td>
                                                <td class="px-4 py-3 text-slate-800 align-top font-bold truncate" title="{{ $item['name'] }}">{{ $item['name'] }}</td>
                                                <td class="px-4 py-3 text-slate-500 align-top break-words font-medium">{{ $item['target'] }}</td>
                                                <td class="px-4 py-3 font-bold text-[#1fa387] align-top truncate" title="{{ $item['project'] ?? 'N/A' }}">
                                                    {{ $item['project'] ?? 'N/A' }}
                                                </td>
                                                <td class="px-4 py-3 align-top whitespace-nowrap">
                                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-bold border {{ $statusColor }}">
                                                        {{ $item['status'] }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-center font-bold text-slate-650 align-top">{{ $item['attempts'] }}x</td>
                                                <td class="px-4 py-3 text-slate-450 font-medium align-top whitespace-nowrap">{{ $item['created_at'] }}</td>
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
                            wire:click="closeRedisQueueModal"
                            class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 active:scale-[0.98] text-slate-600 font-bold rounded-xl text-xs transition duration-150 cursor-pointer shadow-sm"
                        >
                            Tutup
                        </button>
                    </div>

                </div>
            @endif

    <!-- Modal Antrean Apify (Large Modal, Body Scrollable, Tinggi Fix) -->
    @if($showApifyQueueModal)
                <div wire:key="apify-queue-details-modal" x-data x-init="document.body.classList.add('overflow-hidden'); document.documentElement.classList.add('overflow-hidden'); return () => { document.body.classList.remove('overflow-hidden'); document.documentElement.classList.remove('overflow-hidden'); }" style="position: fixed; inset: 0px; z-index: 99999; display: flex; align-items: center; justify-content: center; background-color: rgba(15, 23, 42, 0.6); overscroll-behavior: none;" class="backdrop-blur-sm px-4 py-6 font-sans">
                    <div class="w-11/12 max-w-7xl bg-white shadow-2xl text-left flex flex-col rounded-[24px] overflow-hidden max-h-[calc(100vh-48px)]" style="height: calc(100vh - 48px);">
                        <!-- Modal Header -->
                        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 shrink-0 bg-slate-50/50">
                            <div class="min-w-0 flex-1 pr-4">
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block mb-0.5">APIFY DISPATCH STATE</span>
                                <h2 class="text-base font-black text-slate-900 mt-0.5">Daftar Antrean Berjalan (Apify Pipeline)</h2>
                        <p class="text-[10px] text-slate-400 mt-0.5">Menampilkan status antrean perayapan media sosial yang sedang mengantre, diproses, atau ditunda.</p>
                    </div>
                    <button type="button" wire:click="closeApifyQueueModal" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition cursor-pointer shrink-0">
                        <span class="material-symbols-outlined text-[20px] block">close</span>
                    </button>
                </div>

                <!-- Modal Actions -->
                <div class="px-6 py-3 border-b border-slate-100 bg-white flex items-center justify-end gap-2">
                    <button type="button" 
                            wire:click="openConfirmModal('clean_apify_ghosts')"
                            class="px-3 py-1.5 text-xs font-bold text-amber-600 bg-amber-50 hover:bg-amber-100 rounded border border-amber-200 transition inline-flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">cleaning_services</span>
                        Bersihkan Data
                    </button>
                    <button type="button" 
                            wire:click="openConfirmModal('purge_apify_queue')"
                            class="px-3 py-1.5 text-xs font-bold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded border border-rose-200 transition inline-flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">delete_sweep</span>
                        Kosongkan Redis
                    </button>
                </div>

                <!-- Modal Body (Table dengan Tinggi Fix, Scrollable) -->
                <div class="flex-1 overflow-y-auto p-6 relative" style="height: 500px !important; max-height: 500px !important; overscroll-behavior: contain;">
                    @if(empty($apifyQueueDetails))
                        <div class="flex flex-col items-center justify-center py-20 text-center">
                            <div class="w-16 h-16 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mb-4">
                                <span class="material-symbols-outlined text-[32px]">check_circle</span>
                            </div>
                            <h4 class="text-sm font-bold text-slate-800 mb-1">Antrean Apify Kosong</h4>
                            <p class="text-xs text-slate-450 max-w-[280px] leading-relaxed">Seluruh proses antrean pengumpulan data sosial media telah selesai dikerjakan.</p>
                        </div>
                    @else
                        <div class="border border-slate-200 rounded-2xl overflow-hidden shadow-sm bg-white">
                            <table class="w-full text-left border-collapse text-xs table-fixed">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-200">
                                        <th class="px-4 py-3 font-bold text-slate-700 w-12 text-center">No</th>
                                        <th class="px-4 py-3 font-bold text-slate-700 w-24">Platform</th>
                                        <th class="px-4 py-3 font-bold text-slate-700 w-48">Nama Aktor</th>
                                        <th class="px-4 py-3 font-bold text-slate-700">Keyword / Target</th>
                                        <th class="px-4 py-3 font-bold text-slate-700 w-40">Proyek</th>
                                        <th class="px-4 py-3 font-bold text-slate-700 w-28">Status</th>
                                        <th class="px-4 py-3 font-bold text-slate-700 w-16 text-center">Coba</th>
                                        <th class="px-4 py-3 font-bold text-slate-700 w-36">Waktu Masuk</th>
                                        <th class="px-4 py-3 font-bold text-slate-700 w-24 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($apifyQueueDetails as $idx => $item)
                                        @php
                                            $statusColor = match($item['status']) {
                                                'queued' => 'bg-slate-50 text-slate-600 border-slate-200/80',
                                                'processing' => 'bg-cyan-50 text-cyan-700 border-cyan-100 animate-pulse',
                                                'retry_wait' => 'bg-amber-50 text-amber-700 border-amber-100',
                                                default => 'bg-rose-50 text-rose-700 border-rose-100'
                                            };
                                            $statusLabel = match($item['status']) {
                                                'queued' => 'Mengantre',
                                                'processing' => 'Diproses Apify',
                                                'retry_wait' => 'Tunda',
                                                default => 'Gagal'
                                            };
                                        @endphp
                                        <tr class="hover:bg-slate-50/50 transition">
                                            <td class="px-4 py-3 text-center text-slate-400 font-bold align-top">{{ $idx + 1 }}</td>
                                            <td class="px-4 py-3 font-bold text-slate-600 align-top whitespace-nowrap">{{ $item['platform'] }}</td>
                                            <td class="px-4 py-3 text-slate-800 font-bold align-top truncate" title="{{ $item['actor'] }}">{{ $item['actor'] }}</td>
                                            <td class="px-4 py-3 text-slate-500 align-top break-words font-medium" title="{{ $item['keyword'] }}">{{ $item['keyword'] }}</td>
                                            <td class="px-4 py-3 font-bold text-[#1fa387] align-top truncate" title="{{ $item['project'] }}">
                                                {{ $item['project'] }}
                                            </td>
                                            <td class="px-4 py-3 align-top whitespace-nowrap">
                                                <span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-bold border {{ $statusColor }}">
                                                    {{ $statusLabel }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-center font-bold text-slate-600 align-top">{{ $item['attempts'] }}x</td>
                                            <td class="px-4 py-3 text-slate-450 font-medium align-top whitespace-nowrap">{{ $item['queued_at'] }}</td>
                                            <td class="px-4 py-3 text-center align-top whitespace-nowrap">
                                                @if(in_array($item['status'], ['failed', 'retry_wait']))
                                                    <button type="button"
                                                            wire:click="openConfirmModal('force_apify_requeue', {{ $item['id'] }})"
                                                            wire:loading.attr="disabled"
                                                            wire:target="openConfirmModal('force_apify_requeue', {{ $item['id'] }})"
                                                            class="px-2 py-1 bg-blue-50 text-blue-600 hover:bg-blue-100 hover:text-blue-700 text-[10px] font-bold rounded flex items-center justify-center gap-1 mx-auto disabled:opacity-50 transition">
                                                        <span wire:loading.remove wire:target="openConfirmModal('force_apify_requeue', {{ $item['id'] }})" class="material-symbols-outlined text-[12px]">refresh</span>
                                                        <span wire:loading wire:target="openConfirmModal('force_apify_requeue', {{ $item['id'] }})" class="w-3 h-3 rounded-full border-2 border-blue-600 border-t-transparent animate-spin"></span>
                                                        Kirim Ulang
                                                    </button>
                                                @else
                                                    <span class="text-slate-300">-</span>
                                                @endif
                                            </td>
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
                        wire:click="closeApifyQueueModal"
                        class="px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 active:scale-[0.98] text-slate-600 font-bold rounded-xl text-xs transition duration-150 cursor-pointer shadow-sm"
                    >
                        Tutup
                    </button>
                </div>
                </div>
            @endif

    <style>
        /* ── Mobile Layout Optimization for System Health Audit Modal ── */
        @media (max-width: 1023px) {
            /* Mencegah tinggi modal melampaui layar mobile secara merusak */
            .fixed.inset-0.z-50.px-4.py-2 {
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
                padding-top: 0.5rem !important;
                padding-bottom: 0.5rem !important;
            }
            .w-11/12.max-w-7xl.bg-white,
            div[wire\:key="ai-queue-details-modal"] > div.bg-white,
            div[wire\:key="apify-queue-details-modal"] > div.bg-white {
                width: 100% !important;
                max-width: 100% !important;
                height: calc(100vh - 8px) !important;
                max-height: calc(100vh - 8px) !important;
                border-radius: 16px !important;
            }

            /* Header modal: susun tombol bersihkan/redis secara vertikal */
            div[wire\:key="ai-queue-details-modal"] .flex.items-center.justify-between.border-b.px-6.py-3 {
                flex-direction: column !important;
                align-items: stretch !important;
                padding: 1rem !important;
                gap: 0.75rem !important;
                position: relative !important;
            }
            div[wire\:key="ai-queue-details-modal"] .flex.items-center.justify-between.border-b.px-6.py-3 .flex-1.pr-4 {
                padding-right: 2rem !important; /* memberi ruang tombol close */
            }
            div[wire\:key="ai-queue-details-modal"] .flex.items-center.justify-between.border-b.px-6.py-3 .flex.items-center.gap-2 {
                flex-direction: row !important;
                width: 100% !important;
                margin-right: 0 !important;
                gap: 0.5rem !important;
            }
            div[wire\:key="ai-queue-details-modal"] .flex.items-center.justify-between.border-b.px-6.py-3 .flex.items-center.gap-2 button {
                flex: 1 !important;
                justify-content: center !important;
                height: 34px !important;
                font-size: 10px !important;
                padding: 4px 8px !important;
                border-radius: 8px !important;
            }
            /* Pindahkan tombol close ke kanan atas secara absolute */
            div[wire\:key="ai-queue-details-modal"] .flex.items-center.justify-between.border-b.px-6.py-3 button[wire\:click*="close"] {
                position: absolute !important;
                right: 0.75rem !important;
                top: 0.75rem !important;
            }

            /* Filter bar di bawah header */
            .px-5.py-2\.5.bg-slate-50\/30 {
                padding: 0.75rem !important;
            }
            .grid.grid-cols-2.md\:grid-cols-5.gap-2 {
                grid-template-columns: 1fr !important; /* satu kolom penuh */
                gap: 0.5rem !important;
            }
            .grid.grid-cols-2.md\:grid-cols-5.gap-2 > div {
                grid-column: span 1 / span 1 !important;
            }
            .grid.grid-cols-2.md\:grid-cols-5.gap-2 input,
            .grid.grid-cols-2.md\:grid-cols-5.gap-2 select {
                height: 34px !important;
                font-size: 11px !important;
                border-radius: 8px !important;
            }

            /* Sel data tabel mobile */
            table.w-full.border-collapse th {
                font-size: 8.5px !important;
                padding: 8px 10px !important;
            }
            table.w-full.border-collapse td {
                font-size: 11px !important;
                padding: 10px 10px !important;
            }
            table.w-full.border-collapse td span.inline-flex {
                font-size: 8.5px !important;
                padding: 1px 4px !important;
            }
            
            /* Modal Footer */
            .px-6.py-4.bg-slate-50 {
                padding: 0.75rem !important;
            }
            .px-6.py-4.bg-slate-50 button {
                width: 100% !important;
                height: 36px !important;
                border-radius: 10px !important;
                font-size: 11px !important;
            }
        }
        /* Mengunci bounce/scroll window belakang saat modal aktif */
        body.overflow-hidden {
            overflow: hidden !important;
        }
    </style>
</div>
