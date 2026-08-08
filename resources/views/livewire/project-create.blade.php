<div>
    <div class="max-w-[1400px] mx-auto px-6 py-10">
        <!-- Header -->
        <div class="mb-8">
            <a href="{{ route('home') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-bold text-[#1fa387] hover:text-[#178a71] transition mb-5">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                Kembali ke Proyek
            </a>
            <h1 class="text-3xl font-hanken font-bold text-slate-900 tracking-tight">Tambah Proyek Baru</h1>
            <p class="text-slate-500 text-sm mt-2">
                @if($createStep === 1)
                    Pilih paket untuk proyek baru Anda.
                @else
                    Isi detail proyek Anda.
                @endif
            </p>
        </div>

        <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100">
            <div class="p-8 md:p-10">
                @if($createStep === 1)
                    <!-- Step 1: Pilih Paket -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($packages as $p)
                            @php $isSelected = (int) $packageId === (int) $p->id; @endphp
                            <div
                                wire:click="$set('packageId', {{ $p->id }})"
                                class="group relative cursor-pointer rounded-2xl flex flex-col transition-all duration-300 border-2
                                    {{ $isSelected
                                        ? 'border-[#1fa387] shadow-xl shadow-[#1fa387]/10 bg-gradient-to-b from-[#1fa387]/5 to-white'
                                        : 'border-slate-100 bg-white hover:border-slate-200 hover:shadow-lg' }}"
                            >
                                {{-- Badge POPULAR tidak menggunakan absolute, melainkan di dalam flow normal di bagian paling atas card --}}
                                @if($p->is_popular)
                                    <div class="bg-[#1fa387] rounded-t-[14px] px-4 py-2 flex items-center justify-center gap-2">
                                        <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                        <span class="text-[11px] font-black uppercase tracking-widest text-white">Paling Populer</span>
                                    </div>
                                @else
                                    {{-- Spacer transparan agar semua card tingginya konsisten dari atas --}}
                                    <div class="rounded-t-[14px] px-4 py-2 invisible select-none" aria-hidden="true">
                                        <span class="text-[11px] font-black uppercase tracking-widest">_</span>
                                    </div>
                                @endif

                                <div class="p-7 flex flex-col gap-6 flex-1">
                                    <!-- Header Card -->
                                    <div class="flex items-start gap-4">
                                        <div class="w-11 h-11 rounded-xl {{ $isSelected ? 'bg-[#1fa387] text-white' : 'bg-slate-100 text-slate-500 group-hover:bg-[#1fa387]/10 group-hover:text-[#1fa387]' }} flex items-center justify-center shrink-0 transition-all duration-300">
                                            @if($p->name == 'Enterprise')
                                                <span class="material-symbols-outlined text-[20px]">rocket_launch</span>
                                            @else
                                                <span class="material-symbols-outlined text-[20px]">widgets</span>
                                            @endif
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-extrabold text-slate-800 tracking-tight">{{ $p->name }}</h3>
                                            <p class="text-xs text-slate-500 leading-relaxed mt-1">{{ $p->description ?: 'Solusi handal untuk otomatisasi.' }}</p>
                                        </div>
                                    </div>

                                    <!-- Price -->
                                    <div class="flex flex-col">
                                        @if($p->price > 0)
                                            <span class="text-3xl font-black text-slate-900 leading-none tracking-tight">
                                                Rp {{ number_format($p->price, 0, ',', '.') }}
                                            </span>
                                            <span class="text-slate-400 text-xs font-bold mt-2">/bulan</span>
                                        @else
                                            <span class="text-2xl font-black text-[#1fa387] leading-none uppercase tracking-wide">HUBUNGI KAMI</span>
                                            <span class="text-slate-400 text-xs font-bold mt-2">Harga kustomisasi</span>
                                        @endif
                                    </div>

                                    <!-- Action Button -->
                                    <div class="w-full py-3 rounded-xl font-extrabold text-sm text-center transition-all duration-300
                                        {{ $isSelected
                                            ? 'bg-[#1fa387] text-white shadow-lg shadow-[#1fa387]/25'
                                            : 'bg-slate-50 text-slate-500 border border-slate-200 group-hover:border-[#1fa387]/30 group-hover:text-[#1fa387] group-hover:bg-[#1fa387]/5' }}">
                                        @if($isSelected)
                                            <span class="flex items-center justify-center gap-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                                Terpilih
                                            </span>
                                        @else
                                            Pilih Paket
                                        @endif
                                    </div>

                                    <!-- Features -->
                                    @php
                                        $socialList = $p->social_media_features ?? [];
                                        $portalList = $p->news_portal_features ?? [];
                                        $allFeatures = array_merge(array_slice($socialList, 0, 5), array_slice($portalList, 0, 4));
                                    @endphp
                                    @if(!empty($allFeatures))
                                        <div class="border-t border-slate-100 pt-5 space-y-3">
                                            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest block">Fitur Utama</span>
                                            <div class="space-y-2.5">
                                                @foreach($allFeatures as $feat)
                                                    <div class="flex items-start gap-2.5">
                                                        <svg class="w-4 h-4 text-[#1fa387] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                        <span class="text-xs text-slate-600 font-medium leading-relaxed">{{ $feat }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-8 flex justify-end border-t border-slate-100 pt-6">
                        <button
                            type="button"
                            wire:click="$set('createStep', 2)"
                            @if(!$packageId)
                                disabled
                                class="px-8 py-3.5 bg-slate-100 text-slate-400 font-black rounded-xl text-sm cursor-not-allowed"
                            @else
                                class="px-8 py-3.5 bg-[#1fa387] hover:bg-[#178a71] text-white font-black rounded-xl text-sm transition shadow-lg shadow-[#1fa387]/20 cursor-pointer active:scale-95"
                            @endif
                        >
                            Lanjut ke Pengaturan Proyek
                        </button>
                    </div>

                @else
                    <!-- Step 2: Form Proyek -->
                    <div class="max-w-3xl mx-auto">
                        @if($selectedPackage)
                            <div class="mb-8 p-5 bg-slate-50 rounded-2xl border border-slate-200 flex items-center justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-[#1fa387]/10 flex items-center justify-center text-[#1fa387] shrink-0">
                                        <span class="material-symbols-outlined text-[18px]">inventory_2</span>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Paket Terpilih</p>
                                        <h3 class="text-sm font-black text-slate-800">{{ $selectedPackage->name }}</h3>
                                    </div>
                                </div>
                                <button type="button" wire:click="$set('createStep', 1)"
                                    class="shrink-0 text-xs font-bold text-[#1fa387] hover:text-[#178a71] px-3 py-2 bg-[#1fa387]/10 hover:bg-[#1fa387]/20 rounded-lg transition">
                                    Ganti Paket
                                </button>
                            </div>
                        @endif

                        <form wire:submit.prevent="createProject" class="space-y-8">
                            <!-- Project Name -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <label class="text-sm font-bold text-slate-800">Nama Proyek</label>
                                    <span class="px-2.5 py-0.5 text-[10px] font-bold bg-red-50 text-red-500 border border-red-100 rounded-full">Wajib</span>
                                </div>
                                <input wire:model="name" type="text"
                                    placeholder="Contoh: Arsip Sejarah Tokoh Bangsa"
                                    class="w-full bg-[#F8F9FA] border border-slate-200 focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/20 rounded-xl px-4 py-3 text-sm text-slate-800 placeholder-slate-400 transition outline-none">
                                @error('name') <span class="text-red-500 text-xs font-medium block mt-1">{{ $message }}</span> @enderror
                            </div>

                            <!-- Telegram Chat ID -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <label class="text-sm font-bold text-slate-800">Telegram Chat ID</label>
                                    <span class="px-2.5 py-0.5 text-[10px] font-bold bg-red-50 text-red-500 border border-red-100 rounded-full">Wajib</span>
                                </div>
                                <p class="text-xs text-slate-500 leading-tight">Masukkan ID chat/group Telegram tanpa menggunakan tanda minus di depan (contoh: 10022334455).</p>
                                <input wire:model="telegramChatId" type="text"
                                    placeholder="Contoh: 10022334455"
                                    class="w-full bg-[#F8F9FA] border border-slate-200 focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/20 rounded-xl px-4 py-3 text-sm text-slate-800 placeholder-slate-400 transition outline-none">
                                @error('telegramChatId') <span class="text-red-500 text-xs font-medium block mt-1">{{ $message }}</span> @enderror
                            </div>

                            <!-- Main Keywords -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <label class="text-sm font-bold text-slate-800">Kata Kunci Pencarian (Scraping)</label>
                                    <span class="px-2.5 py-0.5 text-[10px] font-bold bg-red-50 text-red-500 border border-red-100 rounded-full">Wajib</span>
                                </div>
                                <p class="text-xs text-slate-500">Kata kunci pencarian atau frasa utama untuk proyek Anda. Pisahkan dengan koma.</p>
                                <input wire:model="topicsString" type="text"
                                    placeholder="Contoh: Pahlawan Nasional, Proklamator, Tokoh Sejarah"
                                    class="w-full bg-[#F8F9FA] border border-slate-200 focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/20 rounded-xl px-4 py-3 text-sm text-slate-800 placeholder-slate-400 transition outline-none">
                                @error('topicsString') <span class="text-red-500 text-xs font-medium block mt-1">{{ $message }}</span> @enderror
                                <p class="text-[11px] text-slate-400 mt-1">Tidak peka huruf besar/kecil. Pisahkan dengan koma.</p>

                                <!-- Preview Tags -->
                                <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4" x-data="{
                                    topics() {
                                        return $wire.topicsString ? $wire.topicsString.split(',').map(t => t.trim()).filter(Boolean) : [];
                                    },
                                    toHashtag(topic) {
                                        const clean = topic.replace(/^#+/, '').replace(/['''`]/g, '').replace(/\s+/g, '');
                                        return clean ? `#${clean}` : '';
                                    }
                                }">
                                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block mb-3">Preview Hashtag</span>
                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="topic in topics()" :key="topic">
                                            <span class="px-3 py-1.5 rounded-full border border-[#1fa387]/20 bg-[#1fa387]/5 text-[#1fa387] text-xs font-bold" x-text="toHashtag(topic)"></span>
                                        </template>
                                        <span x-show="!$wire.topicsString" class="text-sm text-slate-400 italic">Belum ada keyword.</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Context Keywords -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <label class="text-sm font-bold text-slate-800">Kata Kunci Penyaring</label>
                                    <span class="px-2.5 py-0.5 text-[10px] font-bold bg-slate-100 text-slate-500 border border-slate-200 rounded-full">Opsional</span>
                                </div>
                                <p class="text-xs text-slate-500 leading-relaxed">Kata kunci opsional untuk memperketat penyaringan data di dashboard. Jika kosong, sistem otomatis menampilkan semua data yang cocok dengan Kata Kunci Pencarian.</p>
                                <input wire:model="contextKeywords" type="text"
                                    placeholder="Contoh: Soekarno, Hatta, Sudirman"
                                    class="w-full bg-[#F8F9FA] border border-slate-200 focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/20 rounded-xl px-4 py-3 text-sm text-slate-800 placeholder-slate-400 transition outline-none">
                                @error('contextKeywords') <span class="text-red-500 text-xs font-medium block mt-1">{{ $message }}</span> @enderror
                            </div>

                            <!-- Exclude Keywords -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <label class="text-sm font-bold text-slate-800">Kata Kunci Pengecualian</label>
                                    <span class="px-2.5 py-0.5 text-[10px] font-bold bg-slate-100 text-slate-500 border border-slate-200 rounded-full">Opsional</span>
                                </div>
                                <p class="text-xs text-slate-500 leading-relaxed">Artikel yang mengandung kata kunci ini tidak akan masuk ke database monitoring proyek Anda.</p>
                                <input wire:model="excludeKeywords" type="text"
                                    placeholder="Contoh: promosi, jual, beli, diskon"
                                    class="w-full bg-[#F8F9FA] border border-slate-200 focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/20 rounded-xl px-4 py-3 text-sm text-slate-800 placeholder-slate-400 transition outline-none">
                                @error('excludeKeywords') <span class="text-red-500 text-xs font-medium block mt-1">{{ $message }}</span> @enderror
                            </div>

                            <div class="flex justify-end gap-4 border-t border-slate-100 pt-8">
                                <button type="button" wire:click="$set('createStep', 1)"
                                    class="px-8 py-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-sm transition cursor-pointer active:scale-95">
                                    Kembali
                                </button>
                                <button type="submit"
                                    class="px-8 py-3.5 min-w-[160px] bg-[#1fa387] hover:bg-[#178a71] text-white font-black rounded-xl text-sm transition shadow-lg shadow-[#1fa387]/20 cursor-pointer flex items-center justify-center gap-2 disabled:opacity-75 disabled:cursor-not-allowed"
                                    wire:loading.attr="disabled"
                                    wire:target="createProject">
                                    <span wire:loading.remove wire:target="createProject">Buat Proyek</span>
                                    <span wire:loading.flex wire:target="createProject" class="items-center justify-center gap-2">
                                        <svg class="animate-spin h-4 w-4 text-white shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span>Membuat...</span>
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
