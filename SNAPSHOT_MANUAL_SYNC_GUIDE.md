# Manual Dashboard Harian Snapshot Sync Script

Script untuk manual synchronize dashboard harian snapshots dengan SSA Pinjaman & Simpanan data.

## 📌 Lokasi Script

```
scripts/sync-dashboard-snapshots.php
```

## 🚀 Penggunaan

### 1. **Sync Missing Periods (Default)**
Hanya rebuild periode yang missing:
```bash
php scripts/sync-dashboard-snapshots.php
```

**Output:**
```
✅ All snapshots are up to date!

Stats:
  • Total shared periods: 147
  • Existing snapshots: 147
  • Missing periods: 0
```

### 2. **Force Rebuild ALL Periods**
Rebuild semua periode dari awal:
```bash
php scripts/sync-dashboard-snapshots.php --force
```

**Gunakan ketika:**
- Data SSA di-update/diperbaiki
- Snapshot table corrupt atau inconsistent
- Full audit/validation diperlukan

### 3. **Sync Specific Period**
Rebuild period tertentu saja:
```bash
php scripts/sync-dashboard-snapshots.php --period 2026-04-18
```

### 4. **Detailed Report**
Tampilkan detail per period:
```bash
php scripts/sync-dashboard-snapshots.php --detail
```

**Output:**
```
Detailed Results:
  Period              | Rows Built
  ────────────────────┼───────────
  2026-04-18          |       109
  2026-04-17          |       110
  2026-04-16          |       108
```

### 5. **Kombinasi Options**
```bash
# Force rebuild dengan detail
php scripts/sync-dashboard-snapshots.php --force --detail

# Specific period dengan detail
php scripts/sync-dashboard-snapshots.php --period 2026-04-18 --detail
```

## 📊 Output Penjelasan

### Status Indicators
- `✓` = Period berhasil di-rebuild
- `✗` = Period gagal di-rebuild
- `[========] 100%` = Progress bar

### Results
```
Results:
  • Periods processed: 147        # Total periode diproses
  • Successful: 147 ✓             # Berhasil
  • Failed: 0 ✗                   # Gagal
  • Duration: 45.23s              # Total waktu
  • Avg per period: 0.308s        # Rata-rata per period
```

### Snapshot Status
```
Snapshot Status:
  • Total snapshots in DB: 16023  # Total baris snapshot
  • Latest period: 2026-04-18     # Period terbaru
```

## 📝 Logging

Setiap run di-log ke:
```
storage/logs/snapshot-sync-YYYY-MM-DD_HH-mm-ss.log
```

**Contoh log:**
```json
{
  "timestamp": "2026-04-20 01:43:54",
  "mode": "sync-missing",
  "total_periods": 2,
  "successful": 2,
  "failed": 0,
  "duration_seconds": 0.123,
  "results": {
    "2026-04-17": 110,
    "2026-04-18": 109
  },
  "failed_periods": []
}
```

## ⚡ Contoh Skenario

### Scenario 1: After Data Import
Setelah import data SSA baru:
```bash
# Auto-detect dan rebuild missing periods
php scripts/sync-dashboard-snapshots.php --detail
```

### Scenario 2: Troubleshooting
Ketika dashboard menampilkan data lama:
```bash
# Force rebuild semua period
php scripts/sync-dashboard-snapshots.php --force

# Or specific period yang problem
php scripts/sync-dashboard-snapshots.php --period 2026-04-20
```

### Scenario 3: Data Integrity Check
Verify dan report semua snapshots:
```bash
# Full rebuild dengan detail report
php scripts/sync-dashboard-snapshots.php --force --detail > snapshot-audit.txt
```

## 🔧 Troubleshooting

### Error: "No shared periods found"
```
❌ No shared periods found. Aborting.
```
**Penyebab:** Tidak ada data di SSA Pinjaman atau SSA Simpanan
**Solusi:** Pastikan kedua SSA table memiliki data

### Error: "Failed Periods"
```
⚠️  Failed Periods:
  • 2026-04-18
```
**Penyebab:** Error saat rebuild snapshot untuk periode tersebut
**Solusi:** 
- Check log file untuk detail error
- Verify data integrity di SSA table untuk periode tersebut
- Coba rebuild individual period: `php scripts/sync-dashboard-snapshots.php --period 2026-04-18`

### Slow Performance
Script memakan waktu lama di-rebuild semua 147 periods
**Solusi:**
- Gunakan sync missing mode (default) bukan force
- Rebuild specific period saja saat testing
- Check database load & indexes

## 📌 Perbandingan: Manual vs Auto-Sync

| Aspek | Manual Script | Auto Scheduler |
|-------|--------------|-----------------|
| Waktu | Sesuai kebutuhan | Setiap 5 menit |
| Trigger | Manual command | Automatic |
| Use Case | Troubleshooting, Audit | Production |
| Reporting | Detailed | Log only |
| Control | Full control | Background |

## 🎯 Best Practices

1. **Production:** Gunakan auto-scheduler (setiap 5 menit)
2. **Troubleshooting:** Gunakan manual script dengan `--detail`
3. **Audit:** Jalankan `--force --detail` dan save output
4. **Monitoring:** Check log file reguler di `storage/logs/`
5. **Specific Fix:** Gunakan `--period` untuk rebuild periode tertentu

## 📞 Exit Codes

- `0` = Success (semua periods rebuilt)
- `1` = Failure (ada periods yang gagal atau error)

## Related Commands

```bash
# Via Artisan command (alternative)
php artisan snapshot:sync-harian-dashboard
php artisan snapshot:sync-harian-dashboard --force

# Check current scheduler status
php artisan schedule:list
```
