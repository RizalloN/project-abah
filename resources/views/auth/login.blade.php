<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - BRI DigiBranch Area 6</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bri-nusantara: #0857c3;
            --bri-cakrawala: #307fe2;
            --bri-mentari: #71c5e8;
            --bri-night: #053b82;
            --bri-ink: #153f79;
            --bri-bg: #eaf2ff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Inter", sans-serif;
            background: var(--bri-bg);
            color: #0f172a;
            min-height: 100vh;
        }

        .login-shell {
            min-height: 100vh;
            padding: 28px;
            background:
                radial-gradient(circle at 10% 5%, rgba(113, 197, 232, 0.28), transparent 34%),
                radial-gradient(circle at 95% 0%, rgba(48, 127, 226, 0.24), transparent 26%),
                linear-gradient(135deg, #053b82 0%, #0857c3 54%, #307fe2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            width: 100%;
            max-width: 1220px;
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            border-radius: 30px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 42px 90px -36px rgba(4, 42, 95, 0.66);
            backdrop-filter: blur(8px);
            background: rgba(255, 255, 255, 0.08);
        }

        .left-panel {
            padding: 48px;
            color: #ffffff;
            border-right: 1px solid rgba(255, 255, 255, 0.18);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 30px;
        }

        .left-brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: rgba(255, 255, 255, 0.92);
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.28em;
        }

        .left-brand-badge {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255, 255, 255, 0.34);
            background: rgba(255, 255, 255, 0.14);
            font-size: 0.95rem;
            font-weight: 800;
        }

        .left-eyebrow {
            margin-top: 54px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.35em;
            color: #cde8ff;
        }

        .left-title {
            margin: 14px 0 0;
            font-size: clamp(2rem, 3.4vw, 3.15rem);
            line-height: 1.1;
            font-weight: 800;
        }

        .left-copy {
            margin: 20px 0 0;
            max-width: 560px;
            color: #d8ebff;
            font-size: 1rem;
            line-height: 1.7;
        }

        .left-features {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .left-feature {
            padding: 18px;
            border-radius: 18px;
            border: 1px solid rgba(255, 255, 255, 0.26);
            background: rgba(255, 255, 255, 0.1);
        }

        .left-feature-title {
            margin: 0;
            font-size: 0.92rem;
            font-weight: 700;
        }

        .left-feature-copy {
            margin: 8px 0 0;
            font-size: 0.87rem;
            line-height: 1.6;
            color: #d6e9ff;
        }

        .right-panel {
            background: rgba(255, 255, 255, 0.97);
            padding: 52px 56px;
        }

        .mobile-brand {
            display: none;
        }

        .right-eyebrow {
            margin: 0;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.34em;
            text-transform: uppercase;
            color: var(--bri-nusantara);
        }

        .right-title {
            margin: 12px 0 0;
            font-size: clamp(1.95rem, 3vw, 2.5rem);
            line-height: 1.15;
            color: var(--bri-night);
            font-weight: 800;
        }

        .right-copy {
            margin: 14px 0 0;
            color: #5b6f8c;
            font-size: 1rem;
            line-height: 1.65;
            max-width: 500px;
        }

        .status-box {
            margin-top: 22px;
            border-radius: 14px;
            border: 1px solid #bbf7d0;
            background: #ecfdf3;
            color: #166534;
            padding: 12px 14px;
            font-size: 0.88rem;
            font-weight: 600;
        }

        .login-form {
            margin-top: 28px;
        }

        .field {
            margin-bottom: 16px;
        }

        .field label {
            display: block;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--bri-ink);
            margin-bottom: 8px;
        }

        .field input[type="text"],
        .field input[type="password"] {
            width: 100%;
            border-radius: 16px;
            border: 1px solid #c7dcfb;
            background: #f4f9ff;
            padding: 12px 14px;
            font-size: 0.94rem;
            color: #0f172a;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
        }

        .field input::placeholder {
            color: #8ba2c0;
        }

        .field input:focus {
            border-color: var(--bri-cakrawala);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(48, 127, 226, 0.16);
        }

        .error {
            margin-top: 6px;
            font-size: 0.86rem;
            color: #dc2626;
            font-weight: 600;
        }

        .form-foot {
            margin-top: 4px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .remember {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
            color: #4d6382;
        }

        .remember input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--bri-nusantara);
        }

        .internal-label {
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.25em;
            text-transform: uppercase;
            color: #6d87ab;
        }

        .submit-wrap {
            margin-top: 20px;
        }

        .submit-btn {
            width: 100%;
            border: none;
            border-radius: 16px;
            padding: 13px 16px;
            background: linear-gradient(130deg, var(--bri-nusantara), var(--bri-cakrawala));
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 0.22em;
            font-size: 0.82rem;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 16px 26px -18px rgba(8, 87, 195, 0.8);
            transition: transform 0.15s ease, filter 0.15s ease;
        }

        .submit-btn:hover {
            filter: brightness(1.08);
            transform: translateY(-1px);
        }

        .submit-btn:focus {
            outline: none;
            box-shadow: 0 0 0 4px rgba(48, 127, 226, 0.2);
        }

        @media (max-width: 1024px) {
            .login-card {
                grid-template-columns: 1fr;
                max-width: 780px;
            }

            .left-panel {
                display: none;
            }

            .right-panel {
                padding: 34px 28px;
            }

            .mobile-brand {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                text-decoration: none;
                color: var(--bri-nusantara);
                text-transform: uppercase;
                letter-spacing: 0.22em;
                font-weight: 700;
                font-size: 0.76rem;
                margin-bottom: 22px;
            }

            .mobile-brand-badge {
                width: 38px;
                height: 38px;
                border-radius: 12px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-weight: 800;
                color: #fff;
                background: linear-gradient(130deg, var(--bri-nusantara), var(--bri-cakrawala));
                box-shadow: 0 12px 20px -14px rgba(8, 87, 195, 0.72);
            }
        }

        @media (max-width: 640px) {
            .login-shell {
                padding: 16px;
            }

            .right-panel {
                padding: 26px 18px;
            }
        }
    </style>
