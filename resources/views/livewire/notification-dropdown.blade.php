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
        class="fixed inset-0 bg-slate-900/20 backdrop-blur-[3px] z-[50]"
        style="display: none;"
    ></div>

    <!-- Trigger Button (Z-index lifted to stay above backdrop blur) -->
    <button 
        type="button" 
        @click="open = !open"
        class="relative p-2.5 text-slate-500 hover:text-slate-700 transition-all hover:bg-slate-100 rounded-full z-[60] active:scale-95 cursor-pointer" 
        title="Notifikasi"
    >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
        </svg>
        @if($unreadCount > 0)
            <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white"></span>
        @endif
    </button>

    <!-- Clean Light Premium Dropdown Menu (Z-index lifted to stay above backdrop blur) -->
    <div 
        x-show="open" 
        style="display: none; width: 380px; max-width: 90vw; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0,0,0,0.02);"
        class="absolute right-0 mt-3 bg-white text-slate-800 rounded-[20px] z-[60] overflow-hidden flex flex-col ring-1 ring-slate-100"
    >
        <!-- Header -->
        <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <div class="flex items-center gap-2.5">
                <span class="relative flex h-2.5 w-2.5">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-rose-500"></span>
                </span>
                <span class="font-black text-[11px] uppercase tracking-widest text-slate-700">Peringatan Sentimen</span>
            </div>
            @if($unreadCount > 0)
                <span class="px-2.5 py-0.5 bg-rose-100/50 text-rose-600 rounded-full text-[10px] font-black uppercase tracking-wider">
                    {{ $unreadCount }} Baru
                </span>
            @endif
        </div>

        <!-- Scrollable list of items -->
        <div style="max-height: 380px; overflow-y: auto;" class="w-full divide-y divide-slate-100 bg-white">
            @forelse($notifications as $notif)
                <a 
                    href="{{ $notif['url'] }}" 
                    target="_blank" 
                    class="block px-5 py-4 hover:bg-slate-50 transition-colors group cursor-pointer"
                >
                    <div class="flex items-start gap-4">
                        <!-- Warning Icon wrapper -->
                        <div class="mt-0.5 p-2 bg-rose-50 text-rose-500 rounded-[14px] flex-shrink-0 flex items-center justify-center group-hover:scale-105 transition-transform">
                            <span class="material-symbols-outlined text-[18px]">warning</span>
                        </div>
                        
                        <!-- Details -->
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-bold text-slate-700 line-clamp-2 leading-snug mb-2 group-hover:text-rose-600 transition-colors text-left">
                                {{ Str::limit($notif['title'], 60) }}
                            </p>
                            
                            <!-- Badges -->
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="px-2 py-0.5 rounded-md text-[9px] font-bold uppercase tracking-wider {{ $notif['risk_level'] === 'high' || $notif['risk_level'] === 'critical' ? 'bg-rose-100 text-rose-700' : 'bg-orange-100 text-orange-700' }}">
                                    Risk: {{ $notif['risk_level'] }}
                                </span>
                                <span class="px-2 py-0.5 rounded-md text-[9px] font-bold uppercase tracking-wider bg-slate-100 text-slate-600">
                                    Reach: {{ $notif['reach_level'] }}
                                </span>
                            </div>
                            
                            <!-- Time elapsed -->
                            <div class="flex items-center gap-1.5 text-slate-400">
                                <span class="material-symbols-outlined text-[13px]">schedule</span>
                                <span class="text-[10px] font-semibold">{{ $notif['time'] }}</span>
                            </div>
                        </div>
                    </div>
                </a>
            @empty
                <div class="px-5 py-12 text-center text-slate-400 text-xs flex flex-col items-center justify-center gap-3">
                    <span class="material-symbols-outlined text-[32px] text-slate-300">inbox</span>
                    <span class="font-semibold text-slate-500">Belum ada sentimen negatif baru.</span>
                </div>
            @endforelse
        </div>

        <!-- Footer -->
        <div class="px-5 py-3.5 border-t border-slate-100 text-center bg-slate-50/50">
            <button wire:click.stop="markAllAsRead" class="text-[10px] font-black text-slate-400 hover:text-slate-600 transition-colors uppercase tracking-widest cursor-pointer w-full text-center">
                Tandai semua telah dibaca
            </button>
        </div>
    </div>
</div>
