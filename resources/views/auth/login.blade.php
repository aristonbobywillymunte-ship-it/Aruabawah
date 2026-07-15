<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login — {{ \App\Helpers\AppBrandingHelper::getAppName() }} Media Intelligence</title>
    <meta name="description" content="Login ke platform {{ \App\Helpers\AppBrandingHelper::getAppName() }} Media Intelligence untuk monitoring dan analisis media sosial." />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary: #1fa387;
            --primary-dark: #178a70;
            --primary-light: #e6f6f2;
            --primary-glow: rgba(31, 163, 135, 0.15);
            --bg: #f7f9ff;
            --white: #ffffff;
            --slate-50: #f8fafc;
            --slate-100: #f1f5f9;
            --slate-200: #e2e8f0;
            --slate-400: #94a3b8;
            --slate-500: #64748b;
            --slate-700: #334155;
            --slate-800: #1e293b;
            --slate-900: #0f172a;
            --error: #ef4444;
            --error-bg: #fef2f2;
            --shadow-card: 0 4px 32px rgba(31, 163, 135, 0.08), 0 1px 4px rgba(0,0,0,0.04);
            --shadow-btn: 0 4px 14px rgba(31, 163, 135, 0.35);
            --shadow-btn-hover: 0 6px 20px rgba(31, 163, 135, 0.45);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg);
            min-height: 100vh;
            display: flex;
            align-items: stretch;
            color: var(--slate-800);
        }

        /* ── Left Panel ── */
        .panel-left {
            display: none;
            flex: 1;
            background: linear-gradient(155deg, #0d7a62 0%, #1fa387 45%, #2ec4a3 100%);
            position: relative;
            overflow: hidden;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem;
        }

        @media (min-width: 900px) {
            .panel-left { display: flex; }
        }

        /* Decorative circles */
        .panel-left::before {
            content: '';
            position: absolute;
            width: 480px;
            height: 480px;
            border-radius: 50%;
            background: rgba(255,255,255,0.06);
            top: -120px;
            right: -120px;
        }
        .panel-left::after {
            content: '';
            position: absolute;
            width: 320px;
            height: 320px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
            bottom: -80px;
            left: -80px;
        }

        .panel-left-inner {
            position: relative;
            z-index: 2;
            max-width: 360px;
            text-align: center;
        }

        .brand-logo-big {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 2.5rem;
        }

        .brand-logo-big svg {
            width: 40px; height: 40px;
            color: rgba(255,255,255,0.95);
        }

        .brand-text-big .name {
            font-size: 1.8rem;
            font-weight: 900;
            letter-spacing: 0.18em;
            color: #fff;
            display: block;
            line-height: 1;
        }

        .brand-text-big .sub {
            font-size: 0.6rem;
            font-weight: 600;
            letter-spacing: 0.22em;
            color: rgba(255,255,255,0.75);
            text-transform: uppercase;
            display: block;
            margin-top: 3px;
        }

        .panel-tagline {
            font-size: 1.55rem;
            font-weight: 800;
            color: #fff;
            line-height: 1.3;
            margin-bottom: 1rem;
        }

        .panel-desc {
            font-size: 0.875rem;
            color: rgba(255,255,255,0.78);
            line-height: 1.7;
            margin-bottom: 2.5rem;
        }

        /* Stat pills on left panel */
        .stat-pills {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            width: 100%;
        }

        .stat-pill {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            text-align: left;
            animation: slideIn 0.6s ease both;
        }

        .stat-pill:nth-child(2) { animation-delay: 0.1s; }
        .stat-pill:nth-child(3) { animation-delay: 0.2s; }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        .stat-pill-icon {
            width: 36px; height: 36px;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-pill-icon .material-symbols-outlined {
            font-size: 20px;
            color: #fff;
        }

        .stat-pill-text .label {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.65);
            font-weight: 500;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .stat-pill-text .value {
            font-size: 1rem;
            font-weight: 700;
            color: #fff;
            line-height: 1.2;
        }

        /* ── Right Panel (Form) ── */
        .panel-right {
            display: flex;
            flex: 1;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.5rem;
            background: var(--bg);
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background: var(--white);
            border-radius: 20px;
            box-shadow: var(--shadow-card);
            padding: 2.5rem 2rem;
            animation: fadeUp 0.5s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Small logo on card (for mobile when left panel hidden) */
        .card-brand {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 1.75rem;
        }

        .card-brand svg {
            width: 26px; height: 26px;
            color: var(--primary);
        }

        .card-brand .name {
            font-size: 0.95rem;
            font-weight: 900;
            letter-spacing: 0.18em;
            color: var(--slate-800);
        }

        .card-brand .sub {
            font-size: 0.55rem;
            font-weight: 600;
            letter-spacing: 0.2em;
            color: var(--slate-400);
            text-transform: uppercase;
            display: block;
            line-height: 1;
            margin-top: 1px;
        }

        @media (min-width: 900px) {
            .card-brand { display: none; }
        }

        .login-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--slate-800);
            margin-bottom: 0.35rem;
        }

        .login-subtitle {
            font-size: 0.825rem;
            color: var(--slate-500);
            margin-bottom: 2rem;
        }

        /* Error block */
        .error-block {
            background: var(--error-bg);
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .error-block .material-symbols-outlined {
            font-size: 18px;
            color: var(--error);
            flex-shrink: 0;
            margin-top: 1px;
        }

        .error-block ul {
            list-style: none;
        }

        .error-block li {
            font-size: 0.8rem;
            color: #dc2626;
        }

        /* Form group */
        .form-group {
            margin-bottom: 1.1rem;
        }

        .form-label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--slate-700);
            margin-bottom: 0.4rem;
            letter-spacing: 0.02em;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap .material-symbols-outlined {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            color: var(--slate-400);
            pointer-events: none;
            transition: color 0.2s;
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 20;
        }

        .form-input {
            width: 100%;
            padding: 0.65rem 0.85rem 0.65rem 2.5rem;
            border: 1.5px solid var(--slate-200);
            border-radius: 10px;
            font-size: 0.875rem;
            font-family: inherit;
            color: var(--slate-800);
            background: var(--slate-50);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }

        .form-input:focus {
            border-color: var(--primary);
            background: var(--white);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .form-input:focus + .input-icon,
        .input-wrap:focus-within .material-symbols-outlined {
            color: var(--primary);
        }

        /* Password toggle */
        .toggle-pass {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 2px;
            color: var(--slate-400);
            display: flex;
            align-items: center;
            transition: color 0.2s;
        }

        .toggle-pass:hover { color: var(--primary); }

        .toggle-pass .material-symbols-outlined {
            position: static;
            transform: none;
            font-size: 18px;
        }

        /* Divider row: remember + forgot */
        .form-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            margin-top: 0.25rem;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            color: var(--slate-600, #475569);
        }

        .checkbox-label input[type="checkbox"] {
            appearance: none;
            -webkit-appearance: none;
            width: 16px;
            height: 16px;
            border: 1.5px solid var(--slate-200);
            border-radius: 5px;
            background: var(--slate-50);
            cursor: pointer;
            position: relative;
            transition: all 0.15s;
            flex-shrink: 0;
        }

        .checkbox-label input[type="checkbox"]:checked {
            background: var(--primary);
            border-color: var(--primary);
        }

        .checkbox-label input[type="checkbox"]:checked::after {
            content: '';
            position: absolute;
            left: 4px; top: 1.5px;
            width: 5px; height: 9px;
            border: 2px solid #fff;
            border-top: none;
            border-left: none;
            transform: rotate(45deg);
        }

        .forgot-link {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--primary);
            text-decoration: none;
            transition: opacity 0.2s;
        }

        .forgot-link:hover { opacity: 0.75; }

        /* Submit button */
        .btn-login {
            width: 100%;
            padding: 0.75rem;
            background: var(--primary);
            color: #fff;
            font-family: inherit;
            font-size: 0.9rem;
            font-weight: 700;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            box-shadow: var(--shadow-btn);
            transition: background 0.2s, box-shadow 0.2s, transform 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            letter-spacing: 0.02em;
        }

        .btn-login:hover {
            background: var(--primary-dark);
            box-shadow: var(--shadow-btn-hover);
            transform: translateY(-1px);
        }

        .btn-login:active {
            transform: translateY(0);
            box-shadow: var(--shadow-btn);
        }

        .btn-login .material-symbols-outlined {
            font-size: 18px;
        }

        /* Footer */
        .login-footer {
            text-align: center;
            margin-top: 1.75rem;
            font-size: 0.73rem;
            color: var(--slate-400);
        }

        .login-footer a { color: var(--primary); text-decoration: none; font-weight: 500; }

        /* Divider dots */
        .dot-divider {
            display: flex;
            align-items: center;
            gap: 6px;
            justify-content: center;
            margin: 1.5rem 0;
        }

        .dot-divider span {
            width: 4px; height: 4px;
            border-radius: 50%;
            background: var(--slate-200);
        }

        /* Floating background blobs */
        .bg-blob {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 0;
            pointer-events: none;
        }

        .blob-1 {
            width: 400px; height: 400px;
            background: rgba(31,163,135,0.07);
            top: -100px; right: -100px;
        }

        .blob-2 {
            width: 300px; height: 300px;
            background: rgba(31,163,135,0.05);
            bottom: -80px; left: -80px;
        }
    </style>
</head>
<body>
    <!-- Subtle background blobs (visible on form side) -->
    <div class="bg-blob blob-1" aria-hidden="true"></div>
    <div class="bg-blob blob-2" aria-hidden="true"></div>

    <!-- ══ Left decorative panel ══ -->
    <aside class="panel-left" aria-hidden="true">
        <div class="panel-left-inner">
            <!-- Brand -->
            <div class="brand-logo-big" style="display: flex; align-items: center; gap: 16px;">
                @if($customLogo = \App\Helpers\AppBrandingHelper::getAppLogoPath())
                    <img src="{{ asset('storage/' . $customLogo) }}" style="height: 96px; max-width: 240px; object-fit: contain; vertical-align: middle; margin: 0;" class="transition-transform hover:scale-105 duration-300">
                @else
                    <!-- Ultra-Premium Geometric A & Wave Logo -->
                    <svg width="72" height="72" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" class="transition-transform hover:scale-105 duration-300">
                        <defs>
                            <linearGradient id="premiumLogoGradLoginBig" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#ff4d4d" />
                                <stop offset="50%" stop-color="#e50914" />
                                <stop offset="100%" stop-color="#9a0000" />
                            </linearGradient>
                            <filter id="logoShadowLoginBig" x="-10%" y="-10%" width="120%" height="120%">
                                <feDropShadow dx="0" dy="2" stdDeviation="3" flood-color="#e50914" flood-opacity="0.15" />
                            </filter>
                        </defs>
                        <path d="M12 38 C14 26, 18 10, 24 8" stroke="url(#premiumLogoGradLoginBig)" stroke-width="5.5" stroke-linecap="round" filter="url(#logoShadowLoginBig)" />
                        <path d="M24 8 C30 10, 34 26, 36 38" stroke="url(#premiumLogoGradLoginBig)" stroke-width="5.5" stroke-linecap="round" filter="url(#logoShadowLoginBig)" />
                        <path d="M15 28 Q 24 24, 33 28" stroke="#ff4d4d" stroke-width="4" stroke-linecap="round" />
                        <path d="M18 33 Q 24 30, 30 33" stroke="#e50914" stroke-width="2.5" stroke-linecap="round" opacity="0.8" />
                    </svg>
                @endif
                <div class="brand-text-big">
                    <span class="name">{{ \App\Helpers\AppBrandingHelper::getAppName() }}</span>
                    <span class="sub">Media Intelligence</span>
                </div>
            </div>

            <p class="panel-tagline">Monitor & Analisis<br>Media Sosial Anda</p>
            <p class="panel-desc">Platform monitoring media cerdas untuk memantau percakapan, sentimen, dan tren dari berbagai sumber sosial secara real-time.</p>

            <!-- Feature pills -->
            <div class="stat-pills">
                <div class="stat-pill">
                    <div class="stat-pill-icon">
                        <span class="material-symbols-outlined">query_stats</span>
                    </div>
                    <div class="stat-pill-text">
                        <span class="label">Analisis Sentimen</span>
                        <span class="value">Real-time AI Insights</span>
                    </div>
                </div>
                <div class="stat-pill">
                    <div class="stat-pill-icon">
                        <span class="material-symbols-outlined">monitor_heart</span>
                    </div>
                    <div class="stat-pill-text">
                        <span class="label">Multi-Platform</span>
                        <span class="value">Twitter, IG, TikTok &amp; News</span>
                    </div>
                </div>
                <div class="stat-pill">
                    <div class="stat-pill-icon">
                        <span class="material-symbols-outlined">assessment</span>
                    </div>
                    <div class="stat-pill-text">
                        <span class="label">Laporan Otomatis</span>
                        <span class="value">Export PDF &amp; Excel</span>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <!-- ══ Right login panel ══ -->
    <main class="panel-right">
        <div class="login-card">

            <!-- Logo (visible on mobile only) -->
            <div class="card-brand">
                @if($customLogo = \App\Helpers\AppBrandingHelper::getAppLogoPath())
                    <img src="{{ asset('storage/' . $customLogo) }}" style="height:36px; max-width:120px; object-fit:contain;" class="transition-transform hover:scale-105 duration-300">
                @else
                    <!-- Ultra-Premium Geometric A & Wave Logo -->
                    <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" class="transition-transform hover:scale-105 duration-300">
                        <defs>
                            <linearGradient id="premiumLogoGradLoginMobile" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#ff4d4d" />
                                <stop offset="50%" stop-color="#e50914" />
                                <stop offset="100%" stop-color="#9a0000" />
                            </linearGradient>
                            <filter id="logoShadowLoginMobile" x="-10%" y="-10%" width="120%" height="120%">
                                <feDropShadow dx="0" dy="2" stdDeviation="3" flood-color="#e50914" flood-opacity="0.15" />
                            </filter>
                        </defs>
                        <path d="M12 38 C14 26, 18 10, 24 8" stroke="url(#premiumLogoGradLoginMobile)" stroke-width="5.5" stroke-linecap="round" filter="url(#logoShadowLoginMobile)" />
                        <path d="M24 8 C30 10, 34 26, 36 38" stroke="url(#premiumLogoGradLoginMobile)" stroke-width="5.5" stroke-linecap="round" filter="url(#logoShadowLoginMobile)" />
                        <path d="M15 28 Q 24 24, 33 28" stroke="#ff4d4d" stroke-width="4" stroke-linecap="round" />
                        <path d="M18 33 Q 24 30, 30 33" stroke="#e50914" stroke-width="2.5" stroke-linecap="round" opacity="0.8" />
                    </svg>
                @endif
                <div>
                    <span class="name">{{ \App\Helpers\AppBrandingHelper::getAppName() }}</span>
                    <span class="sub">Media Intelligence</span>
                </div>
            </div>

            <h1 class="login-title">Selamat Datang 👋</h1>
            <p class="login-subtitle">Masuk ke akun {{ \App\Helpers\AppBrandingHelper::getAppName() }} Anda untuk melanjutkan</p>

            <!-- Error messages -->
            @if ($errors->any())
            <div class="error-block" role="alert">
                <span class="material-symbols-outlined">error</span>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <!-- Session status -->
            @if (session('status'))
            <div style="background:#e6f6f2;border:1px solid #a7f0dc;border-radius:10px;padding:0.75rem 1rem;margin-bottom:1.25rem;font-size:0.8rem;color:#0d7a62;">
                {{ session('status') }}
            </div>
            @endif

            <form method="POST" action="{{ route('login') }}" id="login-form">
                @csrf

                <!-- Email -->
                <div class="form-group">
                    <label class="form-label" for="email">Alamat Email</label>
                    <div class="input-wrap">
                        <span class="material-symbols-outlined" aria-hidden="true">mail</span>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            class="form-input"
                            value="{{ old('email') }}"
                            placeholder="nama@perusahaan.com"
                            required
                            autofocus
                            autocomplete="email"
                        />
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrap">
                        <span class="material-symbols-outlined" aria-hidden="true">lock</span>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            class="form-input"
                            placeholder="••••••••"
                            required
                            autocomplete="current-password"
                            style="padding-right: 2.75rem;"
                        />
                        <button type="button" class="toggle-pass" id="toggle-password" aria-label="Tampilkan password">
                            <span class="material-symbols-outlined" id="eye-icon" style="font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 20;">visibility</span>
                        </button>
                    </div>
                </div>

                <!-- Remember me + Forgot -->
                <div class="form-row">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" id="remember" />
                        Ingat saya
                    </label>
                    <a href="#" class="forgot-link">Lupa password?</a>
                </div>

                <!-- Submit -->
                <button type="submit" class="btn-login" id="btn-login">
                    <span class="material-symbols-outlined">login</span>
                    Masuk ke Dashboard
                </button>
            </form>

            <div class="dot-divider" aria-hidden="true">
                <span></span><span></span><span></span>
            </div>

            <div class="login-footer">
                &copy; {{ date('Y') }} {{ \App\Helpers\AppBrandingHelper::getAppName() }} Media Intelligence. Seluruh hak dilindungi.<br/>
                <span style="margin-top:4px;display:inline-block;">Butuh akun? <a href="#">Hubungi administrator</a></span>
            </div>
        </div>
    </main>

    <script>
        // Toggle password visibility
        const toggleBtn = document.getElementById('toggle-password');
        const passInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eye-icon');

        toggleBtn.addEventListener('click', () => {
            const isHidden = passInput.type === 'password';
            passInput.type = isHidden ? 'text' : 'password';
            eyeIcon.textContent = isHidden ? 'visibility_off' : 'visibility';
        });

        // Button loading state on submit
        const form = document.getElementById('login-form');
        const loginBtn = document.getElementById('btn-login');

        form.addEventListener('submit', () => {
            loginBtn.innerHTML = '<span class="material-symbols-outlined" style="animation:spin 1s linear infinite;">progress_activity</span> Memproses...';
            loginBtn.disabled = true;
        });
    </script>

    <style>
        @keyframes spin {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }
    </style>
</body>
</html>
