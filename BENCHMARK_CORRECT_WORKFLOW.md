# Benchmark Correct Workflow - Safe & Accurate

**Status**: Ready for Use  
**Commands Ready**: Yes (All 3 registered)  
**Recommended Action**: Use this workflow for baseline benchmarking

---

## What Changed

The benchmark system had critical safety and accuracy issues. Everything has been fixed:

**Key Changes:**
- Deleted execution mode removed (preview-only)
- Analyzer uses correct audit action names
- Column resolver updated for project tables
- Invalid CLI options removed
- Documentation now accurate

**Files Created:**
- BenchmarkDeletePerformanceCommand.php
- SimulateDeleteScenarioCommand.php
- AnalyzeDeleteAuditsCommand.php

---

## Correct Workflow (3 Steps)

### Step 1: Preview Delete Scope (Optional)

```bash
# Preview what would be deleted (NO EXECUTION)
php artisan benchmark:simulate-delete --report=1 --scenario=small --dry-run
```

Output shows:
- Table name and total rows
- Available filter columns
- Estimated rows that would be deleted
- Instructions for actual delete via UI

**Use this to:**
- Verify column detection works
- Confirm scope estimates are reasonable
- Plan your benchmark scope

---

### Step 2: Monitor Real Managed Delete (Via UI + Command)

**Terminal**: Start monitoring  
```bash
php artisan benchmark:delete-performance --report=1 --clear-audits
```

This will:
- Clear old audit data
- List report details
- Display UI instructions
- Wait for delete activity
- Auto-analyze when complete

**Output**:
```
Monitor managed delete...

WARNING: DELETE MUST BE TRIGGERED VIA WEB UI

Steps:
  1. Open http://localhost/project-abah
  2. Navigate: Report Management > Delete
  3. Select report and configure scope
  4. Click DELETE button
  5. Command will auto-analyze when complete

Watching for activity... (Ctrl+C to stop)
```

**Browser (Parallel)**: Trigger delete through UI
1. Open http://localhost/project-abah
2. Go to Report Management (Import > Report Management Delete)
3. Select report (e.g., "Daily Loan Dinamis")
4. Configure scope (e.g., delete 2024-01 period, all branches)
5. Click "Delete" button
6. Let delete progress in background

**Terminal auto-detects** delete and displays:
```
Activity detected (5 audit records)
Activity detected (12 audit records)
Activity detected (25 audit records)

Delete completed. Analyzing...

=== DELETE PERFORMANCE ANALYSIS ===

Precheck (Full-table guard) (12.5%)
  Time: 15,000ms (iterations: 1)

Delete Chunks (Batch execution) (62.3%)
  Time: 75,000ms (iterations: 60)

Cleanup (Snapshot truncation) (8.2%)
  Time: 10,000ms (iterations: 5)

Snapshot Sync (Rebuild phase) (15.0%)
  Time: 18,000ms (iterations: 1)

---
Total Time: 118000ms
```

---

### Step 3: Detailed Analysis

After delete completes, run detailed analysis:

```bash
php artisan benchmark:analyze-audits --stats --table=daily_loan_dinamis
```

This shows:
- All phases with durations and percentages
- Bottleneck priority list
- Which optimization to tackle first

**Output**:
```
=== PERFORMANCE STATISTICS ===

Action                    Count  Success  Failed  Avg (ms)  Max (ms)  Total (ms)
managed_delete_chunk      60     60       0       1250.00   3500.00   75000
cleanup_snapshot_rows     5      5        0       2000.00   2500.00   10000
snapshot_sync             1      1        0       18000.00  18000.00  18000
cache_invalidate_light... 2      2        0       500.00    1000.00   1000

=== BOTTLENECK ANALYSIS ===

RED 15.0% (18000ms) - Snapshot Sync (Rebuild phase)
  -> Decouple snapshot from delete completion

YELLOW 12.5% (15000ms) - Precheck (Full-table guard)
  -> Use capped count instead of full COUNT(*)

YELLOW 62.3% (75000ms) - Delete Chunks (Batch execution)
  -> Optimize lock efficiency / improve batch size

BLUE 8.2% (10000ms) - Cleanup (Snapshot truncation)
  -> Skip redundant verification step

Summary: Target the RED items first for maximum ROI
```

