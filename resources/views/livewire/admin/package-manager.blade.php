<div>
    {{-- ─── Flash Notification ─────────────────────────────────────────── --}}
    @if($flash)
    <div class="mb-6 flex items-center gap-3 rounded-2xl px-6 py-4 text-sm font-semibold shadow-lg backdrop-blur-md animate-fade-in
        {{ $flashType === 'success' ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-600' : 'bg-rose-500/10 border border-rose-500/20 text-rose-600' }}">
        <span class="material-symbols-outlined text-[20px]">{{ $flashType === 'success' ? 'check_circle' : 'error' }}</span>
        <span class="flex-1">{{ $flash }}</span>
        <button wire:click="dismissFlash" class="hover:opacity-75 transition-opacity duration-150">
            <span class="material-symbols-outlined text-[18px]">close</span>
        </button>
    </div>
    @endif

    {{-- ─── DELETE CONFIRM MODAL ───────────────────────────────────────── --}}
    @if($confirmDeleteId)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 backdrop-blur-md transition-all duration-300" wire:click.self="cancelDelete">
        <div class="bg-white/95 rounded-3xl shadow-2xl p-8 w-full max-w-md mx-4 border border-slate-100/80 transform scale-100 transition-all">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 rounded-2xl bg-rose-50 flex items-center justify-center text-rose-500 shadow-sm border border-rose-100/50">
                    <span class="material-symbols-outlined text-[26px]">delete_forever</span>
                </div>
                <div>
                    <h3 class="text-lg font-black text-slate-800 tracking-tight">Hapus Paket?</h3>
                    <p class="text-xs text-slate-400 font-medium">Tindakan ini tidak bisa dibatalkan</p>
                </div>
            </div>
            <p class="text-slate-600 text-sm mb-6 leading-relaxed">Paket ini akan dihapus secara permanen beserta semua konfigurasi actor dan biaya override di dalamnya.</p>
            <div class="flex gap-3 justify-end">
                <button wire:click="cancelDelete" class="px-5 py-3 rounded-xl text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition-all duration-200 active:scale-95">Batal</button>
                <button wire:click="deletePackage" class="px-5 py-3 rounded-xl text-xs font-black text-white bg-rose-500 hover:bg-rose-600 transition-all duration-200 shadow-lg shadow-rose-500/20 active:scale-95 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[16px]">delete</span> Hapus Paket
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- VIEW: LIST (GRID CARD PREMIUM)                                      --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    @if($view === 'list')
    <div>
        {{-- Header Section --}}
        <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
            <div class="text-left">
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Konfigurasi Sistem</p>
                <h1 class="text-2xl font-black text-slate-900 mt-1 tracking-tight">Manajemen Paket</h1>
                <p class="text-xs text-slate-500 mt-1">Buat bundel actor Apify dan kelola biaya per run yang di-override khusus per paket.</p>
            </div>
            <button wire:click="createPackage"
                class="flex items-center gap-2 px-6 py-3 rounded-2xl bg-[#1fa387] text-white text-xs font-black hover:bg-[#178a71] shadow-lg shadow-[#1fa387]/20 transition-all duration-200 active:scale-95 cursor-pointer">
                <span class="material-symbols-outlined text-[18px]">add_box</span>
                <span>Buat Paket Baru</span>
            </button>
        </div>

        {{-- Search Input (Premium Styling) --}}
        <div class="mb-6 relative max-w-md">
            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-[20px]">search</span>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari paket berdasarkan nama atau deskripsi..."
                class="w-full pl-11 pr-4 py-3 rounded-2xl border border-slate-200 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-[#1fa387]/40 focus:border-[#1fa387] shadow-sm transition-all duration-200" />
        </div>

        {{-- Grid Cards Layout --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($packages as $pkg)
            <div class="bg-white rounded-3xl border border-slate-200/80 p-6 flex flex-col justify-between shadow-[0_4px_20px_-2px_rgba(0,0,0,0.02)] hover:shadow-[0_12px_30px_rgba(0,0,0,0.06)] hover:border-slate-350 transition-all duration-300 group">
                <div>
                    {{-- Badge & Actions --}}
                    <div class="flex items-center justify-between mb-4">
                        @if($pkg->is_active)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black bg-emerald-500/10 text-emerald-600 border border-emerald-500/10">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Aktif
                        </span>
                        @else
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500 border border-slate-200/50">
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Nonaktif
                        </span>
                        @endif

                        <div class="flex items-center gap-1.5">
                            <button wire:click="editPackage({{ $pkg->id }})" title="Edit Paket" class="p-2 rounded-xl bg-slate-50 border border-slate-100 text-slate-500 hover:text-amber-600 hover:bg-amber-50 hover:border-amber-100/50 transition-all duration-150 active:scale-90 cursor-pointer">
                                <span class="material-symbols-outlined text-[16px] block">edit</span>
                            </button>
                            <button wire:click="confirmDelete({{ $pkg->id }})" title="Hapus Paket" class="p-2 rounded-xl bg-slate-50 border border-slate-100 text-slate-500 hover:text-red-600 hover:bg-red-50 hover:border-red-100/50 transition-all duration-150 active:scale-90 cursor-pointer">
                                <span class="material-symbols-outlined text-[16px] block">delete</span>
                            </button>
                        </div>
                    </div>

                    {{-- Title --}}
                    <h3 class="text-base font-black text-slate-900 leading-tight tracking-tight group-hover:text-[#1fa387] transition-colors duration-200">{{ $pkg->name }}</h3>
                    <p class="text-slate-400 text-xs mt-2 line-clamp-2 leading-relaxed min-h-[32px]">{{ $pkg->description ?: 'Tidak ada deskripsi paket.' }}</p>
                </div>

                {{-- Stats and Manage Trigger --}}
                <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-4 text-xs font-bold text-slate-600">
                        <div class="flex items-center gap-1" title="Jumlah Actor Aktif">
                            <span class="material-symbols-outlined text-[18px] text-[#1fa387]">smart_toy</span>
                            <span>{{ $pkg->actors_count ?? 0 }}</span>
                        </div>
                        <div class="flex items-center gap-1" title="Proyek yang menggunakan">
                            <span class="material-symbols-outlined text-[18px] text-blue-400">folder</span>
                            <span>{{ $pkg->projects_count ?? 0 }}</span>
                        </div>
                        <div class="flex items-center gap-1" title="Portal News">
                            <span class="material-symbols-outlined text-[18px] {{ ($pkg->use_portal ?? true) ? 'text-emerald-500' : 'text-slate-300' }}">newspaper</span>
                            <span>{{ ($pkg->use_portal ?? true) ? 'Portal on' : 'Portal off' }}</span>
                        </div>
                    </div>

                    <button wire:click="manageActors({{ $pkg->id }})"
                        class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-black bg-[#1fa387]/10 text-[#1fa387] hover:bg-[#1fa387] hover:text-white border border-[#1fa387]/10 transition-all duration-200 cursor-pointer active:scale-95">
                        <span class="material-symbols-outlined text-[16px]">tune</span>
                        <span>Atur Actor</span>
                    </button>
                </div>
            </div>
            @empty
            <div class="col-span-full bg-white rounded-3xl border border-slate-200/80 p-16 text-center shadow-sm">
                <div class="w-16 h-16 rounded-2xl bg-slate-50 flex items-center justify-center text-slate-300 mx-auto border border-slate-100 mb-4">
                    <span class="material-symbols-outlined text-4xl">inventory_2</span>
                </div>
                <h4 class="text-sm font-black text-slate-800 mb-1">Belum Ada Paket Tersedia</h4>
                <p class="text-xs text-slate-400 max-w-xs mx-auto leading-relaxed mb-6">Paket membantu memetakan actor Apify dan biaya per proyek secara modular.</p>
                <button wire:click="createPackage"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#1fa387] text-white text-xs font-bold hover:bg-[#178a71] transition-all cursor-pointer">
                    <span class="material-symbols-outlined text-[16px]">add</span> Buat Paket Pertama
                </button>
            </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        @if($packages->hasPages())
        <div class="mt-8">
            {{ $packages->links() }}
        </div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- VIEW: FORM (Create / Edit)                                          --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    @elseif($view === 'form')
    <div class="fixed inset-0 z-40 bg-slate-950/35 backdrop-blur-sm flex items-center justify-center p-4" wire:click.self="cancelForm">
        <div class="w-full max-w-5xl max-h-[90vh] bg-white rounded-[28px] border border-slate-200/80 shadow-[0_24px_70px_rgba(15,23,42,0.18)] flex flex-col overflow-hidden">
            <div class="flex items-start justify-between gap-4 px-8 py-6 border-b border-slate-100 shrink-0">
                <div class="flex items-center gap-4">
                    <button wire:click="cancelForm" class="p-2.5 rounded-2xl bg-white border border-slate-200 text-slate-500 hover:text-slate-900 hover:border-slate-350 hover:shadow-sm transition-all duration-200 cursor-pointer">
                        <span class="material-symbols-outlined text-[20px] block">arrow_back</span>
                    </button>
                    <div>
                        <h1 class="text-2xl font-black text-slate-900 tracking-tight">
                            {{ $editingPackageId ? 'Edit Parameter Paket' : 'Buat Paket Baru' }}
                        </h1>
                        <p class="text-xs text-slate-500 mt-1">Konfigurasikan informasi dasar paket di bawah ini.</p>
                    </div>
                </div>
                <button wire:click="cancelForm" class="p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-all duration-150 cursor-pointer">
                    <span class="material-symbols-outlined text-[20px] block">close</span>
                </button>
            </div>

            <div class="overflow-y-auto px-8 py-6 space-y-6">
            {{-- Nama --}}
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-black text-slate-700 uppercase tracking-wider">Nama Paket</label>
                    <span class="text-[9px] font-black text-red-500 bg-red-50 border border-red-100 rounded-full px-2 py-0.5">Wajib</span>
                </div>
                <input wire:model="name" type="text" placeholder="Contoh: Paket VIP Scraping Medsos"
                    class="w-full px-4 py-3 rounded-2xl border border-slate-200 text-xs focus:outline-none focus:ring-2 focus:ring-[#1fa387]/40 focus:border-[#1fa387] transition-all @error('name') border-red-300 bg-red-50/50 @enderror" />
                @error('name') <p class="text-red-500 text-xs font-semibold mt-1.5">{{ $message }}</p> @enderror
            </div>

            {{-- Deskripsi --}}
            <div>
                <label class="block text-xs font-black text-slate-700 uppercase tracking-wider mb-2">Deskripsi Paket</label>
                <textarea wire:model="description" rows="4" placeholder="Opsional — berikan rincian singkat mengenai fungsionalitas paket ini..."
                    class="w-full px-4 py-3 rounded-2xl border border-slate-200 text-xs resize-none focus:outline-none focus:ring-2 focus:ring-[#1fa387]/40 focus:border-[#1fa387] transition-all"></textarea>
                @error('description') <p class="text-red-500 text-xs font-semibold mt-1.5">{{ $message }}</p> @enderror
            </div>

            {{-- Status Switch --}}
            <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100">
                <div class="text-left">
                    <div class="text-xs font-black text-slate-800">Paket Aktif</div>
                    <div class="text-[10px] text-slate-400 mt-0.5">Hanya paket aktif yang dapat dipetakan ke Proyek monitoring.</div>
                </div>
                <button type="button" wire:click="$toggle('is_active')"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none
                    {{ $is_active ? 'bg-[#1fa387]' : 'bg-slate-200' }}">
                    <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow-md transition duration-200 ease-in-out
                        {{ $is_active ? 'translate-x-5' : 'translate-x-0' }}"></span>
                </button>
            </div>

            <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100">
                <div class="text-left">
                    <div class="text-xs font-black text-slate-800">Portal News Paket</div>
                    <div class="text-[10px] text-slate-400 mt-0.5">User bisa menentukan apakah paket ini ikut menjalankan portal atau hanya actor medsos.</div>
                </div>
                <button type="button" wire:click="$toggle('use_portal')"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out
                    {{ $use_portal ? 'bg-[#1fa387]' : 'bg-slate-200' }}">
                    <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow-md transition duration-200 ease-in-out
                        {{ $use_portal ? 'translate-x-5' : 'translate-x-0' }}"></span>
                </button>
            </div>

            <div class="space-y-4 pt-2">
                <div class="flex items-center justify-between">
                    <div class="text-left">
                        <div class="text-xs font-black text-slate-800 uppercase tracking-wider">Actor Dalam Paket</div>
                        <div class="text-[10px] text-slate-400 mt-0.5">Pilih actor yang boleh dipakai paket ini, lalu atur biaya per actor dari sini.</div>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" wire:click="enableAllActors" class="px-3 py-2 rounded-xl text-[11px] font-black bg-emerald-50 text-emerald-600 border border-emerald-100 hover:bg-emerald-500 hover:text-white transition-all cursor-pointer">
                            Aktifkan Semua
                        </button>
                        <button type="button" wire:click="disableAllActors" class="px-3 py-2 rounded-xl text-[11px] font-black bg-slate-100 text-slate-600 border border-slate-200 hover:bg-slate-200 transition-all cursor-pointer">
                            Nonaktifkan Semua
                        </button>
                    </div>
                </div>

                <div class="bg-slate-50 rounded-3xl border border-slate-100 overflow-hidden">
                    @php $groupedActors = $allActors->groupBy('platform'); @endphp

                    @forelse($groupedActors as $platform => $actors)
                        <div class="px-5 py-3 bg-white/70 border-b border-slate-100 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[16px] text-[#1fa387]">web</span>
                            <span class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">{{ $platform ?: 'Lainnya' }}</span>
                            <span class="ml-auto text-[10px] font-bold text-slate-400">{{ $actors->count() }} actor</span>
                        </div>

                        <div class="divide-y divide-slate-100">
                            @foreach($actors as $actor)
                                @php $config = $actorConfig[$actor->id] ?? ['is_enabled' => false, 'cost_per_run_usd' => '']; @endphp
                                <div wire:key="package-form-actor-{{ $actor->id }}" class="px-5 py-4 bg-white grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_180px_170px] gap-4 items-center">
                                    <div class="flex items-start gap-3">
                                        <label class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 cursor-pointer items-center">
                                            <input
                                                type="checkbox"
                                                wire:model.live="actorConfig.{{ $actor->id }}.is_enabled"
                                                class="peer sr-only"
                                            />
                                            <span class="absolute inset-0 rounded-full bg-slate-200 transition-colors duration-200 ease-in-out peer-checked:bg-[#1fa387]"></span>
                                            <span class="relative inline-block h-5 w-5 translate-x-0 rounded-full bg-white shadow-md transition duration-200 ease-in-out peer-checked:translate-x-5"></span>
                                        </label>
                                        <div class="min-w-0">
                                            <div class="text-sm font-black text-slate-800 leading-tight">{{ $actor->actor_name }}</div>
                                            <div class="text-[11px] text-slate-500 mt-1 break-all">{{ $actor->actor_slug }}</div>
                                            <div class="flex flex-wrap gap-2 mt-2">
                                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600">{{ $actor->function_type }}</span>
                                                @if($config['is_enabled'] ?? false)
                                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-600 border border-emerald-100">Aktif di paket</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-left lg:text-center">
                                        <div class="text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Biaya Default Actor</div>
                                        <div class="text-sm font-black text-slate-700">
                                            {{ $actor->maximum_cost_per_run_usd ? '$' . number_format($actor->maximum_cost_per_run_usd, 4) : '—' }}
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1">Biaya di Paket</label>
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.0001"
                                            placeholder="{{ $actor->maximum_cost_per_run_usd ? number_format($actor->maximum_cost_per_run_usd, 4) : '0.0000' }}"
                                            wire:model.lazy="actorConfig.{{ $actor->id }}.cost_per_run_usd"
                                            class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none focus:ring-2 focus:ring-[#1fa387]/25 focus:border-[#1fa387] transition-all bg-white"
                                        />
                                        <div class="text-[10px] text-slate-400 mt-1">
                                            @if($config['cost_per_run_usd'] !== '')
                                                Override aktif untuk actor ini.
                                            @else
                                                Kosong = pakai default actor.
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="px-5 py-6 text-xs text-slate-400">Belum ada actor yang tersedia.</div>
                    @endforelse
                </div>
            </div>
            </div>

            {{-- Actions --}}
            <div class="flex gap-3 px-8 py-5 border-t border-slate-100 justify-end shrink-0 bg-white">
                <button wire:click="cancelForm" class="px-5 py-3 rounded-xl text-xs font-bold text-slate-650 bg-slate-100 hover:bg-slate-200 transition-all duration-150 active:scale-95 cursor-pointer">
                    Batal
                </button>
                <button wire:click="savePackage" class="flex items-center gap-2 px-6 py-3 rounded-xl text-xs font-black text-white bg-[#1fa387] hover:bg-[#178a71] shadow-lg shadow-[#1fa387]/20 transition-all duration-200 active:scale-95 cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]">save</span>
                    <span>{{ $editingPackageId ? 'Simpan Perubahan' : 'Buat Paket' }}</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- VIEW: ACTORS (Manage actor config per package)                      --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    @elseif($view === 'actors')
    <div class="fixed inset-0 z-40 bg-slate-950/35 backdrop-blur-sm flex items-center justify-center p-4" wire:click.self="cancelActors">
        <div class="w-full max-w-6xl max-h-[90vh] bg-white rounded-[28px] border border-slate-200/80 shadow-[0_24px_70px_rgba(15,23,42,0.18)] flex flex-col overflow-hidden text-left">
            <div class="flex items-start justify-between gap-4 px-8 py-6 border-b border-slate-100 shrink-0">
                <div class="flex items-center gap-4">
                    <button wire:click="cancelActors" class="p-2.5 rounded-2xl bg-white border border-slate-200 text-slate-500 hover:text-slate-900 hover:border-slate-350 hover:shadow-sm transition-all duration-200 cursor-pointer">
                        <span class="material-symbols-outlined text-[20px] block">arrow_back</span>
                    </button>
                    <div>
                        <h1 class="text-2xl font-black text-slate-900 tracking-tight">Atur Actor & Biaya</h1>
                        <p class="text-xs text-slate-500 mt-1">
                            Mengonfigurasi paket: <span class="font-extrabold text-[#1fa387]">{{ $managingPackage?->name }}</span>
                        </p>
                    </div>
                </div>
                <button wire:click="cancelActors" class="p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-all duration-150 cursor-pointer">
                    <span class="material-symbols-outlined text-[20px] block">close</span>
                </button>
            </div>

            <div class="overflow-y-auto px-8 py-6">

        {{-- Quick Actions --}}
        <div class="flex gap-2.5 mb-5 flex-wrap">
            <button wire:click="enableAllActors" class="flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-black bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500 hover:text-white border border-emerald-500/10 transition-all duration-150 active:scale-95 cursor-pointer">
                <span class="material-symbols-outlined text-[16px]">check_box</span> Aktifkan Semua
            </button>
            <button wire:click="disableAllActors" class="flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-black bg-slate-100 text-slate-500 hover:bg-slate-200 border border-slate-200 transition-all duration-150 active:scale-95 cursor-pointer">
                <span class="material-symbols-outlined text-[16px]">check_box_outline_blank</span> Nonaktifkan Semua
            </button>
        </div>

        {{-- Actor List --}}
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-[0_4px_25px_-2px_rgba(0,0,0,0.03)] overflow-hidden mb-6">
            @php
                $groupedActors = $allActors->groupBy('platform');
            @endphp

            @forelse($groupedActors as $platform => $actors)
            {{-- Platform Group Header --}}
            <div class="px-6 py-3 bg-slate-50/70 border-b border-slate-200 flex items-center gap-2">
                <span class="material-symbols-outlined text-[16px] text-[#1fa387]">web</span>
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-500">{{ $platform ?: 'Lainnya' }}</span>
                <span class="ml-auto text-[10px] text-slate-400 font-bold bg-white border border-slate-200 rounded-full px-2.5 py-0.5">{{ $actors->count() }} actor</span>
            </div>

            @foreach($actors as $actor)
            @php $config = $actorConfig[$actor->id] ?? ['is_enabled' => false, 'cost_per_run_usd' => '']; @endphp
            <div wire:key="package-actors-actor-{{ $actor->id }}" class="flex items-center gap-4 px-6 py-5 border-b border-slate-100 hover:bg-slate-50/50 transition-colors last:border-b-0 group">
                {{-- Custom Toggle --}}
                <label class="relative inline-flex h-5 w-10 shrink-0 cursor-pointer items-center">
                    <input
                        type="checkbox"
                        wire:model.live="actorConfig.{{ $actor->id }}.is_enabled"
                        class="peer sr-only"
                    />
                    <span class="absolute inset-0 rounded-full bg-slate-200 transition-colors duration-200 ease-in-out peer-checked:bg-[#1fa387]"></span>
                    <span class="relative inline-block h-4 w-4 translate-x-0 rounded-full bg-white shadow-sm transition duration-200 ease-in-out peer-checked:translate-x-5"></span>
                </label>

                {{-- Info Actor --}}
                <div class="flex-1 min-w-0">
                    <div class="font-black text-xs text-slate-800 tracking-tight flex items-center gap-2">
                        <span>{{ $actor->actor_name }}</span>
                        <span class="inline-flex shrink-0 items-center px-2 py-0.5 rounded-full text-[9px] font-black bg-blue-50 text-blue-600 border border-blue-100 uppercase tracking-widest">
                            {{ $actor->function_type ?? 'scraper' }}
                        </span>
                    </div>
                    <div class="text-[10px] text-slate-400 font-mono mt-1 select-all truncate">{{ $actor->actor_slug }}</div>
                </div>

                {{-- Global Cost Display --}}
                <div class="hidden lg:block text-right shrink-0 px-4">
                    <div class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">Biaya Global</div>
                    <div class="text-xs font-black text-slate-600 mt-0.5">
                        {{ $actor->maximum_cost_per_run_usd ? '$' . number_format($actor->maximum_cost_per_run_usd, 4) : '—' }}
                    </div>
                </div>

                {{-- Cost Override Field --}}
                <div class="shrink-0 text-right">
                    <div class="text-[9px] text-slate-400 font-bold uppercase tracking-wider mb-1">Override Biaya Paket</div>
                    <div class="relative flex items-center">
                        <span class="absolute left-3 text-slate-400 text-xs font-bold">$</span>
                        <input
                            type="number"
                            min="0"
                            step="0.0001"
                            placeholder="{{ $actor->maximum_cost_per_run_usd ? number_format($actor->maximum_cost_per_run_usd, 4) : 'Global' }}"
                            wire:model.lazy="actorConfig.{{ $actor->id }}.cost_per_run_usd"
                            class="pl-6 pr-3 py-2 w-28 rounded-xl border text-xs font-mono transition-all focus:outline-none focus:ring-2 focus:ring-[#1fa387]/40 focus:border-[#1fa387]
                            {{ $config['is_enabled'] ? 'border-slate-200 bg-white text-slate-800' : 'border-slate-100 bg-slate-50 text-slate-350' }}" />
                    </div>
                    @if($config['cost_per_run_usd'] !== '')
                    <div class="text-[9px] text-emerald-600 font-black mt-1">↑ override aktif</div>
                    @endif
                </div>
            </div>
            @endforeach
            @empty
            <div class="px-6 py-16 text-center">
                <span class="material-symbols-outlined text-4xl text-slate-300 block mb-3">smart_toy</span>
                <p class="text-xs text-slate-400 font-bold">Tidak ada actor terdaftar.</p>
            </div>
            @endforelse
        </div>
            </div>

        {{-- Save Button Footer --}}
        <div class="flex gap-3 justify-end px-8 py-5 border-t border-slate-100 shrink-0 bg-white">
            <button wire:click="cancelActors" class="px-5 py-3 rounded-xl text-xs font-bold text-slate-655 bg-slate-100 hover:bg-slate-200 transition">
                Batal
            </button>
            <button wire:click="saveActors" class="flex items-center gap-2 px-6 py-3 rounded-xl text-xs font-black text-white bg-[#1fa387] hover:bg-[#178a71] shadow-lg shadow-[#1fa387]/20 transition active:scale-95 cursor-pointer">
                <span class="material-symbols-outlined text-[18px]">save</span>
                <span>Simpan Konfigurasi</span>
            </button>
        </div>
    </div>
    @endif
</div>
