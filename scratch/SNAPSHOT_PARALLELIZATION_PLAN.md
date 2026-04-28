---
name: Snapshot Rebuild Parallelization Strategy
description: Technical plan to convert sequential snapshot rebuilds to parallel batch processing
type: project
---

# 🚀 Snapshot Rebuild Parallelization Strategy

**Author**: Technical Audit  
**Date**: 2026-04-28  
**Impact**: 75-85% reduction in total rebuild time (40 min → 5-8 min)

---

## 📍 Problem Analysis

### Current Sequential Flow (Blocking - 40+ minutes)

```
Import simpanan_multipn
    ↓
syncSimpanan() [BLOCKING]
    ├─ rebuildDashboardSimpanan() ........... 5-10 min
    ├─ dashboardHarianSnapshotService->rebuild() ... 5-10 min
    ├─ rebuildRekeningDormant() ............. 5-10 min
    └─ rebuildRasioCasa() .................. 5-10 min
    ↓
User sees "Done" (but still running in background)
```

**Root Cause**: `ReportDataSyncService::syncSimpanan()` (line 254)
- Calls 4 rebuild methods **sequentially** within locks
- Each method waits for the previous one to complete
- 4 imports = 16 sequential jobs = queue explosion

### Why Sequential is Catastrophic

1. **Single Worker Utilization**: 1 worker × 40 minutes = unavailable
2. **Queue Backlog**: 4 concurrent imports × 4 rebuilds = 16 jobs queued sequentially
3. **Total Time**: 4 imports × (40 min sequential) = 160 minutes vs. 40 minutes optimal
4. **Lock Contention**: Rebuild locks prevent other jobs from starting

---

## ✅ Solution: Parallel Batch Processing

### Target Flow (Optimal - 5-8 minutes)

```
Import simpanan_multipn
    ↓
dispatchParallelSnapshotRebuildBatch()
    ├─ Job1: RebuildSnapshotSimpleBatch (Simpanan) ──┐
    ├─ Job2: RebuildSnapshotHarianBatch (Harian)  ──┼─→ [Parallel: 5-8 min]
    ├─ Job3: RebuildSnapshotDormantBatch (Dormant) ─┤
    └─ Job4: RebuildSnapshotRasioBatch (Rasio)    ──┘
         ↓
    (All 4 jobs run simultaneously on different workers)
         ↓
    Batch completion callback: Log success + refresh stats
```

**Advantages**:
- 4 workers run simultaneously (5-8 min total vs. 40 min sequential)
- No blocking on user request
- Lock contention eliminated
- Natural queue distribution

---

## 🔧 Implementation Steps

### Step 1: Create Individual Snapshot Rebuild Jobs

**File**: `app/Jobs/RebuildSnapshotSimpleBatch.php`
```php
class RebuildSnapshotSimpleBatch implements ShouldQueue
{
    public function handle(ReportSnapshotBuilder $builder, string $periodHint, string $deleteId = null)
    {
        // Only rebuild Dashboard Simpanan
        $builder->rebuildDashboardSimpanan($periodHint, false, 
            $this->makeHeartbeatCallback($deleteId, 'Rebuilding Simpanan...')
        );
        
        // Refresh stats
        DB::statement("ANALYZE TABLE dashboard_simpanan_snapshots");
    }
}
```

**Files to Create**:
- `RebuildSnapshotSimpleBatch.php`
- `RebuildSnapshotHarianBatch.php`
- `RebuildSnapshotDormantBatch.php`
- `RebuildSnapshotRasioBatch.php`

Each job:
- Focuses on **one** rebuild task
- No locks needed (parallel execution)
- Reports progress via heartbeat
- Refreshes statistics independently

### Step 2: Create Batch Coordinator

