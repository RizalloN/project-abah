# Backup & Restore Database Harian

Backup berjalan otomatis setiap hari pukul 00:00 WIB (`database:backup-daily`, Windows Task Scheduler dan Laravel scheduler) ke `D:\BACKUP PROJECT ABAH`. Hanya satu backup terverifikasi yang disimpan; backup lama dihapus setelah backup baru lolos verifikasi.

## Cara kerja (mode `chunked`)

| Bagian | Isi |
| --- | --- |
| `schema.sql.gz` | Struktur seluruh tabel (tanpa trigger) |
| `data/<tabel>.sql.gz` | Data tabel kecil, di-dump penuh setiap malam |
| `data/<tabel>/<periode>.sql.gz` | Data tabel besar per periode (`daily_loan_dinamis`, `simpanan_multipn`, `dashboard_pinjaman_snapshots`, `dashboard_pinjaman_chart_periodik_snapshots`, `lw325_ph`) |
| `post-data.sql.gz` | Trigger, dimuat paling akhir agar tidak berjalan saat restore |
| `manifest.json` | Daftar part, jumlah baris per periode, ukuran, dan SHA-256 tiap file |

- Periode yang identitasnya tidak berubah (jumlah baris + rentang primary key + struktur tabel) **dipakai ulang** dari backup sebelumnya melalui hard link: tanpa dump ulang dan tanpa tambahan ruang disk.
- Re-import, penghapusan, atau rebuild sebuah periode mengubah identitasnya sehingga periode itu di-dump ulang.
- Penyegaran bergilir: setiap part di-dump ulang paling lambat 7 hari sekali (`DATABASE_DAILY_BACKUP_MAX_REUSE_DAYS`); bebannya dibagi rata per malam. Ini menutup perubahan in-place yang tidak mengubah identitas periode.
- Maksimal 3 proses `mysqldump` bersamaan (`DATABASE_DAILY_BACKUP_WORKERS`). Backup menunggu hingga 30 menit bila ada import berjalan.
- Setiap part baru dibaca ulang dan dicocokkan checksum-nya sebelum backup dipublikasikan secara atomik.
- Isi `cache`, `cache_locks`, `sessions`, `jobs`, `job_batches`, `failed_jobs`, dan tabel staging `tmp_bulk_csv_stage_*` tidak di-backup (hanya strukturnya).

## Restore darurat

1. Hentikan akses aplikasi (maintenance) bila restore menimpa database utama.
2. Uji dahulu ke database terpisah:

   ```powershell
   php artisan database:restore-daily --database=project_abah_restore
   ```

   Perintah memverifikasi checksum semua part, membuat database tujuan, memuat struktur → data (paralel) → trigger, lalu mencocokkan jumlah baris setiap periode dengan manifest. Exit code bukan 0 berarti ada ketidakcocokan.
3. Bila hasilnya benar, pilih salah satu:
   - Arahkan `DB_DATABASE` di `.env` ke `project_abah_restore`, lalu `php artisan config:clear`; atau
   - Restore langsung ke database utama: `php artisan database:restore-daily --database=project_abah --force-live`.

Opsi lain: `--backup="backup project-abah DDMMYYYY"` memilih folder tertentu, `--tables=a,b` memulihkan data tabel tertentu saja (struktur tetap lengkap), `--workers=N` mengatur paralelisme.

Restore memakai MariaDB yang sama (XAMPP, `D:\XAMPP\mysql\bin\mysql.exe`). Nilai waktu disimpan dalam UTC oleh `mysqldump` dan dikembalikan apa adanya.

## Konfigurasi (`config/database_backup.php`)

| Env | Default | Fungsi |
| --- | --- | --- |
| `DATABASE_DAILY_BACKUP_MODE` | `chunked` | `single` mengembalikan format satu file lama |
| `DATABASE_DAILY_BACKUP_WORKERS` | `3` | Proses dump paralel (1–6) |
| `DATABASE_DAILY_BACKUP_MAX_REUSE_DAYS` | `7` | Batas umur part yang dipakai ulang |
| `DATABASE_DAILY_BACKUP_IMPORT_WAIT_MINUTES` | `30` | Tunggu import selesai sebelum backup |
| `DATABASE_DAILY_BACKUP_DIRECTORY` | `D:\BACKUP PROJECT ABAH` | Folder backup (harus satu volume NTFS agar hard link berfungsi) |
