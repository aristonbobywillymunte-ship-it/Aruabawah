<div class="mx-auto w-full max-w-7xl space-y-6 font-sans">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-200 pb-5">
        <div class="text-left">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Panel Administrator</p>
            <h1 class="text-2xl font-black text-slate-900 mt-1">Manajemen Sumber Berita</h1>
        </div>

        <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto">
            <div class="relative w-full sm:w-64">
                <input 
                    wire:model.live.debounce.300ms="search" 
                    type="text" 
                    placeholder="Cari portal..." 
                    class="h-10 w-full rounded-2xl border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387]/20"
                />
            </div>
            <button 
                wire:click="openSuggestInput" 
                class="inline-flex h-10 w-full sm:w-auto items-center justify-center gap-1.5 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 text-xs font-bold transition shadow-sm cursor-pointer whitespace-nowrap"
            >
                <span class="material-symbols-outlined text-[18px]">psychology</span>
                <span>Cari Saran AI Baru</span>
            </button>
            <button 
                wire:click="create" 
                class="inline-flex h-10 w-full sm:w-auto items-center justify-center gap-1.5 rounded-2xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-4 text-xs font-bold transition shadow-sm cursor-pointer whitespace-nowrap"
            >
                <span class="material-symbols-outlined text-[18px]">add</span>
                <span>Tambah Portal</span>
            </button>
        </div>
    </div>
    <!-- News Sources Table Card -->
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden text-left">
        <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-sm font-bold text-slate-800">Daftar Portal Berita</h2>
            <p class="text-[10px] text-slate-400 mt-0.5">Kelola tipe perayapan (HTML/RSS) dan selector data target.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-xs text-slate-700">
                <thead class="bg-slate-50/75 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3.5 text-left font-bold w-12">No</th>
                        <th class="px-4 py-3.5 text-left font-bold">Nama Portal</th>
                        <th class="px-4 py-3.5 text-left font-bold">Domain / Base URL</th>
                        <th class="px-4 py-3.5 text-left font-bold">Tipe Crawling</th>
                        <th class="px-4 py-3.5 text-left font-bold">Selector</th>
                        <th class="px-4 py-3.5 text-left font-bold">Timeout</th>
                        <th class="px-4 py-3.5 text-left font-bold">Status</th>
                        <th class="px-4 py-3.5 text-right font-bold w-36">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($sources as $source)
                        <tr wire:key="news-source-row-{{ $source->id }}" class="hover:bg-slate-50/50 transition">
                            <td class="px-4 py-3 font-semibold text-slate-500">{{ ($sources->currentPage() - 1) * $sources->perPage() + $loop->iteration }}</td>
                            <td class="px-4 py-3 font-bold text-slate-900">{{ $source->name }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-500">
                                <div class="space-y-0.5">
                                    <div>{{ $source->domain }}</div>
                                    <div class="text-[10px] text-slate-400 break-all">{{ $source->base_url ?: '-' }}</div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold border {{ $source->crawling_type === 'rss' ? 'bg-orange-50 text-orange-700 border-orange-100' : ($source->crawling_type === 'api' ? 'bg-purple-50 text-purple-700 border-purple-100' : 'bg-blue-50 text-blue-700 border-blue-100') }}">
                                    {{ strtoupper($source->crawling_type) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-mono text-[10px] text-slate-500">{{ $source->selector ?: '-' }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-700">{{ $source->timeout_seconds ? $source->timeout_seconds . 's' : 'Default' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 font-bold {{ $source->is_active ? 'text-emerald-600' : 'text-slate-400' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $source->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                    {{ $source->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5 justify-end whitespace-nowrap">
                                    @php
                                        $normalizedDomain = preg_replace('~^www\.~', '', strtolower(trim($source->domain ?? '')));
                                        $normalizedDomain = preg_replace('~^https?://~', '', $normalizedDomain);
                                        $normalizedDomain = preg_replace('~/.*$~', '', $normalizedDomain);
                                        $hasSuggestion = !empty($suggestionSourceIds[$source->id]) || (!empty($normalizedDomain) && !empty($suggestionDomains[$normalizedDomain]));
                                    @endphp

                                    @unless($hasSuggestion)
                                        <!-- AI Suggestion button -->
                                        <button 
                                            wire:click="generateSuggestionForExisting({{ $source->id }})" 
                                            wire:loading.attr="disabled"
                                            wire:target="generateSuggestionForExisting({{ $source->id }})"
                                            class="p-1.5 text-slate-500 hover:text-indigo-600 bg-slate-50 hover:bg-indigo-50 border border-slate-200 hover:border-indigo-400 rounded-lg transition cursor-pointer disabled:opacity-50 disabled:cursor-wait"
                                            title="Cari Saran AI"
                                        >
                                            <span class="material-symbols-outlined text-[15px] block">psychology</span>
                                        </button>
                                    @endunless
 
                                    <!-- Toggle Active/Inactive Status -->
                                    <button 
                                        wire:click="toggleStatus({{ $source->id }})" 
                                        wire:loading.attr="disabled"
                                        wire:target="toggleStatus({{ $source->id }})"
                                        class="p-1.5 text-slate-500 hover:text-slate-800 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-lg transition cursor-pointer disabled:opacity-50 disabled:cursor-wait"
                                        title="{{ $source->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                                    >
                                        <span class="material-symbols-outlined text-[15px] block">
                                            {{ $source->is_active ? 'toggle_on' : 'toggle_off' }}
                                        </span>
                                    </button>
 
                                    <!-- Delete Button -->
                                    <button 
                                        wire:click="requestDelete({{ $source->id }})" 
                                        wire:loading.attr="disabled"
                                        wire:target="requestDelete({{ $source->id }})"
                                        class="p-1.5 text-slate-400 hover:text-rose-600 bg-slate-50 hover:bg-rose-50 border border-slate-200 hover:border-rose-500 rounded-lg transition cursor-pointer disabled:opacity-50 disabled:cursor-wait"
                                        title="Hapus Portal"
                                    >
                                        <span class="material-symbols-outlined text-[15px] block">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-400 italic">Belum ada portal berita terdaftar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($sources->hasPages())
            <div class="border-t border-slate-100 px-4 sm:px-6 py-4">
                <div class="scale-[0.85] origin-right select-none w-full">
                    {{ $sources->onEachSide(1)->links(data: ['scrollTo' => false]) }}
                </div>
            </div>
        @endif
        
    </div>

    @if($showFormModal)
        <style>
            body, html {
                overflow: hidden !important;
            }
        </style>
        <template x-teleport="body">
            <div class="fixed inset-0 z-[999] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm px-4 py-6">
            <div class="w-full max-w-5xl overflow-hidden rounded-[24px] bg-white shadow-2xl text-left flex flex-col max-h-[92vh] overscroll-contain">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 flex-none">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-[#1fa387]">Manajemen Sumber</p>
                        <h2 class="text-base font-black text-slate-900 mt-0.5">{{ $isEditing ? 'Ubah Portal Berita' : 'Tambah Portal Berita Baru' }}</h2>
                    </div>
                    <button type="button" wire:click="closeFormModal" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition cursor-pointer">
                        <span class="material-symbols-outlined text-[20px] block">close</span>
                    </button>
                </div>
                
                <form wire:submit.prevent="save" class="flex flex-col flex-1 overflow-hidden">
                    <div class="p-6 space-y-6 overflow-y-auto flex-1 custom-scrollbar">
                        
                        <!-- Row 1: Basic Info -->
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-slate-700">Nama Portal</label>
                                <input wire:model="name" placeholder="Contoh: Detikcom" type="text" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                                @error('name') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-slate-700">Domain / Base URL</label>
                                <input wire:model="domain" placeholder="Contoh: detik.com" type="text" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                                @error('domain') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <!-- Row 2: URLs (Full width for long URLs) -->
                        <div class="space-y-4">
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-slate-700">Base URL</label>
                                <input wire:model="base_url" placeholder="Contoh: https://www.kompas.com" type="url" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                                @error('base_url') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-slate-700">Search URL Template <span class="text-rose-500">*</span></label>
                                <input wire:model="search_url" placeholder="Contoh: https://www.kompas.com/search?q={keyword}" type="text" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                                @error('search_url') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-slate-700">Feed URL</label>
                                <input wire:model="feed_url" placeholder="Opsional: https://..." type="url" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                                @error('feed_url') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-slate-700">Sitemap URL</label>
                                <input wire:model="sitemap_url" placeholder="Opsional: https://.../sitemap.xml" type="url" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                                @error('sitemap_url') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <!-- Row 3: Selectors -->
                        <div class="bg-slate-50 border border-slate-100 rounded-xl p-5 space-y-4">
                            <h3 class="text-xs font-black text-slate-700 mb-2 border-b border-slate-200 pb-2">Konfigurasi CSS Selectors</h3>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1.5 block text-[11px] font-bold text-slate-600">Search Result Selector</label>
                                    <input wire:model="search_result_selector" placeholder="Contoh: a[href]" type="text" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs font-mono text-slate-800 outline-none focus:border-[#1fa387] transition">
                                    @error('search_result_selector') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-[11px] font-bold text-slate-600">Article Link Selector</label>
                                    <input wire:model="article_link_selector" placeholder="Contoh: article a[href]" type="text" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs font-mono text-slate-800 outline-none focus:border-[#1fa387] transition">
                                    @error('article_link_selector') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-[11px] font-bold text-slate-600">Article Content Selector</label>
                                    <input wire:model="article_content_selector" placeholder="Opsional override selector konten" type="text" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs font-mono text-slate-800 outline-none focus:border-[#1fa387] transition">
                                    @error('article_content_selector') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-[11px] font-bold text-slate-600">Article Noise Selector</label>
                                    <input wire:model="article_noise_selector" placeholder="Contoh: .ads, .related" type="text" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs font-mono text-slate-800 outline-none focus:border-[#1fa387] transition">
                                    @error('article_noise_selector') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-[11px] font-bold text-slate-600">Selector HTML Author (Optional)</label>
                                    <input wire:model="article_author_selector" placeholder="Contoh: .author-name" type="text" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs font-mono text-slate-800 outline-none focus:border-[#1fa387] transition">
                                    @error('article_author_selector') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-[11px] font-bold text-slate-600">Selector HTML Date (Optional)</label>
                                    <input wire:model="article_date_selector" placeholder="Contoh: time" type="text" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs font-mono text-slate-800 outline-none focus:border-[#1fa387] transition">
                                    @error('article_date_selector') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-[11px] font-bold text-slate-600">Selector Legacy (Optional)</label>
                                    <input wire:model="selector" placeholder="Contoh: article .detail-text" type="text" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-xs font-mono text-slate-800 outline-none focus:border-[#1fa387] transition">
                                    @error('selector') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Row 4: Capabilities -->
                        <div class="grid gap-3 sm:grid-cols-2 bg-white p-4 rounded-xl border border-slate-100">
                            <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer">
                                <input type="checkbox" wire:model="is_feed_enabled" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387]/20 w-4 h-4">
                                Feed Enabled
                            </label>
                            <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer">
                                <input type="checkbox" wire:model="is_sitemap_enabled" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387]/20 w-4 h-4">
                                Sitemap Enabled
                            </label>
                            <p class="sm:col-span-2 text-[11px] text-slate-500 font-medium">
                                Search manual kini mengikuti <span class="font-semibold text-slate-700">Search URL</span> jika terisi.
                            </p>
                        </div>

                        <!-- Row 5: Crawling Config -->
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-slate-700">Tipe Crawling</label>
                                <select wire:model="crawling_type" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition bg-white">
                                    <option value="html">HTML Web Crawler (Playwright/HTTP)</option>
                                    <option value="rss">RSS Feed Reader</option>
                                    <option value="api">API Endpoint</option>
                                </select>
                                @error('crawling_type') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-slate-700">Timeout Perayapan (Detik)</label>
                                <input wire:model="timeout_seconds" placeholder="Opsional (Default sistem)" type="number" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                                @error('timeout_seconds') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <!-- Row 6: Extras -->
                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-slate-700">Catatan Internal / Crawler Rule</label>
                            <textarea wire:model="notes" placeholder="Catatan opsional mengenai struktur web target..." rows="3" class="w-full rounded-xl border border-slate-200 p-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition custom-scrollbar"></textarea>
                            @error('notes') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex items-center gap-2 pt-2 cursor-pointer pb-2">
                            <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387]/20 w-4 h-4" id="is_active_checkbox">
                            <label for="is_active_checkbox" class="text-xs font-bold text-slate-700 cursor-pointer">Portal Aktif (Sistem akan secara periodik merayap tautan baru)</label>
                        </div>
                    </div>

                    <!-- Footer Sticky -->
                    <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100 flex-none bg-slate-50 rounded-b-[24px]">
                        <button type="button" wire:click="closeFormModal" wire:loading.attr="disabled" wire:target="save" class="h-10 rounded-xl border border-slate-200 px-5 text-xs font-bold text-slate-600 hover:bg-slate-100 transition cursor-pointer disabled:opacity-50">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="h-10 rounded-xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-6 text-xs font-bold transition cursor-pointer shadow-md shadow-[#1fa387]/20 disabled:opacity-60 disabled:cursor-wait">
                            <span wire:loading.remove wire:target="save">Simpan Portal</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        </template>
    @endif

    @if($confirmingDelete)
        <style>
            body, html {
                overflow: hidden !important;
            }
        </style>
        <template x-teleport="body">
        <div class="fixed inset-0 z-[999] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm px-4 py-6">
            <div class="w-full max-w-sm rounded-[24px] bg-white p-6 shadow-2xl text-left space-y-4 overscroll-contain">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-full bg-rose-50 flex items-center justify-center text-rose-600">
                        <span class="material-symbols-outlined text-[20px] block">warning</span>
                    </span>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-rose-500">Konfirmasi Hapus</p>
                        <h2 class="text-sm font-black text-slate-900 mt-0.5">Hapus Portal Berita?</h2>
                    </div>
                </div>
                <p class="text-xs text-slate-500 leading-relaxed">Aksi ini bersifat permanen. Portal terpilih tidak akan dirayap kembali di masa mendatang.</p>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button wire:click="$set('confirmingDelete', false)" wire:loading.attr="disabled" wire:target="deleteConfirmed" class="h-10 rounded-xl border border-slate-200 px-5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer disabled:opacity-50">Batal</button>
                    <button wire:click="deleteConfirmed" wire:loading.attr="disabled" wire:target="deleteConfirmed" class="h-10 rounded-xl bg-rose-600 hover:bg-rose-700 text-white px-6 text-xs font-bold transition cursor-pointer disabled:opacity-50 disabled:cursor-wait">Ya, Hapus</button>
                </div>
            </div>
        </div>
        </template>
    @endif

    <!-- AI Suggestions Section -->
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden text-left mt-8">
        <div class="border-b border-slate-100 px-6 py-4 flex justify-between items-center">
            <div>
                <h2 class="text-sm font-bold text-slate-800">Daftar Saran Metadata AI</h2>
                <p class="text-[10px] text-slate-400 mt-0.5">Pantau saran AI, jalankan uji scraping visual, dan aktifkan jika terverifikasi.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-xs text-slate-700">
                <thead class="bg-slate-50/75 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3.5 text-left font-bold w-12">No</th>
                        <th class="px-4 py-3.5 text-left font-bold">Sumber Portal</th>
                        <th class="px-4 py-3.5 text-left font-bold">Metadata Saran</th>
                        <th class="px-4 py-3.5 text-left font-bold">Confidence</th>
                        <th class="px-4 py-3.5 text-left font-bold">Status</th>
                        <th class="px-4 py-3.5 text-right font-bold w-48">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($suggestions as $sugg)
                        @php
                            $hasTestResult = filled($sugg->test_result_json);
                        @endphp
                        <tr wire:key="news-source-suggestion-row-{{ $sugg->id }}" class="hover:bg-slate-50/50 transition">
                            <td class="px-4 py-3 font-semibold text-slate-500">{{ ($suggestions->currentPage() - 1) * $suggestions->perPage() + $loop->iteration }}</td>
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-900">{{ $sugg->source_name ?: 'Portal Baru' }}</div>
                                <div class="text-[10px] text-slate-400 mt-0.5">{{ $sugg->domain }}</div>
                            </td>
                            <td class="px-4 py-3 text-[10px]">
                                <div class="space-y-1">
                                    <div><span class="font-bold text-slate-400">Search URL:</span> <span class="font-mono text-slate-600">{{ $sugg->search_url ?: '-' }}</span></div>
                                    <div><span class="font-bold text-slate-400">Selector Link:</span> <span class="font-mono text-slate-600">{{ $sugg->article_link_selector ?: '-' }}</span></div>
                                    <div><span class="font-bold text-slate-400">Selector Content:</span> <span class="font-mono text-slate-600">{{ $sugg->article_content_selector ?: '-' }}</span></div>
                                </div>
                            </td>
                            <td class="px-4 py-3 font-bold">
                                <span class="{{ $sugg->confidence >= 0.8 ? 'text-emerald-600' : ($sugg->confidence >= 0.5 ? 'text-amber-600' : 'text-slate-400') }}">
                                    {{ number_format($sugg->confidence * 100, 0) }}%
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $statusBadge = match($sugg->status) {
                                        'draft_ai', 'needs_review' => 'bg-amber-50 text-amber-700 border-amber-100',
                                        'testing' => 'bg-blue-50 text-blue-600 border-blue-100 animate-pulse',
                                        'lolos', 'verified' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                                        'failed', 'rejected' => 'bg-rose-50 text-rose-700 border-rose-100',
                                        'approved' => 'bg-purple-50 text-purple-700 border-purple-100',
                                        default => 'bg-slate-50 text-slate-600 border-slate-100'
                                    };
                                    $statusLabel = match($sugg->status) {
                                        'draft_ai', 'needs_review' => 'PERLU REVIEW',
                                        'testing' => 'MENGUJI...',
                                        'lolos' => 'LOLOS UJI',
                                        'verified' => 'TERVERIFIKASI',
                                        'failed', 'rejected' => 'GAGAL UJI',
                                        'approved' => 'TERVERIFIKASI',
                                        default => strtoupper($sugg->status)
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold border {{ $statusBadge }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    @if($hasTestResult)
                                        <button 
                                            wire:click="testSuggestion({{ $sugg->id }})" 
                                            wire:loading.attr="disabled"
                                            wire:target="testSuggestion({{ $sugg->id }})"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[10px] font-bold text-blue-600 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg transition cursor-pointer disabled:opacity-50 disabled:cursor-wait"
                                            title="Uji Ulang Konfigurasi"
                                        >
                                            <span class="material-symbols-outlined text-[14px]">play_circle</span>
                                            <span>Uji Ulang</span>
                                        </button>
                                        <button 
                                            wire:click="viewTestResult({{ $sugg->id }})" 
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[10px] font-bold text-slate-600 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-lg transition cursor-pointer"
                                            title="Lihat Detail Hasil Uji"
                                        >
                                            <span class="material-symbols-outlined text-[14px]">visibility</span>
                                            <span>Hasil</span>
                                        </button>
                                    @else
                                        <button 
                                            wire:click="testSuggestion({{ $sugg->id }})" 
                                            wire:loading.attr="disabled"
                                            wire:target="testSuggestion({{ $sugg->id }})"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[10px] font-bold text-blue-600 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg transition cursor-pointer disabled:opacity-50 disabled:cursor-wait"
                                            title="Jalankan Uji Konfigurasi"
                                        >
                                            <span class="material-symbols-outlined text-[14px]">play_circle</span>
                                            <span>Uji</span>
                                        </button>
                                    @endif

                                    @if($sugg->status !== 'verified')
                                        <button 
                                            wire:click="rejectSuggestion({{ $sugg->id }})" 
                                            wire:confirm="Tolak saran ini dan sembunyikan?"
                                            wire:loading.attr="disabled"
                                            wire:target="rejectSuggestion({{ $sugg->id }})"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[10px] font-bold text-red-600 bg-red-50 hover:bg-red-100 border border-red-200 rounded-lg transition cursor-pointer disabled:opacity-50 disabled:cursor-wait"
                                            title="Tolak Saran"
                                        >
                                            <span class="material-symbols-outlined text-[14px]">block</span>
                                            <span>Tolak</span>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-400 italic">Belum ada saran konfigurasi AI.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($suggestions, 'hasPages') && $suggestions->hasPages())
            <div class="border-t border-slate-100 px-4 sm:px-6 py-4">
                <div class="scale-[0.85] origin-right select-none w-full">
                    {{ $suggestions->onEachSide(1)->links(data: ['scrollTo' => false]) }}
                </div>
            </div>
        @endif
    </div>

    @if($showSuggestInputModal)
        <style>
            body, html {
                overflow: hidden !important;
            }
        </style>
        <template x-teleport="body">
        <div class="fixed inset-0 z-[999] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm px-4 py-6">
            <div class="w-full max-w-sm rounded-[24px] bg-white p-6 shadow-2xl text-left space-y-4 overscroll-contain">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-[#1fa387]">Saran AI Baru</p>
                    <h2 class="text-sm font-black text-slate-900 mt-0.5">Cari Saran AI Untuk Portal Baru</h2>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nama Portal</label>
                        <input wire:model="suggSourceName" placeholder="Contoh: Liputan6" type="text" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                        @error('suggSourceName') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Domain</label>
                        <input wire:model="suggDomain" placeholder="Contoh: liputan6.com" type="text" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                        @error('suggDomain') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button wire:click="$set('showSuggestInputModal', false)" class="h-10 rounded-xl border border-slate-200 px-5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer">Batal</button>
                    <button wire:click="generateSuggestionForNew" class="h-10 rounded-xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-6 text-xs font-bold transition cursor-pointer">Cari Saran</button>
                </div>
            </div>
        </div>
        </template>
    @endif

    @if($showTestModal && $testResult)
        @php
            $activeSuggestion = \App\Models\NewsSourceSuggestion::find($selectedSuggestionId);
            $modalStatusLabel = match($testStatus) {
                'lolos' => 'Lolos Uji',
                'verified' => 'Terverifikasi',
                'failed' => 'Gagal Uji',
                'testing' => 'Menguji...',
                default => 'Perlu Review',
            };
            $modalStatusClass = match($testStatus) {
                'lolos', 'verified' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                'failed' => 'bg-rose-50 text-rose-700 border-rose-100',
                'testing' => 'bg-blue-50 text-blue-700 border-blue-100 animate-pulse',
                default => 'bg-amber-50 text-amber-700 border-amber-100',
            };
        @endphp
        <style>
            body, html {
                overflow: hidden !important;
            }
        </style>
        <template x-teleport="body">
        <div class="fixed inset-0 z-[999] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm px-4 py-6">
            <div class="w-full max-w-5xl overflow-hidden rounded-[24px] bg-white shadow-2xl text-left flex flex-col max-h-[92vh] font-sans border border-slate-100 overscroll-contain">
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-slate-100 px-8 py-5">
                    <h2 class="text-lg font-black text-slate-850">Hasil Uji Saran AI</h2>
                    <button type="button" wire:click="$set('showTestModal', false)" class="rounded-full p-2 text-slate-400 hover:bg-slate-155 hover:text-slate-700 transition cursor-pointer">
                        <span class="material-symbols-outlined text-[22px] block">close</span>
                    </button>
                </div>

                <!-- Modal Content (Scrollable) -->
                <div class="px-8 py-6 space-y-5 overflow-y-auto flex-1 bg-white overscroll-contain">
                    <!-- Metadata Header Row -->
                    <div class="rounded-2xl border border-slate-150 bg-slate-50/50 p-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 border border-slate-200">
                                <span class="material-symbols-outlined text-[20px] block">assignment_turned_in</span>
                            </span>
                            <div class="text-xs">
                                <div><span class="text-slate-400">Portal Berita:</span> <strong class="text-slate-800">{{ $activeSuggestion->source_name ?: $activeSuggestion->domain }}</strong></div>
                                <div class="mt-2 flex items-center gap-2 flex-wrap">
                                    <span class="text-slate-400">ID:</span> <span class="font-bold text-slate-700 bg-slate-200/40 px-1.5 py-0.5 rounded text-[10px]">#{{ $activeSuggestion->id }}</span>
                                    <span class="text-slate-300">|</span>
                                    <span class="text-slate-400">Mode:</span> <span class="font-bold text-slate-600">Dry-run</span>
                                    <span class="text-slate-300">|</span>
                                    <span class="text-slate-450 font-bold">Keyword Uji:</span>
                                    <input type="text" wire:model="testKeyword" class="h-7 w-28 rounded-lg border border-slate-300 px-2 text-[11px] font-bold text-slate-800 outline-none focus:border-[#1fa387] bg-white transition">
                                </div>
                                <div class="mt-3 flex flex-col gap-2">
                                    <label class="text-[10px] uppercase tracking-wider font-bold text-slate-400">Manual Article URL</label>
                                    <div class="flex flex-col sm:flex-row gap-2">
                                        <input type="url" wire:model="manualArticleUrl" placeholder="Paste URL artikel asli di sini" class="h-8 flex-1 rounded-lg border border-slate-300 px-2 text-[11px] font-semibold text-slate-800 outline-none focus:border-[#1fa387] bg-white transition">
                                        <button
                                            type="button"
                                            wire:click="testManualUrl({{ $activeSuggestion->id }})"
                                            class="h-8 rounded-lg border border-[#1fa387] bg-[#1fa387]/10 px-3 text-[10px] font-bold text-[#1fa387] hover:bg-[#1fa387]/20 transition cursor-pointer inline-flex items-center gap-1"
                                        >
                                            <span class="material-symbols-outlined text-[14px]">link</span>
                                            Uji URL Manual
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Status badge in the center -->
                        <div class="flex items-center">
                            <span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-bold rounded-full border {{ $modalStatusClass }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ in_array($testStatus, ['lolos', 'verified'], true) ? 'bg-emerald-500 animate-pulse' : ($testStatus === 'failed' ? 'bg-rose-500' : ($testStatus === 'testing' ? 'bg-blue-500 animate-pulse' : 'bg-amber-500')) }}"></span>
                                {{ $modalStatusLabel }}
                            </span>
                        </div>

                        <!-- Right details -->
                        <div class="text-xs text-right text-slate-500">
                            <div>Uji dilakukan: <span class="font-semibold text-slate-700">{{ $activeSuggestion->updated_at->format('d M Y H:i') }}</span></div>
                            <div class="mt-1">Hasil: 
                                <span class="font-bold {{ in_array($testStatus, ['lolos', 'verified'], true) ? 'text-emerald-600' : ($testStatus === 'failed' ? 'text-rose-600' : ($testStatus === 'testing' ? 'text-blue-600' : 'text-amber-600')) }}">
                                    {{ $testStatus === 'lolos' ? 'Lolos Uji' : ($testStatus === 'verified' ? 'Terverifikasi' : ($testStatus === 'failed' ? 'Gagal Uji' : ($testStatus === 'testing' ? 'Sedang Diuji' : 'Perlu Review'))) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Saran AI (Configuration) -->
                    <div class="border border-slate-200 rounded-2xl p-5 bg-white space-y-4 shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 class="text-xs font-bold text-slate-800 flex items-center gap-2">
                                <span class="material-symbols-outlined text-[16px] text-slate-500">settings_suggest</span>
                                Konfigurasi / Saran AI
                            </h3>
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600">
                                Baca saja
                            </span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                            <div class="space-y-3">
                                <div>
                                    <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider mb-1">Search URL Template</span>
                                    <span class="font-mono text-slate-700 break-all bg-slate-50 px-2 py-1 rounded block">{{ $activeSuggestion->search_url ?: '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider mb-1">Feed URL</span>
                                    <span class="font-mono text-slate-700 break-all bg-slate-50 px-2 py-1 rounded block">{{ $activeSuggestion->feed_url ?: '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider mb-1">Sitemap URL</span>
                                    <span class="font-mono text-slate-700 break-all bg-slate-50 px-2 py-1 rounded block">{{ $activeSuggestion->sitemap_url ?: '-' }}</span>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div>
                                    <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider mb-1">Article Link Selector</span>
                                    <span class="font-mono text-slate-700 break-all bg-slate-50 px-2 py-1 rounded block">{{ $activeSuggestion->article_link_selector ?: '-' }}</span>
                                </div>
                                <div>
                                    <span class="block text-slate-400 mb-1">Article Content Selector</span>
                                    <strong class="text-slate-800 block break-words font-mono text-[10px] bg-slate-100 p-1.5 rounded">{{ $activeSuggestion->article_content_selector ?: '-' }}</strong>
                                </div>
                                <div>
                                    <span class="block text-slate-400 mb-1">Article Noise Selector</span>
                                    <strong class="text-slate-800 block break-words font-mono text-[10px] bg-slate-100 p-1.5 rounded">{{ $activeSuggestion->article_noise_selector ?: '-' }}</strong>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider mb-1">Article Author Selector</span>
                                    <span class="font-mono text-slate-700 break-all bg-slate-50 px-2 py-1 rounded block">{{ $activeSuggestion->article_author_selector ?: '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider mb-1">Article Date Selector</span>
                                    <span class="font-mono text-slate-700 break-all bg-slate-50 px-2 py-1 rounded block">{{ $activeSuggestion->article_date_selector ?: '-' }}</span>
                                </div>
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider">Confidence & Alasan</span>
                                        <span class="font-bold text-[11px] {{ $activeSuggestion->confidence >= 0.8 ? 'text-emerald-600' : 'text-amber-600' }}">
                                            Confidence: {{ number_format($activeSuggestion->confidence * 100, 0) }}%
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 italic bg-slate-50 p-2 rounded leading-normal">{{ $activeSuggestion->ai_reason ?: 'Tidak ada penjelasan dari AI.' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Discovered and Rejected URLs Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <!-- Discovered URLs -->
                        <div class="space-y-2">
                            <h3 class="text-xs font-bold text-slate-800 flex items-center gap-2">
                                URL Ditemukan
                                <span class="px-2 py-0.2 bg-emerald-100 text-emerald-700 rounded-full text-[10px] font-bold">
                                    {{ count($testResult['discovered_urls'] ?? []) }}
                                </span>
                            </h3>
                            <div class="border border-slate-200 rounded-2xl bg-white p-4 max-h-40 overflow-y-auto space-y-2">
                                @forelse($testResult['discovered_urls'] ?? [] as $urlInfo)
                                    @php
                                        $url = is_array($urlInfo) ? ($urlInfo['url'] ?? '') : $urlInfo;
                                        $source = is_array($urlInfo) ? ($urlInfo['source'] ?? 'search_url') : 'search_url';
                                        $status = is_array($urlInfo) ? ($urlInfo['status'] ?? 'accepted') : 'accepted';
                                    @endphp
                                    <div class="flex items-center justify-between gap-3 text-[11px] border-b border-slate-100/50 pb-1.5 last:border-0 last:pb-0">
                                        <div class="flex items-center gap-2 min-w-0 flex-1">
                                            <span class="material-symbols-outlined text-[14px] text-emerald-500 shrink-0">check_circle</span>
                                            <a href="{{ $url }}" target="_blank" class="text-slate-600 hover:text-blue-600 hover:underline font-medium truncate break-all">{{ $url }}</a>
                                        </div>
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <span class="text-[9px] font-bold text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded uppercase">{{ $source }}</span>
                                            <span class="text-[9px] font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded uppercase">{{ $status }}</span>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-[11px] text-slate-400 italic">Tidak ada URL artikel yang ditemukan.</div>
                                @endforelse
                            </div>
                        </div>

                        <!-- Rejected URLs -->
                        <div class="space-y-2">
                            <h3 class="text-xs font-bold text-slate-800 flex items-center gap-2">
                                URL Ditolak
                                <span class="px-2 py-0.2 bg-rose-100 text-rose-700 rounded-full text-[10px] font-bold">
                                    {{ count($testResult['rejected_urls'] ?? []) }}
                                </span>
                            </h3>
                            <div class="border border-slate-200 rounded-2xl bg-white p-4 max-h-40 overflow-y-auto space-y-2">
                                @forelse($testResult['rejected_urls'] ?? [] as $rej)
                                    <div class="flex items-center justify-between gap-4 text-[11px] border-b border-slate-100/50 pb-1.5 last:border-0 last:pb-0">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="material-symbols-outlined text-[14px] text-rose-500 shrink-0">cancel</span>
                                            <span class="text-slate-400 truncate break-all">{{ $rej['url'] }}</span>
                                        </div>
                                        <span class="text-[10px] font-bold text-rose-600 bg-rose-50 px-1.5 py-0.5 rounded whitespace-nowrap">{{ $rej['reason'] }}</span>
                                    </div>
                                @empty
                                    <div class="text-[11px] text-slate-400 italic">Tidak ada URL yang ditolak.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <!-- Valid Sample Article Card -->
                    @php
                        $firstArticle = data_get($testResult, 'valid_articles.0');
                    @endphp
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-[11px]">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="text-slate-400 uppercase tracking-wider font-bold text-[10px]">Mode Test</div>
                            <div class="mt-1 font-bold text-slate-800">{{ data_get($testResult, 'mode', 'discovery') }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="text-slate-400 uppercase tracking-wider font-bold text-[10px]">Manual URL</div>
                            <div class="mt-1 font-mono text-slate-700 break-all">{{ data_get($testResult, 'manual_url', '-') ?: '-' }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="text-slate-400 uppercase tracking-wider font-bold text-[10px]">Status</div>
                            <div class="mt-1 font-bold {{ $testStatus === 'verified' ? 'text-emerald-600' : 'text-amber-600' }}">{{ strtoupper($testStatus ?? data_get($testResult, 'status', 'failed')) }}</div>
                        </div>
                    </div>

                    @if($firstArticle)
                        <div class="border border-emerald-200 rounded-2xl p-5 bg-emerald-50/10 space-y-3">
                            <div class="flex items-center gap-3">
                                <span class="w-10 h-10 rounded-full bg-emerald-100/80 flex items-center justify-center text-emerald-600">
                                    <span class="material-symbols-outlined text-[20px] block">article</span>
                                </span>
                                <h3 class="text-sm font-black text-slate-800">Artikel Valid</h3>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs mt-2 border-t border-slate-100 pt-3">
                                <div class="space-y-2">
                                    <div>
                                        <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider mb-0.5">Judul Artikel</span>
                                        <strong class="text-slate-800 text-[11px] leading-tight block">{{ $firstArticle['title'] }}</strong>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 pt-1">
                                        <div>
                                            <span class="text-slate-400 block text-[9px] uppercase font-bold tracking-wider mb-0.5">Penulis</span>
                                            <strong class="text-slate-700 text-[10px] block truncate" title="{{ $firstArticle['author'] ?? 'Editor' }}">{{ $firstArticle['author'] ?? 'Editor' }}</strong>
                                        </div>
                                        <div>
                                            <span class="text-slate-400 block text-[9px] uppercase font-bold tracking-wider mb-0.5">Tanggal Terbit</span>
                                            <strong class="text-slate-700 text-[10px] block truncate" title="{{ $firstArticle['published_at'] ?? '-' }}">{{ $firstArticle['published_at'] ?? '-' }}</strong>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 pt-1">
                                        <div>
                                            <span class="text-slate-400 block text-[9px] uppercase font-bold tracking-wider mb-0.5">Raw Date</span>
                                            <strong class="text-slate-700 text-[10px] block truncate" title="{{ $firstArticle['published_at_raw'] ?? '-' }}">{{ $firstArticle['published_at_raw'] ?? '-' }}</strong>
                                        </div>
                                        <div>
                                            <span class="text-slate-400 block text-[9px] uppercase font-bold tracking-wider mb-0.5">Date Parse</span>
                                            <strong class="text-slate-700 text-[10px] block truncate" title="{{ $firstArticle['date_parse_status'] ?? '-' }}">{{ $firstArticle['date_parse_status'] ?? '-' }}</strong>
                                        </div>
                                    </div>
                                    <div class="pt-1">
                                        <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider mb-0.5">URL Artikel</span>
                                        <a href="{{ $firstArticle['url'] }}" target="_blank" class="text-blue-600 hover:underline font-mono text-[10px] inline-flex items-center gap-1 break-all">
                                            {{ $firstArticle['url'] }}
                                            <span class="material-symbols-outlined text-[12px] shrink-0">open_in_new</span>
                                        </a>
                                    </div>
                                    <div class="pt-1">
                                        <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider mb-0.5">HTTP Status & Extraction</span>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 text-[9px] font-bold rounded border border-emerald-200">HTTP {{ $firstArticle['http_status'] ?? 200 }}</span>
                                            <span class="px-2 py-0.5 bg-blue-50 text-blue-700 text-[9px] font-bold rounded border border-blue-200">Extraction: {{ strtoupper($firstArticle['extraction_status'] ?? 'success') }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <div>
                                        <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider mb-0.5">Canonical URL</span>
                                        <a href="{{ $firstArticle['canonical_url'] }}" target="_blank" class="text-blue-600 hover:underline font-mono text-[10px] inline-flex items-center gap-1 break-all">
                                            {{ $firstArticle['canonical_url'] }}
                                            <span class="material-symbols-outlined text-[12px] shrink-0">open_in_new</span>
                                        </a>
                                    </div>
                                    <div class="pt-1">
                                        <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider mb-0.5">Selector yang Digunakan</span>
                                        <span class="font-mono text-slate-700 text-[11px] bg-slate-50 px-2 py-0.5 rounded border border-slate-200 inline-block mt-0.5">{{ $firstArticle['selector'] ?? '-' }}</span>
                                    </div>
                                    <div class="pt-1">
                                        <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider mb-0.5">Content Length</span>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <strong class="text-slate-800">{{ number_format($firstArticle['content_length'], 0, ',', '.') }} Karakter</strong>
                                            @if(($firstArticle['content_length'] ?? 0) >= 500)
                                                <span class="inline-flex items-center gap-0.5 px-2 py-0.5 bg-emerald-100 text-emerald-800 text-[9px] font-bold rounded-full">
                                                    <span class="material-symbols-outlined text-[10px]">done</span>
                                                    > 500 karakter
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-0.5 px-2 py-0.5 bg-rose-100 text-rose-800 text-[9px] font-bold rounded-full">
                                                    <span class="material-symbols-outlined text-[10px]">close</span>
                                                    &lt; 500 karakter
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Preview Konten -->
                        <div class="space-y-2">
                            <h3 class="text-xs font-bold text-slate-800">Preview Konten</h3>
                            <div class="border border-slate-200 rounded-2xl bg-white p-4 max-h-44 overflow-y-auto text-xs text-slate-600 leading-relaxed font-sans scrollbar-thin">
                                {{ $firstArticle['preview'] }}
                            </div>
                        </div>
                    @else
                        <div class="border border-rose-200 rounded-2xl p-5 bg-rose-50/10 space-y-2 text-center">
                            <span class="material-symbols-outlined text-rose-500 text-[32px] block mx-auto">warning</span>
                            <h3 class="text-xs font-bold text-slate-800 mt-1">Tidak Ada Artikel Valid Ditemukan</h3>
                            <p class="text-[11px] text-slate-500">Penyebab: Perayapan gagal, link ditolak filter, atau panjang artikel kurang dari 500 karakter.</p>
                        </div>
                    @endif

                    <!-- Reasons Card -->
                    <div class="border border-blue-200 rounded-2xl p-5 bg-[#f0f9ff]/40 flex gap-4">
                        <span class="w-10 h-10 rounded-full bg-blue-100/80 flex items-center justify-center text-blue-600 shrink-0">
                            <span class="material-symbols-outlined text-[20px] block">verified_user</span>
                        </span>
                        <div class="space-y-2 flex-1">
                            <h3 class="text-sm font-black text-slate-800">Alasan Lulus/Gagal</h3>
                            <div class="space-y-1.5 text-xs text-slate-700">
                                @foreach($testResult['reasons'] ?? [] as $reason)
                                    @php
                                        $isPassed = !str_contains(strtolower($reason), 'gagal') && !str_contains(strtolower($reason), 'kurang') && !str_contains(strtolower($reason), 'tidak');
                                    @endphp
                                    <div class="flex items-center gap-2">
                                        @if($isPassed)
                                            <span class="material-symbols-outlined text-[15px] text-emerald-500">done</span>
                                        @else
                                            <span class="material-symbols-outlined text-[15px] text-rose-500">close</span>
                                        @endif
                                        <span>{{ $reason }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-between gap-3 px-8 py-5 border-t border-slate-100 bg-slate-50/70 rounded-b-[24px]">
                    @if($isViewingResultOnly)
                        <div class="flex justify-end w-full">
                            <button
                                wire:click="$set('showTestModal', false)"
                                class="h-10 rounded-xl border border-slate-200 px-6 text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 transition cursor-pointer inline-flex items-center gap-1.5 shadow-sm"
                            >
                                <span class="material-symbols-outlined text-[16px]">close</span>
                                <span>Tutup</span>
                            </button>
                        </div>
                    @else
                        <div class="flex gap-2">
                            {{-- Uji Discovery --}}
                            <button
                                wire:click="testSuggestion({{ $selectedSuggestionId }})"
                                wire:loading.attr="disabled"
                                wire:target="testSuggestion({{ $selectedSuggestionId }}),testManualUrl({{ $selectedSuggestionId }})"
                                class="h-10 rounded-xl border border-slate-200 px-5 text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 transition cursor-pointer inline-flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-wait"
                            >
                                <span class="material-symbols-outlined text-[16px]">refresh</span>
                                <span wire:loading.remove wire:target="testSuggestion({{ $selectedSuggestionId }})">{{ $testResult ? 'Uji Ulang Discovery' : 'Uji Discovery' }}</span>
                                <span wire:loading wire:target="testSuggestion({{ $selectedSuggestionId }})">Menguji...</span>
                            </button>
                            {{-- Uji URL Manual --}}
                            <button
                                wire:click="testManualUrl({{ $selectedSuggestionId }})"
                                wire:loading.attr="disabled"
                                wire:target="testSuggestion({{ $selectedSuggestionId }}),testManualUrl({{ $selectedSuggestionId }})"
                                class="h-10 rounded-xl border border-[#1fa387] px-5 text-xs font-bold text-[#1fa387] bg-[#1fa387]/10 hover:bg-[#1fa387]/20 transition cursor-pointer inline-flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-wait"
                            >
                                <span class="material-symbols-outlined text-[16px]">link</span>
                                <span wire:loading.remove wire:target="testManualUrl({{ $selectedSuggestionId }})">{{ $testResult ? 'Uji Ulang URL Manual' : 'Uji URL Manual' }}</span>
                                <span wire:loading wire:target="testManualUrl({{ $selectedSuggestionId }})">Menguji URL...</span>
                            </button>
                        </div>
                        <div class="flex gap-2">
                            {{-- Tolak Saran — confirm + loading --}}
                            <button
                                wire:click="rejectSuggestion({{ $selectedSuggestionId }})"
                                wire:confirm="Tolak saran ini? Status akan menjadi DITOLAK dan tidak dipakai pipeline."
                                wire:loading.attr="disabled"
                                wire:target="rejectSuggestion({{ $selectedSuggestionId }})"
                                class="h-10 rounded-xl bg-rose-50 hover:bg-rose-100 border border-rose-200 text-rose-600 px-5 text-xs font-bold transition cursor-pointer inline-flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-wait"
                            >
                                <span class="material-symbols-outlined text-[16px]">block</span>
                                <span>Tolak Saran</span>
                            </button>
                            {{-- Simpan sebagai Draft — loading --}}
                            <button
                                wire:click="saveAsDraft({{ $selectedSuggestionId }})"
                                wire:loading.attr="disabled"
                                wire:target="saveAsDraft({{ $selectedSuggestionId }})"
                                class="h-10 rounded-xl border border-slate-200 px-5 text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 transition cursor-pointer inline-flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-wait"
                            >
                                <span class="material-symbols-outlined text-[16px]">save</span>
                                <span>Simpan sebagai Draft</span>
                            </button>
                            {{-- Hapus — confirm permanen + loading --}}
                            <button
                                wire:click="deleteSuggestion({{ $selectedSuggestionId }})"
                                wire:confirm="Hapus saran ini secara permanen?"
                                wire:loading.attr="disabled"
                                wire:target="deleteSuggestion({{ $selectedSuggestionId }})"
                                class="h-10 rounded-xl border border-slate-200 px-5 text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 transition cursor-pointer inline-flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-wait"
                            >
                                <span class="material-symbols-outlined text-[16px]">delete</span>
                                <span>Hapus</span>
                            </button>
                            {{-- Approve & Aktifkan — confirm + loading + disabled jika tidak verified --}}
                            <button
                                wire:click="approveSuggestion({{ $selectedSuggestionId }})"
                                wire:confirm="Setujui dan terapkan konfigurasi ini ke News Source resmi?"
                                wire:loading.attr="disabled"
                                wire:target="approveSuggestion({{ $selectedSuggestionId }})"
                                @if(!in_array($testStatus, ['lolos', 'verified'], true)) disabled @endif
                                class="h-10 rounded-xl bg-[#1fa387] hover:bg-[#1a8b73] disabled:bg-slate-200 disabled:text-slate-400 disabled:cursor-not-allowed text-white px-5 text-xs font-bold transition cursor-pointer inline-flex items-center gap-1.5"
                            >
                                <span class="material-symbols-outlined text-[16px]">check_circle</span>
                                <span wire:loading.remove wire:target="approveSuggestion({{ $selectedSuggestionId }})">Terverifikasi</span>
                                <span wire:loading wire:target="approveSuggestion({{ $selectedSuggestionId }})">Mengaktifkan...</span>
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        </template>
    @endif
</div>
