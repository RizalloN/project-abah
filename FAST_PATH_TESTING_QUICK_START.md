# Fast Path Optimization - Quick Start Testing Guide
**Purpose**: Validate Phase 1 implementations for simpanan_multipn + daily_loan  
**Timeline**: ~2-3 hours total testing  
**Risk**: MINIMAL (all changes are code-only, no schema changes)

---

## Prerequisites

### Required Test Data
- [ ] 680k-row simpanan_multipn sample CSV file
- [ ] 100k-row daily_loan_dinamis sample CSV file
- [ ] Test database environment with writeable daily_loan_dinamis + simpanan_multipn tables

### Required Tools
```bash
php --version                    # >= 8.1
python3 --version              # >= 3.8 with Polars
mysql --version                # >= 5.7
laravel (artisan available)     # Confirmed at project root
```

### Environment
```bash
cd /path/to/project-ABAH
# Verify .env has correct database credentials
cat .env | grep DB_
```

---

## Testing Strategy

### Phase A: Unit Tests (10 minutes)

#### Test 1: Daily Loan Column Classification
```bash
cd /path/to/project-ABAH

python3 << 'EOF'
from scripts.daily_loan_polars_processor import classify_daily_loan_columns

# Test: Check classification logic
test_headers = [
    'PERIODE', 'RATE', 'PLAFON', 'BAKI_DEBET1',  # Mixed
    'TGL_REALISASI', 'CIFNO', 'BRANCH1',          # Mixed
    'UMUR_TUNGGAKAN', 'AO_NAME', 'NOMOR_REKENING1'  # Mixed
]

result = classify_daily_loan_columns(test_headers)

print("Classification Result:")
print(f"  Decimal columns: {len(result['decimal'])} detected")
print(f"  Date columns: {len(result['date'])} detected")
print(f"  Integer columns: {len(result['integer'])} detected")
print(f"  String columns: {len(result['string'])} detected")

# Assertions
assert len(result['decimal']) > 0, "Should detect decimal columns"
assert len(result['date']) > 0, "Should detect date columns"
assert len(result['integer']) > 0, "Should detect integer columns"
assert len(result['string']) > 0, "Should detect string columns"

print("✓ Column classification working correctly")
EOF
```

#### Test 2: Decimal Normalization
```bash
python3 << 'EOF'
from scripts.daily_loan_polars_processor import normalize_decimal_optimized_daily_loan

test_cases = [
    ("1500000.50", "1500000.50"),
    ("(2000.00)", "-2000.00"),
    ("3000", "3000.00"),
    ("", ""),
    ("  ", ""),
]

print("Testing decimal normalization...")
for input_val, expected in test_cases:
    result = normalize_decimal_optimized_daily_loan(input_val)
    status = "✓" if result == expected else "✗"
    print(f"  {status} Input: '{input_val}' → '{result}' (expected: '{expected}')")
    assert result == expected, f"Mismatch for '{input_val}'"

print("✓ All decimal tests passed")
EOF
```

---

### Phase B: Integration Tests (2-3 hours)

#### Setup
```bash
# 1. Backup current data (IMPORTANT!)
mysql -u root -proot project_abah -e "CREATE TABLE simpanan_multipn_backup LIKE simpanan_multipn;"
mysql -u root -proot project_abah -e "INSERT INTO simpanan_multipn_backup SELECT * FROM simpanan_multipn LIMIT 100000;"
echo "✓ Backup created: simpanan_multipn_backup"

# 2. Backup daily_loan data
mysql -u root -proot project_abah -e "CREATE TABLE daily_loan_dinamis_backup LIKE daily_loan_dinamis;"
mysql -u root -proot project_abah -e "INSERT INTO daily_loan_dinamis_backup SELECT * FROM daily_loan_dinamis LIMIT 50000;"
echo "✓ Backup created: daily_loan_dinamis_backup"

# 3. Verify test files exist
ls -lh storage/test/simpanan_multipn_680k.csv 2>/dev/null || echo "⚠️  Need 680k-row simpanan_multipn test file"
ls -lh storage/test/daily_loan_100k.csv 2>/dev/null || echo "⚠️  Need 100k-row daily_loan test file"
```

#### Test 3: Simpanan MultiPN Import (680k rows)
```bash
php artisan tinker

# Import with timing
$start = microtime(true);
$result = Artisan::call('import:simpanan-multipn-backend', [
    'source_path' => 'storage/test/simpanan_multipn_680k.csv',
    'mode' => 'sync',  # Don't queue, run directly
]);
$duration = round(microtime(true) - $start, 2);

echo "Import completed in: {$duration} seconds (" . round($duration / 60, 2) . " minutes)\n";
echo "Expected: < 180 seconds (3 minutes) [Phase 1 optimized]";
```

