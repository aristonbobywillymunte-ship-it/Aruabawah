<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>@yield('title', 'Admin Dashboard') — {{ \App\Helpers\AppBrandingHelper::getAppName() }} Media Intelligence</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1" rel="stylesheet" />
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f7f9ff] text-slate-800 font-sans">
    <div class="flex min-h-screen flex-col lg:flex-row" x-data="{ mobileMenuOpen: false }">
        <!-- Sidebar / Navigation -->
        <aside class="w-full lg:w-72 shrink-0 border-b lg:border-b-0 lg:border-r border-slate-200 bg-white">
            <div class="flex flex-col h-full">
                <!-- Brand logo + Mobile Menu Toggle Button -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 lg:border-none lg:py-5 shrink-0">
                    <a href="{{ url('/admin') }}" class="flex items-center gap-1.5">
                        @if($customLogo = \App\Helpers\AppBrandingHelper::getAppLogoPath())
                            <img src="{{ asset('storage/' . $customLogo) }}" class="h-5 max-w-[60px] object-contain shrink-0">
                        @else
                            <svg width="14" height="14" viewBox="0 0 42 42" fill="none" xmlns="http://www.w3.org/2000/svg" class="shrink-0">
                                <polygon points="21,4 39,38 3,38" fill="none" stroke="#c0392b" stroke-width="4" stroke-linejoin="round"/>
                                <line x1="11" y1="28" x2="31" y2="28" stroke="#c0392b" stroke-width="4" stroke-linecap="round"/>
                            </svg>
                        @endif
                        <div class="leading-none text-left">
                            <div class="text-[10px] font-black leading-none tracking-wider text-slate-900 uppercase">{{ \App\Helpers\AppBrandingHelper::getAppName() }}</div>
                            <div class="mt-0.5 text-[10px] font-black leading-none tracking-wider text-slate-400">Media Intelligence</div>
                        </div>
                    </a>
                    <button @click="mobileMenuOpen = !mobileMenuOpen" class="lg:hidden flex items-center justify-center p-2 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-500">
                        <span class="material-symbols-outlined text-2xl" x-text="mobileMenuOpen ? 'close' : 'menu'">menu</span>
                    </button>
                </div>

                <!-- Navigation links -->
                <div class="lg:flex flex-col text-left px-5 pb-5 lg:py-5 lg:h-[calc(100vh-80px)] lg:sticky lg:top-20 lg:overflow-y-auto" :class="mobileMenuOpen ? 'flex' : 'hidden'">
                    <div class="mt-4 lg:mt-0 text-[10px] font-bold uppercase tracking-[0.24em] text-slate-400">Admin Menu</div>
                    <nav class="mt-3 space-y-1">
                        @php
                            $menuItems = [
                                ['route' => 'admin.dashboard', 'icon' => 'dashboard', 'label' => 'Dashboard'],
                                ['route' => 'admin.users', 'icon' => 'groups', 'label' => 'Kelola User'],
                                ['route' => 'admin.apify', 'icon' => 'smart_toy', 'label' => 'Apify'],
                                ['route' => 'admin.ai-providers', 'icon' => 'psychology', 'label' => 'AI Provider'],
                                ['route' => 'admin.scraping-settings', 'icon' => 'settings', 'label' => 'Scraping Settings'],
                                ['route' => 'admin.branding', 'icon' => 'palette', 'label' => 'Branding Aplikasi'],
                                ['route' => 'admin.pipeline-monitor', 'icon' => 'monitor_heart', 'label' => 'Pipeline Monitor'],
                                ['route' => 'admin.news-sources', 'icon' => 'feed', 'label' => 'Manajemen Sumber Berita'],
                                ['route' => 'admin.ai-prompt-templates', 'icon' => 'terminal', 'label' => 'AI Prompt Templates'],
                                ['route' => 'admin.telegram-settings', 'icon' => 'send', 'label' => 'Telegram Settings'],
                                ['route' => 'admin.logs', 'icon' => 'description', 'label' => 'Log Sistem'],
                                ['route' => 'admin.database', 'icon' => 'database', 'label' => 'Database'],
                                ['route' => 'admin.maintenance', 'icon' => 'cleaning_services', 'label' => 'Maintenance'],
                            ];
                        @endphp
                        @foreach($menuItems as $item)
                            @php
                                $isActive = request()->routeIs($item['route']);
                            @endphp
                            <a href="{{ route($item['route']) }}" wire:navigate 
                               class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-semibold transition-all duration-200 {{ $isActive ? 'bg-[#1fa387]/10 text-[#1fa387]' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                <span class="material-symbols-outlined text-[20px] {{ $isActive ? 'text-[#1fa387]' : 'text-slate-400' }}">{{ $item['icon'] }}</span>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </nav>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Header -->
            <header class="sticky top-0 z-50 w-full border-b border-slate-200 bg-white">
                <div class="mx-auto flex h-16 w-full max-w-[1440px] items-center justify-between px-6">
                    <div class="text-sm font-semibold text-slate-500">@yield('title', 'Admin Panel')</div>
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
            <main class="mx-auto w-full max-w-[1440px] px-4 pb-10 pt-6 sm:px-6 lg:px-8 lg:pt-8">
                @yield('content')
            </main>
        </div>
    </div>
    <x-admin-toast />
    @livewireScripts
</body>
</html>
