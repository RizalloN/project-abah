<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <title>Login - Dashboard A-Six</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/a-six-logo.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0857c3;
            --primary-dark: #053b82;
            --primary-light: #2563eb;
            --primary-soft: #eaf3ff;
            --bg-page: #f4f8ff;
            --surface: #ffffff;
            --border: #d6e6fb;
            --border-focus: #3b82f6;
            --text-main: #082b59;
            --text-muted: #52647d;
            --danger: #dc2626;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding:
                max(24px, env(safe-area-inset-top, 0px))
                max(24px, env(safe-area-inset-right, 0px))
                max(24px, env(safe-area-inset-bottom, 0px))
                max(24px, env(safe-area-inset-left, 0px));
            background-color: var(--bg-page);
            background-image:
                radial-gradient(circle at 10% 10%, rgba(48, 127, 226, 0.18) 0%, transparent 32%),
                radial-gradient(circle at 90% 88%, rgba(113, 197, 232, 0.18) 0%, transparent 30%),
                linear-gradient(145deg, #f9fbff 0%, #eef5ff 100%);
            color: var(--text-main);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            position: relative;
            overflow-x: hidden;
        }

        .ambient-mesh {
            position: absolute;
            inset: 0;
            pointer-events: none;
            opacity: 0;
            background: none;
        }

        .ambient-glow {
            position: absolute;
            width: min(500px, 100vw);
            height: min(500px, 100vw);
            border-radius: 50%;
            filter: blur(80px);
            pointer-events: none;
            opacity: 0.12;
        }
        .glow-1 { top: 0; left: 0; background: #0857c3; transform: translate(-35%, -35%); }
        .glow-2 { right: 0; bottom: 0; background: #71c5e8; transform: translate(35%, 35%); }

        @keyframes revealCard {
            from {
                opacity: 0;
                transform: translateY(24px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            padding: 38px 36px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.96);
            box-shadow:
                0 28px 70px -34px rgba(5, 59, 130, 0.42),
                0 1px 0 rgba(255, 255, 255, 0.9) inset;
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            animation: revealCard 0.7s cubic-bezier(0.16, 1, 0.3, 1) both;
            position: relative;
            z-index: 10;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            text-decoration: none;
            color: var(--text-main);
            border-radius: 14px;
        }

        .brand:focus-visible {
            outline: 3px solid rgba(8, 87, 195, 0.24);
            outline-offset: 4px;
        }

        .brand-icon-box {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: linear-gradient(135deg, #053b82 0%, #0857c3 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 18px -4px rgba(8, 87, 195, 0.45);
            padding: 8px;
        }

        .brand-icon-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .brand-text-wrap {
            display: flex;
            flex-direction: column;
        }

        .brand-badge {
            font-size: 0.65rem;
            font-weight: 800;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }

        .brand-title {
            font-size: 1.15rem;
            font-weight: 900;
            color: var(--text-main);
            letter-spacing: -0.02em;
            line-height: 1.2;
        }

        .login-headline {
            margin-bottom: 22px;
        }

        .login-headline h1 {
            font-size: 1.45rem;
            font-weight: 850;
            color: #0f172a;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }

        .login-headline p {
            margin-top: 4px;
            font-size: 0.8rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .status-box {
            margin-bottom: 20px;
            padding: 12px 14px;
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            background: #f0fdf4;
            color: #15803d;
            font-size: 0.86rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .field {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.82rem;
            font-weight: 750;
            color: #334155;
            letter-spacing: 0.01em;
        }

        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            height: 46px;
            padding: 0 14px;
            border: 1.5px solid #b9cee9;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 500;
            color: #102d52;
            background: #ffffff;
            outline: none;
            transition: border-color 0.2s ease, background-color 0.2s ease, box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        input::placeholder {
            color: #94a3b8;
            font-weight: 400;
        }

        input:hover {
            border-color: #94a3b8;
        }

        input:focus,
        input:focus-visible {
            border-color: var(--primary);
            background: #ffffff;
            box-shadow: 0 0 0 3.5px rgba(8, 87, 195, 0.16);
        }

        .error {
            margin: 6px 0 0;
            color: var(--danger);
            font-size: 0.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 6px 0 24px;
        }

        .remember {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 44px;
            color: #475569;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            user-select: none;
        }

        .remember input {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
            border-radius: 4px;
            cursor: pointer;
        }

        .live-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.7rem;
            font-weight: 750;
            color: #059669;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            padding: 2px 8px;
            border-radius: 999px;
        }

        .live-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 8px #10b981;
            animation: pulseDot 1.8s infinite;
        }

        @keyframes pulseDot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.85); }
        }

        button[type="submit"] {
            width: 100%;
            height: 48px;
            border: 0;
            border-radius: 12px;
            background: linear-gradient(135deg, #0857c3 0%, #1d4ed8 100%);
            color: #ffffff;
            font-size: 0.95rem;
            font-weight: 800;
            letter-spacing: 0.02em;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 10px 24px -4px rgba(8, 87, 195, 0.45);
            transition: background-color 0.18s ease, box-shadow 0.22s cubic-bezier(0.16, 1, 0.3, 1), transform 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        }

        button[type="submit"]:hover,
        button[type="submit"]:focus-visible {
            background: linear-gradient(135deg, #053b82 0%, #1e40af 100%);
            transform: translateY(-2px);
            box-shadow: 0 14px 28px -4px rgba(8, 87, 195, 0.55);
        }

        button[type="submit"]:focus-visible {
            outline: 3px solid rgba(8, 87, 195, 0.25);
            outline-offset: 4px;
        }

        button[type="submit"]:active {
            transform: translateY(0);
            box-shadow: 0 6px 14px -2px rgba(8, 87, 195, 0.4);
        }

        button[type="submit"]:disabled {
            background: #cbd5e1;
            color: #94a3b8;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        .btn-spinner {
            width: 18px;
            height: 18px;
            border: 2.5px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #ffffff;
            animation: btn-spin 0.65s linear infinite;
        }

        @keyframes btn-spin {
            to { transform: rotate(360deg); }
        }

        .card-footer-info {
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.72rem;
            color: #94a3b8;
            font-weight: 500;
        }

        @media (max-width: 480px) {
            body {
                align-items: flex-start;
                padding: 16px;
            }

            .login-card {
                padding: 28px 22px;
                border-radius: 16px;
            }

            .card-footer-info {
                align-items: flex-start;
                flex-direction: column;
                gap: 5px;
            }
        }

        @media (max-width: 320px) {
            body {
                padding: 10px;
            }

            .login-card {
                padding: 24px 16px;
                border-radius: 16px;
            }

            .brand {
                gap: 9px;
            }

            .brand-icon-box {
                width: 42px;
                height: 42px;
            }

            .brand-badge {
                font-size: 0.58rem;
                letter-spacing: 0.08em;
            }

            .brand-title {
                font-size: 1rem;
            }

            .remember-row {
                align-items: flex-start;
                flex-direction: column;
                margin-bottom: 20px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
    <div class="ambient-mesh" aria-hidden="true"></div>
    <div class="ambient-glow glow-1" aria-hidden="true"></div>
    <div class="ambient-glow glow-2" aria-hidden="true"></div>

    <main class="login-card">
        <a href="{{ url('/') }}" class="brand" aria-label="Beranda Dashboard A-Six">
            <div class="brand-icon-box">
                <img src="{{ asset('images/a-six-logo.svg') }}" alt="Logo A-Six">
            </div>
            <div class="brand-text-wrap">
                <span class="brand-badge">Area 6 Executive</span>
                <span class="brand-title">Dashboard A-Six</span>
            </div>
        </a>

        <div class="login-headline">
            <h1>Selamat Datang</h1>
            <p>Silakan masukkan identitas akun untuk mengakses monitoring kinerja.</p>
        </div>

        @if (session('status'))
            <div class="status-box" role="status">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="field">
                <label for="pn">Personal Number (PN)</label>
                <div class="input-wrap">
                    <input id="pn" type="text" name="pn" value="{{ old('pn') }}" placeholder="Contoh: 00123456" required autofocus autocomplete="username">
                </div>
                @error('pn')
                    <p class="error">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        <span>{{ $message }}</span>
                    </p>
                @enderror
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <input id="password" type="password" name="password" placeholder="Masukkan kata sandi" required autocomplete="current-password">
                </div>
                @error('password')
                    <p class="error">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        <span>{{ $message }}</span>
                    </p>
                @enderror
            </div>

            <div class="remember-row">
                <label for="remember_me" class="remember">
                    <input id="remember_me" type="checkbox" name="remember" value="1">
                    <span>Ingat saya</span>
                </label>
                <div class="live-tag">
                    <span class="live-dot"></span>
                    <span>Sistem Aktif</span>
                </div>
            </div>

            <button type="submit">
                <span>Masuk ke Dashboard</span>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
            </button>
        </form>

        <div class="card-footer-info">
            <span>A-SIX Realtime System</span>
            <span>&copy; {{ date('Y') }} PT Bank Rakyat Indonesia</span>
        </div>
    </main>

    <script>
        document.querySelector('form').addEventListener('submit', function (event) {
            const button = this.querySelector('button[type="submit"]');
            if (button) {
                window.setTimeout(function () {
                    button.disabled = true;
                    button.innerHTML = '<span class="btn-spinner"></span> <span>Memproses Masuk...</span>';
                }, 0);
            }
        });
    </script>
</body>
</html>

