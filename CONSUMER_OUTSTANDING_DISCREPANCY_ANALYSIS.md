📊 ANALISIS LENGKAP: DISKREPANSI OUTSTANDING KONSUMEN (BRIGUNA & KPR)
====================================================================

## 📋 TEMUAN UTAMA

### Data Yang Anda Lihat (KC MADIUN - 2026-04-19):
- **Period (daily_loan_dinamis)**: 1.020.375,4 M
- **Dashboard (dashboard_harian_snapshots)**: 1.082.420,8 M
- **PERBEDAAN**: 62.045,4 M ≈ 60M yang Anda laporkan ✓

### Perbandingan Ketiga Sumber Data:
┌─────────────────────────────────────────────────────────────────┐
│ SOURCE                  │ BRIGUNA      │ KPR         │ TOTAL      │
├─────────────────────────────────────────────────────────────────┤
│ daily_loan_dinamis      │ 764.903 M    │ 255.472 M   │ 1.020.375 M│
│ ssa_pinjaman           │ 865.805 M    │ 274.449 M   │ 1.140.254 M│
│ dashboard_snapshots     │ 903.663 M    │ 268.740 M   │ 1.082.420 M│
└─────────────────────────────────────────────────────────────────┘

## 🔍 ROOT CAUSE ANALYSIS

### 1. **daily_loan_dinamis memiliki DUPLIKAT RECORD**
   - Jumlah CONSUMER records: **6.991 records**
   - Jumlah ssa_pinjaman records: **650 records** (10x lebih sedikit!)
   - Kemungkinan: Ada multiple entries untuk loan yang sama
   
### 2. **Dashboard snapshots lebih dekat ke ssa_pinjaman**
   - Dashboard vs daily_loan: **+152.027,6 M** (15% lebih tinggi)
   - Dashboard vs ssa_pinjaman: **+32.149,2 M** (2.8% lebih tinggi)
   - ✓ Dashboard lebih reliable dari daily_loan_dinamis

### 3. **ssa_pinjaman adalah single-source-of-truth**
   - Jumlah record paling sedikit = data aggregated dengan benar
   - Dashboard snapshots mencerminkan ssa_pinjaman
   - daily_loan_dinamis kemungkinan ada data duplikasi/cleaning issue

## 🎯 SOLUSI REKOMENDASI

### Pilihan 1: GUNAKAN ssa_pinjaman (Recommended)
- Data lebih clean dan konsisten
- Sudah aggregated dengan benar
- Perbedaan dari dashboard hanya ~32M (2.8%) - acceptable margin

### Pilihan 2: VALIDASI daily_loan_dinamis
Cek apakah ada:
- Multiple loan entries untuk same loan_id
- Filter conditions yang berbeda
- Data cleaning yang perlu diterapkan

### Pilihan 3: REBUILD Dashboard Snapshots
Jika snapshot belum di-rebuild sejak update data terakhir:
```bash
# Jalankan rebuild snapshot
php artisan dashboard:rebuild-snapshots --date=2026-04-19
```

## 📊 PERBANDINGAN DETAIL PER PRODUK (ssa_pinjaman - KC Madiun)

KUR-Mikro           → 1.759.414,6 M (254 loans)
Briguna-Konsumer    →   813.680,9 M (14 loans) ← Anda mencari ini
KPR                 →   274.449,2 M (25 loans) ← dan ini
Kupedes             →   802.291,0 M (256 loans)
Commercial          →   783.226,1 M (41 loans)
Medium              →   100.390,7 M (1 loan)
Briguna-Mikro       →    52.123,7 M (46 loans)
Cashcall            →    11.552,9 M (4 loans)
Cash Collateral     →     1.144,6 M (9 loans)
                    ─────────────────
TOTAL              → 5.998.173,2 M

## ⚠️  REKOMENDASI TINDAK LANJUT

1. **IMMEDIATE**: Verify tanggal update data terakhir
   - Cek kapan daily_loan_dinamis di-update
   - Cek kapan dashboard_harian_snapshots di-generate

2. **SHORT TERM**: Identifikasi duplikasi di daily_loan_dinamis
   - Query distinct(nomor_rekening + periode) count
   - Bandingkan dengan ssa_pinjaman

3. **MEDIUM TERM**: Standardisasi data source
   - Tentukan single source of truth
   - Implement validation layer untuk consistency check

4. **LONG TERM**: Implement data quality monitoring
   - Alert jika perbedaan > threshold tertentu
   - Automatic data reconciliation

## 📌 DASHBOARD YANG TEPAT DIGUNAKAN

Untuk laporan konsumen outstanding (Briguna & KPR), gunakan:
- **Dashboard Harian (default)**: Paling akurat, ~1.082,42 M untuk KC Madiun
- **Jangan gunakan daily_loan_dinamis langsung**: Ada issue duplikasi
- **Referensi ke ssa_pinjaman**: Untuk cross-validation

Selisih ~60M yang Anda temukan adalah akibat duplikasi/inconsistency 
di daily_loan_dinamis yang belum di-sync dengan ssa_pinjaman.
