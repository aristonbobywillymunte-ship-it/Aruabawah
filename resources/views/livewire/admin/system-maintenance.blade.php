<div class="mx-auto w-full max-w-7xl space-y-6 font-sans">

    <!-- Maintenance Card -->
    <div class="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm text-left">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-xl">
                <h2 class="text-base font-extrabold text-slate-800 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#1fa387]">cleaning_services</span>
                    <span>System Maintenance Panel</span>
                </h2>
                <p class="mt-2 text-xs text-slate-500 leading-relaxed">
                    Gunakan panel ini untuk mengelola antrean background, restart worker, dan pembersihan cache aplikasi. Setiap aksi dicatat ke Log Sistem untuk kebutuhan audit.
                </p>
            </div>
            
            <div class="flex flex-wrap items-center gap-3">
                <!-- Clear Redis Queue -->
                <button
                    wire:click="confirmClearRedisQueue"
                    wire:loading.attr="disabled"
                    wire:target="confirmClearRedisQueue, clearRedisQueue"
                    class="inline-flex h-11 items-center gap-2.5 rounded-2xl border border-slate-200 bg-white px-5 text-xs font-bold text-slate-700 transition hover:border-rose-500 hover:bg-rose-50 hover:text-rose-600 cursor-pointer disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="confirmClearRedisQueue, clearRedisQueue" class="material-symbols-outlined text-[18px]">delete_sweep</span>
                    <svg wire:loading wire:target="confirmClearRedisQueue, clearRedisQueue" class="animate-spin -ml-1 mr-1 h-4.5 w-4.5 text-rose-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span>Clear Redis Queue</span>
                </button>

                <!-- Restart Worker -->
                <button
                    wire:click="restartWorkers"
                    wire:loading.attr="disabled"
                    wire:target="restartWorkers"
                    class="inline-flex h-11 items-center gap-2.5 rounded-2xl border border-slate-200 bg-white px-5 text-xs font-bold text-slate-700 transition hover:border-[#1fa387] hover:bg-[#1fa387]/5 hover:text-[#1fa387] cursor-pointer disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="restartWorkers" class="material-symbols-outlined text-[18px]">restart_alt</span>
                    <svg wire:loading wire:target="restartWorkers" class="animate-spin -ml-1 mr-1 h-4.5 w-4.5 text-[#1fa387]" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span>Restart Worker</span>
                </button>

                <!-- Restart Scheduler -->
                <button
                    wire:click="restartScheduler"
                    wire:loading.attr="disabled"
                    wire:target="restartScheduler"
                    class="inline-flex h-11 items-center gap-2.5 rounded-2xl border border-slate-200 bg-white px-5 text-xs font-bold text-slate-700 transition hover:border-[#1fa387] hover:bg-[#1fa387]/5 hover:text-[#1fa387] cursor-pointer disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="restartScheduler" class="material-symbols-outlined text-[18px]">schedule</span>
                    <svg wire:loading wire:target="restartScheduler" class="animate-spin -ml-1 mr-1 h-4.5 w-4.5 text-[#1fa387]" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span>Restart Scheduler</span>
                </button>

                <!-- Clear Cache -->
                <button
                    wire:click="clearMaintenanceCache"
                    wire:loading.attr="disabled"
                    wire:target="clearMaintenanceCache"
                    class="inline-flex h-11 items-center gap-2.5 rounded-2xl bg-[#1fa387] px-5 text-xs font-bold text-white transition hover:bg-[#1a8b73] cursor-pointer shadow-sm disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="clearMaintenanceCache" class="material-symbols-outlined text-[18px]">cleaning_services</span>
                    <svg wire:loading wire:target="clearMaintenanceCache" class="animate-spin -ml-1 mr-1 h-4.5 w-4.5 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span>Clear Cache</span>
                </button>
            </div>
        </div>

        @if($maintenanceSummary)
            <div class="mt-6 rounded-2xl border border-[#1fa387]/10 bg-[#1fa387]/5 px-5 py-4">
                <div class="text-xs font-extrabold uppercase tracking-wider text-[#1fa387] flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]">info</span>
                    <span>{{ $maintenanceSummary['title'] }}</span>
                </div>
                <div class="mt-1 text-xs font-medium text-slate-600 leading-relaxed">{{ $maintenanceSummary['detail'] }}</div>
            </div>
        @endif
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Queue Snapshot</p>
                <h3 class="mt-1 text-sm font-black text-slate-900">Ringkasan antrean yang dilindungi panel maintenance</h3>
            </div>
            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500">Pending + Delayed + Reserved</span>
        </div>

        <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 font-bold text-slate-700">Connection</th>
                        <th class="px-4 py-3 font-bold text-slate-700">Queue</th>
                        <th class="px-4 py-3 font-bold text-slate-700">Status</th>
                        <th class="px-4 py-3 font-bold text-slate-700">Pending</th>
                        <th class="px-4 py-3 font-bold text-slate-700">Delayed</th>
                        <th class="px-4 py-3 font-bold text-slate-700">Reserved</th>
                        <th class="px-4 py-3 font-bold text-slate-700">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($queueSnapshot as $queue)
                        <tr>
                            <td class="px-4 py-3 font-semibold text-slate-700">{{ $queue['queue_connection'] }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $queue['queue'] }}</td>
                            <td class="px-4 py-3">
                                @if(($queue['status'] ?? 'ok') === 'ok')
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-bold uppercase tracking-[0.12em] text-emerald-700">OK</span>
                                @else
                                    <span class="inline-flex rounded-full bg-rose-50 px-2 py-1 text-[10px] font-bold uppercase tracking-[0.12em] text-rose-700">Tidak tersedia</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $queue['status'] === 'ok' ? $queue['pending'] : '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $queue['status'] === 'ok' ? $queue['delayed'] : '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $queue['status'] === 'ok' ? $queue['reserved'] : '—' }}</td>
                            <td class="px-4 py-3 font-bold text-slate-900">{{ $queue['status'] === 'ok' ? $queue['total'] : '—' }}</td>
                        </tr>
                        @if(($queue['status'] ?? 'ok') !== 'ok' && ! empty($queue['error']))
                            <tr class="bg-rose-50/40">
                                <td colspan="7" class="px-4 py-2 text-[11px] text-rose-700">
                                    {{ $queue['error'] }}
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-slate-500">Tidak ada antrean yang terdaftar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Confirmation Modal -->
    @if($showConfirmModal)
        <div 
            class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
            x-data="{ show: @entangle('showConfirmModal') }"
            x-show="show"
            x-transition
        >
            <div class="bg-white rounded-3xl w-full max-w-md shadow-2xl overflow-hidden p-8 border border-slate-100 space-y-6 text-left">
                <div class="flex items-center gap-3 text-rose-600">
                    <div class="w-10 h-10 rounded-full bg-rose-50 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[24px]">warning</span>
                    </div>
                    <h3 class="text-base font-extrabold text-slate-900">Bersihkan Antrean Redis?</h3>
                </div>

                <p class="text-xs text-slate-500 leading-relaxed">
                    Aksi ini hanya menghapus job yang masih menunggu dan delayed di Redis. Job yang sedang berjalan tetap dibiarkan. Untuk melanjutkan, ketik <strong>{{ $requiredConfirmationPhrase }}</strong> di bawah ini.
                </p>

                <div class="space-y-2">
                    <label class="block text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Konfirmasi Tertulis</label>
                    <input
                        type="text"
                        wire:model.live="clearConfirmation"
                        placeholder="Ketik {{ $requiredConfirmationPhrase }}"
                        class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-800 outline-none transition focus:border-rose-400 focus:ring-2 focus:ring-rose-100"
                    >
                    <p class="text-[11px] text-slate-400">Aksi tidak akan berjalan sampai teks cocok persis.</p>
                </div>

                <div class="flex justify-end gap-3">
                    <button
                        wire:click="cancelClearRedisQueue"
                        class="px-4 py-2 text-xs font-bold text-slate-500 rounded-xl hover:bg-slate-100 transition cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        wire:click="clearRedisQueue"
                        wire:loading.attr="disabled"
                        wire:target="clearRedisQueue"
                        @disabled($clearConfirmation !== $requiredConfirmationPhrase)
                        class="px-4 py-2 text-xs font-bold text-white bg-rose-600 rounded-xl hover:bg-rose-700 transition cursor-pointer flex items-center gap-1.5 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg wire:loading wire:target="clearRedisQueue" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        <span>Ya, Bersihkan Antrean</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