#### Data Integrity Check (Simpanan MultiPN)
```sql
-- Run after import completes
SELECT 
  COUNT(*) as total_rows,
  COUNT(DISTINCT posisi) as unique_dates,
  COUNT(DISTINCT kantor_cabang) as unique_branches,
  MIN(saldo_idr) as min_balance,
  MAX(saldo_idr) as max_balance,
  AVG(CAST(saldo_idr AS DECIMAL(20,2))) as avg_balance
FROM simpanan_multipn
WHERE created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE);

-- Expected results:
-- total_rows: ~680,000
-- unique_dates: varies
-- min_balance: should be negative or zero
-- max_balance: should be large number
-- avg_balance: reasonable number
```

#### Test 4: Daily Loan Import (100k rows)
```bash
php artisan tinker

# Import with timing
$start = microtime(true);
$result = Artisan::call('import:daily-loan-backend', [
    'source_path' => 'storage/test/daily_loan_100k.csv',
    'mode' => 'sync',  # Don't queue
]);
$duration = round(microtime(true) - $start, 2);

echo "Import completed in: {$duration} seconds (" . round($duration / 60, 2) . " minutes)\n";
echo "Expected: < 1800 seconds (30 minutes) [Phase 1 optimized: ~20 minutes target]";
```

#### Data Integrity Check (Daily Loan)
```sql
-- Run after import completes
SELECT 
  COUNT(*) as total_rows,
  COUNT(DISTINCT periode) as unique_periods,
  COUNT(DISTINCT cabang1) as unique_branches,
  COUNT(DISTINCT status_rekening1) as unique_statuses,
  MIN(CAST(os_idr AS DECIMAL(20,2))) as min_os,
  MAX(CAST(os_idr AS DECIMAL(20,2))) as max_os,
  AVG(CAST(os_idr AS DECIMAL(20,2))) as avg_os
FROM daily_loan_dinamis
WHERE created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE);

-- Expected results:
-- total_rows: ~100,000
-- unique_periods: 1-5 (depends on test file)
-- unique_branches: 10-50+
-- os_idr values: check for NULL/zero/negative indicators
```

---

### Phase C: Performance Validation (30 minutes)

#### Metric 1: Polars Stage Duration
```bash
# Monitor logs for phase duration
tail -f storage/logs/laravel.log | grep -E "Membaca|Sanitasi|Menulis|done"

# Expected output:
# "Membaca dan menyiapkan CSV Daily Loan dengan Polars..." (5% progress)
# "Sanitasi selesai..." (56% progress) [~time for Polars to load + normalize]
# "Menulis CSV bersih..." (86% progress) [write phase]
# done event with timing

# Time from 56% to 86% should be < 5 minutes (ideally ~4.5 minutes)
```

#### Metric 2: LOAD DATA Duration
```bash
# Monitor slow query log
mysql -u root -proot project_abah -e "
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 1;
-- Run import, then check:
SELECT query_time, sql_text FROM mysql.slow_log WHERE db='project_abah' ORDER BY query_time DESC LIMIT 5;
"

# Expected LOAD DATA duration:
# simpanan_multipn: < 25 minutes (optimized with SET unique_checks=0)
# daily_loan_dinamis: < 20 minutes (with Phase 1 vectorization)
```

#### Metric 3: Memory Usage
```bash
# Monitor during import
watch -n 2 'ps aux | grep -E "python3|php|mysql" | grep -v grep | awk "{print \$6}" | paste -sd+ | bc'

# Expected:
# Memory usage stable < 2GB
# No OOM errors in syslog
```

---

## Success Criteria

### Minimal Success (All tests pass)
```
✓ Unit tests: Decimal parsing correct
✓ Integration test: 680k-row import completes without error
✓ Integration test: 100k-row import completes without error
✓ Data integrity: Row counts + checksums match
✓ No Python errors in logs
✓ No MySQL timeout errors
```

### Good Success (Above + performance improvement)
```
✓ Polars duration: < 5 minutes (10% improvement visible)
✓ LOAD DATA duration: < 20 minutes for daily_loan (10-15% improvement)
✓ Polars + LOAD DATA combined: < 30 minutes for daily_loan (15-20% improvement visible)
```

### Excellent Success (Significant improvement)
```
✓ Daily Loan: 20 minutes total (30% improvement over baseline)
✓ Simpanan MultiPN: 2.5-3 hours total (50% improvement over baseline)
✓ No regressions in Dashboard queries
✓ No data corruption observed
```

---

## Rollback Procedure

If tests fail or issues discovered:

