<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, interactive-widget=resizes-content">
    <title>Database Tidak Tersedia</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f8ff;
            --panel: #ffffff;
            --text: #082b59;
            --muted: #52647d;
            --blue: #0857c3;
            --blue-dark: #053b82;
            --accent: #9a5b00;
            --border: #d6e6fb;
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
                radial-gradient(circle at 92% 88%, rgba(113, 197, 232, 0.14), transparent 28%),
                var(--bg);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text);
        }

        .card {
            width: min(100%, 680px);
            background: var(--panel);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
            border-radius: 16px;
            padding: 36px;
            box-shadow: 0 28px 76px -38px rgba(5, 59, 130, 0.42);
        }

        .card::before {
            position: absolute;
            inset: 0 0 auto;
            height: 5px;
            background: linear-gradient(90deg, var(--blue-dark), var(--blue), #71c5e8);
            content: "";
        }

        h1 {
            margin: 0 0 12px;
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            line-height: 1.12;
            letter-spacing: -0.035em;
            overflow-wrap: anywhere;
        }

        p {
            margin: 0 0 14px;
            line-height: 1.6;
            color: var(--muted);
        }

        .badge {
            display: inline-block;
            margin-bottom: 16px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #fff7e8;
            border: 1px solid #f3d59e;
            color: var(--accent);
            font-weight: 700;
            letter-spacing: 0.03em;
        }

        code {
            padding: 2px 6px;
            border-radius: 6px;
            background: #edf5ff;
            color: var(--blue-dark);
            overflow-wrap: anywhere;
        }

        @media (max-width: 480px) {
            body {
                padding: 16px;
            }

            .card {
                padding: 30px 24px 26px;
                border-radius: 16px;
            }
        }

        @media (max-width: 320px) {
            body {
                padding: 10px;
            }

            .card {
                padding: 26px 16px 22px;
                border-radius: 16px;
            }

            h1 {
                font-size: 1.7rem;
            }

            .badge {
                font-size: 0.82rem;
            }
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="badge">503 Service Unavailable</div>
        <h1>Koneksi database belum tersedia</h1>
        <p>Aplikasi berhasil dijalankan, tetapi saat ini tidak bisa terhubung ke server MySQL.</p>
        <p>Pastikan layanan MySQL atau MariaDB aktif di <code>127.0.0.1:3306</code>, lalu muat ulang halaman login atau halaman yang sedang dibuka.</p>
        <p>Jika Anda memakai XAMPP, jalankan modul <code>MySQL</code> terlebih dahulu. Setelah database aktif, proses login akan kembali normal.</p>
    </main>
</body>
</html>
