<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ \App\Helpers\AppBrandingHelper::getAppName() }} Media Intelligence</title>
    <!-- Premium smooth dark theme with subtle gradient -->
    <style>
        :root {
            --primary-h: 210;
            --primary-s: 60%;
            --primary-l: 55%;
            --bg-h: 210;
            --bg-s: 30%;
            --bg-l: 12%;
        }
        body {
            margin: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: hsl(var(--bg-h), var(--bg-s), var(--bg-l));
            color: #fff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .container {
            max-width: 960px;
            margin: 2rem auto;
            padding: 1rem 1.5rem;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
        }
        a { color: hsl(var(--primary-h), var(--primary-s), var(--primary-l)); }
        .btn-primary {
            background: hsl(var(--primary-h), var(--primary-s), var(--primary-l));
            border: none;
            color: #fff;
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .btn-primary:hover { background: hsl(var(--primary-h), var(--primary-s), calc(var(--primary-l) - 5%)); }
    </style>
    @stack('styles')
</head>
<body>
    <header style="background:#fff;border-bottom:1px solid #e2e8f0;position:sticky;top:0;z-index:50;">
        <div style="max-width:1400px;margin:0 auto;padding:0 1.5rem;height:64px;display:flex;align-items:center;justify-content:space-between;">
            <a href="{{ url('/') }}" style="display:flex;align-items:center;gap:8px;text-decoration:none;">
                @if($customLogo = \App\Helpers\AppBrandingHelper::getAppLogoPath())
                    <img src="{{ asset('storage/' . $customLogo) }}" style="height:28px;max-width:120px;object-fit:contain;">
                @else
                    <svg width="28" height="28" viewBox="0 0 42 42" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <polygon points="21,4 39,38 3,38" fill="none" stroke="#c0392b" stroke-width="4" stroke-linejoin="round"/>
                        <line x1="11" y1="28" x2="31" y2="28" stroke="#c0392b" stroke-width="4" stroke-linecap="round"/>
                    </svg>
                @endif
                <div style="display:flex;flex-direction:column;line-height:1;">
                    <span style="font-size:0.875rem;font-weight:900;letter-spacing:0.15em;color:#1e293b;text-transform:uppercase;">{{ \App\Helpers\AppBrandingHelper::getAppName() }}</span>
                    <span style="font-size:0.55rem;font-weight:600;letter-spacing:0.2em;color:#94a3b8;text-transform:uppercase;margin-top:2px;">Media Intelligence</span>
                </div>
            </a>
        </div>
    </header>
    <main class="container flex-1">
        @if (session('status'))
            <div class="mb-4 p-2 bg-green-600 rounded">{{ session('status') }}</div>
        @endif
        @yield('content')
    </main>
    <footer class="container text-center text-sm opacity-75 mt-4">
        &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
    </footer>
    @stack('scripts')
</body>
</html>
