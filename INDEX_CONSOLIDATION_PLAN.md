# Simpanan MultiPN Index Consolidation Plan
**Priority**: MEDIUM-HIGH (long-term optimization)  
**Current Status**: 23 indexes identified (excessive redundancy)  
**Target**: Consolidate to 5-7 strategic indexes

---

## Index Audit Results

```sql
SHOW KEYS FROM simpanan_multipn;
```

### Current Indexes (23 total)
```
PRIMARY (uniqueid_SMPN)                           -- PK [KEEP]
├─ 4x Composite Indexes:
│  ├─ idx_smp_posisi_cab_unit (posisi, kantor_cabang, unit_kerja)
│  ├─ idx_smp_posisi_status_cab_unit (posisi, status, kantor_cabang, unit_kerja)
│  ├─ idx_smp_posisi_updated (posisi, updated_at)
│  └─ idx_smp_posisi_cif (posisi, CIFNO)
├─ 5x Single-Column Indexes:
│  ├─ simpanan_multipn_kantor_cabang_index (kantor_cabang)
│  ├─ simpanan_multipn_unit_kerja_index (unit_kerja)
│  ├─ simpanan_multipn_cifno_index (CIFNO)
│  ├─ simpanan_multipn_status_index (status)
│  └─ idx_smp_jenis_simpanan_filter (jenis_simpanan)
├─ 4x Covering Indexes:
│  ├─ idx_smp_posisi_cif_covering (posisi, CIFNO, jenis_simpanan, saldo_idr)
│  ├─ idx_smp_dormant_covering (posisi, status, kantor_cabang, unit_kerja, no_rekening)
│  ├─ idx_smp_period_covering_counts (posisi, kantor_cabang, unit_kerja, no_rekening, CIFNO, jenis_simpanan, saldo_idr)
│  └─ idx_smp_posisi_distinct_queries (posisi, no_rekening, CIFNO)
```

### Redundancy Analysis

#### Problem 1: Overlapping Composite Indexes
```
idx_smp_posisi_cab_unit       (posisi, kantor_cabang, unit_kerja)
idx_smp_posisi_status_cab_unit (posisi, status, kantor_cabang, unit_kerja)
↑ Same prefix, idx_smp_posisi_status_cab_unit is superset
```

**Issue**: MySQL can only use ONE index per query. Having both wastes space.

#### Problem 2: Single-Column Indexes vs Composite
```
simpanan_multipn_kantor_cabang_index (kantor_cabang)
simpanan_multipn_unit_kerja_index (unit_kerja)
idx_smp_posisi_cab_unit (posisi, kantor_cabang, unit_kerja)
↑ If queries need posisi + kantor_cabang, use composite
↑ Single-column indexes are dead weight
```

#### Problem 3: Covering Indexes Overlap
```
idx_smp_posisi_cif_covering (posisi, CIFNO, jenis_simpanan, saldo_idr)
idx_smp_period_covering_counts (posisi, kantor_cabang, unit_kerja, no_rekening, CIFNO, jenis_simpanan, saldo_idr)
↑ Second covers first + more columns = first is redundant
```

---

## Proposed Index Strategy

### Phase 1: Analysis (Identify Query Patterns)

Before consolidating, need to analyze actual query usage:

```php
// Enable slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 0.5;  // Log queries > 500ms

// Analyze for 24 hours, then:
SELECT * FROM mysql.slow_log WHERE db = 'project_abah';

// Find which columns are most queried:
// - Dashboard filters (posisi, status, kantor_cabang, unit_kerja, jenis_simpanan)
// - Reports (aggregations by posisi, status)
// - Exports (by date range, branch, department)
```

### Phase 2: Strategic Index Plan

Based on **estimated** Dashboard/API usage patterns:

```sql
-- KEEP: Primary key
PRIMARY KEY (uniqueid_SMPN)

-- STRATEGIC: Period queries (MOST COMMON in dashboards/reports)
CREATE INDEX idx_smp_period_branch 
  ON simpanan_multipn (posisi, kantor_cabang, unit_kerja)
  COMMENT 'Dashboard: period + branch/dept filters';

-- STRATEGIC: Period + Status (status filter is common)
CREATE INDEX idx_smp_period_status 
  ON simpanan_multipn (posisi, status, kantor_cabang)
  COMMENT 'Dashboard: period + status filter';

-- STRATEGIC: Period + Account lookup
CREATE INDEX idx_smp_period_account 
  ON simpanan_multipn (posisi, no_rekening, CIFNO)
  COMMENT 'Account lookup by period';

-- STRATEGIC: Type filter (jenis_simpanan = TABUNGAN/GIRO/DEPOSITO)
CREATE INDEX idx_smp_jenis_simpanan_covering 
  ON simpanan_multipn (jenis_simpanan, posisi, saldo_idr)
  COMMENT 'Type filter + balance aggregation';

-- STRATEGIC: Balance aggregations (SUM queries)
CREATE INDEX idx_smp_updated_for_sync 
  ON simpanan_multipn (updated_at, posisi)
  COMMENT 'Snapshot sync by update time';

-- OPTIONAL: Account lookup without period (rare but useful)
CREATE INDEX idx_smp_cifno_simple 
  ON simpanan_multipn (CIFNO)
  COMMENT 'Quick account lookup';
```

**Result**: From 23 → 7 indexes (69% reduction)

---

## Expected Benefits

### Space Reduction
```
Current:  23 indexes × ~500MB per index = ~11.5GB overhead
Target:   7 indexes × ~500MB per index = ~3.5GB overhead
Savings:  ~8GB less disk I/O during import
```

