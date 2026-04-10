# Local Start Guide

Gunakan workflow ini untuk development lokal yang stabil di Windows.

## Jalankan Project

1. Start `Apache` dan `MySQL` dari XAMPP.
2. Buka aplikasi di:
   `http://localhost/project-ABAH/`
3. Jangan pakai `php artisan serve` untuk workflow normal project ini.

## Frontend / Vite

- Default-nya aplikasi tetap bisa jalan tanpa `npm run dev` karena asset build ada di `public/build`.
- Jalankan Vite hanya jika memang sedang butuh HMR frontend:

```powershell
npm.cmd install
npm.cmd run dev
```

- Jika PowerShell memblokir `npm`, gunakan `npm.cmd` atau `cmd /c npm run dev`.

## Recovery Saat Halaman Buffer / Blank

Gejala yang biasanya muncul:
- tab browser berhenti di `Loading...`
- route Laravel terlihat terpanggil, tapi halaman kosong
- log berisi error `rename(...tmp, ...php): Access is denied`

Langkah recovery:

1. Tutup tab browser yang sedang spam reload.
2. Jika perlu, stop `Apache` sebentar.
3. Jalankan:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\reset-local-web.ps1
```

4. Start ulang `Apache`.
5. Hard refresh browser dengan `Ctrl+F5`.

Script recovery akan:
- membersihkan compiled Blade cache
- membersihkan legacy view cache
- menjalankan `php artisan optimize:clear`

## Rekomendasi Windows

- Jika memungkinkan, exclude folder project ini dari antivirus/indexer.
- Prioritaskan exclude:
  - `storage/framework/cache/blade`
  - `storage/framework/views`
- Jangan buka file compiled Blade hasil generate di editor/tools.
- Hindari menjalankan `Apache`, `php artisan serve`, dan auto-reload agresif secara bersamaan.
