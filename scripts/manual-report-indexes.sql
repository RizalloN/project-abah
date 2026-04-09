-- Manual indexing for the 3 heavy reports:
-- 1. Dashboard Pinjaman
-- 2. Rasio CASA Debitur
-- 3. Rekening Dormant
-- 4. Performance New Payroll
--
-- Run these statements one-by-one in MySQL during a maintenance window.
-- On very large tables, ALTER TABLE may take a long time.

USE `project_abah`;

-- =========================================================
-- 0. CHECK EXISTING INDEXES
-- =========================================================
SHOW INDEX FROM `daily_loan_dinamis`;
SHOW INDEX FROM `simpanan_multipn`;
SHOW INDEX FROM `lw325_ph`;
SHOW INDEX FROM `performance_pis_per_produk`;

-- Based on current index inventory:
-- daily_loan_dinamis already has:
--   idx_dld_periode_rekening (periode, nomor_rekening1)
--   idx_dld_periode_segmen_produk (periode, segmen_dashboard, produk_dashboard)
--   idx_dld_periode_cabang_unit (periode, cabang1, unit1)
--   idx_loan_periode_cif (periode, cifno)
--
-- simpanan_multipn already has:
--   idx_simp_posisi_cif (posisi, CIFNO)
--   idx_simp_posisi_jenis (posisi, jenis_simpanan)
--   idx_simp_dormant_posisi_status_cabang_rek (posisi, status, kantor_cabang, no_rekening)
--
-- lw325_ph already has:
--   report_ph_periode_index (periode)
--   report_ph_acctno_index (acctno)

-- =========================================================
-- 1. EXPLAIN BEFORE
-- These are representative queries taken from the actual controllers.
-- =========================================================

-- 1A. Dashboard Pinjaman: filter options
EXPLAIN
SELECT DISTINCT `unit1`
FROM `daily_loan_dinamis`
WHERE `periode` = '2026-04-04'
  AND `segmen_dashboard` IN ('Consumer')
  AND `produk_dashboard` IN ('KPR')
  AND `cabang1` IN ('KC Madiun')
  AND `unit1` IS NOT NULL
  AND `unit1` <> ''
ORDER BY `unit1`;

-- 1B. Dashboard Pinjaman: snapshot by account
EXPLAIN
SELECT
  TRIM(`nomor_rekening1`) AS account_number,
  COALESCE(`baki_debet1`, 0) AS current_balance
FROM `daily_loan_dinamis`
WHERE `periode` = '2026-04-04'
  AND `nomor_rekening1` IS NOT NULL
  AND TRIM(`nomor_rekening1`) <> '';

-- 2A. Rasio CASA Debitur: loan lookup by period and CIF
EXPLAIN
SELECT
  `cifno`,
  `cabang1`,
  `segmen_dashboard`,
  `produk_dashboard`,
  COALESCE(`baki_debet1`, 0) AS loan_balance
FROM `daily_loan_dinamis`
WHERE `periode` = '2026-04-04'
  AND `cifno` IS NOT NULL
  AND TRIM(COALESCE(`cifno`, '')) <> '';

-- 2B. Rasio CASA Debitur: CASA aggregation by date and CIF
EXPLAIN
SELECT
  `CIFNO`,
  SUM(COALESCE(`saldo_idr`, 0)) AS casa_balance
FROM `simpanan_multipn`
WHERE `posisi` = '2025-12-31'
  AND `CIFNO` IN ('1234567890', '9876543210')
  AND (`jenis_simpanan` LIKE 'GIRO%' OR `jenis_simpanan` LIKE 'TABUNGAN%')
GROUP BY `CIFNO`;

-- 3A. Rekening Dormant: dropdown unit
EXPLAIN
SELECT DISTINCT `unit_kerja`
FROM `simpanan_multipn`
WHERE `posisi` = '2025-12-31'
  AND `status` = '9'
  AND `kantor_cabang` IN ('00045 -- KC Madiun(Konsolidasi-MB)')
  AND `unit_kerja` IS NOT NULL
  AND `unit_kerja` <> ''
ORDER BY `unit_kerja`;

-- 3B. Rekening Dormant: summary table
EXPLAIN
SELECT
  `posisi`,
  `kantor_cabang`,
  COUNT(`status`) AS dormant_count
FROM `simpanan_multipn`
WHERE `posisi` IN ('2025-12-31', '2025-11-30', '2024-12-31')
  AND `status` = '9'
  AND `kantor_cabang` IN (
    '00045 -- KC Madiun(Konsolidasi-MB)',
    '00049 -- KC Magetan(Konsolidasi-MB)',
    '00057 -- KC Ngawi(Konsolidasi-MB)',
    '00070 -- KC Ponorogo(Konsolidasi-MB)'
  )
