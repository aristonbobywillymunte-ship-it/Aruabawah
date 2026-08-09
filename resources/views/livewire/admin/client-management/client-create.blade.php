<div>
    {{-- Page Header --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.clients') }}" wire:navigate
           class="cursor-pointer flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-[#1fa387] hover:bg-[#1fa387]/5 transition-colors">
            <span class="material-symbols-outlined text-[20px]">arrow_back</span>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Tambah Klien Baru</h1>
            <p class="text-slate-500 text-sm">Buat akun untuk klien Anda agar bisa mengelola proyek.</p>
        </div>
    </div>

    <div class="max-w-3xl">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <form wire:submit.prevent="createClient" class="p-6 space-y-6">

                <div class="space-y-1.5">
                    <label class="text-sm font-bold text-slate-800">Nama Klien</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">person</span>
                        <input wire:model="name" type="text" placeholder="Masukkan nama klien"
                               class="w-full pl-10 pr-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#1fa387]/20 focus:border-[#1fa387] transition-all">
                    </div>
                    @error('name') <p class="text-red-500 text-xs font-medium">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-1.5">
                    <label class="text-sm font-bold text-slate-800">Email</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">mail</span>
                        <input wire:model="email" type="email" placeholder="email@contoh.com"
                               class="w-full pl-10 pr-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#1fa387]/20 focus:border-[#1fa387] transition-all">
                    </div>
                    @error('email') <p class="text-red-500 text-xs font-medium">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-1.5">
                    <label class="text-sm font-bold text-slate-800">Password</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">lock</span>
                        <input wire:model="password" type="password" placeholder="Minimal 8 karakter"
                               class="w-full pl-10 pr-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#1fa387]/20 focus:border-[#1fa387] transition-all">
                    </div>
                    @error('password') <p class="text-red-500 text-xs font-medium">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-1.5">
                    <label class="text-sm font-bold text-slate-800">Konfirmasi Password</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">lock</span>
                        <input wire:model="password_confirmation" type="password" placeholder="Ulangi password"
                               class="w-full pl-10 pr-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#1fa387]/20 focus:border-[#1fa387] transition-all">
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex justify-end gap-3">
                    <a href="{{ route('admin.clients') }}" wire:navigate
                       class="cursor-pointer px-5 py-2.5 text-sm font-bold text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                        Batal
                    </a>
                    <button type="submit" class="cursor-pointer px-5 py-2.5 text-sm font-bold text-white bg-[#1fa387] hover:bg-[#178a71] rounded-xl transition-colors flex items-center gap-2">
                        <span wire:loading.remove wire:target="createClient" class="material-symbols-outlined text-[18px]">save</span>
                        <span wire:loading wire:target="createClient" class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span>
                        <span wire:loading.remove wire:target="createClient">Simpan Klien</span>
                        <span wire:loading wire:target="createClient">Menyimpan...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
