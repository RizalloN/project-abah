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

        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 32px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
            backdrop-filter: blur(8px);
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
        }

        button:hover,
        button:focus {
            background: var(--primary-dark);
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
</body>
</html>
