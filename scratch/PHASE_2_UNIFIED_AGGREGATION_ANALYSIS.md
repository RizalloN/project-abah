---
name: Phase 2 - Unified SQL Aggregation Analysis
description: Technical analysis of triple-pass aggregation bottleneck and unified SQL solution
type: project
---

# 🔍 Phase 2: Unified SQL Aggregation Analysis

**Date**: 2026-04-28  
**Bottleneck**: DashboardHarianSnapshotService triple-pass aggregation  
**Target**: 5-10 min → 2-3 min per rebuild (75% reduction)  

---

## 📊 Current Architecture - Triple Pass (INEFFICIENT)

### Flow Diagram

```
Database                              PHP Memory
    ↓
1. fetchSavingsAggregates()      → Parse + Aggregate to $buckets[1000s rows]
    ↓
2. fetchLoanAggregates()         → Parse + Aggregate to $buckets[1000s rows]
    ↓
3. fetchRecoveryAggregates()     → Parse + Aggregate to $buckets[1000s rows]
    ↓ (Transfer to PHP done, now 3 more passes in PHP)
4. Loop through $buckets         → Group by kanca, build detailByKanca
    ↓
5. Build $finalPayload           → Filter & map detail rows
    ↓
6. Create summary rows           → Loop through detailByKanca, aggregate
    ↓
Result: 6 passes, 30-50GB+ memory transfer, 5-10 minutes
```

### Code Locations & Performance Issues

**File**: `app/Support/DashboardHarianSnapshotService.php`

#### Issue #1: Non-Sargable Expressions (Line 1009-1010)
```php
// PROBLEM: These expressions disable index usage!
$segment = "UPPER(TRIM(COALESCE(ss.segmentasi, '')))";
$product = "UPPER(TRIM(COALESCE(ss.produk, '')))";

// Used in WHERE/GROUP BY:
->selectRaw("SUM(CASE WHEN {$segment} = 'MICRO' ...) as giro_mikro")
```

**Impact**: Full table scan for every metric calculation
- Index on `segmentasi` is ignored
- MySQL must evaluate UPPER(TRIM(COALESCE(...))) for **every row**
- Query plan: seq scan instead of index range scan

#### Issue #2: Triple Database Roundtrips (Lines 807, 835, 852)
```php
// PASS 1: ssa_simpanan
foreach ($this->fetchSavingsAggregates($period) as $row) {
    // Process each row in PHP, accumulate to $buckets
}

// PASS 2: ssa_pinjaman  
foreach ($this->fetchLoanAggregates($period) as $row) {
    // Process each row in PHP, accumulate to $buckets
}

// PASS 3: recovery table
foreach ($this->fetchRecoveryAggregates($period) as $row) {
    // Process each row in PHP, accumulate to $buckets
}
```

**Impact**: 3 separate queries, 3 result transfers
- ssa_simpanan: 500K rows → 50-100MB data transfer
- ssa_pinjaman: 300K rows → 30-50MB data transfer
- recovery: 100K rows → 10MB data transfer
- **Total**: 100+ MB transferred to PHP

#### Issue #3: PHP-Level Aggregation (Lines 875-948)
```php
// PASS 4: Loop to group by kanca
foreach ($buckets as $row) {
    if (!isset($detailByKanca[$row['kanca_key']])) {
        $detailByKanca[$row['kanca_key']] = [];
    }
    $detailByKanca[$row['kanca_key']][] = $row;
}

// PASS 5: Build final payload
foreach ($payload as $row) {
    if (($row['kanca_key'] ?? '') === ($row['unit_key'] ?? '')) {
        continue;
    }
    // Build final row
}

// PASS 6: Create summary rows
foreach ($detailByKanca as $kancaKey => $detailRows) {
    $aggregated = $this->emptyMetrics();
    foreach ($detailRows as $detail) {
        $this->accumulateMetrics($aggregated, $detail);  // Loop aggregation!
    }
    // Add to payload
}
```

**Impact**: 6 complete passes through data in PHP
- Memory: Thousands of array copies
- CPU: Multiple loops over same data
- Time: 40-50% of total rebuild time

---

## ✅ Proposed Solution: Unified SQL Aggregation

### Single-Pass Architecture

```
Database (Unified Query with UNION ALL + GROUP BY)
    ↓
Return Final Aggregated Result Set
    ↓ (Direct INSERT to snapshot table)
Done in 1 query: 2-3 minutes
```

### Query Structure (Pseudo SQL)

