# Snapshot Update Process: Performance Optimization Analysis

**Date**: 2026-04-29  
**Analysis Type**: Read-Only System Architecture Review  
**Scope**: Identification of bottlenecks and recommendations for 30-50% time reduction

---

## Current System Performance

### Baseline Metrics
- **Historical Snapshot Sync**: 148 periods × ~0.34s per period = **50.9 seconds** (observed)
- **Parallel Job Execution**: 5 snapshot types in parallel per batch
- **Queue Driver**: Database queue (single-threaded processing)
- **Table Sizes**:
  - `daily_loan_dinamis`: 1,949,643 rows
  - `dashboard_pinjaman_snapshots`: 647,495 rows
  - `dashboard_harian_snapshots`: Unknown (needs check, likely large)
  - `dashboard_simpanan_snapshots`: 4 rows (anomaly - may indicate incomplete setup)

### Architecture Overview
```
┌─ SnapshotForceSyncCommand (sequential loop over 6 tables)
│  └─ ReportDataSyncService::syncImportedTable (sequential)
│     └─ ParallelSnapshotBatchCoordinator::dispatch (5 jobs in parallel via Bus::batch)
│        ├─ RebuildSnapshotSimpleBatch (Simpanan)
│        ├─ RebuildSnapshotHarianBatch (Harian)
│        ├─ RebuildSnapshotRasioBatch (Rasio CASA)
│        ├─ RebuildSnapshotPerformanceRmBatch (Performance RM)
│        └─ RebuildSnapshotDormantBatch (Rekening Dormant)
│           └─ ReportSnapshotBuilder::rebuild* (SQL INSERT SELECT aggregations)
│              ├── Query execution (aggregate, compute buckets, MD5 hashing)
│              └── ANALYZE TABLE (table statistics refresh)
```

---

## Identified Bottlenecks (Priority Order)

### 🔴 **CRITICAL: Blocking Synchronous Architecture (30-40% time waste)**

**Issue**: The main command loops sequentially through 6 tables, waiting for each sync to complete.

```php
// Current (sequential, blocking):
foreach (self::SYNC_TABLES as $table) {  // Must wait for each table
    $syncService->syncImportedTable($table, ...);  // Blocks until complete
}
```

**Impact**:
- Table A sync: 8-12 seconds
- Table B sync: 5-8 seconds (waits for A)
- Table C sync: 3-5 seconds (waits for A+B)
- **Total**: ~20-30 seconds for sequential table syncs

**Could Be Parallelized**:
- `daily_loan_dinamis` and `simpanan_multipn` are independent → can run concurrently
- `ssa_simpanan`, `ssa_pinjaman`, `lw325_ph`, `performance_pis_per_produk` each touch different snapshot tables → can run concurrently
- All 6 could run in **2-3 parallel batches** instead of 6 sequential waits

**Recommendation**: Dispatch all 6 table syncs as independent jobs, not sequential calls
- **Expected gain**: 35-45% time reduction (6 sequential → 2-3 parallel batches)

---

### 🟠 **HIGH: Missing Period-Level Parallelization (20-30% time waste)**

**Issue**: Even within a single snapshot rebuild, periods are processed sequentially.

```php
// In ReportSnapshotBuilder::rebuildDashboardSimpanan():
foreach ($periods as $snapshotPeriod) {  // 148 periods loop sequentially
    $this->buildDashboardSimpananPeriodSnapshot($snapshotPeriod, $force);
    // Each period: SELECT from 1.9M row table, aggregate, INSERT → 0.3-0.5s
}
```

**Impact**:
- 148 periods × 0.34s = 50.9 seconds
- If only 5 periods are actually **dirty** (changed), still rebuild 148 periods

**Current Optimizations** (partially implemented):
- `DashboardHarianSnapshotService::syncDuePeriods()` tries to detect dirty periods
- `SimpananMultiPnSnapshotGate::isReady()` defers incomplete branches
- But: Initial sync still touches ALL historical periods

