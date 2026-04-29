# Shadow Columns Backfill - Implementation Summary

**Status**: ✓ Fully Implemented
**Date**: 2026-04-29
**Target**: Restore data integrity for Kinerja RM & Mantri reports

---

## 📋 Deliverables

### 1. **Artisan Command: `shadow:backfill`**
**File**: [app/Console/Commands/BackfillShadowColumnsCommand.php](app/Console/Commands/BackfillShadowColumnsCommand.php)

**Features**:
- ✓ Chunked processing (configurable: default 10,000 rows)
- ✓ Delay between chunks (default 500ms)
- ✓ Retry logic with exponential backoff
- ✓ Progress bar with real-time tracking
- ✓ Automatic snapshot rebuild on success
- ✓ Cache clearing after rebuild
- ✓ Detailed error logging
- ✓ Dry-run mode for safe preview

**Usage**:
```bash
php artisan shadow:backfill --periods=2026-04-25,2026-04-26 --chunk-size=5000 --delay=1000
```

**For XAMPP Windows** (recommended):
```bash
php artisan shadow:backfill --chunk-size=5000 --delay=1000 --retry-count=5
```

---

### 2. **Artisan Command: `shadow:validate`**
**File**: [app/Console/Commands/ValidateShadowColumnsCommand.php](app/Console/Commands/ValidateShadowColumnsCommand.php)

**Features**:
- ✓ Real-time validation of all shadow columns
- ✓ Consistency checks (source column vs shadow column)
- ✓ Watch mode for continuous monitoring (auto-refresh every 5s)
- ✓ Verbose mode with sample data display
- ✓ JSON output for automation
- ✓ Data quality checks
- ✓ Pre-flight checks before snapshot rebuild

**Usage**:
```bash
# Initial check
php artisan shadow:validate --periods=2026-04-25,2026-04-26

# Monitor progress in real-time
php artisan shadow:validate --periods=2026-04-25,2026-04-26 --watch

# Detailed validation with samples
php artisan shadow:validate --periods=2026-04-25,2026-04-26 --verbose

# JSON output for automation
php artisan shadow:validate --periods=2026-04-25,2026-04-26 --json
```

---

### 3. **Documentation Files**

#### A. **Quick Start Guide** 
**File**: [SHADOW_BACKFILL_QUICK_START.md](SHADOW_BACKFILL_QUICK_START.md)

5-minute quick reference with:
- TL;DR commands
- Default behavior
- Quick troubleshooting
- XAMPP Windows settings
- Alternative manual SQL

#### B. **Comprehensive Guide**
**File**: [SHADOW_BACKFILL_GUIDE.md](SHADOW_BACKFILL_GUIDE.md)

Full documentation including:
- Problem analysis
- Parameter details
- Penyesuaian untuk XAMPP Windows
- Monitoring procedures
- Step-by-step workflow
- Troubleshooting guide
- Recovery procedures
- Performance expectations
- Verification queries

#### C. **Manual SQL Script**
**File**: [SHADOW_BACKFILL_MANUAL_SQL.sql](SHADOW_BACKFILL_MANUAL_SQL.sql)

Fallback solution with:
- Validation queries
- Chunked SQL updates (50K rows per batch)
- Progress checks
- Troubleshooting queries
- Recovery commands

---

## 🚀 Quick Start (5 Minutes)

### Step 1: Validate Initial State
```bash
php artisan shadow:validate --periods=2026-04-25,2026-04-26
```

### Step 2: Preview (Dry Run)
```bash
php artisan shadow:backfill --periods=2026-04-25,2026-04-26 --dry-run
```

### Step 3: Execute Backfill
```bash
php artisan shadow:backfill --chunk-size=5000 --delay=1000
```

