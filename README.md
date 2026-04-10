# Project ABAH

## Local Development

- Gunakan `Apache` dan `MySQL` dari XAMPP sebagai workflow lokal utama.
- Buka aplikasi di:
  `http://localhost/project-ABAH/`
- Jangan jadikan `php artisan serve` sebagai server normal untuk project ini.

## Frontend Assets

- Asset build statis tersedia di `public/build`, jadi app tetap bisa jalan tanpa `npm run dev`.
- Jalankan Vite hanya jika memang sedang butuh HMR:

```powershell
npm.cmd install
npm.cmd run dev
```

## Troubleshooting Blank / Buffer Page

Jika browser berhenti di `Loading...` atau halaman blank:

1. Tutup tab yang sedang reload terus.
2. Jalankan recovery script:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\reset-local-web.ps1
```

3. Jika perlu, restart Apache lalu refresh browser.

Panduan lengkap ada di [START_PROJECT.md](START_PROJECT.md).
