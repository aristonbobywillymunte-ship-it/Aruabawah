<div class="p-6 max-w-7xl mx-auto py-10">
    <div class="mb-10">
        <a href="{{ route('home') }}" wire:navigate
           class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#1fa387] hover:text-[#178a71] transition-colors mb-6 group">
            <span class="material-symbols-outlined text-[18px] group-hover:-translate-x-0.5 transition-transform">arrow_back</span>
            Kembali ke Proyek
        </a>
    </div>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Manajemen Klien</h1>
            <p class="text-slate-500 text-sm mt-1">Kelola akun klien, batas limit, dan izin proyek.</p>
        </div>
        <a href="{{ route('admin.clients.create') }}" wire:navigate class="bg-[#1fa387] hover:bg-[#178a71] text-white px-4 py-2 rounded-xl text-sm font-bold flex items-center gap-2 transition-colors">
            <span class="material-symbols-outlined text-[18px]">person_add</span>
            Tambah Klien
        </a>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <div class="relative w-72">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari klien..." class="w-full pl-9 pr-4 py-2 text-sm bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#1fa387]/20 focus:border-[#1fa387] transition-all">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                        <th class="px-6 py-4 font-semibold border-b border-slate-200">Klien</th>
                        <th class="px-6 py-4 font-semibold border-b border-slate-200">Dibuat Oleh</th>
                        <th class="px-6 py-4 font-semibold border-b border-slate-200 text-center">Status</th>
                        <th class="px-6 py-4 font-semibold border-b border-slate-200 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                    @forelse($clients as $client)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-900">{{ $client->name }}</div>
                                <div class="text-slate-500 text-xs">{{ $client->email }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-slate-700">{{ optional($client->creator)->name ?? 'Sistem' }}</div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                @if($client->status === 'active')
                                    <span class="inline-flex px-2 py-1 rounded-full bg-green-50 text-green-600 text-[10px] font-bold uppercase tracking-wider">Aktif</span>
                                @else
                                    <span class="inline-flex px-2 py-1 rounded-full bg-slate-100 text-slate-500 text-[10px] font-bold uppercase tracking-wider">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('admin.clients.settings', $client->id) }}" wire:navigate class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 text-slate-600 hover:bg-[#1fa387] hover:text-white transition-colors" title="Pengaturan Klien">
                                    <span class="material-symbols-outlined text-[18px]">settings</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-slate-500">
                                <span class="material-symbols-outlined text-[32px] mb-2 text-slate-300">group_off</span>
                                <p>Belum ada klien ditemukan.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($clients->hasPages())
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50">
                {{ $clients->links() }}
            </div>
        @endif
    </div>
</div>
