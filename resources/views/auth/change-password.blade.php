<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Ganti Password — {{ \App\Helpers\AppBrandingHelper::getAppName() }} Media Intelligence</title>
    <meta name="description" content="Perbarui kata sandi akun {{ \App\Helpers\AppBrandingHelper::getAppName() }} Media Intelligence Anda." />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f7f9ff] flex items-center justify-center font-sans text-slate-800 p-4 sm:p-6 relative overflow-x-hidden">
    <!-- Subtle background blobs -->
    <div class="fixed -top-24 -right-24 w-96 h-96 rounded-full bg-[#1fa387]/5 blur-3xl pointer-events-none" aria-hidden="true"></div>
    <div class="fixed -bottom-24 -left-24 w-96 h-96 rounded-full bg-[#1fa387]/5 blur-3xl pointer-events-none" aria-hidden="true"></div>

    <div class="w-full max-w-md bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-8 relative z-10">
        <!-- Brand Header -->
        <div class="flex items-center gap-3 mb-6 pb-5 border-b border-slate-100">
            @if($customLogo = \App\Helpers\AppBrandingHelper::getAppLogoPath())
                <img src="{{ asset('storage/' . $customLogo) }}" alt="{{ \App\Helpers\AppBrandingHelper::getAppName() }}" class="h-10 max-w-[130px] object-contain">
            @else
                <div class="w-10 h-10 rounded-2xl bg-[#1fa387]/10 flex items-center justify-center text-[#1fa387] shrink-0">
                    <span class="material-symbols-outlined text-[24px]">shield_lock</span>
                </div>
            @endif
            <div class="min-w-0">
                <span class="block text-xs font-black tracking-widest text-[#1fa387] uppercase leading-none">{{ \App\Helpers\AppBrandingHelper::getAppName() }}</span>
                <span class="block text-[11px] font-bold text-slate-400 mt-1 uppercase tracking-wider leading-none">Media Intelligence</span>
            </div>
        </div>

        <div class="mb-6">
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 leading-tight">Ganti Password</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1.5 leading-relaxed">Perbarui kata sandi akun Anda untuk menjaga keamanan akses sistem.</p>
        </div>

        <!-- Status Alert -->
        @if (session('status'))
            <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50/80 px-4 py-3 text-xs sm:text-sm text-emerald-800 flex items-center gap-2.5">
                <span class="material-symbols-outlined text-emerald-600 text-[20px] shrink-0">check_circle</span>
                <span class="font-semibold">{{ session('status') }}</span>
            </div>
        @endif

        <!-- Error Alert -->
        @if (isset($errors) && $errors->any())
            <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50/80 px-4 py-3 text-xs sm:text-sm text-rose-800">
                <div class="flex items-center gap-2 font-bold mb-1.5">
                    <span class="material-symbols-outlined text-rose-600 text-[18px] shrink-0">error</span>
                    <span>Terdapat kendala pada input:</span>
                </div>
                <ul class="list-disc pl-7 space-y-1 text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form 
            method="POST" 
            action="{{ url('/change-password') }}" 
            class="space-y-4"
            x-data="{ showCurrent: false, showNew: false, showConfirm: false, submitting: false }"
            @submit="submitting = true"
        >
            @csrf

            <!-- Current Password -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5" for="current_password">Password Saat Ini</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-[18px] text-slate-400 pointer-events-none">lock</span>
                    <input 
                        id="current_password" 
                        name="current_password" 
                        :type="showCurrent ? 'text' : 'password'" 
                        required 
                        autocomplete="current-password"
                        placeholder="Masukkan password saat ini"
                        class="w-full rounded-xl border border-slate-200 bg-slate-50/50 pl-10 pr-10 py-2.5 sm:py-3 text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:bg-white focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/15 focus:outline-none transition" 
                    />
                    <button 
                        type="button" 
                        @click="showCurrent = !showCurrent" 
                        tabindex="-1"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none cursor-pointer flex items-center p-1"
                        title="Tampilkan / Sembunyikan Password"
                    >
                        <span class="material-symbols-outlined text-[18px]" x-text="showCurrent ? 'visibility_off' : 'visibility'"></span>
                    </button>
                </div>
            </div>

            <!-- New Password -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5" for="password">Password Baru</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-[18px] text-slate-400 pointer-events-none">key</span>
                    <input 
                        id="password" 
                        name="password" 
                        :type="showNew ? 'text' : 'password'" 
                        required 
                        autocomplete="new-password"
                        placeholder="Minimal 8 karakter"
                        class="w-full rounded-xl border border-slate-200 bg-slate-50/50 pl-10 pr-10 py-2.5 sm:py-3 text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:bg-white focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/15 focus:outline-none transition" 
                    />
                    <button 
                        type="button" 
                        @click="showNew = !showNew" 
                        tabindex="-1"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none cursor-pointer flex items-center p-1"
                        title="Tampilkan / Sembunyikan Password"
                    >
                        <span class="material-symbols-outlined text-[18px]" x-text="showNew ? 'visibility_off' : 'visibility'"></span>
                    </button>
                </div>
                <p class="text-[10.5px] text-slate-400 mt-1 flex items-center gap-1">
                    <span class="material-symbols-outlined text-[13px] text-slate-400">info</span>
                    <span>Gunakan minimal 8 karakter dengan kombinasi huruf dan angka.</span>
                </p>
            </div>

            <!-- Confirm New Password -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5" for="password_confirmation">Konfirmasi Password Baru</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-[18px] text-slate-400 pointer-events-none">verified_user</span>
                    <input 
                        id="password_confirmation" 
                        name="password_confirmation" 
                        :type="showConfirm ? 'text' : 'password'" 
                        required 
                        autocomplete="new-password"
                        placeholder="Ketik ulang password baru"
                        class="w-full rounded-xl border border-slate-200 bg-slate-50/50 pl-10 pr-10 py-2.5 sm:py-3 text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:bg-white focus:border-[#1fa387] focus:ring-2 focus:ring-[#1fa387]/15 focus:outline-none transition" 
                    />
                    <button 
                        type="button" 
                        @click="showConfirm = !showConfirm" 
                        tabindex="-1"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none cursor-pointer flex items-center p-1"
                        title="Tampilkan / Sembunyikan Password"
                    >
                        <span class="material-symbols-outlined text-[18px]" x-text="showConfirm ? 'visibility_off' : 'visibility'"></span>
                    </button>
                </div>
            </div>

            <!-- Action Controls -->
            <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                <a
                    href="{{ $backUrl ?? url('/') }}"
                    class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-slate-800 transition py-2 px-1 cursor-pointer"
                >
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                    <span>Kembali</span>
                </a>
                <button 
                    type="submit" 
                    :disabled="submitting"
                    class="rounded-xl bg-[#1fa387] px-5 py-2.5 sm:py-3 text-xs sm:text-sm font-bold text-white hover:bg-[#178a70] transition shadow-sm flex items-center gap-2 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
                >
                    <span x-show="!submitting" class="material-symbols-outlined text-[18px]">save</span>
                    <svg x-show="submitting" class="w-4 h-4 animate-spin text-white" style="display: none;" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-text="submitting ? 'Menyimpan...' : 'Simpan Password'">Simpan Password</span>
                </button>
            </div>
        </form>
    </div>
</body>
</html>