**Expected output**:
```
╔════════════════════════════════════════════════════════════════╗
║  Shadow Columns Backfill - Chunked Processing                  ║
║  Purpose: Restore data integrity for RM reports                ║
╚════════════════════════════════════════════════════════════════╝

📅 Processing period: 2026-04-25
   Processing 323,635 rows in chunks of 5000
   [████████████████████████] 100% | 323635/323635 | 02:15 / 02:15
   ✓ Period completed: 323635/323635 rows

📅 Processing period: 2026-04-26
   Processing 200,000 rows in chunks of 5000
   [████████████████████████] 100% | 200000/200000 | 01:30 / 01:30
   ✓ Period completed: 200000/200000 rows

🔄 Rebuilding Performance RM snapshots...
✓ Snapshots rebuilt successfully
🧹 Clearing report cache...
✓ All done! Reports should now display correctly.
```

### Step 4: Validate Result
```bash
php artisan shadow:validate --periods=2026-04-25,2026-04-26
```

**Expected**: All columns at 100% ✓

---

## 🔧 Configuration Guide

### For Different Scenarios

**Scenario 1: XAMPP Windows (Recommended)**
```bash
php artisan shadow:backfill \
  --chunk-size=5000 \
  --delay=1000 \
  --retry-count=5
```
- Smaller chunks to handle limited I/O
- Longer delays for lock cleanup
- More retries for temporary locks

**Scenario 2: Production Linux (Faster)**
```bash
php artisan shadow:backfill \
  --chunk-size=20000 \
  --delay=100
```

**Scenario 3: If Timeout Occurs**
```bash
php artisan shadow:backfill \
  --chunk-size=2000 \
  --delay=2000 \
  --retry-count=10
```

**Scenario 4: Manual SQL (No Artisan)**
See file: [SHADOW_BACKFILL_MANUAL_SQL.sql](SHADOW_BACKFILL_MANUAL_SQL.sql)

---

## 🔍 Monitoring & Verification

### Real-Time Monitoring
```bash
# Terminal 1: Run backfill
php artisan shadow:backfill

# Terminal 2: Monitor progress
php artisan shadow:validate --watch
```

### Database Verification
```sql
-- Verify 100% completion
SELECT 
    periode,
    COUNT(*) as total_rows,
    COUNT(CASE WHEN segmen_kinerja IS NOT NULL THEN 1 END) as filled,
    ROUND(100.0 * COUNT(CASE WHEN segmen_kinerja IS NOT NULL THEN 1 END) 
            / COUNT(*), 2) as pct
FROM daily_loan_dinamis
WHERE periode IN ('2026-04-25', '2026-04-26')
GROUP BY periode;

-- Expected: pct = 100.00 for all periods
```

### UI Verification
1. Navigate to: **Laporan > Kinerja RM > Mikro (Mantri)**
2. Select period: **2026-04-26**
3. ✓ Data should display (not empty)

---

## 📊 Shadow Columns Reference

| Column | Source | Transformation | Purpose |
|--------|--------|---|---|
| `segmen_kinerja` | `segmen_dashboard` | UPPER + TRIM + 5x REPLACE | Filter by segment |
| `produk_kinerja` | `produk_dashboard` | UPPER + TRIM + 5x REPLACE | Filter by product |
| `cabang_normalized` | `cabang1` | UPPER + TRIM | Group by branch |
| `unit_normalized` | `unit1` | UPPER + TRIM | Group by unit |
| `branch_normalized` | `branch1` | UPPER + TRIM | Filter by branch |
| `rm_normalized` | `pn_pengelola1` | UPPER + TRIM | Filter by RM |
| `cifno_clean` | `cifno` | REGEXP_REPLACE (numeric only) | Customer ID lookup |

---

## ⚠️ Troubleshooting

### Error: Lock Wait Timeout

**Solution**:
```bash
php artisan shadow:backfill \
  --chunk-size=2000 \
  --delay=2000 \
  --retry-count=10
```

Or run periods separately:
```bash
php artisan shadow:backfill --periods=2026-04-25 --chunk-size=3000
# Wait 5 minutes
php artisan shadow:backfill --periods=2026-04-26 --chunk-size=3000
```