### Import Speed
```
Before consolidation:
  - LOAD DATA with 23 indexes: 6-12 hours (thrashing)
  - Index rebuild: 10-20 minutes

After consolidation (with constraint optimization):
  - LOAD DATA with 7 indexes: 1-2 hours (manageable)
  - Index rebuild: 5-10 minutes
  - Total: ~2-3 hours (2-4x faster than status quo)
```

### Query Performance
```
Before: Queries may be slow due to optimizer confusion (too many choices)
After:  Clearer index selection, better query planner decisions
```

---

## Implementation Schedule

### ✅ **PHASE 1: Short-term (Immediate - Apr 26)**
- [x] Optimize constraint checking (SET unique_checks/foreign_key_checks)
- [x] Disable double-scan on large files
- [x] Improve Polars normalization

**Impact**: 42-minute import (vs 6+ hours)

### 🔄 **PHASE 2: Medium-term (This week)**
1. **Enable slow query log** for 24-48 hours
2. **Analyze index usage** from slow query log
3. **Identify which indexes actually used** by Dashboard/API
4. **Propose final index list** based on actual patterns

### ⏳ **PHASE 3: Long-term (Next sprint)**
1. **Create new indexes** in parallel (no downtime)
2. **Test queries** with new indexes
3. **Drop redundant indexes** after validation
4. **Optimize query plans** if needed

---

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|-----------|
| Drop index used by unexpected query | Slow query | Enable slow query log first |
| Import speed still slow | Low ROI on effort | Verify constraint optimization is sufficient |
| Dashboard queries break | P0 incident | Query test suite before index drop |

---

## Monitoring Plan

### Key Metrics
```sql
-- Check index statistics
SELECT object_schema, object_name, count_read, count_write
FROM performance_schema.table_io_waits_summary_by_index_usage
WHERE object_schema = 'project_abah'
  AND object_name = 'simpanan_multipn'
ORDER BY count_read DESC;

-- Check slow queries
SELECT query_time, rows_examined, rows_sent, sql_text
FROM mysql.slow_log
WHERE db = 'project_abah'
ORDER BY query_time DESC
LIMIT 20;
```

### Alerts
- If import > 3 hours: Check index usage
- If Dashboard query > 1s: Check if missing index
- If disk I/O spikes: Check index size

---

## SQL Consolidation Script (DRAFT)

```sql
-- PHASE 2A: Create strategic new indexes (no downtime)
CREATE INDEX idx_smp_period_branch 
  ON simpanan_multipn (posisi, kantor_cabang, unit_kerja);
CREATE INDEX idx_smp_period_status 
  ON simpanan_multipn (posisi, status, kantor_cabang);
CREATE INDEX idx_smp_period_account 
  ON simpanan_multipn (posisi, no_rekening, CIFNO);
CREATE INDEX idx_smp_jenis_covering 
  ON simpanan_multipn (jenis_simpanan, posisi, saldo_idr);
CREATE INDEX idx_smp_updated_sync 
  ON simpanan_multipn (updated_at, posisi);

-- PHASE 2B: Test with new indexes (verify queries still fast)
-- [Run Dashboard, API, export tests for 2-4 hours]

-- PHASE 2C: Drop redundant indexes (one by one, monitor carefully)
DROP INDEX idx_smp_posisi_cab_unit ON simpanan_multipn;
DROP INDEX idx_smp_posisi_status_cab_unit ON simpanan_multipn;
DROP INDEX idx_smp_posisi_updated ON simpanan_multipn;
DROP INDEX idx_smp_posisi_cif ON simpanan_multipn;
DROP INDEX simpanan_multipn_kantor_cabang_index ON simpanan_multipn;
DROP INDEX simpanan_multipn_unit_kerja_index ON simpanan_multipn;
DROP INDEX idx_smp_posisi_cif_covering ON simpanan_multipn;
DROP INDEX idx_smp_dormant_covering ON simpanan_multipn;
DROP INDEX idx_smp_period_covering_counts ON simpanan_multipn;
DROP INDEX idx_smp_posisi_distinct_queries ON simpanan_multipn;
DROP INDEX idx_smp_jenis_simpanan_filter ON simpanan_multipn;
-- Keep: idx_smp_cifno_simple, idx_smp_updated_for_sync (new ones)

-- PHASE 2D: Optimize table (rebuild remaining indexes)
OPTIMIZE TABLE simpanan_multipn;
```

---

## Comparison: Before vs After Optimization

| Phase | Strategy | LOAD Duration | Index Count | Disk Usage |
|-------|----------|---------------|-------------|-----------|
| Current (Apr 26) | Constraint optimization only | ~42 min | 23 | ~11.5GB |
| Phase 2 (Next week) | New index + drop redundant | ~1.5-2h | 7 | ~3.5GB |
| Phase 3 (Next month) | Query optimization | ~1h | 5-7 | ~3.5GB |

---

## Decision Point

### Should We Do Index Consolidation?

**YES IF**:
- Current 42-minute import is still too slow for business needs
- Disk space is constrained (8GB saving is significant)
- Dashboard queries are slow (20+ slow queries in log)

**MAYBE IF**:
- Business can tolerate 42-minute import for now
- Consolidation can wait for next quarter

**NO IF**:
- 42-minute import meets SLA requirements
- Resources better spent on other features

---

## Questions to Answer

1. **Is 42-minute import acceptable?** (vs original 6-9 hour SLA)
2. **Do we have slow query log data** to identify which indexes are actually used?
3. **Can we tolerate 2-4 hour index consolidation project** this sprint?
4. **Is 8GB disk space saving valuable** in our environment?

---

**Next Action**: Enable slow query log and collect 24h of query patterns before deciding on consolidation.