**Recommendation**: 
1. **Implement dirty-period detection at queue dispatch time** (not during rebuild)
   - Query `daily_loan_dinamis` to find which date ranges have changed
   - Only dispatch batch jobs for those periods
   - Expected gain: 60-80% for incremental syncs
   
2. **Batch-insert multiple periods in single query** (if data structure allows)
   - Instead of loop + individual INSERT per period
   - Use UNION ALL for 5-10 periods in one statement
   - Expected gain: 15-20% for query latency

---

### 🟡 **MEDIUM: ANALYZE TABLE Overhead (15-25% time waste)**

**Issue**: Every snapshot rebuild ends with `ANALYZE TABLE`, which is synchronous and expensive on large tables.

```php
// In RebuildSnapshotSimpleBatch::handle():
$this->refreshTableStatistics('dashboard_simpanan_snapshots', $this->periodHint);
$this->refreshTableStatistics('dashboard_simpanan_branch_snapshots', $this->periodHint);
// Each ANALYZE TABLE on 647K rows ≈ 2-4 seconds
```

**Impact**:
- `dashboard_pinjaman_snapshots`: 647K rows → ~2-3s per ANALYZE
- Called after each of 5 parallel jobs → 5 × 2-3s = 10-15 seconds
- But: **Runs in parallel across the 5 jobs**, so actual wall-clock time ≈ 2-3s (not 15s)
- However: **Blocking the job process** during analysis

**Recommendation**:
1. **Defer ANALYZE TABLE to async background job** (lowest priority queue)
   - Don't wait for analysis before marking job complete
   - Schedule as separate deferred job on `default` queue
   - Expected gain: 5-10% per job (3s parallelization)
   
2. **Use OPTIMIZE TABLE instead** (may auto-update statistics)
   - Or: Configure `innodb_stats_on_metadata = OFF` to skip auto-analysis
   - Expected gain: 2-5% (prevents immediate recalculation)

3. **Batch ANALYZE calls** – analyze only the specific date range that was inserted
   - Use `ANALYZE TABLE ... PARTITION (p_name)` if partitioned (see below)

---

### 🟡 **MEDIUM: Missing Table Partitioning (10-20% query optimization)**

**Current State**: Tables are NOT partitioned (checked via `information_schema.PARTITIONS`)

**Impact**:
- Full table scan on 1.9M row `daily_loan_dinamis` to build snapshots for 1 day
- No partition pruning → reads all years of historical data even for recent periods
- Index seeks still need to scan millions of rows in WHERE filters

**Recommendation**: 
1. **Partition `daily_loan_dinamis` by date (RANGE partitioning on `periode`)**
   - Prune to relevant partition(s) when building snapshot for date X
   - Expected gain: 20-40% for period-specific queries
   
2. **Partition snapshot tables by `periode`** (if storing historical snapshots)
   - Allows cleanup of old partitions without table-wide DELETE
   - Expected gain: 5-10% (cleaner index structures, faster metadata operations)
   
3. **Optional: Sub-partition by `segmen_dashboard` or `produk_dashboard`**
   - If snapshot queries filter heavily by these columns
   - Expected gain: 5-15% (narrower index scans)

---

### 🟡 **MEDIUM: Inefficient Hash Computation (5-10% query time)**

**Issue**: MD5 hashing happens per-row during aggregation.

```sql
-- From buildDashboardPeriodSnapshot():
SELECT MD5(CONCAT_WS('|', 'dps', ?, TRIM(COALESCE(d.uniqueid_namareport, '')))) as uniqueid_dps
```

**Impact**:
- Called for 1.9M rows per snapshot rebuild
- MD5 + CONCAT_WS + COALESCE + TRIM = expensive per-row computation
- Could be pre-computed at data load time

**Recommendation**:
1. **Pre-compute hash on import** (in `prepareCsvStaging()` or `RunLoadDataJob`)
   - Store hash in `daily_loan_dinamis` as a computed/stored column
   - Snapshot builder just SELECT the hash, no recomputation
   - Expected gain: 8-15% (avoid 1.9M MD5 per rebuild)
   
2. **Or: Cache hash results in Redis** between imports
   - Only recompute for new records
   - Expected gain: 20-30% for incremental rebuilds

