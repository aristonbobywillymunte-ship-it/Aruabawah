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

        <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100 overflow-visible">
            <div class="p-8 md:p-10">
                @if($createStep === 1)
                    <!-- Step 1: Pilih Paket -->
                    <!-- Wrapper diberi padding-top agar badge "POPULAR" tidak terpotong -->
                    <div class="pt-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                            @foreach($packages as $p)
                                @php $isSelected = (int) $packageId === (int) $p->id; @endphp
                                <div
                                    wire:click="$set('packageId', {{ $p->id }})"
                                    class="group relative cursor-pointer rounded-3xl p-8 flex flex-col gap-6 transition-all duration-300 bg-white border-2 mt-4
                                        {{ $isSelected
                                            ? 'border-[#1fa387] shadow-2xl shadow-slate-200/60 scale-[1.02]'
                                            : 'border-slate-100 hover:border-slate-200 hover:shadow-xl' }}"
                                >
                                    {{-- Badge POPULAR diletakkan di dalam card, di atas semua konten --}}
                                    @if($p->is_popular)
                                        <div class="absolute -top-3.5 left-1/2 -translate-x-1/2">
                                            <span class="inline-flex items-center px-5 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest bg-[#1fa387] text-white shadow-md border-2 border-white whitespace-nowrap">
                                                ✦ POPULAR
                                            </span>
                                        </div>
                                    @endif

                                    <!-- Header Card -->
                                    <div class="flex items-start gap-4">
                                        <div class="w-12 h-12 rounded-2xl bg-[#1fa387]/10 flex items-center justify-center text-[#1fa387] shrink-0">
                                            @if($p->name == 'Enterprise')
                                                <span class="material-symbols-outlined text-[24px]">rocket_launch</span>
                                            @else
                                                <span class="material-symbols-outlined text-[24px]">widgets</span>
                                            @endif
                                        </div>
                                        <div>
                                            <h3 class="text-xl font-extrabold text-slate-800 tracking-tight">{{ $p->name }}</h3>
                                            <p class="text-xs text-slate-500 leading-relaxed mt-1">{{ $p->description ?: 'Solusi handal untuk otomatisasi.' }}</p>
                                        </div>
                                    </div>

                                    <!-- Price -->
                                    <div class="flex flex-col">
                                        @if($p->price > 0)
                                            <span class="text-4xl font-black text-slate-900 leading-none tracking-tight">
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
                                            ? 'bg-[#1fa387] text-white shadow-lg shadow-[#1fa387]/30'
                                            : 'bg-slate-50 text-slate-500 border border-slate-200/60 group-hover:bg-slate-100' }}">
                                        {{ $isSelected ? 'Terpilih ✓' : 'Pilih Paket' }}
                                    </div>

                                    <!-- Features -->
                                    <div class="border-t border-slate-100 pt-4 space-y-4">
                                        <span class="text-[11px] font-extrabold text-slate-400 uppercase tracking-widest block">Fitur Utama</span>

                                        @php $socialList = $p->social_media_features ?? []; @endphp
                                        @if(!empty($socialList))
                                            <div class="space-y-2.5">
                                                @foreach(array_slice($socialList, 0, 5) as $feat)
                                                    <div class="flex items-start gap-3">
                                                        <span class="material-symbols-outlined text-[16px] text-[#1fa387] shrink-0 mt-0.5">check_circle</span>
                                                        <span class="text-xs text-slate-600 font-medium leading-relaxed">{{ $feat }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        @php $portalList = $p->news_portal_features ?? []; @endphp
                                        @if(!empty($portalList))
                                            <div class="space-y-2.5">
                                                @foreach(array_slice($portalList, 0, 4) as $feat)
                                                    <div class="flex items-start gap-3">
                                                        <span class="material-symbols-outlined text-[16px] text-[#1fa387] shrink-0 mt-0.5">check_circle</span>
                                                        <span class="text-xs text-slate-600 font-medium leading-relaxed">{{ $feat }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-10 flex justify-end border-t border-slate-100 pt-6">
                        <button
                            type="button"
                            wire:click="$set('createStep', 2)"
                            @if(!$packageId)
                                disabled
                                class="px-8 py-3.5 bg-slate-200 text-slate-400 font-black rounded-xl text-sm cursor-not-allowed"
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
                            <!-- Project Name Field -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <label class="text-sm font-bold text-slate-800 block">Nama Proyek</label>
                                    <span class="px-2.5 py-0.5 text-[10px] font-bold bg-red-50 text-red-500 border border-red-100 rounded-full">Wajib</span>
                                </div>
                                <input
                                    wire:model="name"
                                    type="text"
                                    placeholder="Contoh: Arsip Sejarah Tokoh Bangsa"
                                    class="w-full bg-[#F8F9FA] border border-slate-200 focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387] rounded-xl px-4 py-3 text-sm text-slate-800 placeholder-slate-400 transition shadow-sm outline-none"
                                >
                                @error('name') <span class="text-red-500 text-xs font-medium block mt-1">{{ $message }}</span> @enderror
                            </div>

                            <!-- Telegram Chat ID -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <label class="text-sm font-bold text-slate-800 block">Telegram Chat ID</label>
                                    <span class="px-2.5 py-0.5 text-[10px] font-bold bg-red-50 text-red-500 border border-red-100 rounded-full">Wajib</span>
                                </div>
                                <p class="text-xs text-slate-500 leading-tight">Masukkan ID chat/group Telegram tanpa menggunakan tanda minus di depan (contoh: 10022334455).</p>
                                <input
                                    wire:model="telegramChatId"
                                    type="text"
                                    placeholder="Contoh: 10022334455"
                                    class="w-full bg-[#F8F9FA] border border-slate-200 focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387] rounded-xl px-4 py-3 text-sm text-slate-800 placeholder-slate-400 transition shadow-sm outline-none"
                                >
                                @error('telegramChatId') <span class="text-red-500 text-xs font-medium block mt-1">{{ $message }}</span> @enderror
                            </div>

                            <!-- Main Keywords Field -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <label class="text-sm font-bold text-slate-800 block">Kata Kunci Pencarian (Scraping)</label>
                                    <span class="px-2.5 py-0.5 text-[10px] font-bold bg-red-50 text-red-500 border border-red-100 rounded-full">Wajib</span>
                                </div>
                                <p class="text-xs text-slate-500">
                                    Kata kunci pencarian atau frasa utama untuk proyek Anda. Kata kunci ini digunakan sebagai acuan untuk melakukan scraping data berita dan sosial media.
                                </p>
                                <input
                                    wire:model="topicsString"
                                    type="text"
                                    placeholder="Contoh: Pahlawan Nasional, Proklamator, Tokoh Sejarah"
                                    class="w-full bg-[#F8F9FA] border border-slate-200 focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387] rounded-xl px-4 py-3 text-sm text-slate-800 placeholder-slate-400 transition shadow-sm outline-none"
                                >
                                @error('topicsString') <span class="text-red-500 text-xs font-medium block mt-1">{{ $message }}</span> @enderror
                                <p class="text-[11px] text-slate-400 mt-1">Tidak peka huruf besar/kecil. Pisahkan dengan koma.</p>

                                <!-- Preview Tags -->
                                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50/70 p-5" x-data="{
                                    topics() {
                                        return $wire.topicsString ? $wire.topicsString.split(',').map(t => t.trim()).filter(Boolean) : [];
                                    },
                                    toHashtag(topic) {
                                        const clean = topic
                                            .replace(/^#+/, '')
                                            .replace(/['''`]/g, '')
                                            .replace(/\s+/g, '');
                                        return clean ? `#${clean}` : '';
                                    }
                                }">
                                    <div class="flex items-center justify-between gap-3 mb-4">
                                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Preview Hashtag</span>
                                    </div>
                                    <div class="flex flex-wrap gap-2 text-sm">
                                        <template x-for="topic in topics()" :key="topic">
                                            <span
                                                class="px-4 py-2 rounded-full border border-[#1fa387]/20 bg-[#1fa387]/5 text-[#1fa387] font-bold"
                                                x-text="toHashtag(topic)"
                                            ></span>
                                        </template>
                                        <span x-show="!$wire.topicsString" class="text-sm text-slate-400 italic">Belum ada keyword.</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Context Keywords -->
                            <div class="space-y-2 pt-2">
                                <div class="flex items-center justify-between">
                                    <label class="text-sm font-bold text-slate-800 block">Kata Kunci Penyaring (Opsional)</label>
                                    <span class="px-2.5 py-0.5 text-[10px] font-bold bg-slate-100 text-slate-500 border border-slate-200 rounded-full">Opsional</span>
                                </div>
                                <p class="text-xs text-slate-500 leading-relaxed">
                                    Kata kunci opsional untuk memperketat penyaringan data di dashboard. Jika kolom ini dikosongkan, sistem akan otomatis menampilkan semua data yang cocok dengan <strong>Kata Kunci Pencarian (Scraping)</strong> di atas.
                                </p>
                                <input
                                    wire:model="contextKeywords"
                                    type="text"
                                    placeholder="Contoh: Soekarno, Hatta, Sudirman"
                                    class="w-full bg-[#F8F9FA] border border-slate-200 focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387] rounded-xl px-4 py-3 text-sm text-slate-800 placeholder-slate-400 transition shadow-sm outline-none"
                                >
                                @error('contextKeywords') <span class="text-red-500 text-xs font-medium block mt-1">{{ $message }}</span> @enderror
                            </div>

                            <!-- Exclude Keywords -->
                            <div class="space-y-2 pt-2">
                                <div class="flex items-center justify-between">
                                    <label class="text-sm font-bold text-slate-800 block">Kata Kunci Pengecualian</label>
                                    <span class="px-2.5 py-0.5 text-[10px] font-bold bg-slate-100 text-slate-500 border border-slate-200 rounded-full">Opsional</span>
                                </div>
                                <p class="text-xs text-slate-500 leading-relaxed">
                                    Artikel berita yang mengandung kata kunci ini tidak akan dimasukkan ke database sistem monitoring proyek Anda.
                                </p>
                                <input
                                    wire:model="excludeKeywords"
                                    type="text"
                                    placeholder="Contoh: promosi, jual, beli, diskon"
                                    class="w-full bg-[#F8F9FA] border border-slate-200 focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387] rounded-xl px-4 py-3 text-sm text-slate-800 placeholder-slate-400 transition shadow-sm outline-none"
                                >
                                @error('excludeKeywords') <span class="text-red-500 text-xs font-medium block mt-1">{{ $message }}</span> @enderror
                            </div>

                            <div class="flex justify-end gap-4 border-t border-slate-100 pt-8">
                                <button
                                    type="button"
                                    wire:click="$set('createStep', 1)"
                                    class="px-8 py-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-sm transition cursor-pointer active:scale-95"
                                >
                                    Kembali
                                </button>
                                <button
                                    type="submit"
                                    class="px-8 py-3.5 min-w-[160px] bg-[#1fa387] hover:bg-[#178a71] text-white font-black rounded-xl text-sm transition shadow-lg shadow-[#1fa387]/20 cursor-pointer flex items-center justify-center gap-2 disabled:opacity-75 disabled:cursor-not-allowed"
                                    wire:loading.attr="disabled"
                                    wire:target="createProject"
                                >
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
