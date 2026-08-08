<div class="p-6 max-w-4xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.clients') }}" wire:navigate class="text-slate-400 hover:text-[#1fa387] transition-colors">
                <span class="material-symbols-outlined text-[20px]">arrow_back</span>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Pengaturan Klien: {{ $client->name }}</h1>
                <p class="text-slate-500 text-sm mt-1">Atur hak akses, batas sumber daya, dan ketersediaan paket.</p>
            </div>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="mb-6 p-4 rounded-xl bg-green-50 text-green-700 border border-green-200 flex items-center gap-3">
            <span class="material-symbols-outlined text-[20px]">check_circle</span>
            <span class="text-sm font-medium">{{ session('message') }}</span>
        </div>
    @endif

    <form wire:submit.prevent="saveSettings" class="space-y-6">
        
        <!-- Izin Dasar -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-slate-50">
                <h2 class="text-sm font-bold text-slate-800">Hak Akses Proyek</h2>
            </div>
            <div class="p-6 space-y-4">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input wire:model="can_create_projects" type="checkbox" class="w-5 h-5 text-[#1fa387] rounded border-slate-300 focus:ring-[#1fa387]">
                    <div>
                        <div class="text-sm font-bold text-slate-800">Izinkan Membuat Proyek</div>
                        <div class="text-xs text-slate-500">Klien dapat membuat proyek baru sendiri.</div>
                    </div>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input wire:model="can_edit_projects" type="checkbox" class="w-5 h-5 text-[#1fa387] rounded border-slate-300 focus:ring-[#1fa387]">
                    <div>
                        <div class="text-sm font-bold text-slate-800">Izinkan Mengedit Proyek</div>
                        <div class="text-xs text-slate-500">Klien dapat mengedit nama dan kata kunci proyeknya.</div>
                    </div>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input wire:model="can_delete_projects" type="checkbox" class="w-5 h-5 text-red-500 rounded border-slate-300 focus:ring-red-500">
                    <div>
                        <div class="text-sm font-bold text-slate-800">Izinkan Menghapus Proyek</div>
                        <div class="text-xs text-slate-500">Klien dapat menonaktifkan/menghapus proyeknya.</div>
                    </div>
                </label>
            </div>
        </div>

        <!-- Batas Sumber Daya -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-slate-50">
                <h2 class="text-sm font-bold text-slate-800">Batas Sumber Daya Khusus Klien</h2>
                <p class="text-xs text-slate-500 mt-1">Kosongkan jika ingin menggunakan batas default dari paket.</p>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-1.5">
                    <label class="text-sm font-bold text-slate-800">Maksimal Proyek</label>
                    <input wire:model="max_projects" type="number" min="1" placeholder="Batas jumlah proyek" class="w-full px-4 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#1fa387]/20 focus:border-[#1fa387]">
                    @error('max_projects') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="space-y-1.5">
                    <label class="text-sm font-bold text-slate-800">Maksimal Kata Kunci / Proyek</label>
                    <input wire:model="max_keywords_per_project" type="number" min="1" placeholder="Batas kata kunci per proyek" class="w-full px-4 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#1fa387]/20 focus:border-[#1fa387]">
                    @error('max_keywords_per_project') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <!-- Paket yang Diizinkan -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-slate-50">
                <h2 class="text-sm font-bold text-slate-800">Paket yang Tersedia (Whitelist)</h2>
                <p class="text-xs text-slate-500 mt-1">Pilih paket mana saja yang boleh dipilih oleh klien ini saat membuat proyek.</p>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach($packages as $package)
                    <label class="flex items-start gap-3 p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition-colors {{ in_array($package->id, $allowedPackages) ? 'border-[#1fa387] bg-[#1fa387]/5' : '' }}">
                        <input wire:model="allowedPackages" type="checkbox" value="{{ $package->id }}" class="mt-1 w-5 h-5 text-[#1fa387] rounded border-slate-300 focus:ring-[#1fa387]">
                        <div>
                            <div class="text-sm font-bold text-slate-800">{{ $package->name }}</div>
                            <div class="text-xs font-medium text-[#1fa387] mt-0.5">Rp {{ number_format($package->price, 0, ',', '.') }}</div>
                            <div class="text-xs text-slate-500 mt-1">
                                Limit Proyek: {{ $package->max_projects ?? '∞' }} | Limit KW: {{ $package->max_keywords_per_project ?? '∞' }}
                            </div>
                        </div>
                    </label>
                @endforeach
            </div>
            @error('allowedPackages') <p class="text-red-500 text-xs mt-1 px-6 pb-4">{{ $message }}</p> @enderror
        </div>

        <div class="flex justify-end gap-3">
            <button type="submit" class="px-6 py-2.5 text-sm font-bold text-white bg-[#1fa387] hover:bg-[#178a71] rounded-xl transition-colors flex items-center gap-2">
                <span wire:loading.remove wire:target="saveSettings" class="material-symbols-outlined text-[18px]">save</span>
                <span wire:loading wire:target="saveSettings" class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span>
                <span>Simpan Pengaturan</span>
            </button>
        </div>

    </form>
</div>