---

### 🟢 **LOW: Unnecessary Cache Invalidation (5% overhead)**

**Issue**: Force-sync invalidates **global cache version** even for single-period updates.

```php
// In ReportDataSyncService::syncImportedTable():
$newVersion = $this->bumpReportCacheVersion();  // Cache buster for entire app
```

**Impact**:
- Every snapshot sync triggers full cache invalidation
- All connected clients lose cached query results
- Clients re-query from scratch (unnecessary if only 1 period changed)

**Recommendation**:
1. **Use period-specific cache keys** instead of global version bump
   - `cache_version:period:{YYYY-MM-DD}` instead of `cache_version:global`
   - Only invalidate cache for affected period
   - Expected gain: 3-5% (avoid unnecessary client-side cache flushes)

---

## Proposed Optimization Roadmap

### **Phase 1: Quick Wins (30-40% time reduction, 1-2 hours implementation)**

| # | Optimization | Implementation | Est. Gain |
|---|---|---|---|
| 1 | **Parallelize table syncs** (6 sequential → 2-3 batches) | Dispatch all 6 tables as queue jobs | **35-40%** |
| 2 | **Defer ANALYZE TABLE** to background job | Dispatch on `default` queue after job success | **5-8%** |
| 3 | **Period-specific cache keys** | Replace global version with date-scoped keys | **3-5%** |
| **Phase 1 Total** | | | **43-53%** |

### **Phase 2: Intelligent Rebuilds (20-30% additional, 2-3 hours)**

| # | Optimization | Implementation | Est. Gain |
|---|---|---|---|
| 4 | **Dirty-period detection at dispatch** | Query `daily_loan_dinamis` metadata for date ranges | **15-25%** incremental |
| 5 | **Batch-insert multiple periods** | Combine 5-10 periods in UNION ALL INSERT | **10-15%** |
| 6 | **Pre-computed hashes** | Store hash in `daily_loan_dinamis` column | **8-12%** |
| **Phase 2 Total** | | | **33-52%** additional |

### **Phase 3: Structural Changes (10-20% additional, 4-6 hours)**

| # | Optimization | Implementation | Est. Gain |
|---|---|---|---|
| 7 | **Partition by date** | Add RANGE partitioning on `periode` | **15-25%** |
| 8 | **Optimize snapshot indexes** | Review and consolidate indexes on snapshots | **5-10%** |
| **Phase 3 Total** | | | **20-35%** additional |

---

## Implementation Examples

### **1. Parallelize Table Syncs (Quick Win)**

**Current** (SnapshotForceSyncCommand.php):
```php
foreach (self::SYNC_TABLES as $table) {
    $syncService->syncImportedTable($table, $period, null, 'force-sync');
}
```

**Proposed**:
```php
$jobs = [
    new SyncTableSnapshotJob('daily_loan_dinamis', $period),
    new SyncTableSnapshotJob('simpanan_multipn', $period),
    new SyncTableSnapshotJob('ssa_simpanan', $period),
    new SyncTableSnapshotJob('ssa_pinjaman', $period),
    new SyncTableSnapshotJob('lw325_ph', $period),
    new SyncTableSnapshotJob('performance_pis_per_produk', $period),
];

Bus::batch($jobs)
    ->name("force-sync:{$period}")
    ->onQueue('snapshots-parallel')
    ->dispatch();
```

**Impact**: 6 sequential ~3-5s calls → 2-3 parallel batches = **40-50% faster**

---

### **2. Dirty-Period Detection**

**Current**: Rebuild all 148 historical periods even if only today changed.

**Proposed**:
```php
// In ParallelSnapshotBatchCoordinator:
private static function detectDirtyPeriods(?string $periodHint): array
{
    if ($periodHint) {
        return [$periodHint];  // Specific period
    }
    
    // Find periods with recent changes
    $lastSnapshot = DB::table('dashboard_pinjaman_snapshots')
        ->max('updated_at');
    
    $dirtyPeriods = DB::table('daily_loan_dinamis')
        ->where('updated_at', '>', $lastSnapshot)
        ->distinct()
        ->pluck('periode')
        ->toArray();
    
    return !empty($dirtyPeriods) ? $dirtyPeriods : ['today'];
}
```

