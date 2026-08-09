<div>
    {{-- Page Header --}}
    <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 lg:flex-row lg:items-end lg:justify-between">
        <div class="max-w-3xl text-left space-y-1">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Panel Administrator</p>
            <h1 class="text-2xl font-black leading-tight text-slate-900">Manajemen Klien</h1>
            <p class="text-xs text-slate-500">Kelola akun klien, batas limit, dan izin proyek secara real-time.</p>
        </div>
        <a href="{{ route('admin.clients.create') }}" wire:navigate
           class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 bg-[#1fa387] hover:bg-[#188c73] text-white rounded-xl text-sm font-semibold transition-all shadow-sm shrink-0">
            <span class="material-symbols-outlined text-[18px]">person_add</span>
            <span>Tambah Klien</span>
        </a>
    </div>

    {{-- Table Container --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        {{-- Toolbar --}}
        <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-4 bg-slate-50/50">
            <div class="relative w-full sm:w-80">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari nama atau email klien..."
                       class="w-full pl-10 pr-4 py-2.5 text-sm font-medium bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#1fa387]/20 focus:border-[#1fa387] transition-all shadow-sm">
            </div>
        </div>

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
                                    <button wire:click="toggleStatus({{ $client->id }})"
                                            class="cursor-pointer inline-flex items-center justify-center w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-500 hover:border-amber-500 hover:text-amber-500 hover:bg-amber-50 transition-all shadow-sm"
                                            title="{{ $client->status === 'active' ? 'Nonaktifkan Akun' : 'Aktifkan Akun' }}">
                                        <span class="material-symbols-outlined text-[16px]">{{ $client->status === 'active' ? 'do_not_disturb_on' : 'check_circle' }}</span>
                                    </button>
                                    <a href="{{ route('admin.clients.settings', $client->id) }}" wire:navigate
                                       class="cursor-pointer inline-flex items-center justify-center w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-500 hover:border-[#1fa387] hover:text-[#1fa387] hover:bg-[#1fa387]/5 transition-all shadow-sm"
                                       title="Pengaturan & Limitasi Klien">
                                        <span class="material-symbols-outlined text-[16px]">settings</span>
                                    </a>
                                    <button wire:click="deleteClient({{ $client->id }})" wire:confirm="Apakah Anda yakin ingin menghapus klien ini permanen? Tindakan ini tidak dapat dibatalkan."
                                            class="cursor-pointer inline-flex items-center justify-center w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-500 hover:border-rose-500 hover:text-rose-500 hover:bg-rose-50 transition-all shadow-sm"
                                            title="Hapus Klien">
                                        <span class="material-symbols-outlined text-[16px]">delete</span>
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
</div>
