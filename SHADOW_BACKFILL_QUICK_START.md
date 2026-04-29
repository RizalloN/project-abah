# Quick Start: Perbaikan Shadow Columns dalam 5 Menit

## TL;DR - Solusi Cepat

Jalankan command ini di terminal project Anda:

```bash
# Langkah 1: Validasi kondisi awal (30 detik)
php artisan shadow:validate --periods=2026-04-25,2026-04-26

# Langkah 2: Preview tanpa perubahan data (1 menit)
php artisan shadow:backfill --periods=2026-04-25,2026-04-26 --dry-run

# Langkah 3: Jalankan backfill untuk periode terbaru (3-5 menit)
php artisan shadow:backfill --periods=2026-04-25,2026-04-26 --chunk-size=5000 --delay=1000

# Langkah 4: Validasi hasil (30 detik)
php artisan shadow:validate --periods=2026-04-25,2026-04-26
```

**Total waktu**: 5-8 menit untuk perbaikan lengkap + automatic snapshot rebuild + cache clearing.

---

## Apa Yang Terjadi?

✓ **Before**: Laporan Kinerja RM dan Mantri tampak kosong ("zonk")
```
Periode 2026-04-26: Nol baris dalam report
```

✓ **After**: Shadow columns terisi 100%, laporan tampil normal
```
Periode 2026-04-26: 323,635 rows terisi dengan data agregat yang benar
```

---

## Jika Terjadi Lock Timeout Error

**Gejala:**
```
SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded
```

**Solusi - Reduce chunk size:**
```bash
php artisan shadow:backfill \
  --periods=2026-04-25,2026-04-26 \
  --chunk-size=3000 \
  --delay=2000
```

---

## Monitoring Progress

**Cek status real-time (di terminal 2):**
```bash
php artisan shadow:validate --periods=2026-04-25,2026-04-26 --watch
```

Akan refresh otomatis setiap 5 detik sampai Ctrl+C.

---

## Verifikasi Hasil

**Setelah command selesai, cek laporan:**

1. Akses UI aplikasi: `http://localhost/project-ABAH`
2. Menu: **Laporan > Kinerja RM > Mikro (Mantri)**
3. Pilih periode: **2026-04-26**
4. ✓ Data harus muncul (bukan kosong)

**Database check:**
```sql
SELECT 
    periode,
    COUNT(*) as total,
    COUNT(CASE WHEN segmen_kinerja IS NOT NULL THEN 1 END) as filled
FROM daily_loan_dinamis
WHERE periode IN ('2026-04-25', '2026-04-26')
GROUP BY periode;

-- Output yang diharapkan:
-- periode    | total  | filled
-- 2026-04-25 | 323635 | 323635 ✓
-- 2026-04-26 | 200000 | 200000 ✓
```

---

## Default Behavior (Recommended)

Jika Anda hanya menjalankan:
```bash
php artisan shadow:backfill
```

Maka otomatis akan:
- ✓ Backfill periode **2026-04-25** dan **2026-04-26**
- ✓ Chunk size: **10,000 rows**
- ✓ Delay: **500ms** antara chunks
- ✓ Retry: **3 attempts** per chunk
- ✓ Rebuild snapshots otomatis
- ✓ Clear cache

---

## Untuk XAMPP Windows (Recommended Settings)

```bash
php artisan shadow:backfill \
  --periods=2026-04-25,2026-04-26 \
  --chunk-size=5000 \
  --delay=1000 \
  --retry-count=5
```

**Penjelasan:**
- `chunk-size=5000`: Lebih kecil untuk menghindari lock (XAMPP lebih lambat)
- `delay=1000`: Tunggu 1 detik antar chunk (beri waktu cleanup)
- `retry-count=5`: Retry lebih banyak jika terjadi temporary lock

---

## Alternative: Manual SQL (Jika Command Tidak Bisa Dijalankan)

