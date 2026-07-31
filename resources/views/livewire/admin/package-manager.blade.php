<div>
    {{-- Global Loading State for UI Responsiveness --}}
    <div wire:loading.flex wire:target="createPackage, editPackage, manageActors, savePackage, saveActors" class="fixed inset-0 z-[9999] items-center justify-center bg-slate-950/20 backdrop-blur-[2px]">
        <div class="bg-white px-5 py-3 rounded-2xl shadow-xl flex items-center gap-3 border border-slate-100 animate-fade-in">
            <svg class="animate-spin h-5 w-5 text-[#1fa387]" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-xs font-bold text-slate-700">Memproses perubahan...</span>
        </div>
    </div>



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
    <div class="px-2 md:px-0">
        {{-- Header Section --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4 text-left">
            <div>
                <p class="text-[9px] md:text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Konfigurasi Sistem</p>
                <h1 class="text-xl md:text-2xl font-black text-slate-900 mt-1 tracking-tight">Manajemen Paket</h1>
                <p class="text-[11px] md:text-xs text-slate-500 mt-1 leading-relaxed">Buat bundel actor Apify dan kelola biaya per run yang di-override khusus per paket.</p>
            </div>
            <button wire:click="createPackage"
                class="self-start md:self-auto flex items-center gap-2 px-4 md:px-6 py-2.5 md:py-3 rounded-xl md:rounded-2xl bg-[#1fa387] text-white text-[11px] md:text-xs font-black hover:bg-[#178a71] shadow-md hover:shadow-lg shadow-[#1fa387]/20 transition-all duration-200 active:scale-95 cursor-pointer">
                <span class="material-symbols-outlined text-[16px] md:text-[18px]">add_box</span>
                <span>Buat Paket Baru</span>
            </button>
        </div>

        {{-- Search Input (Premium Styling) --}}
        <div class="mb-6 relative w-full max-w-full md:max-w-md flex items-center">
            <span class="material-symbols-outlined absolute left-4 text-slate-400 text-[18px] md:text-[20px] select-none pointer-events-none">search</span>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari paket berdasarkan nama atau deskripsi..." style="padding-left: 2.75rem;"
                class="w-full pr-4 py-2.5 md:py-3 rounded-xl md:rounded-2xl border border-slate-200 text-[11px] md:text-xs bg-white focus:outline-none focus:ring-2 focus:ring-[#1fa387]/40 focus:border-[#1fa387] shadow-sm transition-all duration-200" />
        </div>

        {{-- Grid Cards Layout --}}
        @php
            $maxPrice = $packages->max('price');
        @endphp
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
            @forelse($packages as $pkg)
            @php
                $isPremiumTheme = ($pkg->price == $maxPrice && $packages->count() > 1) || str_contains(strtolower($pkg->name), 'enterprise') || str_contains(strtolower($pkg->name), 'vip');
            @endphp

            @if($isPremiumTheme)
            {{-- ══════════════════════════════════════ --}}
            {{-- PREMIUM CARD — Light SaaS (Enterprise) --}}
            {{-- ══════════════════════════════════════ --}}
            <div class="relative bg-white rounded-2xl md:rounded-3xl flex flex-col justify-between overflow-hidden
                        border border-[#1fa387]/15
                        shadow-xl shadow-[#1fa387]/5
                        hover:shadow-2xl hover:shadow-[#1fa387]/10 hover:-translate-y-1
                        transition-all duration-300 group">

                {{-- Top gradient accent bar --}}
                <div class="h-1.5 w-full bg-gradient-to-r from-[#1fa387] via-emerald-400 to-teal-500"></div>

                <div class="p-6 md:p-7 flex flex-col flex-1">
                    {{-- Top Row: Badges + Actions --}}
                    <div class="flex justify-between items-start mb-5">
                        <div class="flex flex-wrap items-center gap-1.5">
                            {{-- Terpopuler badge (hanya jika is_popular = true) --}}
                            @if($pkg->is_popular)
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest bg-amber-400 text-white shadow-sm">
                                ⭐ Terpopuler
                            </span>
                            @endif
                            {{-- Status badge --}}
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest
                                {{ $pkg->is_active ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-slate-100 text-slate-400 border border-slate-200' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $pkg->is_active ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' }}"></span>
                                {{ $pkg->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>
                        {{-- Action Buttons --}}
                        <div class="flex items-center gap-1.5 shrink-0">
                            <button wire:click="editPackage({{ $pkg->id }})" title="Edit Paket"
                                class="p-1.5 rounded-lg bg-slate-50 border border-slate-200 text-slate-400 hover:text-[#1fa387] hover:bg-[#1fa387]/8 hover:border-[#1fa387]/30 transition-all duration-200 cursor-pointer">
                                <span class="material-symbols-outlined text-[14px] block">edit</span>
                            </button>
                            <button wire:click="confirmDelete({{ $pkg->id }})" title="Hapus Paket"
                                class="p-1.5 rounded-lg bg-slate-50 border border-slate-200 text-slate-400 hover:text-rose-500 hover:bg-rose-50 hover:border-rose-200 transition-all duration-200 cursor-pointer">
                                <span class="material-symbols-outlined text-[14px] block">delete</span>
                            </button>
                        </div>
                    </div>

                    {{-- Plan Name & Price --}}
                    <div class="mb-5">
                        <p class="text-[12px] font-black uppercase tracking-[0.25em] text-[#1fa387] mb-1.5">{{ $pkg->name }}</p>
                        <div class="flex items-end gap-1.5 mb-2">
                            @if($pkg->price > 0)
                                <span class="text-3xl md:text-4xl font-black text-slate-700 leading-none">Rp {{ number_format($pkg->price, 0, ',', '.') }}</span>
                                <span class="text-slate-400 text-xs font-semibold pb-1">/bulan</span>
                            @else
                                <span class="text-2xl md:text-3xl font-black text-slate-700 leading-none">Hubungi Kami</span>
                            @endif
                        </div>
                        <p class="text-slate-400 text-[11px] leading-relaxed">{{ $pkg->description ?: 'Solusi lengkap untuk kebutuhan monitoring & scraping skala enterprise.' }}</p>
                    </div>

                    {{-- Divider --}}
                    <div class="h-px bg-gradient-to-r from-transparent via-slate-200 to-transparent mb-4"></div>

                    {{-- Feature Categories --}}
                    <div class="space-y-4 flex-1">
                        @php
                            $hasData = !empty($pkg->advantages) || !empty($pkg->social_media_features) || !empty($pkg->news_portal_features);
                            $advantagesFallback = ['Proyek Tak Terbatas', 'Kata Kunci Tak Terbatas', '500.000 Penyebutan /bln', 'Pengguna Tak Terbatas'];
                        @endphp

                        {{-- Fitur Sosial Media --}}
                        @php $socialList = $hasData ? ($pkg->social_media_features ?? []) : []; @endphp
                        @if(!empty($socialList))
                        <div>
                            <div class="flex items-center gap-1.5 mb-2">
                                <span class="material-symbols-outlined text-[11px] text-sky-500">share</span>
                                <span class="text-[8px] font-black uppercase tracking-[0.15em] text-slate-400">Sosial Media & Keunggulan</span>
                            </div>
                            <div class="space-y-1.5">
                                @foreach($socialList as $feat)
                                <div class="flex items-start gap-2">
                                    <span class="w-4 h-4 rounded-md bg-sky-50 flex items-center justify-center shrink-0 border border-sky-100 mt-0.5">
                                        <span class="material-symbols-outlined text-[9px] text-sky-500">check</span>
                                    </span>
                                    <span class="text-[11px] text-slate-600 font-medium leading-tight break-words min-w-0">{{ $feat }}</span>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- Fitur Portal Berita --}}
                        @php $portalList = $hasData ? ($pkg->news_portal_features ?? []) : []; @endphp
                        @if(!empty($portalList))
                        <div>
                            <div class="flex items-center gap-1.5 mb-2">
                                <span class="material-symbols-outlined text-[11px] text-violet-500">newspaper</span>
                                <span class="text-[8px] font-black uppercase tracking-[0.15em] text-slate-400">Portal Berita</span>
                            </div>
                            <div class="space-y-1.5">
                                @foreach($portalList as $feat)
                                <div class="flex items-start gap-2">
                                    <span class="w-4 h-4 rounded-md bg-violet-50 flex items-center justify-center shrink-0 border border-violet-100 mt-0.5">
                                        <span class="material-symbols-outlined text-[9px] text-violet-500">check</span>
                                    </span>
                                    <span class="text-[11px] text-slate-600 font-medium leading-tight break-words min-w-0">{{ $feat }}</span>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Footer CTA --}}
                <div class="px-6 md:px-7 pb-6 md:pb-7 pt-4 border-t border-[#1fa387]/10">
                    <button wire:click="manageActors({{ $pkg->id }})"
                        class="w-full flex items-center justify-center gap-2 py-3 rounded-xl text-xs font-black
                               bg-[#1fa387] text-white
                               hover:bg-[#178a71] hover:shadow-lg hover:shadow-[#1fa387]/25
                               active:scale-[0.98] transition-all duration-200 cursor-pointer">
                        <span class="material-symbols-outlined text-[15px]">tune</span>
                        <span>Atur Actor & Biaya</span>
                    </button>
                </div>
            </div>

            @else
            {{-- ══════════════════════════════════════ --}}
            {{-- STANDARD CARD — Light SaaS (Regular)  --}}
            {{-- ══════════════════════════════════════ --}}
            <div class="relative bg-white rounded-2xl md:rounded-3xl flex flex-col justify-between overflow-hidden
                        border border-[#1fa387]/15
                        shadow-sm
                        hover:shadow-lg hover:shadow-slate-200/60 hover:border-[#1fa387]/30 hover:-translate-y-0.5
                        transition-all duration-300 group">

                {{-- Hover top accent --}}
                <div class="h-1 w-full bg-gradient-to-r from-[#1fa387]/0 via-[#1fa387]/50 to-[#1fa387]/0 opacity-0 group-hover:opacity-100 transition-opacity duration-400"></div>

                <div class="p-6 md:p-7 flex flex-col flex-1">
                    {{-- Top Row: Status + Badges + Actions --}}
                    <div class="flex justify-between items-start mb-5">
                        <div class="flex flex-wrap items-center gap-1.5">
                            {{-- Terpopuler badge (hanya jika is_popular = true) --}}
                            @if($pkg->is_popular)
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest bg-amber-400 text-white shadow-sm">
                                ⭐ Terpopuler
                            </span>
                            @endif
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest
                                {{ $pkg->is_active ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-slate-100 text-slate-400 border border-slate-200' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $pkg->is_active ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' }}"></span>
                                {{ $pkg->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button wire:click="editPackage({{ $pkg->id }})" title="Edit Paket"
                                class="p-1.5 rounded-lg bg-slate-50 border border-slate-200 text-slate-400 hover:text-[#1fa387] hover:bg-[#1fa387]/8 hover:border-[#1fa387]/30 transition-all duration-200 cursor-pointer">
                                <span class="material-symbols-outlined text-[14px] block">edit</span>
                            </button>
                            <button wire:click="confirmDelete({{ $pkg->id }})" title="Hapus Paket"
                                class="p-1.5 rounded-lg bg-slate-50 border border-slate-200 text-slate-400 hover:text-rose-500 hover:bg-rose-50 hover:border-rose-200 transition-all duration-200 cursor-pointer">
                                <span class="material-symbols-outlined text-[14px] block">delete</span>
                            </button>
                        </div>
                    </div>

                    {{-- Plan Name & Price --}}
                    <div class="mb-5">
                        <p class="text-[12px] font-black uppercase tracking-[0.25em] text-slate-500 mb-1.5">{{ $pkg->name }}</p>
                        <div class="flex items-end gap-1.5 mb-2">
                            @if($pkg->price > 0)
                                <span class="text-3xl md:text-4xl font-black text-slate-800 leading-none">Rp {{ number_format($pkg->price, 0, ',', '.') }}</span>
                                <span class="text-slate-400 text-xs font-semibold pb-1">/bulan</span>
                            @else
                                <span class="text-2xl md:text-3xl font-black text-[#1fa387] leading-none">Hubungi Kami</span>
                            @endif
                        </div>
                        <p class="text-slate-400 text-[11px] leading-relaxed">{{ $pkg->description ?: 'Paket standar dengan fungsionalitas scraping yang handal.' }}</p>
                    </div>

                    {{-- Divider --}}
                    <div class="h-px bg-gradient-to-r from-transparent via-slate-100 to-transparent mb-4"></div>

                    {{-- Feature Categories --}}
                    <div class="space-y-4 flex-1">
                        @php
                            $hasDataPro = !empty($pkg->advantages) || !empty($pkg->social_media_features) || !empty($pkg->news_portal_features);
                        @endphp

                        {{-- Fitur Sosial Media --}}
                        @if(!empty($pkg->social_media_features))
                        <div>
                            <div class="flex items-center gap-1.5 mb-2">
                                <span class="material-symbols-outlined text-[11px] text-sky-500">share</span>
                                <span class="text-[8px] font-black uppercase tracking-[0.15em] text-slate-400">Sosial Media & Keunggulan</span>
                            </div>
                            <div class="space-y-1.5">
                                @foreach($pkg->social_media_features as $feat)
                                <div class="flex items-start gap-2">
                                    <span class="w-4 h-4 rounded-md bg-sky-50 flex items-center justify-center shrink-0 border border-sky-100 mt-0.5">
                                        <span class="material-symbols-outlined text-[9px] text-sky-500">check</span>
                                    </span>
                                    <span class="text-[11px] text-slate-600 font-medium leading-tight break-words min-w-0">{{ $feat }}</span>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- Fitur Portal Berita --}}
                        @if(!empty($pkg->news_portal_features))
                        <div>
                            <div class="flex items-center gap-1.5 mb-2">
                                <span class="material-symbols-outlined text-[11px] text-violet-500">newspaper</span>
                                <span class="text-[8px] font-black uppercase tracking-[0.15em] text-slate-400">Portal Berita</span>
                            </div>
                            <div class="space-y-1.5">
                                @foreach($pkg->news_portal_features as $feat)
                                <div class="flex items-start gap-2">
                                    <span class="w-4 h-4 rounded-md bg-violet-50 flex items-center justify-center shrink-0 border border-violet-100 mt-0.5">
                                        <span class="material-symbols-outlined text-[9px] text-violet-500">check</span>
                                    </span>
                                    <span class="text-[11px] text-slate-600 font-medium leading-tight break-words min-w-0">{{ $feat }}</span>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Footer CTA --}}
                <div class="px-6 md:px-7 pb-6 md:pb-7 pt-4 border-t border-[#1fa387]/10">
                    <button wire:click="manageActors({{ $pkg->id }})"
                        class="w-full flex items-center justify-center gap-2 py-3 rounded-xl text-xs font-black
                               bg-[#1fa387] text-white
                               hover:bg-[#178a71] hover:shadow-lg hover:shadow-[#1fa387]/25
                               active:scale-[0.98] transition-all duration-200 cursor-pointer">
                        <span class="material-symbols-outlined text-[15px]">tune</span>
                        <span>Atur Actor & Biaya</span>
                    </button>
                </div>
            </div>
            @endif

            @empty
            <div class="col-span-full bg-gradient-to-br from-slate-50/50 via-white to-slate-50/30 rounded-2xl md:rounded-3xl border border-slate-200/80 p-8 md:p-12 text-center shadow-sm">
                <div class="relative w-16 h-16 md:w-20 md:h-20 rounded-2xl md:rounded-3xl bg-gradient-to-tr from-[#1fa387]/10 to-emerald-500/5 flex items-center justify-center text-[#1fa387] mx-auto mb-6 border border-[#1fa387]/10">
                    <span class="material-symbols-outlined text-3xl md:text-4xl animate-pulse" style="animation-duration: 3s;">inventory_2</span>
                    <span class="absolute -top-1 -right-1 w-3 h-3 rounded-full bg-emerald-500 border-2 border-white animate-pulse"></span>
                </div>
                
                <h4 class="text-sm md:text-base font-black text-slate-800 tracking-tight">Belum Ada Paket Scraping Aktif</h4>
                <p class="text-[11px] md:text-xs text-slate-450 max-w-sm mx-auto leading-relaxed mt-2">Buat bundel konfigurasi actor Apify dan kustomisasi biaya per run secara modular untuk proyek Anda.</p>
                
                <div class="max-w-sm mx-auto my-6 p-4 rounded-xl md:rounded-2xl bg-white border border-slate-200/50 text-left space-y-3 shadow-sm">
                    <div class="flex items-center gap-3 text-slate-600 text-[10px] md:text-[11px] font-bold">
                        <span class="w-5 h-5 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-600"><span class="material-symbols-outlined text-[13px] md:text-[14px]">check_circle</span></span>
                        <span>Modular multi-actor Apify per proyek</span>
                    </div>
                    <div class="flex items-center gap-3 text-slate-600 text-[10px] md:text-[11px] font-bold">
                        <span class="w-5 h-5 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-600"><span class="material-symbols-outlined text-[13px] md:text-[14px]">check_circle</span></span>
                        <span>Override limit biaya maksimal per run</span>
                    </div>
                    <div class="flex items-center gap-3 text-slate-600 text-[10px] md:text-[11px] font-bold">
                        <span class="w-5 h-5 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-600"><span class="material-symbols-outlined text-[13px] md:text-[14px]">check_circle</span></span>
                        <span>Kontrol penuh atas setelan portal berita</span>
                    </div>
                </div>

                <button wire:click="createPackage"
                    class="inline-flex items-center gap-2 px-5 py-3 rounded-xl md:rounded-2xl bg-gradient-to-r from-[#1fa387] to-[#178a71] text-white text-[11px] md:text-xs font-black hover:shadow-md transition-all duration-200 cursor-pointer active:scale-95">
                    <span class="material-symbols-outlined text-[16px] md:text-[18px]">add_box</span>
                    <span>Buat Paket Pertama</span>
                </button>
            </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        @if($packages->hasPages())
        <div class="mt-8 flex justify-center">
            {{ $packages->links() }}
        </div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- VIEW: FORM (Create / Edit)                                          --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    @elseif($view === 'form')
    <!-- Static Backdrop Blur Layer (Separated for scrolling performance) -->
    <div class="fixed inset-0 z-40 bg-slate-950/40 backdrop-blur-sm cursor-default" wire:click="cancelForm"></div>

    <!-- Modal Container -->
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" wire:click.self="cancelForm">
        <div class="w-full max-w-5xl bg-white rounded-3xl border border-[#1fa387]/15 shadow-[0_24px_70px_rgba(31,163,135,0.12)] flex flex-col overflow-hidden animate-fade-in text-left" style="height: 85vh; max-height: 720px;">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between gap-4 px-8 py-5 border-b border-[#1fa387]/10 shrink-0 bg-white" style="flex-shrink: 0;">
                <div class="flex items-center gap-4">
                    <button wire:click="cancelForm" class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-500 hover:text-[#1fa387] hover:border-[#1fa387]/50 transition-all duration-200 cursor-pointer">
                        <span class="material-symbols-outlined text-[18px] block">arrow_back</span>
                    </button>
                    <div>
                        <h2 class="text-lg font-black text-slate-700 tracking-tight">
                            {{ $editingPackageId ? 'Edit Parameter Paket' : 'Buat Paket Baru' }}
                        </h2>
                        <p class="text-[11px] text-slate-500 mt-0.5">Konfigurasikan informasi dasar paket dan biaya override di bawah ini.</p>
                    </div>
                </div>
                <button wire:click="cancelForm" class="p-2 rounded-xl text-slate-400 hover:text-slate-650 hover:bg-slate-50 transition-all duration-150 cursor-pointer">
                    <span class="material-symbols-outlined text-[18px] block">close</span>
                </button>
            </div>

            <!-- Scrollable Content Area with Hardware Accelerated Scrolling & Backdrop Isolation -->
            <div x-init="$el.scrollTop = 0" class="flex-grow overflow-y-auto p-8 space-y-6 text-left" style="flex: 1 1 auto; -webkit-overflow-scrolling: touch; scroll-behavior: smooth; overscroll-behavior: contain; transform: translate3d(0, 0, 0); will-change: scroll-position;">
                
                <!-- Section 1: Informasi Dasar -->
                <div class="mb-8">
                    <h3 class="text-sm font-black text-slate-600 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-[#1fa387]">info</span>
                        Informasi Dasar
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
                        <!-- Nama Paket -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-[11px] font-bold text-slate-700 tracking-wide">Nama Paket</label>
                                <span class="text-[9px] font-bold text-rose-500 bg-rose-50 px-2 py-0.5 rounded-md">Wajib</span>
                            </div>
                            <input wire:model="name" type="text" placeholder="Contoh: Paket VIP Scraping Medsos"
                                class="w-full px-4 py-2.5 rounded-xl border border-[#1fa387]/20 text-sm focus:outline-none focus:ring-4 focus:ring-[#1fa387]/10 focus:border-[#1fa387] transition-all bg-slate-50/60 hover:bg-white @error('name') border-rose-300 bg-rose-50/50 @enderror" />
                            @error('name') <p class="text-rose-500 text-[11px] font-semibold mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- Harga Paket -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-[11px] font-bold text-slate-700 tracking-wide">Harga Paket (Rp/bulan)</label>
                                <span class="text-[9px] font-bold text-rose-500 bg-rose-50 px-2 py-0.5 rounded-md">Wajib</span>
                            </div>
                            <div class="relative flex items-center"
                                 x-data="{
                                    display: '{{ $price ? number_format((float)$price, 0, ',', '.') : '' }}',
                                    onInput(e) {
                                        let raw = e.target.value.replace(/\D/g, '');
                                        this.display = raw ? parseInt(raw).toLocaleString('id-ID') : '';
                                        $wire.set('price', raw ? parseInt(raw) : 0);
                                    }
                                 }"
                                 x-init="$watch('$wire.price', v => { display = v ? parseInt(v).toLocaleString('id-ID') : '' })">
                                <span class="absolute left-3.5 text-[#1fa387] font-black text-xs select-none pointer-events-none z-10">Rp</span>
                                <input type="text"
                                    inputmode="numeric"
                                    placeholder="500.000"
                                    x-model="display"
                                    x-on:input="onInput($event)"
                                    x-on:focus="$event.target.select()"
                                    style="padding-left: 2.25rem;"
                                    class="w-full pr-4 py-2.5 rounded-xl border border-[#1fa387]/20 text-sm font-semibold text-slate-700 focus:outline-none focus:ring-4 focus:ring-[#1fa387]/10 focus:border-[#1fa387] transition-all bg-slate-50/60 hover:bg-white @error('price') border-rose-300 bg-rose-50/50 @enderror" />
                            </div>
                            @error('price') <p class="text-rose-500 text-[11px] font-semibold mt-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- Deskripsi Singkat (Melapisi 2 kolom) -->
                        <div class="md:col-span-2">
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-[11px] font-bold text-slate-700 tracking-wide">Deskripsi Singkat</label>
                                <span class="text-[9px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-md">Opsional</span>
                            </div>
                            <textarea wire:model="description" rows="3" placeholder="Rincian mengenai fungsionalitas paket ini..."
                                class="w-full px-4 py-3 rounded-xl border border-[#1fa387]/20 text-sm resize-none focus:outline-none focus:ring-4 focus:ring-[#1fa387]/10 focus:border-[#1fa387] transition-all bg-slate-50/60 hover:bg-white h-24"></textarea>
                            @error('description') <p class="text-rose-500 text-[11px] font-semibold mt-1">{{ $message }}</p> @enderror
                        </div>
                        
                        <!-- Switches (3 kolom) -->
                        <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-4">
                            {{-- Status Switch --}}
                            <div class="flex flex-col p-3.5 bg-slate-50 rounded-xl border border-slate-100">
                                <div class="flex items-center justify-between mb-1.5">
                                    <div class="text-[11px] font-bold text-slate-750">Status Paket Aktif</div>
                                    <button type="button" wire:click="$toggle('is_active')"
                                        class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $is_active ? 'bg-[#1fa387]' : 'bg-slate-300' }}">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition duration-200 ease-in-out {{ $is_active ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                    </button>
                                </div>
                                <div class="text-[9px] text-slate-500 leading-tight">Aktifkan agar bisa dipilih di form Proyek.</div>
                            </div>

                            {{-- Portal News Switch --}}
                            <div class="flex flex-col p-3.5 bg-slate-50 rounded-xl border border-slate-100">
                                <div class="flex items-center justify-between mb-1.5">
                                    <div class="text-[11px] font-bold text-slate-750">Fitur News Portal</div>
                                    <button type="button" wire:click="$toggle('use_portal')"
                                        class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $use_portal ? 'bg-[#1fa387]' : 'bg-slate-300' }}">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition duration-200 ease-in-out {{ $use_portal ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                    </button>
                                </div>
                                <div class="text-[9px] text-slate-500 leading-tight">Akses fitur scraping & manajemen berita.</div>
                            </div>

                            {{-- Terpopuler Switch --}}
                            <div class="flex flex-col p-3.5 rounded-xl border transition-all duration-150 {{ $is_popular ? 'bg-amber-50/60 border-amber-200' : 'bg-slate-50 border-slate-100' }}">
                                <div class="flex items-center justify-between mb-1.5">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-[11px] font-bold {{ $is_popular ? 'text-amber-700' : 'text-slate-750' }}">⭐ Terpopuler</span>
                                        <span class="text-[8px] font-bold text-slate-400 bg-white border border-slate-200 px-1.5 py-0.5 rounded-md leading-none">Opsional</span>
                                    </div>
                                    <button type="button" wire:click="$toggle('is_popular')"
                                        class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $is_popular ? 'bg-amber-400' : 'bg-slate-300' }}">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition duration-200 ease-in-out {{ $is_popular ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                    </button>
                                </div>
                                <div class="text-[9px] leading-tight {{ $is_popular ? 'text-amber-600' : 'text-slate-500' }}">Tampilkan badge "Terpopuler" di kartu paket.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Spesifikasi & Fitur Dinamis -->
                <div class="mb-8">
                    <h3 class="text-sm font-black text-[#1fa387] mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-[#1fa387]">featured_play_list</span>
                        Spesifikasi Layanan
                    </h3>

                    <!-- Fitur Tambahan (Toggles) -->
                     <div class="bg-white/80 p-5 rounded-2xl border border-[#1fa387]/10 shadow-[0_2px_15px_-3px_rgba(31,163,135,0.06)] mb-5 text-left">
                        <div class="flex items-center gap-1.5 text-xs font-black text-[#1fa387] uppercase tracking-wider mb-4 border-b border-[#1fa387]/10 pb-2">
                            <span class="material-symbols-outlined text-[16px] text-[#1fa387]">bolt</span>
                            Fitur Tambahan Paket
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <!-- Fitur AI -->
                            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50/50 border border-[#1fa387]/10 hover:bg-white hover:shadow-sm hover:border-[#1fa387]/25 transition-all duration-150">
                                <div class="flex flex-col min-w-0 pr-2">
                                    <span class="text-[11px] font-black text-[#1fa387] leading-tight">Fitur AI Lanjut</span>
                                    <span class="text-[8.5px] text-slate-400 mt-0.5 truncate">Sentiment & summary</span>
                                </div>
                                <button type="button"
                                    wire:click="$set('feat_ai', {{ !$feat_ai ? 'true' : 'false' }})"
                                    class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $feat_ai ? 'bg-[#1fa387]' : 'bg-slate-200' }}">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition duration-200 ease-in-out {{ $feat_ai ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                </button>
                            </div>
                            
                            <!-- RSS Feed -->
                            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50/50 border border-[#1fa387]/10 hover:bg-white hover:shadow-sm hover:border-[#1fa387]/25 transition-all duration-150">
                                <div class="flex flex-col min-w-0 pr-2">
                                    <span class="text-[11px] font-black text-[#1fa387] leading-tight">RSS & Portal Scraper</span>
                                    <span class="text-[8.5px] text-slate-400 mt-0.5 truncate">Scraping web & berita</span>
                                </div>
                                <button type="button"
                                    wire:click="$set('feat_rss', {{ !$feat_rss ? 'true' : 'false' }})"
                                    class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $feat_rss ? 'bg-[#1fa387]' : 'bg-slate-200' }}">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition duration-200 ease-in-out {{ $feat_rss ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                </button>
                            </div>
                            
                            <!-- Integrasi Telegram -->
                            <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50/50 border border-[#1fa387]/10 hover:bg-white hover:shadow-sm hover:border-[#1fa387]/25 transition-all duration-150">
                                <div class="flex flex-col min-w-0 pr-2">
                                    <span class="text-[11px] font-black text-[#1fa387] leading-tight">Integrasi Telegram</span>
                                    <span class="text-[8.5px] text-slate-400 mt-0.5 truncate">Notifikasi & alert Telegram</span>
                                </div>
                                <button type="button"
                                    wire:click="$set('feat_api', {{ !$feat_api ? 'true' : 'false' }})"
                                    class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $feat_api ? 'bg-[#1fa387]' : 'bg-slate-200' }}">
                                    <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition duration-200 ease-in-out {{ $feat_api ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

                        {{-- Fitur Sosial Media & Keunggulan --}}
                        <div class="bg-white p-5 rounded-2xl border border-[#1fa387]/10 shadow-sm flex flex-col">
                            <label class="block text-[11px] font-bold text-[#1fa387] tracking-wide mb-3">Fitur Sosial Media & Keunggulan</label>
                            <div class="flex gap-2 mb-4">
                                <input wire:model.live="newSocialFeature" type="text" placeholder="Misal: TikTok Analytics..." wire:keydown.enter.prevent="addSocialFeature"
                                    class="flex-1 px-3 py-2 rounded-lg border border-slate-200 text-xs focus:outline-none focus:ring-2 focus:ring-[#1fa387]/20 focus:border-[#1fa387] bg-slate-50" />
                                <button type="button" wire:click="addSocialFeature" class="px-3.5 py-2 rounded-xl bg-[#1fa387] text-white hover:bg-[#178a71] shadow-sm shadow-[#1fa387]/15 transition-all active:scale-95 cursor-pointer flex items-center justify-center">
                                    <span class="material-symbols-outlined text-[16px] block">add</span>
                                </button>
                            </div>
                            <div class="space-y-2 flex-grow overflow-y-auto max-h-[140px] pr-1 custom-scrollbar">
                                @forelse($social_media_features as $idx => $feat)
                                <div class="flex items-center justify-between gap-2 group/item bg-slate-50 hover:bg-white hover:border-[#1fa387]/30 border border-transparent px-3 py-2 rounded-lg text-xs text-[#1fa387] transition-all">
                                    <span class="truncate flex-1">{{ $feat }}</span>
                                    <div class="flex items-center gap-1.5 opacity-0 group-hover/item:opacity-100 transition-all duration-150 shrink-0">
                                        <button type="button" wire:click="editSocialFeature({{ $idx }})" class="w-6 h-6 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-500 hover:text-white transition-all duration-150 active:scale-90 flex items-center justify-center cursor-pointer" title="Edit">
                                            <span class="material-symbols-outlined text-[13px] font-black">edit</span>
                                        </button>
                                        <button type="button" wire:click="removeSocialFeature({{ $idx }})" class="w-6 h-6 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-500 hover:text-white transition-all duration-150 active:scale-90 flex items-center justify-center cursor-pointer" title="Hapus">
                                            <span class="material-symbols-outlined text-[13px] font-black">close</span>
                                        </button>
                                    </div>
                                </div>
                                @empty
                                <div class="text-center py-4 text-[10px] text-slate-400 border border-dashed border-slate-200 rounded-lg bg-slate-50">Belum ada fitur sosmed</div>
                                @endforelse
                            </div>
                        </div>

                        {{-- Fitur Portal --}}
                        <div class="bg-white p-5 rounded-2xl border border-[#1fa387]/10 shadow-sm flex flex-col">
                            <label class="block text-[11px] font-bold text-[#1fa387] tracking-wide mb-3">Fitur Portal Berita</label>
                            <div class="flex gap-2 mb-4">
                                <input wire:model.live="newPortalFeature" type="text" placeholder="Misal: Anti-Blocker..." wire:keydown.enter.prevent="addPortalFeature"
                                    class="flex-1 px-3 py-2 rounded-lg border border-slate-200 text-xs focus:outline-none focus:ring-2 focus:ring-[#1fa387]/20 focus:border-[#1fa387] bg-slate-50" />
                                <button type="button" wire:click="addPortalFeature" class="px-3.5 py-2 rounded-xl bg-[#1fa387] text-white hover:bg-[#178a71] shadow-sm shadow-[#1fa387]/15 transition-all active:scale-95 cursor-pointer flex items-center justify-center">
                                    <span class="material-symbols-outlined text-[16px] block">add</span>
                                </button>
                            </div>
                            <div class="space-y-2 flex-grow overflow-y-auto max-h-[140px] pr-1 custom-scrollbar">
                                @forelse($news_portal_features as $idx => $feat)
                                <div class="flex items-center justify-between gap-2 group/item bg-slate-50 hover:bg-white hover:border-[#1fa387]/30 border border-transparent px-3 py-2 rounded-lg text-xs text-[#1fa387] transition-all">
                                    <span class="truncate flex-1">{{ $feat }}</span>
                                    <div class="flex items-center gap-1.5 opacity-0 group-hover/item:opacity-100 transition-all duration-150 shrink-0">
                                        <button type="button" wire:click="editPortalFeature({{ $idx }})" class="w-6 h-6 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-500 hover:text-white transition-all duration-150 active:scale-90 flex items-center justify-center cursor-pointer" title="Edit">
                                            <span class="material-symbols-outlined text-[13px] font-black">edit</span>
                                        </button>
                                        <button type="button" wire:click="removePortalFeature({{ $idx }})" class="w-6 h-6 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-500 hover:text-white transition-all duration-150 active:scale-90 flex items-center justify-center cursor-pointer" title="Hapus">
                                            <span class="material-symbols-outlined text-[13px] font-black">close</span>
                                        </button>
                                    </div>
                                </div>
                                @empty
                                <div class="text-center py-4 text-[10px] text-slate-400 border border-dashed border-slate-200 rounded-lg bg-slate-50">Belum ada fitur portal</div>
                                @endforelse
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Section 3: Konfigurasi Actor -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between bg-white px-5 py-4 rounded-t-2xl border border-[#1fa387]/10 border-b-0 shadow-sm mt-4">
                        <div class="text-left">
                            <div class="text-sm font-black text-[#1fa387] flex items-center gap-2">
                                <span class="material-symbols-outlined text-[18px] text-[#1fa387]">smart_toy</span>
                                Actor dalam Paket
                            </div>
                            <div class="text-[11px] text-slate-500 mt-0.5">Pilih actor yang diizinkan untuk paket ini dan set biaya custom per run.</div>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" wire:click="enableAllActors" class="px-3 py-2 rounded-lg text-[11px] font-bold bg-emerald-50 text-emerald-600 hover:bg-emerald-100 transition-colors cursor-pointer">
                                Aktifkan Semua
                            </button>
                            <button type="button" wire:click="disableAllActors" class="px-3 py-2 rounded-lg text-[11px] font-bold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors cursor-pointer">
                                Nonaktifkan
                            </button>
                        </div>
                    </div>

                    <div class="bg-white rounded-b-2xl border border-[#1fa387]/10 shadow-sm overflow-hidden mt-0!">
                        @php $groupedActors = $allActors->groupBy('platform'); @endphp

                        @forelse($groupedActors as $platform => $actors)
                            <div class="px-5 py-2.5 bg-[#1fa387]/5 border-y border-[#1fa387]/10 flex items-center gap-2">
                                <span class="material-symbols-outlined text-[14px] text-[#1fa387]">grid_view</span>
                                <span class="text-[10px] font-black uppercase tracking-wider text-[#1fa387]">{{ $platform ?: 'Lainnya' }}</span>
                                <span class="ml-auto text-[10px] font-bold text-[#1fa387] bg-white px-2 rounded-full border border-[#1fa387]/20">{{ $actors->count() }} actor</span>
                            </div>

                            <div class="divide-y divide-[#1fa387]/8">
                                @foreach($actors as $actor)
                                    @php $config = $actorConfig[$actor->id] ?? ['is_enabled' => false, 'cost_per_run_usd' => '']; @endphp
                                    <div wire:key="package-form-actor-{{ $actor->id }}" class="px-5 py-4 hover:bg-slate-50/50 transition-colors flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b last:border-0 border-slate-100">
                                        <div class="flex items-start gap-3 flex-1 min-w-0">
                                            <button
                                                type="button"
                                                wire:click="toggleActor({{ $actor->id }})"
                                                class="relative mt-1 inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ ($config['is_enabled'] ?? false) ? 'bg-[#1fa387]' : 'bg-slate-200' }}"
                                            >
                                                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow-md transition duration-200 ease-in-out {{ ($config['is_enabled'] ?? false) ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                            </button>
                                            <div class="min-w-0">
                                                <div class="text-sm font-bold text-slate-700 leading-tight truncate">{{ $actor->actor_name }}</div>
                                                <div class="text-[11px] text-slate-400 mt-0.5 truncate">{{ $actor->actor_slug }}</div>
                                                <div class="flex flex-wrap gap-1.5 mt-2">
                                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[9px] font-bold text-slate-500 uppercase tracking-wide">{{ $actor->function_type }}</span>
                                                    @if($config['is_enabled'] ?? false)
                                                        <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[9px] font-bold text-emerald-600 border border-emerald-100 uppercase tracking-wide">Aktif</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Panel Konfigurasi Aktor Per Paket (Cost, Limit, RAM) --}}
                                        <div class="flex flex-wrap items-center gap-4 bg-slate-50 border border-slate-100 rounded-2xl p-3 shrink-0 w-full lg:w-auto justify-between lg:justify-end">
                                            {{-- Global Cost Display --}}
                                            <div class="text-left px-2">
                                                <div class="text-[8px] text-slate-400 font-bold uppercase tracking-wider">Biaya Global</div>
                                                <div class="text-xs font-black text-slate-650 mt-0.5">
                                                    {{ $actor->maximum_cost_per_run_usd ? '$' . number_format($actor->maximum_cost_per_run_usd, 2) : '—' }}
                                                </div>
                                            </div>

                                            {{-- Divider --}}
                                            <div class="hidden sm:block h-6 w-px bg-slate-200/80"></div>

                                            {{-- Cost Override Field --}}
                                            <div class="text-left px-2">
                                                <div class="text-[8px] text-slate-400 font-bold uppercase tracking-wider mb-1">Override Biaya ($)</div>
                                                <div class="flex items-center">
                                                    <div class="relative flex items-center">
                                                        <span class="absolute left-2.5 text-slate-400 text-[10px] font-bold">$</span>
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            placeholder="{{ $actor->maximum_cost_per_run_usd ? number_format($actor->maximum_cost_per_run_usd, 2) : 'Global' }}"
                                                            wire:model.lazy="actorConfig.{{ $actor->id }}.cost_per_run_usd"
                                                            style="padding-left: 1.5rem;"
                                                            class="pr-1.5 py-1 w-24 text-center font-mono rounded-lg border text-xs transition-all focus:outline-none focus:ring-2 focus:ring-[#1fa387]/40 focus:border-[#1fa387]
                                                            {{ $config['is_enabled'] ? 'border-slate-200 bg-white text-slate-800' : 'border-slate-150 bg-slate-100 text-slate-400' }}" />
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Divider --}}
                                            <div class="hidden sm:block h-6 w-px bg-slate-200/80"></div>

                                            {{-- Default Limit Field --}}
                                            <div class="text-left px-2">
                                                <div class="text-[8px] text-slate-400 font-bold uppercase tracking-wider mb-1">Batas Hasil (Limit)</div>
                                                <input
                                                    type="number"
                                                    min="1"
                                                    placeholder="{{ $actor->default_limit }}"
                                                    wire:model.lazy="actorConfig.{{ $actor->id }}.default_limit"
                                                    class="px-2 py-1 w-20 text-center font-mono rounded-lg border text-xs transition-all focus:outline-none focus:ring-2 focus:ring-[#1fa387]/40 focus:border-[#1fa387]
                                                    {{ $config['is_enabled'] ? 'border-slate-200 bg-white text-slate-800' : 'border-slate-150 bg-slate-100 text-slate-400' }}"
                                                    {{ !$config['is_enabled'] ? 'disabled' : '' }} />
                                            </div>

                                            {{-- Divider --}}
                                            <div class="hidden sm:block h-6 w-px bg-slate-200/80"></div>

                                            {{-- Memory Limit Select --}}
                                            <div class="text-left px-2">
                                                <div class="text-[8px] text-slate-400 font-bold uppercase tracking-wider mb-1">Alokasi RAM</div>
                                                <select
                                                    wire:model="actorConfig.{{ $actor->id }}.memory_limit"
                                                    class="px-2 py-1 w-24 text-center rounded-lg border text-[11px] font-semibold transition-all focus:outline-none focus:ring-2 focus:ring-[#1fa387]/40 focus:border-[#1fa387] bg-white
                                                    {{ $config['is_enabled'] ? 'border-slate-200 text-slate-800' : 'border-slate-150 bg-slate-100 text-slate-400' }}"
                                                    {{ !$config['is_enabled'] ? 'disabled' : '' }}>
                                                    <option value="128">128 MB</option>
                                                    <option value="256">256 MB</option>
                                                    <option value="512">512 MB</option>
                                                    <option value="1024">1 GB</option>
                                                    <option value="2048">2 GB</option>
                                                    <option value="4096">4 GB</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @empty
                            <div class="px-5 py-8 text-center text-xs text-slate-400">Belum ada actor yang tersedia di sistem.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex gap-3 px-8 py-5 border-t border-[#1fa387]/10 justify-end shrink-0 bg-white" style="flex-shrink: 0;">
                <button type="button" wire:click="cancelForm"
                    class="px-5 py-2.5 bg-slate-50 border border-slate-200 hover:bg-slate-100 text-slate-700 font-bold rounded-xl text-xs transition cursor-pointer active:scale-95">
                    Batal
                </button>
                <button
                    type="button"
                    wire:click="savePackage"
                    wire:loading.attr="disabled"
                    wire:target="savePackage"
                    class="flex items-center gap-2 px-6 py-3 rounded-xl text-xs font-black text-white bg-[#1fa387] hover:bg-[#178a71] shadow-lg shadow-[#1fa387]/20 transition-all duration-200 active:scale-95 cursor-pointer disabled:opacity-70 disabled:cursor-wait">
                    <svg wire:loading.remove wire:target="savePackage" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24">
                        <path d="M5 4a1 1 0 0 1 1-1h10.586a1 1 0 0 1 .707.293l1.414 1.414A1 1 0 0 1 19 5.414V20a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                        <path d="M8 3v5h6V3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <svg wire:loading wire:target="savePackage" class="w-[18px] h-[18px] animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="savePackage">{{ $editingPackageId ? 'Simpan Perubahan' : 'Buat Paket' }}</span>
                    <span wire:loading wire:target="savePackage">Menyimpan...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    {{-- VIEW: ACTORS (Manage actor config per package)                      --}}
    {{-- ═══════════════════════════════════════════════════════════════════ --}}
    @elseif($view === 'actors')
    <!-- Static Backdrop Blur Layer (Separated for scrolling performance) -->
    <div class="fixed inset-0 z-40 bg-slate-950/40 backdrop-blur-sm cursor-default" wire:click="cancelActors"></div>

    <!-- Modal Container -->
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" wire:click.self="cancelActors">
        <div class="w-full max-w-5xl bg-white rounded-3xl border border-[#1fa387]/15 shadow-[0_24px_70px_rgba(31,163,135,0.12)] flex flex-col overflow-hidden animate-fade-in text-left" style="height: 85vh; max-height: 720px;">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between gap-4 px-8 py-5 border-b border-[#1fa387]/10 shrink-0 bg-white" style="flex-shrink: 0;">
                <div class="flex items-center gap-4">
                    <button wire:click="cancelActors" class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-500 hover:text-[#1fa387] hover:border-[#1fa387]/50 transition-all duration-200 cursor-pointer">
                        <span class="material-symbols-outlined text-[18px] block">arrow_back</span>
                    </button>
                    <div>
                        <h2 class="text-lg font-black text-slate-700 tracking-tight">Atur Actor & Biaya</h2>
                        <p class="text-[11px] text-slate-500 mt-0.5">
                            Mengonfigurasi paket: <span class="font-extrabold text-[#1fa387]">{{ $managingPackage?->name }}</span>
                        </p>
                    </div>
                </div>
                <button wire:click="cancelActors" class="p-2 rounded-xl text-slate-400 hover:text-slate-650 hover:bg-slate-50 transition-all duration-150 cursor-pointer">
                    <span class="material-symbols-outlined text-[18px] block">close</span>
                </button>
            </div>

            <!-- Scrollable Content Area with Hardware Accelerated Scrolling & Backdrop Isolation -->
            <div class="flex-grow overflow-y-auto px-8 py-6" style="flex: 1 1 auto; -webkit-overflow-scrolling: touch; scroll-behavior: smooth; overscroll-behavior: contain; transform: translate3d(0, 0, 0); will-change: scroll-position;">

        {{-- Quick Actions --}}
        <div class="flex gap-2.5 mb-5 flex-wrap">
            <button wire:click="enableAllActors" class="flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-black bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500 hover:text-white border border-emerald-500/10 transition-all duration-150 active:scale-95 cursor-pointer">
                <span class="material-symbols-outlined text-[16px]">check_box</span> Aktifkan Semua
            </button>
            <button wire:click="disableAllActors" class="flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-black bg-[#1fa387]/5 text-slate-500 hover:bg-[#1fa387]/10 border border-[#1fa387]/10 transition-all duration-150 active:scale-95 cursor-pointer">
                <span class="material-symbols-outlined text-[16px]">check_box_outline_blank</span> Nonaktifkan Semua
            </button>
        </div>

        {{-- Actor List --}}
        <div class="bg-white rounded-3xl border border-[#1fa387]/12 shadow-[0_4px_25px_-2px_rgba(31,163,135,0.06)] overflow-hidden mb-6">
            @php
                $groupedActors = $allActors->groupBy('platform');
            @endphp

            @forelse($groupedActors as $platform => $actors)
            @php
                $platformLower = strtolower($platform);
                $headerClass = 'bg-[#1fa387]/5 text-[#1fa387] border-b border-[#1fa387]/10';
                $brandColor = 'text-[#1fa387]';
                
                // Real Brand SVGs
                $svgIcon = '<svg class="w-4 h-4 text-[#1fa387] shrink-0" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>';
                
                if (str_contains($platformLower, 'facebook')) {
                    $headerClass = 'bg-blue-50/50 text-blue-900 border-b border-blue-100/50';
                    $brandColor = 'text-blue-600';
                    $svgIcon = '<svg class="w-4 h-4 text-blue-650 shrink-0" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>';
                } elseif (str_contains($platformLower, 'instagram')) {
                    $headerClass = 'bg-pink-50/50 text-pink-900 border-b border-pink-100/50';
                    $brandColor = 'text-pink-600';
                    $svgIcon = '<svg class="w-4 h-4 text-pink-600 shrink-0" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.051.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/></svg>';
                } elseif (str_contains($platformLower, 'tiktok')) {
                    $headerClass = 'bg-slate-50 text-slate-700 border-b border-slate-200/60';
                    $brandColor = 'text-slate-700';
                    $svgIcon = '<svg class="w-4 h-4 text-slate-950 shrink-0" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .8.11V9.4a6.27 6.27 0 0 0-3.78.3 6.3 6.3 0 0 0-3.3 5.4 6.29 6.29 0 0 0 5.4 6.28 6.33 6.33 0 0 0 7.1-5.91V8.66a8.21 8.21 0 0 0 5.5 2.13V7.34a4.82 4.82 0 0 1-5.1-4.3v3.65z"/></svg>';
                }
            @endphp
            {{-- Platform Group Header --}}
            <div class="px-6 py-3.5 flex items-center gap-2.5 {{ $headerClass }}">
                {!! $svgIcon !!}
                <span class="text-[10px] font-black uppercase tracking-widest">{{ $platform ?: 'Lainnya' }}</span>
                <span class="ml-auto text-[9px] text-[#1fa387] font-extrabold bg-white border border-[#1fa387]/20 rounded-full px-2.5 py-0.5 shadow-sm">{{ $actors->count() }} actor</span>
            </div>

            @foreach($actors as $actor)
            @php $config = $actorConfig[$actor->id] ?? ['is_enabled' => false, 'cost_per_run_usd' => '']; @endphp
            <div wire:key="package-actors-actor-{{ $actor->id }}" 
                 class="flex flex-col md:flex-row md:items-center justify-between gap-4 px-6 py-4.5 border-b border-slate-100 hover:bg-slate-50/30 transition-all duration-150 last:border-b-0 group
                 {{ ($config['is_enabled'] ?? false) ? 'border-l-4 border-l-[#1fa387]' : 'border-l-4 border-l-transparent' }}">
                
                {{-- Left Area: Toggle + Info --}}
                <div class="flex items-center gap-4 flex-1 min-w-0">
                    <button
                        type="button"
                        wire:click="toggleActor({{ $actor->id }})"
                        class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ ($config['is_enabled'] ?? false) ? 'bg-[#1fa387]' : 'bg-slate-200' }}"
                    >
                        <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition duration-200 ease-in-out {{ ($config['is_enabled'] ?? false) ? 'translate-x-4' : 'translate-x-0' }}"></span>
                    </button>

                    {{-- Info Actor --}}
                    <div class="min-w-0">
                        <div class="font-extrabold text-xs text-slate-700 tracking-tight flex flex-wrap items-center gap-2">
                            <span class="hover:text-[#1fa387] transition-colors">{{ $actor->actor_name }}</span>
                            <span class="inline-flex shrink-0 items-center px-2 py-0.5 rounded-full text-[8.5px] font-black bg-blue-50 text-blue-650 border border-blue-100/50 uppercase tracking-wider">
                                {{ $actor->function_type ?? 'scraper' }}
                            </span>
                        </div>
                        <div class="text-[9.5px] text-slate-400 font-mono mt-1 select-all truncate max-w-xs md:max-w-md">{{ $actor->actor_slug }}</div>
                    </div>
                </div>

                {{-- Right Area: Unified Pricing, Limit & Memory Config Panel (Micro Card) --}}
                <div class="flex flex-wrap items-center gap-4 bg-slate-50 border border-slate-100 rounded-2xl p-3 shrink-0 w-full lg:w-auto justify-between lg:justify-end">
                    {{-- Global Cost Display --}}
                    <div class="text-left px-2">
                        <div class="text-[8px] text-slate-400 font-bold uppercase tracking-wider">Biaya Global</div>
                        <div class="text-xs font-black text-slate-650 mt-0.5">
                            {{ $actor->maximum_cost_per_run_usd ? '$' . number_format($actor->maximum_cost_per_run_usd, 2) : '—' }}
                        </div>
                    </div>

                    {{-- Divider --}}
                    <div class="hidden sm:block h-6 w-px bg-slate-200/80"></div>

                    {{-- Cost Override Field --}}
                    <div class="text-left px-2">
                        <div class="text-[8px] text-slate-400 font-bold uppercase tracking-wider mb-1">Override Biaya ($)</div>
                        <div class="flex items-center">
                            <div class="relative flex items-center">
                                <span class="absolute left-2.5 text-slate-400 text-[10px] font-bold">$</span>
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="{{ $actor->maximum_cost_per_run_usd ? number_format($actor->maximum_cost_per_run_usd, 2) : 'Global' }}"
                                    wire:model.lazy="actorConfig.{{ $actor->id }}.cost_per_run_usd"
                                    style="padding-left: 1.5rem;"
                                    class="pr-1.5 py-1 w-24 text-center font-mono rounded-lg border text-xs transition-all focus:outline-none focus:ring-2 focus:ring-[#1fa387]/40 focus:border-[#1fa387]
                                    {{ $config['is_enabled'] ? 'border-slate-200 bg-white text-slate-800' : 'border-slate-150 bg-slate-100 text-slate-400' }}" />
                            </div>
                        </div>
                    </div>

                    {{-- Divider --}}
                    <div class="hidden sm:block h-6 w-px bg-slate-200/80"></div>

                    {{-- Default Limit Field --}}
                    <div class="text-left px-2">
                        <div class="text-[8px] text-slate-400 font-bold uppercase tracking-wider mb-1">Batas Hasil (Limit)</div>
                        <input
                            type="number"
                            min="1"
                            placeholder="{{ $actor->default_limit }}"
                            wire:model.lazy="actorConfig.{{ $actor->id }}.default_limit"
                            class="px-2 py-1 w-20 text-center font-mono rounded-lg border text-xs transition-all focus:outline-none focus:ring-2 focus:ring-[#1fa387]/40 focus:border-[#1fa387]
                            {{ $config['is_enabled'] ? 'border-slate-200 bg-white text-slate-800' : 'border-slate-150 bg-slate-100 text-slate-400' }}"
                            {{ !$config['is_enabled'] ? 'disabled' : '' }} />
                    </div>

                    {{-- Divider --}}
                    <div class="hidden sm:block h-6 w-px bg-slate-200/80"></div>

                    {{-- Memory Limit Select --}}
                    <div class="text-left px-2">
                        <div class="text-[8px] text-slate-400 font-bold uppercase tracking-wider mb-1">Alokasi RAM</div>
                        <select
                            wire:model="actorConfig.{{ $actor->id }}.memory_limit"
                            class="px-2 py-1 w-24 text-center rounded-lg border text-[11px] font-semibold transition-all focus:outline-none focus:ring-2 focus:ring-[#1fa387]/40 focus:border-[#1fa387] bg-white
                            {{ $config['is_enabled'] ? 'border-slate-200 text-slate-800' : 'border-slate-150 bg-slate-100 text-slate-400' }}"
                            {{ !$config['is_enabled'] ? 'disabled' : '' }}>
                            <option value="128">128 MB</option>
                            <option value="256">256 MB</option>
                            <option value="512">512 MB</option>
                            <option value="1024">1 GB</option>
                            <option value="2048">2 GB</option>
                            <option value="4096">4 GB</option>
                        </select>
                    </div>
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
        <div class="flex gap-3 justify-end px-8 py-5 border-t border-[#1fa387]/10 shrink-0 bg-white">
            <button type="button" wire:click="cancelActors" 
                class="px-5 py-2.5 bg-slate-50 border border-slate-200 hover:bg-slate-100 text-slate-700 font-bold rounded-xl text-xs transition cursor-pointer active:scale-95">
                Batal
            </button>
            <button type="button" wire:click="saveActors"
                wire:loading.attr="disabled"
                wire:target="saveActors"
                class="flex items-center gap-2 px-6 py-2.5 rounded-xl text-xs font-black text-white bg-[#1fa387] hover:bg-[#178a71] shadow-lg shadow-[#1fa387]/15 transition cursor-pointer active:scale-95 disabled:opacity-70 disabled:cursor-wait">
                <svg wire:loading.remove wire:target="saveActors" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24">
                    <path d="M5 4a1 1 0 0 1 1-1h10.586a1 1 0 0 1 .707.293l1.414 1.414A1 1 0 0 1 19 5.414V20a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M8 3v5h6V3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <svg wire:loading wire:target="saveActors" class="w-[18px] h-[18px] animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span wire:loading.remove wire:target="saveActors">Simpan Konfigurasi</span>
                <span wire:loading wire:target="saveActors">Menyimpan...</span>
            </button>
        </div>
    </div>
    @endif

</div>