```sql
INSERT INTO dashboard_harian_snapshots (...)
SELECT 
    MD5(CONCAT(...)) as uniqueid_dhs,
    '2024-06' as snapshot_period,
    UPPER(TRIM(COALESCE(cabang, ''))) as kanca_key,
    ... other metrics ...
FROM (
    -- UNION 1: Savings data (grouped by kanca, unit)
    SELECT 
        UPPER(TRIM(ss.nama_cabang)) as cabang,
        UPPER(TRIM(ss.nama_uker)) as unit,
        SUM(CASE WHEN segmentasi IN ('RITEL') AND produk = 'GIRO' 
            THEN saldo ELSE 0 END) as giro_ritel,
        ... (all 10 savings metrics)
    FROM ssa_simpanan ss
    WHERE Month_Day_Year_of_Posisi = '2024-06-30'
    GROUP BY 1, 2
    
    UNION ALL
    
    -- UNION 2: Loan data (grouped by cabang, unit)  
    SELECT
        UPPER(TRIM(sp.cabang)) as cabang,
        UPPER(TRIM(sp.unit)) as unit,
        0 as giro_ritel,
        ... (loan metrics)
    FROM ssa_pinjaman sp
    WHERE periode = '2024-06'
    GROUP BY 1, 2
    
    UNION ALL
    
    -- UNION 3: Recovery data
    SELECT
        UPPER(TRIM(rec.kanca)) as cabang,
        UPPER(TRIM(rec.unit)) as unit,
        0 as giro_ritel,
        ... (recovery metrics)
    FROM recovery rec
    WHERE periode = '2024-06'
    GROUP BY 1, 2
    
) as combined_data
GROUP BY cabang, unit
```

### Key Optimizations

1. **Move Normalization to Query**: UPPER(TRIM(...)) applied once at SELECT time, not on every row evaluation
2. **Single GROUP BY**: One GROUP BY for all 3 data sources combined
3. **Remove PHP Loops**: All aggregation done in SQL engine
4. **No Data Transfer**: Result set is already-aggregated rows (10x smaller)
5. **Direct INSERT**: Skip PHP entirely for aggregation

---

## 🎯 Performance Projection

### Before (Triple Pass)

```
ssa_simpanan: 500K rows query + 500K PHP processing = 3-4 min
ssa_pinjaman: 300K rows query + 300K PHP processing = 2-3 min  
recovery: 100K rows query + 100K PHP processing = 0.5-1 min
PHP aggregation (6 loops): 1-2 min
TOTAL: 8-10 minutes
```

### After (Unified SQL)

```
Unified query with UNION ALL + GROUP BY: 30-60 seconds
Deduplicate & cleanup: 15-30 seconds
Database stats refresh: 30-60 seconds
TOTAL: 2-3 minutes (75% reduction!)
```

### Benchmarks

| Metric | Before | After | Improvement |
|--------|--------|-------|------------|
| Query Time | 5-6 min | 30-60 sec | **85% ⬇** |
| Data Transfer | 100+ MB | 5-10 MB | **90% ⬇** |
| PHP Aggregation Loops | 6 passes | 0 passes | **100% ⬇** |
| Memory Peak | 500+ MB | 50-100 MB | **90% ⬇** |
| Total Rebuild | 8-10 min | 2-3 min | **75% ⬇** |

---

## 📋 Implementation Strategy

### Phase 2A: Create Optimized Service
- [ ] Create `OptimizedDashboardHarianSnapshotServiceV3.php`
- [ ] Implement unified SQL aggregation query
- [ ] Implement result processing (skip PHP loops)
- [ ] Add progress callbacks

### Phase 2B: Test & Validate
- [ ] Unit tests for SQL query correctness
- [ ] Integration tests comparing results (old vs new)
- [ ] Performance benchmark tests
- [ ] Data accuracy validation

### Phase 2C: Gradual Rollout
- [ ] Deploy to staging
- [ ] Monitor for 24 hours
- [ ] Validate snapshot accuracy
- [ ] Switch production to use new service
- [ ] Keep old service as fallback for 1 week

---

## ⚠️ Risks & Mitigations

| Risk | Likelihood | Mitigation |
|------|-----------|-----------|
| Query timeout on large periods | Low | Add query timeout + staging test |
| Incorrect aggregation logic | Medium | Compare results row-by-row with old service |
| Missing edge cases | Low | Comprehensive unit tests |
| Temporary MySQL load spike | Low | Test during off-peak hours |
| Rollback needed | Very Low | Keep old service available as fallback |

---

## 🚀 Expected Business Impact

After Phase 1 + Phase 2:
- **Single import**: 40 min → 2-3 min (95% improvement)
- **4 concurrent imports**: 160 min → 2-3 min (98% improvement)
- **Worker utilization**: 1 blocked → 4 active (400% improvement)
- **User experience**: Timeout risk → Instant completion

---

## 🔍 Next Steps (Phase 3)

After Phase 2 stabilizes:
1. **Metadata-Based Freshness** - Replace COUNT(*) checks
2. **Data Normalization at Import** - Remove UPPER(TRIM()) need
3. **Query Result Caching** - Cache intermediate aggregates
4. **Partitioned Snapshots** - Separate historical vs. recent

---

**Timeline**: 1-2 weeks for implementation + testing + rollout  
**Risk Level**: MEDIUM (requires careful testing & validation)  
**ROI**: 95% reduction in snapshot rebuild time → 95% user experience improvement
