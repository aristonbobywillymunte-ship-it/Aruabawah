<div class="mx-auto w-full max-w-7xl space-y-6 font-sans">
    <!-- Top Header & Search Bar -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-200 pb-5">
        <div class="text-left">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Panel Administrator</p>
            <h1 class="text-2xl font-black text-slate-900 mt-1">Kelola Pengguna</h1>
            <p class="text-xs text-slate-500 mt-1">Manajemen akun, status keaktifan, role hak akses, dan pengaturan kata sandi.</p>
        </div>

        <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto">
            <div class="relative w-full sm:w-80">
                <input 
                    wire:model.live.debounce.300ms="search" 
                    type="text" 
                    placeholder="Cari nama atau email..." 
                    class="h-10 w-full rounded-2xl border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-[#1fa387] focus:ring-1 focus:ring-[#1fa387]/20"
                />
            </div>
            <button 
                wire:click="create" 
                class="inline-flex h-10 w-full sm:w-auto items-center justify-center gap-1.5 rounded-2xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-5 text-xs font-bold transition shadow-sm cursor-pointer"
            >
                <span class="material-symbols-outlined text-[18px]">person_add</span>
                <span>Tambah Pengguna</span>
            </button>
        </div>
    </div>
    <!-- Users Table Card -->
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden text-left">
        <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-sm font-bold text-slate-800">Daftar Akun Pengguna</h2>
            <p class="text-[10px] text-slate-400 mt-0.5">Daftar seluruh pengguna aktif dan administrator pada platform</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-xs text-slate-700">
                <thead class="bg-slate-50/75 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3.5 text-left font-bold w-12">No</th>
                        <th class="px-4 py-3.5 text-left font-bold">Nama Pengguna</th>
                        <th class="px-4 py-3.5 text-left font-bold">Alamat Email</th>
                        <th class="px-4 py-3.5 text-left font-bold">Role</th>
                        <th class="px-4 py-3.5 text-left font-bold">Status</th>
                        <th class="px-4 py-3.5 text-left font-bold">Tanggal Dibuat</th>
                        <th class="px-4 py-3.5 text-right font-bold w-48">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($users as $index => $user)
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-4 py-3 text-slate-500 font-semibold">{{ $index + 1 }}</td>
                            <td class="px-4 py-3 font-bold text-slate-900">{{ $user->name }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-600">{{ $user->email }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-md border px-2.5 py-0.5 text-[9px] font-bold {{ $user->isAdmin() ? 'bg-teal-50 text-teal-700 border-teal-100' : 'bg-slate-100 text-slate-600 border-slate-200' }}">
                                    {{ strtoupper($user->role ?? 'user') }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 font-bold {{ ($user->status ?? 'active') === 'active' ? 'text-emerald-600' : 'text-slate-400' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ ($user->status ?? 'active') === 'active' ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                    {{ ($user->status ?? 'active') === 'active' ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-500 font-semibold">{{ optional($user->created_at)->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <!-- Edit Button -->
                                    <button 
                                        wire:click="edit({{ $user->id }})" 
                                        class="p-1.5 text-slate-500 hover:text-[#1fa387] bg-slate-50 hover:bg-[#1fa387]/5 border border-slate-200 hover:border-[#1fa387] rounded-lg transition cursor-pointer"
                                        title="Ubah Data"
                                    >
                                        <span class="material-symbols-outlined text-[15px] block">edit</span>
                                    </button>
                                    
                                    <!-- Toggle Status Button -->
                                    <button 
                                        wire:click="toggleStatus({{ $user->id }})" 
                                        class="p-1.5 text-slate-500 hover:text-slate-800 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-lg transition cursor-pointer"
                                        title="{{ ($user->status ?? 'active') === 'active' ? 'Nonaktifkan Akun' : 'Aktifkan Akun' }}"
                                    >
                                        <span class="material-symbols-outlined text-[15px] block">
                                            {{ ($user->status ?? 'active') === 'active' ? 'toggle_on' : 'toggle_off' }}
                                        </span>
                                    </button>
                                    
                                    <!-- Reset Password Button -->
                                    <button 
                                        wire:click="resetPassword({{ $user->id }})" 
                                        class="p-1.5 text-slate-500 hover:text-amber-600 bg-slate-50 hover:bg-amber-50 border border-slate-200 hover:border-amber-500 rounded-lg transition cursor-pointer"
                                        title="Reset Password ke default ('password')"
                                    >
                                        <span class="material-symbols-outlined text-[15px] block">lock_reset</span>
                                    </button>

                                    <!-- Delete Button -->
                                    @if($user->id !== auth()->id())
                                        <button 
                                            wire:click="requestDelete({{ $user->id }})" 
                                            class="p-1.5 text-slate-400 hover:text-rose-600 bg-slate-50 hover:bg-rose-50 border border-slate-200 hover:border-rose-500 rounded-lg transition cursor-pointer"
                                            title="Hapus Akun"
                                        >
                                            <span class="material-symbols-outlined text-[15px] block">delete</span>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-400 italic">Tidak ada pengguna ditemukan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Form Add/Edit Modal -->
    @if($showForm)
        <div wire:key="form-modal" x-data x-init="document.body.classList.add('overflow-hidden'); return () => document.body.classList.remove('overflow-hidden');" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm px-4 py-6">
            <div class="w-full max-w-md overflow-hidden rounded-[24px] bg-white shadow-2xl text-left overscroll-contain">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-[#1fa387]">Manajemen Pengguna</p>
                        <h2 class="text-base font-black text-slate-900 mt-0.5">{{ $isEditing ? 'Ubah Data Pengguna' : 'Tambah Pengguna Baru' }}</h2>
                    </div>
                    <button type="button" wire:click="closeForm" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition cursor-pointer">
                        <span class="material-symbols-outlined text-[20px] block">close</span>
                    </button>
                </div>

                <form wire:submit.prevent="save" class="p-6 space-y-4">
                    <div>
                        <label class="mb-1.5 block text-xs font-bold text-slate-700">Nama Lengkap</label>
                        <input wire:model="name" placeholder="Contoh: John Doe" type="text" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                        @error('name') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-bold text-slate-700">Alamat Email</label>
                        <input wire:model="email" placeholder="Contoh: john@example.com" type="email" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                        @error('email') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-slate-700">Password</label>
                            <input wire:model="password" placeholder="Min. 8 karakter" type="password" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                            @error('password') <p class="mt-1 text-[10px] font-bold text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-slate-700">Ulangi Password</label>
                            <input wire:model="password_confirmation" placeholder="Ulangi password" type="password" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-slate-700">Role Akses</label>
                            <select wire:model="role" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                                <option value="user">User biasa</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold text-slate-700">Status Akun</label>
                            <select wire:model="status" class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-800 outline-none focus:border-[#1fa387] transition">
                                <option value="active">Aktif</option>
                                <option value="inactive">Nonaktif</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <button type="button" wire:click="closeForm" class="h-10 rounded-xl border border-slate-200 px-5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer">Batal</button>
                        <button type="submit" class="h-10 rounded-xl bg-[#1fa387] hover:bg-[#1a8b73] text-white px-6 text-xs font-bold transition cursor-pointer">Simpan Data</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Delete Confirmation Modal -->
    @if($confirmingDelete)
        <div wire:key="delete-modal" x-data x-init="document.body.classList.add('overflow-hidden'); return () => document.body.classList.remove('overflow-hidden');" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm px-4 py-6">
            <div class="w-full max-w-sm rounded-[24px] bg-white p-6 shadow-2xl text-left space-y-4 overscroll-contain">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-full bg-rose-50 flex items-center justify-center text-rose-600">
                        <span class="material-symbols-outlined text-[20px] block">warning</span>
                    </span>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-rose-500">Konfirmasi Hapus</p>
                        <h2 class="text-sm font-black text-slate-900 mt-0.5">Hapus Akun Pengguna?</h2>
                    </div>
                </div>
                <p class="text-xs text-slate-500 leading-relaxed">Aksi ini bersifat permanen. Seluruh hak akses pengguna yang bersangkutan akan dicabut total.</p>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button wire:click="$set('confirmingDelete', false)" class="h-10 rounded-xl border border-slate-200 px-5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer">Batal</button>
                    <button wire:click="deleteConfirmed" class="h-10 rounded-xl bg-rose-600 hover:bg-rose-700 text-white px-6 text-xs font-bold transition cursor-pointer">Ya, Hapus</button>
                </div>
            </div>
        </div>
    @endif
</div>
