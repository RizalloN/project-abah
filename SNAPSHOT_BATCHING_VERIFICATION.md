# Snapshot Batching - Verification Checklist

Complete this checklist to ensure the batching system is properly installed and working.

## Pre-Deployment Verification

### 1. Code Syntax Check
```bash
# Verify all PHP files have correct syntax
php -l app/Support/SnapshotBatchAggregator.php
php -l app/Jobs/ExecuteBatchedSnapshotJob.php
php -l app/Services/Import/ImportCleanupService.php
php -l app/Console/Commands/FlushDueSnapshotBatches.php
```

**Expected**: No syntax errors in any file
- [ ] SnapshotBatchAggregator.php - ✓
- [ ] ExecuteBatchedSnapshotJob.php - ✓
- [ ] ImportCleanupService.php - ✓
- [ ] FlushDueSnapshotBatches.php - ✓

### 2. File Existence Check
```bash
# All required files should exist
ls -la app/Support/SnapshotBatchAggregator.php
ls -la app/Jobs/ExecuteBatchedSnapshotJob.php
ls -la app/Console/Commands/FlushDueSnapshotBatches.php
ls -la app/Console/Commands/ScheduleSnapshotBatchFlush.php
```

**Expected**: All files exist
- [ ] app/Support/SnapshotBatchAggregator.php
- [ ] app/Jobs/ExecuteBatchedSnapshotJob.php
- [ ] app/Console/Commands/FlushDueSnapshotBatches.php
- [ ] app/Console/Commands/ScheduleSnapshotBatchFlush.php

### 3. Import Check
```bash
php artisan tinker
>>> use App\Support\SnapshotBatchAggregator;
>>> use App\Jobs\ExecuteBatchedSnapshotJob;
>>> exit
```

**Expected**: No errors, classes load successfully
- [ ] SnapshotBatchAggregator imports
- [ ] ExecuteBatchedSnapshotJob imports

### 4. Configuration Check
```bash
# Verify batching is enabled
php artisan tinker
>>> app(App\Services\Import\ImportCleanupService::class)
# (should show USE_BATCHING = true in reflection)
>>> exit
```

**Expected**: Batching is enabled (USE_BATCHING = true)
- [ ] USE_BATCHING = true confirmed

## Runtime Verification

### 5. Cache System Check
```bash
php artisan cache:test
```

**Expected**: Output shows cache is working
```
Cache working successfully!
```

- [ ] Cache driver functional
- [ ] Check .env: CACHE_DRIVER=redis (or memcached/file)

### 6. Queue System Check
```bash
# Check queue is configured
php artisan config:show queue

# Start queue worker
php artisan queue:work --verbose
```

**Expected**: Queue worker starts successfully
- [ ] Queue driver configured
- [ ] Worker process starts without errors

### 7. Command Registration Check
```bash
php artisan list | grep snapshot
```

**Expected**: Both commands appear
```
snapshot:flush-due-batches              Flush snapshot batches that are due for processing
snapshot:setup-batch-flush-schedule      Setup scheduler entry for periodic snapshot batch flush
```

- [ ] snapshot:flush-due-batches registered
- [ ] snapshot:setup-batch-flush-schedule registered

### 8. Manual Command Test
```bash
php artisan snapshot:flush-due-batches
```

**Expected**: Command executes without error (may show "Flushed 0..." since no batches exist)
- [ ] Command runs successfully
- [ ] No error messages in output

## Functional Testing

### 9. Create Test Batch
```bash
php artisan tinker
>>> $aggregator = app(App\Support\SnapshotBatchAggregator::class);
>>> $result = $aggregator->registerSyncRequest('daily_loan_dinamis', null, 1, 'test');
>>> dd($result);
```

**Expected**: Response shows batching succeeded
```
Array with 'batched' => true, 'batch_key' => '...', 'batch_size' => 1
```

- [ ] Sync request registered successfully
- [ ] Batch key generated correctly
- [ ] Response contains expected fields

