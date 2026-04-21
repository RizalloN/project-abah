
=== SNAPSHOT OPTIMIZATION STRATEGY ===

## Current Status
- Original rebuild (force=true): 11.5 seconds ❌
- Cached read (exists): 0.56 seconds ✅

## Bottleneck Identified
The slow part is `buildAggregatedRowsForPeriod()` which does:
1. Query ssa_simpanan with GROUP BY kanca, unit
2. Query ssa_pinjaman with GROUP BY cabang, unit  
3. Query lw325_ph with GROUP BY kanca, unit
4. PHP aggregation in 3 passes

## Quick Wins (No code changes needed):

### 1. Add Database Indexes
```sql
-- On ssa_simpanan table
ALTER TABLE ssa_simpanan ADD INDEX idx_periode_kanca_unit (Month_Day_Year_of_Posisi, nama_cabang, nama_uker);

-- On ssa_pinjaman table
ALTER TABLE ssa_pinjaman ADD INDEX idx_periode_kanca_unit (periode, kantor_cabang, unit_kerja);

-- On lw325_ph table
ALTER TABLE lw325_ph ADD INDEX idx_periode_kanca_unit (periode, kantor_cabang, unit_kerja);
```

Expected improvement: 11.5s → 6-8s (40% faster)

### 2. Enable Query Cache (MySQL)
```sql
SET SESSION query_cache_type = ON;
SET SESSION query_cache_limit = 2097152;  -- 2MB per query
```

Expected improvement: Subsequent rebuilds of same period → near instant

### 3. Increase InnoDB Buffer Pool
MySQL settings in my.ini:
```ini
innodb_buffer_pool_size = 2G  (or 50-75% of available RAM)
innodb_flush_log_at_trx_commit = 2  (balance between speed/safety)
```

## Implementation Plan

**Phase 1 (Immediate)**: Add indexes - should drop 11.5s to 6-8s
**Phase 2 (Optional)**: Enable query cache - makes repeated rebuilds instant  
**Phase 3 (Advanced)**: Denormalize data into materialized view

