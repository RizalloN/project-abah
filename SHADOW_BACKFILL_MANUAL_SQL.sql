-- ================================================================
-- SHADOW COLUMNS BACKFILL - Manual SQL Script
-- ================================================================
-- 
-- Gunakan script ini jika command artisan tidak bisa dijalankan
-- Jalankan via phpMyAdmin atau MySQL CLI dengan copy-paste per bagian
--
-- PENTING: Jalankan per bagian (jangan semua sekaligus) untuk menghindari timeout
-- 
-- ================================================================

-- ================================================================
-- BAGIAN 1: Validasi kondisi awal (JALANKAN DULU)
-- ================================================================

-- Check: Berapa banyak NULL di periode 2026-04-25 & 2026-04-26?
SELECT 
    periode,
    COUNT(*) as total_rows,
    COUNT(CASE WHEN segmen_kinerja IS NOT NULL THEN 1 END) as segmen_filled,
    COUNT(CASE WHEN produk_kinerja IS NOT NULL THEN 1 END) as produk_filled,
    COUNT(CASE WHEN cabang_normalized IS NOT NULL THEN 1 END) as cabang_filled,
    COUNT(CASE WHEN cifno_clean IS NOT NULL THEN 1 END) as cifno_filled,
    COUNT(CASE WHEN segmen_kinerja IS NULL THEN 1 END) as total_null_shadow
FROM daily_loan_dinamis
WHERE periode IN ('2026-04-25', '2026-04-26')
GROUP BY periode;

-- Expected output SEBELUM backfill:
-- periode    | total_rows | segmen_filled | produk_filled | cabang_filled | cifno_filled | total_null_shadow
-- 2026-04-25 | 323635     | 0             | 0             | 0             | 0            | 323635
-- 2026-04-26 | 200000     | 0             | 0             | 0             | 0            | 200000

-- ================================================================
-- BAGIAN 2: BACKFILL PERIODE 2026-04-25
-- ================================================================

