# Testing Guide: Import Reliability & Deduplication System
**Status:** Ready for testing  
**Verified:** Virtual column ✓ | Index ✓ | Query Performance ✓

---

## ✅ Pre-Testing Checklist
- [x] Migration executed successfully
- [x] Virtual column `job_content_hash` created
- [x] Index `idx_import_jobs_content_hash` created
- [x] Query performance verified (6.37ms)
- [ ] All 5 test scenarios passed

---

## 🧪 Test Scenarios

### Test 1: Duplicate File Detection
**Purpose:** Verify that the same file cannot be imported twice in the same period

**Steps:**
1. Navigate to Simpanan MultiPN import page
2. Upload File A (e.g., `simpanan_multipn_march_2026.csv`)
3. Click "Preview" → "Import"
4. Wait for import to complete → Status should show "Completed"
5. **Upload the SAME file again** (File A)
6. Click "Preview" → Try to "Import"

**Expected Result:**
- ✓ Error message appears: **"File sudah pernah di-import sebelumnya untuk periode yang sama..."**
- ✓ Import is rejected before LOAD DATA executes
- ✓ No data is written to database

**Verification:**
```sql
-- Check job_context contains content_hash
SELECT id, file_name, job_content_hash, 
       JSON_EXTRACT(job_context, '$.content_hash') AS ctx_hash
FROM import_jobs 
WHERE id_report = 9 
AND status = 'completed'
ORDER BY id DESC LIMIT 3;
```

**Pass Criteria:** Second import is rejected with clear error message

---

### Test 2: Multi-File Append Mode
**Purpose:** Verify that different files CAN be imported in the same period (append mode)

**Steps:**
1. Upload File A (e.g., `simpanan_multipn_march_2026_part1.csv`)
2. Click "Preview" → "Import"
3. Wait for completion → Status "Completed"
4. **Upload File B** (different file, SAME period, e.g., `simpanan_multipn_march_2026_part2.csv`)
5. Click "Preview" → "Import"
6. Wait for completion → Status "Completed"
7. **Upload File C** (yet another file, SAME period)
8. Click "Preview" → "Import"
9. Wait for completion → Status "Completed"

**Expected Result:**
- ✓ All 3 files import successfully
- ✓ All data is appended to same period in `simpanan_multipn` table
- ✓ No duplicate rejection errors
- ✓ Total rows = sum of rows from all 3 files

**Verification:**
```sql
-- Check all 3 jobs completed
SELECT id, file_name, status, total_success 
FROM import_jobs 
WHERE id_report = 9 
ORDER BY id DESC LIMIT 3;

-- Count total rows imported for March 2026
SELECT COUNT(*) as total_rows, 
       COUNT(DISTINCT created_at) as distinct_batches
FROM simpanan_multipn
WHERE posisi = '2026-03-31';
```

**Pass Criteria:** All 3 files imported without rejection, data appended correctly

---

### Test 3: Hash Calculation Performance
**Purpose:** Verify that fingerprint calculation doesn't slow down the import process

**Steps:**
1. Upload a large CSV file (100MB+)
2. Monitor time from file upload to "Validasi file..." message
3. Check console logs for timing information

**Expected Result:**
- ✓ Hash calculation completes in < 100ms
- ✓ No noticeable delay during import startup
- ✓ Log shows: "Simpanan MultiPN: Content fingerprint calculated"

**Verification:**
Check application logs for timing:
```
[2026-04-28 12:34:56] DEBUG: Simpanan MultiPN: Content fingerprint calculated {
  "job_id": 123,
  "content_hash": "abc123...",
  "file_size": 104857600
}
```

**Pass Criteria:** Hash calculated and logged, < 100ms delay observed

---

### Test 4: KILL CONNECTION on Terminate
**Purpose:** Verify that terminating an import mid-execution properly kills the MySQL connection

**Steps:**
1. Prepare a VERY LARGE CSV file (1M+ rows, takes 5+ minutes to import)
2. Start the import
3. Wait 10-15 seconds (let LOAD DATA get into processing)
4. Click "Terminate" button in Job Management dashboard
5. Observe the following:
   - Browser shows termination success message
   - Job status changes to "Terminated"
   - Check MySQL PROCESSLIST in parallel (in another terminal)

**Expected Result:**
- ✓ Termination completes immediately (< 2 seconds)
- ✓ Success message mentions: "MySQL connection dipaksa disconnect..."
- ✓ MySQL PROCESSLIST shows the LOAD DATA thread disappears
- ✓ Data in `simpanan_multipn` **STOPS growing** (no new rows after terminate)
- ✓ **No partial data** is persisted (InnoDB rollback)
- ✓ Job record shows status = 'terminated'

