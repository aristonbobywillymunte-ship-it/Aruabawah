<div class="mx-auto w-full max-w-7xl space-y-6 font-sans">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-200 pb-5">
        <div class="text-left">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Panel Administrator</p>
            <h1 class="text-2xl font-black text-slate-900 mt-1">Pengaturan Scraping</h1>
            <p class="text-xs text-slate-500 mt-1">Atur parameter interval, limit crawler berita online, serta retry limit.</p>
        </div>

        <div class="flex items-center gap-3">
            <button 
                wire:click="openEditModal" 
                class="inline-flex h-10 items-center justify-center gap-1.5 rounded-2xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-5 text-xs font-bold transition shadow-sm cursor-pointer"
            >
                <span class="material-symbols-outlined text-[18px]">settings</span>
                <span>Edit Konfigurasi</span>
            </button>
        </div>
    </div>

    <!-- Configurations Status Grid -->
    <div class="grid gap-6 md:grid-cols-3">
        <!-- Card 1: System Status -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between text-left">
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Aktivitas Scraping</h2>
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $setting->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                        {{ $setting->is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 leading-relaxed">Saat dinonaktifkan, seluruh proses pencarian (*discovery*) dan pengambilan (*crawling*) berita akan ditangguhkan.</p>
            </div>
            
            <div class="mt-6 space-y-3 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="flex h-2 w-2 relative">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $setting->is_active ? 'bg-emerald-400' : 'bg-slate-300' }} opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 {{ $setting->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                        </span>
                        <span class="text-[11px] font-bold text-slate-600">Sistem Scraping</span>
                    </div>
                    <button 
                        wire:click="toggleStatus" 
                        class="inline-flex h-8 items-center gap-1.5 rounded-xl border border-slate-200 hover:border-slate-300 bg-white text-slate-700 px-3.5 text-[11px] font-bold transition shadow-sm cursor-pointer"
                    >
                        <span>{{ $setting->is_active ? 'Matikan' : 'Aktifkan' }}</span>
                    </button>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-slate-200/60 pt-2">
                    <div class="flex items-center gap-2">
                        <span class="flex h-2 w-2 relative">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $setting->enable_realtime ? 'bg-cyan-400' : 'bg-slate-300' }} opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 {{ $setting->enable_realtime ? 'bg-cyan-500' : 'bg-slate-400' }}"></span>
                        </span>
                        <span class="text-[11px] font-bold text-slate-600">Real-time (Reverb)</span>
                    </div>
                    <span class="text-[11px] font-bold {{ $setting->enable_realtime ? 'text-cyan-600' : 'text-slate-500' }}">
                        {{ $setting->enable_realtime ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Card 2: Discovery Intervals -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between text-left">
            <div>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-4">Interval Pencarian & Crawling</h2>
                <div class="space-y-4">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-2">
                        <span class="text-xs font-semibold text-slate-500">Google News Discovery</span>
                        <span class="text-xs font-bold text-slate-800">{{ $setting->google_news_interval }} menit</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs font-semibold text-slate-500">Portal Crawling</span>
                        <span class="text-xs font-bold text-slate-800">{{ $setting->portal_crawling_interval }} menit</span>
                    </div>
                </div>
            </div>
            <p class="text-[10px] text-slate-400 mt-4 leading-relaxed">Scheduler akan memicu pencarian berita baru secara berkala sesuai waktu di atas.</p>
        </div>

        <!-- Card 3: Scraping Rules & Limits -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between text-left">
            <div>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-4">Aturan & Limit Crawler</h2>
                <div class="space-y-3">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-2">
                        <span class="text-xs font-semibold text-slate-500">Limit per Run</span>
                        <span class="text-xs font-bold text-slate-800">{{ $setting->limit_per_run }} artikel</span>
                    </div>
                    <div class="flex justify-between items-center border-b border-slate-100 pb-2">
                        <span class="text-xs font-semibold text-slate-500">Rentang Waktu</span>
                        <span class="text-xs font-bold text-slate-800 uppercase">{{ $setting->date_range }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs font-semibold text-slate-500">HTTP Timeout</span>
                        <span class="text-xs font-bold text-slate-800">{{ $setting->timeout_seconds }} detik</span>
                    </div>
                </div>
            </div>
            <div class="mt-4 flex items-center justify-between text-[10px] text-slate-400 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                <span>Retry Limit: <strong>{{ $setting->retry_limit }}x</strong></span>
                <span>Delay: <strong>{{ $setting->retry_delay_minutes }} menit</strong></span>
            </div>
        </div>
    </div>

    <!-- Configuration Details Card -->
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden text-left p-6 space-y-4">
        <h2 class="text-sm font-bold text-slate-800">Catatan Konfigurasi Pipeline</h2>
        <p class="text-xs text-slate-500 leading-relaxed">
            Semua link berita yang ditemukan dari Google News maupun Portal Crawling akan disaring terlebih dahulu ke dalam tabel **Candidate Links**. 
            Setelah lolos seleksi kata kunci, tautan terpilih dipindahkan ke **Scraping Items** untuk diambil oleh *Scraper Worker* dengan limit maksimal **{{ $setting->limit_per_run }}** artikel per proses jalan.
        </p>
    </div>

    <!-- Edit Configuration Modal -->
    @if($showEditModal)
        <div x-data x-init="document.body.style.overflow = 'hidden'; document.documentElement.style.overflow = 'hidden'; return () => { document.body.style.overflow = ''; document.documentElement.style.overflow = ''; }" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm px-4 py-6">
            <div class="w-full max-w-lg overflow-hidden rounded-[24px] bg-white shadow-2xl text-left overscroll-contain">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-[#1fa387]">Pengaturan Sistem</p>
                        <h2 class="text-base font-black text-slate-900 mt-0.5">Edit Parameter Scraping</h2>
                    </div>
                    <button type="button" wire:click="$set('showEditModal', false)" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition cursor-pointer">
                        <span class="material-symbols-outlined text-[20px] block">close</span>
                    </button>
                </div>
                
                <form wire:submit.prevent="save" class="p-6 space-y-4 max-h-[75vh] overflow-y-auto pr-1">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-slate-700">Interval Google News (Menit)</label>
                            <input wire:model="google_news_interval" type="number" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                            @error('google_news_interval') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-slate-700">Interval Crawling Portal (Menit)</label>
                            <input wire:model="portal_crawling_interval" type="number" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                            @error('portal_crawling_interval') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-slate-700">Limit Artikel per Run</label>
                            <input wire:model="limit_per_run" type="number" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                            @error('limit_per_run') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-slate-700">Ambil Rentang Berita</label>
                            <select wire:model="date_range" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                                <option value="24h">24 Jam Terakhir (24h)</option>
                                <option value="7d">7 Hari Terakhir (7d)</option>
                                <option value="30d">30 Hari Terakhir (30d)</option>
                                <option value="90d">90 Hari Terakhir (90d)</option>
                            </select>
                            @error('date_range') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-slate-700">HTTP Timeout (Detik)</label>
                            <input wire:model="timeout_seconds" type="number" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                            @error('timeout_seconds') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-slate-700">Batas Percobaan Ulang</label>
                            <input wire:model="retry_limit" type="number" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                            @error('retry_limit') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-slate-700">Delay Retry (Menit)</label>
                            <input wire:model="retry_delay_minutes" type="number" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                            @error('retry_delay_minutes') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="space-y-2 pt-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387]/20 w-4 h-4">
                            <span class="text-xs font-bold text-slate-700">Jalankan Scheduler & Scraping Otomatis</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="enable_realtime" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387]/20 w-4 h-4">
                            <span class="text-xs font-bold text-slate-700">Aktifkan Fitur Real-time (Laravel Reverb)</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <button type="button" wire:click="$set('showEditModal', false)" class="h-10 rounded-xl border border-slate-200 px-5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer">Batal</button>
                        <button type="submit" class="h-10 rounded-xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-6 text-xs font-bold transition cursor-pointer">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
