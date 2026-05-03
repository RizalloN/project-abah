# 📚 BACKFILL & SNAPSHOT LOGIC EXPLANATION

## Overview Architecture

Project ini menggunakan 2 konsep fundamental yang terintegrasi:
1. **SHADOW COLUMNS (Backfill)** - Pre-compute expensive transformations
2. **SNAPSHOTS (Dashboard Cache)** - Cache aggregated data untuk query cepat

---

## 1. SHADOW COLUMNS & BACKFILL

### Konsep Dasar

**SHADOW COLUMNS** = Extra columns yang menyimpan **hasil transformasi** dari source column.

**BACKFILL** = Process mengisi shadow columns dengan data transformasi untuk existing records.

### Mengapa Ada Shadow Columns?

#### PROBLEM (Tanpa Shadow):
```sql
-- Query LAMBAT: Function evaluation per row
SELECT SUM(saldo_idr) 
FROM simpanan_multipn 
WHERE REGEXP_REPLACE(CIFNO, '[^0-9]', '') = '1234567'
-- ❌ REGEXP_REPLACE dijalankan untuk SETIAP ROW!
-- ❌ Tidak bisa gunakan INDEX
-- ❌ Full table scan (14M rows = slow)
```

#### SOLUTION (Dengan Shadow):
```sql
-- Query CEPAT: Direct column comparison
SELECT SUM(saldo_idr) 
FROM simpanan_multipn 
WHERE cif_normalized = '1234567'
-- ✅ cif_normalized sudah pre-computed
-- ✅ Index dapat digunakan
-- ✅ Index seek (fast)
```

### Shadow Column Examples di Project

Dari `config/shadow-columns.php`:

#### 1. CIF NORMALIZATION
```
SOURCE: CIFNO = "01234-ABC"
SHADOW: cif_normalized = "01234"  (REGEXP_REPLACE('[^0-9]', ''))

Performance Impact: 10x speedup on Rasio CASA queries
```

#### 2. ACCOUNT NORMALIZATION
```
SOURCE: ACCTNO = "5701-0271-5253-6"
SHADOW: account_normalized = "57010271525236"

Digunakan untuk: Account matching di JOIN operations
```

#### 3. BRANCH NORMALIZATION
```
SOURCE: MAINBR = "57"
SHADOW: branch_normalized = "00057"  (LPAD(..., 5, '0'))

Keuntungan: Consistent branch code format
```

#### 4. SEGMENT NORMALIZATION
```
SOURCE: SEGMENT = "  consumer  "
SHADOW: segment_normalized = "CONSUMER"  (UPPER(TRIM()))

Keuntungan: Case-insensitive comparison, trim whitespace
```

---

## 2. BACKFILL PROCESS (Mengisi Shadow Columns)

### Flow Backfill

```
┌─────────────────────────────────────────┐
│  ADD SHADOW COLUMN (Migration)          │
│  ALTER TABLE simpanan_multipn           │
│  ADD COLUMN cif_normalized VARCHAR(50)  │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  BACKFILL DATA (Distributed Job)        │
│  UPDATE simpanan_multipn                │
│  SET cif_normalized = REGEXP_REPLACE... │
│  WHERE cif_normalized IS NULL           │
│  (Process in 50K chunks)                │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  VALIDATE COMPLETION (95% threshold)    │
│  Check: Are all rows backfilled?        │
│  Continue: Until 95% complete           │
└──────────────┬──────────────────────────┘
               │
               ▼
┌─────────────────────────────────────────┐
│  CREATE INDEX (After backfill done)     │
│  CREATE INDEX idx_cif_normalized        │
│  ON simpanan_multipn(cif_normalized)    │
└──────────────┬──────────────────────────┘
               │
               ▼
        ✅ READY TO USE
```

### Implementation di Project

**Command**: `BackfillShadowColumnsCommand`
**Job**: `DistributedShadowBackfillJob`

#### Key Features:

1. **Chunked Processing** (50K rows at a time)
   ```php
   // Prevent memory spike & lock contention
   // Large table = process in batches
   ```

2. **Progress Tracking**
   ```php
   $command = 'ShadowBackfillStatusCommand'
   // Monitor: 0% → 50% → 95% → 100%
   ```

3. **Retry Logic** (Attempt 1/5)
   ```php
   if ($backfillFailed && $retryCount < 5) {
       // Auto-retry dengan exponential backoff
   }
   ```

