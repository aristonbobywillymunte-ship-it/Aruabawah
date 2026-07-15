<div class="mx-auto w-full max-w-7xl space-y-6 font-sans">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-200 pb-5">
        <div class="text-left">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Panel Administrator</p>
            <h1 class="text-2xl font-black text-slate-900 mt-1">Konfigurasi Scraper Apify</h1>
            <p class="text-xs text-slate-500 mt-1">Kelola API Token, konfigurasi Actor medsos, alokasi RAM, limit, dan prioritas fallback.</p>
        </div>
        <div class="flex items-center gap-3">
            <button 
                wire:click="testConnection" 
                class="inline-flex h-10 items-center gap-2 rounded-2xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-4 text-xs font-bold transition shadow-sm cursor-pointer"
            >
                <span class="material-symbols-outlined text-[18px]">network_check</span>
                <span>Uji Koneksi API</span>
            </button>
        </div>
    </div>

    <!-- Top Config Grid -->
    <div class="grid gap-6 md:grid-cols-3">
        <!-- Connection Status Card -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between text-left">
            <div>
                <div class="flex items-center justify-between">
                    <h2 class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Status Koneksi</h2>
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $connectionStatus === 'connected' ? 'bg-emerald-50 text-emerald-700' : ($connectionStatus === 'error' ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-600') }}">
                        {{ $connectionStatus === 'connected' ? 'Terhubung' : ($connectionStatus === 'error' ? 'Error' : 'Belum Dicek') }}
                    </span>
                </div>
                <p class="mt-2 text-xs text-slate-500 leading-relaxed">Status integrasi server Apify bisa diblok sementara kalau limit tercapai. Sistem akan coba pulih otomatis.</p>
            </div>
            
            <div class="mt-4 rounded-2xl bg-slate-50 p-4 border border-slate-100">
                <div class="flex items-center gap-2">
                    <span class="flex h-2 w-2 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $connectionStatus === 'connected' ? 'bg-emerald-400' : ($connectionStatus === 'error' ? 'bg-rose-400' : 'bg-slate-300') }} opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 {{ $connectionStatus === 'connected' ? 'bg-emerald-500' : ($connectionStatus === 'error' ? 'bg-rose-500' : 'bg-slate-400') }}"></span>
                    </span>
                    <span class="text-[11px] font-bold text-slate-600">{{ $lastTestAt ? 'Uji terakhir: ' . $lastTestAt : 'Belum pernah diuji' }}</span>
                </div>
            </div>
        </div>

        <!-- Token Card -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:col-span-2 text-left flex flex-col justify-between font-sans">
            <div>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Apify API Token</h2>
                <p class="mt-1 text-xs text-slate-500">Masukkan API Token akun Apify Anda untuk menghubungkan data dengan scraper. Kalau limit penuh, sistem menunggu pulih otomatis.</p>
            </div>
            
            <div class="mt-4 flex flex-col sm:flex-row items-end gap-3">
                <div class="flex-1 w-full text-left">
                    <label class="mb-1.5 block text-xs font-bold text-slate-700">Token Akses API</label>
                    <input 
                        wire:model="apiToken" 
                        type="password" 
                        placeholder="apify_api_xxxxxxxxxxxxxxxxxxxxxx" 
                        class="h-10 w-full rounded-xl border border-slate-200 px-4 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387]/20 transition"
                    />
                </div>
                <button 
                    wire:click="saveToken" 
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-5 text-xs font-bold transition shadow-sm w-full sm:w-auto cursor-pointer"
                >
                    <span class="material-symbols-outlined text-[18px]">lock</span>
                    <span>Simpan Token</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Section Grid -->
    <div class="grid gap-6 font-sans">
        <!-- Primary Actors -->
        <div class="rounded-3xl border border-slate-200 bg-white shadow-sm text-left flex flex-col overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h2 class="text-sm font-bold text-slate-800">Aktor Bawaan Sistem</h2>
                    <p class="text-[10px] text-slate-400 mt-0.5">Hanya menampilkan Facebook, Instagram, dan TikTok yang dipakai sistem.</p>
                </div>
                <button 
                    wire:click="syncManagedActors" 
                    wire:loading.attr="disabled"
                    wire:target="syncManagedActors"
                    class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-4 text-xs font-bold transition shadow-sm cursor-pointer whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span wire:loading.remove wire:target="syncManagedActors" class="material-symbols-outlined text-[17px]">sync</span>
                    <svg wire:loading wire:target="syncManagedActors" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="syncManagedActors">Sinkronkan Actor Bawaan</span>
                    <span wire:loading wire:target="syncManagedActors">Menyinkronkan...</span>
                </button>
            </div>

            <!-- Table Container -->
            <div class="overflow-x-auto">
                <table class="w-full table-fixed border-collapse text-xs text-slate-700">
                    <thead class="bg-slate-50/75 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                        <tr>
                            <th class="w-12 px-3 py-3.5 text-left font-bold">No</th>
                            <th class="w-20 px-3 py-3.5 text-left font-bold">Platform</th>
                            <th class="w-[22rem] px-3 py-3.5 text-left font-bold">Aktor / Slug</th>
                            <th class="w-32 px-3 py-3.5 text-left font-bold">Memory / Interval</th>
                            <th class="w-28 px-3 py-3.5 text-left font-bold">Range / Prioritas</th>
                            <th class="w-24 px-3 py-3.5 text-left font-bold">Status</th>
                            <th class="w-36 px-3 py-3.5 text-right font-bold">Opsi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($primaryActors as $actor)
                            @php
                                $primarySlugs = collect($primaryActorDefs)->pluck('actor_slug')->all();
                                $isPrimary = in_array($actor->actor_slug, $primarySlugs, true);
                                $badgeColor = match($actor->platform) {
                                    'Facebook' => 'bg-blue-50 text-blue-700 border-blue-100',
                                    'Instagram' => 'bg-pink-50 text-pink-700 border-pink-100',
                                    'TikTok' => 'bg-amber-50 text-amber-700 border-amber-100',
                                    default => 'bg-slate-50 text-slate-600 border-slate-100'
                                };
                            @endphp
                            <tr class="h-18 hover:bg-slate-50/50 transition">
                                <td class="px-3 py-3 align-top font-semibold text-slate-500">{{ $loop->iteration }}</td>
                                <td class="px-3 py-3 align-top">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold border {{ $badgeColor }}">
                                        {{ $actor->platform }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 align-top overflow-hidden">
                                    <div class="font-bold text-slate-900 truncate">{{ $actor->actor_name }}</div>
                                    <div class="text-[10px] text-slate-400 mt-0.5 truncate">{{ $actor->actor_slug }}</div>
                                    <div class="mt-1 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 whitespace-nowrap">Bawaan Sistem</div>
                                </td>
                                <td class="px-3 py-3 align-top font-semibold text-slate-600">
                                    <div>{{ $actor->memory_limit }} MB RAM</div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">{{ $actor->interval_minutes }} menit</div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                        Max ${{ number_format((float) ($actor->maximum_cost_per_run_usd ?? 0), 2) }}/run
                                    </div>
                                </td>
                                <td class="px-3 py-3 align-top font-bold text-slate-700">
                                    <div class="uppercase truncate">{{ $actor->range_mode }}</div>
                                    <div class="text-[10px] text-slate-400 mt-0.5 whitespace-nowrap">Priority: #{{ $actor->priority }}</div>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <span class="inline-flex items-center gap-1.5 font-bold whitespace-nowrap {{ $actor->status === 'active' ? 'text-emerald-600' : 'text-slate-400' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $actor->status === 'active' ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                        {{ $actor->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <div class="flex items-center justify-end gap-1.5 whitespace-nowrap">
                                        <button 
                                            wire:click="editActor({{ $actor->id }})" 
                                            class="p-1.5 text-slate-500 hover:text-[#1fa387] bg-slate-50 hover:bg-[#1fa387]/5 border border-slate-200 hover:border-[#1fa387] rounded-lg transition cursor-pointer"
                                            title="Ubah Aktor"
                                        >
                                            <span class="material-symbols-outlined text-[15px] block">edit</span>
                                        </button>
                                        <button 
                                            wire:click="toggleActorStatus({{ $actor->id }})" 
                                            class="p-1.5 text-slate-500 hover:text-slate-800 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-lg transition cursor-pointer"
                                            title="{{ $actor->status === 'active' ? 'Nonaktifkan actor' : 'Aktifkan actor' }}"
                                        >
                                            <span class="material-symbols-outlined text-[15px] block">
                                                {{ $actor->status === 'active' ? 'toggle_on' : 'toggle_off' }}
                                            </span>
                                        </button>
                                        @unless($isPrimary)
                                            <button 
                                                wire:click="requestDeleteActor({{ $actor->id }})" 
                                                class="p-1.5 text-slate-400 hover:text-rose-600 bg-slate-50 hover:bg-rose-50 border border-slate-200 hover:border-rose-500 rounded-lg transition cursor-pointer"
                                                title="Hapus"
                                            >
                                                <span class="material-symbols-outlined text-[15px] block">delete</span>
                                            </button>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-slate-400 italic">Belum ada aktor scraper terdaftar.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    <!-- Form Add/Edit Actor Modal -->
    @if($showActorModal)
        <div x-data x-init="document.body.style.overflow = 'hidden'; document.documentElement.style.overflow = 'hidden'; return () => { document.body.style.overflow = ''; document.documentElement.style.overflow = ''; }" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm px-4 py-6 font-sans">
            <div class="w-full max-w-2xl overflow-hidden rounded-[24px] bg-white shadow-2xl text-left overscroll-contain flex flex-col max-h-[90vh]">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-[#1fa387]">Manajemen Scraper Medsos</p>
                        <h2 class="text-base font-black text-slate-900 mt-0.5">{{ $editingActor ? 'Ubah Aktor Apify' : 'Tambah Aktor Apify Baru' }}</h2>
                    </div>
                    <button type="button" wire:click="closeActorModal" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition cursor-pointer">
                        <span class="material-symbols-outlined text-[20px] block">close</span>
                    </button>
                </div>
                <form wire:submit.prevent="saveActor" class="flex flex-col min-h-0 flex-1">
                    <div class="p-6 space-y-5 overflow-y-auto flex-1">
                        <div class="space-y-6">
                        @php
                            $actorGuidance = match ($platform) {
                                'Facebook' => [
                                    'judul' => 'Panduan Singkat Facebook',
                                    'isi' => [
                                        'Isi daftar keyword di <span class="font-semibold text-slate-800">searchQueries</span>.',
                                        'Isi <span class="font-semibold text-slate-800">maxPosts</span> sebagai batas total yang boleh diproses.',
                                        'Jika ada, sistem tetap bisa memakai tanggal dan proxy dari pengaturan.',
                                    ],
                                ],
                                'TikTok' => [
                                    'judul' => 'Panduan Singkat TikTok',
                                    'isi' => [
                                        'Isi daftar keyword di <span class="font-semibold text-slate-800">keywords</span>.',
                                        'Isi <span class="font-semibold text-slate-800">maxItems</span> sebagai batas total hasil.',
                                        'Sistem akan membagi batas itu untuk tiap keyword secara otomatis.',
                                    ],
                                ],
                                'Instagram' => [
                                    'judul' => 'Panduan Singkat Instagram',
                                    'isi' => [
                                        'Isi keyword di <span class="font-semibold text-slate-800">search</span> dengan teks dipisah koma.',
                                        'Isi <span class="font-semibold text-slate-800">searchLimit</span> sebagai batas hasil.',
                                        'Opsi lain seperti pencarian langsung dan jenis hasil bisa dipakai jika tersedia.',
                                    ],
                                ],
                                default => null,
                            };
                        @endphp
                        @if($actorGuidance)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-[11px] text-slate-600">
                                <p class="font-black uppercase tracking-widest text-slate-500">{{ $actorGuidance['judul'] }}</p>
                                <ul class="mt-2 space-y-1.5">
                                    @foreach($actorGuidance['isi'] as $line)
                                        <li class="flex gap-2">
                                            <span class="mt-1.5 h-1.5 w-1.5 rounded-full bg-[#1fa387] shrink-0"></span>
                                            <span>{!! $line !!}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <!-- Group 1: Identitas & Target -->
                        <div>
                            <h3 class="text-[11px] font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                <span class="w-5 h-5 rounded bg-slate-100 flex items-center justify-center text-slate-500"><span class="material-symbols-outlined text-[13px]">badge</span></span>
                                Identitas & Target
                            </h3>
                            <div class="bg-slate-50/50 border border-slate-100 p-4 rounded-2xl space-y-4">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Platform Medsos</label>
                                        <select wire:model="platform" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm">
                                            <option value="Facebook">Facebook</option>
                                            <option value="Instagram">Instagram</option>
                                            <option value="TikTok">TikTok</option>
                                        </select>
                                        @error('platform') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Fungsi Scraper</label>
                                        <select wire:model="functionType" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm">
                                            <option value="Search Post">Search Post (Cari Postingan)</option>
                                            <option value="Detail Post">Detail Post (Detil Postingan)</option>
                                            <option value="Comment Scraper">Comment Scraper (Komentar)</option>
                                        </select>
                                        @error('functionType') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Nama Aktor</label>
                                        <input wire:model="actorName" placeholder="Contoh: Facebook Posts Scraper" type="text" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm">
                                        @error('actorName') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Actor Slug (Path Apify)</label>
                                        <input wire:model="actorSlug" placeholder="Contoh: alien_force/facebook-search-scraper" type="text" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm font-mono">
                                        @error('actorSlug') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Group 2: Performa & Jadwal -->
                        <div>
                            <h3 class="text-[11px] font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                <span class="w-5 h-5 rounded bg-blue-50 flex items-center justify-center text-blue-500"><span class="material-symbols-outlined text-[13px]">speed</span></span>
                                Konfigurasi Performa & Jadwal
                            </h3>
                            <div class="bg-blue-50/30 border border-blue-100/50 p-4 rounded-2xl space-y-4">
                                <div class="grid gap-4 sm:grid-cols-3">
                                    <div>
                                        <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Alokasi RAM (MB)</label>
                                        <select wire:model="memory_limit" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm">
                                            <option value="128">128 MB</option>
                                            <option value="256">256 MB</option>
                                            <option value="512">512 MB</option>
                                            <option value="1024">1024 MB (Default)</option>
                                            <option value="2048">2048 MB</option>
                                            <option value="4096">4096 MB</option>
                                        </select>
                                        @error('memory_limit') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Interval Scraping (Menit)</label>
                                        <input wire:model="interval_minutes" type="number" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm">
                                        @error('interval_minutes') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Prioritas Fallback</label>
                                        <input wire:model="priority" type="number" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm">
                                        @error('priority') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                                <div class="grid gap-4 {{ $platform === 'Facebook' ? 'sm:grid-cols-2' : 'sm:grid-cols-3' }}">
                                    @if($platform !== 'Facebook')
                                    <div>
                                        <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Target Rentang Waktu</label>
                                        <select wire:model="range_mode" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm">
                                            <option value="24h">24 Jam (24h)</option>
                                            <option value="7d">7 Hari (7d)</option>
                                            <option value="30d">30 Hari (30d)</option>
                                            <option value="90d">90 Hari (90d)</option>
                                        </select>
                                        @error('range_mode') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                    @endif
                                    <div>
                                        <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Batas Hasil (Default Limit)</label>
                                        <input wire:model="defaultLimit" type="number" min="1" max="50" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm">
                                        @error('defaultLimit') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Batas Biaya per Run (USD)</label>
                                        <input wire:model="maximum_cost_per_run_usd" type="number" step="0.01" min="0" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm" placeholder="0.25">
                                        <p class="mt-1 text-[10px] font-semibold text-slate-400">0 berarti tanpa batas biaya dari aplikasi.</p>
                                        @error('maximum_cost_per_run_usd') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if($platform === 'TikTok')
                        <div>
                            <h3 class="text-[11px] font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                <span class="w-5 h-5 rounded bg-slate-900 flex items-center justify-center text-white"><span class="material-symbols-outlined text-[13px]">tiktok</span></span>
                                Konfigurasi Khusus TikTok
                            </h3>
                            <div class="bg-slate-50/70 border border-slate-200 p-4 rounded-2xl space-y-4">
                                <div class="grid gap-4 sm:grid-cols-3">
                                    <div>
                                        <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Lokasi</label>
                                        <input wire:model="tiktok_location" type="text" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm font-mono" placeholder="ID">
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Sort Type</label>
                                        <select wire:model="tiktok_sort_type" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm">
                                            <option value="RELEVANCE">RELEVANCE</option>
                                            <option value="LATEST">LATEST</option>
                                            <option value="POPULAR">POPULAR</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Concurrent Keywords</label>
                                        <input wire:model="tiktok_max_concurrent_keywords" type="number" min="1" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm">
                                    </div>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-3">
                                    <label class="flex items-center gap-2.5 cursor-pointer group py-1">
                                        <input wire:model="tiktok_include_search_keywords" type="checkbox" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387] w-4 h-4">
                                        <span class="text-[11px] font-bold text-slate-700">Include Search Keywords</span>
                                    </label>
                                    <label class="flex items-center gap-2.5 cursor-pointer group py-1">
                                        <input wire:model="tiktok_mirror_videos" type="checkbox" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387] w-4 h-4">
                                        <span class="text-[11px] font-bold text-slate-700">Mirror Videos</span>
                                    </label>
                                    <label class="flex items-center gap-2.5 cursor-pointer group py-1">
                                        <input wire:model="tiktok_use_proxy" type="checkbox" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387] w-4 h-4">
                                        <span class="text-[11px] font-bold text-slate-700">Use Proxy</span>
                                    </label>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Proxy Group</label>
                                        <input wire:model="tiktok_proxy_group" type="text" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm font-mono" placeholder="RESIDENTIAL">
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Strict Keyword Match</label>
                                        <select wire:model="tiktok_strict_keyword_match" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm">
                                            <option value="0">False</option>
                                            <option value="1">True</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        @if($platform === 'Facebook')
                            <details class="rounded-2xl border border-slate-200 bg-white">
                                <summary class="cursor-pointer list-none px-4 py-3 text-[11px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-2">
                                    <span class="w-5 h-5 rounded bg-blue-50 flex items-center justify-center text-blue-500"><span class="material-symbols-outlined text-[13px]">schema</span></span>
                                    Payload Aktor Facebook
                                </summary>
                                <div class="border-t border-slate-100 bg-blue-50/30 p-4 space-y-4 rounded-b-2xl">
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Keyword Query</label>
                                            <div class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3.5 flex items-center shadow-sm">
                                                <span class="text-xs font-semibold text-slate-500 font-mono">searchQueries</span>
                                            </div>
                                            <p class="mt-1 text-[10px] text-slate-400">Ini adalah daftar keyword yang dikirim ke actor sebagai array pencarian.</p>
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Keyword Cadangan (Opsional)</label>
                                            <input wire:model="defaultKeyword" placeholder="wagub kaltim, seno aji, gubernur kaltim" type="text" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm">
                                            <p class="mt-1 text-[10px] text-slate-400">Dipakai sebagai cadangan bila kata kunci proyek kosong. Format tetap dipisah koma di database.</p>
                                            @error('defaultKeyword') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                        </div>
                                    </div>
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Rentang Waktu Post</label>
                                            <select wire:model="facebook_post_time_range" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm">
                                                <option value="24h">24h</option>
                                                <option value="7d">7d</option>
                                                <option value="30d">30d</option>
                                                <option value="90d">90d</option>
                                            </select>
                                        </div>
                                        <div class="pt-6">
                                            <label class="flex items-center gap-2.5 cursor-pointer group">
                                                <input wire:model="facebook_use_apify_proxy" type="checkbox" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387] w-4 h-4">
                                                <span class="text-[11px] font-bold text-slate-700">Use Apify Proxy</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="rounded-xl border border-blue-100 bg-blue-50/60 p-3 text-[11px] text-slate-600 space-y-1">
                                        <p class="font-bold text-blue-700">Aturan isi Facebook:</p>
                                        <p><span class="font-mono">searchQueries</span> adalah keyword pencarian.</p>
                                        <p><span class="font-mono">postTimeRange</span> adalah rentang waktu posting.</p>
                                        <p><span class="font-mono">maxPosts</span> mengikuti <span class="font-mono">Batas Hasil (Default Limit)</span>.</p>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-[11px] font-bold text-slate-700 flex justify-between items-center">
                                            Payload Input JSON Aktif Facebook
                                            <span class="font-normal text-slate-400 text-[10px]">Mendukung variabel: {keyword}, {limit}, {time_filter}</span>
                                        </label>
                                        <textarea wire:model="output_mapping" placeholder='{"maxPosts":"{limit}","postTimeRange":"24h","proxyConfiguration":{"useApifyProxy":true},"searchQueries":["{keyword}"]}' rows="4" class="w-full rounded-xl border border-slate-200 p-3.5 text-[11px] font-medium text-slate-700 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-[#f8fafc] shadow-inner font-mono leading-relaxed"></textarea>
                                        @error('output_mapping') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            </details>
                        @elseif($platform === 'TikTok')
                            <details class="rounded-2xl border border-slate-200 bg-white">
                                <summary class="cursor-pointer list-none px-4 py-3 text-[11px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-2">
                                    <span class="w-5 h-5 rounded bg-emerald-50 flex items-center justify-center text-emerald-500"><span class="material-symbols-outlined text-[13px]">schema</span></span>
                                    Payload Aktor TikTok
                                </summary>
                                <div class="border-t border-slate-100 bg-emerald-50/30 p-4 space-y-4 rounded-b-2xl">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    @php
                                        $tiktokDefaultKeywordPlaceholder = "gubernur kaltim, seno aji, wagub kaltim";
                                    @endphp
                                    <div>
                                        <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Kunci Query Pencarian</label>
                                        <input wire:model="keyword_field_mapping" readonly type="text" value="search" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-500 outline-none bg-white shadow-sm font-mono">
                                        <p class="mt-1 text-[10px] text-slate-400">TikTok memakai <span class="font-mono">keywords</span> berbentuk array. Keyword diambil dari project dan dikirim sekaligus ke actor.</p>
                                        @error('keyword_field_mapping') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Default Keyword (Opsional)</label>
                                        <input wire:model="defaultKeyword" placeholder="{{ $tiktokDefaultKeywordPlaceholder }}" type="text" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm">
                                        <p class="mt-1 text-[10px] text-slate-400">Dipakai sebagai cadangan kalau keyword proyek kosong.</p>
                                        @error('defaultKeyword') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-[11px] font-bold text-slate-700 flex justify-between items-center">
                                        Payload Input JSON Aktif TikTok
                                            <span class="font-normal text-slate-400 text-[10px]">
                                                Contoh payload sesuai actor.
                                            </span>
                                        </label>
                                        @php
                                            $tiktokPayloadPlaceholder = '{"dateRange":"7days","includeSearchKeywords":false,"keywords":["Wakil Gubernur Kalimantan Timur","wagub kaltim","Seno Aji"],"location":"ID","maxItems":5,"mirrorVideos":true,"proxyConfiguration":{"useApifyProxy":true,"apifyProxyGroups":["RESIDENTIAL"],"apifyProxyCountry":"ID"},"sortType":"RELEVANCE","strictKeywordMatch":false,"useProxy":true,"minPlayCount":0,"mirrorVideoBytes":262144,"minDurationSec":0,"maxConcurrentKeywords":1}';
                                        @endphp
                                        <textarea wire:model="output_mapping" placeholder="{{ $tiktokPayloadPlaceholder }}" rows="4" class="w-full rounded-xl border border-slate-200 p-3.5 text-[11px] font-medium text-slate-700 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-[#f8fafc] shadow-inner font-mono leading-relaxed"></textarea>
                                        @error('output_mapping') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-3 text-[11px] text-slate-600 space-y-1">
                                        <p class="font-bold text-emerald-700">Aturan isi TikTok:</p>
                                        <p><span class="font-mono">keywords</span> wajib array. Ambil dari keyword proyek.</p>
                                        <p><span class="font-mono">maxItems</span> diatur langsung dari modal actor TikTok.</p>
                                        <p><span class="font-mono">dateRange</span>, <span class="font-mono">location</span>, <span class="font-mono">sortType</span>, <span class="font-mono">strictKeywordMatch</span>, dan proxy mengikuti actor.</p>
                                        <p>Kalau ingin aman, pakai batas kecil dulu saat uji coba manual.</p>
                                    </div>
                                </div>
                            </details>
                        @else
                            <details class="rounded-2xl border border-slate-200 bg-white">
                                <summary class="cursor-pointer list-none px-4 py-3 text-[11px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-2">
                                    <span class="w-5 h-5 rounded bg-emerald-50 flex items-center justify-center text-emerald-500"><span class="material-symbols-outlined text-[13px]">schema</span></span>
                                    Pengaturan Lanjutan
                                </summary>
                                <div class="border-t border-slate-100 bg-emerald-50/30 p-4 space-y-4 rounded-b-2xl">
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Kunci Query Pencarian</label>
                                            <input wire:model="keyword_field_mapping" placeholder="Contoh: keyword / searchQueries" type="text" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm font-mono text-[#1fa387]">
                                            @error('keyword_field_mapping') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Default Keyword (Opsional)</label>
                                            <input wire:model="defaultKeyword" placeholder="Contoh: indonesia" type="text" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm">
                                            @error('defaultKeyword') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                        </div>
                                    </div>
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Search Limit</label>
                                            <input wire:model="instagram_search_limit" type="number" min="1" max="50" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm">
                                            @error('instagram_search_limit') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                        </div>
                                        <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-3 text-[11px] text-slate-600 space-y-1">
                                            <p class="font-bold text-emerald-700">Aturan isi Instagram:</p>
                                            <p><span class="font-mono">search</span> berisi keyword dipisah koma.</p>
                                            <p><span class="font-mono">searchLimit</span> diatur langsung dari modal actor Instagram.</p>
                                            <p><span class="font-mono">searchType</span> mengikuti setting actor aktif.</p>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-[11px] font-bold text-slate-700 flex justify-between items-center">
                                            Payload Input JSON Aktif Instagram
                                            <span class="font-normal text-slate-400 text-[10px]">Mendukung variabel: {keyword}, {limit}, {time_filter}</span>
                                        </label>
                                        <textarea wire:model="output_mapping" placeholder='{"enhanceUserSearchWithFacebookPage":false,"liveSearch":true,"search":"{keyword}","searchLimit":"{limit}","searchType":"popular"}' rows="4" class="w-full rounded-xl border border-slate-200 p-3.5 text-[11px] font-medium text-slate-700 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-[#f8fafc] shadow-inner font-mono leading-relaxed"></textarea>
                                        @error('output_mapping') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            </details>
                        @endif

                        <!-- Group 4: Status & Filter -->
                        <div class="bg-slate-50 border border-slate-100 p-4 rounded-2xl flex flex-wrap gap-6 items-center">
                            <label class="flex items-center gap-2.5 cursor-pointer group">
                                <div class="relative w-9 h-5 rounded-full bg-slate-200 transition-colors duration-200 ease-in-out" :class="{ 'bg-[#1fa387]': $wire.post_filter_enabled }">
                                    <input type="checkbox" wire:model.live="post_filter_enabled" class="sr-only">
                                    <div class="absolute left-1 top-1 w-3 h-3 bg-white rounded-full transition-transform duration-200 ease-in-out" :class="{ 'translate-x-4': $wire.post_filter_enabled }"></div>
                                </div>
                                <span class="text-[11px] font-bold text-slate-700 group-hover:text-slate-900 transition-colors">Post-Filter Tanggal di Lokal</span>
                            </label>
                            
                            <label class="flex items-center gap-2.5 cursor-pointer group">
                                <div class="relative w-9 h-5 rounded-full bg-slate-200 transition-colors duration-200 ease-in-out" :class="{ 'bg-blue-500': $wire.actorStatus === 'active' }">
                                    <input type="checkbox" wire:model.live="actorStatus" true-value="active" false-value="inactive" class="sr-only">
                                    <div class="absolute left-1 top-1 w-3 h-3 bg-white rounded-full transition-transform duration-200 ease-in-out" :class="{ 'translate-x-4': $wire.actorStatus === 'active' }"></div>
                                </div>
                                <span class="text-[11px] font-bold text-slate-700 group-hover:text-slate-900 transition-colors">Aktor Dalam Status Aktif</span>
                            </label>
                        </div>
                        </div>

                        </div>


                    <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-white shrink-0">
                        <button type="button" wire:click="closeActorModal" class="h-10 rounded-xl border border-slate-200 px-5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer">Batal</button>
                        <button type="submit" class="h-10 rounded-xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-6 text-xs font-bold transition cursor-pointer">Simpan Actor</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Delete Confirmation Modal -->
    @if($confirmingDelete)
        <div x-data x-init="document.body.style.overflow = 'hidden'; document.documentElement.style.overflow = 'hidden'; return () => { document.body.style.overflow = ''; document.documentElement.style.overflow = ''; }" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm px-4 py-6 font-sans">
            <div class="w-full max-w-sm rounded-[24px] bg-white p-6 shadow-2xl text-left space-y-4 overscroll-contain">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-full bg-rose-50 flex items-center justify-center text-rose-600">
                        <span class="material-symbols-outlined text-[20px] block">warning</span>
                    </span>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-rose-500">Konfirmasi Hapus</p>
                        <h2 class="text-sm font-black text-slate-900 mt-0.5">Hapus Aktor Scraper?</h2>
                    </div>
                </div>
                <p class="text-xs text-slate-500 leading-relaxed">Aksi ini bersifat permanen. Seluruh isian data aktor akan terhapus total dari database.</p>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button wire:click="$set('confirmingDelete', false)" class="h-10 rounded-xl border border-slate-200 px-5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer">Batal</button>
                    <button wire:click="deleteActorConfirmed" class="h-10 rounded-xl bg-rose-600 hover:bg-rose-700 text-white px-6 text-xs font-bold transition cursor-pointer">Ya, Hapus</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Test Run Modal -->
    @if($showTestModal)
        <div x-data x-init="document.body.style.overflow = 'hidden'; document.documentElement.style.overflow = 'hidden'; return () => { document.body.style.overflow = ''; document.documentElement.style.overflow = ''; }" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm px-4 py-6 font-sans">
            <div class="w-full max-w-lg overflow-hidden rounded-[24px] bg-white shadow-2xl text-left overscroll-contain">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-[#1fa387]">Uji Coba Scraper</p>
                        <h2 class="text-base font-black text-slate-900 mt-0.5">Uji Perayapan Aktor</h2>
                    </div>
                    <button type="button" wire:click="closeTestModal" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition cursor-pointer">
                        <span class="material-symbols-outlined text-[20px] block">close</span>
                    </button>
                </div>
                
                <form wire:submit.prevent="runTest" class="p-6 space-y-4">
                    <div>
                        <label class="mb-1.5 block text-xs font-bold text-slate-700">Keyword Uji</label>
                        <input wire:model="testKeyword" type="text" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                        @error('testKeyword') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="sm:col-span-1">
                            <label class="mb-1.5 block text-xs font-bold text-slate-700">Limit</label>
                            <input wire:model="testLimit" type="number" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                            @error('testLimit') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-1">
                            <label class="mb-1.5 block text-xs font-bold text-slate-700">Mulai Tanggal</label>
                            <input wire:model="testDateFrom" type="date" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                            @error('testDateFrom') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-1">
                            <label class="mb-1.5 block text-xs font-bold text-slate-700">Akhir Tanggal</label>
                            <input wire:model="testDateTo" type="date" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                            @error('testDateTo') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    @if($testResultStatus)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-2 text-xs">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-700">Hasil Test:</span>
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $testResultStatus === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                                    {{ $testResultStatus === 'success' ? 'Berhasil' : 'Gagal' }}
                                </span>
                            </div>
                            @if($testResultStatus === 'success')
                                <div class="text-slate-600">Ditemukan <strong>{{ $testResultCount }}</strong> postingan dummy medsos. Dataset ID: <strong class="font-mono text-[10px]">{{ $testResultDatasetId }}</strong></div>
                            @else
                                <div class="text-rose-600 font-bold">{{ $testResultError }}</div>
                            @endif
                        </div>
                    @endif

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100 font-sans">
                        <button type="button" wire:click="closeTestModal" class="h-10 rounded-xl border border-slate-200 px-5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer">Batal</button>
                        <button type="submit" class="h-10 rounded-xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-6 text-xs font-bold transition cursor-pointer">Jalankan Simulasi</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
