# Recovery Logic Testing Guide

## Objective
Verify that the recovery logic improvements work correctly under various scenarios including normal operations, edge cases, and error conditions.

---

## Pre-Test Setup

### Requirements
- ✅ Valid MySQL/MariaDB database with project_abah database
- ✅ Valid .sql backup file in `storage/app/private/database_backups/`
- ✅ Valid .sql.gz compressed backup file (optional)
- ✅ Admin access to report management page
- ✅ Browser developer console open (F12) for logging

### Test Data Preparation
```bash
# Create test backup file
mysqldump -u root -p project_abah > storage/app/private/database_backups/test_backup.sql

# Create compressed backup
gzip -c storage/app/private/database_backups/test_backup.sql > storage/app/private/database_backups/test_backup.sql.gz
```

---

## Test Cases

### ✅ TEST 1: Basic Recovery with Valid .sql Backup

**Scenario:** User selects valid report and valid .sql backup file, then runs recovery

**Steps:**
1. Navigate to Report Management page
2. Select a report from dropdown (e.g., "Cognos Recovery")
3. Select a valid .sql backup file
4. Verify "Jalankan Recovery" button is enabled
5. Click recovery button
6. Confirm on warning dialog

**Expected Results:**
- ✅ Progress bar appears with realtime updates
- ✅ Progress increases smoothly from 0% to 100%
- ✅ Stages shown: queued → validating → extracting_backup → importing_backup → swapping_data → syncing → cleanup → completed
- ✅ Final message shows number of rows restored
- ✅ Success dialog appears
- ✅ Table data updated (verify in grid)

**Browser Console:** Should show NO errors

---

### ✅ TEST 2: Recovery with Compressed .sql.gz Backup

**Scenario:** User selects valid report and .sql.gz compressed backup

**Steps:**
1. Follow same as TEST 1 but select a .sql.gz file
2. Monitor progress closely for extraction stage

**Expected Results:**
- ✅ Recovery processes compressed file correctly
- ✅ Progress tracking works for compressed files
- ✅ Same success as TEST 1

**Browser Console:** Should show NO errors

---

### ✅ TEST 3: Path Validation - Invalid Path

**Scenario:** User attempts recovery with path traversal (security test)

**Steps:**
1. Open Browser DevTools → Network tab
2. Intercept recovery request
3. Modify backup_path parameter to: `../../etc/passwd` or `../../../some/file`
4. Send modified request

**Expected Results:**
- ✅ Server returns 422 validation error
- ✅ Error message: "Path file backup tidak valid"
- ✅ Recovery rejected before database operations

**Verification:** Check server logs for security warning

---

### ✅ TEST 4: Path Validation - Non-existent File

**Scenario:** User provides path to file that doesn't exist

**Steps:**
1. Use DevTools to modify backup_path to non-existent file: `test_nonexistent_backup.sql`
2. Send request

**Expected Results:**
- ✅ Server returns error before processing
- ✅ Error message: "File backup tidak ditemukan"
- ✅ No staging table created

---

### ✅ TEST 5: Invalid Report Selection

**Scenario:** User provides invalid report ID

**Steps:**
1. DevTools → modify id_report to: 99999 (non-existent)
2. Send request

**Expected Results:**
- ✅ Server returns error: "Report tidak ditemukan"
- ✅ Recovery process never starts

---

### ✅ TEST 6: Empty Backup File

**Scenario:** Backup file exists but contains no table data

**Steps:**
1. Create empty backup file: `touch storage/app/private/database_backups/empty.sql`
2. Select report and empty backup
3. Run recovery

**Expected Results:**
- ✅ Progress starts normally
- ✅ At extraction stage, error occurs
- ✅ Error message: "Tabel [table_name] tidak ditemukan di file backup"
- ✅ Recovery fails gracefully
- ✅ No temporary tables left behind

---

### ✅ TEST 7: Network Timeout During Polling

**Scenario:** Network connection drops during recovery status polling

