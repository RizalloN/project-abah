# Implementation Plan: Import Reliability & Deduplication System
**Created:** 2026-04-28  
**Project:** project-ABAH (Laravel 12 Financial Dashboard)  
**Objective:** Resolve import race conditions, duplicate prevention, and robust termination

---

## Executive Summary

This plan addresses three critical issues in the Simpanan MultiPN CSV import pipeline:

1. **Race Condition on Terminate** - LOAD DATA INFILE is blocking and continues even after termination request
2. **Duplicate File Import** - Same file can be imported multiple times without warning
3. **Append Mode Without Safeguards** - No per-file uniqueness check for multi-file periods

**Solution Strategy:**
- Content-based deduplication using filename + size + first 100 rows
- MySQL thread kill implementation to force LOAD DATA termination
- Pre-import history validation to prevent reimporting

---

## Phase 1: Deduplication Logic
**Files:** ImportSimpananMultiPnCsvController.php  
**Scope:** Add content fingerprinting and history validation

### 1.1 Add calculateFileFingerprint() Method
- **Location:** ImportSimpananMultiPnCsvController.php (new private method)
- **Input:** File path (absolute), delimiter (optional)
- **Output:** SHA1 hash of filename + size + first 8192 bytes
- **Purpose:** Fast fingerprinting without full file scan
- **Implementation Details:**
  - Read filename from path
  - Get filesize() from disk
  - Open file and read 8192 bytes
  - Compute: sha1(basename + filesize + sample)

### 1.2 Add validateFileUniqueness() Method
- **Location:** ImportSimpananMultiPnCsvController.php (new private method)
- **Input:** File path, period_hints array
- **Output:** Boolean (throws exception if duplicate found)
- **Purpose:** Check if file hash already imported in same period
- **Database Query:**
  - Join `import_jobs` WHERE status='completed'
  - WHERE `job_context` contains the content_hash
  - WHERE `id_report` = 9 (Simpanan MultiPN)
  - Check if period_hints match

### 1.3 Integrate into processImportStream()
- **Location:** ImportSimpananMultiPnCsvController.php, line ~236-450
- **Insert After:** Line 305 (after eligibility check)
- **Insert Before:** Line 407 (before assertTransactionalTable)
- **Call:** `$contentHash = $this->calculateFileFingerprint($absolutePath, $params['delimiter']);`
- **Call:** `$this->validateFileUniqueness($absolutePath, $periodHints);` (gets computed later)
- **Timing:** Must happen BEFORE LOAD DATA execution
- **Error Handling:** Throw RuntimeException with message: "File sudah pernah di-import untuk periode ini."

### 1.4 Store Content Hash in job_context
- **Location:** buildDirectCsvLoadPlan(), line ~1114-1138
- **Update:** Add to $plan array: `'content_hash' => $contentHash`
- **Storage:** Will be saved via job_context by ImportProgressService
- **JSON Format:** `{"content_hash": "sha1hash...", ...other_context}`

---

## Phase 2: Thread Tracking & Connection ID Capture
**Files:** ImportSimpananMultiPnCsvController.php, ImportProgressService.php  
**Scope:** Track MySQL CONNECTION_ID for proper termination

### 2.1 Capture CONNECTION_ID Before LOAD DATA
- **Location:** executeDirectCsvLoad(), line ~1245-1262
- **Implementation:**
  ```
  $connectionId = $pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
  ```
- **Timing:** Execute AFTER `$pdo->beginTransaction()` but BEFORE `$pdo->exec($sql)`
- **Store:** Add to job_context metadata via updateImportJobStatusSafely()
- **Format:** `{"mysql_thread_id": 12345, "content_hash": "...", ...}`

### 2.2 Create updateJobMetadataWithThreadId() Helper
- **Location:** ImportProgressService.php (new public method)
- **Input:** jobId, threadId
- **Action:** Update `job_context` JSON while preserving existing keys
- **Implementation:** Use JSON_SET or json_encode merge approach
- **Safety:** Don't overwrite existing job_context, append thread_id only

