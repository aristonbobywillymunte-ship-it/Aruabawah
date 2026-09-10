<div class="relative" x-data="{ open: false }" @click.outside="open = false">
    <!-- Trigger Button (Clean, matches profile button styling) -->
    <button 
        type="button" 
        @click="open = !open"
        class="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-full pl-3 pr-3.5 py-1.5 cursor-pointer hover:bg-slate-100 transition-colors duration-150 active:scale-95 z-[60] relative"
        title="Peringatan Sentimen"
    >
        <div class="relative flex items-center justify-center">
            <svg class="w-4 h-4 text-slate-500 hover:text-[#1fa387] transition-colors" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
            </svg>
        </div>
        <span class="text-xs font-semibold text-slate-600">Peringatan</span>
        @if($unreadCount > 0)
            <span class="px-1.5 py-0.2 text-[10px] font-bold bg-rose-50 text-rose-600 rounded-full border border-rose-200">
                {{ $unreadCount }}
            </span>
        @endif
    </button>

    <!-- Clean Solid Dropdown Panel -->
    <div 
        x-show="open" 
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        style="display: none; width: 380px; max-width: 92vw;"
        class="absolute right-0 mt-2 bg-white text-slate-800 rounded-2xl z-[100] overflow-hidden flex flex-col border border-slate-200 shadow-xl"
    >
        <!-- Header -->
        <div class="px-5 py-3.5 border-b border-slate-100 flex justify-between items-center bg-slate-50/70">
            <div class="flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-rose-500"></div>
                <span class="font-bold text-xs text-slate-700">Peringatan Sentimen Negatif</span>
            </div>
            @if($unreadCount > 0)
                <span class="px-2 py-0.5 bg-rose-50 text-rose-600 rounded-full text-[10px] font-bold border border-rose-100">
                    {{ $unreadCount }} Baru
                </span>
            @endif
        </div>

        <!-- Scrollable list of items -->
        <div style="max-height: 400px; overflow-y: auto;" class="w-full bg-white p-3 space-y-2 divide-y divide-slate-100">
            @forelse($notifications as $notif)
                <a 
                    href="{{ $notif['url'] }}" 
                    target="_blank" 
                    class="block p-3 hover:bg-slate-50 rounded-xl transition duration-150 group cursor-pointer"
                >
                    <div class="flex items-start gap-3">
                        <!-- Warning Icon wrapper -->
                        <div class="mt-0.5 w-7 h-7 bg-rose-50 text-rose-600 rounded-lg flex-shrink-0 flex items-center justify-center border border-rose-100">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                        
                        <!-- Details -->
                        <div class="flex-1 min-w-0 space-y-1 text-left">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wide">{{ $notif['source_type'] }}</span>
                                <span class="text-[10px] text-slate-400">{{ $notif['time'] }}</span>
                            </div>
                            <p class="text-xs font-semibold text-slate-800 leading-snug group-hover:text-[#1fa387] transition-colors line-clamp-2">
                                {{ Str::limit($notif['title'], 75) }}
                            </p>
                            
                            <!-- Badges Row -->
                            <div class="flex flex-wrap items-center gap-1.5 pt-1">
                                <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider {{ $notif['risk_level'] === 'high' || $notif['risk_level'] === 'critical' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                    {{ $notif['risk_label'] }}
                                </span>
                                <span class="px-2 py-0.5 rounded text-[9px] font-medium uppercase tracking-wider bg-slate-100 text-slate-600">
                                    {{ $notif['reach_label'] }}
                                </span>
                            </div>
                        </div>
                    </div>
                </a>
            @empty
                <div class="px-5 py-10 text-center text-slate-400 text-xs flex flex-col items-center justify-center gap-2">
                    <div class="w-10 h-10 bg-slate-50 rounded-full flex items-center justify-center text-slate-400 border border-slate-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                        </svg>
                    </div>
                    <span class="font-medium text-slate-500">Belum ada sentimen negatif baru.</span>
                </div>
            @endforelse
        </div>

        <!-- Footer -->
        <div class="px-4 py-2.5 border-t border-slate-100 text-center bg-slate-50/70">
            <button wire:click.stop="markAllAsRead" class="text-[11px] font-semibold text-slate-500 hover:text-[#1fa387] transition-colors cursor-pointer w-full text-center">
                Tandai semua telah dibaca
            </button>
        </div>
    </div>
</div>