### 10. Verify Batch Persists
```bash
php artisan tinker
>>> $batch = \Illuminate\Support\Facades\Cache::get('snapshot:batch:daily_loan_dinamis:__all__');
>>> dd($batch);
```

**Expected**: Batch structure is visible in cache
```
Array with 'batch_key', 'table_name', 'request_count', 'requests', etc.
```

- [ ] Batch stored in cache correctly
- [ ] All expected fields present
- [ ] Request count accurate

### 11. Test Batch Flushing
```bash
php artisan tinker
>>> $aggregator = app(App\Support\SnapshotBatchAggregator::class);
>>> $result = $aggregator->flushBatch('daily_loan_dinamis:__all__');
>>> dd($result);
```

**Expected**: Batch flushed to job queue
```
Array with 'batched' => true, 'flushed' => true, 'request_count' => 1
```

- [ ] Batch flushed successfully
- [ ] Job dispatched to queue
- [ ] Batch removed from cache

### 12. Verify Job in Queue
```bash
# Check jobs in queue
php artisan queue:failed  # (if using failed jobs table)

# Or check jobs table directly if using database queue
php artisan tinker
>>> DB::table('jobs')->latest()->first();
```

**Expected**: ExecuteBatchedSnapshotJob appears in queue with correct payload
- [ ] Job appears in queue
- [ ] Payload contains batch requests
- [ ] Job status is queued

## Integration Testing

### 13. Full Import → Batch → Process Flow

**Test Setup**:
1. Clear queue and cache
2. Start queue worker in separate terminal
3. Simulate import completion

```bash
# Terminal 1: Start queue worker
php artisan queue:work --verbose

# Terminal 2: Run test script
php artisan tinker << 'EOF'
$cleanupService = app(App\Services\Import\ImportCleanupService::class);

// Simulate import 1 completing
$cleanupService->dispatchImportedJobSync(1, 'daily_loan_dinamis', null, 'test');

// Simulate import 2 completing (within batching window)
$cleanupService->dispatchImportedJobSync(2, 'simpanan_multipn', null, 'test');

echo "Imports dispatched. Check queue worker output...\n";
EOF
```

**Expected Behavior**:
1. Terminal 1 shows batching messages in logs
2. See "Started new snapshot batch" messages
3. See "Processing batched snapshot requests"
4. Jobs complete successfully

**Verification Points**:
- [ ] Logs show batching occurred
- [ ] Multiple imports batched into one job
- [ ] Job executes without errors
- [ ] Syncs complete successfully

### 14. Performance Baseline

Run before enabling in production:

```bash
# Test 1: Single import
time php artisan tinker << 'EOF'
$service = app(App\Services\Import\ImportCleanupService::class);
for ($i = 0; $i < 10; $i++) {
    $service->dispatchImportedJobSync($i + 1, 'daily_loan_dinamis', null, 'perf_test');
    sleep(1);
}
EOF

# Check queue depth
php artisan queue:failed | wc -l
```

**Expected**:
- [ ] 10 imports dispatched
- [ ] Batching creates 1-2 jobs instead of 10
- [ ] Process completes in reasonable time

## Production Readiness

### 15. Scheduler/Cron Setup
```bash
# Check if scheduler entry is configured
grep "snapshot:flush-due-batches" app/Console/Kernel.php
```

**Expected**: Entry exists in Kernel.php
```php
$schedule->command('snapshot:flush-due-batches')->everyThreeMinutes();
```

**If using crontab instead**:
```bash
crontab -l | grep "snapshot:flush-due-batches"
```

- [ ] Scheduler or cron entry added
- [ ] Runs every 2-5 minutes
- [ ] Output redirected to /dev/null or log file

### 16. Logging Configuration
```bash
# Verify logs are being written
tail -f storage/logs/laravel.log | grep snapshot
```

**Expected**: Log messages appear when snapshots batch/process
- [ ] Logs are being written
- [ ] Log file location accessible
- [ ] Log rotation configured

