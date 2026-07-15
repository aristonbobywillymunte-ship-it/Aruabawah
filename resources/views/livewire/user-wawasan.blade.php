<div class="min-h-screen bg-surface-studio text-slate-800 flex flex-col font-sans">
    <!-- Header -->
    <header class="w-full bg-white border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-[1400px] mx-auto px-6 h-16 flex flex-row flex-nowrap items-center justify-between gap-6">
            <!-- Brand & Nav -->
            <div class="flex items-center gap-10 h-full justify-self-start">
                <!-- Brand Logo Arusbawah -->
                <div class="flex items-center gap-2 font-sans cursor-pointer" onclick="window.location.href='/'">
                    <!-- Red A logo -->
                    <svg width="28" height="28" viewBox="0 0 42 42" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <polygon points="21,4 39,38 3,38" fill="none" stroke="#c0392b" stroke-width="4" stroke-linejoin="round"/>
                        <line x1="11" y1="28" x2="31" y2="28" stroke="#c0392b" stroke-width="4" stroke-linecap="round"/>
                    </svg>
                    <div class="flex flex-col text-left">
                        <span class="text-sm font-black tracking-wider leading-none text-slate-800">ARUSBAWAH</span>
                        <span class="text-[7.5px] font-bold text-slate-400 uppercase tracking-widest leading-none mt-0.5">Media Intelligence</span>
                    </div>
                </div>

            </div>

            <!-- User Profile and Actions -->
            <div class="flex items-center gap-4 justify-self-end">
                <!-- Notifikasi Dropdown Component -->
                <livewire:notification-dropdown />
                <div class="relative" x-data="{ open: false }">
                    <button
                        type="button"
                        @click="open = !open"
                        class="flex items-center gap-3 bg-slate-50 border border-slate-200 rounded-full pl-1 pr-3 py-1 cursor-pointer hover:bg-slate-100 transition-colors active:scale-95"
                    >
                        <div class="w-7 h-7 rounded-full bg-slate-200 flex items-center justify-center">
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                            </svg>
                        </div>
                        <span class="text-xs font-medium text-slate-600">{{ auth()->user()?->email ?? 'Guest' }}</span>
                        <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M19 9l-7 7-7-7" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                        </svg>
                    </button>

                    <!-- Dropdown Menu -->
                    <div 
                        x-show="open" 
                        @click.away="open = false"
                        style="display: none;"
                        class="absolute right-0 mt-2 w-56 bg-white rounded-xl border border-slate-100 shadow-lg z-[60] py-2"
                    >
                        <a wire:navigate class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition-colors" href="{{ route('password.change') }}">
                            <span class="material-symbols-outlined text-slate-400 text-lg">person</span>
                            <span>Ganti Password</span>
                        </a>
                        <div class="my-1 border-t border-slate-100"></div>
                        <form method="POST" action="{{ url('/logout') }}">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-red-500 hover:bg-red-50 transition-colors text-left">
                                <span class="material-symbols-outlined text-lg">logout</span>
                                <span>Logout</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-[1400px] mx-auto px-6 py-10 flex-grow w-full">
        <!-- Title Section -->
        <section class="mb-10">
            <h1 class="text-2xl font-hanken font-bold text-slate-900 mb-1">Wawasan Intelijen</h1>
            <p class="text-slate-500 text-sm">Eksplorasi temuan data, ringkasan analitik, dan tren dari seluruh media.</p>
        </section>

        <!-- Dashboard Overview Grid -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <!-- Total Proyek -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col justify-center">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Total Proyek Terhubung</span>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center text-blue-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    </div>
                    <span class="text-3xl font-extrabold text-slate-900">{{ $data['total_projects'] }}</span>
                </div>
            </div>

            <!-- Total Penyebutan -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col justify-center">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Total Analisis AI</span>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <span class="text-3xl font-extrabold text-slate-900">{{ number_format($data['total_mentions'], 0, ',', '.') }}</span>
                </div>
            </div>

            <!-- Sentimen Positif -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col justify-center">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Sentimen Positif Keseluruhan</span>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-teal-50 flex items-center justify-center text-teal-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"></path></svg>
                    </div>
                    <span class="text-3xl font-extrabold text-teal-600">{{ $data['pos_pct'] }}%</span>
                </div>
            </div>

            <!-- Global Reputation -->
            <div class="bg-slate-900 rounded-2xl p-6 shadow-md flex flex-col justify-center text-white relative overflow-hidden">
                <div class="absolute top-0 right-0 p-4 opacity-10">
                    <svg class="w-24 h-24" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path></svg>
                </div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2 relative z-10">Skor Reputasi Global</span>
                <div class="flex items-end gap-2 relative z-10">
                    <span class="text-4xl font-extrabold text-white">{{ $data['reputation_score'] }}</span>
                    <span class="text-sm font-bold text-slate-400 mb-1">/ 100</span>
                </div>
            </div>
        </div>

        @if($data['total_mentions'] > 0)
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Komposisi Sentimen -->
                <div class="lg:col-span-2 bg-white border border-slate-200 rounded-2xl p-8 shadow-sm">
                    <h3 class="text-sm font-bold text-slate-800 mb-6">Distribusi Sentimen Media</h4>
                    
                    <div class="flex h-12 w-full rounded-xl overflow-hidden mb-6 bg-slate-100">
                        @if($data['pos_pct'] > 0)<div class="h-full bg-teal-500 transition-all hover:opacity-90" style="width: {{ $data['pos_pct'] }}%" title="Positif: {{ $data['pos_pct'] }}%"></div>@endif
                        @if($data['neu_pct'] > 0)<div class="h-full bg-slate-300 transition-all hover:opacity-90" style="width: {{ $data['neu_pct'] }}%" title="Netral: {{ $data['neu_pct'] }}%"></div>@endif
                        @if($data['neg_pct'] > 0)<div class="h-full bg-rose-500 transition-all hover:opacity-90" style="width: {{ $data['neg_pct'] }}%" title="Negatif: {{ $data['neg_pct'] }}%"></div>@endif
                    </div>

                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div>
                            <p class="text-xl font-bold text-teal-600">{{ $data['pos_pct'] }}%</p>
                            <p class="text-xs font-medium text-slate-500 uppercase">Positif</p>
                        </div>
                        <div>
                            <p class="text-xl font-bold text-slate-600">{{ $data['neu_pct'] }}%</p>
                            <p class="text-xs font-medium text-slate-500 uppercase">Netral</p>
                        </div>
                        <div>
                            <p class="text-xl font-bold text-rose-600">{{ $data['neg_pct'] }}%</p>
                            <p class="text-xs font-medium text-slate-500 uppercase">Negatif</p>
                        </div>
                    </div>
                </div>

                <!-- Radar Risiko -->
                <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm flex flex-col h-full">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-sm font-bold text-slate-800">Radar Risiko Terkini</h3>
                        <span class="px-2.5 py-0.5 rounded-full bg-rose-100 text-rose-600 text-[10px] font-bold">Peringatan</span>
                    </div>

                    <div class="flex-grow space-y-4">
                        @forelse($data['alerts'] as $alert)
                            <div class="flex items-start gap-3 p-3 rounded-xl hover:bg-slate-50 transition-colors border border-transparent hover:border-slate-100 cursor-pointer">
                                <div class="mt-1 w-2 h-2 rounded-full {{ $alert->risk_level === 'high' || $alert->risk_level === 'critical' ? 'bg-rose-500 shadow-[0_0_8px_rgba(244,63,94,0.6)]' : 'bg-amber-400' }}"></div>
                                <div>
                                    <p class="text-xs font-bold text-slate-800 line-clamp-2 leading-snug mb-1">{{ $alert->article->title ?? $alert->article->content ?? 'Penyebutan negatif terdeteksi' }}</p>
                                    <div class="flex gap-2">
                                        <span class="text-[9px] font-bold uppercase text-slate-500">{{ $alert->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="h-full flex flex-col items-center justify-center text-slate-400 py-10">
                                <svg class="w-12 h-12 mb-3 text-slate-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span class="text-xs text-center font-medium">Tidak ada risiko atau sentimen negatif signifikan yang perlu diperhatikan saat ini.</span>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @else
            <!-- Placeholder Content -->
            <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center shadow-[0_4px_20px_-2px_rgba(0,0,0,0.03)] flex flex-col items-center justify-center min-h-[400px]">
                <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-6">
                    <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-hanken font-bold text-slate-800 mb-2">Belum Ada Data Analitik</h3>
                <p class="text-slate-500 text-sm max-w-md mx-auto leading-relaxed">
                    Kami sedang menunggu data terkumpul. Begitu data proyek Anda terhubung dan teranalisis, layar ini akan menampilkan ringkasan reputasi seluruh aset digital Anda.
                </p>
            </div>
        @endif
    </main>
</div>