---

## Usage Examples

### Example 1: Quick Benchmark (30 minutes)

```bash
# 1. List available reports
php artisan benchmark:delete-performance --list

# 2. Preview small scenario
php artisan benchmark:simulate-delete --report=1 --scenario=small --dry-run

# 3. Start monitoring
php artisan benchmark:delete-performance --report=1

# 4. In web UI: trigger small delete
# (Instructions shown by command)

# 5. Auto-analysis when complete

# 6. Detailed analysis
php artisan benchmark:analyze-audits --stats
```

### Example 2: Comprehensive Suite (2-4 hours)

```bash
# Test all priority tables with different scenarios

for report_id in 1 2 3; do
    echo "Benchmarking Report: $report_id"
    
    # Monitor and trigger via UI for each
    php artisan benchmark:delete-performance --report=$report_id
    
    # Then manually trigger delete in web UI for:
    # - Small scenario (5k rows)
    # - Medium scenario (100k rows)  
    # - Large scenario (500k rows)
done

# Comprehensive analysis
php artisan benchmark:analyze-audits --hours=8 --stats
```

### Example 3: Analyze Existing Data

```bash
# View all audits from last 24 hours
php artisan benchmark:analyze-audits --hours=24 --stats

# Filter by specific table
php artisan benchmark:analyze-audits --table=daily_loan_dinamis --stats

# Filter by specific action
php artisan benchmark:analyze-audits --action=managed_delete_chunk --stats

# See details (not just stats)
php artisan benchmark:analyze-audits --table=daily_loan_dinamis
```

---

## What Gets Measured

### Phase 1: Precheck
- **Action**: managed_delete_shortcut_prepare
- **What**: Full-table delete guard verification
- **When**: Before delete starts
- **Measured By**: Guard execution

### Phase 2: Delete Chunks
- **Action**: managed_delete_chunk
- **What**: Per-batch deletion with chunking
- **When**: During delete execution
- **Measured By**: Each batch completion

### Phase 3: Cleanup
- **Action**: cleanup_snapshot_rows
- **What**: Partition truncate/delete for snapshots
- **When**: After source delete completes
- **Measured By**: Snapshot cleanup operation

### Phase 4: Snapshot Sync
- **Actions**: snapshot_sync, snapshot_parallel_dispatch, snapshot_rebuild_after_delete
- **What**: Rebuild snapshots for reporting
- **When**: Post-cleanup background
- **Measured By**: Snapshot rebuild jobs

### Phase 5: Cache
- **Actions**: cache_invalidate, cache_invalidate_lightweight
- **What**: Invalidate caches and update versions
- **When**: During/after sync
- **Measured By**: Cache operations

### Phase 6: Analysis
- **Action**: analyze_table
- **What**: Table ANALYZE for optimizer stats
- **When**: Post-cleanup
- **Measured By**: Database command

---

## Interpreting Results

### Good Performance
```
Delete chunks:    60% (fast batching)
Precheck:         10% (reasonable guard)
Cleanup:          5% (efficient)
Snapshot sync:    20% (parallel working well)
```

System is well-optimized. Watch for regressions.

### Bottleneck: Snapshot Sync (30%+)
```
Snapshot sync:    35% (too much time!)
```

Action: Implement optimization - Decouple snapshot from delete completion  
Impact: Users see delete "done" sooner, sync runs async

### Bottleneck: Precheck (20%+)
```
Precheck:         25% (expensive guard)
```

Action: Implement optimization - Use capped count  
Impact: Faster initial response, skip full table count

### Bottleneck: Cleanup (15%+)
```
Cleanup:          18% (redundant verification)
```