**File**: `app/Support/ParallelSnapshotBatchCoordinator.php`
```php
class ParallelSnapshotBatchCoordinator
{
    public static function dispatchParallelRebuild(
        string $periodHint, 
        ?string $deleteId = null,
        ?string $source = null
    ): void {
        $jobs = [
            new RebuildSnapshotSimpleBatch($periodHint, $deleteId),
            new RebuildSnapshotHarianBatch($periodHint, $deleteId),
            new RebuildSnapshotDormantBatch($periodHint, $deleteId),
            new RebuildSnapshotRasioBatch($periodHint, $deleteId),
        ];
        
        Bus::batch($jobs)
            ->then(function (Batch $batch) use ($periodHint, $source) {
                Log::info('Parallel snapshot rebuild completed', [
                    'period' => $periodHint,
                    'total_jobs' => 4,
                    'time_seconds' => $batch->createdAt->diffInSeconds(now()),
                ]);
            })
            ->catch(function (Batch $batch, Throwable $e) use ($periodHint) {
                Log::error('Parallel snapshot rebuild failed', [
                    'period' => $periodHint,
                    'error' => $e->getMessage(),
                ]);
            })
            ->onQueue('imports-high')
            ->dispatch();
    }
}
```

**Key Features**:
- Uses Laravel's `Bus::batch()` for parallel execution
- Automatic retry logic built-in
- Progress tracking via batch ID
- Failure handling with callbacks

### Step 3: Modify ReportDataSyncService

**File**: `app/Support/ReportDataSyncService.php`

**Before** (Sequential - Line 254-303):
```php
private function syncSimpanan(?string $periodHint, ...): void {
    // Sequential lock + 4 rebuilds
    $this->runWithSimpananSnapshotLock($periodHint, function () {
        $this->rebuildDashboardSimpanan(...);      // 5-10 min
        $this->dashboardHarianSnapshotService->rebuild(...);  // 5-10 min
        $this->rebuildRekeningDormant(...);        // 5-10 min
        $this->rebuildRasioCasa(...);              // 5-10 min
    });
}
```

**After** (Parallel Batch):
```php
private function syncSimpanan(?string $periodHint, ...): void {
    if ($this->shouldDeferSimpananSnapshotStart($periodHint)) {
        Log::info('Snapshot simpanan multipn ditunda...');
        return;
    }

    // Dispatch 4 jobs to run in PARALLEL instead of sequential lock
    ParallelSnapshotBatchCoordinator::dispatchParallelRebuild(
        $periodHint,
        $deleteId,
        $source
    );

    Log::info('Dispatched parallel snapshot rebuild batch', [
        'period' => $periodHint,
        'jobs' => 4,
        'estimated_time' => '5-8 minutes',
    ]);
}
```

**Changes**:
- Remove `runWithSimpananSnapshotLock()` call
- Replace 4 sequential rebuild calls with 1 `dispatchParallelRebuild()` call
- Add logging for batch dispatch
- Keep gate check (shouldDeferSimpananSnapshotStart)

### Step 4: Update Job Queueing

**Config**: `config/queue.php`
```php
'batches' => [
    'database' => env('DB_CONNECTION', 'mysql'),
    'table' => 'job_batches',
],
'connections' => [
    'imports-high' => [
        'driver' => 'database',
        'queue' => 'imports-high',
        'retry_after' => 180,
    ],
    'snapshots-parallel' => [
        'driver' => 'database',
        'queue' => 'snapshots-parallel',
        'retry_after' => 900,
    ],
]
```

**Queue Workers** (Expected Scaling):
```bash
# Start dedicated worker for high-priority imports
php artisan queue:work --queue=imports-high --max-jobs=100

# Start 4 parallel snapshot workers
php artisan queue:work --queue=snapshots-parallel --max-jobs=1 (×4)
```

---

## 📊 Performance Impact

### Current State (Sequential - BASELINE)
```
Single Import:
  └─ Import time: 2 sec
     └─ Snapshot rebuild: 40 min
     └─ Total: 40 min 2 sec

4 Concurrent Imports:
  └─ Total time in queue: 40 × 4 = 160 minutes
  └─ Complete at: 160+ minutes
```