**Jalankan di phpMyAdmin atau MySQL CLI:**

```sql
-- Backfill periode 2026-04-25 (bisa dilakukan per-periode untuk menghindari timeout)
UPDATE daily_loan_dinamis 
SET 
    segmen_kinerja = UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(segmen_dashboard, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', '')),
    produk_kinerja = UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(produk_dashboard, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', '')),
    cabang_normalized = UPPER(TRIM(COALESCE(cabang1, ''))),
    unit_normalized = UPPER(TRIM(COALESCE(unit1, ''))),
    branch_normalized = UPPER(TRIM(COALESCE(branch1, ''))),
    rm_normalized = UPPER(TRIM(COALESCE(pn_pengelola1, ''))),
    cifno_clean = REGEXP_REPLACE(COALESCE(cifno, ''), '[^0-9]', '')
WHERE periode = '2026-04-25' AND segmen_kinerja IS NULL
LIMIT 50000;

-- Ulangi hingga semua baris terisi
-- Check progress:
SELECT COUNT(*) FROM daily_loan_dinamis WHERE periode = '2026-04-25' AND segmen_kinerja IS NULL;
-- Hasil harus 0 ketika selesai

-- Lalu backfill periode 2026-04-26
UPDATE daily_loan_dinamis 
SET 
    segmen_kinerja = UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(segmen_dashboard, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', '')),
    produk_kinerja = UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(produk_dashboard, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', '')),
    cabang_normalized = UPPER(TRIM(COALESCE(cabang1, ''))),
    unit_normalized = UPPER(TRIM(COALESCE(unit1, ''))),
    branch_normalized = UPPER(TRIM(COALESCE(branch1, ''))),
    rm_normalized = UPPER(TRIM(COALESCE(pn_pengelola1, ''))),
    cifno_clean = REGEXP_REPLACE(COALESCE(cifno, ''), '[^0-9]', '')
WHERE periode = '2026-04-26' AND segmen_kinerja IS NULL
LIMIT 50000;

-- Setelah semua baris terisi, rebuild snapshots dari terminal
```

Kemudian rebuild snapshots:
```bash
php artisan snapshot:rebuild-rm --period=2026-04-25 --force
php artisan snapshot:rebuild-rm --period=2026-04-26 --force
php artisan cache:clear
```

---

## Checklist Eksekusi

- [ ] **Langkah 1**: Jalankan `php artisan shadow:validate --periods=2026-04-25,2026-04-26`
- [ ] **Langkah 2**: Catat jumlah NULL sebelum backfill
- [ ] **Langkah 3**: Jalankan `php artisan shadow:backfill --chunk-size=5000 --delay=1000`
- [ ] **Langkah 4**: Tunggu hingga selesai (3-8 menit)
- [ ] **Langkah 5**: Jalankan validation lagi untuk confirm 100% filled
- [ ] **Langkah 6**: Akses aplikasi dan test laporan Kinerja RM
- [ ] **Langkah 7**: Dokumentasikan hasil di log/issue

---

## Timeout Terjadi? Restart dari Sini

Jika process terganggu/timeout di tengah jalan:

```bash
# Reset kolom shadow untuk periode
UPDATE daily_loan_dinamis 
SET 
    segmen_kinerja = NULL,
    produk_kinerja = NULL,
    cabang_normalized = NULL,
    unit_normalized = NULL,
    branch_normalized = NULL,
    rm_normalized = NULL,
    cifno_clean = NULL
WHERE periode IN ('2026-04-25', '2026-04-26');

# Run backfill lagi dengan setting lebih konservatif
php artisan shadow:backfill --periods=2026-04-25,2026-04-26 --chunk-size=2000 --delay=2000 --retry-count=5
```

---

**Questions?** Lihat file `SHADOW_BACKFILL_GUIDE.md` untuk dokumentasi lengkap dan troubleshooting detail.

**Status**: Ready to execute on 2026-04-29
