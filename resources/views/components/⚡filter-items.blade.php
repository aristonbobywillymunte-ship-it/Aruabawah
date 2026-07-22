@php $filterContext = $filterContext ?? 'default'; @endphp
@if($this->isTab('laporan'))
    <!-- Laporan Filter Panel (matching screenshot) -->
    <div class="space-y-1.5 text-left">
        <label class="text-xs font-bold text-slate-650">Periode</label>
        <div class="relative">
            <select 
                class="w-full bg-[#f8f9fa] border border-slate-200 rounded-xl px-4 py-2.5 text-xs text-slate-800 focus:outline-none focus:border-[#1fa387] focus:bg-white transition cursor-pointer appearance-none font-semibold"
            >
                <option value="daily">Harian</option>
                <option value="weekly">Mingguan</option>
                <option value="monthly">Bulanan</option>
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
            </div>
        </div>
    </div>
@else
    <!-- Search Panel -->
    <div class="space-y-1.5">
        <label class="text-sm font-bold text-slate-700">Pencarian</label>
        <div class="relative">
            <input 
                wire:model.live.debounce.300ms="search" 
                type="text" 
                placeholder="Cari..."
                class="w-full bg-[#f8f9fa] border border-slate-200 focus:border-primary focus:ring-1 focus:ring-primary rounded-xl pl-3 pr-9 py-2.5 text-xs text-slate-800 placeholder-[#727785] transition"
            >
            <svg class="w-4 h-4 text-slate-400 absolute right-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
        </div>
    </div>

    <!-- Date Range Selector (Triggers Custom DatePicker Modal) -->
    <div class="space-y-1.5">
        <label class="text-sm font-bold text-slate-700">Rentang Tanggal</label>
        <div class="relative">
            <button 
                type="button"
                wire:click="$set('showDatePicker', true)"
                class="w-full bg-[#f8f9fa] border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-700 hover:bg-slate-50 transition flex items-center justify-between font-semibold"
            >
                <span>{{ $startDate ? \Carbon\Carbon::parse($startDate)->format('d/m/Y') . ($endDate && $endDate !== $startDate ? ' - ' . \Carbon\Carbon::parse($endDate)->format('d/m/Y') : '') : 'Semua Waktu' }}</span>
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
            </button>
        </div>
    </div>

    <div class="desktop-filter-scroll space-y-6">
        <!-- Sumber Checklist -->
        <div class="space-y-3">
        <div class="flex justify-between items-center">
            <label class="text-sm font-bold text-slate-700">Sumber Data</label>
            <button wire:click="$set('selectedSources', ['Instagram', 'TikTok', 'Facebook', 'News'])" class="text-xs text-[#1fa387] hover:underline font-bold">Pilih Semua</button>
        </div>
        <div class="space-y-2.5">
            <!-- Instagram -->
            <label class="flex items-center justify-between cursor-pointer group py-0.5 gap-3">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <input wire:key="src-ig-{{ $filterContext }}" wire:model.live="selectedSources" value="Instagram" type="checkbox" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387] w-4 h-4">
                    <div class="w-8 h-8 rounded-lg bg-fuchsia-500 shadow-sm shadow-fuchsia-500/20 flex items-center justify-center" style="background-color: #e1306c;">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M12 4.4c2.1 0 2.4.01 3.3.05.9.04 1.5.18 2 .38.5.2.9.44 1.3.82.38.4.62.8.82 1.3.2.5.34 1.1.38 2 .04.9.05 1.2.05 3.3s-.01 2.4-.05 3.3c-.04.9-.18 1.5-.38 2-.2.5-.44.9-.82 1.3-.4.38-.8.62-1.3.82-.5.2-1.1.34-2 .38-.9.04-1.2.05-3.3.05s-2.4-.01-3.3-.05c-.9-.04-1.5-.18-2-.38-.5-.2-.9-.44-1.3-.82-.38-.4-.62-.8-.82-1.3-.2-.5-.34-1.1-.38-2C4.45 14.4 4.4 14.1 4.4 12s.01-2.4.05-3.3c.04-.9.18-1.5.38-2 .2-.5.44-.9.82-1.3.4-.38.8-.62 1.3-.82.5-.2 1.1-.34 2-.38C9.6 4.41 9.9 4.4 12 4.4Z"></path>
                            <circle cx="12" cy="12" r="3.1"></circle>
                            <circle cx="16.9" cy="7.1" r="1"></circle>
                        </svg>
                    </div>
                    <span class="text-sm text-slate-700 group-hover:text-slate-900 font-semibold transition truncate">Instagram</span>
                </div>
                <span class="text-xs font-bold text-slate-400 tabular-nums w-6 text-right flex-shrink-0">{{ $counts['sources']['Instagram'] ?? 0 }}</span>
            </label>

            <!-- TikTok -->
            <label class="flex items-center justify-between cursor-pointer group py-0.5 gap-3">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <input wire:key="src-tt-{{ $filterContext }}" wire:model.live="selectedSources" value="TikTok" type="checkbox" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387] w-4 h-4">
                    <div class="w-8 h-8 rounded-lg bg-slate-950 shadow-sm shadow-slate-900/20 flex items-center justify-center" style="background-color: #000000;">
                        <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
                            <path fill="currentColor" d="M16.4 4.3h2.2c.1 1.7 1.1 3.2 2.7 3.9v2.3c-1.1 0-2.2-.2-3.2-.6v4.9c0 3.1-2.5 5.6-5.6 5.6-2.9 0-5.3-2.4-5.3-5.3 0-3 2.4-5.4 5.4-5.4.4 0 .8 0 1.1.1v2.7c-.3 0-.7-.1-1-.1-1.4 0-2.5 1.1-2.5 2.6 0 1.4 1.1 2.6 2.5 2.6 1.5 0 2.7-1.2 2.7-2.7V4.3z"></path>
                            <path fill="currentColor" d="M15.3 4.3h1.5c.6 1.2 1.6 2.2 3 2.6v2.2c-1.5-.4-2.7-1-3.6-2-.5-.5-.8-1.1-.9-1.8v-1z"></path>
                        </svg>
                    </div>
                    <span class="text-sm text-slate-700 group-hover:text-slate-900 font-semibold transition truncate">TikTok</span>
                </div>
                <span class="text-xs font-bold text-slate-400 tabular-nums w-6 text-right flex-shrink-0">{{ $counts['sources']['TikTok'] ?? 0 }}</span>
            </label>

            <!-- Facebook -->
            <label class="flex items-center justify-between cursor-pointer group py-0.5 gap-3">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <input wire:key="src-fb-{{ $filterContext }}" wire:model.live="selectedSources" value="Facebook" type="checkbox" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387] w-4 h-4">
                    <div class="w-8 h-8 rounded-lg bg-blue-600 shadow-sm shadow-blue-600/20 flex items-center justify-center">
                        <svg class="w-4 h-4 fill-current text-white" viewBox="0 0 24 24">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"></path>
                        </svg>
                    </div>
                    <span class="text-sm text-slate-700 group-hover:text-slate-900 font-semibold transition truncate">Facebook</span>
                </div>
                <span class="text-xs font-bold text-slate-400 tabular-nums w-6 text-right flex-shrink-0">{{ $counts['sources']['Facebook'] ?? 0 }}</span>
            </label>

            <!-- News -->
            <label class="flex items-center justify-between cursor-pointer group py-0.5 gap-3">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <input wire:key="src-news-{{ $filterContext }}" wire:model.live="selectedSources" value="News" type="checkbox" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387] w-4 h-4">
                    <div class="w-8 h-8 rounded-lg bg-emerald-500 shadow-sm shadow-emerald-500/20 flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path>
                        </svg>
                    </div>
                    <span class="text-sm text-slate-700 group-hover:text-slate-900 font-semibold transition truncate">Portal News</span>
                </div>
                <span class="text-xs font-bold text-slate-400 tabular-nums w-6 text-right flex-shrink-0">{{ $counts['sources']['News'] ?? 0 }}</span>
            </label>
        </div>
    </div>

    <!-- Sentimen Checklist -->
    <div class="space-y-3">
        <div class="flex justify-between items-center">
            <label class="text-sm font-bold text-slate-700">Sentimen</label>
            <button wire:click="$set('selectedSentiment', ['positive', 'neutral', 'negative'])" class="text-xs text-[#1fa387] hover:underline font-bold">Pilih Semua</button>
        </div>
        <div class="space-y-2.5">
            <!-- Positive -->
            <label class="flex items-center justify-between cursor-pointer group py-0.5 gap-3">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <input wire:key="sent-pos-{{ $filterContext }}" wire:model.live="selectedSentiment" value="positive" type="checkbox" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387] w-4 h-4">
                    <span class="w-3 h-3 rounded-full inline-block bg-emerald-500 shadow-sm shadow-emerald-500/30"></span>
                    <span class="text-sm text-slate-700 group-hover:text-slate-900 font-semibold transition truncate">Positif</span>
                </div>
                <span class="text-xs font-bold text-slate-400 tabular-nums w-6 text-right flex-shrink-0">{{ $counts['sentiments']['positive'] ?? 0 }}</span>
            </label>

            <!-- Neutral -->
            <label class="flex items-center justify-between cursor-pointer group py-0.5 gap-3">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <input wire:key="sent-neu-{{ $filterContext }}" wire:model.live="selectedSentiment" value="neutral" type="checkbox" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387] w-4 h-4">
                    <span class="w-3 h-3 rounded-full inline-block bg-slate-400 shadow-sm shadow-slate-400/30"></span>
                    <span class="text-sm text-slate-700 group-hover:text-slate-900 font-semibold transition truncate">Netral</span>
                </div>
                <span class="text-xs font-bold text-slate-400 tabular-nums w-6 text-right flex-shrink-0">{{ $counts['sentiments']['neutral'] ?? 0 }}</span>
            </label>

            <!-- Negative -->
            <label class="flex items-center justify-between cursor-pointer group py-0.5 gap-3">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <input wire:key="sent-neg-{{ $filterContext }}" wire:model.live="selectedSentiment" value="negative" type="checkbox" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387] w-4 h-4">
                    <span class="w-3 h-3 rounded-full inline-block bg-red-500 shadow-sm shadow-red-500/30"></span>
                    <span class="text-sm text-slate-700 group-hover:text-slate-900 font-semibold transition truncate">Negatif</span>
                </div>
                <span class="text-xs font-bold text-slate-400 tabular-nums w-6 text-right flex-shrink-0">{{ $counts['sentiments']['negative'] ?? 0 }}</span>
            </label>
        </div>
    </div>

    <div class="space-y-3 pt-4 border-t border-slate-100">
        <div class="flex justify-between items-center">
            <label class="text-sm font-bold text-slate-700">Risiko AI <span class="text-[10px] font-normal text-slate-400 ml-1">(Risk global sementara)</span></label>
        </div>
        <div class="space-y-2.5">
            <label class="flex items-center justify-between group py-0.5 gap-3">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <span class="w-3 h-3 rounded-full inline-block bg-slate-300 shadow-sm shadow-slate-300/30"></span>
                    <span class="text-sm text-slate-700 font-semibold truncate">Rendah</span>
                </div>
                <span class="text-xs font-bold text-slate-400 tabular-nums w-6 text-right flex-shrink-0">{{ $counts['risks']['low'] ?? 0 }}</span>
            </label>
            <label class="flex items-center justify-between group py-0.5 gap-3">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <span class="w-3 h-3 rounded-full inline-block bg-amber-400 shadow-sm shadow-amber-400/30"></span>
                    <span class="text-sm text-slate-700 font-semibold truncate">Sedang</span>
                </div>
                <span class="text-xs font-bold text-slate-400 tabular-nums w-6 text-right flex-shrink-0">{{ $counts['risks']['medium'] ?? 0 }}</span>
            </label>
            <label class="flex items-center justify-between group py-0.5 gap-3">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <span class="w-3 h-3 rounded-full inline-block bg-rose-500 shadow-sm shadow-rose-500/30"></span>
                    <span class="text-sm text-slate-700 font-semibold truncate">Tinggi</span>
                </div>
                <span class="text-xs font-bold text-slate-400 tabular-nums w-6 text-right flex-shrink-0">{{ $counts['risks']['high'] ?? 0 }}</span>
            </label>
            <label class="flex items-center justify-between group py-0.5 gap-3">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <span class="w-3 h-3 rounded-full inline-block bg-purple-600 shadow-sm shadow-purple-600/30"></span>
                    <span class="text-sm text-slate-700 font-semibold truncate">Kritis</span>
                </div>
                <span class="text-xs font-bold text-slate-400 tabular-nums w-6 text-right flex-shrink-0">{{ $counts['risks']['critical'] ?? 0 }}</span>
            </label>
        </div>
    </div>
    </div>
@endif
