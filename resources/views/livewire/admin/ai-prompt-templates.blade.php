<div class="mx-auto w-full max-w-7xl space-y-6 font-sans">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-200 pb-5">
        <div class="text-left">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Panel Administrator</p>
            <h1 class="text-2xl font-black text-slate-900 mt-1">AI Prompt Templates</h1>
            <p class="text-xs text-slate-500 mt-1">Atur prompt utama, user prompt template, dan schema output secara manual untuk semua alur AI.</p>
        </div>

        <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto">
            <div class="relative w-full sm:w-80">
                <input 
                    wire:model.live.debounce.300ms="search" 
                    type="text" 
                    placeholder="Cari template..." 
                    class="h-10 w-full rounded-2xl border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387]/20"
                />
            </div>
            <button 
                wire:click="openTrashModal" 
                class="inline-flex h-10 w-full sm:w-auto items-center justify-center gap-1.5 rounded-2xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 px-4 text-xs font-bold transition shadow-sm cursor-pointer whitespace-nowrap"
            >
                <span class="material-symbols-outlined text-[18px]">delete_outline</span>
                <span>Data Dihapus</span>
                @php
                    $trashCount = \App\Models\AiPromptTemplate::onlyTrashed()->count();
                @endphp
                @if($trashCount > 0)
                    <span class="ml-1 px-1.5 py-0.5 rounded-full bg-rose-500 text-white text-[9px] font-bold">{{ $trashCount }}</span>
                @endif
            </button>
            <button 
                wire:click="create" 
                class="inline-flex h-10 w-full sm:w-auto items-center justify-center gap-1.5 rounded-2xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-5 text-xs font-bold transition shadow-sm cursor-pointer whitespace-nowrap"
            >
                <span class="material-symbols-outlined text-[18px]">add</span>
                <span>Tambah Template</span>
            </button>
        </div>
    </div>
    <!-- AI Templates Table Card -->
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden text-left">
        <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-sm font-bold text-slate-800">Daftar Prompt Template</h2>
            <p class="text-[10px] text-slate-400 mt-0.5">Semua template AI disimpan di database dan bisa diedit langsung dari sini.</p>
        </div>

        @if(isset($duplicateNames) && $duplicateNames->isNotEmpty())
            <div class="px-6 pt-4">
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                    <p class="font-bold">Ada template dengan nama yang sama. Ini bisa bikin hasil test berbeda dari alur produksi.</p>
                    <p class="mt-1 text-[10px] leading-relaxed">
                        {{ $duplicateNames->map(fn ($row) => $row->name . ' (' . $row->source_type . ') x' . $row->total)->implode(', ') }}
                    </p>
                </div>
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-xs text-slate-700">
                <thead class="bg-slate-50/75 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3.5 text-left font-bold w-12">No</th>
                        <th class="px-4 py-3.5 text-left font-bold">Nama Template</th>
                        <th class="px-4 py-3.5 text-left font-bold">Tipe Sumber</th>
                        <th class="px-4 py-3.5 text-left font-bold">Status</th>
                        <th class="px-4 py-3.5 text-right font-bold w-36">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($templates as $template)
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-4 py-3 text-slate-500 font-semibold">{{ $loop->iteration }}</td>
                        <td class="px-4 py-3 font-bold text-slate-900">{{ $template->name }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-[10px] font-bold border {{ $template->source_type === 'article' ? 'bg-blue-50 text-blue-700 border-blue-100' : ($template->source_type === 'portal_suggestion' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-purple-50 text-purple-700 border-purple-100') }}">
                                {{ $template->source_type === 'article' ? 'Portal Berita' : ($template->source_type === 'portal_suggestion' ? 'Saran Portal' : 'Media Sosial') }}
                            </span>
                        </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 font-bold {{ $template->is_active ? 'text-emerald-600' : 'text-slate-400' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $template->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                    {{ $template->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button 
                                        wire:click="openTestModal({{ $template->id }})" 
                                        class="p-1.5 text-slate-500 hover:text-blue-600 bg-slate-50 hover:bg-blue-50 border border-slate-200 hover:border-blue-400 rounded-lg transition cursor-pointer"
                                        title="Test Prompt"
                                    >
                                        <span class="material-symbols-outlined text-[15px] block">science</span>
                                    </button>
                                    @if($template->is_default)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold border bg-emerald-50 text-emerald-700 border-emerald-100">Default</span>
                                    @endif
                                    <!-- Edit Button -->
                                    <button 
                                        wire:click="edit({{ $template->id }})" 
                                        class="p-1.5 text-slate-500 hover:text-[#1fa387] bg-slate-50 hover:bg-[#1fa387]/5 border border-slate-200 hover:border-[#1fa387] rounded-lg transition cursor-pointer"
                                        title="Ubah Prompt"
                                    >
                                        <span class="material-symbols-outlined text-[15px] block">edit</span>
                                    </button>
                                    
                                    <!-- Toggle Active/Inactive Status -->
                                    <button 
                                        wire:click="toggleStatus({{ $template->id }})" 
                                        class="p-1.5 text-slate-500 hover:text-slate-800 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-lg transition cursor-pointer"
                                        title="{{ $template->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                                    >
                                        <span class="material-symbols-outlined text-[15px] block">
                                            {{ $template->is_active ? 'toggle_on' : 'toggle_off' }}
                                        </span>
                                        </button>
                                    <!-- Delete Button -->
                                    <button 
                                        wire:click="requestDelete({{ $template->id }})" 
                                        class="p-1.5 text-slate-400 hover:text-rose-600 bg-slate-50 hover:bg-rose-50 border border-slate-200 hover:border-rose-500 rounded-lg transition cursor-pointer"
                                        title="Hapus Prompt"
                                    >
                                        <span class="material-symbols-outlined text-[15px] block">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-slate-400 italic">Belum ada prompt template terdaftar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($templates->hasPages())
            <div class="border-t border-slate-100 px-6 py-4">
                <div class="scale-[0.85] origin-right select-none w-full">
                    {{ $templates->onEachSide(1)->links(data: ['scrollTo' => false]) }}
                </div>
            </div>
        @endif
    </div>

    <!-- Form Add/Edit Prompt Modal -->
    @if($showFormModal)
        <div x-data x-init="document.body.style.overflow = 'hidden'; document.documentElement.style.overflow = 'hidden'; return () => { document.body.style.overflow = ''; document.documentElement.style.overflow = ''; }" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm px-4 py-6">
            <div class="w-full max-w-3xl overflow-hidden rounded-[24px] bg-white shadow-2xl text-left flex flex-col max-h-[92vh] font-sans border border-slate-200 overscroll-contain">
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-slate-100 px-8 py-5 flex-none">
                    <h2 class="text-lg font-black text-slate-850">
                        {{ $isEditing ? 'Ubah Template AI' : 'Tambah Template AI Baru' }}
                    </h2>
                    <button type="button" wire:click="closeFormModal" class="rounded-full p-2 text-slate-400 hover:bg-slate-50 hover:text-slate-700 transition cursor-pointer">
                        <span class="material-symbols-outlined text-[22px] block">close</span>
                    </button>
                </div>
                
                <!-- Modal Content (Scrollable Form Body) -->
                <form wire:submit.prevent="save" class="px-8 py-6 space-y-5 overflow-y-auto flex-1 bg-white overscroll-contain">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-slate-700">Nama Template *</label>
                            <input wire:model="name" placeholder="Contoh: Analisis Berita Utama" type="text" class="h-11 w-full rounded-xl border border-slate-200 px-4 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387]/20 transition placeholder:text-slate-400">
                            @error('name') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-slate-700">Tipe Sumber Data *</label>
                            <select wire:model="source_type" class="h-11 w-full rounded-xl border border-slate-200 px-4 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387]/20 transition">
                                <option value="article">Portal / Berita (article)</option>
                                <option value="social">Sosial Media (social)</option>
                                <option value="portal_suggestion">Saran Portal Manual (portal_suggestion)</option>
                            </select>
                            @error('source_type') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- Prompt Utama -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-slate-700">Prompt Utama (System Prompt) *</label>
                            <span class="text-[9px] font-semibold text-slate-400">System Instruction</span>
                        </div>
                        <textarea wire:model="system_prompt" placeholder="Contoh: Kamu adalah analis media yang fokus pada sentimen, risiko, dan konteks isi." rows="5" class="w-full rounded-xl border border-slate-200 p-4 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387]/20 transition font-mono leading-relaxed placeholder:text-slate-400"></textarea>
                        @error('system_prompt') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                        <p class="text-[9px] text-slate-400 font-medium leading-normal">Prompt utama ini bertindak sebagai instruksi inti pembentuk perilaku AI.</p>
                    </div>

                    <!-- User Prompt Template -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-slate-700">User Prompt Template *</label>
                            <span class="text-[9px] font-semibold text-slate-400">User Input Template</span>
                        </div>
                        <textarea wire:model="user_prompt_template" placeholder="Contoh: Analisis artikel berikut:\nJudul: {title}\nKonten: {content}" rows="7" class="w-full rounded-xl border border-slate-200 p-4 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387]/20 transition font-mono leading-relaxed placeholder:text-slate-400"></textarea>
                        @error('user_prompt_template') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                        <p class="text-[9px] text-slate-400 font-medium leading-normal">Gunakan placeholders pendukung seperti `{title}`, `{content}`, atau `{url}` yang akan diganti otomatis.</p>
                    </div>

                    <!-- Output Schema -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-slate-700">Output Schema (JSON Schema) *</label>
                            <span class="text-[9px] font-semibold text-slate-400">Response Validation</span>
                        </div>
                        <textarea wire:model="output_schema" placeholder='Contoh: {"type":"object","properties":{"summary":{"type":"string"}}}' rows="7" class="w-full rounded-xl border border-slate-200 p-4 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387]/20 transition font-mono leading-relaxed placeholder:text-slate-400"></textarea>
                        @error('output_schema') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                        <p class="text-[9px] text-slate-400 font-medium leading-normal">Wajib diisi dengan JSON Schema valid agar struktur kembalian AI konsisten terstruktur.</p>
                    </div>

                    <!-- Status dan Info -->
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-4 border border-slate-150 rounded-2xl bg-slate-50/50">
                        <label class="flex items-center gap-2.5 cursor-pointer select-none">
                            <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387]/20 w-4.5 h-4.5">
                            <div>
                                <span class="text-xs font-bold text-slate-700 block">Status Aktif</span>
                                <span class="text-[9px] text-slate-400 font-semibold block mt-0.5">Template dapat dipilih & dijalankan</span>
                            </div>
                        </label>
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[16px] text-slate-400">database</span>
                            <span class="text-[10px] text-slate-400 font-medium">Prompt & Schema tersimpan di database</span>
                        </div>
                    </div>

                    <!-- Hidden Button to support Enter key submission -->
                    <button type="submit" class="hidden"></button>
                </form>

                <!-- Modal Footer -->
                <div class="flex items-center justify-end px-8 py-5 border-t border-slate-100 bg-slate-50/70 rounded-b-[24px] flex-none">
                    <div class="flex items-center gap-3">
                        <button type="button" wire:click="closeFormModal" class="h-10 rounded-xl border border-slate-200 bg-white px-6 text-xs font-bold text-slate-700 hover:bg-slate-55 transition cursor-pointer shadow-sm">Batal</button>
                        <button type="button" wire:click="save" class="h-10 rounded-xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-6 text-xs font-bold transition cursor-pointer shadow-sm">Simpan Template</button>
                    </div>
                </div>
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
                        <h2 class="text-sm font-black text-slate-900 mt-0.5">Hapus Prompt Template?</h2>
                    </div>
                </div>
                <p class="text-xs text-slate-500 leading-relaxed">Aksi ini bersifat permanen. Seluruh isian prompt kustom akan terhapus total dari database.</p>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button wire:click="$set('confirmingDelete', false)" class="h-10 rounded-xl border border-slate-200 px-5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer">Batal</button>
                    <button wire:click="deleteConfirmed" class="h-10 rounded-xl bg-rose-600 hover:bg-rose-700 text-white px-6 text-xs font-bold transition cursor-pointer">Ya, Hapus</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Test Prompt Modal -->
    @if($showTestModal && $testingTemplateId)
        @php
            $testingTemplate = \App\Models\AiPromptTemplate::find($testingTemplateId);
        @endphp
        @if($testingTemplate)
            <div x-data x-init="document.body.style.overflow = 'hidden'; document.documentElement.style.overflow = 'hidden'; return () => { document.body.style.overflow = ''; document.documentElement.style.overflow = ''; }" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm px-4 py-6">
                <div class="w-full max-w-4xl overflow-hidden rounded-[24px] bg-white shadow-2xl text-left overscroll-contain">
                    <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-[#1fa387]">Test Prompt Template</p>
                            <h2 class="text-base font-black text-slate-900 mt-0.5">{{ $testingTemplate->name }}</h2>
                        </div>
                        <button type="button" wire:click="closeTestModal" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition cursor-pointer">
                            <span class="material-symbols-outlined text-[20px] block">close</span>
                        </button>
                    </div>

                    <div class="p-6 space-y-4 max-h-[80vh] overflow-y-auto pr-1">
                        <div class="grid gap-4 lg:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-slate-700">Nama Portal</label>
                                <input wire:model="test_name" type="text" placeholder="Contoh: Arusbawah.co" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <label class="mb-1.5 block text-xs font-bold text-slate-700">Domain</label>
                                    <input wire:model="test_domain" type="text" placeholder="Contoh: arusbawah.co" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-bold text-slate-700">Base URL</label>
                                    <input wire:model="test_base_url" type="url" placeholder="Contoh: https://arusbawah.co" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-bold text-slate-700">HTML Mentah</label>
                                    <textarea wire:model="test_article_url" rows="4" placeholder="Tempel HTML mentah di sini..." class="w-full rounded-xl border border-slate-200 px-3.5 py-3 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition font-mono"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-bold text-slate-700">Prompt Ter-render</label>
                                    <textarea readonly rows="8" class="w-full rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-xs font-semibold text-slate-800 outline-none font-mono">{{ $test_rendered_prompt }}</textarea>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-slate-700">Output AI</label>
                                <textarea readonly rows="10" class="w-full rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-xs font-semibold text-slate-800 outline-none font-mono">{{ $test_raw_output ?? $test_error ?? '' }}</textarea>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                            <button type="button" wire:click="closeTestModal" class="h-10 rounded-xl border border-slate-200 px-5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer">Tutup</button>
                            <button type="button" wire:click="runTemplateTest" class="h-10 rounded-xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-6 text-xs font-bold transition cursor-pointer">Jalankan Test</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif

    @if($showTrashModal)
        <style>
            body, html {
                overflow: hidden !important;
            }
        </style>
        <template x-teleport="body" wire:key="ai-prompt-templates-trash-modal-template">
        <div class="fixed inset-0 z-[999] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm px-4 py-6">
            <div class="w-full max-w-5xl overflow-hidden rounded-[24px] bg-white shadow-2xl text-left flex flex-col max-h-[92vh] font-sans border border-slate-200 overscroll-contain">
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-slate-100 px-8 py-5 flex-none">
                    <h2 class="text-lg font-black text-slate-850">
                        Daftar Prompt Template Dihapus
                    </h2>
                    <button type="button" wire:click="closeTrashModal" class="rounded-full p-2 text-slate-400 hover:bg-slate-50 hover:text-slate-700 transition cursor-pointer">
                        <span class="material-symbols-outlined text-[22px] block">close</span>
                    </button>
                </div>

                <!-- Modal Content (Scrollable) -->
                <div class="px-8 py-6 space-y-5 overflow-y-auto flex-1 bg-white overscroll-contain">
                    <!-- Metadata Header Row -->
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 border border-slate-200">
                                <span class="material-symbols-outlined text-[20px] block">delete_sweep</span>
                            </span>
                            <div>
                                <h3 class="text-xs font-black text-slate-800">Tempat Sampah Prompt Template</h3>
                                <p class="text-[9px] text-slate-400 font-medium mt-0.5">
                                    Aksi pemulihan (Kembalikan) atau hapus permanen dapat dilakukan langsung pada tabel di bawah.
                                </p>
                            </div>
                        </div>
                    </div>

                    @if($trashTemplates->count() > 0)
                        <!-- Premium Table Containerized -->
                        <div class="overflow-hidden border border-slate-200 rounded-2xl bg-white shadow-sm">
                            <table class="w-full border-collapse text-xs text-slate-700">
                                <thead class="bg-slate-50/75 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider text-left">
                                    <tr>
                                        <th class="px-4 py-3.5 w-12 text-left font-bold">No</th>
                                        <th class="px-4 py-3.5 font-bold">Nama Template</th>
                                        <th class="px-4 py-3.5 font-bold">Tipe Sumber</th>
                                        <th class="px-4 py-3.5 font-bold">Waktu Dihapus</th>
                                        <th class="px-4 py-3.5 text-right w-64 font-bold">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($trashTemplates as $trashTemplate)
                                        <tr wire:key="trash-template-row-{{ $trashTemplate->id }}" class="hover:bg-slate-50/30 transition">
                                            <td class="px-4 py-3 font-bold text-slate-400">{{ $loop->iteration }}</td>
                                            <td class="px-4 py-3 font-bold text-slate-900">{{ $trashTemplate->name }}</td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-[10px] font-bold border {{ $trashTemplate->source_type === 'article' ? 'bg-blue-50 text-blue-700 border-blue-100' : 'bg-purple-50 text-purple-700 border-purple-100' }}">
                                                    {{ $trashTemplate->source_type === 'article' ? 'Portal Berita' : 'Media Sosial' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 font-semibold text-slate-400">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="material-symbols-outlined text-[14px] text-rose-400">calendar_today</span>
                                                    <span>{{ $trashTemplate->deleted_at->format('d M Y, H:i') }}</span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <div class="flex items-center gap-2 justify-end whitespace-nowrap">
                                                    <!-- Restore Action -->
                                                    <button 
                                                        wire:click="confirmRestoreTemplate({{ $trashTemplate->id }})" 
                                                        wire:loading.attr="disabled"
                                                        wire:target="confirmRestoreTemplate({{ $trashTemplate->id }})"
                                                        class="inline-flex h-8 items-center justify-center gap-1 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200/40 hover:border-emerald-300 px-3.5 text-[11px] font-black transition cursor-pointer disabled:opacity-50"
                                                        title="Kembalikan Template ke Daftar Aktif"
                                                    >
                                                        <span class="material-symbols-outlined text-[15px] block">restore_from_trash</span>
                                                        <span>Kembalikan</span>
                                                    </button>
 
                                                    <!-- Permanent Delete Action -->
                                                    <button 
                                                        wire:click="confirmForceDeleteTemplate({{ $trashTemplate->id }})" 
                                                        wire:loading.attr="disabled"
                                                        wire:target="confirmForceDeleteTemplate({{ $trashTemplate->id }})"
                                                        class="inline-flex h-8 items-center justify-center gap-1 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200/40 hover:border-rose-300 px-3.5 text-[11px] font-black transition cursor-pointer disabled:opacity-50"
                                                        title="Hapus Permanen"
                                                    >
                                                        <span class="material-symbols-outlined text-[15px] block">delete_forever</span>
                                                        <span>Hapus Permanen</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Trash Pagination -->
                        @if($trashTemplates->hasPages())
                            <div class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-4 bg-slate-50/50 p-4 border border-slate-200 rounded-2xl">
                                <div class="text-xs font-bold text-slate-500">
                                    Menampilkan <span class="text-slate-800 font-black">{{ $trashTemplates->firstItem() }}</span> - <span class="text-slate-800 font-black">{{ $trashTemplates->lastItem() }}</span> dari <span class="text-slate-800 font-black">{{ $trashTemplates->total() }}</span> template
                                </div>
                                <div class="flex items-center gap-1.5">
                                    @if ($trashTemplates->onFirstPage())
                                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-slate-50 border border-slate-100 text-slate-300 cursor-not-allowed">
                                            <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                                        </span>
                                    @else
                                        <button wire:click="gotoPage({{ $trashTemplates->currentPage() - 1 }}, 'trashPage')" wire:loading.attr="disabled" class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 active:scale-95 transition cursor-pointer shadow-sm">
                                            <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                                        </button>
                                    @endif

                                    @foreach ($trashTemplates->getUrlRange(max(1, $trashTemplates->currentPage() - 1), min($trashTemplates->lastPage(), $trashTemplates->currentPage() + 1)) as $page => $url)
                                        @if ($page == $trashTemplates->currentPage())
                                            <span class="inline-flex h-9 min-w-9 px-3 items-center justify-center rounded-xl bg-[#1fa387] text-white text-xs font-black shadow-sm border border-[#1fa387]">
                                                {{ $page }}
                                            </span>
                                        @else
                                            <button wire:click="gotoPage({{ $page }}, 'trashPage')" wire:loading.attr="disabled" class="inline-flex h-9 min-w-9 px-3 items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-bold active:scale-95 transition cursor-pointer shadow-sm">
                                                {{ $page }}
                                            </button>
                                        @endif
                                    @endforeach

                                    @if ($trashTemplates->hasMorePages())
                                        <button wire:click="gotoPage({{ $trashTemplates->currentPage() + 1 }}, 'trashPage')" wire:loading.attr="disabled" class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 active:scale-95 transition cursor-pointer shadow-sm">
                                            <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                                        </button>
                                    @else
                                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-slate-50 border border-slate-100 text-slate-300 cursor-not-allowed">
                                            <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @else
                        <!-- Empty Premium State -->
                        <div class="flex flex-col items-center justify-center py-16 px-4 text-center">
                            <span class="w-16 h-16 rounded-full bg-slate-50 flex items-center justify-center text-slate-300 border border-slate-100/80 shadow-inner">
                                <span class="material-symbols-outlined text-[36px] block">archive</span>
                            </span>
                            <h3 class="text-sm font-black text-slate-700 mt-4">Tempat Sampah Bersih</h3>
                            <p class="text-[11px] text-slate-400 mt-1 max-w-xs leading-normal">Tidak ada data prompt template yang terdaftar di tempat sampah saat ini.</p>
                        </div>
                    @endif
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-end px-8 py-5 border-t border-slate-100 bg-slate-50/70 rounded-b-[24px] flex-none">
                    <button type="button" wire:click="closeTrashModal" class="h-10 rounded-xl border border-slate-200 bg-white px-6 text-xs font-bold text-slate-700 hover:bg-slate-55 transition cursor-pointer shadow-sm">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
        </template>
    @endif

    @if($confirmingRestoreTemplateId)
        @php
            $restoreTemplateTarget = \App\Models\AiPromptTemplate::onlyTrashed()->find($confirmingRestoreTemplateId);
        @endphp
        <style>
            body, html {
                overflow: hidden !important;
            }
        </style>
        <template x-teleport="body" wire:key="ai-prompt-template-confirm-restore-modal-template">
        <div wire:key="ai-prompt-template-confirm-restore-modal" class="fixed inset-0 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm px-4 py-6" style="z-index: 1050;">
            <div class="w-full max-w-sm rounded-[24px] bg-white p-6 shadow-2xl text-left space-y-4 overscroll-contain border border-slate-100/80">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600 border border-emerald-100">
                        <span class="material-symbols-outlined text-[20px] block">restore_from_trash</span>
                    </span>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-500">Konfirmasi Pemulihan</p>
                        <h2 class="text-sm font-black text-slate-900 mt-0.5">Kembalikan Template?</h2>
                    </div>
                </div>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Apakah Anda yakin ingin mengembalikan template <strong class="text-slate-800">{{ $restoreTemplateTarget?->name }}</strong> ke daftar aktif?
                </p>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button wire:click="cancelRestore" wire:loading.attr="disabled" wire:target="restoreTemplateConfirmed" class="h-10 rounded-xl border border-slate-200 px-5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer disabled:opacity-50">Batal</button>
                    <button wire:click="restoreTemplateConfirmed" wire:loading.attr="disabled" wire:target="restoreTemplateConfirmed" class="h-10 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-6 text-xs font-bold transition cursor-pointer disabled:opacity-50 disabled:cursor-wait">Ya, Kembalikan</button>
                </div>
            </div>
        </div>
        </template>
    @endif

    @if($confirmingForceDeleteTemplateId)
        @php
            $deleteTemplateTarget = \App\Models\AiPromptTemplate::onlyTrashed()->find($confirmingForceDeleteTemplateId);
        @endphp
        <style>
            body, html {
                overflow: hidden !important;
            }
        </style>
        <template x-teleport="body" wire:key="ai-prompt-template-confirm-force-delete-modal-template">
        <div wire:key="ai-prompt-template-confirm-force-delete-modal" class="fixed inset-0 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm px-4 py-6" style="z-index: 1050;">
            <div class="w-full max-w-sm rounded-[24px] bg-white p-6 shadow-2xl text-left space-y-4 overscroll-contain border border-slate-100/80">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-full bg-rose-50 flex items-center justify-center text-rose-600 border border-rose-100">
                        <span class="material-symbols-outlined text-[20px] block">delete_forever</span>
                    </span>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-rose-500">Hapus Permanen</p>
                        <h2 class="text-sm font-black text-slate-900 mt-0.5">Hapus Secara Permanen?</h2>
                    </div>
                </div>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Tindakan ini tidak dapat dibatalkan. Template <strong class="text-slate-800">{{ $deleteTemplateTarget?->name }}</strong> akan dihapus selamanya dari sistem.
                </p>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button wire:click="cancelForceDelete" wire:loading.attr="disabled" wire:target="forceDeleteTemplateConfirmed" class="h-10 rounded-xl border border-slate-200 px-5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer disabled:opacity-50">Batal</button>
                    <button wire:click="forceDeleteTemplateConfirmed" wire:loading.attr="disabled" wire:target="forceDeleteTemplateConfirmed" class="h-10 rounded-xl bg-rose-600 hover:bg-rose-700 text-white px-6 text-xs font-bold transition cursor-pointer disabled:opacity-50 disabled:cursor-wait">Ya, Hapus</button>
                </div>
            </div>
        </div>
        </template>
    @endif
</div>
