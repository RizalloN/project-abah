# Snapshot Systems - Project Overview

Project ini memiliki **11+ snapshot tables** untuk cache dan performa berbagai reports dan dashboards.

## 📊 Daftar Snapshot Systems

### 1. **Dashboard Harian** ⭐ (AUTO-SYNC IMPLEMENTED)
| Aspek | Detail |
|-------|--------|
| **Table** | `dashboard_harian_snapshots` |
| **Source** | SSA Pinjaman ∩ SSA Simpanan (shared periods) |
| **Periode** | Daily (2026-01-XX sampai 2026-04-XX) |
| **Auto-Sync** | ✅ Every 5 minutes |
| **Manual Sync** | ✅ Script: `scripts/sync-dashboard-snapshots.php` |
| **Artisan Command** | `php artisan snapshot:sync-harian-dashboard` |
| **Status** | ✅ READY & MONITORED |

### 2. **Dashboard Pinjaman** (Loan Dashboard)
| Aspek | Detail |
|-------|--------|
| **Table** | `dashboard_pinjaman_snapshots` |
| **Source** | LW325_PH table (Loan data) |
| **Used By** | `DashboardPinjamanReportController` |
| **Rebuild Method** | `ReportSnapshotBuilder::rebuildDashboard()` |
| **Auto-Sync** | ❌ Manual only (via jobs/commands) |
| **Job** | `EnsureDashboardSnapshotJob` |

### 3. **Dashboard Simpanan** (Savings Dashboard)
| Aspek | Detail |
|-------|--------|
| **Tables** | `dashboard_simpanan_snapshots` |
| **Branch Table** | `dashboard_simpanan_branch_snapshots` |
| **Source** | SSA Simpanan table |
| **Used By** | `DashboardSimpananController` |
| **Rebuild Method** | `ReportSnapshotBuilder::rebuildDashboardSimpanan()` |
| **Auto-Sync** | ❌ Manual only (via jobs/commands) |
| **Job** | `EnsureDashboardSimpananSnapshotJob` |

### 4. **Rasio CASA Debitur** (CASA Ratio)
| Aspek | Detail |
|-------|--------|
| **Tables** | `rasio_casa_debitur_snapshots` |
| **Uker Table** | `rasio_casa_debitur_uker_snapshots` |
| **Source** | SSA data calculations |
| **Rebuild Method** | `ReportSnapshotBuilder::rebuildRasioCasa()` |
| **Auto-Sync** | ❌ Manual only |
| **Job** | `EnsureRasioCasaSnapshotJob` |

### 5. **Rekening Dormant** (Dormant Accounts)
| Aspek | Detail |
|-------|--------|
| **Table** | `rekening_dormant_snapshots` |
| **Source** | Account activity analysis |
| **Rebuild Method** | `ReportSnapshotBuilder::rebuildRekeningDormant()` |
| **Auto-Sync** | ❌ Manual only |
| **Job** | `EnsureRekeningDormantSnapshotJob` |

### 6. **Performance New Payroll** (Payroll Reports)
| Aspek | Detail |
|-------|--------|
| **Table** | `performance_new_payroll_snapshots` |
| **Source** | Payroll performance data |
| **Rebuild Method** | `ReportSnapshotBuilder::rebuildPerformanceNewPayroll()` |
| **Auto-Sync** | ❌ Manual only |

### 7. **SSA Simpanan Snapshot**
| Aspek | Detail |
|-------|--------|
| **Table** | `ssa_simpanan_snapshots` |
| **Source** | Direct copy/snapshot of SSA Simpanan |
| **Purpose** | Archive/backup cache |

### 8. **SSA Pinjaman Snapshot**
| Aspek | Detail |
|-------|--------|
| **Table** | `ssa_pinjaman_snapshots` |
| **Source** | Direct copy/snapshot of SSA Pinjaman |
| **Purpose** | Archive/backup cache |

### 9. **LW325 PH Snapshot**
| Aspek | Detail |
|-------|--------|
| **Table** | `lw325_ph_snapshots` |
| **Source** | LW325_PH (Loan data) archive |
| **Purpose** | Recovery/audit snapshot |

## 🔄 Snapshot Rebuild Architecture

### Central Management: `ReportSnapshotBuilder`
Satu service yang manage ALL snapshots kecuali Dashboard Harian (yang punya sendiri):

```php
$builder = app(ReportSnapshotBuilder::class);

// Rebuild specific type
$builder->rebuild('dashboard-pinjaman', '2026-04-18');
$builder->rebuild('dashboard-simpanan', '2026-04-18');
$builder->rebuild('rasio-casa', '2026-04-18');
$builder->rebuild('dormant', '2026-04-18');
$builder->rebuild('new-payroll', '2026-04-18');

// Or rebuild all
$builder->rebuild('all');
```

### Jobs for Async Rebuild
- `EnsureDashboardSnapshotJob` - Dashboard Pinjaman
- `EnsureDashboardSimpananSnapshotJob` - Dashboard Simpanan
- `EnsureRasioCasaSnapshotJob` - Rasio CASA
- `EnsureRekeningDormantSnapshotJob` - Dormant
- `ExecuteBatchedSnapshotJob` - Batched rebuilds
- `RunManagedReportSnapshotRebuildJob` - Managed rebuilds
- `SmartPartialSnapshotRebuildJob` - Smart partial rebuild