### 17. Monitoring Setup
```bash
# Optional: Set up metric collection
# Check if any APM tools are configured
grep -r "sentry\|newrelic\|datadog" config/
```

**Expected**: (Optional) APM tools configured
- [ ] Error tracking configured (optional)
- [ ] Performance monitoring active (optional)

### 18. Documentation Review
```bash
# Verify all documentation files exist and are readable
ls -la SNAPSHOT_BATCHING_*.md IMPLEMENTATION_SUMMARY.md
```

**Expected**: All docs exist and are readable
- [ ] SNAPSHOT_BATCHING_QUICK_START.md
- [ ] SNAPSHOT_BATCHING_OPTIMIZATION.md
- [ ] IMPLEMENTATION_SUMMARY.md
- [ ] SNAPSHOT_BATCHING_VERIFICATION.md (this file)

- [ ] Documentation reviewed
- [ ] Team has access to docs
- [ ] Setup instructions understood

## Rollback Verification

### 19. Fallback Test
```bash
# Edit ImportCleanupService to disable batching temporarily
# Change: private const USE_BATCHING = true;
#     to: private const USE_BATCHING = false;

php artisan config:clear
php artisan cache:clear

# Run integration test again
# System should use traditional SyncImportedReportJob dispatch
```

**Expected**: System works without batching
- [ ] USE_BATCHING toggle works
- [ ] Traditional dispatch functions when disabled
- [ ] No errors on toggle

### 20. Emergency Recovery Test
```bash
# Test cache clearing doesn't break system
php artisan cache:clear

# Verify batching still works
php artisan snapshot:flush-due-batches
```

**Expected**: No errors, graceful recovery
- [ ] Cache clear doesn't cause errors
- [ ] System recovers and continues working
- [ ] New batches can be created

## Final Approval Checklist

- [ ] All syntax checks passed
- [ ] All files exist and import correctly
- [ ] Cache system functional
- [ ] Queue system functional
- [ ] Commands registered and callable
- [ ] Batching creates cache entries correctly
- [ ] Batches flush to jobs correctly
- [ ] Full flow test completed successfully
- [ ] Performance shows improvement
- [ ] Scheduler/cron configured
- [ ] Logging working
- [ ] Documentation complete
- [ ] Fallback/rollback tested
- [ ] Emergency recovery tested

## Sign-Off

**Verified by**: ________________________  
**Date**: ________________________  
**Notes**: ________________________________________________  

## Troubleshooting During Verification

### Syntax Errors?
```bash
# Detailed error info
php -l file.php

# Check for encoding issues
file app/Support/SnapshotBatchAggregator.php
```

### Cache Not Working?
```bash
# Test cache specifically
php artisan cache:test

# Try different driver
CACHE_DRIVER=file php artisan cache:test
```

### Queue Not Processing?
```bash
# Check queue status
php artisan queue:work --once --verbose

# View failed jobs
php artisan queue:failed
php artisan queue:retry all
```

### Commands Not Appearing?
```bash
# Rebuild autoloader
composer dump-autoload

# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### Batch Not Creating?
```bash
# Check ImportCleanupService modification
grep "USE_BATCHING" app/Services/Import/ImportCleanupService.php

# Verify SnapshotBatchAggregator::class exists
php artisan tinker
>>> class_exists('App\Support\SnapshotBatchAggregator')
```

## Performance Baseline (Optional)

Record baseline metrics before going live:

```
Before Batching:
- Queue jobs per 10 imports: ______
- Average batch completion time: ______
- Peak queue depth: ______
- CPU usage during batch: ______
- Memory peak during batch: ______

After Batching (Production):
- Queue jobs per 10 imports: ______
- Average batch completion time: ______
- Peak queue depth: ______
- CPU usage during batch: ______
- Memory peak during batch: ______

Expected Improvement:
- Queue jobs: 70-80% reduction
- Completion time: 60-75% faster
- Queue depth: Significant reduction
```

## Success Criteria

All items checked = Ready for production ✅

If any item fails, see "Troubleshooting During Verification" section before going live.
