<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>AI Providers — Arusbawah Media Intelligence</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1" rel="stylesheet" />
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f7f9ff] text-slate-800 font-sans">
    <div class="flex min-h-screen flex-row">
        <!-- Sidebar -->
        <aside class="w-72 shrink-0 border-r border-slate-200 bg-white"><div class="flex flex-col text-left px-5 py-5 h-screen sticky top-0 overflow-y-auto">
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

            <div class="mt-6 text-[10px] font-bold uppercase tracking-[0.24em] text-slate-400">Admin Menu</div>
            <nav class="mt-3 space-y-1">
                <a href="{{ route('admin.dashboard') }}" wire:navigate class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    <span class="material-symbols-outlined text-[20px] text-slate-400">dashboard</span>
                    <span>Dashboard</span>
                </a>
                <a href="{{ route('admin.users') }}" wire:navigate class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    <span class="material-symbols-outlined text-[20px] text-slate-400">groups</span>
                    <span>Kelola User</span>
                </a>
                <a href="{{ route('admin.apify') }}" wire:navigate class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    <span class="material-symbols-outlined text-[20px] text-slate-400">smart_toy</span>
                    <span>Apify</span>
                </a>
                <a href="{{ route('admin.ai-providers') }}" wire:navigate class="flex items-center gap-3 rounded-2xl bg-[#1fa387]/10 px-4 py-3 text-sm font-semibold text-[#1fa387]">
                    <span class="material-symbols-outlined text-[20px]">psychology</span>
                    <span>AI Provider</span>
                </a>
                <a href="{{ route('admin.scraping-settings') }}" wire:navigate class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
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
                <a href="{{ route('admin.news-sources') }}" wire:navigate class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    <span class="material-symbols-outlined text-[20px] text-slate-400">feed</span>
                    <span>Manajemen Sumber Berita</span>
                </a>
                <a href="{{ route('admin.ai-prompt-templates') }}" wire:navigate class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    <span class="material-symbols-outlined text-[20px] text-slate-400">terminal</span>
                    <span>AI Prompt Templates</span>
                </a>
                <a href="{{ route('admin.telegram-settings') }}" wire:navigate class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    <span class="material-symbols-outlined text-[20px] text-slate-400">send</span>
                    <span>Telegram Settings</span>
                </a>
                <a href="{{ route('admin.logs') }}" wire:navigate class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    <span class="material-symbols-outlined text-[20px] text-slate-400">description</span>
                    <span>Log Sistem</span>
                </a>
                <a wire:navigate href="{{ route('admin.database') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    <span class="material-symbols-outlined text-[20px] text-slate-400">database</span>
                    <span>Database</span>
                </a>
                <a href="{{ route('admin.maintenance') }}" wire:navigate class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    <span class="material-symbols-outlined text-[20px] text-slate-400">cleaning_services</span>
                    <span>Maintenance</span>
                </a>
            </nav>
        </div></aside>

        <!-- Main Content Area -->
        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Header -->
            <header class="sticky top-0 z-50 w-full border-b border-slate-200 bg-white">
                <div class="mx-auto flex h-16 w-full max-w-[1440px] items-center justify-between px-6">
                    <div class="text-sm font-semibold text-slate-500">AI Provider Configuration</div>
                    <div class="flex items-center gap-4">
                        <div class="hidden text-right sm:block">
                            <div class="text-sm font-bold text-slate-800">{{ auth()->user()->name }}</div>
                            <div class="text-xs text-slate-500">{{ auth()->user()->email }}</div>
                        </div>
                        <details class="relative">
                            <summary class="list-none flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-full bg-slate-200 hover:bg-slate-300">
                                <svg class="h-5 w-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                                </svg>
                            </summary>
                            <div class="absolute right-0 mt-2 w-56 rounded-xl border border-slate-100 bg-white py-2 shadow-lg text-left">
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

            <!-- Main Panel -->
            <main class="mx-auto w-full max-w-[1440px] px-4 pb-10 pt-10 sm:px-6 lg:px-8 lg:pt-12">
                <livewire:admin.ai-providers />
            </main>
        </div>
    </div>
    <x-admin-toast />
    @livewireScripts
</body>
</html>
