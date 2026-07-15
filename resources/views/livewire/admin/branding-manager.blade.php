<div class="mx-auto w-full max-w-7xl space-y-6 font-sans">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-200 pb-5">
        <div class="text-left">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Panel Administrator</p>
            <h1 class="text-2xl font-black text-slate-900 mt-1">Branding Aplikasi</h1>
            <p class="text-xs text-slate-500 mt-1">Kustomisasi nama dan logo aplikasi Anda secara dinamis.</p>
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-3">
        <!-- Left Side: Form -->
        <div class="md:col-span-2 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm text-left">
            <form wire:submit.prevent="save" class="space-y-6">
                <!-- App Name Input -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Nama Aplikasi</label>
                    <input 
                        wire:model="app_name" 
                        type="text" 
                        class="h-11 w-full rounded-xl border border-slate-200 px-4 text-sm font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm"
                        placeholder="ARUSBAWAH"
                    >
                    @error('app_name') <p class="mt-1.5 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                </div>

                <!-- App Logo Upload -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Logo Aplikasi (Custom)</label>
                    <div class="flex items-center gap-4">
                        <input 
                            wire:model="app_logo" 
                            type="file" 
                            id="logoUploadInput"
                            class="hidden"
                            accept="image/*"
                        >
                        <label 
                            for="logoUploadInput" 
                            class="inline-flex h-11 items-center justify-center gap-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 text-xs font-bold transition shadow-sm cursor-pointer border border-slate-200"
                        >
                            <span class="material-symbols-outlined text-[18px]">upload</span>
                            <span>Pilih File Gambar</span>
                        </label>

                        @if($current_logo_path)
                            <button 
                                type="button"
                                wire:click="deleteLogo"
                                class="inline-flex h-11 items-center justify-center gap-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 px-4 text-xs font-bold transition border border-rose-100 cursor-pointer"
                            >
                                <span class="material-symbols-outlined text-[18px]">delete</span>
                                <span>Hapus Logo Kustom</span>
                            </button>
                        @endif
                    </div>
                    @error('app_logo') <p class="mt-1.5 text-xs font-bold text-rose-600">{{ $message }}</p> @enderror
                    <p class="text-[10px] text-slate-400 mt-2">Dukungan format gambar: JPG, PNG, GIF, SVG (Maks. 2MB).</p>
                </div>

                <!-- Submit Button -->
                <div class="pt-4 border-t border-slate-100 flex justify-end">
                    <button 
                        type="submit" 
                        class="inline-flex h-11 items-center justify-center gap-1.5 rounded-xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-6 text-xs font-bold transition shadow-sm cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[18px]">save</span>
                        <span>Simpan Branding</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Right Side: Preview -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between text-left">
            <div>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-4">Preview Header Aktif</h2>
                <div class="border border-slate-200/80 rounded-2xl p-4 bg-slate-50 flex items-center justify-center min-h-[120px]">
                    <div class="flex items-center gap-2">
                        @if($app_logo)
                            <!-- File Upload Temporary Preview -->
                            <img src="{{ $app_logo->temporaryUrl() }}" class="h-8 max-w-[120px] object-contain">
                        @elseif($current_logo_path)
                            <!-- Saved Custom Logo -->
                            <img src="{{ asset('storage/' . $current_logo_path) }}" class="h-8 max-w-[120px] object-contain">
                        @else
                            <!-- Default Red SVG Logo -->
                            <svg width="28" height="28" viewBox="0 0 42 42" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <polygon points="21,4 39,38 3,38" fill="none" stroke="#c0392b" stroke-width="4" stroke-linejoin="round"/>
                                <line x1="11" y1="28" x2="31" y2="28" stroke="#c0392b" stroke-width="4" stroke-linecap="round"/>
                            </svg>
                        @endif

                        <div class="flex flex-col text-left leading-none">
                            <span class="text-sm font-black tracking-wider text-slate-800 uppercase">{{ $app_name ?: 'ARUSBAWAH' }}</span>
                            <span class="text-[7.5px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">Media Intelligence</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-6 bg-slate-50/50 border border-slate-100 p-4 rounded-2xl">
                <span class="text-[10px] font-bold text-slate-400 block mb-1">Status Storage Symlink</span>
                <p class="text-[10px] text-slate-500 leading-relaxed">Pastikan folder public storage ditautkan agar logo kustom dapat dimuat di browser dengan benar (`php artisan storage:link`).</p>
            </div>
        </div>
    </div>
</div>
