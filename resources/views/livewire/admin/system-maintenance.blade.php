<div class="mx-auto w-full max-w-7xl space-y-6 font-sans">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-200 pb-5 text-left">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Panel Administrator</p>
            <h1 class="text-2xl font-black text-slate-900 mt-1">System Maintenance</h1>
            <p class="text-xs text-slate-500 mt-1">Aksi pembersihan antrean, pengelolaan worker queue, dan optimasi cache aplikasi.</p>
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
                <!-- Clear Apify Queue -->
                <button
                    wire:click="clearApifyQueue"
                    class="inline-flex h-11 items-center gap-2.5 rounded-2xl border border-slate-200 bg-white px-5 text-xs font-bold text-slate-700 transition hover:border-rose-500 hover:bg-rose-50 hover:text-rose-600 cursor-pointer"
                >
                    <span class="material-symbols-outlined text-[18px]">delete_sweep</span>
                    <span>Clear Apify Queue</span>
                </button>

                <!-- Restart Worker -->
                <button
                    wire:click="restartWorkers"
                    class="inline-flex h-11 items-center gap-2.5 rounded-2xl border border-slate-200 bg-white px-5 text-xs font-bold text-slate-700 transition hover:border-[#1fa387] hover:bg-[#1fa387]/5 hover:text-[#1fa387] cursor-pointer"
                >
                    <span class="material-symbols-outlined text-[18px]">restart_alt</span>
                    <span>Restart Worker</span>
                </button>

                <!-- Restart Scheduler -->
                <button
                    wire:click="restartScheduler"
                    class="inline-flex h-11 items-center gap-2.5 rounded-2xl border border-slate-200 bg-white px-5 text-xs font-bold text-slate-700 transition hover:border-[#1fa387] hover:bg-[#1fa387]/5 hover:text-[#1fa387] cursor-pointer"
                >
                    <span class="material-symbols-outlined text-[18px]">schedule</span>
                    <span>Restart Scheduler</span>
                </button>

                <!-- Clear Cache -->
                <button
                    wire:click="clearMaintenanceCache"
                    class="inline-flex h-11 items-center gap-2.5 rounded-2xl bg-[#1fa387] px-5 text-xs font-bold text-white transition hover:bg-[#1a8b73] cursor-pointer shadow-sm"
                >
                    <span class="material-symbols-outlined text-[18px]">cleaning_services</span>
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

    <!-- Reverb Server Control Card -->
    <div class="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm text-left">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-xl">
                <h2 class="text-base font-extrabold text-slate-800 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#1fa387]">sensors</span>
                    <span>Laravel Reverb Server Control</span>
                </h2>
                <p class="mt-2 text-xs text-slate-500 leading-relaxed">
                    Kontrol proses server WebSocket Laravel Reverb di latar belakang. Fitur ini memungkinkan Anda menyalakan atau mematikan daemon secara instan tanpa melalui terminal.
                </p>
                <div class="mt-4 flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold {{ $isReverbRunning ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' }}">
                        <span class="flex h-2 w-2 relative">
                            @if($isReverbRunning)
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            @endif
                            <span class="relative inline-flex rounded-full h-2 w-2 {{ $isReverbRunning ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                        </span>
                        <span>Server Status: {{ $isReverbRunning ? 'Running (Aktif)' : 'Stopped (Mati)' }}</span>
                    </span>
                </div>
            </div>
            
            <div class="flex items-center gap-3">
                @if($isReverbRunning)
                    <button
                        wire:click="stopReverb"
                        class="inline-flex h-11 items-center gap-2.5 rounded-2xl border border-rose-200 bg-rose-50 px-5 text-xs font-bold text-rose-700 transition hover:bg-rose-100 cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[18px]">power_settings_new</span>
                        <span>Matikan Server Reverb</span>
                    </button>
                @else
                    <button
                        wire:click="startReverb"
                        class="inline-flex h-11 items-center gap-2.5 rounded-2xl bg-[#1fa387] px-5 text-xs font-bold text-white transition hover:bg-[#1a8b73] cursor-pointer shadow-sm"
                    >
                        <span class="material-symbols-outlined text-[18px]">power_settings_new</span>
                        <span>Nyalakan Server Reverb</span>
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
