<div class="mx-auto w-full max-w-7xl space-y-6 font-sans">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-200 pb-5">
        <div class="text-left">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Panel Administrator</p>
            <h1 class="text-2xl font-black text-slate-900 mt-1">AI Prompt Templates</h1>
            <p class="text-xs text-slate-500 mt-1">Atur instruksi kustom model AI untuk memproses sentiment, risiko, dan reach.</p>
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
            <p class="text-[10px] text-slate-400 mt-0.5">Tentukan bagaimana respon JSON dan petunjuk analisis diekstrak.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-xs text-slate-700">
                <thead class="bg-slate-50/75 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3.5 text-left font-bold w-12">No</th>
                        <th class="px-4 py-3.5 text-left font-bold">Nama Template</th>
                        <th class="px-4 py-3.5 text-left font-bold">Tipe Sumber</th>
                        <th class="px-4 py-3.5 text-left font-bold">Status</th>
                        <th class="px-4 py-3.5 text-left font-bold">Default</th>
                        <th class="px-4 py-3.5 text-right font-bold w-36">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($templates as $template)
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-4 py-3 text-slate-500 font-semibold">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3 font-bold text-slate-900">{{ $template->name }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-[10px] font-bold border {{ $template->source_type === 'article' ? 'bg-blue-50 text-blue-700 border-blue-100' : 'bg-purple-50 text-purple-700 border-purple-100' }}">
                                    {{ $template->source_type === 'article' ? 'Portal Berita' : 'Media Sosial' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 font-bold {{ $template->is_active ? 'text-emerald-600' : 'text-slate-400' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $template->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                    {{ $template->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if($template->is_default)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[9px] font-bold bg-teal-50 text-teal-700 border border-teal-200">
                                        DEFAULT ACTIVE
                                    </span>
                                @else
                                    <button 
                                        wire:click="setDefault({{ $template->id }})" 
                                        class="text-[9px] font-bold text-slate-400 hover:text-[#1fa387] border border-slate-200 hover:border-[#1fa387] bg-slate-50 px-2 py-0.5 rounded-md transition cursor-pointer"
                                    >
                                        SET DEFAULT
                                    </button>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1.5">
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
                                    @if(!$template->is_default)
                                        <button 
                                            wire:click="requestDelete({{ $template->id }})" 
                                            class="p-1.5 text-slate-400 hover:text-rose-600 bg-slate-50 hover:bg-rose-50 border border-slate-200 hover:border-rose-500 rounded-lg transition cursor-pointer"
                                            title="Hapus Prompt"
                                        >
                                            <span class="material-symbols-outlined text-[15px] block">delete</span>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-400 italic">Belum ada prompt template terdaftar.</td>
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
        <div x-data x-init="document.body.style.overflow = 'hidden'; document.documentElement.style.overflow = 'hidden'; return () => { document.body.style.overflow = ''; document.documentElement.style.overflow = ''; }" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm px-4 py-6">
            <div class="w-full max-w-2xl overflow-hidden rounded-[24px] bg-white shadow-2xl text-left overscroll-contain">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-[#1fa387]">Instruksi Kustom AI</p>
                        <h2 class="text-base font-black text-slate-900 mt-0.5">{{ $isEditing ? 'Ubah Template Prompt' : 'Tambah Template Prompt Baru' }}</h2>
                    </div>
                    <button type="button" wire:click="closeFormModal" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition cursor-pointer">
                        <span class="material-symbols-outlined text-[20px] block">close</span>
                    </button>
                </div>
                
                <form wire:submit.prevent="save" class="p-6 space-y-4 max-h-[75vh] overflow-y-auto pr-1">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-slate-700">Nama Template</label>
                            <input wire:model="name" placeholder="Contoh: Analisis Berita Default" type="text" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                            @error('name') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-slate-700">Tipe Sumber Data</label>
                            <select wire:model="source_type" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                                <option value="article">Portal / Berita</option>
                                <option value="social">Sosial Media</option>
                            </select>
                            @error('source_type') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-bold text-slate-700">System Prompt</label>
                        <textarea wire:model="system_prompt" placeholder="Contoh: Anda adalah analis berita cerdas..." rows="4" class="w-full rounded-xl border border-slate-200 p-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition font-mono"></textarea>
                        @error('system_prompt') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-bold text-slate-700">User Prompt Template</label>
                        <textarea wire:model="user_prompt_template" placeholder="Contoh: Analisis artikel ini: Title: {title}, Content: {content}..." rows="4" class="w-full rounded-xl border border-slate-200 p-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition font-mono"></textarea>
                        @error('user_prompt_template') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-bold text-slate-700">Output JSON Schema (Opsional)</label>
                        <textarea wire:model="output_schema" placeholder='Contoh: {"type": "object", "properties": {"sentiment": {"type": "string"}}}' rows="4" class="w-full rounded-xl border border-slate-200 p-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition font-mono"></textarea>
                        @error('output_schema') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 pt-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387]/20 w-4 h-4">
                            <span class="text-xs font-bold text-slate-700">Status Aktif</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="is_default" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387]/20 w-4 h-4">
                            <span class="text-xs font-bold text-slate-700">Jadikan Default Tipe Ini</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <button type="button" wire:click="closeFormModal" class="h-10 rounded-xl border border-slate-200 px-5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer">Batal</button>
                        <button type="submit" class="h-10 rounded-xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-6 text-xs font-bold transition cursor-pointer">Simpan Template</button>
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
</div>