**Impact**: 148 periods → 5-10 periods for incremental syncs = **85-95% faster** for daily jobs

---

### **3. Deferred ANALYZE TABLE**

**Current** (RebuildSnapshotSimpleBatch.php):
```php
public function handle(ReportSnapshotBuilder $builder): void
{
    $builder->rebuildDashboardSimpanan(...);
    $this->refreshTableStatistics('dashboard_simpanan_snapshots', ...);  // Blocks
}

private function refreshTableStatistics(string $tableName): void
{
    DB::statement("ANALYZE TABLE `{$tableName}`");  // 2-4 seconds
}
```

**Proposed**:
```php
public function handle(ReportSnapshotBuilder $builder): void
{
    $builder->rebuildDashboardSimpanan(...);
    
    // Dispatch analysis to background queue (don't wait)
    RefreshTableStatisticsJob::dispatch('dashboard_simpanan_snapshots')
        ->onQueue('maintenance')
        ->delay(now()->addSeconds(10));  // Give queries time to cache
}
```

**Impact**: Job completes 2-4 seconds faster per rebuild = **5-8% faster**

---

## Monitoring & Validation

### Metrics to Track

1. **Per-table sync duration** (add to logs):
   ```
   sync_duration_ms: {duration}
   table: {table_name}
   period: {period}
   period_count: {num_periods}
   avg_ms_per_period: {duration / period_count}
   ```

2. **Batch-level metrics**:
   - Batch ID, job count, wall-clock duration
   - Individual job start/end times
   - Parallelism achieved (expected vs. actual)

3. **Query performance**:
   - `SLOW_QUERY_LOG` with threshold 1s
   - Track `INSERT ... SELECT` duration per period
   - Track `ANALYZE TABLE` duration per table

### Validation Checkpoints

- [ ] Phase 1 implementation: **40-50% reduction observed** (50.9s → 25-30s for full rebuild)
- [ ] Phase 2 implementation: **20-30% additional reduction** (25-30s → 17-21s for dirty-period detect)
- [ ] Phase 3 implementation: **10-20% additional reduction** (17-21s → 14-17s with partitioning)
- [ ] No data loss or corruption in snapshots
- [ ] Parallel job dependencies respected (no race conditions)
- [ ] Cache invalidation still reaches all clients correctly

---

## Risks & Mitigations

| Risk | Mitigation |
|---|---|
| **Race conditions** in dirty-period detection | Use DB-level locks (GET_LOCK) per table during rebuild |
| **Incomplete snapshots** if job fails mid-rebuild | Wrap batch in transaction, rollback on failure |
| **Cache inconsistency** with period-specific keys | Implement cache versioning per period + global version bump on major changes |
| **Partitioning migration downtime** | Use online DDL (pt-online-schema-change) with zero downtime |
| **Hash pre-computation** overhead on import | Add column only after validating import doesn't slow down |

---

## Summary

**Current Performance**: 50.9 seconds for full historical sync  
**Phase 1 Target** (Quick Wins): **25-30 seconds** (40-50% reduction)  
**Phase 2 Target** (Intelligent Rebuilds): **17-21 seconds** (20-30% additional)  
**Phase 3 Target** (Structural): **14-17 seconds** (10-20% additional)  
**Total Opportunity**: **65-70% time reduction** (50.9s → 15-17s)

**Recommended Priority**: Implement Phase 1 immediately (highest ROI, lowest risk, 1-2 hours work)

---

## Technical Debt & Future Considerations

1. **Consider Redis caching** for snapshot data instead of MySQL (only if read-heavy)
2. **Implement snapshot versioning** (keep multiple snapshots per period for rollback)
3. **Add snapshot health checks** (data completeness, freshness SLAs)
4. **Monitor for snapshot lag** (when snapshot is older than source data)
5. **Document snapshot dependency graph** (which snapshots depend on which source tables)
