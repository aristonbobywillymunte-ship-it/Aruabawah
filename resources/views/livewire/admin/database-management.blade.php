<div class="space-y-6">
    <!-- Sleek Title Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900">Manajemen Database</h1>
            <p class="mt-1 text-sm text-slate-500">Ekspor berkas cadangan atau pulihkan seluruh data sistem secara instan.</p>
        </div>
    </div>

    <!-- Main Grid Operations -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Card 1: Download/Backup -->
        <div class="flex flex-col justify-between rounded-3xl border border-slate-200/80 bg-white p-8 shadow-sm transition hover:shadow-md">
            <div class="space-y-6">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                        <span class="material-symbols-outlined text-[26px]">download</span>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Unduh Database (Export)</h2>
                        <p class="text-xs text-slate-400">Salin skema struktur & data records ke berkas SQL</p>
                    </div>
                </div>

                <p class="text-sm leading-relaxed text-slate-600">
                    Menghasilkan file salinan database lengkap berformat SQL. Proses ini aman dijalankan kapan saja dan tidak akan mengganggu aktivitas pengguna atau perayapan data yang sedang berjalan.
                </p>

                <div class="space-y-2 rounded-2xl bg-slate-50 p-4 border border-slate-100">
                    <div class="flex items-center gap-3 text-xs text-slate-500">
                        <span class="material-symbols-outlined text-[16px] text-slate-400">info</span>
                        <span>Format File: SQL Plain Text (.sql)</span>
                    </div>
                    <div class="flex items-center gap-3 text-xs text-slate-500">
                        <span class="material-symbols-outlined text-[16px] text-slate-400">layers</span>
                        <span>Mencakup seluruh tabel, indeks, relasi, dan baris data</span>
                    </div>
                </div>
            </div>
            
            <div class="mt-8 pt-6 border-t border-slate-100">
                <button 
                    wire:click="download" 
                    wire:loading.attr="disabled"
                    class="w-full flex items-center justify-center gap-2 rounded-2xl bg-[#1fa387] px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-[#1fa387]/15 hover:bg-[#1a8e75] active:scale-[0.98] transition-all duration-250"
                >
                    <span wire:loading.remove wire:target="download" class="material-symbols-outlined text-[20px]">download</span>
                    <span wire:loading wire:target="download" class="animate-spin material-symbols-outlined text-[20px]">sync</span>
                    <span>Mulai Unduh Database</span>
                </button>
            </div>
        </div>

        <!-- Card 2: Upload/Restore -->
        <div class="flex flex-col justify-between rounded-3xl border border-slate-200/80 bg-white p-8 shadow-sm transition hover:shadow-md">
            <div class="space-y-6">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
                        <span class="material-symbols-outlined text-[26px]">upload</span>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Pulihkan Database (Import)</h2>
                        <p class="text-xs text-slate-400">Unggah berkas SQL untuk memulihkan keadaan data</p>
                    </div>
                </div>

                <!-- Danger Alert Notice -->
                <div class="rounded-2xl border border-red-100 bg-red-50/40 p-4 text-xs text-red-800 space-y-1.5">
                    <div class="flex items-center gap-2 font-bold text-red-950">
                        <span class="material-symbols-outlined text-[16px] text-red-600">warning</span>
                        <span>Peringatan Penghapusan Data</span>
                    </div>
                    <p class="leading-relaxed text-red-700/90">
                        Proses ini akan **menghapus seluruh tabel dan data aktif saat ini (CASCADE)** sebelum memulihkan data dari berkas SQL baru.
                    </p>
                </div>

                <!-- Livewire File Dropzone Area -->
                <div class="space-y-2">
                    <div class="relative flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50 p-6 hover:bg-slate-50 transition cursor-pointer group">
                        <input 
                            type="file" 
                            wire:model="databaseFile" 
                            id="db-file-input"
                            accept=".sql"
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" 
                        />
                        <span class="material-symbols-outlined text-slate-400 group-hover:text-[#1fa387] text-[32px] mb-2 transition">upload_file</span>
                        @if ($databaseFile)
                            <div class="text-sm font-bold text-[#1fa387]">{{ $databaseFile->getClientOriginalName() }}</div>
                            <div class="text-xs text-slate-500">Berkas siap diunggah</div>
                        @else
                            <div class="text-sm font-semibold text-slate-700">Klik atau seret file SQL ke sini</div>
                            <div class="text-xs text-slate-400 mt-0.5">Berkas SQL (.sql) maksimal 50MB</div>
                        @endif
                    </div>
                    @error('databaseFile') 
                        <div class="text-xs text-red-500 mt-1 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[14px]">error</span>
                            <span>{{ $message }}</span>
                        </div> 
                    @enderror
                </div>
            </div>

            <!-- Import Action Button -->
            <div class="mt-8 pt-6 border-t border-slate-100 space-y-3">
                <!-- Upload/Processing Loading Progress -->
                <div wire:loading wire:target="databaseFile" class="w-full text-center py-1">
                    <div class="flex items-center justify-center gap-2 text-xs font-semibold text-[#1fa387]">
                        <span class="animate-spin material-symbols-outlined text-[16px]">sync</span>
                        <span>Mengirim berkas cadangan ke server...</span>
                    </div>
                </div>

                <button 
                    wire:click="import" 
                    wire:loading.attr="disabled"
                    @if(!$databaseFile) disabled @endif
                    class="w-full flex items-center justify-center gap-2 rounded-2xl px-6 py-3.5 text-sm font-semibold text-white transition-all duration-250 shadow-md @if($databaseFile) bg-amber-600 shadow-amber-600/10 hover:bg-amber-700 active:scale-[0.98] @else bg-slate-200 text-slate-400 cursor-not-allowed shadow-none @endif"
                    onclick="return confirm('Apakah Anda yakin ingin memulihkan database dari berkas ini? Seluruh data aktif di web saat ini akan dihapus permanen.') || event.stopImmediatePropagation()"
                >
                    <span wire:loading.remove wire:target="import" class="material-symbols-outlined text-[20px]">upload</span>
                    <span wire:loading wire:target="import" class="animate-spin material-symbols-outlined text-[20px]">sync</span>
                    <span>Impor & Pulihkan Database</span>
                </button>
            </div>
        </div>
    </div>
</div>
