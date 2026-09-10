<div>
    @if($showModal)
        <div
            x-data
            x-init="document.body.classList.add('overflow-hidden'); return () => document.body.classList.remove('overflow-hidden');"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            @keydown.escape.window="$wire.close()"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
        >
            <div 
                @click.outside.stop="$wire.close()"
                class="bg-white rounded-3xl w-full max-w-2xl shadow-2xl border border-slate-100 overflow-hidden flex flex-col h-[82vh] max-h-[640px]"
            >
                <!-- Modal Header (Fixed / Non-Scrollable) -->
                <div class="px-8 py-5 border-b border-slate-100 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-[#1fa387]/10 flex items-center justify-center text-[#1fa387] shrink-0">
                            <span class="material-symbols-outlined text-[20px]">edit</span>
                        </div>
                        <div>
                            <h3 class="text-lg font-hanken font-extrabold text-slate-900 leading-tight">Edit Proyek</h3>
                            <p class="text-xs text-slate-500 mt-0.5 font-medium">Sesuaikan parameter pemantauan dan sumber data proyek Anda.</p>
                        </div>
                    </div>
                    <button 
                        type="button"
                        wire:click="close" 
                        class="text-slate-400 hover:text-slate-600 hover:bg-slate-100 p-2 rounded-xl transition duration-150 cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <!-- Modal Body (Form - Hanya Bagian Ini Yang Boleh Di-scroll) -->
                <form wire:submit.prevent="updateProject" class="flex flex-col flex-1 min-h-0 overflow-hidden">
                    <div class="px-8 py-6 space-y-6 flex-1 overflow-y-auto overscroll-contain">
                        <!-- Pilih Paket (Paling Atas) -->
                        @if($projectPackage)
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-sm font-bold text-slate-800 block">Paket Aktif</label>
                                <span class="px-2.5 py-0.5 text-[10px] font-bold bg-slate-100 text-slate-500 border border-slate-200 rounded-full">Terkunci</span>
                            </div>
                            <div class="relative">
                                <select wire:model="packageId" disabled class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-500 cursor-not-allowed appearance-none font-medium">
                                    <option value="{{ $projectPackage->id }}">{{ $projectPackage->name }} @if($projectPackage->price > 0) (Rp {{ number_format($projectPackage->price, 0, ',', '.') }}/bln) @else (Kustom) @endif</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-400">
                                    <span class="material-symbols-outlined text-[18px]">lock</span>
                                </div>
                            </div>
                            @error('packageId') <span class="text-red-500 text-xs font-medium block mt-1">{{ $message }}</span> @enderror
                        </div>

                        @php
                            $portalSlots = min(24, (int) ($projectPackage->news_runs_per_day ?? 0));
                            $socialSlots = min(24, (int) ($projectPackage->social_runs_per_day ?? 0));
                        @endphp
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <div class="space-y-3 rounded-2xl border border-violet-100 bg-violet-50/50 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-violet-500">Portal Paket</p>
                                        <p class="text-sm font-extrabold text-slate-800">{{ $projectPackage->news_runs_per_day ? $projectPackage->news_runs_per_day . 'x / hari' : 'Tidak dijadwalkan' }}</p>
                                    </div>
                                    <span class="px-2 py-1 rounded-full text-[10px] font-bold bg-white border border-violet-100 text-violet-500">Jadwal Paket</span>
                                </div>
                                <p class="text-xs text-slate-500 leading-relaxed">{{ $projectPackage->news_run_times ? implode(' · ', $projectPackage->news_run_times) : 'Belum ada jadwal paket.' }}</p>
                                @if($portalSlots > 0)
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <label class="text-sm font-bold text-slate-800 block">Override Jadwal Portal Proyek</label>
                                        <span class="px-2 py-0.5 text-[10px] font-bold bg-violet-50 text-violet-500 border border-violet-100 rounded-full">Opsional</span>
                                    </div>
                                    <p class="text-xs text-slate-500">Kosongkan seluruh jam untuk mengikuti jadwal Paket.</p>
                                    <div class="grid gap-2">
                                        @for($i = 0; $i < $portalSlots; $i++)
                                            <div class="flex items-center gap-2">
                                                <span class="text-[10px] font-black text-violet-400 w-10 shrink-0 text-right">Slot {{ $i + 1 }}</span>
                                                <input
                                                    wire:model="news_run_times_override.{{ $i }}"
                                                    type="time"
                                                    class="flex-1 bg-white border border-violet-100 focus:border-violet-400 focus:ring-1 focus:ring-violet-200 rounded-xl px-3 py-2 text-sm text-slate-800 transition"
                                                >
                                            </div>
                                        @endfor
                                    </div>
                                    @error('news_run_times_override') <span class="text-red-500 text-xs font-medium block mt-1">{{ $message }}</span> @enderror
                                </div>
                                @endif
                            </div>

                            <div class="space-y-3 rounded-2xl border border-sky-100 bg-sky-50/50 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-sky-500">Sosial Paket</p>
                                        <p class="text-sm font-extrabold text-slate-800">{{ $projectPackage->social_runs_per_day ? $projectPackage->social_runs_per_day . 'x / hari' : 'Tidak dijadwalkan' }}</p>
                                    </div>
                                    <span class="px-2 py-1 rounded-full text-[10px] font-bold bg-white border border-sky-100 text-sky-500">Jadwal Paket</span>
                                </div>
                                <p class="text-xs text-slate-500 leading-relaxed">{{ $projectPackage->social_run_times ? implode(' · ', $projectPackage->social_run_times) : 'Belum ada jadwal paket.' }}</p>
                                @if($socialSlots > 0)
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <label class="text-sm font-bold text-slate-800 block">Override Jadwal Sosial Proyek</label>
                                        <span class="px-2 py-0.5 text-[10px] font-bold bg-sky-50 text-sky-500 border border-sky-100 rounded-full">Opsional</span>
                                    </div>
                                    <p class="text-xs text-slate-500">Kosongkan seluruh jam untuk mengikuti jadwal Paket.</p>
                                    <div class="grid gap-2">
                                        @for($i = 0; $i < $socialSlots; $i++)
                                            <div class="flex items-center gap-2">
                                                <span class="text-[10px] font-black text-sky-400 w-10 shrink-0 text-right">Slot {{ $i + 1 }}</span>
                                                <input
                                                    wire:model="social_run_times_override.{{ $i }}"
                                                    type="time"
                                                    class="flex-1 bg-white border border-sky-100 focus:border-sky-400 focus:ring-1 focus:ring-sky-200 rounded-xl px-3 py-2 text-sm text-slate-800 transition"
                                                >
                                            </div>
                                        @endfor
                                    </div>
                                    @error('social_run_times_override') <span class="text-red-500 text-xs font-medium block mt-1">{{ $message }}</span> @enderror
                                </div>
                                @endif
                            </div>
                        </div>
                        @endif

                        <!-- Project Name Field -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-sm font-bold text-slate-800 block">Nama Proyek</label>
                                <span class="px-2.5 py-0.5 text-[10px] font-bold bg-red-50 text-red-500 border border-red-100 rounded-full">Wajib</span>
                            </div>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">folder</span>
                                <input 
                                    wire:model="editName" 
                                    type="text" 
                                    placeholder="Contoh: Monitoring Prabowo Subianto"
                                    class="w-full bg-slate-50 border border-slate-200 focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/20 rounded-xl pl-10 pr-4 py-3 text-sm text-slate-800 placeholder-slate-400 transition font-medium"
                                >
                            </div>
                            @error('editName') <span class="text-red-500 text-xs font-medium block mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Telegram Chat ID -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-sm font-bold text-slate-800 block">Telegram Chat ID</label>
                                <span class="px-2.5 py-0.5 text-[10px] font-bold bg-red-50 text-red-500 border border-red-100 rounded-full">Wajib</span>
                            </div>
                            <p class="text-xs text-slate-400 leading-tight">Masukkan ID chat/group Telegram tanpa menggunakan tanda minus di depan (contoh: 10022334455).</p>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">send</span>
                                <input 
                                    wire:model="telegramChatId" 
                                    type="text" 
                                    placeholder="Contoh: 10022334455"
                                    class="w-full bg-slate-50 border border-slate-200 focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/20 rounded-xl pl-10 pr-4 py-3 text-sm text-slate-800 placeholder-slate-400 transition font-medium"
                                >
                            </div>
                            @error('telegramChatId') <span class="text-red-500 text-xs font-medium block mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Main Keywords Field (Kata Kunci Pencarian (Scraping)) -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-sm font-bold text-slate-800 block">Kata Kunci Pencarian (Scraping)</label>
                                <span class="px-2.5 py-0.5 text-[10px] font-bold bg-red-50 text-red-500 border border-red-100 rounded-full">Wajib</span>
                            </div>
                            <p class="text-xs text-slate-400">
                                Kata kunci pencarian atau frasa utama untuk proyek Anda. Kata kunci ini digunakan sebagai acuan untuk melakukan scraping data berita dan sosial media.
                            </p>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                                <input 
                                    wire:model="editTopicsString" 
                                    type="text" 
                                    placeholder="Contoh: Prabowo, Presiden, Menhan"
                                    class="w-full bg-slate-50 border border-slate-200 focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/20 rounded-xl pl-10 pr-4 py-3 text-sm text-slate-800 placeholder-slate-400 transition font-medium"
                                >
                            </div>
                            @error('editTopicsString') <span class="text-red-500 text-xs font-medium block mt-1">{{ $message }}</span> @enderror
                            <p class="text-[10px] text-slate-400 mt-1">Tidak peka huruf besar/kecil. Pisahkan dengan Koma untuk banyak kata kunci.</p>
                            
                            <div class="mt-3 rounded-2xl border border-slate-200 bg-slate-50/70 p-4" x-data="{
                                topics() {
                                    return $wire.editTopicsString ? $wire.editTopicsString.split(',').map(t => t.trim()).filter(Boolean) : [];
                                },
                                toHashtag(topic) {
                                    const clean = topic
                                        .replace(/^#+/, '')
                                        .replace(/['’‘`]/g, '')
                                        .replace(/\s+/g, '');
                                    return clean ? `#${clean}` : '';
                                }
                            }">
                                <div class="flex items-center justify-between gap-3 mb-3">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Preview Hashtag</span>
                                    <span class="text-[10px] font-semibold text-slate-500">Hasil akhir saat disimpan</span>
                                </div>
                                <div class="flex flex-wrap gap-2 text-xs">
                                    <template x-for="topic in topics()" :key="topic">
                                        <span
                                            class="px-3 py-1.5 rounded-full border border-[#1fa387]/20 bg-[#1fa387]/5 text-[#1fa387] font-bold"
                                            x-text="toHashtag(topic)"
                                        ></span>
                                    </template>
                                    <span x-show="!$wire.editTopicsString" class="text-xs text-slate-400 italic">Belum ada keyword.</span>
                                </div>
                            </div>
                        </div>

                        <!-- Filter Keyword (Kata Kunci Penyaring) -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-sm font-bold text-slate-800 block">Kata Kunci Penyaring (Opsional)</label>
                                <span class="px-2.5 py-0.5 text-[10px] font-bold bg-slate-100 text-slate-500 border border-slate-200 rounded-full">Opsional</span>
                            </div>
                            <p class="text-xs text-slate-400 leading-normal">
                                Kata kunci opsional untuk memperketat penyaringan data di dashboard. Jika kolom ini dikosongkan, sistem akan otomatis menampilkan semua data yang cocok dengan <strong>Kata Kunci Pencarian (Scraping)</strong> di atas.
                            </p>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">filter_alt</span>
                                <input 
                                    wire:model="contextKeywords" 
                                    type="text" 
                                    placeholder="Contoh: Soekarno, Hatta, Sudirman (Kosongkan jika tidak ingin disaring ganda)"
                                    class="w-full bg-slate-50 border border-slate-200 focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/20 rounded-xl pl-10 pr-4 py-3 text-sm text-slate-800 placeholder-slate-400 transition font-medium"
                                >
                            </div>
                            @error('contextKeywords') <span class="text-red-500 text-xs font-medium block mt-1">{{ $message }}</span> @enderror
                            <p class="text-[10px] text-slate-400 mt-1">Pisahkan dengan koma.</p>
                        </div>

                        <!-- Dikecualikan Column (Kata Kunci Pengecualian) -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-sm font-bold text-slate-800 block">Kata Kunci Pengecualian</label>
                                <span class="px-2.5 py-0.5 text-[10px] font-bold bg-slate-100 text-slate-500 border border-slate-200 rounded-full">Opsional</span>
                            </div>
                            <p class="text-xs text-slate-400 leading-tight">Penyebutan tidak akan dikumpulkan jika mengandung kata kunci ini.</p>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">block</span>
                                <input 
                                    wire:model="excludeKeywords" 
                                    type="text" 
                                    placeholder="Contoh: hoaks, fiksi, mitos"
                                    class="w-full bg-slate-50 border border-slate-200 focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/20 rounded-xl pl-10 pr-4 py-3 text-sm text-slate-800 placeholder-slate-400 transition font-medium"
                                >
                            </div>
                            @error('excludeKeywords') <span class="text-red-500 text-xs font-medium block mt-1">{{ $message }}</span> @enderror
                            <p class="text-[10px] text-slate-400 mt-1">Pisahkan dengan koma.</p>
                        </div>
                    </div>

                    <!-- Footer buttons -->
                    <div class="flex justify-end space-x-3 px-8 py-4 border-t border-slate-100 shrink-0 bg-white">
                        <button 
                            type="button" 
                            wire:click="close"
                            class="px-6 py-2.5 bg-slate-100 hover:bg-slate-200 active:scale-[0.98] text-slate-700 font-bold rounded-xl text-xs transition cursor-pointer"
                        >
                            Batal
                        </button>
                        <button 
                            type="submit" 
                            class="px-6 py-2.5 bg-[#1fa387] hover:bg-[#188c73] active:scale-[0.98] text-white font-bold rounded-xl text-xs transition cursor-pointer flex items-center justify-center gap-2 shadow-sm disabled:opacity-75 disabled:cursor-not-allowed"
                            wire:loading.attr="disabled"
                            wire:target="updateProject"
                        >
                            <span wire:loading.remove wire:target="updateProject" class="inline-flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px]">save</span>
                                <span>Simpan Perubahan</span>
                            </span>
                            <span wire:loading.flex wire:target="updateProject" class="items-center justify-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                                <span>Menyimpan...</span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
