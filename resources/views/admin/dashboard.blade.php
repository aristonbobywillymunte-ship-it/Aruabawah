<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Dashboard — Arusbawah Media Intelligence</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1" rel="stylesheet" />
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f7f9ff] text-slate-800 font-sans">
    <div class="flex min-h-screen flex-row">
        <aside class="w-72 shrink-0 border-r border-slate-200 bg-white"><div class="flex flex-col px-5 py-5 h-screen sticky top-0 overflow-y-auto">
            <a href="{{ url('/admin') }}" class="flex items-center gap-1.5">
                @if($customLogo = \App\Helpers\AppBrandingHelper::getAppLogoPath())
                    <img src="{{ asset('storage/' . $customLogo) }}" class="h-5 max-w-[60px] object-contain shrink-0">
                @else
                    <svg width="14" height="14" viewBox="0 0 42 42" fill="none" xmlns="http://www.w3.org/2000/svg" class="shrink-0">
                        <polygon points="21,4 39,38 3,38" fill="none" stroke="#c0392b" stroke-width="4" stroke-linejoin="round"/>
                        <line x1="11" y1="28" x2="31" y2="28" stroke="#c0392b" stroke-width="4" stroke-linecap="round"/>
                    </svg>
                @endif
                <div class="leading-none">
                    <div class="text-[10px] font-black leading-none tracking-wider text-slate-900 uppercase">{{ \App\Helpers\AppBrandingHelper::getAppName() }}</div>
                    <div class="mt-0.5 text-[10px] font-black leading-none tracking-wider text-slate-400">Media Intelligence</div>
                </div>
            </a>

            <div class="mt-6 text-[10px] font-bold uppercase tracking-[0.24em] text-slate-400">
                Admin Menu
            </div>

            <nav class="mt-3 space-y-1">
                <a href="{{ url('/admin') }}" class="flex items-center gap-3 rounded-2xl bg-[#1fa387]/10 px-4 py-3 text-sm font-semibold text-[#1fa387]">
                    <span class="material-symbols-outlined text-[20px]">dashboard</span>
                    <span>Dashboard</span>
                </a>
                <a wire:navigate href="{{ route('admin.users') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    <span class="material-symbols-outlined text-[20px] text-slate-400">groups</span>
                    <span>Kelola User</span>
                </a>
                <a wire:navigate href="{{ route('admin.apify') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    <span class="material-symbols-outlined text-[20px] text-slate-400">smart_toy</span>
                    <span>Apify</span>
                </a>
                <a wire:navigate href="{{ route('admin.ai-providers') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    <span class="material-symbols-outlined text-[20px] text-slate-400">psychology</span>
                    <span>AI Provider</span>
                </a>
                <a wire:navigate href="{{ route('admin.scraping-settings') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    <span class="material-symbols-outlined text-[20px] text-slate-400">settings</span>
                    <span>Scraping Settings</span>
                </a>
                <a href="{{ route('admin.branding') }}" wire:navigate class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    <span class="material-symbols-outlined text-[20px] text-slate-400">palette</span>
                    <span>Branding Aplikasi</span>
                </a>
                <a href="{{ route('admin.pipeline-monitor') }}" wire:navigate class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    <span class="material-symbols-outlined text-[20px] text-slate-400">monitor_heart</span>
                    <span>Pipeline Monitor</span>
                </a>
                <a wire:navigate href="{{ route('admin.news-sources') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    <span class="material-symbols-outlined text-[20px] text-slate-400">feed</span>
                    <span>Manajemen Sumber Berita</span>
                </a>
                <a wire:navigate href="{{ route('admin.ai-prompt-templates') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    <span class="material-symbols-outlined text-[20px] text-slate-400">terminal</span>
                    <span>AI Prompt Templates</span>
                </a>
                <a wire:navigate href="{{ route('admin.telegram-settings') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    <span class="material-symbols-outlined text-[20px] text-slate-400">send</span>
                    <span>Telegram Settings</span>
                </a>
                <a wire:navigate href="{{ route('admin.logs') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    <span class="material-symbols-outlined text-[20px] text-slate-400">description</span>
                    <span>Log Sistem</span>
                </a>
                <a wire:navigate href="{{ route('admin.database') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    <span class="material-symbols-outlined text-[20px] text-slate-400">database</span>
                    <span>Database</span>
                </a>
                <a wire:navigate href="{{ route('admin.maintenance') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    <span class="material-symbols-outlined text-[20px] text-slate-400">cleaning_services</span>
                    <span>Maintenance</span>
                </a>
            </nav>
        </div></aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-50 w-full border-b border-slate-200 bg-white">
                <div class="mx-auto flex h-16 w-full max-w-[1440px] items-center justify-between px-6">
                    <div class="text-sm font-semibold text-slate-500">Admin Dashboard</div>
                    <div class="flex items-center gap-4">
                        <div class="text-right hidden sm:block">
                            <div class="text-sm font-bold text-slate-800">{{ $user->name }}</div>
                            <div class="text-xs text-slate-500">{{ $user->email }}</div>
                        </div>
                        <details class="relative">
                            <summary class="list-none flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-full bg-slate-200 hover:bg-slate-300">
                                <svg class="h-5 w-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                                </svg>
                            </summary>
                            <div class="absolute right-0 mt-2 w-56 rounded-xl border border-slate-100 bg-white py-2 shadow-lg">
                                <a wire:navigate class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50" href="{{ route('password.change') }}">
                                    <span class="material-symbols-outlined text-lg text-slate-400">lock</span>
                                    <span>Ganti Password</span>
                                </a>
                                <div class="my-1 border-t border-slate-100"></div>
                                <form method="POST" action="{{ url('/logout') }}">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm text-red-500 hover:bg-red-50">
                                        <span class="material-symbols-outlined text-lg">logout</span>
                                        <span>Logout</span>
                                    </button>
                                </form>
                            </div>
                        </details>
                    </div>
                </div>
            </header>

            <main class="mx-auto w-full max-w-[1440px] px-6 py-8 space-y-6">
                <!-- Status Header -->
                <div class="flex items-center justify-between border-b border-slate-200 pb-5 text-left">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#1fa387]">Sistem Kesehatan Platform</p>
                        <h1 class="text-2xl font-black text-slate-900 mt-1">Dashboard Administrator</h1>
                        <p class="text-xs text-slate-500 mt-1">Pantau status konektivitas basis data, server perayap, limit AI, serta notifikasi krisis.</p>
                    </div>
                </div>

                @php
                    $apifyIssues = \App\Models\ApifyActor::where('status', 'active')
                        ->where('last_run_status', 'failed')
                        ->get()
                        ->filter(fn ($actor) => !\App\Models\ApifyActor::shouldSuppressUiError($actor->last_run_message));
                @endphp
                @if($apifyIssues->isNotEmpty())
                    <div class="bg-rose-50 border border-rose-200 rounded-3xl p-5 flex items-start gap-3.5 text-rose-800 text-xs font-semibold">
                        <span class="material-symbols-outlined text-rose-600 text-xl shrink-0 mt-0.5">error</span>
                        <div class="space-y-1 text-left">
                            <strong class="text-sm font-bold block text-rose-900">Kendala Pengambilan Data Media Sosial</strong>
                            <p class="leading-relaxed">Proses pengambilan data untuk media sosial <strong>{{ $apifyIssues->pluck('platform')->unique()->implode(', ') }}</strong> sedang ditangguhkan sementara. <span class="font-sans bg-rose-100 px-1.5 py-0.5 rounded text-[11px] block mt-1.5 leading-normal">{{ \App\Models\ApifyActor::friendlyRunMessage($apifyIssues->first()->last_run_message) }}</span></p>
                        </div>
                    </div>
                @endif

                <!-- Livewire System Health Gadget -->
                <livewire:admin.system-health />
            </main>
        </div>
    </div>
    <x-admin-toast />
    @livewireScripts
</body>
</html>