</head>
<body>
<div class="login-shell">
    <div class="login-card">
        <section class="left-panel">
            <div>
            <a href="{{ url('/') }}" class="left-brand">
                    <span class="left-brand-badge">BRI</span>
                    BRI DigiBranch
                </a>

                <p class="left-eyebrow">BRI Performance Portal</p>
                <h1 class="left-title">Dashboard Area 6</h1>
                <p class="left-copy">Akses ringkas ke laporan utama dalam satu portal.</p>
            </div>

            <div class="left-features">
                <article class="left-feature">
                    <p class="left-feature-title">Warna BRI Konsisten</p>
                    <p class="left-feature-copy">Warna utama tetap konsisten.</p>
                </article>
                <article class="left-feature">
                    <p class="left-feature-title">Akses Internal Aman</p>
                    <p class="left-feature-copy">Masuk dengan PN dan password.</p>
                </article>
            </div>
        </section>

        <section class="right-panel">
                <a href="{{ url('/') }}" class="mobile-brand">
                <span class="mobile-brand-badge">BRI</span>
                BRI DigiBranch
            </a>

            <p class="right-eyebrow">Selamat Datang</p>
            <h2 class="right-title">Masuk ke akun</h2>
            <p class="right-copy">Masuk dengan PN dan password.</p>

            @if (session('status'))
                <div class="status-box">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="login-form">
                @csrf

                <div class="field">
                    <label for="pn">PN</label>
                    <input
                        id="pn"
                        type="text"
                        name="pn"
                        value="{{ old('pn') }}"
                        placeholder="PN Anda"
                        required
                        autofocus
                    />
                    @error('pn')
                        <p class="error">{{ $message }}</p>
                    @enderror
                    @error('personal_number')
                        <p class="error">{{ $message }}</p>
                    @enderror
                    @error('email')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        placeholder="Password Anda"
                        required
                    />
                    @error('password')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-foot">
                    <label for="remember_me" class="remember">
                        <input id="remember_me" type="checkbox" name="remember">
                    <span>Ingat saya</span>
                    </label>
                    <span class="internal-label">Area 6 Internal</span>
                </div>

                <div class="submit-wrap">
                    <button type="submit" class="submit-btn">Masuk</button>
                </div>
            </form>
        </section>
    </div>
</div>
</body>
</html>
