<div class="space-y-4">
    {{-- Back to Projects (hanya untuk role user biasa, bukan admin) --}}
    @if(auth()->user()->isUser())
        <div>
            <a href="{{ route('home') }}" wire:navigate
               class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-slate-500 hover:text-[#1fa387] hover:bg-[#1fa387]/5 text-xs font-bold transition-all">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                <span>Kembali ke Proyek</span>
            </a>
        </div>
    @endif

    {{-- Unified Toolbar --}}
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="relative w-full sm:w-80">
            <span wire:loading.remove wire:target="search" class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
            <span wire:loading wire:target="search" class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-[#1fa387] text-[18px] animate-spin">progress_activity</span>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari nama atau email klien..."
                   class="w-full pl-10 pr-4 py-2.5 text-xs sm:text-sm font-medium bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#1fa387]/20 focus:border-[#1fa387] transition-all shadow-sm">
        </div>

        <a href="{{ route('admin.clients.create') }}" wire:navigate
           class="cursor-pointer inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-[#1fa387] hover:bg-[#188c73] text-white rounded-xl text-xs sm:text-sm font-bold transition-all shadow-sm shrink-0 w-full sm:w-auto">
            <span class="material-symbols-outlined text-[18px]">person_add</span>
            <span>Tambah Klien</span>
        </a>
    </div>

    {{-- Table Container --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[700px]">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-[11px] sm:text-xs font-bold uppercase tracking-wider">
                        <th class="px-6 py-4 border-b border-slate-200">Informasi Klien</th>
                        <th class="px-6 py-4 border-b border-slate-200">Dibuat Oleh</th>
                        <th class="px-6 py-4 border-b border-slate-200 text-center">Status</th>
                        <th class="px-6 py-4 border-b border-slate-200 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                    @forelse($clients as $client)
                        <tr class="hover:bg-slate-50/70 transition-colors group">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-[#1fa387]/10 border border-[#1fa387]/20 flex items-center justify-center flex-shrink-0 text-[#1fa387] font-bold text-sm">
                                        {{ strtoupper(substr($client->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900 group-hover:text-[#1fa387] transition-colors">{{ $client->name }}</div>
                                        <div class="text-slate-500 text-xs font-medium">{{ $client->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-50 border border-slate-100 text-slate-600 text-xs font-medium">
                                    <span class="material-symbols-outlined text-[14px] text-slate-400">person</span>
                                    {{ optional($client->creator)->name ?? 'Sistem' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($client->status === 'active')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-600 text-[10px] font-bold uppercase tracking-wider border border-emerald-100">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Aktif
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-slate-50 text-slate-500 text-[10px] font-bold uppercase tracking-wider border border-slate-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        Nonaktif
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                                    <button wire:click="requestToggleStatus({{ $client->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="requestToggleStatus({{ $client->id }})"
                                            class="cursor-pointer inline-flex items-center justify-center w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-500 hover:border-amber-500 hover:text-amber-500 hover:bg-amber-50 transition-all shadow-sm disabled:opacity-50"
                                            title="{{ $client->status === 'active' ? 'Nonaktifkan Akun' : 'Aktifkan Akun' }}">
                                        <span wire:loading.remove wire:target="requestToggleStatus({{ $client->id }})" class="material-symbols-outlined text-[16px]">{{ $client->status === 'active' ? 'do_not_disturb_on' : 'check_circle' }}</span>
                                        <span wire:loading wire:target="requestToggleStatus({{ $client->id }})" class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                                    </button>
                                    <a href="{{ route('admin.clients.settings', $client->id) }}" wire:navigate
                                       class="cursor-pointer inline-flex items-center justify-center w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-500 hover:border-[#1fa387] hover:text-[#1fa387] hover:bg-[#1fa387]/5 transition-all shadow-sm"
                                       title="Pengaturan & Limitasi Klien">
                                        <span class="material-symbols-outlined text-[16px]">settings</span>
                                    </a>
                                    <button wire:click="requestDelete({{ $client->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="requestDelete({{ $client->id }})"
                                            class="cursor-pointer inline-flex items-center justify-center w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-500 hover:border-rose-500 hover:text-rose-500 hover:bg-rose-50 transition-all shadow-sm disabled:opacity-50"
                                            title="Hapus Klien">
                                        <span wire:loading.remove wire:target="requestDelete({{ $client->id }})" class="material-symbols-outlined text-[16px]">delete</span>
                                        <span wire:loading wire:target="requestDelete({{ $client->id }})" class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-16 text-center text-slate-500">
                                <div class="w-16 h-16 rounded-full bg-slate-50 flex items-center justify-center mx-auto mb-4 border border-slate-100">
                                    <span class="material-symbols-outlined text-3xl text-slate-400">group</span>
                                </div>
                                <h3 class="text-lg font-bold text-slate-800 mb-1">Belum Ada Klien</h3>
                                <p class="text-sm max-w-sm mx-auto mb-6">Anda belum mendaftarkan akun klien. Buat klien baru untuk mulai membagikan akses dashboard.</p>
                                <a href="{{ route('admin.clients.create') }}" wire:navigate class="cursor-pointer inline-flex items-center gap-2 px-5 py-2.5 bg-[#1fa387] hover:bg-[#188c73] text-white rounded-xl text-sm font-semibold transition-colors shadow-sm">
                                    <span class="material-symbols-outlined text-[18px]">person_add</span>
                                    Buat Klien Pertama
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($clients->hasPages())
            <div class="px-6 py-4 border-t border-slate-100 bg-white">
                {{ $clients->links() }}
            </div>
        @endif
    </div>

    <!-- Status Change Confirmation Modal -->
    @if($confirmingStatusChange)
        <div wire:key="status-modal-{{ $statusClientId }}"
             x-data
             x-init="document.body.classList.add('overflow-hidden'); return () => document.body.classList.remove('overflow-hidden');"
             @keydown.escape.window="$wire.set('confirmingStatusChange', false)"
             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
            <div @click.outside="$wire.set('confirmingStatusChange', false)"
                 class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl text-left space-y-4 border border-slate-100">
                <div class="flex items-center gap-3.5">
                    <span class="w-12 h-12 rounded-2xl {{ $targetStatus === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600' }} flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[24px]">{{ $targetStatus === 'active' ? 'check_circle' : 'do_not_disturb_on' }}</span>
                    </span>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider {{ $targetStatus === 'active' ? 'text-emerald-600' : 'text-amber-600' }}">Konfirmasi Status</p>
                        <h2 class="text-base font-black text-slate-900 leading-snug">{{ $targetStatus === 'active' ? 'Aktifkan Akun Klien?' : 'Nonaktifkan Akun Klien?' }}</h2>
                    </div>
                </div>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Apakah Anda yakin ingin mengubah status akun <strong>{{ $statusClientName }}</strong> menjadi <strong>{{ $targetStatus === 'active' ? 'Aktif' : 'Nonaktif' }}</strong>?
                    {{ $targetStatus === 'inactive' ? 'Klien tidak dapat login ke dashboard selama akun dalam status nonaktif.' : 'Klien akan dapat kembali login dan mengakses proyek yang diberikan.' }}
                </p>
                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                    <button type="button"
                            wire:click="$set('confirmingStatusChange', false)"
                            wire:loading.attr="disabled"
                            class="px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer disabled:opacity-50">
                        Batal
                    </button>
                    <button type="button"
                            wire:click="toggleStatusConfirmed"
                            wire:loading.attr="disabled"
                            wire:target="toggleStatusConfirmed"
                            class="px-6 py-2.5 rounded-xl {{ $targetStatus === 'active' ? 'bg-[#1fa387] hover:bg-[#188c73]' : 'bg-amber-500 hover:bg-amber-600' }} text-white text-xs font-bold transition cursor-pointer flex items-center gap-1.5 disabled:opacity-50 shadow-sm">
                        <span wire:loading wire:target="toggleStatusConfirmed" class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                        <span>{{ $targetStatus === 'active' ? 'Ya, Aktifkan' : 'Ya, Nonaktifkan' }}</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Delete Confirmation Modal -->
    @if($confirmingDelete)
        <div wire:key="delete-modal-{{ $deleteClientId }}"
             x-data
             x-init="document.body.classList.add('overflow-hidden'); return () => document.body.classList.remove('overflow-hidden');"
             @keydown.escape.window="$wire.set('confirmingDelete', false)"
             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
            <div @click.outside="$wire.set('confirmingDelete', false)"
                 class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl text-left space-y-4 border border-slate-100">
                <div class="flex items-center gap-3.5">
                    <span class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[24px]">warning</span>
                    </span>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-rose-500">Konfirmasi Hapus</p>
                        <h2 class="text-base font-black text-slate-900 leading-snug">Hapus Klien Permanen?</h2>
                    </div>
                </div>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Apakah Anda yakin ingin menghapus akun klien <strong>{{ $deleteClientName }}</strong> secara permanen?
                    Tindakan ini tidak dapat dibatalkan dan seluruh konfigurasi limit klien akan dibersihkan. Data artikel dan proyek yang sudah ada tetap aman.
                </p>
                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                    <button type="button"
                            wire:click="$set('confirmingDelete', false)"
                            wire:loading.attr="disabled"
                            class="px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer disabled:opacity-50">
                        Batal
                    </button>
                    <button type="button"
                            wire:click="deleteConfirmed"
                            wire:loading.attr="disabled"
                            wire:target="deleteConfirmed"
                            class="px-6 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition cursor-pointer flex items-center gap-1.5 disabled:opacity-50 shadow-sm">
                        <span wire:loading wire:target="deleteConfirmed" class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                        <span>Ya, Hapus Permanen</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
