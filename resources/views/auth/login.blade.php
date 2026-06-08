<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Dashboard A-Six</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/a-six-logo.svg') }}">
    <style>
        :root {
            --primary: #f97316;
            --primary-dark: #ea580c;
            --bg: #f8fafc;
            --border: #e2e8f0;
            --text: #0f172a;
            --muted: #64748b;
            --danger: #dc2626;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background:
                linear-gradient(135deg, rgba(15, 23, 42, 0.82), rgba(249, 115, 22, 0.58)),
                url("{{ asset('images/bri-logo.png') }}") center center / min(520px, 70vw) no-repeat,
                var(--bg);
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
        }

        @keyframes revealCard {
            from {
                opacity: 0;
                transform: translateY(18px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 32px;
            border: 1px solid rgba(8, 87, 195, 0.08);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.88);
            box-shadow: 
                0 20px 45px rgba(8, 87, 195, 0.08), 
                0 10px 20px rgba(15, 23, 42, 0.04);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            animation: revealCard 0.65s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
            text-decoration: none;
            color: var(--text);
            font-weight: 700;
        }

        .brand img {
            width: 36px;
            height: 36px;
        }

        h1 {
            margin: 0 0 24px;
            font-size: 1.35rem;
            line-height: 1.25;
        }

        .status-box {
            margin-bottom: 18px;
            padding: 10px 12px;
            border: 1px solid #bbf7d0;
            border-radius: 6px;
            background: #f0fdf4;
            color: #166534;
            font-size: 0.9rem;
        }

        .field {
            margin-bottom: 16px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-size: 0.92rem;
            font-weight: 700;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.2s ease;
        }

        input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.14);
        }

        .error {
            margin: 6px 0 0;
            color: var(--danger);
            font-size: 0.85rem;
            font-weight: 600;
        }

        .remember {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 2px 0 20px;
            color: var(--muted);
            font-size: 0.92rem;
        }

        .remember input {
            width: 16px;
            height: 16px;
            accent-color: var(--primary);
        }

        button {
            width: 100%;
            padding: 12px 16px;
            border: 0;
            border-radius: 6px;
            background: var(--primary);
            color: #ffffff;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        button:hover,
        button:focus {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.2);
        }

        button:active {
            transform: translateY(0);
        }

        button:disabled {
            background: #cbd5e1;
            color: #94a3b8;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        /* Button spinner style */
        .btn-spinner {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #ffffff;
            animation: btn-spin 0.6s linear infinite;
        }

        @keyframes btn-spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 480px) {
            body {
                padding: 16px;
            }

            .login-card {
                padding: 24px;
            }
        }
    </style>
</head>
<body>
    <main class="login-card">
        <a href="{{ url('/') }}" class="brand">
            <img src="{{ asset('images/a-six-logo.svg') }}" alt="Logo A-Six">
            <span>Dashboard A-Six</span>
        </a>

        <h1>Login</h1>

        @if (session('status'))
            <div class="status-box">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="field">
                <label for="pn">PN</label>
                <input id="pn" type="text" name="pn" value="{{ old('pn') }}" required autofocus autocomplete="username">
                @error('pn')
                    <p class="error">{{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">
                @error('password')
                    <p class="error">{{ $message }}</p>
                @enderror
            </div>

            <label for="remember_me" class="remember">
                <input id="remember_me" type="checkbox" name="remember" value="1">
                <span>Ingat saya</span>
            </label>

            <button type="submit">Masuk</button>
        </form>
    </main>

    <script>
        document.querySelector('form').addEventListener('submit', function (event) {
            const button = this.querySelector('button[type="submit"]');
            if (button) {
                // Use setTimeout to ensure native validation runs first
                window.setTimeout(function () {
                    button.disabled = true;
                    button.innerHTML = '<span class="btn-spinner"></span> <span>Memproses...</span>';
                }, 0);
            }
        });
    </script>
</body>
</html>