Action: Implement optimization - Skip final COUNT  
Impact: Faster cleanup phase

---

## Data Validation

To ensure results are accurate:

1. **Verify audit records exist**
   ```sql
   SELECT COUNT(*) FROM report_sync_audits 
   WHERE table_name = 'daily_loan_dinamis'
   AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR);
   ```

2. **Check action types match**
   ```sql
   SELECT DISTINCT action FROM report_sync_audits 
   WHERE table_name = 'daily_loan_dinamis'
   ORDER BY action;
   ```
   Should see: managed_delete_chunk, cleanup_snapshot_rows, cache_invalidate_lightweight, etc.

3. **Verify timing is reasonable**
   ```sql
   SELECT action, COUNT(*) as count, 
          SUM(duration_ms) as total_ms,
          AVG(duration_ms) as avg_ms
   FROM report_sync_audits 
   WHERE table_name = 'daily_loan_dinamis'
   GROUP BY action;
   ```

---

## Troubleshooting

### No audit records after delete

**Problem**: Delete completed but command didn't auto-analyze

**Solution**:
```bash
# Manually check for records
php artisan benchmark:analyze-audits --hours=2 --table=daily_loan_dinamis

# If still empty, verify delete actually ran:
# - Check UI status page
# - Check application logs (storage/logs/laravel.log)
# - Check database for source table changes
```

### Column resolver didn't find columns

**Problem**: Preview shows "NOT FOUND" for period/kanca

**Solution**:
```bash
# Check actual table columns
mysql -u root project_abah -e "DESCRIBE daily_loan_dinamis;" | grep -E "periode|period|cabang|kanca"

# If columns are different, note them for next preview
```

### Analyzer shows 0ms for phase

**Problem**: Phase duration is blank/zero

**Causes**:
- Phase didn't execute (check if delete was full-table)
- Audit records weren't created
- Wrong table name

**Solution**:
```bash
# Check raw audit data
php -r "
\$db = new PDO('mysql:host=localhost;dbname=project_abah', 'root', '');
\$audits = \$db->query('SELECT DISTINCT action FROM report_sync_audits 
                       WHERE table_name=\"daily_loan_dinamis\" LIMIT 20');
foreach (\$audits as \$row) { print_r(\$row); }
"
```

---

## Success Criteria

- Commands run without errors
- Preview mode works (--dry-run)
- UI delete triggers successfully
- Command detects delete activity
- Analyzer shows all 6+ phases
- Bottleneck percentages are non-zero
- Throughput matches data size / time

---

## Next Steps After Collecting Baseline

1. **Document baseline metrics** for each priority table
2. **Identify top bottleneck** (which phase is slowest?)
3. **Plan optimization** based on bottleneck
4. **Implement single optimization** (smallest change first)
5. **Re-benchmark** same scenario
6. **Compare metrics** (show improvement %)
7. **Iterate** to next bottleneck

---

## Commands Quick Reference

```bash
# List reports
php artisan benchmark:delete-performance --list

# Preview scenario (no execution)
php artisan benchmark:simulate-delete --report=<ID> --scenario=<small|medium|large|full> --dry-run

# Monitor delete from UI
php artisan benchmark:delete-performance --report=<ID> --clear-audits

# Analyze existing audits
php artisan benchmark:analyze-audits --stats
php artisan benchmark:analyze-audits --table=<table> --hours=24 --stats
php artisan benchmark:analyze-audits --action=managed_delete_chunk --stats
```

---

## Files Updated

- BenchmarkDeletePerformanceCommand.php - Safe UI monitoring
- SimulateDeleteScenarioCommand.php - Preview-only (execution disabled)
- AnalyzeDeleteAuditsCommand.php - Corrected action names
- BENCHMARK_CORRECT_WORKFLOW.md - This workflow guide

---

## Ready to Benchmark?

1. All syntax verified
2. All issues fixed
3. Safe workflow established
4. Documentation complete

**Start with**: `php artisan benchmark:delete-performance --list`
