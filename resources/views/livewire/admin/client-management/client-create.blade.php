<div class="space-y-4">
    {{-- Back Navigation --}}
    <div class="flex items-center justify-between">
        <a href="{{ route('admin.clients') }}" wire:navigate
           class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-slate-500 hover:text-[#1fa387] hover:bg-[#1fa387]/5 text-xs font-bold transition-all">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            <span>Kembali ke Manajemen Klien</span>
        </a>
    </div>

    <div class="max-w-3xl">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden" x-data="{ showPassword: false, showPasswordConfirmation: false }">
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
                        <input wire:model="password" :type="showPassword ? 'text' : 'password'" placeholder="Minimal 8 karakter"
                               class="w-full pl-10 pr-10 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#1fa387]/20 focus:border-[#1fa387] transition-all">
                        <button type="button"
                                @click="showPassword = !showPassword"
                                tabindex="-1"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none cursor-pointer flex items-center p-1"
                                title="Tampilkan / Sembunyikan Password">
                            <span class="material-symbols-outlined text-[18px]" x-text="showPassword ? 'visibility_off' : 'visibility'">visibility</span>
                        </button>
                    </div>
                    @error('password') <p class="text-red-500 text-xs font-medium">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-1.5">
                    <label class="text-sm font-bold text-slate-800">Konfirmasi Password</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">lock</span>
                        <input wire:model="password_confirmation" :type="showPasswordConfirmation ? 'text' : 'password'" placeholder="Ulangi password"
                               class="w-full pl-10 pr-10 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#1fa387]/20 focus:border-[#1fa387] transition-all">
                        <button type="button"
                                @click="showPasswordConfirmation = !showPasswordConfirmation"
                                tabindex="-1"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none cursor-pointer flex items-center p-1"
                                title="Tampilkan / Sembunyikan Password">
                            <span class="material-symbols-outlined text-[18px]" x-text="showPasswordConfirmation ? 'visibility_off' : 'visibility'">visibility</span>
                        </button>
                    </div>
                </div>

                {{-- Paket yang Diizinkan untuk Klien --}}
                <div class="space-y-3 pt-2">
                    <div>
                        <label class="text-sm font-bold text-slate-800">Pilihan Paket Monitoring</label>
                        <p class="text-xs text-slate-500 mt-0.5">Tentukan paket mana saja yang boleh dipilih atau digunakan oleh klien ini.</p>
                    </div>

                    @if($packages->isEmpty())
                        <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl text-amber-700 text-xs">
                            Belum ada paket monitoring aktif di sistem. Silakan buat atau aktifkan paket di menu Master Paket terlebih dahulu.
                        </div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            @foreach($packages as $package)
                                @php
                                    $isPkgChecked = in_array($package->id, $selectedPackages);
                                @endphp
                                <label class="flex items-start gap-3 p-4 border rounded-xl cursor-pointer transition-all {{ $isPkgChecked ? 'border-[#1fa387] bg-[#1fa387]/5 shadow-sm' : 'border-slate-200 hover:bg-slate-50' }}">
                                    <input wire:model="selectedPackages" type="checkbox" value="{{ $package->id }}" class="mt-1 w-4 h-4 text-[#1fa387] rounded border-slate-300 focus:ring-[#1fa387]">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-sm font-bold text-slate-800 truncate">{{ $package->name }}</span>
                                            <span class="text-xs font-bold text-[#1fa387] shrink-0">
                                                {{ ($package->price ?? 0) > 0 ? 'Rp ' . number_format($package->price, 0, ',', '.') : 'Gratis / Khusus' }}
                                            </span>
                                        </div>
                                        <div class="text-[11px] text-slate-500 mt-1 flex flex-wrap gap-x-3 gap-y-1">
                                            <span>Maks. Proyek: <strong>{{ $package->max_projects ?? 'Unlimited' }}</strong></span>
                                            <span>Keyword: <strong>{{ $package->max_keywords_per_project ?? 'Unlimited' }}</strong></span>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    @error('selectedPackages') <p class="text-red-500 text-xs font-medium mt-1">{{ $message }}</p> @enderror
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