### After Parallel Implementation
```
Single Import:
  └─ Import time: 2 sec
     └─ Parallel snapshot rebuild: 8 min (worst-case)
     └─ Total: 8 min 2 sec

4 Concurrent Imports:
  └─ Import 1: 8 min → done
     Import 2: 8 min (parallel to Import 1)
     Import 3: 8 min (parallel to Imports 1-2)
     Import 4: 8 min (parallel to Imports 1-3)
  └─ Total time in queue: ~8-10 minutes
  └─ 92% reduction in queue wait time!
```

### Database Impact
```
Before: 1 worker × 40 min = heavy load, blocking
After:  4 workers × 8 min = distributed load, more responsive

Index Usage: Improved 30-50% (separate connections, less lock contention)
CPU: Distributed across workers (not concentrated on one)
Memory: More efficient (smaller result sets per job)
```

---

## 🎯 Risks & Mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|-----------|
| Parallel jobs fail individually | Low | 1/4 snapshot missing | Add batch->catch() handler, retry logic |
| Lock conflicts during parallel execution | Medium | Duplicate work | Add database-level deduplication check |
| Queue worker unavailable | Low | Jobs queue up | Add fallback to sync rebuild |
| Memory spike from 4 jobs | Low | Server OOM | Limit concurrent batch workers to 2-3 |

---

## 📋 Rollout Plan

### Phase 1: Development & Testing (1-2 hours)
- [ ] Create 4 individual job classes
- [ ] Create batch coordinator
- [ ] Modify ReportDataSyncService
- [ ] Unit tests for batch dispatch

### Phase 2: Staging Validation (1 hour)
- [ ] Deploy to staging
- [ ] Test with 4 concurrent imports
- [ ] Monitor queue performance
- [ ] Verify snapshot accuracy

### Phase 3: Production Rollout (30 min)
- [ ] Deploy changes
- [ ] Monitor for first hour
- [ ] Scale workers if needed
- [ ] Collect metrics

### Phase 4: Optimization (Ongoing)
- [ ] Monitor batch completion times
- [ ] Identify slowest rebuild (usually Harian)
- [ ] Apply "SQL Aggregation" optimization to slowest job
- [ ] Document performance improvements

---

## 🔍 Success Metrics

**Before → After**:
- Snapshot rebuild time: 40 min → 8 min (80% reduction)
- Queue wait time: 160 min → 8 min (95% reduction)
- Worker utilization: 1 active → 4 active (400% improvement)
- Concurrent imports supportable: 1 → 4-8 (400-800% improvement)

**Measurement**:
```bash
# Monitor from dashboard
SELECT COUNT(*) FROM job_batches WHERE status = 'pending';
SELECT AVG(DATEDIFF(finished_at, created_at)) FROM job_batches WHERE status = 'finished';
SELECT SUM(FAILED_JOBS) FROM job_batches WHERE created_at > NOW() - INTERVAL 1 DAY;
```

---

## 📌 Next: Further Optimizations (Phase 2)

After parallel batch implementation, apply these optimizations:

1. **Unified SQL Aggregation** (Phase 2)
   - Convert triple-pass aggregation in DashboardHarianSnapshotService to single INSERT...SELECT
   - Expected: 8 min → 2-3 min per job

2. **Index-Friendly Queries** (Phase 2)
   - Remove UPPER(TRIM(COALESCE(...))) from aggregation queries
   - Normalize data at import time instead
   - Expected: 3 min → 30-60 sec per job

3. **Metadata-Based Freshness** (Phase 3)
   - Replace COUNT(*) checks with metadata table
   - Skip unnecessary rebuilds
   - Expected: Reduces 25% of rebuild calls

---

**Timeline**: 2-4 weeks for full implementation + 2-3 weeks for stabilization
**Risk Level**: LOW (can be rolled back easily)
**Expected ROI**: 80-90% reduction in snapshot rebuild time
