<div class="relative" x-data="{ open: false }">
    <!-- Backdrop Blur Focus Overlay (Dims and blurs page background behind dropdown) -->
    <div 
        x-show="open" 
        x-transition:enter="transition ease-out duration-250"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="open = false"
        class="fixed inset-0 bg-slate-900/10 backdrop-blur-[2px] z-[50]"
        style="display: none;"
    ></div>

    <!-- Trigger Button (Matching Profile Button style) -->
    <button 
        type="button" 
        @click="open = !open"
        class="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-full pl-3 pr-3.5 py-1.5 cursor-pointer hover:bg-slate-100 transition-all duration-200 active:scale-95 z-[60] relative"
        title="Peringatan Sentimen"
    >
        <div class="relative flex items-center justify-center">
            <svg class="w-4 h-4 text-slate-500 hover:text-[#1fa387] transition-colors" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
            </svg>
            @if($unreadCount > 0)
                <span class="absolute -top-1 -right-1 flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                </span>
            @endif
        </div>
        <span class="text-xs font-bold text-slate-600">Peringatan</span>
        @if($unreadCount > 0)
            <span class="px-1.5 py-0.5 text-[9px] font-black bg-rose-50 text-rose-600 rounded-full border border-rose-100">
                {{ $unreadCount }}
            </span>
        @endif
    </button>

    <!-- Clean Light Premium Dropdown Menu (Z-index lifted to stay above backdrop blur) -->
    <div 
        x-show="open" 
        style="display: none; width: 395px; max-width: 92vw; box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.08);"
        class="absolute right-0 mt-3 bg-white text-slate-800 rounded-[24px] z-[100] overflow-hidden flex flex-col border border-slate-100/80"
    >
        <!-- Header -->
        <div class="px-6 py-4.5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <div class="flex items-center gap-2">
                <div class="w-1.5 h-1.5 rounded-full bg-rose-500"></div>
                <span class="font-extrabold text-[11px] uppercase tracking-wider text-slate-600">Peringatan Sentimen</span>
            </div>
            @if($unreadCount > 0)
                <span class="px-2.5 py-0.5 bg-rose-50 text-rose-600 rounded-full text-[9px] font-black uppercase tracking-wide border border-rose-100/50">
                    {{ $unreadCount }} Baru
                </span>
            @endif
        </div>

        <!-- Scrollable list of items -->
        <div style="max-height: 420px; overflow-y: auto;" class="w-full bg-white px-5 py-5 space-y-3">
            @forelse($notifications as $notif)
                <a 
                    href="{{ $notif['url'] }}" 
                    target="_blank" 
                    class="block p-4.5 bg-[#F8F9FA] hover:bg-white hover:shadow-[0_12px_30px_rgba(0,0,0,0.04)] rounded-2xl transition duration-200 group cursor-pointer"
                >
                    <div class="flex items-start gap-4">
                        <!-- Warning Icon wrapper -->
                        <div class="mt-0.5 w-8 h-8 bg-rose-50/70 text-rose-550 rounded-full flex-shrink-0 flex items-center justify-center group-hover:scale-105 transition-transform duration-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        </div>
                        
                        <!-- Details -->
                        <div class="flex-1 min-w-0 space-y-1.5 text-left">
                            <p class="text-xs font-black text-slate-700 leading-snug group-hover:text-[#1fa387] transition-colors duration-150">
                                {{ Str::limit($notif['title'], 60) }}
                            </p>
                            
                            <!-- Badges Row -->
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider {{ $notif['risk_level'] === 'high' || $notif['risk_level'] === 'critical' ? 'bg-rose-50 text-rose-650' : 'bg-amber-50/70 text-amber-700' }}">
                                    Risk: {{ $notif['risk_level'] }}
                                </span>
                                <span class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider bg-slate-100/60 text-slate-500">
                                    Reach: {{ $notif['reach_level'] }}
                                </span>
                            </div>
                            
                            <!-- Time elapsed -->
                            <div class="flex items-center gap-1 text-[9px] font-semibold text-slate-400 pt-0.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span>{{ $notif['time'] }}</span>
                            </div>
                        </div>
                    </div>
                </a>
            @empty
                <div class="px-5 py-12 text-center text-slate-400 text-xs flex flex-col items-center justify-center gap-3">
                    <div class="w-12 h-12 bg-slate-50 rounded-full flex items-center justify-center text-slate-350 border border-slate-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                    </div>
                    <span class="font-bold text-slate-500">Belum ada sentimen negatif baru.</span>
                </div>
            @endforelse
        </div>

        <!-- Footer -->
        <div class="px-5 py-3.5 border-t border-slate-100 text-center bg-slate-50/50">
            <button wire:click.stop="markAllAsRead" class="text-[10px] font-black text-slate-400 hover:text-[#1fa387] transition-colors uppercase tracking-widest cursor-pointer w-full text-center">
                Tandai semua telah dibaca
            </button>
        </div>
    </div>
</div>