```bash
# 1. Stop current import (Ctrl+C if in tinker)

# 2. Restore database from backup
mysql -u root -proot project_abah -e "
DELETE FROM simpanan_multipn WHERE created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE);
INSERT INTO simpanan_multipn SELECT * FROM simpanan_multipn_backup;
"

# 3. Revert code changes
git checkout -- scripts/daily_loan_polars_processor.py
git checkout -- app/Http/Controllers/Import/ImportExcelController.php

# 4. Verify revert
git status

# 5. Cleanup
mysql -u root -proot project_abah -e "DROP TABLE simpanan_multipn_backup;"
mysql -u root -proot project_abah -e "DROP TABLE daily_loan_dinamis_backup;"
```

---

## Common Issues & Troubleshooting

### Issue 1: Python Syntax Error
```
Error: ModuleNotFoundError: No module named 'polars'
Solution:
  pip3 install polars
  python3 -c "import polars; print(polars.__version__)"
```

### Issue 2: Classification Returns Empty
```
Error: result['decimal'] = []
Possible Causes:
  - Column names case mismatch (must be UPPERCASE)
  - Headers parameter incorrect
Solution:
  - Verify all header names are uppercase
  - Check test_headers list in unit test
```

### Issue 3: LOAD DATA Timeout
```
Error: MySQL server has gone away
Possible Causes:
  - net_read_timeout too low
  - wait_timeout too low
Solution:
  - Increase timeout settings:
    SET SESSION net_read_timeout = 3600;
    SET SESSION wait_timeout = 7200;
```

### Issue 4: Memory Spike
```
Error: Process killed (OOM)
Solution:
  - Reduce test file size (try 100k rows instead of 680k)
  - Check available disk space for temp CSV
  - Monitor MySQL innodb_buffer_pool_size
```

---

## Testing Checklist

### Before Testing
- [ ] Backups created (simpanan_multipn + daily_loan)
- [ ] Test files available (680k + 100k row CSVs)
- [ ] .env database credentials verified
- [ ] Disk space > 10GB available
- [ ] MySQL running, accessible

### Unit Tests
- [ ] Classification test passed
- [ ] Decimal normalization tests passed
- [ ] No Python errors

### Integration Tests
- [ ] Simpanan MultiPN 680k-row import completed
- [ ] Daily Loan 100k-row import completed
- [ ] No MySQL timeout errors
- [ ] No data corruption

### Performance Validation
- [ ] Polars stage duration recorded
- [ ] LOAD DATA duration recorded
- [ ] Memory usage reasonable (< 2GB)
- [ ] Duration matches expected targets

### Regression Checks
- [ ] Dashboard Daily Loan queries work
- [ ] Dashboard Simpanan MultiPN queries work
- [ ] Snapshot generation completes
- [ ] Export functionality works

### Cleanup
- [ ] Backups dropped
- [ ] Temporary files cleaned up
- [ ] Slow query log disabled (if enabled for testing)

---

## Results Template

After testing, fill out:

```
# Fast Path Testing Results

**Date**: [Date tested]
**Tester**: [Your name]
**Files Tested**: 
  - simpanan_multipn: [filename, row count]
  - daily_loan_dinamis: [filename, row count]

## Unit Tests
- Classification: PASS/FAIL
- Decimal parsing: PASS/FAIL

## Integration Tests
- Simpanan import: PASS/FAIL (duration: __ minutes)
- Daily loan import: PASS/FAIL (duration: __ minutes)
- Data integrity: PASS/FAIL (details: ...)

## Performance
- Polars stage: __ minutes (target: < 5)
- LOAD DATA: __ minutes (daily_loan target: < 20)
- Total: __ minutes

## Issues Found
1. [Issue]
2. [Issue]

## Recommendation
[ ] DEPLOY - All tests passed, improvements visible
[ ] DEPLOY WITH MONITORING - Tests passed, performance unclear
[ ] FIX & RETEST - Issues found
[ ] ROLLBACK - Critical issues found
```

---

## Next Steps After Testing

### If Tests Pass ✅
1. Document results
2. Plan deployment to production
3. Implement Phase 2 (adaptive heartbeat)
4. Plan Phase 3 (double-scan audit)

### If Tests Fail ❌
1. Document which test failed
2. Debug root cause
3. Fix code
4. Re-run failed test
5. If multiple failures: Consider rollback

---

## Estimated Timeline

```
Unit tests: 10 min
Backup + Setup: 10 min
Simpanan import: 45 min
Daily loan import: 30 min
Data checks: 10 min
Performance analysis: 10 min
Cleanup: 5 min
---
TOTAL: 2-3 hours
```

---

**Testing Owner**: DevOps / QA team  
**Date**: Apr 26, 2026  
**Approval Required**: CTO / Tech Lead (before deployment)

---

For detailed implementation info, see:
- `DAILY_LOAN_PHASE1_IMPLEMENTATION_SUMMARY.md` - Code changes
- `OPTIMIZATION_IMPLEMENTATION_SUMMARY.md` - simpanan_multipn reference
- `OPTIMIZATION_STATUS_DASHBOARD.md` - Overall project status
