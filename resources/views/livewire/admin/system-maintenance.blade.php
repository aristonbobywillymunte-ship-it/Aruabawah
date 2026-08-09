<div class="mx-auto w-full max-w-7xl space-y-6 font-sans">
    <!-- Header Section -->
    <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 lg:flex-row lg:items-end lg:justify-between text-left">
        <div class="max-w-3xl space-y-1">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Panel Administrator</p>
            <h1 class="text-2xl font-black leading-tight text-slate-900">System Maintenance</h1>
            <p class="text-xs text-slate-500">Aksi pembersihan antrean, pengelolaan worker queue, dan optimasi cache aplikasi.</p>
        </div>
    </div>

    <!-- Maintenance Card -->
    <div class="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm text-left">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-xl">
                <h2 class="text-base font-extrabold text-slate-800 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#1fa387]">cleaning_services</span>
                    <span>System Maintenance Panel</span>
                </h2>
                <p class="mt-2 text-xs text-slate-500 leading-relaxed">
                    Gunakan panel ini untuk mengelola performa dan kebersihan data sistem secara berkala. Aksi di bawah ini berjalan secara *real-time* dan aktivitasnya akan dicatat otomatis ke Log Sistem untuk kebutuhan audit.
                </p>
            </div>
            
            <div class="flex flex-wrap items-center gap-3">
                <!-- Clear Redis Queue -->
                <button
                    wire:click="confirmClearRedisQueue"
                    wire:loading.attr="disabled"
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
                    Apakah Anda yakin ingin menghapus seluruh antrean kerja aktif (scraping, analisis, notifikasi) di Redis? Tindakan ini tidak dapat dibatalkan.
                </p>

                <div class="flex justify-end gap-3">
                    <button
                        wire:click="cancelClearRedisQueue"
                        class="px-4 py-2 text-xs font-bold text-slate-500 rounded-xl hover:bg-slate-100 transition cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        wire:click="clearRedisQueue"
                        class="px-4 py-2 text-xs font-bold text-white bg-rose-600 rounded-xl hover:bg-rose-700 transition cursor-pointer flex items-center gap-1.5 shadow-sm"
                    >
                        <svg wire:loading wire:target="clearRedisQueue" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        <span>Ya, Hapus Semua</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
