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

    <!-- PROYEK KLIEN SECTION -->
    <div class="mb-6">

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">

            <!-- Assign New Project -->
            <div class="p-5 border-b border-slate-100 bg-slate-50 flex flex-col gap-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-800">Tambahkan Proyek</h3>
                    <p class="text-slate-500 text-xs mt-0.5">Cari proyek yang ingin diberikan akses ke klien.</p>
                </div>

                @if($availableProjects->isEmpty())
                    <div class="bg-white rounded-xl border border-slate-200 p-4 text-center text-sm text-slate-500">
                        Tidak ada proyek tersedia untuk ditambahkan.
                    </div>
                @else
                    <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                        <div class="flex-1 w-full" id="project-select-container">
                            <div wire:ignore>
                                <select id="project-select" class="w-full" multiple="multiple">
                                    @foreach($availableProjects as $proj)
                                        <option value="{{ $proj->id }}"
                                                data-status="{{ $proj->is_active ? 'Aktif' : 'Nonaktif' }}"
                                                data-package="{{ $proj->package ? $proj->package->name : 'Tanpa Paket' }}">
                                            {{ $proj->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex items-center justify-between mt-1 min-h-[20px]">
                                <div>
                                    @error('selectedProjectIds') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
                                </div>
                                @if(count($selectedProjectIds) > 0)
                                    <div class="text-xs font-bold text-[#1fa387]">
                                        {{ count($selectedProjectIds) }} proyek dipilih
                                    </div>
                                @endif
                            </div>
                        </div>

                        <button type="button"
                                wire:click="assignProject"
                                class="w-full sm:w-auto px-6 py-2.5 min-h-[44px] text-sm font-bold text-white bg-[#1fa387] hover:bg-[#178a71] rounded-xl transition-colors whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center justify-center"
                                wire:loading.attr="disabled"
                                {{ empty($selectedProjectIds) ? 'disabled' : '' }}>
                            <span wire:loading.remove wire:target="assignProject">Tambahkan</span>
                            <span wire:loading wire:target="assignProject">Memproses...</span>
                        </button>
                    </div>
                @endif
            </div>

            @assets
                <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
                <style>
                    .select2-container {
                        font-family: inherit !important;
                    }
                    /* Custom style to match the modern look */
                    .select2-container .select2-selection--multiple {
                        min-height: 44px !important;
                        border-radius: 0.75rem !important;
                        border-color: #e2e8f0 !important;
                        display: flex !important;
                        align-items: center !important;
                        padding: 4px 8px !important;
                    }
                    .select2-container--default .select2-selection--multiple .select2-selection__choice {
                        background-color: #f1f5f9 !important;
                        border: 1px solid #e2e8f0 !important;
                        border-radius: 9999px !important;
                        color: #0f172a !important;
                        font-size: 0.8125rem !important;
                        font-weight: 500 !important;
                        padding: 2px 10px 2px 8px !important;
                        margin-top: 4px !important;
                        display: inline-flex !important;
                        flex-direction: row-reverse !important;
                        align-items: center !important;
                        gap: 4px !important;
                    }
                    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
                        color: #64748b !important;
                        margin: 0 !important;
                        border: none !important;
                        position: static !important;
                        font-weight: 400 !important;
                        font-size: 1.125rem !important;
                        line-height: 1 !important;
                        display: inline-flex !important;
                        align-items: center !important;
                        justify-content: center !important;
                        width: 18px !important;
                        height: 18px !important;
                    }
                    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
                        background-color: transparent !important;
                        color: #ef4444 !important;
                    }
                    .select2-container--default .select2-selection--multiple .select2-selection__clear {
                        margin-top: 0 !important;
                        margin-right: 8px !important;
                        display: flex !important;
                        align-items: center !important;
                        font-size: 1.25rem !important;
                    }
                    .select2-dropdown {
                        border-color: #e2e8f0 !important;
                        border-radius: 0.75rem !important;
                        box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1) !important;
                        font-size: 0.875rem !important;
                        padding: 4px !important;
                    }
                    .select2-selection__rendered {
                        display: flex !important;
                        align-items: center !important;
                        flex-wrap: wrap !important;
                        width: 100% !important;
                        min-height: 42px !important;
                        padding: 0 !important;
                    }
                    .select2-search--inline {
                        display: flex !important;
                        align-items: center !important;
                        min-height: 34px !important;
                    }
                    .select2-search--inline .select2-search__field {
                        margin: 0 !important;
                        padding: 0 6px !important;
                        height: 34px !important;
                        line-height: 34px !important;
                    }
                    .select2-search__field {
                        color: #0f172a !important;
                        margin-top: 0 !important;
                    }
                    .select2-container--default .select2-results__option--highlighted[aria-selected] {
                        background-color: #f8fafc !important;
                        color: #0f172a !important;
                        border-radius: 0.5rem !important;
                    }
                    .select2-results__option {
                        padding: 6px 12px !important;
                        margin-bottom: 2px !important;
                    }
                </style>
                <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
                <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
            @endassets

            @script
                <script>
                    const initSelect2 = () => {
                        const el = $($wire.$el).find('#project-select');

                        if (el.length > 0) {
                            // Destroy existing Select2 instance if it exists
                            if (el.hasClass('select2-hidden-accessible')) {
                                el.select2('destroy');
                            }
                            const formatProject = (project) => {
                                if (!project.id) return project.text;

                                const el = $(project.element);
                                const status = el.data('status');
                                const pkg = el.data('package');
                                const isActive = status === 'Aktif';

                                const statusBadge = isActive
                                    ? `<span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-md whitespace-nowrap">Aktif</span>`
                                    : `<span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-500 bg-slate-100 border border-slate-200 rounded-md whitespace-nowrap">Nonaktif</span>`;

                                return $(`
                                    <div class="flex items-center justify-between gap-4 py-1">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-slate-800 text-sm leading-tight">${project.text}</span>
                                            <span class="text-xs text-slate-500 mt-0.5">${pkg}</span>
                                        </div>
                                        <div>
                                            ${statusBadge}
                                        </div>
                                    </div>
                                `);
                            };

                            const formatSelection = (project) => {
                                return project.text;
                            };

                            el.select2({
                                placeholder: 'Cari & pilih proyek...',
                                width: '100%',
                                multiple: true,
                                allowClear: true,
                                templateResult: formatProject,
                                templateSelection: formatSelection
                            });

                            el.off('change').on('change', function(e) {
                                $wire.$set('selectedProjectIds', $(this).val() || []);
                            });

                            // sync value on init
                            el.val($wire.$get('selectedProjectIds')).trigger('change.select2');
                        }
                    };

                    initSelect2();

                    Livewire.hook('morph.updated', ({ el, component }) => {
                        // Re-initialize Select2 when Livewire DOM morphs to ensure dropdown options are synced
                        if (component.el === $wire.$el) {
                            initSelect2();
                        }
                    });

                    $wire.$on('admin-toast', () => {
                        const el = $($wire.$el).find('#project-select');
                        if (el.hasClass('select2-hidden-accessible')) {
                            el.val(null).trigger('change.select2');
                        }
                    });
                </script>
            @endscript

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
                        <div class="text-xs text-slate-500">Klien dapat menonaktifkan/menghapus proyeknya (hanya <i>soft-delete</i>, bukan hapus permanen).</div>
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

    @include('components.admin-toast')
</div>