**Verification - In separate terminal during test:**
```bash
# Terminal 1: Monitor MySQL processlist
watch "mysql -uroot -p project-abah -e 'SHOW PROCESSLIST;' | grep LOAD"

# Check after terminate - thread should disappear
mysql -uroot -p project-abah -e "SHOW PROCESSLIST;" | grep -i "LOAD DATA"
# (Should return nothing after terminate)

# Check rows didn't increase after terminate
mysql -uroot -p project-abah -e "SELECT COUNT(*) FROM simpanan_multipn WHERE posisi='2026-03-31';"
# Run multiple times - count should freeze after terminate
```

**Pass Criteria:** 
- MySQL thread killed instantly
- Data stops being written
- Status becomes 'terminated'
- No data corruption/partial writes

---

### Test 5: Edge Cases
**Purpose:** Verify robustness of deduplication logic

#### 5a: Reimport After Job Deletion
**Steps:**
1. Import File A → Complete
2. Check import_jobs: Job shows status 'completed'
3. Manually delete the job record from import_jobs table
4. Try reimporting File A

**Expected:** 
- ⚠ Should accept the reimport (job record deleted = no history check)
- Note: This is acceptable behavior (admin deleted job record)

#### 5b: Modified File With Same Name
**Steps:**
1. Import `data.csv` (100 rows) → Complete
2. Modify `data.csv` (add 50 more rows)
3. Try reimporting modified `data.csv`

**Expected:**
- ✓ Import accepted (different file hash due to size change)
- ✓ New rows appended to database

#### 5c: Concurrent Imports
**Steps:**
1. Start import of File A (large, will take time)
2. Immediately start import of File B (different file, same period)
3. Both should process in parallel/sequential without conflicts

**Expected:**
- ✓ No deadlocks
- ✓ Both complete successfully
- ✓ Data from both files in database

#### 5d: Null job_context
**Steps:**
1. Find an old job with NULL job_context (from before this feature)
2. Try reimporting that file

**Expected:**
- ✓ No errors (null check should handle it)
- ✓ Reimport allowed (no history to check)

---

## 📊 Test Results Summary

Create a table to track results:

| Test | Scenario | Status | Notes | Pass |
|------|----------|--------|-------|------|
| 1 | Duplicate Detection | PENDING | File A rejected on 2nd import | [ ] |
| 2 | Multi-File Append | PENDING | Files A,B,C all import to same period | [ ] |
| 3 | Hash Performance | PENDING | < 100ms hash calculation | [ ] |
| 4 | KILL CONNECTION | PENDING | Thread killed, data rolled back | [ ] |
| 5a | Job Deletion | PENDING | Can reimport after deleting job | [ ] |
| 5b | Modified File | PENDING | Modified file with same name imports | [ ] |
| 5c | Concurrent | PENDING | Parallel imports work | [ ] |
| 5d | Null Context | PENDING | Old jobs without context work | [ ] |

---

## 🔍 Debugging Tips

### If Test 1 Fails (Duplicate Not Rejected)
**Check:**
```php
// In processImportStream(), verify validateFileUniqueness() is called
// Check logs for:
"File uniqueness validation passed"
// or
"Validasi keunikan file gagal"

// Check database - virtual column populated?
SELECT id, file_name, job_content_hash FROM import_jobs 
WHERE status='completed' AND job_content_hash IS NOT NULL LIMIT 1;
```

### If Test 4 Fails (Terminate Doesn't Work)
**Check:**
```php
// Verify mysql_thread_id is captured:
SELECT id, job_context, 
       JSON_EXTRACT(job_context, '$.mysql_thread_id') as thread_id
FROM import_jobs WHERE id = [jobId];

// Check logs for KILL attempt:
"Successfully killed MySQL connection"
or
"Failed to kill MySQL connection"
```

### If Tests Slow Down
**Check:**
- Is index being used? Run EXPLAIN query
- Are there other slow queries? Check slow log
- Check CPU/memory during import

---

## 📝 Test Execution Checklist

- [ ] Test 1: Duplicate detection works
- [ ] Test 2: Multi-file append works
- [ ] Test 3: Hash performance acceptable
- [ ] Test 4: Terminate kills connection
- [ ] Test 5a: Edge case - job deletion
- [ ] Test 5b: Edge case - modified file
- [ ] Test 5c: Edge case - concurrent
- [ ] Test 5d: Edge case - null context
- [ ] All tests passed
- [ ] No data corruption observed
- [ ] Performance is acceptable
- [ ] No regressions in normal import flow

---

## ✅ Sign-Off

When all tests pass:

```
Tested by: [Name]
Date: [Date]
Status: ✅ ALL TESTS PASSED
No regressions observed.
System ready for production.
```

---

**Need help with any test scenario? Ask questions!**