### 2.3 Expose Thread ID for Termination Handler
- **Location:** ImportJobManagementController.php
- **Access Pattern:** Read job_context from import_jobs WHERE id = jobId
- **Parse:** json_decode(job_context, true) to get mysql_thread_id

---

## Phase 3: Robust Termination Handler
**Files:** ImportJobManagementController.php  
**Scope:** Implement KILL CONNECTION and verify data rollback

### 3.1 Enhance terminate() Method
- **Location:** ImportJobManagementController.php, line 267-300
- **Current Behavior:** Only calls requestTermination() + markTerminated()
- **New Behavior:**
  1. Get job record from database
  2. Extract job_context from job record
  3. Parse JSON to get mysql_thread_id
  4. If thread_id exists and is valid (> 0):
     - Create new PDO connection
     - Execute: `KILL CONNECTION {$threadId}`
     - Log result
  5. Call existing requestTermination() + markTerminated()
  6. Return success/warning response to user

### 3.2 Implement killMySqlConnection() Helper
- **Location:** ImportJobManagementController.php (new private method)
- **Input:** threadId (int), jobId (int)
- **Output:** Boolean (success/failure)
- **Implementation:**
  - Try to create separate PDO connection
  - Execute KILL CONNECTION statement
  - Log: "KILL CONNECTION {$threadId} untuk job {$jobId}"
  - Handle exceptions gracefully (don't fail terminate if KILL fails)
  - Return success if KILL executed without exception

### 3.3 Add Error Handling
- **Scenario 1:** Thread already dead (connection lost) - Log as warning, continue
- **Scenario 2:** No thread_id in job_context - Skip KILL, mark terminated normally
- **Scenario 3:** KILL fails due to permissions - Log and continue (job still terminated at DB level)
- **Scenario 4:** Job already in terminal status - Skip KILL, just update status

---

## Phase 4: Database Optimization (CRITICAL FOR PERFORMANCE)
**Files:** Database migrations + verification scripts  
**Scope:** Virtual generated column + index for O(log N) deduplication

### 4.1 Virtual Generated Column Strategy
- **Problem:** Original validateFileUniqueness() pulled ALL completed jobs to PHP, parsed JSON for each row (O(N) complexity)
- **Solution:** Create virtual column that auto-extracts content_hash from JSON, then index it
- **Column:** `job_content_hash` (VARCHAR 64, VIRTUAL GENERATED)
- **Formula:** `JSON_UNQUOTE(JSON_EXTRACT(job_context, '$.content_hash'))`
- **Benefits:**
  - Zero storage overhead (VIRTUAL = no disk duplication)
  - Auto-computed from source JSON (always in sync)
  - Fully indexable (B-Tree index)
  - Enables Sargable queries (queryable by index)

### 4.2 Index on Virtual Column
- **Migration File:** `2026_04_28_add_job_content_hash_virtual_column.php`
- **Index Name:** `idx_import_jobs_content_hash`
- **Type:** BTREE (single-column)
- **Query Performance:** O(N) → O(log N)
  - Before: 1M rows = 500ms+ query time (full scan)
  - After: 1M rows = < 1ms query time (index seek)

### 4.3 Refactored validateFileUniqueness()
**Old Approach (Bad):**
```php
$existingJobs = DB::table('import_jobs')->get();  // Pull ALL rows!
foreach ($existingJobs as $job) {
    $context = json_decode($job->job_context);    // Parse JSON in PHP
    if ($context['content_hash'] == $targetHash) { // Compare in loop
        // found it
    }
}
```

**New Approach (Good):**
```php
$existingJob = DB::table('import_jobs')
    ->where('job_content_hash', $contentHash)  // Uses index!
    ->first();
if ($existingJob) {
    // Parse JSON only for 1 result (not 1M)
}
```

### 4.4 Verification Steps
After running migration `php artisan migrate`:

1. **Verify Column Exists:**
   ```sql
   SHOW COLUMNS FROM import_jobs WHERE Field = 'job_content_hash';
   ```
   Expected: Column type VARCHAR(64), Extra shows GENERATED ALWAYS AS

2. **Verify Index Exists:**
   ```sql
   SHOW INDEX FROM import_jobs WHERE Key_name = 'idx_import_jobs_content_hash';
   ```
   Expected: Index appears with Seq_in_index = 1

3. **Verify Query Uses Index (CRITICAL):**
   ```sql
   EXPLAIN SELECT * FROM import_jobs
   WHERE job_content_hash = 'your_hash_here'
   AND id_report = 9;
   ```
   Expected: 
   - `key` column shows `idx_import_jobs_content_hash` (NOT NULL)
   - `type` shows `ref` or `eq_ref` (NOT `ALL`)
   - `rows` shows small number like 1-10 (NOT 1000000)

Full verification script: `database/queries/verify_job_content_hash_index.sql`

---

## Phase 5: Integration Points & Code Review
**Critical Paths:**

### 5.1 executeDirectCsvLoad() Enhancement
- Line 1207-1210: Add PDO initialization
- Line 1245-1262: Capture CONNECTION_ID
- Line 1262-1267: Store thread_id in job_context before/after LOAD DATA
- Ensure transaction is active when capturing thread_id

### 5.2 buildDirectCsvLoadPlan() Enhancement
- Line 1114-1138: Add content_hash to returned plan array
- Ensure content_hash calculated before this method is called

### 5.3 processImportStream() Enhancement
- Line 305-306: After eligibility check
- Insert calculateFileFingerprint() call
- Line 407-418: Before assertTransactionalTable
- Insert validateFileUniqueness() call with period hints

### 5.4 terminate() in ImportJobManagementController
- Line 267-300: Current implementation
- Add: Extract job record with job_context
- Add: Call killMySqlConnection() if thread_id exists
- Update response message to mention thread termination

---

## Testing Strategy

### Test Scenario 1: Duplicate File Detection
**Test Case:** Import File A → Wait for completion → Import File A again  
**Expected:** Second import rejected with "File sudah pernah di-import" message  
**Verification:** Check console/log, browser error response, import_jobs status

### Test Scenario 2: Multi-File Append Mode
**Test Case:** Import File A → Import File B → Import File C (different files, same period)  
**Expected:** All 3 files import successfully, data appended to same period  
**Verification:** Count rows in simpanan_multipn for period, verify all files present

### Test Scenario 3: Content Hash Calculation Performance
**Test Case:** Import 100MB+ file  
**Expected:** Hash calculation < 100ms (8KB sample read overhead minimal)  
**Verification:** Log timestamps, measure end-to-end time vs baseline

### Test Scenario 4: KILL CONNECTION On Terminate
**Test Case:** 
1. Start large import (680k+ rows, will take minutes)
2. Wait 10-15 seconds into import
3. Click "Terminate" button
4. Check MySQL SHOW PROCESSLIST during termination
5. Verify: Thread disappears within 1-2 seconds
6. Verify: simpanan_multipn row count does NOT increase after terminate
7. Verify: InnoDB transaction auto-rollback (check updated_at timestamp)

**Expected Results:**
- KILL CONNECTION executes successfully
- MySQL thread disappears from PROCESSLIST
- Data remains consistent (no partial rows written)
- Job status = 'terminated' in database
- UI shows "Import dihentikan oleh pengguna"

### Test Scenario 5: Edge Cases
**5a - Reupload Same File After Accidental Deletion**
- Delete completed job record from import_jobs
- Try reimporting same file
- Expected: Still rejected (content_hash check in completed jobs should still find it)

**5b - Modified File with Same Name**
- Modify File A (add/remove rows)
- Reimport as "File A"
- Expected: Accepted (different hash due to different size/content)

**5c - Terminate During LOAD DATA (Long-Running)**
- Import 1M+ row file
- Terminate during LOAD DATA execution
- Expected: Connection killed, transaction rolled back, no partial data

---

## Success Criteria

| Criterion | Status | Validation |
|-----------|--------|-----------|
| Content-based deduplication works | PENDING | Test Scenario 1 passes |
| Multi-file append mode functional | PENDING | Test Scenario 2 passes |
| Hash calculation performance acceptable | PENDING | Test Scenario 3: < 100ms |
| Thread kill forces termination | PENDING | Test Scenario 4: All checks pass |
| Edge cases handled correctly | PENDING | Test Scenario 5: All cases pass |
| No regressions in normal import | PENDING | Full regression suite passes |
| Database consistency maintained | PENDING | No data loss/corruption observed |
| Virtual column created successfully | PENDING | SHOW COLUMNS confirms job_content_hash |
| Index on virtual column works | PENDING | SHOW INDEX confirms idx_import_jobs_content_hash |
| Query uses index (SARGABLE) | PENDING | EXPLAIN shows key = idx_import_jobs_content_hash |
| Query performance improved | PENDING | EXPLAIN shows rows < 100 (not full table scan) |

---

## Expected Performance Gains

### Before Optimization
- **Complexity:** O(N) - Linear scan of all completed jobs
- **Dataset:** 100K completed jobs
- **Average Query Time:** 500-2000ms (slow!)
- **Memory Usage:** High (loads all rows + JSON parsing)
- **Bottleneck:** PHP loop + JSON parsing for every check

### After Optimization
- **Complexity:** O(log N) - Index seek + single result
- **Dataset:** 100K completed jobs (same)
- **Average Query Time:** 1-5ms (40-200x faster!)
- **Memory Usage:** Minimal (only matching row loaded)
- **Bottleneck:** Eliminated (direct index lookup)

### Real-World Impact
For a busy system with 10 imports/hour:
- **Old:** 10 checks × 500ms = 5 seconds/hour added overhead
- **New:** 10 checks × 2ms = 20ms/hour added overhead
- **Savings:** ~250x faster duplicate checks

For periodic batch imports (1000 jobs in parallel):
- **Old:** 1000 × 500ms = 500 seconds (8+ minutes!)
- **New:** 1000 × 2ms = 2 seconds
- **Savings:** Batch processing goes from 8+ minutes to 2 seconds

---

## Rollback Plan

If implementation introduces issues:

1. **Revert validateFileUniqueness() Check**
   - Comment out calls in processImportStream()
   - Deduplication disabled but codebase not broken
   - Data integrity not affected (append mode still works)

2. **Revert KILL CONNECTION Feature**
   - Remove killMySqlConnection() calls from terminate()
   - Termination reverts to DB status update only
   - Normal (non-force) termination behavior restored

3. **Database Rollback**
   - No migrations required, no schema changes
   - job_context column already exists
   - No data cleanup needed

---

## Timeline & Deliverables

| Phase | Deliverable | Est. Duration | Status |
|-------|-------------|---|--------|
| 1 | Deduplication methods + integration | 2 hours | PENDING |
| 2 | Thread tracking + helper methods | 1 hour | PENDING |
| 3 | Robust termination + killMySqlConnection() | 1.5 hours | PENDING |
| 4 | Testing scenarios execution | 2 hours | PENDING |
| 5 | Code review + documentation | 1 hour | PENDING |
| **TOTAL** | **End-to-end implementation** | **~7.5 hours** | **PENDING** |

---

## Implementation Order

1. Start Phase 1 (deduplication logic)
2. Add Phase 2 (thread tracking) in parallel
3. Complete Phase 3 (termination handler)
4. Execute all 5 test scenarios
5. Document lessons learned & update codebase comments

---

**Next Step:** Await confirmation to proceed with Phase 1 implementation. Ready to start with calculateFileFingerprint() and validateFileUniqueness() methods.
