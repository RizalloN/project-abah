# Dashboard Harian Snapshot - Quick Reference

## 🚀 Common Commands

### Check Status
```bash
# View last 5 snapshot periods
php artisan tinker
> $service = app(App\Support\DashboardHarianSnapshotService::class);
> $service->fetchPeriods()->slice(-5)->values()->all();

# Get latest effective period
> $service->resolveEffectivePeriod(null);
```

### Auto-Sync (Scheduled)
```bash
# Manual trigger of scheduled sync
php artisan snapshot:sync-harian-dashboard

# Force rebuild via scheduler command
php artisan snapshot:sync-harian-dashboard --force
```

### Manual Sync Script
```bash
# Sync missing periods only
php scripts/sync-dashboard-snapshots.php

# Force rebuild all 147 periods
php scripts/sync-dashboard-snapshots.php --force

# Rebuild specific period
php scripts/sync-dashboard-snapshots.php --period 2026-04-18

# With detailed reporting
php scripts/sync-dashboard-snapshots.php --detail
php scripts/sync-dashboard-snapshots.php --force --detail
php scripts/sync-dashboard-snapshots.php --period 2026-04-18 --detail
```

### Rebuild Specific Periods (Tinker)
```bash
php artisan tinker

# Rebuild single period
> $service->rebuild('2026-04-18');

# Rebuild multiple periods
> $service->rebuild('2026-04-17');
> $service->rebuild('2026-04-18');

# Force rebuild
> $service->rebuild('2026-04-18', true);

# Check sync status
> $service->syncMissingPeriods();
```

## 📊 Database Queries

### View Snapshot Periods
```sql
-- Latest 10 snapshot periods
SELECT DISTINCT snapshot_period 
FROM dashboard_harian_snapshots 
ORDER BY snapshot_period DESC 
LIMIT 10;

-- Total snapshots
SELECT COUNT(*) FROM dashboard_harian_snapshots;

-- Snapshots per period
SELECT snapshot_period, COUNT(*) as row_count
FROM dashboard_harian_snapshots
GROUP BY snapshot_period
ORDER BY snapshot_period DESC;
```

### View SSA Periods
```sql
-- SSA Pinjaman periods
SELECT DISTINCT month_day_year_of_periode 
FROM ssa_pinjaman 
ORDER BY month_day_year_of_periode DESC 
LIMIT 10;

-- SSA Simpanan periods
SELECT DISTINCT Month_Day_Year_of_Posisi 
FROM ssa_simpanan 
ORDER BY Month_Day_Year_of_Posisi DESC 
LIMIT 10;

-- Shared periods (both tables)
SELECT DISTINCT p.month_day_year_of_periode as periode
FROM ssa_pinjaman p
INNER JOIN ssa_simpanan s ON p.month_day_year_of_periode = s.Month_Day_Year_of_Posisi
ORDER BY periode DESC;
```

### Find Missing Snapshots
```sql
-- Periods in SSA but missing in snapshot
SELECT DISTINCT p.month_day_year_of_periode as missing_period
FROM ssa_pinjaman p
INNER JOIN ssa_simpanan s ON p.month_day_year_of_periode = s.Month_Day_Year_of_Posisi
LEFT JOIN dashboard_harian_snapshots d ON p.month_day_year_of_periode = d.snapshot_period
WHERE d.snapshot_period IS NULL
ORDER BY missing_period DESC;
```

## 📁 Related Files

| File | Purpose |
|------|---------|
| `app/Console/Commands/SyncDashboardHarianSnapshot.php` | Auto-sync artisan command |
| `app/Console/Kernel.php` | Scheduler configuration (line 16-22) |
| `app/Support/DashboardHarianSnapshotService.php` | Core service logic |
| `scripts/sync-dashboard-snapshots.php` | Manual sync script |
| `SNAPSHOT_MANUAL_SYNC_GUIDE.md` | Detailed documentation |

## 🔄 Workflow

### Normal Operation (Auto)
1. Data imported → SSA tables updated
2. Scheduler runs every 5 minutes
3. `SyncDashboardHarianSnapshot` command triggers
4. New periods detected & rebuilt
5. Dashboard auto-shows latest data

### Manual Intervention (Troubleshooting)
1. Check status: `php scripts/sync-dashboard-snapshots.php`
2. If missing periods: `php scripts/sync-dashboard-snapshots.php`
3. If data inconsistent: `php scripts/sync-dashboard-snapshots.php --force`
4. If specific issue: `php scripts/sync-dashboard-snapshots.php --period XXXX`

### Monitoring
- Check logs: `storage/logs/snapshot-sync-*.log`
- View status: `php artisan tinker` → check latest period
- Database: Query `dashboard_harian_snapshots` table

## ⏰ Scheduled Tasks

```
Every 5 minutes:
└─ snapshot:sync-harian-dashboard
   ├─ Scan SSA shared periods
   ├─ Compare with existing snapshots
   ├─ Rebuild missing periods
   └─ Log results
```

## 💡 Tips

- **After SSA import:** Snapshots auto-sync within 5 minutes
- **Emergency rebuild:** Use `--force` to rebuild all
- **Audit trail:** Check `storage/logs/snapshot-sync-*.log`
- **Performance:** Don't use `--force` frequently (147 periods ≈ 45s)
- **Monitoring:** Subscribe to cron logs for failures

## 🆘 Troubleshooting

| Issue | Solution |
|-------|----------|
| Dashboard shows old data | Run: `php scripts/sync-dashboard-snapshots.php` |
| Missing periods | Run: `php scripts/sync-dashboard-snapshots.php --detail` |
| Data inconsistent | Run: `php scripts/sync-dashboard-snapshots.php --force` |
| Specific period issue | Run: `php scripts/sync-dashboard-snapshots.php --period XXXX` |
| Performance slow | Check DB query logs, might need index rebuild |

## 📞 Support

For detailed documentation, see: `SNAPSHOT_MANUAL_SYNC_GUIDE.md`
