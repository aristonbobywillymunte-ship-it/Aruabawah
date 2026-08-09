<div>
    <div class="max-w-3xl mx-auto px-6 py-10">

        {{-- ── Header ── --}}
        <div class="mb-10">
            <a href="{{ route('home') }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#1fa387] hover:text-[#178a71] transition-colors mb-6 group">
                <span class="material-symbols-outlined text-[18px] group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
                Kembali ke Proyek
            </a>

            {{-- Step indicator --}}
            <div class="flex items-center gap-3 mb-5">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-black
                        {{ $createStep === 1 ? 'bg-[#1fa387] text-white' : 'bg-[#1fa387]/10 text-[#1fa387]' }}">
                        @if($createStep > 1)
                            <span class="material-symbols-outlined text-[14px]">check</span>
                        @else
                            1
                        @endif
                    </div>
                    <span class="text-xs font-bold {{ $createStep === 1 ? 'text-slate-800' : 'text-slate-400' }}">Pilih Paket</span>
                </div>
                <div class="h-px w-8 bg-slate-200"></div>
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-black
                        {{ $createStep === 2 ? 'bg-[#1fa387] text-white' : 'bg-slate-100 text-slate-400' }}">
                        2
                    </div>
                    <span class="text-xs font-bold {{ $createStep === 2 ? 'text-slate-800' : 'text-slate-400' }}">Konfigurasi Proyek</span>
                </div>
            </div>

            <h1 class="text-2xl font-hanken font-bold text-slate-900 tracking-tight">
                @if($createStep === 1) Pilih Paket Monitoring @else Konfigurasi Proyek @endif
            </h1>
            <p class="text-slate-500 text-sm mt-1.5">
                @if($createStep === 1)
                    Pilih paket yang sesuai dengan kebutuhan monitoring Anda.
                @else
                    Lengkapi detail proyek untuk memulai monitoring.
                @endif
            </p>
        </div>

        {{-- ── STEP 1: Pilih Paket ── --}}
        @if($createStep === 1)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 items-stretch">
                @foreach($packages as $p)
                    @php 
                        $isSelected = (int) $packageId === (int) $p->id; 
                        $isPremiumTheme = ($p->price > 0 && $packages->count() > 1) || str_contains(strtolower($p->name), 'enterprise') || str_contains(strtolower($p->name), 'vip');
                    @endphp

                    <div
                        wire:click="$set('packageId', {{ $p->id }})"
                        class="group relative cursor-pointer bg-white rounded-2xl md:rounded-3xl flex flex-col justify-between overflow-hidden transition-all duration-300
                            {{ $isSelected 
                                ? 'shadow-[0_0_0_2px_#1fa387] shadow-[#1fa387]/10 border-transparent' 
                                : 'border border-[#1fa387]/15 shadow-[0_2px_15px_-3px_rgba(0,0,0,0.04)] hover:shadow-xl hover:-translate-y-1' }}"
                    >
                        {{-- Top gradient accent bar --}}
                        @if($isPremiumTheme)
                            <div class="h-1.5 w-full bg-gradient-to-r from-[#1fa387] via-emerald-400 to-teal-500"></div>
                        @else
                            <div class="h-1.5 w-full bg-slate-200 group-hover:bg-slate-300 transition-colors"></div>
                        @endif

                        <div class="p-6 md:p-7 flex flex-col flex-1 {{ $isSelected ? 'bg-gradient-to-b from-[#1fa387]/[0.03] to-white' : '' }}">
                            
                            {{-- Top Row: Badges --}}
                            <div class="flex justify-between items-start mb-5">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    {{-- Terpopuler badge --}}
                                    @if($p->is_popular)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest bg-amber-400 text-white shadow-sm">
                                        ⭐ Terpopuler
                                    </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Plan Name & Price --}}
                            <div class="mb-5">
                                <p class="text-[12px] font-black uppercase tracking-[0.25em] {{ $isPremiumTheme ? 'text-[#1fa387]' : 'text-slate-500' }} mb-1.5">{{ $p->name }}</p>
                                <div class="flex items-end gap-1.5 mb-2">
                                    @if(($p->price ?? 0) > 0)
                                        <span class="text-3xl md:text-4xl font-black {{ $isPremiumTheme ? 'text-slate-700' : 'text-slate-800' }} leading-none">Rp {{ number_format($p->price, 0, ',', '.') }}</span>
                                        <span class="text-slate-400 text-xs font-semibold pb-1">/bulan</span>
                                    @else
                                        <span class="text-2xl md:text-3xl font-black {{ $isPremiumTheme ? 'text-slate-700' : 'text-[#1fa387]' }} leading-none">Hubungi Kami</span>
                                    @endif
                                </div>
                                <p class="text-slate-400 text-[11px] leading-relaxed">{{ $p->description ?: 'Solusi monitoring otomatis untuk bisnis Anda.' }}</p>
                                
                                {{-- Scraping Intervals & Limits Information --}}
                                <div class="mt-3 grid grid-cols-2 gap-2 bg-slate-50 rounded-xl p-2.5 border border-slate-100 text-[10px] text-slate-500 font-semibold">
                                    <div class="flex items-center gap-1.5" title="Batas maksimal proyek">
                                        <span class="material-symbols-outlined text-[14px] text-[#1fa387]">folder</span>
                                        <span>Maks. Proyek: {{ $p->max_projects ? $p->max_projects : 'Unlimited' }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5" title="Batas maksimal keyword per proyek">
                                        <span class="material-symbols-outlined text-[14px] text-[#1fa387]">key</span>
                                        <span>Keyword: {{ $p->max_keywords_per_project ? $p->max_keywords_per_project . ' / Proyek' : 'Unlimited' }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 mt-1 pt-1 border-t border-slate-200">
                                        <span class="material-symbols-outlined text-[14px] text-violet-500">newspaper</span>
                                        <span>Berita: {{ $p->news_interval_minutes ?? 5 }}m</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 mt-1 pt-1 border-t border-slate-200">
                                        <span class="material-symbols-outlined text-[14px] text-sky-500">share</span>
                                        <span>Sosmed: {{ $p->social_interval_minutes ?? 10 }}m</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Divider --}}
                            <div class="h-px bg-gradient-to-r from-transparent via-slate-200 to-transparent mb-4"></div>

                            {{-- Feature Categories (Exact Admin Design) --}}
                            <div class="space-y-4 flex-1 mb-6">
                                @php
                                    $hasData = !empty($p->advantages) || !empty($p->social_media_features) || !empty($p->news_portal_features);
                                @endphp

                                {{-- Fitur Sosial Media --}}
                                @php $socialList = $hasData ? ($p->social_media_features ?? []) : []; @endphp
                                @if(!empty($socialList))
                                <div>
                                    <div class="flex items-center gap-1.5 mb-2">
                                        <span class="material-symbols-outlined text-[11px] text-sky-500">share</span>
                                        <span class="text-[8px] font-black uppercase tracking-[0.15em] text-slate-400">Sosial Media & Keunggulan</span>
                                    </div>
                                    <div class="space-y-1.5">
                                        @foreach(array_slice($socialList, 0, 4) as $feat)
                                        <div class="flex items-start gap-2">
                                            <span class="w-4 h-4 rounded-md bg-sky-50 flex items-center justify-center shrink-0 border border-sky-100 mt-0.5">
                                                <span class="material-symbols-outlined text-[9px] text-sky-500">check</span>
                                            </span>
                                            <span class="text-[11px] text-slate-600 font-medium leading-tight break-words min-w-0">{{ $feat }}</span>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif

                                {{-- Fitur Portal Berita --}}
                                @php $newsList = $hasData ? ($p->news_portal_features ?? []) : []; @endphp
                                @if(!empty($newsList))
                                <div>
                                    <div class="flex items-center gap-1.5 mb-2">
                                        <span class="material-symbols-outlined text-[11px] text-violet-500">newspaper</span>
                                        <span class="text-[8px] font-black uppercase tracking-[0.15em] text-slate-400">Portal Berita</span>
                                    </div>
                                    <div class="space-y-1.5">
                                        @foreach(array_slice($newsList, 0, 3) as $feat)
                                        <div class="flex items-start gap-2">
                                            <span class="w-4 h-4 rounded-md bg-violet-50 flex items-center justify-center shrink-0 border border-violet-100 mt-0.5">
                                                <span class="material-symbols-outlined text-[9px] text-violet-500">check</span>
                                            </span>
                                            <span class="text-[11px] text-slate-600 font-medium leading-tight break-words min-w-0">{{ $feat }}</span>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif
                            </div>

                            {{-- CTA button --}}
                            <button type="button" class="mt-auto w-full py-2.5 rounded-xl font-bold text-sm transition-all duration-200 flex items-center justify-center gap-1.5
                                {{ $isSelected
                                    ? 'bg-[#1fa387] text-white shadow-sm shadow-[#1fa387]/20'
                                    : 'bg-[#1fa387]/10 text-[#1fa387] group-hover:bg-[#1fa387]/15' }}">
                                @if($isSelected)
                                    <span class="material-symbols-outlined text-[16px]">check_circle</span>
                                    Dipilih
                                @else
                                    <span class="material-symbols-outlined text-[16px]">radio_button_unchecked</span>
                                    Pilih Paket
                                @endif
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-8 flex flex-col sm:flex-row sm:items-center justify-between gap-6 pt-6 border-t border-slate-100">
                <!-- Inline Toast Notification -->
                <div class="inline-flex items-center gap-2.5 px-4 py-2.5 bg-blue-50 border border-blue-100 rounded-xl text-blue-700 shadow-sm">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span class="text-xs font-bold tracking-wide">Paket dapat diubah kapan saja setelah proyek dibuat.</span>
                </div>
                
                <button
                    type="button"
                    wire:click="$set('createStep', 2)"
                    @disabled(!$packageId)
                    class="inline-flex justify-center items-center gap-2 px-6 py-3 font-extrabold rounded-xl text-sm transition-all duration-200 min-w-[220px]
                        {{ $packageId
                            ? 'bg-[#1fa387] hover:bg-[#178a71] text-white shadow-sm cursor-pointer active:scale-[0.98] shadow-[#1fa387]/20 hover:shadow-[#1fa387]/40'
                            : 'bg-slate-100 text-slate-400 cursor-not-allowed' }}"
                >
                    <span wire:loading.remove wire:target="$set('createStep', 2)">Lanjut ke Pengaturan</span>
                    <span wire:loading wire:target="$set('createStep', 2)">Memuat...</span>
                    
                    <svg wire:loading wire:target="$set('createStep', 2)" class="animate-spin w-4 h-4 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="$set('createStep', 2)" class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </button>
            </div>

        {{-- ── STEP 2: Form Proyek ── --}}
        @else
            <div class="bg-white rounded-2xl shadow-xl shadow-slate-200/50">

                {{-- Selected package pill --}}
                @if($selectedPackage)
                    <div class="flex items-center justify-between px-6 py-4 bg-slate-50/60 rounded-t-2xl">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-[#1fa387]/10 flex items-center justify-center text-[#1fa387]">
                                <span class="material-symbols-outlined text-[16px]">inventory_2</span>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Paket</p>
                                <p class="text-sm font-bold text-slate-800 leading-tight">{{ $selectedPackage->name }}</p>
                            </div>
                        </div>
                        <button type="button" wire:click="$set('createStep', 1)"
                            class="text-xs font-bold text-[#1fa387] hover:text-[#178a71] inline-flex items-center gap-1 transition-colors">
                            <span class="material-symbols-outlined text-[14px]">edit</span>
                            Ubah
                        </button>
                    </div>
                @endif

                <form wire:submit.prevent="createProject" class="p-6 space-y-6">

                    {{-- Nama Proyek --}}
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-bold text-slate-800">Nama Proyek</label>
                            <span class="text-[10px] font-bold text-red-500 bg-red-50 px-2 py-0.5 rounded-full border border-red-100">Wajib</span>
                        </div>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">folder</span>
                            <input wire:model="name" type="text"
                                placeholder="Contoh: Monitoring Prabowo Subianto"
                                class="w-full pl-10 pr-4 py-3 text-sm bg-slate-50 shadow-inner shadow-slate-200/60 rounded-xl focus:outline-none focus:bg-white focus:shadow-none focus:ring-2 focus:ring-[#1fa387]/20 placeholder-slate-400 text-slate-800 transition-all font-medium">
                        </div>
                        @error('name') <p class="text-red-500 text-xs font-medium mt-1 flex items-center gap-1"><span class="material-symbols-outlined text-[13px]">error</span>{{ $message }}</p> @enderror
                    </div>

                    {{-- Telegram Chat ID --}}
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-bold text-slate-800">Telegram Chat ID</label>
                            <span class="text-[10px] font-bold text-red-500 bg-red-50 px-2 py-0.5 rounded-full border border-red-100">Wajib</span>
                        </div>
                        <p class="text-xs text-slate-500 leading-relaxed">ID chat/group Telegram tanpa tanda minus ( <code class="bg-slate-100 px-1 py-0.5 rounded text-slate-700 font-mono text-[10px]">-</code> ) di depan.</p>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">send</span>
                            <input wire:model="telegramChatId" type="text"
                                placeholder="Contoh: 10022334455"
                                class="w-full pl-10 pr-4 py-3 text-sm bg-slate-50 shadow-inner shadow-slate-200/60 rounded-xl focus:outline-none focus:bg-white focus:shadow-none focus:ring-2 focus:ring-[#1fa387]/20 placeholder-slate-400 text-slate-800 transition-all font-medium">
                        </div>
                        @error('telegramChatId') <p class="text-red-500 text-xs font-medium mt-1 flex items-center gap-1"><span class="material-symbols-outlined text-[13px]">error</span>{{ $message }}</p> @enderror
                    </div>

                    {{-- Kata Kunci Scraping --}}
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-bold text-slate-800">Kata Kunci Scraping</label>
                            <span class="text-[10px] font-bold text-red-500 bg-red-50 px-2 py-0.5 rounded-full border border-red-100">Wajib</span>
                        </div>
                        <p class="text-xs text-slate-500 leading-relaxed">Kata kunci utama yang digunakan sistem untuk mengambil data dari berita dan media sosial. Pisahkan dengan koma.</p>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                            <input wire:model="topicsString" type="text"
                                placeholder="Contoh: Prabowo, Presiden, Menhan"
                                class="w-full pl-10 pr-4 py-3 text-sm bg-slate-50 shadow-inner shadow-slate-200/60 rounded-xl focus:outline-none focus:bg-white focus:shadow-none focus:ring-2 focus:ring-[#1fa387]/20 placeholder-slate-400 text-slate-800 transition-all font-medium">
                        </div>
                        @error('topicsString') <p class="text-red-500 text-xs font-medium mt-1 flex items-center gap-1"><span class="material-symbols-outlined text-[13px]">error</span>{{ $message }}</p> @enderror

                        {{-- Preview Hashtag --}}
                        <div class="mt-2 p-3.5 rounded-xl bg-slate-50"
                             x-data="{
                                topics() {
                                    return $wire.topicsString
                                        ? $wire.topicsString.split(',').map(t => t.trim()).filter(Boolean) : [];
                                },
                                toHashtag(t) {
                                    const c = t.replace(/^#+/,'').replace(/['''`]/g,'').replace(/\s+/g,'');
                                    return c ? '#'+c : '';
                                }
                             }">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2.5">Preview</p>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="topic in topics()" :key="topic">
                                    <span class="px-3 py-1 rounded-full bg-[#1fa387]/8 text-[#1fa387] text-xs font-bold" x-text="toHashtag(topic)"></span>
                                </template>
                                <span x-show="!$wire.topicsString" class="text-xs text-slate-400 italic">Ketik kata kunci di atas...</span>
                            </div>
                        </div>
                    </div>

                    {{-- Kata Kunci Penyaring --}}
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-bold text-slate-800">Kata Kunci Penyaring</label>
                            <span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full border border-slate-200">Opsional</span>
                        </div>
                        <p class="text-xs text-slate-500 leading-relaxed">Persempit hasil dashboard hanya ke konten yang memuat kata kunci ini. Kosongkan untuk menampilkan semua hasil scraping.</p>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">filter_alt</span>
                            <input wire:model="contextKeywords" type="text"
                                placeholder="Contoh: Soekarno, Hatta, Sudirman"
                                class="w-full pl-10 pr-4 py-3 text-sm bg-slate-50 shadow-inner shadow-slate-200/60 rounded-xl focus:outline-none focus:bg-white focus:shadow-none focus:ring-2 focus:ring-[#1fa387]/20 placeholder-slate-400 text-slate-800 transition-all font-medium">
                        </div>
                        @error('contextKeywords') <p class="text-red-500 text-xs font-medium mt-1 flex items-center gap-1"><span class="material-symbols-outlined text-[13px]">error</span>{{ $message }}</p> @enderror
                    </div>

                    {{-- Kata Kunci Pengecualian --}}
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-bold text-slate-800">Kata Kunci Pengecualian</label>
                            <span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full border border-slate-200">Opsional</span>
                        </div>
                        <p class="text-xs text-slate-500 leading-relaxed">Artikel yang mengandung kata kunci ini tidak akan masuk ke database proyek.</p>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">block</span>
                            <input wire:model="excludeKeywords" type="text"
                                placeholder="Contoh: promosi, jual, beli, diskon"
                                class="w-full pl-10 pr-4 py-3 text-sm bg-slate-50 shadow-inner shadow-slate-200/60 rounded-xl focus:outline-none focus:bg-white focus:shadow-none focus:ring-2 focus:ring-[#1fa387]/20 placeholder-slate-400 text-slate-800 transition-all font-medium">
                        </div>
                        @error('excludeKeywords') <p class="text-red-500 text-xs font-medium mt-1 flex items-center gap-1"><span class="material-symbols-outlined text-[13px]">error</span>{{ $message }}</p> @enderror
                    </div>

                    {{-- Action buttons --}}
                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" wire:click="$set('createStep', 1)"
                            class="inline-flex items-center gap-1.5 px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-extrabold rounded-xl text-xs transition cursor-pointer active:scale-[0.98]">
                            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                            Kembali
                        </button>
                        <button type="submit"
                            class="inline-flex items-center justify-center gap-1.5 px-5 py-2.5 min-w-[140px] bg-[#1fa387] hover:bg-[#178a71] text-white font-extrabold rounded-xl text-xs transition shadow-sm cursor-pointer active:scale-[0.98] disabled:opacity-70 disabled:cursor-not-allowed"
                            wire:loading.attr="disabled"
                            wire:target="createProject">
                            <span wire:loading.remove wire:target="createProject" class="inline-flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px]">add_circle</span>
                                Buat Proyek
                            </span>
                            <span wire:loading.flex wire:target="createProject" class="items-center gap-1.5">
                                <svg class="animate-spin h-3.5 w-3.5 text-white shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Membuat...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>