-- STEP 2A: Backfill batch 1 (rows 1-50000)
UPDATE daily_loan_dinamis 
SET 
    segmen_kinerja = UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(segmen_dashboard, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', '')),
    produk_kinerja = UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(produk_dashboard, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', '')),
    cabang_normalized = UPPER(TRIM(COALESCE(cabang1, ''))),
    unit_normalized = UPPER(TRIM(COALESCE(unit1, ''))),
    branch_normalized = UPPER(TRIM(COALESCE(branch1, ''))),
    rm_normalized = UPPER(TRIM(COALESCE(pn_pengelola1, ''))),
    cifno_clean = REGEXP_REPLACE(COALESCE(cifno, ''), '[^0-9]', '')
WHERE periode = '2026-04-25'
    AND (segmen_kinerja IS NULL OR produk_kinerja IS NULL OR cabang_normalized IS NULL)
LIMIT 50000;

-- WAIT 30 SECONDS - beri waktu lock untuk release
-- (Manual pause)

-- Check progress:
SELECT COUNT(*) as remaining_null 
FROM daily_loan_dinamis 
WHERE periode = '2026-04-25' AND segmen_kinerja IS NULL;

-- Jika result > 0, lanjut ke STEP 2B
-- ================================================================

-- STEP 2B: Backfill batch 2 (rows 50001-100000)
UPDATE daily_loan_dinamis 
SET 
    segmen_kinerja = UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(segmen_dashboard, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', '')),
    produk_kinerja = UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(produk_dashboard, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', '')),
    cabang_normalized = UPPER(TRIM(COALESCE(cabang1, ''))),
    unit_normalized = UPPER(TRIM(COALESCE(unit1, ''))),
    branch_normalized = UPPER(TRIM(COALESCE(branch1, ''))),
    rm_normalized = UPPER(TRIM(COALESCE(pn_pengelola1, ''))),
    cifno_clean = REGEXP_REPLACE(COALESCE(cifno, ''), '[^0-9]', '')
WHERE periode = '2026-04-25'
    AND (segmen_kinerja IS NULL OR produk_kinerja IS NULL OR cabang_normalized IS NULL)
LIMIT 50000;

-- WAIT 30 SECONDS

-- Check progress:
SELECT COUNT(*) as remaining_null 
FROM daily_loan_dinamis 
WHERE periode = '2026-04-25' AND segmen_kinerja IS NULL;

-- Jika result > 0, lanjut ke STEP 2C
-- ================================================================

-- STEP 2C: Backfill batch 3 (rows 100001-150000)
UPDATE daily_loan_dinamis 
SET 
    segmen_kinerja = UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(segmen_dashboard, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', '')),
    produk_kinerja = UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(produk_dashboard, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', '')),
    cabang_normalized = UPPER(TRIM(COALESCE(cabang1, ''))),
    unit_normalized = UPPER(TRIM(COALESCE(unit1, ''))),
    branch_normalized = UPPER(TRIM(COALESCE(branch1, ''))),
    rm_normalized = UPPER(TRIM(COALESCE(pn_pengelola1, ''))),
    cifno_clean = REGEXP_REPLACE(COALESCE(cifno, ''), '[^0-9]', '')
WHERE periode = '2026-04-25'
    AND (segmen_kinerja IS NULL OR produk_kinerja IS NULL OR cabang_normalized IS NULL)
LIMIT 50000;

-- WAIT 30 SECONDS

-- Check progress:
SELECT COUNT(*) as remaining_null 
FROM daily_loan_dinamis 
WHERE periode = '2026-04-25' AND segmen_kinerja IS NULL;

-- Jika result > 0, lanjut ke STEP 2D
-- ================================================================

-- STEP 2D: Backfill batch 4 (sisa rows)
UPDATE daily_loan_dinamis 
SET 
    segmen_kinerja = UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(segmen_dashboard, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', '')),
    produk_kinerja = UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(produk_dashboard, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', '')),
    cabang_normalized = UPPER(TRIM(COALESCE(cabang1, ''))),
    unit_normalized = UPPER(TRIM(COALESCE(unit1, ''))),
    branch_normalized = UPPER(TRIM(COALESCE(branch1, ''))),
    rm_normalized = UPPER(TRIM(COALESCE(pn_pengelola1, ''))),
    cifno_clean = REGEXP_REPLACE(COALESCE(cifno, ''), '[^0-9]', '')
WHERE periode = '2026-04-25'
    AND (segmen_kinerja IS NULL OR produk_kinerja IS NULL OR cabang_normalized IS NULL)
LIMIT 100000;

-- WAIT 30 SECONDS

-- Verify periode 2026-04-25 selesai:
SELECT COUNT(*) as remaining_null 
FROM daily_loan_dinamis 
WHERE periode = '2026-04-25' AND segmen_kinerja IS NULL;

-- Result harus = 0

-- ================================================================
-- BAGIAN 3: BACKFILL PERIODE 2026-04-26
-- ================================================================

-- STEP 3A: Backfill batch 1 untuk 2026-04-26
UPDATE daily_loan_dinamis 
SET 
    segmen_kinerja = UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(segmen_dashboard, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', '')),
    produk_kinerja = UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(produk_dashboard, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', '')),
    cabang_normalized = UPPER(TRIM(COALESCE(cabang1, ''))),
    unit_normalized = UPPER(TRIM(COALESCE(unit1, ''))),
    branch_normalized = UPPER(TRIM(COALESCE(branch1, ''))),
    rm_normalized = UPPER(TRIM(COALESCE(pn_pengelola1, ''))),
    cifno_clean = REGEXP_REPLACE(COALESCE(cifno, ''), '[^0-9]', '')
WHERE periode = '2026-04-26'
    AND (segmen_kinerja IS NULL OR produk_kinerja IS NULL OR cabang_normalized IS NULL)
LIMIT 50000;

-- WAIT 30 SECONDS

-- Check progress:
SELECT COUNT(*) as remaining_null 
FROM daily_loan_dinamis 
WHERE periode = '2026-04-26' AND segmen_kinerja IS NULL;

-- Jika result > 0, lanjut ke STEP 3B
-- ================================================================

-- STEP 3B: Backfill batch 2 untuk 2026-04-26 (sisa)
UPDATE daily_loan_dinamis 
SET 
    segmen_kinerja = UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(segmen_dashboard, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', '')),
    produk_kinerja = UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(produk_dashboard, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', '')),
    cabang_normalized = UPPER(TRIM(COALESCE(cabang1, ''))),
    unit_normalized = UPPER(TRIM(COALESCE(unit1, ''))),
    branch_normalized = UPPER(TRIM(COALESCE(branch1, ''))),
    rm_normalized = UPPER(TRIM(COALESCE(pn_pengelola1, ''))),
    cifno_clean = REGEXP_REPLACE(COALESCE(cifno, ''), '[^0-9]', '')
WHERE periode = '2026-04-26'
    AND (segmen_kinerja IS NULL OR produk_kinerja IS NULL OR cabang_normalized IS NULL)
LIMIT 100000;

-- WAIT 30 SECONDS

-- Verify periode 2026-04-26 selesai:
SELECT COUNT(*) as remaining_null 
FROM daily_loan_dinamis 
WHERE periode = '2026-04-26' AND segmen_kinerja IS NULL;

-- Result harus = 0

-- ================================================================
-- BAGIAN 4: Validasi hasil akhir
-- ================================================================

-- Jalankan query validasi awal lagi untuk confirm 100% terisi:
SELECT 
    periode,
    COUNT(*) as total_rows,
    COUNT(CASE WHEN segmen_kinerja IS NOT NULL THEN 1 END) as segmen_filled,
    COUNT(CASE WHEN produk_kinerja IS NOT NULL THEN 1 END) as produk_filled,
    COUNT(CASE WHEN cabang_normalized IS NOT NULL THEN 1 END) as cabang_filled,
    COUNT(CASE WHEN cifno_clean IS NOT NULL THEN 1 END) as cifno_filled,
    COUNT(CASE WHEN segmen_kinerja IS NULL THEN 1 END) as total_null_shadow
FROM daily_loan_dinamis
WHERE periode IN ('2026-04-25', '2026-04-26')
GROUP BY periode;

-- Expected output SETELAH backfill:
-- periode    | total_rows | segmen_filled | produk_filled | cabang_filled | cifno_filled | total_null_shadow
-- 2026-04-25 | 323635     | 323635        | 323635        | 323635        | 323635       | 0 ✓
-- 2026-04-26 | 200000     | 200000        | 200000        | 200000        | 200000       | 0 ✓

-- Sample data check (verify transformations are correct):
SELECT 
    id, periode,
    segmen_dashboard, segmen_kinerja,
    produk_dashboard, produk_kinerja,
    cifno, cifno_clean
FROM daily_loan_dinamis
WHERE periode IN ('2026-04-25', '2026-04-26')
LIMIT 10;

-- ================================================================
-- BAGIAN 5: Rebuild Snapshots & Clear Cache (via Terminal)
-- ================================================================

-- Setelah SQL backfill selesai, jalankan di terminal:
-- 
-- php artisan snapshot:rebuild-rm --period=2026-04-25 --force
-- php artisan snapshot:rebuild-rm --period=2026-04-26 --force
-- php artisan cache:clear
--
-- Laporan sekarang akan tampil dengan data yang benar

-- ================================================================
-- TROUBLESHOOTING
-- ================================================================

-- A. Jika terjadi error "Lock wait timeout"
--    -> Stop dan tunggu 5 menit
--    -> Jalankan WAIT PERIOD CHECK query di bawah
--    -> Lalu lanjutkan batch berikutnya

-- B. Check if process is running/locked
SELECT * FROM INFORMATION_SCHEMA.PROCESSLIST 
WHERE DB = 'project_abah' AND COMMAND != 'Sleep';

-- C. Kill specific process jika stuck (HATI-HATI!)
-- KILL <process_id_dari_query_di_atas>;

-- D. Reset shadow columns jika gagal total (DESTRUCTIVE!)
--    Jalankan hanya jika ingin restart dari awal:
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

-- Kemudian jalankan BAGIAN 2 dan BAGIAN 3 lagi

-- ================================================================
-- End of script
-- Created: 2026-04-29
-- ================================================================
