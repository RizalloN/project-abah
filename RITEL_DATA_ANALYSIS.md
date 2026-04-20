# Investigasi Ritel Data - Dashboard Harian

## Status: ✅ SNAPSHOT DATA LENGKAP

Snapshot untuk 2026-04-18 sudah rebuild dan **LENGKAP** dengan 109 rows. 
Data ritel dan mikro **TIDAK HILANG** dalam snapshot.

---

## 📊 Data Investigation Results

### 1. **RITEL di Savings (ssa_simpanan)** ✅
- **Exists**: 36,949 rows total
- **Locations**: Hanya di Kanca/KCP level, TIDAK di UNIT level:
  - KC Madiun: 1,134,550,739,643.69
  - KCP Caruban: 191,670,099,116.49
  - KCP Dolopo: 188,311,586,889.58
  - KCP Sudirman Madiun: 87,017,390,726.80
  
- **Status di Snapshot**: ✅ SEMUA DATA RITEL TERSIMPAN

### 2. **RITEL di Loans (ssa_pinjaman)** ❌
- **Does NOT exist**: 0 rows
- **Segments di Loans**: COMMERCIAL, SMALL, MEDIUM, CONSUMER, MICRO
- **RITEL segment**: TIDAK ADA di loan table sama sekali
- **Impact**: Tidak ada data SML/NPL untuk RITEL (bukan bug, data source tidak ada)

### 3. **MIKRO** ✅
- **Savings**: Ada lengkap di semua unit level
- **Loans**: Ada lengkap (MICRO segment ada)
- **Snapshot**: ✅ LENGKAP

---

## 📋 Current Snapshot Status

```
Period: 2026-04-18
Total Rows: 109
├── Summary rows (Kanca level): 4
└── Detail rows (Unit level): 105

Total Ritel Simpanan: 3,601,648,243,732.02
Total Mikro Simpanan: 2,433,877,283,872.20
Total Loan OS (All segments): ~4 Triliun
```

---

## 🔍 Mengapa Dashboard Menunjukkan 0,00 M?

Jika dashboard menunjukkan 0,00 M untuk semua nilai, kemungkinannya:

### Scenario 1: Filter pada UNIT level tanpa data
- Jika user filter ke "UNIT Aloon Madiun" → Ritel akan 0 (karena tidak ada ritel di unit level)
- Loan OS akan > 0 (karena ada mikro loans)
- Ini adalah behavior normal, bukan bug

### Scenario 2: Filter pada branch/KCP dengan data
- Jika filter ke "KC Madiun" atau "KCP Caruban" → Ritel akan tampil dengan nilai besar
- Ini bekerja dengan baik

### Scenario 3: Cache issue (sudah fixed)
- Cache sudah di-clear: `php artisan cache:clear` ✅
- Dashboard akan pull data fresh dari snapshot

---

## ✅ Solutions Completed

1. **✅ Added buildFilterCondition() method**
   - File: [app/Support/DashboardHarianSnapshotService.php](app/Support/DashboardHarianSnapshotService.php#L1574)
   - Properly filters subsegments by branch/unit

2. **✅ Rebuilt snapshot**
   - Period 2026-04-18: 109 rows successfully rebuilt
   - All ritel and mikro data preserved

3. **✅ Cleared application cache**
   - Force dashboard to reload fresh data

---

## 📝 Data Completeness Check

| Segment | Savings | Loans | Status |
|---------|---------|-------|--------|
| **Ritel** | ✅ 36,949 rows | ❌ 0 rows | Partial (savings only) |
| **Mikro** | ✅ Complete | ✅ Complete | ✅ Complete |
| **Wholesale** | ✅ Complete | N/A | ✅ Complete |
| **Commercial** | N/A | ✅ Complete | ✅ Complete |
| **SME/Kecil** | N/A | ✅ Complete | ✅ Complete |
| **Medium** | N/A | ✅ Complete | ✅ Complete |
| **Consumer** | N/A | ✅ Complete | ✅ Complete |

---

## 🎯 Langkah Selanjutnya

Jika masih melihat 0,00 M di dashboard:

1. **Cek filter yang digunakan**
   - Apakah filter ke unit level yang memang tidak punya ritel?
   - Coba filter ke Kanca/KCP level yang punya ritel data

2. **Refresh browser cache**
   - Ctrl+Shift+Delete (hard refresh)
   - Clear browser cache & cookies

3. **Check period selection**
   - Apakah periode yang dipilih adalah 2026-04-18?
   - Jika tidak, rebuild untuk periode yang dimau:
     ```bash
     php scripts/sync-dashboard-snapshots.php --force --period [YYYY-MM-DD]
     ```

4. **Verify filter matching**
   - Gunakan filter "Semua Kanca" atau "Semua Unit Kerja"
   - Lihat apakah ritel data muncul untuk summary row

---

## 🔧 Troubleshooting Commands

```bash
# Clear cache and rebuild latest snapshot
php artisan cache:clear
php scripts/sync-dashboard-snapshots.php --force --period 2026-04-18

# Check if ritel data exists for specific period
php -r "
\$pdo = new PDO('mysql:host=localhost;dbname=project_abah', 'root', '');
\$stmt = \$pdo->prepare('SELECT SUM(simpanan_ritel) as total FROM dashboard_harian_snapshots WHERE snapshot_period = ?');
\$stmt->execute(['2026-04-18']);
\$result = \$stmt->fetch(PDO::FETCH_ASSOC);
echo 'Total Ritel Simpanan in snapshot: ' . \$result['total'] . \"\n\";
"
```

---

## 📌 Important Notes

1. **RITEL di Loans tidak akan pernah ada** - ini bukan bug, data source (ssa_pinjaman) tidak memiliki RITEL segment
2. **Ritel hanya ada di Savings & Simpanan** - sesuai dengan data source
3. **Mikro data LENGKAP** di savings, loans, SML, dan NPL
4. **Snapshot sudah rebuild dan valid** - semua data tersimpan dengan benar
