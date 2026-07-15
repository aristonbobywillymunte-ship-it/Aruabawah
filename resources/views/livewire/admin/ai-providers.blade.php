<div class="mx-auto w-full max-w-7xl space-y-6 font-sans">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-200 pb-5">
        <div class="text-left">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Panel Administrator</p>
            <h1 class="text-2xl font-black text-slate-900 mt-1">AI Provider</h1>
            <p class="text-xs text-slate-500 mt-1">Kelola model AI untuk analisis portal dan sosial media.</p>
        </div>

        <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto">
            <div class="relative w-full sm:w-80">
                <input 
                    wire:model.live.debounce.300ms="search" 
                    type="text" 
                    placeholder="Cari provider..." 
                    class="h-10 w-full rounded-2xl border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387]/20"
                />
            </div>
            <button 
                wire:click="create" 
                class="inline-flex h-10 w-full sm:w-auto items-center justify-center gap-1.5 rounded-2xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-5 text-xs font-bold transition shadow-sm cursor-pointer"
            >
                <span class="material-symbols-outlined text-[18px]">add</span>
                <span>Tambah Provider</span>
            </button>
        </div>
    </div>
    <!-- Top Config / Active Provider Card -->
    <div class="grid gap-6 md:grid-cols-3">
        <!-- Active / Default Provider Card -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:col-span-2 text-left flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <h2 class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Provider Aktif Utama</h2>
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold bg-teal-50 text-teal-700 border border-teal-100">
                        Default Active
                    </span>
                </div>
                @if($defaultProvider)
                    <h3 class="text-lg font-black text-slate-900 mt-2">{{ $defaultProvider->name }}</h3>
                    <p class="text-xs text-slate-500 mt-1">Model: <strong class="text-slate-700">{{ $defaultProvider->model_name }}</strong> (Type: {{ $defaultProvider->provider_type }})</p>
                @else
                    <h3 class="text-lg font-black text-slate-400 mt-2">Belum ada provider default</h3>
                    <p class="text-xs text-slate-500 mt-1">Set salah satu provider di tabel sebagai default.</p>
                @endif
            </div>

            @if($defaultProvider)
                <div class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="flex h-2 w-2 relative">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $defaultProvider->last_test_status === 'success' ? 'bg-emerald-400' : ($defaultProvider->last_test_status === 'failed' ? 'bg-rose-400' : 'bg-slate-300') }} opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 {{ $defaultProvider->last_test_status === 'success' ? 'bg-emerald-500' : ($defaultProvider->last_test_status === 'failed' ? 'bg-rose-500' : 'bg-slate-400') }}"></span>
                        </span>
                        <span class="text-[11px] font-bold text-slate-600">
                            {{ $defaultProvider->last_tested_at ? 'Uji terakhir: ' . $defaultProvider->last_tested_at->format('d/m/Y H:i') : 'Belum pernah diuji' }}
                        </span>
                    </div>
                    <button 
                        wire:click="openTest({{ $defaultProvider->id }})" 
                        class="inline-flex h-8 items-center gap-1.5 rounded-xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-3.5 text-[11px] font-bold transition shadow-sm cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[15px]">network_check</span>
                        <span>Test Connection</span>
                    </button>
                </div>
            @endif
        </div>

        <!-- Mode Note Card -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between text-left">
            <div>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Fleksibilitas Analisis</h2>
                <p class="mt-2 text-xs text-slate-500 leading-relaxed">Pengaturan provider yang diatur di sini akan menentukan API LLM yang digunakan untuk menganalisis sentimen portal berita, media sosial, serta menyusun rangkuman wawasan.</p>
            </div>
        </div>
    </div>

    <!-- AI Providers Table Card -->
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden text-left">
        <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-sm font-bold text-slate-800">Daftar AI Provider</h2>
            <p class="text-[10px] text-slate-400 mt-0.5">Kelola seluruh vendor dan endpoint model kecerdasan buatan</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-xs text-slate-700">
                <thead class="bg-slate-50/75 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3.5 text-left font-bold w-12">No</th>
                        <th class="px-4 py-3.5 text-left font-bold">Nama Provider</th>
                        <th class="px-4 py-3.5 text-left font-bold">Jenis</th>
                        <th class="px-4 py-3.5 text-left font-bold">Base URL</th>
                        <th class="px-4 py-3.5 text-left font-bold">Model</th>
                        <th class="px-4 py-3.5 text-left font-bold">Rate Limit/Min</th>
                        <th class="px-4 py-3.5 text-left font-bold">Status</th>
                        <th class="px-4 py-3.5 text-left font-bold">Default</th>
                        <th class="px-4 py-3.5 text-left font-bold">Last Tested</th>
                        <th class="px-4 py-3.5 text-right font-bold w-48">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($providers as $provider)
                        @php
                            $badgeColor = match($provider->provider_type) {
                                'OpenAI' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                                'Gemini' => 'bg-blue-50 text-blue-700 border-blue-100',
                                'Anthropic' => 'bg-orange-50 text-orange-700 border-orange-100',
                                'Groq' => 'bg-red-50 text-red-700 border-red-100',
                                'OpenRouter' => 'bg-purple-50 text-purple-700 border-purple-100',
                                'Ollama' => 'bg-slate-100 text-slate-800 border-slate-200',
                                default => 'bg-slate-50 text-slate-600 border-slate-100'
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-4 py-3 text-slate-500 font-semibold">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3 font-bold text-slate-900">{{ $provider->name }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold border {{ $badgeColor }}">
                                    {{ $provider->provider_type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-semibold text-slate-500 max-w-xs truncate" title="{{ $provider->base_url }}">
                                {{ $provider->base_url ?: 'Default API Route' }}
                            </td>
                            <td class="px-4 py-3 font-bold text-slate-700">
                                {{ $provider->model_name }}
                            </td>
                            <td class="px-4 py-3 font-semibold text-slate-600">{{ $provider->requests_per_minute ?? 60 }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 font-bold {{ $provider->is_active ? 'text-emerald-600' : 'text-slate-400' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $provider->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                    {{ $provider->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if($provider->is_default)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[9px] font-bold bg-teal-50 text-teal-700 border border-teal-200">
                                        DEFAULT
                                    </span>
                                @else
                                    <button 
                                        wire:click="setDefault({{ $provider->id }})" 
                                        class="text-[9px] font-bold text-slate-400 hover:text-[#1fa387] border border-slate-200 hover:border-[#1fa387] bg-slate-50 px-2 py-0.5 rounded-md transition cursor-pointer"
                                    >
                                        SET DEFAULT
                                    </button>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-semibold text-slate-500">
                                @if($provider->last_tested_at)
                                    <div class="flex items-center gap-1">
                                        <span class="inline-flex w-1.5 h-1.5 rounded-full {{ $provider->last_test_status === 'success' ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                                        <span>{{ $provider->last_tested_at->format('d/m/Y H:i') }}</span>
                                    </div>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <!-- Edit Button -->
                                    <button 
                                        wire:click="edit({{ $provider->id }})" 
                                        class="p-1.5 text-slate-500 hover:text-[#1fa387] bg-slate-50 hover:bg-[#1fa387]/5 border border-slate-200 hover:border-[#1fa387] rounded-lg transition cursor-pointer"
                                        title="Ubah Konfigurasi"
                                    >
                                        <span class="material-symbols-outlined text-[15px] block">edit</span>
                                    </button>
                                    
                                    <!-- Test Connection Button -->
                                    <button 
                                        wire:click="openTest({{ $provider->id }})" 
                                        class="p-1.5 text-slate-500 hover:text-amber-600 bg-slate-50 hover:bg-amber-50 border border-slate-200 hover:border-amber-500 rounded-lg transition cursor-pointer"
                                        title="Uji Koneksi Prompt"
                                    >
                                        <span class="material-symbols-outlined text-[15px] block">play_arrow</span>
                                    </button>

                                    <!-- Quick Connection Test -->
                                    <button 
                                        wire:click="testConnectionDirect({{ $provider->id }})" 
                                        class="p-1.5 text-slate-500 hover:text-[#1fa387] bg-slate-50 hover:bg-[#1fa387]/5 border border-slate-200 hover:border-[#1fa387] rounded-lg transition cursor-pointer"
                                        title="Uji Koneksi Cepat"
                                    >
                                        <span class="material-symbols-outlined text-[15px] block">flash_on</span>
                                    </button>
                                    
                                    <!-- Toggle Active/Inactive Status -->
                                    <button 
                                        wire:click="toggleStatus({{ $provider->id }})" 
                                        class="p-1.5 text-slate-500 hover:text-slate-800 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-lg transition cursor-pointer"
                                        title="{{ $provider->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                                    >
                                        <span class="material-symbols-outlined text-[15px] block">
                                            {{ $provider->is_active ? 'toggle_on' : 'toggle_off' }}
                                        </span>
                                    </button>

                                    <!-- Delete Button -->
                                    @if(!$provider->is_default)
                                        <button 
                                            wire:click="requestDelete({{ $provider->id }})" 
                                            class="p-1.5 text-slate-400 hover:text-rose-600 bg-slate-50 hover:bg-rose-50 border border-slate-200 hover:border-rose-500 rounded-lg transition cursor-pointer"
                                            title="Hapus Provider"
                                        >
                                            <span class="material-symbols-outlined text-[15px] block">delete</span>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-slate-400 italic">Belum ada provider AI terdaftar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Form Add/Edit Provider Modal -->
    @if($showFormModal)
        <div x-data x-init="document.body.style.overflow = 'hidden'; document.documentElement.style.overflow = 'hidden'; return () => { document.body.style.overflow = ''; document.documentElement.style.overflow = ''; }" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm px-4 py-6">
            <div class="w-full max-w-lg overflow-hidden rounded-[24px] bg-white shadow-2xl text-left overscroll-contain flex flex-col max-h-[90vh]">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 shrink-0">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-[#1fa387]">Manajemen Model AI</p>
                        <h2 class="text-base font-black text-slate-900 mt-0.5">{{ $isEditing ? 'Ubah Provider AI' : 'Tambah Provider AI Baru' }}</h2>
                    </div>
                    <button type="button" wire:click="closeFormModal" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition cursor-pointer">
                        <span class="material-symbols-outlined text-[20px] block">close</span>
                    </button>
                </div>
                
                <form wire:submit.prevent="save" class="flex flex-col min-h-0 flex-1">
                    <div class="p-6 space-y-5 overflow-y-auto flex-1">
                        <!-- Group 1: Identitas & Kredensial -->
                        <div>
                            <h3 class="text-[11px] font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                <span class="w-5 h-5 rounded bg-slate-100 flex items-center justify-center text-slate-500"><span class="material-symbols-outlined text-[13px]">badge</span></span>
                                Identitas & Kredensial
                            </h3>
                            <div class="bg-slate-50/50 border border-slate-100 p-4 rounded-2xl space-y-4">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Nama Provider</label>
                                        <input wire:model="name" placeholder="Contoh: OpenAI Utama" type="text" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm">
                                        @error('name') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Jenis Provider</label>
                                        <select wire:model="provider_type" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm">
                                            <option value="OpenAI">OpenAI</option>
                                            <option value="Gemini">Gemini</option>
                                            <option value="Anthropic">Anthropic</option>
                                            <option value="Groq">Groq</option>
                                            <option value="OpenRouter">OpenRouter</option>
                                            <option value="Ollama">Ollama / Local</option>
                                            <option value="Custom API">Custom API</option>
                                        </select>
                                        @error('provider_type') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Base URL (Opsional / Wajib untuk Custom API & Ollama)</label>
                                    <input wire:model="base_url" placeholder="Contoh: https://api.openai.com/v1" type="text" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm">
                                    @error('base_url') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1.5 block text-[11px] font-bold text-slate-700">API Key (Masked)</label>
                                        <div class="flex gap-2">
                                            <input wire:model="api_key" placeholder="Masukkan API key Anda" type="password" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm flex-1">
                                            <button type="button" wire:click="detectModels" class="h-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 text-xs font-bold transition flex-shrink-0 cursor-pointer">
                                                <span>Deteksi</span>
                                            </button>
                                        </div>
                                        @error('api_key') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Model Name</label>
                                        <div class="flex flex-col gap-1.5">
                                            <input wire:model="model_name" placeholder="Tulis nama model..." type="text" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm font-mono">
                                            
                                            @if(count($detectedModels) > 0)
                                                <select wire:change="selectDetectedModel($event.target.value)" class="h-9 w-full rounded-xl border border-slate-200 px-3.5 text-[11px] font-semibold text-slate-500 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-slate-50/50">
                                                    <option value="">-- Pilih dari Model Terdeteksi --</option>
                                                    @foreach($detectedModels as $m)
                                                        <option value="{{ $m }}" {{ $model_name === $m ? 'selected' : '' }}>{{ $m }}</option>
                                                    @endforeach
                                                </select>
                                            @endif
                                        </div>
                                        @error('model_name') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Group 2: Performa & Limit -->
                        <div>
                            <h3 class="text-[11px] font-black text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                <span class="w-5 h-5 rounded bg-blue-50 flex items-center justify-center text-blue-500"><span class="material-symbols-outlined text-[13px]">speed</span></span>
                                Performa & Limit
                            </h3>
                            <div class="bg-blue-50/30 border border-blue-100/50 p-4 rounded-2xl space-y-4">
                                <div>
                                    <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Request per Menit</label>
                                    <input wire:model="requests_per_minute" type="number" min="1" step="1" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-white shadow-sm">
                                    <p class="mt-1 text-[10px] text-slate-400">Batas aman jumlah request per menit untuk provider ini.</p>
                                    @error('requests_per_minute') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Group 3: Pengaturan Lanjutan -->
                        <details class="rounded-2xl border border-slate-200 bg-white">
                            <summary class="cursor-pointer list-none px-4 py-3 text-[11px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-2">
                                <span class="w-5 h-5 rounded bg-emerald-50 flex items-center justify-center text-emerald-500"><span class="material-symbols-outlined text-[13px]">schema</span></span>
                                Pengaturan Lanjutan
                            </summary>
                            <div class="border-t border-slate-100 bg-emerald-50/30 p-4 space-y-4 rounded-b-2xl">
                                <div>
                                    <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Custom Headers (Opsional, JSON format)</label>
                                    <textarea wire:model="custom_headers" placeholder='Contoh: {"Authorization": "Bearer key", "X-Custom": "val"}' rows="3" class="w-full rounded-xl border border-slate-200 p-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-[#f8fafc] shadow-inner font-mono"></textarea>
                                    @error('custom_headers') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="mb-1.5 block text-[11px] font-bold text-slate-700">Custom Request Body Template (Opsional, JSON format)</label>
                                    <textarea wire:model="custom_body_template" placeholder='Contoh: {"model": "{model}", "messages": [{"role": "user", "content": "{prompt}"}]}' rows="4" class="w-full rounded-xl border border-slate-200 p-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/10 transition bg-[#f8fafc] shadow-inner font-mono"></textarea>
                                    @error('custom_body_template') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </details>

                        <!-- Group 4: Status & Default -->
                        <div class="bg-slate-50 border border-slate-100 p-4 rounded-2xl flex flex-wrap gap-6 items-center">
                            <label class="flex items-center gap-2.5 cursor-pointer group">
                                <div class="relative w-9 h-5 rounded-full bg-slate-200 transition-colors duration-200 ease-in-out" :class="{ 'bg-blue-500': $wire.is_active }">
                                    <input type="checkbox" wire:model.live="is_active" class="sr-only">
                                    <div class="absolute left-1 top-1 w-3 h-3 bg-white rounded-full transition-transform duration-200 ease-in-out" :class="{ 'translate-x-4': $wire.is_active }"></div>
                                </div>
                                <span class="text-[11px] font-bold text-slate-700 group-hover:text-slate-900 transition-colors">Status Aktif</span>
                            </label>

                            <label class="flex items-center gap-2.5 cursor-pointer group">
                                <div class="relative w-9 h-5 rounded-full bg-slate-200 transition-colors duration-200 ease-in-out" :class="{ 'bg-[#1fa387]': $wire.is_default }">
                                    <input type="checkbox" wire:model.live="is_default" class="sr-only">
                                    <div class="absolute left-1 top-1 w-3 h-3 bg-white rounded-full transition-transform duration-200 ease-in-out" :class="{ 'translate-x-4': $wire.is_default }"></div>
                                </div>
                                <span class="text-[11px] font-bold text-slate-700 group-hover:text-slate-900 transition-colors">Jadikan Default Utama</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100 bg-white shrink-0">
                        <button type="button" wire:click="closeFormModal" class="h-10 rounded-xl border border-slate-200 px-5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer">Batal</button>
                        <button type="submit" class="h-10 rounded-xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-6 text-xs font-bold transition cursor-pointer font-sans">Simpan Provider</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Test Run Modal -->
    @if($showTestModal)
        <div x-data x-init="document.body.style.overflow = 'hidden'; document.documentElement.style.overflow = 'hidden'; return () => { document.body.style.overflow = ''; document.documentElement.style.overflow = ''; }" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm px-4 py-6">
            <div class="w-full max-w-lg overflow-hidden rounded-[24px] bg-white shadow-2xl text-left overscroll-contain">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-[#1fa387]">Pengujian LLM</p>
                        <h2 class="text-base font-black text-slate-900 mt-0.5">Uji Koneksi Provider</h2>
                    </div>
                    <button type="button" wire:click="closeTestModal" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition cursor-pointer">
                        <span class="material-symbols-outlined text-[20px] block">close</span>
                    </button>
                </div>
                
                <form wire:submit.prevent="runTest" class="p-6 space-y-4 font-sans">
                    <div>
                        <label class="mb-1.5 block text-xs font-bold text-slate-700">Prompt Uji</label>
                        <textarea wire:model="testPrompt" rows="3" class="w-full rounded-xl border border-slate-200 p-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition"></textarea>
                        @error('testPrompt') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    @if($testResultStatus)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-2 text-xs">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-700">Hasil Test:</span>
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $testResultStatus === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                                    {{ $testResultStatus === 'success' ? 'Berhasil' : 'Gagal' }}
                                </span>
                            </div>
                            @if($testResultResponse)
                                <div class="text-slate-800 bg-white border border-slate-100 rounded-xl p-3 mt-1 leading-relaxed max-h-40 overflow-y-auto font-mono text-[10px]">
                                    {!! $testResultResponse !!}
                                </div>
                            @endif
                            @if($testResultError)
                                <div class="text-rose-600 font-bold mt-1 border-t border-slate-200 pt-2">{{ $testResultError }}</div>
                            @endif
                        </div>
                    @endif

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <button type="button" wire:click="closeTestModal" class="h-10 rounded-xl border border-slate-200 px-5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer font-sans">Batal</button>
                        <button type="submit" class="h-10 rounded-xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-6 text-xs font-bold transition cursor-pointer font-sans">Jalankan Uji</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Delete Confirmation Modal -->
    @if($confirmingDelete)
        <div x-data x-init="document.body.style.overflow = 'hidden'; document.documentElement.style.overflow = 'hidden'; return () => { document.body.style.overflow = ''; document.documentElement.style.overflow = ''; }" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm px-4 py-6">
            <div class="w-full max-w-sm rounded-[24px] bg-white p-6 shadow-2xl text-left space-y-4 overscroll-contain">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-full bg-rose-50 flex items-center justify-center text-rose-600">
                        <span class="material-symbols-outlined text-[20px] block">warning</span>
                    </span>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-rose-500">Konfirmasi Hapus</p>
                        <h2 class="text-sm font-black text-slate-900 mt-0.5">Hapus Provider AI?</h2>
                    </div>
                </div>
                <p class="text-xs text-slate-500 leading-relaxed">Aksi ini bersifat permanen. Konfigurasi model dan API Key terkait akan dihapus secara total dari database.</p>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button wire:click="$set('confirmingDelete', false)" class="h-10 rounded-xl border border-slate-200 px-5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer">Batal</button>
                    <button wire:click="deleteConfirmed" class="h-10 rounded-xl bg-rose-600 hover:bg-rose-700 text-white px-6 text-xs font-bold transition cursor-pointer">Ya, Hapus</button>
                </div>
            </div>
        </div>
    @endif
</div>
