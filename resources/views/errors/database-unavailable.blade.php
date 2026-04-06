<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Tidak Tersedia</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f7f4ee;
            --panel: #fffdf8;
            --text: #1f2937;
            --muted: #6b7280;
            --accent: #a16207;
            --border: #e5dccd;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background:
                radial-gradient(circle at top left, rgba(161, 98, 7, 0.12), transparent 28%),
                linear-gradient(180deg, #f9f5ee 0%, var(--bg) 100%);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text);
        }

        .card {
            width: min(100%, 680px);
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 32px;
            box-shadow: 0 20px 50px rgba(31, 41, 55, 0.08);
        }

        h1 {
            margin: 0 0 12px;
            font-size: clamp(1.8rem, 4vw, 2.5rem);
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
            background: rgba(161, 98, 7, 0.12);
            color: var(--accent);
            font-weight: 700;
            letter-spacing: 0.03em;
        }

        code {
            padding: 2px 6px;
            border-radius: 6px;
            background: #f3efe6;
            color: #92400e;
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
