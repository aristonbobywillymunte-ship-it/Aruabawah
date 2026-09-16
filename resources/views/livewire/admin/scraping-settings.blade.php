<div class="mx-auto w-full max-w-7xl space-y-6 font-sans">
    <!-- Header Section -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <button
                wire:click="openEditModal"
                wire:loading.attr="disabled"
                wire:target="openEditModal"
                class="inline-flex h-10 items-center justify-center gap-1.5 rounded-2xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-5 text-xs font-bold transition shadow-sm cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
            >
                <span wire:loading.remove wire:target="openEditModal" class="material-symbols-outlined text-[18px]">settings</span>
                <span wire:loading wire:target="openEditModal" class="flex items-center justify-center">
                    <span class="material-symbols-outlined text-[18px] animate-spin text-white">progress_activity</span>
                </span>
                <span>Edit Konfigurasi</span>
            </button>
        </div>
    </div>

    <!-- Configurations Status Grid -->
    <div class="grid gap-6 md:grid-cols-3">
        <!-- Card 1: Global Switches -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between text-left">
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Global Switches</h2>
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $setting->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                        {{ $setting->is_active ? 'Master ON' : 'Master OFF' }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 leading-relaxed">Kontrol utama seluruh scraping otomatis. Switch di bawah ini disimpan persisten untuk tiap engine.</p>
            </div>
            
            <div class="mt-6 space-y-3 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <div class="flex items-center justify-between gap-3">
                    <div class="space-y-0.5">
                        <span class="text-[11px] font-bold text-slate-700">Master Scraping Otomatis</span>
                        <p class="text-[10px] text-slate-400">Kontrol utama seluruh scraping otomatis.</p>
                    </div>
                    <button
                        wire:click="toggleStatus"
                        wire:loading.attr="disabled"
                        wire:target="toggleStatus"
                        class="inline-flex h-8 items-center gap-1.5 rounded-xl border border-slate-200 hover:border-slate-300 bg-white text-slate-700 px-3.5 text-[11px] font-bold transition shadow-sm cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        <span wire:loading.remove wire:target="toggleStatus">{{ $setting->is_active ? 'ON' : 'OFF' }}</span>
                        <span wire:loading wire:target="toggleStatus" class="flex items-center justify-center">
                            <span class="material-symbols-outlined text-[14px] animate-spin text-slate-500">progress_activity</span>
                        </span>
                    </button>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <div class="space-y-0.5">
                        <span class="text-[11px] font-bold text-slate-700">Google News</span>
                        <p class="text-[10px] text-slate-400">Mengizinkan proses otomatis Google News.</p>
                    </div>
                    <span class="inline-flex h-8 items-center rounded-xl border border-slate-200 bg-white px-3.5 text-[11px] font-bold text-slate-700 shadow-sm">
                        {{ $setting->google_news_enabled ? 'ON' : 'OFF' }}
                    </span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <div class="space-y-0.5">
                        <span class="text-[11px] font-bold text-slate-700">Portal Manual</span>
                        <p class="text-[10px] text-slate-400">Mengizinkan crawling portal yang dikonfigurasi secara manual.</p>
                    </div>
                    <span class="inline-flex h-8 items-center rounded-xl border border-slate-200 bg-white px-3.5 text-[11px] font-bold text-slate-700 shadow-sm">
                        {{ $setting->manual_portal_enabled ? 'ON' : 'OFF' }}
                    </span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <div class="space-y-0.5">
                        <span class="text-[11px] font-bold text-slate-700">Apify / Sosial Media</span>
                        <p class="text-[10px] text-slate-400">Mengizinkan scraping sosial media melalui Apify.</p>
                    </div>
                    <span class="inline-flex h-8 items-center rounded-xl border border-slate-200 bg-white px-3.5 text-[11px] font-bold text-slate-700 shadow-sm">
                        {{ $setting->apify_enabled ? 'ON' : 'OFF' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Card 2: Discovery Intervals -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between text-left">
            <div>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-4">Interval Pencarian &amp; Perayapan</h2>
                <div class="space-y-4">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-2">
                        <span class="text-xs font-semibold text-slate-500">Google News Discovery</span>
                        <span class="text-xs font-bold text-slate-800">{{ $setting->google_news_interval }} menit</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs font-semibold text-slate-500">Portal Lokal (Manual)</span>
                        <span class="text-xs font-bold text-slate-800">{{ $setting->portal_crawling_interval }} menit</span>
                    </div>
                </div>
            </div>
            <p class="text-[10px] text-slate-400 mt-4 leading-relaxed">Scheduler akan memicu pencarian dan perayapan berita baru secara berkala sesuai waktu di atas.</p>
        </div>

        <!-- Card 3: Scraping Rules & Limits -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between text-left">
            <div>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-4">Aturan &amp; Limit Crawler</h2>
                <div class="space-y-3">
                    <div class="flex justify-between items-center border-b border-slate-100 pb-2">
                        <span class="text-xs font-semibold text-slate-500">Limit per Run</span>
                        <span class="text-xs font-bold text-slate-800">{{ $setting->limit_per_run }} artikel</span>
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
            Semua link berita yang ditemukan dari Google News maupun Portal Lokal (Manual) akan disaring terlebih dahulu ke dalam tabel <strong>Candidate Links</strong>.
            Setelah lolos seleksi kata kunci, tautan terpilih dipindahkan ke <strong>Scraping Items</strong> untuk diambil oleh <em>Scraper Worker</em> dengan limit maksimal <strong>{{ $setting->limit_per_run }}</strong> artikel per proses jalan.
        </p>
    </div>

    <!-- Edit Configuration Modal -->
    <div wire:key="scraping-settings-edit-modal"
         x-data="{ get open() { return $wire.showEditModal } }"
         x-show="open"
         x-cloak
         wire:click.self="$set('showEditModal', false)"
         x-init="
             $watch('open', val => {
                 if (val) {
                     document.body.style.overflow = 'hidden';
                     document.documentElement.style.overflow = 'hidden';
                 } else {
                     document.body.style.overflow = '';
                     document.documentElement.style.overflow = '';
                 }
             })
         "
         class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm px-4 py-6 font-sans">
        <div class="w-full max-w-xl bg-white shadow-2xl text-left flex flex-col rounded-[24px] overflow-hidden max-h-[90vh]">
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 flex-none bg-slate-50/50">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-[#1fa387]">Pengaturan Sistem</p>
                    <h2 class="text-base font-black text-slate-900 mt-0.5">Edit Parameter Scraping</h2>
                </div>
                <button type="button" wire:click="$set('showEditModal', false)" wire:loading.attr="disabled" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition cursor-pointer disabled:opacity-50">
                    <span class="material-symbols-outlined text-[20px] block">close</span>
                </button>
            </div>
            
            <form wire:submit.prevent="save" class="flex flex-col flex-1 overflow-hidden">
                <!-- Modal Body (Scrollable) -->
                <div class="p-6 space-y-5 overflow-y-auto flex-1 overscroll-contain">
                    <!-- Group 1: Discovery Intervals -->
                    <div>
                        <h3 class="text-[11px] font-black uppercase tracking-wider text-slate-400 mb-3">Interval Pencarian &amp; Perayapan</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-slate-700">Interval Google News (Menit) <span class="text-rose-500">*</span></label>
                                <input wire:model="google_news_interval" type="number" min="5" max="1440" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387]/20 transition">
                                <p class="mt-1 text-[10px] text-slate-400">Minimal 5 menit, maksimal 1440 menit (24 jam).</p>
                                @error('google_news_interval') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-slate-700">Interval Portal Lokal Manual (Menit) <span class="text-rose-500">*</span></label>
                                <input wire:model="portal_crawling_interval" type="number" min="5" max="1440" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387]/20 transition">
                                <p class="mt-1 text-[10px] text-slate-400">Jeda minimal crawling domain yang sama.</p>
                                @error('portal_crawling_interval') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Group 2: Scraping Limits & Resilience -->
                    <div class="pt-4 border-t border-slate-100">
                        <h3 class="text-[11px] font-black uppercase tracking-wider text-slate-400 mb-3">Aturan &amp; Limit Crawler</h3>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-slate-700">Limit per Run <span class="text-rose-500">*</span></label>
                                <input wire:model="limit_per_run" type="number" min="1" max="1000" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387]/20 transition">
                                @error('limit_per_run') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-slate-700">HTTP Timeout (Detik) <span class="text-rose-500">*</span></label>
                                <input wire:model="timeout_seconds" type="number" min="5" max="300" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387]/20 transition">
                                @error('timeout_seconds') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-slate-700">Batas Percobaan Ulang</label>
                                <input wire:model="retry_limit" type="number" min="0" max="10" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387]/20 transition">
                                @error('retry_limit') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="mb-1.5 block text-xs font-bold text-slate-700">Delay Retry (Menit)</label>
                            <input wire:model="retry_delay_minutes" type="number" min="1" max="180" class="h-10 w-full sm:w-1/2 rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387]/20 transition">
                            @error('retry_delay_minutes') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- Group 3: Engine Switches -->
                    <div class="pt-4 border-t border-slate-100 space-y-2.5">
                        <h3 class="text-[11px] font-black uppercase tracking-wider text-slate-400 mb-2">Aktivasi Engine &amp; Layanan</h3>
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-100 bg-slate-50/50 hover:bg-slate-50 transition cursor-pointer">
                            <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387]/20 w-4 h-4">
                            <div>
                                <span class="text-xs font-bold text-slate-800 block">Master Scraping Otomatis</span>
                                <p class="text-[10px] text-slate-400">Saklar global seluruh crawler latar belakang.</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-100 bg-slate-50/50 hover:bg-slate-50 transition cursor-pointer">
                            <input type="checkbox" wire:model="google_news_enabled" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387]/20 w-4 h-4">
                            <div>
                                <span class="text-xs font-bold text-slate-800 block">Google News</span>
                                <p class="text-[10px] text-slate-400">Mengizinkan pencarian artikel via Google News RSS.</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-100 bg-slate-50/50 hover:bg-slate-50 transition cursor-pointer">
                            <input type="checkbox" wire:model="manual_portal_enabled" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387]/20 w-4 h-4">
                            <div>
                                <span class="text-xs font-bold text-slate-800 block">Portal Manual</span>
                                <p class="text-[10px] text-slate-400">Mengizinkan perayapan domain terdaftar pada News Sources.</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-100 bg-slate-50/50 hover:bg-slate-50 transition cursor-pointer">
                            <input type="checkbox" wire:model="apify_enabled" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387]/20 w-4 h-4">
                            <div>
                                <span class="text-xs font-bold text-slate-800 block">Apify / Sosial Media</span>
                                <p class="text-[10px] text-slate-400">Mengizinkan scraping Facebook, Instagram, dan TikTok.</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-100 bg-slate-50/50 hover:bg-slate-50 transition cursor-pointer">
                            <input type="checkbox" wire:model="enable_realtime" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387]/20 w-4 h-4">
                            <div>
                                <span class="text-xs font-bold text-slate-800 block">Aktifkan Fitur Real-time (Laravel Reverb)</span>
                                <p class="text-[10px] text-slate-400">Menyiarkan update pipeline secara langsung via WebSocket.</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-slate-50/70 rounded-b-[24px] flex-none">
                    <button type="button" wire:click="$set('showEditModal', false)" wire:loading.attr="disabled" wire:target="save" class="h-10 rounded-xl border border-slate-200 bg-white px-5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer disabled:opacity-50">Batal</button>
                    <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        class="h-10 rounded-xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-6 text-xs font-bold transition cursor-pointer shadow-sm disabled:opacity-60 disabled:cursor-not-allowed inline-flex items-center gap-2">
                        <span wire:loading.remove wire:target="save">Simpan Perubahan</span>
                        <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                            <span class="material-symbols-outlined text-[16px] animate-spin text-white">progress_activity</span>
                            <span>Menyimpan...</span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