## 📋 Available Commands

### Dashboard Harian (✅ AUTO-SYNC)
```bash
# Scheduled every 5 minutes (automatic)
php artisan snapshot:sync-harian-dashboard

# Force rebuild
php artisan snapshot:sync-harian-dashboard --force
```

### Recovery/Audit
```bash
# Rebuild recovery snapshots
php artisan snapshot:rebuild-recovery

# Manage batches
php artisan snapshot:manage-batches
php artisan snapshot:schedule-batch-flush
php artisan snapshot:flush-due-batches
```

### Via Script (All Reports)
```bash
# Manual script untuk sync semua snapshots
php scripts/sync-dashboard-snapshots.php
```

## 🔄 Workflow Sync

```
┌─────────────────────────────────────────────────────────────┐
│ Dashboard Harian Snapshot (✅ AUTO-SYNC)                     │
├─────────────────────────────────────────────────────────────┤
│ • Every 5 minutes: Auto detect missing periods              │
│ • Sync dengan SSA Pinjaman & SSA Simpanan shared periods    │
│ • Manual: scripts/sync-dashboard-snapshots.php             │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ Other Snapshots (Manual/Job-based)                           │
├─────────────────────────────────────────────────────────────┤
│ • Dashboard Pinjaman         → EnsureDashboardSnapshotJob    │
│ • Dashboard Simpanan         → EnsureDashboardSimpananSnapshotJob │
│ • Rasio CASA                 → EnsureRasioCasaSnapshotJob    │
│ • Rekening Dormant           → EnsureRekeningDormantSnapshotJob    │
│ • Performance New Payroll    → (Job-based)                  │
│ • Central rebuild via artisan: (planned)                    │
└─────────────────────────────────────────────────────────────┘
```

## 💡 Next Steps to Implement Auto-Sync

### Priority 1: High Impact (Dashboard Pinjaman & Simpanan)
Mirip dengan Dashboard Harian, buat:

```bash
# Artisan Command
php artisan snapshot:sync-dashboard-pinjaman --every=5m
php artisan snapshot:sync-dashboard-simpanan --every=5m

# Manual Script
php scripts/sync-dashboard-pinjaman.php
php scripts/sync-dashboard-simpanan.php
```

### Priority 2: Medium (Rasio, Dormant)
```bash
php artisan snapshot:sync-rasio-casa
php artisan snapshot:sync-rekening-dormant
```

### Priority 3: Low (Archive/Backup Snapshots)
```bash
# SSA snapshots (for audit trail)
php artisan snapshot:sync-ssa-backups
```

## 🎯 Recommended Implementation Order

1. ✅ **Done**: Dashboard Harian auto-sync (every 5 minutes)
2. **Next**: Dashboard Pinjaman auto-sync (every 10 minutes)
3. **Then**: Dashboard Simpanan auto-sync (every 10 minutes)
4. **Then**: Rasio CASA auto-sync (every 30 minutes)
5. **Optional**: Dormant & Payroll (job-based is fine)

## 📊 Performance Considerations

| Snapshot | Rows | Rebuild Time | Frequency |
|----------|------|-------------|-----------|
| Dashboard Harian | 16K+ | ~45s | ✅ 5m |
| Dashboard Pinjaman | Unknown | ? | Manual |
| Dashboard Simpanan | Unknown | ? | Manual |
| Rasio CASA | Unknown | ? | Manual |
| Rekening Dormant | Unknown | ? | Manual |

## 🔗 Related Files

| File | Purpose |
|------|---------|
| `app/Support/ReportSnapshotBuilder.php` | Central snapshot management |
| `app/Support/DashboardHarianSnapshotService.php` | Dashboard Harian specific |
| `app/Http/Controllers/DashboardPinjamanReportController.php` | Uses Pinjaman snapshot |
| `app/Http/Controllers/DashboardSimpananController.php` | Uses Simpanan snapshots |
| `app/Console/Commands/SyncDashboardHarianSnapshot.php` | Artisan command for Harian |
| `scripts/sync-dashboard-snapshots.php` | Manual sync script |

## ⚙️ Database Queries for Status

### Check all snapshots
```sql
SELECT TABLE_NAME as snapshot_table, TABLE_ROWS as row_count
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_NAME LIKE '%snapshot%'
ORDER BY TABLE_NAME;
```

### Dashboard Harian Status
```sql
SELECT snapshot_period, COUNT(*) as rows, MAX(updated_at) as last_updated
FROM dashboard_harian_snapshots
GROUP BY snapshot_period
ORDER BY snapshot_period DESC
LIMIT 10;
```

### Check Missing Periods per Snapshot
```sql
-- Dashboard Pinjaman
SELECT DISTINCT l.periode as missing_period
FROM (SELECT DISTINCT DATE_FORMAT(periode, '%Y-%m-01') as periode FROM lw325_ph) l
LEFT JOIN dashboard_pinjaman_snapshots d ON l.periode = d.periode
WHERE d.periode IS NULL;
```
