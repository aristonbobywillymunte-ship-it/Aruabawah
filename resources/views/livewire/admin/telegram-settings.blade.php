<div class="mx-auto w-full max-w-7xl space-y-6 font-sans">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-200 pb-5">
        <div class="text-left">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Panel Administrator</p>
            <h1 class="text-2xl font-black text-slate-900 mt-1">Telegram Settings</h1>
            <p class="text-xs text-slate-500 mt-1">Konfigurasi bot token dan chat ID tujuan notifikasi krisis atau ancaman tinggi.</p>
        </div>

        <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto">
            <button 
                wire:click="openTestModal" 
                class="inline-flex h-10 w-full sm:w-auto items-center justify-center gap-1.5 rounded-2xl border border-slate-200 hover:border-slate-300 bg-white text-slate-700 px-5 text-xs font-bold transition shadow-sm cursor-pointer whitespace-nowrap"
            >
                <span class="material-symbols-outlined text-[18px]">send</span>
                <span>Uji Kirim Pesan</span>
            </button>
            <button 
                wire:click="createRecipient" 
                class="inline-flex h-10 w-full sm:w-auto items-center justify-center gap-1.5 rounded-2xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-5 text-xs font-bold transition shadow-sm cursor-pointer whitespace-nowrap"
            >
                <span class="material-symbols-outlined text-[18px]">add</span>
                <span>Tambah Penerima</span>
            </button>
        </div>
    </div>
    <!-- Global Telegram Settings & Info Card -->
    <div class="grid gap-6 md:grid-cols-3">
        <!-- Configuration Card (Span 2) -->
        <form wire:submit.prevent="saveGlobalSettings" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:col-span-2 text-left space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-800">Konfigurasi Bot Utama</h2>
                <span class="inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                    {{ $is_active ? 'Bot Aktif' : 'Bot Nonaktif' }}
                </span>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs font-bold text-slate-700">Bot Token Telegram (Masked)</label>
                    <input 
                        wire:model="bot_token" 
                        type="password" 
                        placeholder="1234567890:ABCdefGhIJKlmNoPQRsTUVwxyZ" 
                        autocomplete="off"
                        autocapitalize="off"
                        autocorrect="off"
                        spellcheck="false"
                        inputmode="text"
                        class="h-10 w-full rounded-xl border border-slate-200 px-4 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition"
                    />
                    <p class="mt-1 text-[10px] text-slate-400">Tempel token asli Telegram tanpa spasi atau karakter asing.</p>
                    @error('bot_token') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold text-slate-700">Default Chat/Group ID</label>
                    <input 
                        wire:model="default_chat_id" 
                        type="text" 
                        placeholder="Contoh: -100123456789" 
                        autocomplete="off"
                        autocapitalize="off"
                        autocorrect="off"
                        spellcheck="false"
                        inputmode="numeric"
                        class="h-10 w-full rounded-xl border border-slate-200 px-4 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition font-mono"
                    />
                    @error('default_chat_id') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387]/20 w-4 h-4">
                    <span class="text-xs font-bold text-slate-700">Aktifkan Pengiriman Notifikasi</span>
                </label>
                <button 
                    type="submit" 
                    class="h-9 rounded-xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-5 text-xs font-bold transition shadow-sm cursor-pointer whitespace-nowrap"
                >
                    Simpan Konfigurasi
                </button>
            </div>
        </form>

        <!-- Information Card (Span 1) -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm flex flex-col justify-between text-left">
            <div>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Aturan Pengiriman</h2>
                <p class="mt-2 text-xs text-slate-500 leading-relaxed">Notifikasi risiko hanya akan dikirimkan ke Telegram apabila artikel/medsos berkategori:</p>
                <ul class="mt-2 text-xs text-slate-600 list-disc pl-4 space-y-1 font-semibold">
                    <li class="text-rose-600">Risk Level: High / Critical</li>
                    <li class="text-amber-600">Risk Level: Medium & Reach Level: High</li>
                </ul>
            </div>
            <div class="text-[10px] text-slate-400 bg-slate-50 border border-slate-100 p-2.5 rounded-xl">
                Tautan/Berita berlabel netral atau aman tidak akan dikirim demi mencegah *spam*.
            </div>
        </div>
    </div>

    <!-- Project Recipients Table Card -->
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden text-left">
        <div class="border-b border-slate-100 px-6 py-4 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-800">Daftar Penerima Khusus Proyek</h2>
                <p class="text-[10px] text-slate-400 mt-0.5">Petakan target group chat ID kustom per masing-masing proyek pemantauan.</p>
            </div>
            <div class="relative w-72">
                <input 
                    wire:model.live.debounce.300ms="search" 
                    type="text" 
                    placeholder="Cari proyek..." 
                    class="h-9 w-full rounded-xl border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387]/20"
                />
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-xs text-slate-700">
                <thead class="bg-slate-50/75 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3.5 text-left font-bold w-12">No</th>
                        <th class="px-4 py-3.5 text-left font-bold">Nama Proyek</th>
                        <th class="px-4 py-3.5 text-left font-bold">Custom Chat/Group ID</th>
                        <th class="px-4 py-3.5 text-left font-bold">Status</th>
                        <th class="px-4 py-3.5 text-right font-bold w-36">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($recipients as $rec)
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-4 py-3 text-slate-500 font-semibold">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3 font-bold text-slate-900">{{ $rec->project?->name ?? 'Proyek Terhapus' }}</td>
                            <td class="px-4 py-3 font-mono text-slate-600">{{ $rec->chat_id }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 font-bold {{ $rec->is_active ? 'text-emerald-600' : 'text-slate-400' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $rec->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                    {{ $rec->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <!-- Edit Button -->
                                    <button 
                                        wire:click="editRecipient({{ $rec->id }})" 
                                        class="p-1.5 text-slate-500 hover:text-[#1fa387] bg-slate-50 hover:bg-[#1fa387]/5 border border-slate-200 hover:border-[#1fa387] rounded-lg transition cursor-pointer"
                                        title="Ubah Penerima"
                                    >
                                        <span class="material-symbols-outlined text-[15px] block">edit</span>
                                    </button>
                                    
                                    <!-- Toggle Active/Inactive Status -->
                                    <button 
                                        wire:click="toggleRecipientStatus({{ $rec->id }})" 
                                        class="p-1.5 text-slate-500 hover:text-slate-800 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-lg transition cursor-pointer"
                                        title="{{ $rec->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                                    >
                                        <span class="material-symbols-outlined text-[15px] block">
                                            {{ $rec->is_active ? 'toggle_on' : 'toggle_off' }}
                                        </span>
                                    </button>

                                    <!-- Delete Button -->
                                    <button 
                                        wire:click="requestDeleteRecipient({{ $rec->id }})" 
                                        class="p-1.5 text-slate-400 hover:text-rose-600 bg-slate-50 hover:bg-rose-50 border border-slate-200 hover:border-rose-500 rounded-lg transition cursor-pointer"
                                        title="Hapus"
                                    >
                                        <span class="material-symbols-outlined text-[15px] block">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-slate-400 italic">Belum ada penerima khusus proyek terdaftar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($recipients->hasPages())
            <div class="border-t border-slate-100 px-6 py-4">
                <div class="scale-[0.85] origin-right select-none w-full">
                    {{ $recipients->onEachSide(1)->links(data: ['scrollTo' => false]) }}
                </div>
            </div>
        @endif
    </div>

    <!-- Custom Recipient Modal -->
    @if($showRecipientModal)
        <div x-data x-init="document.body.style.overflow = 'hidden'; document.documentElement.style.overflow = 'hidden'; return () => { document.body.style.overflow = ''; document.documentElement.style.overflow = ''; }" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm px-4 py-6">
            <div class="w-full max-w-md overflow-hidden rounded-[24px] bg-white shadow-2xl text-left overscroll-contain">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-[#1fa387]">Penerima Proyek</p>
                        <h2 class="text-base font-black text-slate-900 mt-0.5">{{ $editingRecipient ? 'Ubah Penerima Kustom' : 'Tambah Penerima Kustom Baru' }}</h2>
                    </div>
                    <button type="button" wire:click="closeRecipientModal" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition cursor-pointer">
                        <span class="material-symbols-outlined text-[20px] block">close</span>
                    </button>
                </div>
                
                <form wire:submit.prevent="saveRecipient" class="p-6 space-y-4">
                    <div>
                        <label class="mb-1.5 block text-xs font-bold text-slate-700">Pilih Proyek</label>
                        <select wire:model="project_id" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                            <option value="">-- Pilih Proyek --</option>
                            @foreach($projects as $proj)
                                <option value="{{ $proj->id }}">{{ $proj->name }}</option>
                            @endforeach
                        </select>
                        @error('project_id') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-bold text-slate-700">Group / Chat ID Telegram Khusus</label>
                        <input wire:model="chat_id" placeholder="Contoh: -100987654321" type="text" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition font-mono">
                        @error('chat_id') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center gap-2 pt-2 cursor-pointer">
                        <input type="checkbox" wire:model="recipient_is_active" class="rounded border-slate-300 text-[#1fa387] focus:ring-[#1fa387]/20 w-4 h-4">
                        <span class="text-xs font-bold text-slate-700">Penerima Aktif</span>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <button type="button" wire:click="closeRecipientModal" class="h-10 rounded-xl border border-slate-200 px-5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer">Batal</button>
                        <button type="submit" class="h-10 rounded-xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-6 text-xs font-bold transition cursor-pointer">Simpan Penerima</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Test Send Message Modal -->
    @if($showTestModal)
        <div x-data x-init="document.body.style.overflow = 'hidden'; document.documentElement.style.overflow = 'hidden'; return () => { document.body.style.overflow = ''; document.documentElement.style.overflow = ''; }" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm px-4 py-6">
            <div class="w-full max-w-lg overflow-hidden rounded-[24px] bg-white shadow-2xl text-left font-sans overscroll-contain">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-[#1fa387]">Pengujian Telegram</p>
                        <h2 class="text-base font-black text-slate-900 mt-0.5">Uji Kirim Pesan Notifikasi</h2>
                    </div>
                    <button type="button" wire:click="$set('showTestModal', false)" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition cursor-pointer">
                        <span class="material-symbols-outlined text-[20px] block">close</span>
                    </button>
                </div>
                
                <form wire:submit.prevent="runTestSend" class="p-6 space-y-4">
                    <div>
                        <label class="mb-1.5 block text-xs font-bold text-slate-700">Chat ID Tujuan Uji</label>
                        <input wire:model="test_chat_id" placeholder="Contoh: -100123456789" type="text" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" inputmode="numeric" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition font-mono">
                        @error('test_chat_id') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-bold text-slate-700">Isi Pesan Uji</label>
                        <textarea wire:model="test_message" rows="3" class="w-full rounded-xl border border-slate-200 p-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition"></textarea>
                        @error('test_message') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    @if($testResultStatus)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-2 text-xs">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-700">Hasil Uji Koneksi:</span>
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $testResultStatus === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                                    {{ $testResultStatus === 'success' ? 'Berhasil' : 'Gagal' }}
                                </span>
                            </div>
                            @if($testResultStatus === 'success')
                                <div class="text-slate-600">Pesan uji coba berhasil terkirim ke Chat ID <strong>{{ $test_chat_id }}</strong>.</div>
                            @else
                                <div class="text-rose-600 font-bold">{{ $testResultError }}</div>
                            @endif
                        </div>
                    @endif

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <button type="button" wire:click="$set('showTestModal', false)" class="h-10 rounded-xl border border-slate-200 px-5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer">Batal</button>
                        <button type="submit" class="h-10 rounded-xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-6 text-xs font-bold transition cursor-pointer">Jalankan Uji</button>
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
                        <h2 class="text-sm font-black text-slate-900 mt-0.5">Hapus Penerima Proyek?</h2>
                    </div>
                </div>
                <p class="text-xs text-slate-500 leading-relaxed">Aksi ini bersifat permanen. Tautan custom chat ID untuk proyek terpilih akan dihapus.</p>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button wire:click="$set('confirmingDelete', false)" class="h-10 rounded-xl border border-slate-200 px-5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer">Batal</button>
                    <button wire:click="deleteRecipientConfirmed" class="h-10 rounded-xl bg-rose-600 hover:bg-rose-700 text-white px-6 text-xs font-bold transition cursor-pointer">Ya, Hapus</button>
                </div>
            </div>
        </div>
    @endif
</div>
