<div class="p-6 max-w-4xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.clients') }}" wire:navigate class="text-slate-400 hover:text-[#1fa387] transition-colors">
                <span class="material-symbols-outlined text-[20px]">arrow_back</span>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Pengaturan Klien: {{ $client->name }}</h1>
                <p class="text-slate-500 text-sm mt-1">Atur hak akses, batas sumber daya, dan ketersediaan paket.</p>
            </div>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="mb-6 p-4 rounded-xl bg-green-50 text-green-700 border border-green-200 flex items-center gap-3">
            <span class="material-symbols-outlined text-[20px]">check_circle</span>
            <span class="text-sm font-medium">{{ session('message') }}</span>
        </div>
    @endif

    <form wire:submit.prevent="saveSettings" class="space-y-6">
        
        <!-- Izin Dasar -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-slate-50">
                <h2 class="text-sm font-bold text-slate-800">Hak Akses Proyek</h2>
            </div>
            <div class="p-6 space-y-4">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input wire:model="can_create_projects" type="checkbox" class="w-5 h-5 text-[#1fa387] rounded border-slate-300 focus:ring-[#1fa387]">
                    <div>
                        <div class="text-sm font-bold text-slate-800">Izinkan Membuat Proyek</div>
                        <div class="text-xs text-slate-500">Klien dapat membuat proyek baru sendiri.</div>
                    </div>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input wire:model="can_edit_projects" type="checkbox" class="w-5 h-5 text-[#1fa387] rounded border-slate-300 focus:ring-[#1fa387]">
                    <div>
                        <div class="text-sm font-bold text-slate-800">Izinkan Mengedit Proyek</div>
                        <div class="text-xs text-slate-500">Klien dapat mengedit nama dan kata kunci proyeknya.</div>
                    </div>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input wire:model="can_delete_projects" type="checkbox" class="w-5 h-5 text-red-500 rounded border-slate-300 focus:ring-red-500">
                    <div>
                        <div class="text-sm font-bold text-slate-800">Izinkan Menghapus Proyek</div>
                        <div class="text-xs text-slate-500">Klien dapat menonaktifkan/menghapus proyeknya.</div>
                    </div>
                </label>
            </div>
        </div>

        <!-- Batas Sumber Daya -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-slate-50">
                <h2 class="text-sm font-bold text-slate-800">Batas Sumber Daya Khusus Klien</h2>
                <p class="text-xs text-slate-500 mt-1">Kosongkan jika ingin menggunakan batas default dari paket.</p>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-1.5">
                    <label class="text-sm font-bold text-slate-800">Maksimal Proyek</label>
                    <input wire:model="max_projects" type="number" min="1" placeholder="Batas jumlah proyek" class="w-full px-4 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#1fa387]/20 focus:border-[#1fa387]">
                    @error('max_projects') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="space-y-1.5">
                    <label class="text-sm font-bold text-slate-800">Maksimal Kata Kunci / Proyek</label>
                    <input wire:model="max_keywords_per_project" type="number" min="1" placeholder="Batas kata kunci per proyek" class="w-full px-4 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#1fa387]/20 focus:border-[#1fa387]">
                    @error('max_keywords_per_project') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <!-- Paket yang Diizinkan -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-slate-50">
                <h2 class="text-sm font-bold text-slate-800">Paket yang Tersedia (Whitelist)</h2>
                <p class="text-xs text-slate-500 mt-1">Pilih paket mana saja yang boleh dipilih oleh klien ini saat membuat proyek.</p>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach($packages as $package)
                    <label class="flex items-start gap-3 p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition-colors {{ in_array($package->id, $allowedPackages) ? 'border-[#1fa387] bg-[#1fa387]/5' : '' }}">
                        <input wire:model="allowedPackages" type="checkbox" value="{{ $package->id }}" class="mt-1 w-5 h-5 text-[#1fa387] rounded border-slate-300 focus:ring-[#1fa387]">
                        <div>
                            <div class="text-sm font-bold text-slate-800">{{ $package->name }}</div>
                            <div class="text-xs font-medium text-[#1fa387] mt-0.5">Rp {{ number_format($package->price, 0, ',', '.') }}</div>
                            <div class="text-xs text-slate-500 mt-1">
                                Limit Proyek: {{ $package->max_projects ?? '∞' }} | Limit KW: {{ $package->max_keywords_per_project ?? '∞' }}
                            </div>
                        </div>
                    </label>
                @endforeach
            </div>
            @error('allowedPackages') <p class="text-red-500 text-xs mt-1 px-6 pb-4">{{ $message }}</p> @enderror
        </div>

        <div class="flex justify-end gap-3">
            <button type="submit" class="px-6 py-2.5 text-sm font-bold text-white bg-[#1fa387] hover:bg-[#178a71] rounded-xl transition-colors flex items-center gap-2">
                <span wire:loading.remove wire:target="saveSettings" class="material-symbols-outlined text-[18px]">save</span>
                <span wire:loading wire:target="saveSettings" class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span>
                <span>Simpan Pengaturan</span>
            </button>
        </div>

    </form>

    <!-- PROYEK KLIEN SECTION -->
    <div class="mt-10 mb-10">
        <div class="mb-4">
            <h2 class="text-xl font-bold text-slate-900">Proyek Klien</h2>
            <p class="text-slate-500 text-sm mt-1">Kelola proyek mana saja yang dapat diakses oleh klien ini.</p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            
            <!-- Assign New Project -->
            <div class="p-4 border-b border-slate-100 bg-slate-50 flex flex-col sm:flex-row sm:items-center gap-4">
                <div class="flex-1 w-full" id="project-select-container">
                    <div wire:ignore>
                        <select id="project-select" class="w-full" multiple="multiple">
                            @foreach($availableProjects as $proj)
                                <option value="{{ $proj->id }}">
                                    {{ $proj->name }} ({{ $proj->is_active ? 'Aktif' : 'Nonaktif' }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @error('selectedProjectIds') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                
                <button type="button" wire:click="assignProject" class="px-6 py-2 text-sm font-bold text-white bg-[#1fa387] hover:bg-[#178a71] rounded-xl transition-colors whitespace-nowrap disabled:opacity-50" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="assignProject">Tambahkan</span>
                    <span wire:loading wire:target="assignProject">Memproses...</span>
                </button>
            </div>
            
            @push('styles')
                <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
                <style>
                    /* Custom style to match the modern look */
                    .select2-container .select2-selection--multiple {
                        min-height: 38px !important;
                        border-radius: 0.75rem !important;
                        border-color: #e2e8f0 !important;
                        display: flex !important;
                        align-items: center !important;
                        padding: 2px 4px !important;
                    }
                    .select2-container--default .select2-selection--multiple .select2-selection__choice {
                        background-color: #f1f5f9 !important;
                        border: 1px solid #e2e8f0 !important;
                        border-radius: 0.5rem !important;
                        color: #0f172a !important;
                        font-size: 0.8125rem !important;
                        padding: 2px 8px !important;
                        margin-top: 4px !important;
                    }
                    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
                        color: #64748b !important;
                        margin-right: 4px !important;
                        border-right: none !important;
                    }
                    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
                        background-color: transparent !important;
                        color: #ef4444 !important;
                    }
                    .select2-dropdown {
                        border-color: #e2e8f0 !important;
                        border-radius: 0.75rem !important;
                        box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1) !important;
                        font-size: 0.875rem !important;
                    }
                </style>
            @endpush

            @push('scripts')
                <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
                <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
                <script>
                    document.addEventListener('livewire:initialized', () => {
                        const initSelect2 = () => {
                            if (typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined') {
                                jQuery('#project-select').select2({
                                    placeholder: 'Pilih satu atau lebih proyek...',
                                    width: '100%',
                                    multiple: true,
                                    allowClear: true
                                }).off('change').on('change', function(e) {
                                    let values = jQuery(this).val() || [];
                                    @this.set('selectedProjectIds', values);
                                });
                                // sync value
                                jQuery('#project-select').val(@this.selectedProjectIds).trigger('change.select2');
                            }
                        };
                        
                        initSelect2();
                        
                        Livewire.hook('morph.updated', ({ el, component }) => {
                            // Only re-init if the select2 container is missing or destroyed
                            if (!jQuery('#project-select').hasClass('select2-hidden-accessible')) {
                                initSelect2();
                            }
                        });
                        
                        // Clear selection when successfully assigned
                        Livewire.on('admin-toast', () => {
                            if (jQuery('#project-select').hasClass('select2-hidden-accessible')) {
                                jQuery('#project-select').val(null).trigger('change.select2');
                            }
                        });
                    });
                </script>
            @endpush

            <!-- Assigned Projects List -->
            <div class="divide-y divide-slate-100">
                @forelse($assignedProjects as $ap)
                    <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-slate-50/50 transition-colors">
                        <div>
                            <div class="font-bold text-slate-800 text-sm">{{ $ap->name }}</div>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-xs text-slate-500">{{ $ap->package ? $ap->package->name : 'Tanpa Paket' }}</span>
                                <span class="text-slate-300">•</span>
                                <span class="text-xs font-medium {{ $ap->is_active ? 'text-emerald-600' : 'text-slate-400' }}">
                                    {{ $ap->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>
                        </div>
                        
                        <div>
                            @if($confirmDetachProjectId === $ap->id)
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-slate-500 mr-2">Lepas proyek dari klien?</span>
                                    <button type="button" wire:click="detachProject({{ $ap->id }})" class="px-3 py-1.5 text-xs font-bold text-white bg-red-500 hover:bg-red-600 rounded-lg transition-colors">Ya, Lepas</button>
                                    <button type="button" wire:click="$set('confirmDetachProjectId', null)" class="px-3 py-1.5 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">Batal</button>
                                </div>
                            @else
                                <button type="button" wire:click="$set('confirmDetachProjectId', {{ $ap->id }})" class="px-4 py-1.5 text-xs font-bold text-red-500 hover:bg-red-50 rounded-lg transition-colors border border-transparent hover:border-red-100">
                                    Lepas
                                </button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-slate-500 text-sm">
                        Belum ada proyek yang terhubung ke klien ini.
                    </div>
                @endforelse
            </div>
            
        </div>
    </div>

    @include('components.admin-toast')
</div>
