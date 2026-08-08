<div>
    @if($showModal)
        <div
            x-data="{ show: true }"
            x-show="show"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
        >
            <div class="bg-white rounded-3xl w-full max-w-4xl shadow-2xl border border-slate-100 overflow-hidden animate-fade-in" style="height: 80vh; max-height: 650px; display: flex; flex-direction: column;">
                <!-- Modal Header -->
                <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between shrink-0" style="flex-shrink: 0;">
                    <div>
                        <h3 class="text-lg font-hanken font-extrabold text-slate-900 leading-tight">Edit Proyek</h3>
                        <p class="text-xs text-slate-455 mt-0.5 font-medium">Sesuaikan parameter pemantauan dan sumber data proyek Anda.</p>
                    </div>
                    <button wire:click="close" class="text-slate-400 hover:text-slate-650 hover:bg-slate-100 p-2 rounded-full transition duration-150 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                    </button>
                </div>

                <!-- Modal Body (Form) -->
                <form wire:submit.prevent="updateProject" class="flex flex-col flex-1 min-h-0">
                    <div class="px-8 py-6 space-y-6" style="flex: 1 1 auto; overflow-y: auto;">
                        <!-- Pilih Paket (Paling Atas) -->
                        @if($projectPackage)
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-sm font-bold text-slate-800 block">Paket Aktif</label>
                                <span class="px-2.5 py-0.5 text-[10px] font-bold bg-slate-100 text-slate-500 border border-slate-200 rounded-full">Terkunci</span>
                            </div>
                            <div class="relative">
                                <select wire:model="packageId" disabled class="w-full bg-slate-100 border border-slate-350 rounded-custom px-4 py-3 text-sm text-slate-500 cursor-not-allowed appearance-none">
                                    <option value="{{ $projectPackage->id }}">{{ $projectPackage->name }} @if($projectPackage->price > 0) (Rp {{ number_format($projectPackage->price, 0, ',', '.') }}/bln) @else (Kustom) @endif</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-500">
                                    <svg class="h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                                </div>
                            </div>
                            @error('packageId') <span class="text-red-500 text-xs font-medium block mt-1">{{ $message }}</span> @enderror
                        </div>
                        @endif

                        <!-- Project Name Field -->
                        <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-bold text-slate-800 block">Nama Proyek</label>
                            <span class="px-2.5 py-0.5 text-[10px] font-bold bg-red-50 text-red-500 border border-red-100 rounded-full">Wajib</span>
                        </div>
                        <input 
                            wire:model="editName" 
                            type="text" 
                            placeholder="Contoh: Arsip Sejarah Tokoh Bangsa"
                            class="w-full bg-[#F8F9FA] border border-slate-350 focus:border-primary focus:ring-1 focus:ring-primary rounded-custom px-4 py-3 text-sm text-slate-855 placeholder-[#727785] transition"
                            >
                        @error('editName') <span class="text-red-500 text-xs font-medium block mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- Telegram Chat ID -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-sm font-bold text-slate-800 block">Telegram Chat ID</label>
                                <span class="px-2.5 py-0.5 text-[10px] font-bold bg-red-50 text-red-500 border border-red-100 rounded-full">Wajib</span>
                            </div>
                            <p class="text-xs text-slate-400 leading-tight">Masukkan ID chat/group Telegram tanpa menggunakan tanda minus di depan (contoh: 10022334455).</p>
                            <input 
                                wire:model="telegramChatId" 
                                type="text" 
                                placeholder="Contoh: 10022334455"
                                class="w-full bg-[#F8F9FA] border border-slate-350 focus:border-primary focus:ring-1 focus:ring-primary rounded-custom px-4 py-3 text-sm text-slate-855 placeholder-[#727785] transition"
                            >
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
                            <input 
                                wire:model="editTopicsString" 
                                type="text" 
                                placeholder="Contoh: Pahlawan Nasional, Proklamator, Tokoh Sejarah"
                                class="w-full bg-[#F8F9FA] border border-slate-350 focus:border-primary focus:ring-1 focus:ring-primary rounded-custom px-4 py-3 text-sm text-slate-855 placeholder-[#727785] transition"
                            >
                            @error('editTopicsString') <span class="text-red-500 text-xs font-medium block mt-1">{{ $message }}</span> @enderror
                            <p class="text-[10px] text-slate-400 mt-1">Tidak peka huruf besar/kecil. Pisahkan dengan Koma atau tekan Enter untuk banyak kata kunci.</p>
                            
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

                        <!-- Filter Keyword (Kata Kunci Penyaring) - Pindah ke bawah dan Opsional -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-sm font-bold text-slate-800 block">Kata Kunci Penyaring (Opsional)</label>
                                <span class="px-2.5 py-0.5 text-[10px] font-bold bg-slate-50 text-slate-500 border border-slate-200 rounded-full">Opsional</span>
                            </div>
                            <p class="text-xs text-slate-400 leading-normal">
                                Kata kunci opsional untuk memperketat penyaringan data di dashboard. Jika kolom ini dikosongkan, sistem akan otomatis menampilkan semua data yang cocok dengan <strong>Kata Kunci Pencarian (Scraping)</strong> di atas.
                            </p>
                            <input 
                                wire:model="contextKeywords" 
                                type="text" 
                                placeholder="Contoh: Soekarno, Hatta, Sudirman (Kosongkan jika tidak ingin disaring ganda)"
                                class="w-full bg-[#F8F9FA] border border-slate-350 focus:border-primary focus:ring-1 focus:ring-primary rounded-custom px-4 py-3 text-sm text-slate-855 placeholder-[#727785] transition"
                            >
                            @error('contextKeywords') <span class="text-red-500 text-xs font-medium block mt-1">{{ $message }}</span> @enderror
                            <p class="text-[10px] text-slate-400 mt-1">Pisahkan dengan koma.</p>
                        </div>

                        <!-- Dikecualikan Column (Kata Kunci Pengecualian) -->
                        <div class="space-y-2">
                            <div class="flex items-center gap-1.5">
                                <label class="text-sm font-bold text-slate-800">Kata Kunci Pengecualian (Dikecualikan)</label>
                                <span class="text-[9px] font-bold bg-slate-100 text-slate-400 px-1.5 py-0.5 rounded-full uppercase">Opsional</span>
                            </div>
                            <p class="text-xs text-slate-400 leading-tight">Penyebutan tidak akan dikumpulkan jika mengandung kata kunci ini.</p>
                            <input 
                                wire:model="excludeKeywords" 
                                type="text" 
                                placeholder="Contoh: hoaks, fiksi, mitos"
                                class="w-full bg-[#F8F9FA] border border-slate-350 focus:border-primary focus:ring-1 focus:ring-primary rounded-custom px-4 py-3 text-sm text-slate-855 placeholder-[#727785] transition"
                            >
                            @error('excludeKeywords') <span class="text-red-500 text-xs font-medium block mt-1">{{ $message }}</span> @enderror
                            <p class="text-[10px] text-slate-400 mt-1">Pisahkan dengan koma.</p>
                        </div>
                    </div>

                    <!-- Footer buttons -->
                    <div class="flex justify-end space-x-3 px-8 py-4 border-t border-slate-200 shrink-0 bg-white">
                        <button 
                            type="button" 
                            wire:click="close"
                            class="px-6 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-custom text-sm transition-all cursor-pointer"
                        >
                            Batal
                        </button>
                        <button 
                            type="submit" 
                            style="background-color: #1fa387;"
                            class="px-6 py-2.5 hover:opacity-90 text-white font-bold rounded-custom text-sm transition-all cursor-pointer flex items-center justify-center gap-2 disabled:opacity-75 disabled:cursor-not-allowed"
                            wire:loading.attr="disabled"
                            wire:target="updateProject"
                        >
                            <span wire:loading.remove wire:target="updateProject">Simpan Perubahan</span>
                            <span wire:loading.flex wire:target="updateProject" class="items-center justify-center gap-2">
                                <svg class="animate-spin h-4 w-4 text-white shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>Menyimpan...</span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