4. **Index Creation** (After completion)
   ```php
   // Buat index HANYA setelah backfill selesai
   // Prevent index maintenance overhead during backfill
   ```

---

## 3. SNAPSHOTS (Dashboard Cache)

### Konsep Dasar

**SNAPSHOT** = Pre-aggregated data untuk dashboard, refreshed setiap periode.

### Mengapa Ada Snapshots?

#### PROBLEM (Tanpa Snapshot):
```sql
-- Query LAMBAT: Aggregate 14M rows setiap kali
SELECT 
  COUNT(*) as account_count,
  SUM(saldo_idr) as total_balance
FROM simpanan_multipn
WHERE DATE(posisi) = '2026-04-28'
-- ❌ 14M rows = 2-5 seconds per query
-- ❌ Multiple dashboard loads = multiple aggregations
-- ❌ Network slow
```

#### SOLUTION (Dengan Snapshot):
```sql
-- Query CEPAT: Lookup pre-aggregated data
SELECT account_count, total_balance
FROM dashboard_simpanan_snapshots
WHERE snapshot_period = '2026-04-28'
-- ✅ 1 row lookup = <10ms
-- ✅ Instant dashboard load
```

### Snapshot Tables di Project

#### 1. DASHBOARD_SIMPANAN_SNAPSHOTS
```
Columns:
- snapshot_period (DATE)  : Period tanggalnya
- total_balance (DECIMAL) : Total saldo semua account
- account_count (INT)     : Jumlah account
- cif_count (INT)         : Jumlah CIF
- tabungan_balance        : Saldo tabungan
- giro_balance            : Saldo giro
- other_balance           : Saldo tipe lain

Refresh: Daily (otomatis trigger saat import)
```

#### 2. DASHBOARD_HARIAN_SNAPSHOTS (Loan data)
```
Columns:
- snapshot_period
- outstanding_balance
- npl_amount
- account_count
- branch_distribution
- segment_distribution

Refresh: Daily (batch process)
```

#### 3. PERFORMANCE_RM_SNAPSHOTS
```
Untuk: Performance Report per RM
Data: OS, Target, Achievement, etc
```

---

## 4. INTEGRATION: BACKFILL & SNAPSHOT

### Timing dan Dependencies

```
DAILY WORKFLOW:
┌─────────────┐
│ DATA IMPORT │ (simpanan_multipn INSERT)
└──────┬──────┘
       │
       ▼
┌─────────────────────────────────┐
│ TRIGGER: trg_merchant_detail... │
│ (Row-level, for each INSERT)    │
│ Action: DELETE from snapshots   │
│ Reason: Invalidate stale cache  │
└──────┬──────────────────────────┘
       │
       ▼
┌─────────────────────────────────┐
│ BACKFILL SHADOW COLUMNS         │
│ (For NEW imported data)         │
│ cif_normalized, account_norm... │
│ Automatic via trigger INSERT    │
└──────┬──────────────────────────┘
       │
       ▼
┌─────────────────────────────────┐
│ REBUILD SNAPSHOTS               │
│ (EnsureDashboardSnapshotJob)   │
│ Aggregate from source table     │
│ Store in snapshot tables        │
└──────┬──────────────────────────┘
       │
       ▼
        ✅ DASHBOARD READY
           (Next query hits snapshot)
```

---

## 5. SHADOW COLUMN BACKFILL PHASES

### Phase Configuration

```php
'phases' => [
    'phase_1_prepare' => [
        'name' => 'Initial table analysis and column creation',
        'Add new shadow columns
    ],
    'phase_2_simpanan_multipn' => [
        'name' => 'Simpanan MultiPN Optimization',
        'Backfill simpanan_multipn shadow columns
    ],
    'phase_3_daily_loan' => [
        'name' => 'Daily Loan Optimization',
        'Backfill daily_loan_dinamis shadow columns
    ],
    'phase_4_finalize' => [
        'name' => 'Index creation and cleanup',
        'Create indexes after backfill complete
    ],
]
```

### Completion Tracking

```php
'completion_threshold' => 95  // Require 95% complete

Why 95%?
- First 95% = Fast (easy rows)
- Last 5% = Slow (rows with NULL, edge cases)
- 95% threshold = Good enough for production
- Prevent hanging indefinitely on stragglers
```

---

## 6. SNAPSHOT INVALIDATION MECHANISM

### Auto-Invalidation via Triggers

