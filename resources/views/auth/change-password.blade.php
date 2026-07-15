<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Ganti Password — Arusbawah Media Intelligence</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f7f9ff] flex items-center justify-center font-sans text-slate-800">
    <div class="w-full max-w-md bg-white rounded-3xl border border-slate-200 shadow-sm p-8">
        <div class="mb-6">
            <p class="text-xs font-bold tracking-widest text-[#1fa387] uppercase mb-2">Arusbawah Media Intelligence</p>
            <h1 class="text-2xl font-black text-slate-900">Ganti Password</h1>
            <p class="text-sm text-slate-500 mt-2">Ubah password akun Anda dengan aman.</p>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ url('/change-password') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2" for="current_password">Password Saat Ini</label>
                <input id="current_password" name="current_password" type="password" required class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-[#1fa387] focus:outline-none" />
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2" for="password">Password Baru</label>
                <input id="password" name="password" type="password" required class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-[#1fa387] focus:outline-none" />
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2" for="password_confirmation">Konfirmasi Password Baru</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-[#1fa387] focus:outline-none" />
            </div>

            <div class="flex items-center justify-between pt-2">
                <button
                    type="button"
                    onclick="window.history.length > 1 ? window.history.back() : window.location.href='{{ url('/') }}'"
                    class="text-sm font-semibold text-slate-500 hover:text-slate-800"
                >
                    Kembali
                </button>
                <button type="submit" class="rounded-xl bg-[#1fa387] px-5 py-3 text-sm font-bold text-white hover:bg-[#178a70]">
                    Simpan Password
                </button>
            </div>
        </form>
    </div>
</body>
</html>
