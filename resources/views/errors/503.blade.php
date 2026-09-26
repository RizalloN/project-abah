<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, interactive-widget=resizes-content">
    <meta http-equiv="refresh" content="30">
    <title>A-Six Dashboard Maintenance</title>
    <style>
        :root {
            color-scheme: light;
            --blue: #0f52ba;
            --blue-dark: #053b82;
            --cyan: #71c5e8;
            --ink: #082b59;
            --muted: #52647d;
            --panel: #ffffff;
            --line: #d6e6fb;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding:
                max(24px, env(safe-area-inset-top, 0px))
                max(24px, env(safe-area-inset-right, 0px))
                max(24px, env(safe-area-inset-bottom, 0px))
                max(24px, env(safe-area-inset-left, 0px));
            background:
                radial-gradient(circle at 8% 12%, rgba(48, 127, 226, 0.16), transparent 30%),
                radial-gradient(circle at 92% 90%, rgba(113, 197, 232, 0.15), transparent 28%),
                #f4f8ff;
            color: var(--ink);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        .maintenance-card {
            width: min(100%, 720px);
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--panel);
            box-shadow: 0 28px 76px -38px rgba(5, 59, 130, 0.42);
        }

        .maintenance-header {
            padding: 28px 32px;
            background: linear-gradient(135deg, var(--blue-dark), var(--blue) 68%, #307fe2);
            color: #fff;
        }

        .brand {
            margin: 0 0 8px;
            font-size: 0.8rem;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            opacity: 0.9;
        }

        h1 {
            margin: 0;
            font-size: clamp(2rem, 4vw, 3rem);
            line-height: 1.08;
            letter-spacing: -0.035em;
            overflow-wrap: anywhere;
        }

        .maintenance-body {
            padding: 30px 32px 34px;
        }

        p {
            margin: 0 0 16px;
            color: var(--muted);
            font-size: 1rem;
            line-height: 1.65;
        }

        .status-row {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-top: 22px;
        }

        .status-item {
            min-height: 92px;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 14px;
            background: #f7faff;
        }

        .label {
            display: block;
            margin-bottom: 8px;
            color: #475569;
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .value {
            color: var(--blue);
            font-size: 1.15rem;
            font-weight: 800;
        }

        .progress {
            height: 9px;
            margin-top: 20px;
            overflow: hidden;
            border-radius: 999px;
            background: #e2e8f0;
        }

        .progress span {
            display: block;
            width: 42%;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--blue), var(--cyan));
            animation: pulse 1.6s ease-in-out infinite alternate;
        }

        @keyframes pulse {
            from { transform: translateX(-20%); }
            to { transform: translateX(155%); }
        }

        @media (max-width: 640px) {
            .maintenance-header,
            .maintenance-body {
                padding: 24px;
            }

            .status-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 320px) {
            body {
                padding: 10px;
            }

            .maintenance-card {
                border-radius: 16px;
            }

            .maintenance-header,
            .maintenance-body {
                padding: 20px 16px;
            }

            h1 {
                font-size: 1.8rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .progress span {
                width: 100%;
                animation: none;
            }
        }
    </style>
</head>
<body>
    <main class="maintenance-card" role="main" aria-labelledby="maintenance-title">
        <section class="maintenance-header">
            <p class="brand">A-Six Dashboard Portal</p>
            <h1 id="maintenance-title">Website sedang maintenance</h1>
        </section>

        <section class="maintenance-body">
            <p>Database sedang dioptimalkan agar akses dashboard, import, dan report kembali lebih ringan. Proses dilakukan bertahap pada tabel besar untuk mengurangi durasi lock.</p>
            <p>Halaman ini akan mencoba memuat ulang otomatis setiap 30 detik.</p>

            <div class="status-row" aria-label="Status maintenance">
                <div class="status-item">
                    <span class="label">Status</span>
                    <span class="value">Maintenance</span>
                </div>
                <div class="status-item">
                    <span class="label">Fokus</span>
                    <span class="value">Optimasi Database</span>
                </div>
                <div class="status-item">
                    <span class="label">Refresh</span>
                    <span class="value">30 detik</span>
                </div>
            </div>

            <div class="progress" aria-hidden="true"><span></span></div>
        </section>
    </main>
</body>
</html>
