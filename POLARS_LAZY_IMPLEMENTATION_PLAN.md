# Polars Lazy Evaluation Implementation Plan

## Fase 1: Analyze & Design (DONE)

### Current State
- ✅ Using Polars eager evaluation in `ssa_pinjaman_polars_processor.py`
- ✅ Flow: Read Full → Filter → Write
- ✅ Performance: ~45s untuk 5M baris (database LOAD DATA faster)

### Lazy Evaluation Benefits
1. **Predicate Pushdown** - Filter pushed to read level
2. **Column Projection** - Only select needed columns during read
3. **Memory Efficiency** - Chunk-based reading, not full load
4. **Auto Parallelization** - Multi-threaded operations
5. **Query Optimization** - Entire plan analyzed before execution

### Target Performance
- Expected: 65-75% faster (12-15s untuk 5M baris)
- Memory: 50% less

---

## Fase 2: Create Lazy Module

### File: `scripts/ssa_pinjaman_lazy_processor.py`
- Implement `stage_ssa_pinjaman_lazy()` function
- Use `pl.scan_csv()` instead of `pl.read_csv()`
- Build lazy pipeline with transformations
- Call `.collect()` only for output

### Key Optimizations

#### 1. Lazy Scan
```python
df_lazy = pl.scan_csv(
    path,
    separator=delimiter,
    has_header=True,
    schema_overrides=schema_overrides,
    ignore_errors=False
)
```

#### 2. Predicate Pushdown
```python
df_lazy = (
    df_lazy
    .filter(
        pl.col("month_day_year_of_periode").is_not_null()
        & (pl.col("month_day_year_of_periode") != "")
        & pl.col("nama_cabang").is_not_null()
    )
)
```

#### 3. Column Projection
```python
df_lazy = df_lazy.select([
    col for col in schema_overrides.keys()
])
```

#### 4. String Operations (Parallelized)
```python
string_columns = [c for c in schema_overrides.keys()]
df_lazy = df_lazy.with_columns([
    pl.col(col).str.strip_chars().alias(col)
    for col in string_columns
])
```

#### 5. Collect Only When Ready
```python
df = df_lazy.collect()
```

---

## Fase 3: Integration Points

### Update: `app/Services/Import/ExcelQueuedImportService.php`
- Add config flag for lazy evaluation: `"use_lazy_polars": true`
- Invoke lazy processor instead of eager when flag set

### Update: `scripts/ssa_pinjaman_polars_processor.py`
- Add `--use-lazy` CLI flag
- Route to lazy implementation when enabled

---

## Fase 4: Performance Testing

### Test Scenarios
1. 100K rows - Verify correctness
2. 1M rows - Measure speed improvement
3. 5M rows - Measure memory usage
4. Compare: Eager vs. Lazy vs. Database LOAD DATA

### Metrics
- Execution time
- Memory peak usage
- Rows processed/second
- I/O operations

---

## Fase 5: Deployment

### Files to Create
- [x] `scripts/ssa_pinjaman_lazy_processor.py` - New lazy module
- [x] `POLARS_LAZY_TESTING_RESULTS.md` - Performance results

### Files to Update
- [ ] `app/Services/Import/ExcelQueuedImportService.php` - Add lazy flag
- [ ] `scripts/ssa_pinjaman_polars_processor.py` - Add --use-lazy option

### Backward Compatibility
- Default: Keep eager mode (safe)
- Optional: Enable lazy via config
- No changes to PHP logic or report structure

