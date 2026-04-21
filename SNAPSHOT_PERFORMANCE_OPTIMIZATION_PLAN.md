# Dashboard Harian Snapshot Performance Optimization

## Bottleneck Analysis (11.5 seconds per period)

### Current Flow:
```
buildAggregatedRowsForPeriod($period)
  ├─ fetchSavingsAggregates() → SELECT ... GROUP BY kanca, unit
  ├─ fetchLoanAggregates()  → SELECT ... GROUP BY cabang, unit
  ├─ fetchRecoveryAggregates() → SELECT ... GROUP BY kanca, unit
  │
  ├─ Build buckets in PHP (foreach loops)
  ├─ Multiple passes through payload
  └─ Create summary rows
     └─ upsert (batch 250 rows)
```

### Performance Issues:
1. ❌ **3 separate SELECT queries** - could be 1 UNION query
2. ❌ **PHP array iterations** - doing aggregation in app code instead of SQL
3. ❌ **Multiple passes** - pass 1 collect, pass 2 detail, pass 3 summary
4. ❌ **No query caching** - same queries run every time even if source data unchanged
5. ❌ **No index optimization** - checking table indexes

---

## Optimization Strategy

### Phase 1: Index Optimization (Quick win)
✅ Ensure indexes exist on period + kanca + unit columns
- `ssa_pinjaman(periode, kantor_cabang, unit_kerja)`
- `ssa_simpanan(periode, kantor_cabang, unit_kerja)`

### Phase 2: Query Consolidation (Moderate optimization)
- Combine 3 queries into 1-2 optimized queries
- Use window functions to avoid multiple SELECT ... GROUP BY
- Reduce PHP loops

### Phase 3: Incremental Updates (Advanced)
- Only rebuild changed periods instead of all
- Cache last rebuild timestamp per period
- Only re-aggregate if source data changed

### Phase 4: Async Batching (Already done)
- Dispatch to background queue ✅
- User sees response in 0.6s ✅

---

## Implementation Roadmap

**Target**: 11.5 seconds → 2-3 seconds per period (4-5x faster)

| Step | Optimization | Expected Time | Status |
|------|--------------|----------------|--------|
| 1 | Add missing indexes | 11.5s → 8s | ⏳ TODO |
| 2 | Consolidate queries | 8s → 4-5s | ⏳ TODO |
| 3 | Reduce PHP loops | 4-5s → 2-3s | ⏳ TODO |
| 4 | Use incremental build | 2-3s → 1s | ⏳ TODO |

