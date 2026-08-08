<div>
    <div class="max-w-[1400px] mx-auto px-6 py-10">

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
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 max-w-3xl items-stretch">
                @foreach($packages as $p)
                    @php $isSelected = (int) $packageId === (int) $p->id; @endphp

                    <div
                        wire:click="$set('packageId', {{ $p->id }})"
                        class="group relative cursor-pointer rounded-2xl transition-all duration-200 flex flex-col overflow-hidden
                            {{ $isSelected
                                ? 'shadow-[0_0_0_2px_#1fa387] shadow-[#1fa387]/10'
                                : 'shadow-[0_2px_12px_rgba(0,0,0,0.06)] hover:shadow-[0_4px_24px_rgba(0,0,0,0.10)]' }}"
                    >
                        {{-- Header strip: popular = hijau, standar = transparan tapi sama tinggi --}}
                        @if($p->is_popular)
                            <div class="bg-[#1fa387] px-5 py-2.5 flex items-center gap-2">
                                <span class="material-symbols-outlined text-white text-[14px]">star</span>
                                <span class="text-white text-[11px] font-black uppercase tracking-[0.12em]">Paling Populer</span>
                            </div>
                        @else
                            <div class="px-5 py-2.5" aria-hidden="true" style="visibility:hidden">
                                <span class="text-[11px] font-black uppercase tracking-[0.12em]">_</span>
                            </div>
                        @endif

                        {{-- Card body --}}
                        <div class="p-6 flex flex-col gap-5 flex-1 {{ $isSelected ? 'bg-gradient-to-b from-[#1fa387]/[0.03] to-white' : 'bg-white' }}">

                            {{-- Package identity --}}
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 transition-all duration-200
                                    {{ $isSelected ? 'bg-[#1fa387] text-white' : 'bg-slate-100 text-slate-500 group-hover:bg-[#1fa387]/10 group-hover:text-[#1fa387]' }}">
                                    <span class="material-symbols-outlined text-[22px]">
                                        {{ $p->name === 'Enterprise' ? 'rocket_launch' : 'widgets' }}
                                    </span>
                                </div>
                                <div>
                                    <h3 class="text-base font-hanken font-bold text-slate-900 leading-tight">{{ $p->name }}</h3>
                                    <p class="text-xs text-slate-500 mt-0.5 leading-snug">
                                        {{ $p->description ?: 'Solusi monitoring otomatis untuk bisnis Anda.' }}
                                    </p>
                                </div>
                            </div>

                            {{-- Price --}}
                            <div class="flex flex-col">
                                @if(($p->price ?? 0) > 0)
                                    <span class="font-hanken text-3xl font-black text-slate-900 leading-none">
                                        Rp {{ number_format($p->price, 0, ',', '.') }}
                                    </span>
                                    <span class="text-xs font-semibold text-slate-400 mt-1.5">per bulan</span>
                                @else
                                    <span class="font-hanken text-lg font-bold text-slate-800 leading-none">
                                        Hubungi Kami
                                    </span>
                                    <span class="text-xs text-slate-400 mt-1.5">Harga disesuaikan kebutuhan</span>
                                @endif
                            </div>

                            {{-- CTA button --}}
                            <button type="button" class="w-full py-2.5 rounded-xl font-bold text-sm transition-all duration-200 flex items-center justify-center gap-1.5
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

                            {{-- Features list --}}
                            @php
                                $features = array_merge(
                                    array_slice($p->social_media_features ?? [], 0, 4),
                                    array_slice($p->news_portal_features ?? [], 0, 3)
                                );
                            @endphp
                            @if(!empty($features))
                                <div class="pt-4">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">Fitur Termasuk</p>
                                    <div class="space-y-2">
                                        @foreach($features as $feat)
                                            <div class="flex items-start gap-2.5">
                                                <span class="material-symbols-outlined text-[15px] text-[#1fa387] shrink-0 mt-[1px]">check_circle</span>
                                                <span class="text-xs text-slate-600 leading-snug font-medium">{{ $feat }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-8 flex items-center justify-between pt-6">
                <p class="text-xs text-slate-400">
                    <span class="material-symbols-outlined text-[14px] align-middle mr-1">lock</span>
                    Paket dapat diubah kapan saja setelah proyek dibuat.
                </p>
                <button
                    type="button"
                    wire:click="$set('createStep', 2)"
                    @disabled(!$packageId)
                    class="inline-flex items-center gap-1.5 px-5 py-2.5 font-extrabold rounded-xl text-xs transition-all duration-200
                        {{ $packageId
                            ? 'bg-[#1fa387] hover:bg-[#178a71] text-white shadow-sm cursor-pointer active:scale-[0.98]'
                            : 'bg-slate-100 text-slate-400 cursor-not-allowed' }}"
                >
                    Lanjut ke Pengaturan
                    <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                </button>
            </div>

        {{-- ── STEP 2: Form Proyek ── --}}
        @else
            <div class="max-w-2xl bg-white rounded-2xl shadow-[0_2px_20px_rgba(0,0,0,0.06)]">

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
                                class="w-full pl-10 pr-4 py-3 text-sm bg-slate-50 rounded-xl focus:outline-none focus:bg-white focus:ring-2 focus:ring-[#1fa387]/20 placeholder-slate-400 text-slate-800 transition font-medium">
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
                                class="w-full pl-10 pr-4 py-3 text-sm bg-slate-50 rounded-xl focus:outline-none focus:bg-white focus:ring-2 focus:ring-[#1fa387]/20 placeholder-slate-400 text-slate-800 transition font-medium">
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
                                class="w-full pl-10 pr-4 py-3 text-sm bg-slate-50 rounded-xl focus:outline-none focus:bg-white focus:ring-2 focus:ring-[#1fa387]/20 placeholder-slate-400 text-slate-800 transition font-medium">
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
                                class="w-full pl-10 pr-4 py-3 text-sm bg-slate-50 rounded-xl focus:outline-none focus:bg-white focus:ring-2 focus:ring-[#1fa387]/20 placeholder-slate-400 text-slate-800 transition font-medium">
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
                                class="w-full pl-10 pr-4 py-3 text-sm bg-slate-50 rounded-xl focus:outline-none focus:bg-white focus:ring-2 focus:ring-[#1fa387]/20 placeholder-slate-400 text-slate-800 transition font-medium">
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