```sql
TRIGGER: trg_merchant_detail_after_insert
WHEN: ROW is inserted into jumlah_merchant_detail

ACTION:
  IF @skip_snapshot_invalidation IS NOT SET THEN
    DELETE FROM dashboard_harian_snapshots
    WHERE snapshot_period = NEW.POSISI
  END IF

OPTIMIZATION:
  @skip_snapshot_invalidation = 1  (Set during bulk import)
  = Skip 50K delete queries
  = 6-15x faster import
```

### Snapshot Rebuild Process

```
1. Snapshot INVALIDATED
   (Row deleted from dashboard_XXX_snapshots)

2. Dashboard loads → Query hits source table
   (Slow, but happens once)

3. Job triggered: EnsureDashboardSnapshotJob
   (Aggregate from source, cache result)

4. Next dashboard load → Query hits snapshot
   (Fast: <10ms)
```

---

## 7. PERFORMANCE COMPARISON

### Query Performance

| Scenario | Method | Time | Index Use |
|----------|--------|------|-----------|
| Filter by raw CIF | REGEXP_REPLACE per row | 2-5s | ❌ No |
| Filter by shadow cif_normalized | Direct column match | 50ms | ✅ Yes |
| **10x faster** | | | |

### Aggregation Performance

| Scenario | Method | Time | Rows |
|----------|--------|------|------|
| Aggregate 14M rows | Live SUM() query | 2-3s | 14M |
| Aggregate from snapshot | Direct lookup | <10ms | 1 |
| **200x faster** | | | |

---

## 8. CURRENT STATE di Project

### Shadow Columns Status
```
✅ cif_normalized         (100% backfilled)
✅ account_normalized     (100% backfilled)
✅ branch_normalized      (100% backfilled)
✅ segment_normalized     (100% backfilled)

Indexes: ✅ Created dan active
```

### Snapshots Status
```
✅ dashboard_simpanan_snapshots
✅ dashboard_harian_snapshots
✅ performance_rm_snapshots
✅ rasio_casa_snapshots

Auto-refresh: ✅ Enabled via job queue
```

---

## 9. PRACTICAL EXAMPLE: Rasio CASA Query

### WITHOUT Shadow Columns (SLOW):
```sql
SELECT 
  COUNT(*) as account_count,
  SUM(saldo_idr) as savings_balance
FROM simpanan_multipn sm
LEFT JOIN daily_loan_dinamis dl 
  ON REGEXP_REPLACE(sm.CIFNO, '[^0-9]', '') = 
     REGEXP_REPLACE(dl.CIFNO, '[^0-9]', '')  -- Function per row!
WHERE sm.DATE(sm.posisi) = CURDATE()
  AND dl.DATE(dl.periode) = CURDATE()
GROUP BY sm.kantor_cabang

⏱️  Time: 5-10 seconds
🔴 Index: Cannot use (function calls)
💾 Rows scanned: 14M + 2M = 16M
```

### WITH Shadow Columns (FAST):
```sql
SELECT 
  COUNT(*) as account_count,
  SUM(saldo_idr) as savings_balance
FROM simpanan_multipn sm
LEFT JOIN daily_loan_dinamis dl 
  ON sm.cif_normalized = dl.cif_normalized  -- Direct column match
WHERE DATE(sm.posisi) = CURDATE()
  AND DATE(dl.periode) = CURDATE()
GROUP BY sm.kantor_cabang

⏱️  Time: 50-100ms
🟢 Index: Can use (direct column)
💾 Rows scanned: ~1000 (via index seek)
```

---

## 10. KEY TAKEAWAYS

### Backfill = One-time setup cost
```
- Add columns
- Process existing data (one-time)
- Create indexes
- Ongoing: Auto-populated on INSERT
```

### Snapshots = Recurring refresh
```
- Pre-aggregate for dashboard
- Invalidate on data change
- Rebuild automatically
- Query from cache (fast)
```

### Together = Optimal Performance
```
Backfill     → Direct column access (no function eval)
+ Snapshots  → Pre-aggregated data (no runtime calc)
= Dashboard queries: 200x faster ⚡
```

---

## Files untuk Reference

1. **Config**: `/config/shadow-columns.php`
2. **Backfill**: `/app/Console/Commands/BackfillShadowColumnsCommand.php`
3. **Snapshot**: `/app/Jobs/EnsureDashboardSnapshotJob.php`
4. **Dashboard**: `/app/Http/Controllers/DashboardSimpananController.php`