### Error: REGEXP_REPLACE Not Found

**Solution** (MySQL < 8.0):
Manual SQL fallback uses compatible function

### Snapshot Rebuild Failed

**Check**:
```bash
php artisan shadow:validate --periods=2026-04-25,2026-04-26
```

**Fix** (if not 100%):
```bash
# Reset and retry
UPDATE daily_loan_dinamis 
SET segmen_kinerja = NULL, produk_kinerja = NULL, ... 
WHERE periode IN ('2026-04-25', '2026-04-26');

php artisan shadow:backfill --chunk-size=3000 --delay=2000
```

---

## 📝 Process Overview

```
┌─────────────────────────────────────────────────────────────────┐
│ Shadow Columns Backfill Process                                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ START: php artisan shadow:backfill                             │
│   ├─ Load configuration                                        │
│   ├─ Get periods (2026-04-25, 2026-04-26)                     │
│   │                                                             │
│   └─ For each period:                                          │
│       ├─ Count total NULL rows                                 │
│       │                                                         │
│       └─ While rows remain:                                    │
│           ├─ Get chunk IDs (LIMIT 5000)                       │
│           ├─ UPDATE chunk with shadow calculations            │
│           │  └─ Retry up to 5 times if lock timeout           │
│           ├─ Advance progress bar                             │
│           └─ Delay 1000ms before next chunk                   │
│                                                             │
│   ├─ Display summary                                           │
│   ├─ Rebuild snapshots (auto-rebuild-rm)                      │
│   └─ Clear cache                                               │
│                                                             │
│ END: Reports now display correctly ✓                           │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 📚 Files Reference

| File | Purpose | Type |
|------|---------|------|
| [BackfillShadowColumnsCommand.php](app/Console/Commands/BackfillShadowColumnsCommand.php) | Main backfill logic | Artisan Command |
| [ValidateShadowColumnsCommand.php](app/Console/Commands/ValidateShadowColumnsCommand.php) | Validation & monitoring | Artisan Command |
| [SHADOW_BACKFILL_QUICK_START.md](SHADOW_BACKFILL_QUICK_START.md) | 5-minute quick start | Documentation |
| [SHADOW_BACKFILL_GUIDE.md](SHADOW_BACKFILL_GUIDE.md) | Comprehensive guide | Documentation |
| [SHADOW_BACKFILL_MANUAL_SQL.sql](SHADOW_BACKFILL_MANUAL_SQL.sql) | Manual SQL fallback | SQL Script |

---

## ✅ Implementation Checklist

- [x] Create BackfillShadowColumnsCommand with chunking
- [x] Create ValidateShadowColumnsCommand with monitoring
- [x] Implement retry logic with exponential backoff
- [x] Add progress tracking with progress bar
- [x] Integrate automatic snapshot rebuild
- [x] Integrate cache clearing
- [x] Create quick-start guide
- [x] Create comprehensive guide
- [x] Create manual SQL fallback
- [x] Create implementation summary (this file)

---

## 🎯 Expected Results

### Before Backfill
```
Laporan Kinerja RM > Mikro (Mantri) untuk 2026-04-26: 
Status: KOSONG (0 rows / "zonk")
```

### After Backfill
```
Laporan Kinerja RM > Mikro (Mantri) untuk 2026-04-26:
Status: ✓ TERISI LENGKAP (323,635 rows dengan data agregat)
```

---

## 📞 Support

**For issues**:
1. Check [SHADOW_BACKFILL_GUIDE.md](SHADOW_BACKFILL_GUIDE.md) troubleshooting section
2. Review logs: `storage/logs/laravel.log`
3. Run validation: `php artisan shadow:validate --verbose`
4. Check database: SQL queries in manual guide

---

**Implementation Ready**: 2026-04-29
**Estimated Execution Time**: 5-10 minutes for both periods (XAMPP Windows)
