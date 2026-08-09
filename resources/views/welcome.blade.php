<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ \App\Helpers\AppBrandingHelper::getAppName() }} Media Intelligence</title>
        
        <!-- Google Fonts: Hanken Grotesk, Inter, JetBrains Mono -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@300;400;500;650;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1" rel="stylesheet" />

        <!-- Styles & Scripts -->
        @livewireStyles
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @include('components.media-dashboard-styles')
        @stack('styles')
    </head>
    <body class="antialiased bg-surface-studio text-on-surface font-sans selection:bg-primary/20 selection:text-primary {{ request()->query('project') ? 'h-screen overflow-hidden' : 'min-h-screen' }} flex flex-col">

        @if(isset($slot))
            {{ $slot }}
        @else
            <livewire:projects-list />
        @endif

        @livewireScripts

        @if(session()->has('toast'))
            <script>
                (function() {
                    const triggerToast = () => {
                        setTimeout(() => {
                            window.dispatchEvent(new CustomEvent('project-action-toast', {
                                detail: {
                                    type: '{{ session('toast')['type'] ?? 'success' }}',
                                    message: '{{ session('toast')['message'] ?? 'Berhasil' }}'
                                }
                            }));
                        }, 500);
                    };

                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', triggerToast);
                    } else {
                        triggerToast();
                    }
                    
                    document.addEventListener('livewire:navigated', triggerToast, { once: true });
                })();
            </script>
        @endif

        @stack('scripts')
    </body>
</html>