GROUP BY `posisi`, `kantor_cabang`;

-- 1) Dashboard Pinjaman
-- Existing indexes already cover most filter and snapshot paths.
-- Add only one composite index to bridge the 2 common filter chains:
-- (periode, segmen_dashboard, produk_dashboard) + cabang1 + unit1
ALTER TABLE `daily_loan_dinamis`
  ADD INDEX `idx_dld_periode_segmen_produk_cabang_unit`
  (`periode`, `segmen_dashboard`, `produk_dashboard`, `cabang1`, `unit1`);

-- 2) Rasio CASA Debitur
-- Existing (posisi, CIFNO) and (posisi, jenis_simpanan) are separate.
-- This composite helps the real query directly:
-- WHERE posisi = ? AND CIFNO IN (...) AND jenis_simpanan LIKE ...
ALTER TABLE `simpanan_multipn`
  ADD INDEX `idx_smp_posisi_cif_jenis`
  (`posisi`, `CIFNO`, `jenis_simpanan`);

-- If branch rollup by CIF on daily loan is still heavy, add this bridge index.
ALTER TABLE `daily_loan_dinamis`
  ADD INDEX `idx_dld_periode_cif_cabang`
  (`periode`, `cifno`, `cabang1`);

-- 3) Rekening Dormant
-- Existing dormant index ends with no_rekening, not unit_kerja.
-- This one is specifically for unit dropdown + branch/unit filtering.
ALTER TABLE `simpanan_multipn`
  ADD INDEX `idx_smp_posisi_status_cabang_unit`
  (`posisi`, `status`, `kantor_cabang`, `unit_kerja`);

-- PH lookup for Dashboard Pinjaman
-- Existing periode and acctno are separate; this helps combined lookup.
ALTER TABLE `lw325_ph`
  ADD INDEX `idx_lw325ph_periode_acctno_pokok`
  (`periode`, `acctno`, `pokok`);

-- 4) Performance New Payroll (on-the-fly fallback path)
-- Speeds up date-range aggregation by branch and account-open date.
ALTER TABLE `performance_pis_per_produk`
  ADD INDEX `idx_perf_posisi_kanca_tgl_buat`
  (`posisi`, `kanca`, `tanggal_pembuatan_rekening`);

-- =========================================================
-- 2. EXPLAIN AFTER
-- Run the same checks again after indexes are created.
-- Key/possible_keys should now reference the new indexes.
-- =========================================================

EXPLAIN
SELECT DISTINCT `unit1`
FROM `daily_loan_dinamis`
WHERE `periode` = '2026-04-04'
  AND `segmen_dashboard` IN ('Consumer')
  AND `produk_dashboard` IN ('KPR')
  AND `cabang1` IN ('KC Madiun')
  AND `unit1` IS NOT NULL
  AND `unit1` <> ''
ORDER BY `unit1`;

EXPLAIN
SELECT
  TRIM(`nomor_rekening1`) AS account_number,
  COALESCE(`baki_debet1`, 0) AS current_balance
FROM `daily_loan_dinamis`
WHERE `periode` = '2026-04-04'
  AND `nomor_rekening1` IS NOT NULL
  AND TRIM(`nomor_rekening1`) <> '';

EXPLAIN
SELECT
  `CIFNO`,
  SUM(COALESCE(`saldo_idr`, 0)) AS casa_balance
FROM `simpanan_multipn`
WHERE `posisi` = '2025-12-31'
  AND `CIFNO` IN ('1234567890', '9876543210')
  AND (`jenis_simpanan` LIKE 'GIRO%' OR `jenis_simpanan` LIKE 'TABUNGAN%')
GROUP BY `CIFNO`;

EXPLAIN
SELECT DISTINCT `unit_kerja`
FROM `simpanan_multipn`
WHERE `posisi` = '2025-12-31'
  AND `status` = '9'
  AND `kantor_cabang` IN ('00045 -- KC Madiun(Konsolidasi-MB)')
  AND `unit_kerja` IS NOT NULL
  AND `unit_kerja` <> ''
ORDER BY `unit_kerja`;

EXPLAIN
SELECT
  `posisi`,
  `kantor_cabang`,
  COUNT(`status`) AS dormant_count
FROM `simpanan_multipn`
WHERE `posisi` IN ('2025-12-31', '2025-11-30', '2024-12-31')
  AND `status` = '9'
  AND `kantor_cabang` IN (
    '00045 -- KC Madiun(Konsolidasi-MB)',
    '00049 -- KC Magetan(Konsolidasi-MB)',
    '00057 -- KC Ngawi(Konsolidasi-MB)',
    '00070 -- KC Ponorogo(Konsolidasi-MB)'
  )
GROUP BY `posisi`, `kantor_cabang`;