**Steps:**
1. Start recovery
2. After ~10 seconds, disconnect network (unplug ethernet or disable WiFi)
3. Wait for polling timeout
4. Reconnect network

**Expected Results:**
- ✅ After 10s request timeout, error handling triggers
- ✅ Console shows network error message
- ✅ User sees error dialog: "Network error saat polling recovery status"
- ✅ Exponential backoff prevents spam requests
- ✅ Recovery can be retried after reconnection

**Verification:** Check Network tab → requests show timeouts

---

### ✅ TEST 8: Server Error During Recovery

**Scenario:** Database error occurs during import stage

**Steps:**
1. Start recovery with valid backup
2. Before it reaches 50%, manually insert lock on target table in another session:
   ```sql
   LOCK TABLES [target_table] WRITE;
   ```
3. Watch recovery attempt

**Expected Results:**
- ✅ Recovery progresses to import stage
- ✅ Import fails with clear error message
- ✅ Temporary tables cleaned up
- ✅ Original table unchanged (atomic operation)
- ✅ Transaction rolled back properly

---

### ✅ TEST 9: Exponential Backoff Verification

**Scenario:** Verify polling uses exponential backoff, not fixed delay

**Steps:**
1. Open DevTools → Network tab
2. Filter requests by recovery status endpoint
3. Start recovery and monitor
4. Note timestamps of requests

**Expected Results:**
- ✅ First request: ~500ms after start
- ✅ Requests gradually increase in delay
- ✅ Later requests: ~5s apart (capped)
- ✅ NOT 1s fixed intervals

**Calculation:**
```
Request 1: 0.5s
Request 2: 0.7s  
Request 3: 1.0s
Request 4: 1.5s
Request 5: 2.2s
...capped at 5.0s
```

---

### ✅ TEST 10: Data Consistency Check

**Scenario:** Verify recovered data matches backup

**Steps:**
1. Before recovery: Count rows in target table
   ```sql
   SELECT COUNT(*) FROM [target_table];
   ```
2. Run recovery with backup
3. After recovery: Count rows again
   ```sql
   SELECT COUNT(*) FROM [target_table];
   ```
4. Compare with backup manifest

**Expected Results:**
- ✅ Row counts match backup data
- ✅ Spot check: Compare random records with backup
- ✅ No data corruption or truncation
- ✅ All columns populated correctly
- ✅ Foreign keys valid

---

### ✅ TEST 11: Concurrent Recovery Attempts

**Scenario:** Two recovery processes started simultaneously (edge case)

**Steps:**
1. Start recovery process A with backup file
2. Before A completes (~30s in), start recovery process B
3. Monitor both processes

**Expected Results:**
- ✅ Process B queues successfully
- ✅ Process A completes with recovery_id_A
- ✅ Process B starts after A completes
- ✅ Both finish successfully (no race conditions)

---

### ✅ TEST 12: State Recovery After Timeout

**Scenario:** Recovery state persists if browser closes during polling

**Steps:**
1. Start recovery
2. After ~15s, close browser tab (kill process)
3. Wait 60 seconds
4. Reopen page and manually check recovery status

**Expected Results:**
- ✅ Recovery continues in background (fallback mechanism)
- ✅ Status can be checked later
- ✅ Process either completes or times out after configured duration

---

### ✅ TEST 13: Large Backup File (>100MB)

**Scenario:** Recovery with large backup file

**Steps:**
1. Create/use large backup file (>100MB)
2. Run recovery
3. Monitor progress closely

**Expected Results:**
- ✅ Progress updates smoothly without freezing
- ✅ Extraction stage works with large files
- ✅ Import progresses incrementally
- ✅ Memory usage stays reasonable (no crash)
- ✅ Recovery completes successfully

**Performance Note:** Expected time: ~5-15 minutes depending on file size & server performance

---

### ✅ TEST 14: Recovery with Special Characters in Table Name

**Scenario:** Recovery for tables with backticks, spaces, or special chars

**Steps:**
1. Select report with table name like `cognos_recovery` or similar
2. Run recovery with valid backup

