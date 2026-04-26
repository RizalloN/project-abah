# Performance RM Cabang Snapshot - Fast Path Implementation

## Problem Statement
**Current Flow**:
```
Dashboard Request
→ Query performance_rm_snapshots (thousands of RM rows)
→ Pivot by periode (4 periods: current, YoY, MTD, YTD)
→ Group by cabang
→ 5-minute cache
→ Render (slow on first load, cache misses)
```

**Performance Impact**:
- Thousands of rows for each query
- Complex pivoting in application memory
- Cache thrashing during peak hours
- Slow first-load experience for executives

## Solution: Cabang-Level Summary Table

**New Flow**:
```
Dashboard Request
→ Query performance_rm_cabang_snapshots (aggregated by cabang only)
→ Data already pivoted by periode
→ Direct rendering
→ 5-minute cache
→ Fast response (even on first load)
```

## Table Design: `performance_rm_cabang_snapshots`

```sql
CREATE TABLE performance_rm_cabang_snapshots (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    periode DATE NOT NULL,
    cabang VARCHAR(100) NOT NULL,
    segmen VARCHAR(50) NOT NULL,
    produk VARCHAR(100) NOT NULL,
    
    -- Aggregated metrics (SUM across all RM in this cabang/segmen/produk)
    loan_os DECIMAL(20,2) DEFAULT 0,
    lancar_os DECIMAL(20,2) DEFAULT 0,
    sml_os DECIMAL(20,2) DEFAULT 0,
    npl_os DECIMAL(20,2) DEFAULT 0,
    total_deb INT DEFAULT 0,
    lancar_deb INT DEFAULT 0,
    sml_deb INT DEFAULT 0,
    npl_deb INT DEFAULT 0,
    restruk_os DECIMAL(20,2) DEFAULT 0,
    realisasi_deb INT DEFAULT 0,
    realisasi_os DECIMAL(20,2) DEFAULT 0,
    total_deposit DECIMAL(20,2) DEFAULT 0,
    plafon DECIMAL(20,2) DEFAULT 0,
    
    -- Covering index for dashboard queries
    KEY idx_periode_cabang_segmen (periode, cabang, segmen),
    KEY idx_periode_segmen_produk (periode, segmen, produk),
    UNIQUE KEY unique_snapshot (periode, cabang, segmen, produk),
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## Data Population Strategy

### Option A: Post-Snapshot Build (Recommended)
**When**: After `buildPerformanceRmPeriodSnapshot()` completes
**Approach**: 
```sql
INSERT INTO performance_rm_cabang_snapshots 
  (periode, cabang, segmen, produk, loan_os, lancar_os, sml_os, npl_os, ...)
SELECT 
  periode, 
  cabang, 
  segmen,  -- Store in snapshot for faster access
  produk,
  SUM(loan_os) as loan_os,
  SUM(lancar_os) as lancar_os,
  SUM(sml_os) as sml_os,
  SUM(npl_os) as npl_os,
  SUM(total_deb) as total_deb,
  ...
FROM performance_rm_snapshots
WHERE periode = ?
GROUP BY cabang, segmen, produk
ON DUPLICATE KEY UPDATE
  loan_os = VALUES(loan_os),
  lancar_os = VALUES(lancar_os),
  ...
```

**Pros**:
- Simple to implement (just one extra query after RM snapshot)
- No redundant data (derives from single source of truth)
- Fast aggregation (using MySQL's GROUP BY instead of PHP pivoting)

**Cons**:
- Requires segmen column in performance_rm_snapshots (already there ✅)

### Option B: Direct from daily_loan_dinamis
**When**: Bypass RM snapshot if we only need cabang level
**Approach**: Query and aggregate directly, skip RM-level snapshot
**Pros**: Single pass aggregation
**Cons**: Requires refactoring, data inconsistency if RM logic changes

## Migration Path

### Phase 1: Create Table & Build Historical Data
1. Create `performance_rm_cabang_snapshots` table
2. Backfill with data from existing `performance_rm_snapshots`
3. Verify data correctness

### Phase 2: Update Snapshot Builder
1. Add `buildPerformanceRmCabangSnapshots()` method
2. Call after `buildPerformanceRmPeriodSnapshot()` completes
3. Maintain consistency between tables

### Phase 3: Update Dashboard Controller
1. (Optional) Update `KinerjaRmReportController` to use cabang snapshot when no RM detail needed
2. Keep RM snapshot for detail views

### Phase 4: Optimize Indexes
1. Add covering indexes for common filter patterns:
   - WHERE periode = ? AND cabang = ? AND segmen = ?
   - WHERE periode = ? AND segmen = ?

## Expected Performance Gains

| Scenario | Before | After | Improvement |
|----------|--------|-------|-------------|
| Load all cabangs | 500ms query + pivoting | 50ms query (index-only) | **10x faster** |
| Filter by cabang | 200ms query + pivoting | 10ms query | **20x faster** |
| Aggregate by segmen | 300ms query + pivoting | 20ms query | **15x faster** |
| Cache hit (5m) | Instant (cached) | Instant (cached) | Same |
| Cache miss load | Slow (complex pivot) | Fast (simple query) | **Better UX** |

## Data Consistency Checks

**Validation Query** (post-build):
```sql
SELECT cabang, segmen, produk, periode,
  SUM(loan_os) as expected_loan_os,
  (SELECT SUM(loan_os) FROM performance_rm_cabang_snapshots 
   WHERE periode = p.periode AND cabang = p.cabang AND segmen = p.segmen AND produk = p.produk) as actual_loan_os
FROM performance_rm_snapshots p
GROUP BY cabang, segmen, produk, periode
HAVING expected_loan_os != actual_loan_os;
```

## Related Tables Reference
- `performance_rm_snapshots` - RM-level detail (source of truth)
- `daily_loan_dinamis` - Raw transaction data
- `performance_targets` - Manual targets for comparison

## Implementation Order
1. ✅ Create migration for table structure
2. ⏳ Backfill historical data
3. ⏳ Update ReportSnapshotBuilder::buildPerformanceRmPeriodSnapshot()
4. ⏳ Add data validation command
5. ⏳ Monitor and verify consistency
6. ⏳ (Optional) Update controller to use cabang snapshot

---
**Status**: Design phase - Ready for implementation  
**Owner**: Claude Code + Senior Program Developer  
**Target**: Reduce dashboard query time by 10-20x on first load