**Expected Results:**
- ✅ Table name properly escaped in SQL statements
- ✅ Recovery processes correctly
- ✅ No SQL errors from unescaped names

---

### ✅ TEST 15: Error Message Clarity

**Scenario:** Verify error messages are clear and actionable

**Tests:**
- Missing backup file → "File backup tidak ditemukan..."
- Invalid report → "Report tidak ditemukan..."
- Empty backup → "Tabel tidak ditemukan di file backup"
- Network error → "Network error saat polling..."
- Transaction failure → Specific database error with suggestion

**Expected Results:**
- ✅ Each error message is unique and actionable
- ✅ User knows WHAT failed and WHY
- ✅ Messages suggest next steps (e.g., "Jalankan ulang recovery")

---

## Performance Verification

### Polling Optimization Check

```javascript
// In DevTools Console during recovery
performance.mark('recovery_start');
// ... wait for recovery to complete ...
performance.mark('recovery_end');
performance.measure('recovery_time', 'recovery_start', 'recovery_end');
performance.getEntriesByName('recovery_time');
```

**Expected Results:**
- ✅ Total requests: ~30-50 (not 14400)
- ✅ Request delays: 500ms→5000ms exponential
- ✅ No spike in CPU/network usage

---

## Regression Testing

### Verify No Breaking Changes

**Tests:**
- [ ] Report list still loads
- [ ] Data grid display works
- [ ] Delete functionality works
- [ ] Rebuild snapshot works
- [ ] Deduplication works
- [ ] Other report management features work

**Expected Results:**
- ✅ All existing features work as before
- ✅ No regressions introduced
- ✅ Performance maintained or improved

---

## Success Criteria

### All Tests Must Pass ✅

1. **Functionality:** Recovery completes successfully with valid inputs
2. **Security:** Invalid paths blocked, no injection vulnerabilities
3. **Reliability:** Error handling graceful, data integrity preserved
4. **Performance:** Polling optimized, exponential backoff working
5. **UX:** Error messages clear, progress visible, user informed
6. **Data:** Recovered data matches backup, no corruption

---

## Troubleshooting

### Recovery Hangs at "Queued" Stage

**Diagnosis:**
- Check if queue system working: `php artisan queue:work imports-high`
- Check error logs: `storage/logs/laravel.log`

**Fix:**
- Start queue worker
- Check fallback mechanism in fallback_stale_seconds

### Progress Not Updating

**Diagnosis:**
- Check recovery status endpoint returns valid JSON
- Network tab → verify requests to recovery status URL

**Fix:**
- Manually refresh page
- Check server logs for errors
- Restart queue worker

### Data Mismatch After Recovery

**Diagnosis:**
- Count rows: `SELECT COUNT(*) FROM [table]`
- Check log for extraction errors
- Compare backup file integrity

**Fix:**
- Restore from fresh backup
- Check if backup file corrupted
- Verify SQL extraction patterns

---

## Test Report Template

```
DATE: [YYYY-MM-DD]
TESTER: [Name]
ENVIRONMENT: [Dev/Staging/Production]

TEST RESULTS:
- TEST 1: PASS / FAIL
- TEST 2: PASS / FAIL
- ...
- TEST 15: PASS / FAIL

TOTAL: X/15 PASSED
REGRESSIONS: [List any]
ISSUES FOUND: [List any]
COMMENTS: [Any notes]

SIGN-OFF: _________________ DATE: __________
```

---

## Deployment Checklist

Before deploying to production:

- [ ] All 15 tests passed
- [ ] No regressions detected
- [ ] Performance validated (polling optimized)
- [ ] Security review completed (path validation)
- [ ] Error messages verified as helpful
- [ ] Backup files verified as recoverable
- [ ] Database transaction safety confirmed
- [ ] Data consistency checks working
- [ ] Logging enabled for debugging
- [ ] Rollback plan documented

---

**Status:** Ready for Testing  
**Estimated Duration:** 2-4 hours for full test suite  
**Priority:** HIGH - Data recovery is critical function  
