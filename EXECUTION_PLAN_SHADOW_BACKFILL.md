# EXECUTION PLAN - Shadow Columns Backfill

**Status**: ✅ READY TO EXECUTE
**Date**: 2026-04-29
**Duration**: 6-10 minutes
**Risk Level**: Low
**Required**: Terminal/CLI access

---

## 📋 Quick Execution Guide (Choose One)

### **Option A: Fastest Path (Recommended for XAMPP Windows)**

```bash
# 1. Validate current state (30 sec)
php artisan shadow:validate --periods=2026-04-25,2026-04-26

# 2. Preview changes (30 sec) - optional
php artisan shadow:backfill --periods=2026-04-25,2026-04-26 --dry-run

# 3. Execute backfill (5-8 min)
php artisan shadow:backfill --chunk-size=5000 --delay=1000 --retry-count=5

# 4. Monitor progress - Optional (in separate terminal)
php artisan shadow:validate --watch

# 5. Verify completion (30 sec)
php artisan shadow:validate --periods=2026-04-25,2026-04-26

# Expected output:
# ✓ All shadow columns: 100% filled
```

**Total Time**: 6-10 minutes

---

### **Option B: Ultra-Conservative (If Lock Timeout Occurs)**

```bash
# Run period by period with aggressive settings
php artisan shadow:backfill --periods=2026-04-25 --chunk-size=2000 --delay=2000 --retry-count=10

# Wait 5 minutes for completion

php artisan shadow:backfill --periods=2026-04-26 --chunk-size=2000 --delay=2000 --retry-count=10

# Wait 5 minutes for completion

# Verify
php artisan shadow:validate --periods=2026-04-25,2026-04-26
```

**Total Time**: 12-15 minutes

---

### **Option C: Manual SQL (No Artisan)**

```
1. Open phpMyAdmin or MySQL CLI
2. Open file: SHADOW_BACKFILL_MANUAL_SQL.sql
3. Copy first section (validation)
4. Execute validation query
5. Copy "BAGIAN 2A" (first batch for 2026-04-25)
6. Execute & wait 30 seconds
7. Repeat for remaining batches
8. Verify completion
9. Run snapshot rebuild via terminal
```

**Total Time**: 15-30 minutes

---

## 🎯 What Will Happen

### **During Execution**

```
╔════════════════════════════════════════════════════════════════╗
║  Shadow Columns Backfill - Chunked Processing                  ║
║  Purpose: Restore data integrity for RM reports                ║
╚════════════════════════════════════════════════════════════════╝

Configuration:
  Periods: 2026-04-25, 2026-04-26
  Chunk Size: 5000 rows
  Delay Between Chunks: 1000 ms
  Dry Run: NO

📅 Processing period: 2026-04-25
   Processing 323,635 rows in chunks of 5000
   [████████░░░░░░░░░░░░] 40% | 130000/323635 | 00:45 / 02:00

(After completion...)

✓ Period completed: 323635/323635 rows

📅 Processing period: 2026-04-26
   Processing 200,000 rows in chunks of 5000
   [██████████████████░░] 90% | 180000/200000 | 01:15 / 01:30

✓ Period completed: 200000/200000 rows

🔄 Rebuilding Performance RM snapshots...
✓ Snapshots rebuilt successfully
🧹 Clearing report cache...
✓ All done! Reports should now display correctly.
```

---

## ✅ Verification Steps (After Execution)

### Step 1: Command Output ✓
Check that final status shows:
```
✓ All done! Reports should now display correctly.
```

### Step 2: Database Validation ✓

```bash
php artisan shadow:validate --periods=2026-04-25,2026-04-26
```

**Expected Output**:
```
Period: 2026-04-25 (Total: 323,635 rows)
  ✓ segmen_kinerja:      323635 / 323635 (100%)
  ✓ produk_kinerja:      323635 / 323635 (100%)
  ✓ cabang_normalized:   323635 / 323635 (100%)
  ... (all columns at 100%)

✓ All shadow columns are properly filled!
Ready to rebuild snapshots.
```

### Step 3: SQL Verification ✓

```sql
SELECT COUNT(*) as null_count 
FROM daily_loan_dinamis 
WHERE periode IN ('2026-04-25', '2026-04-26') 
AND segmen_kinerja IS NULL;

-- Expected: 0 (zero NULL values)
```

### Step 4: UI Test ✓

1. Access application: `http://localhost/project-ABAH`
2. Navigate: **Laporan > Kinerja RM > Mikro (Mantri)**
3. Select period: **2026-04-26**
4. Expected: **Data displays** (not empty/blank)

---

## ⚠️ If Something Goes Wrong

### **Scenario 1: Lock Timeout Error**

**Error Message**:
```
SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded
```

**Action**:
1. Wait 5 minutes
2. Run again with smaller chunk size:
   ```bash
   php artisan shadow:backfill --chunk-size=2000 --delay=2000
   ```

**Alternative**:
- Use manual SQL (Option C above)

---

### **Scenario 2: Command Not Found**

**Error Message**:
```
Command "shadow:backfill" is not defined
```

**Action**:
1. Verify file exists: `app/Console/Commands/BackfillShadowColumnsCommand.php`
2. Clear cache: `php artisan cache:clear`
3. Try again
4. If still fails: Use manual SQL (Option C)

---

### **Scenario 3: Reports Still Empty After Execution**

**Action**:
1. Check validation:
   ```bash
   php artisan shadow:validate --periods=2026-04-25,2026-04-26 --verbose
   ```

2. Verify database:
   ```sql
   SELECT segmen_kinerja, COUNT(*) FROM daily_loan_dinamis 
   WHERE periode = '2026-04-26' GROUP BY segmen_kinerja LIMIT 5;
   ```

3. Manual snapshot rebuild:
   ```bash
   php artisan snapshot:rebuild-rm --period=2026-04-25 --force
   php artisan snapshot:rebuild-rm --period=2026-04-26 --force
   ```

4. Manual cache clear:
   ```bash
   php artisan cache:clear
   ```

---

### **Scenario 4: Process Interrupted/Killed**

**Action**:
1. Wait 5 minutes (allow database to settle)
2. Check progress:
   ```bash
   php artisan shadow:validate --periods=2026-04-25,2026-04-26
   ```

3. If still has NULL values, reset and retry:
   ```bash
   # Reset shadow columns to NULL
   UPDATE daily_loan_dinamis 
   SET segmen_kinerja = NULL, produk_kinerja = NULL, ...
   WHERE periode IN ('2026-04-25', '2026-04-26');
   
   # Then run backfill again
   php artisan shadow:backfill --chunk-size=2000 --delay=2000
   ```

---

## 📊 Expected Timeline

```
Timeline (Option A - Recommended):

T+0s:      Start validation (30 sec)
T+30s:     Validation complete, start dry-run
T+60s:     Dry-run complete, start actual backfill
T+60s-2m:  Processing period 2026-04-25 chunk 1
T+2m-4m:   Processing period 2026-04-25 (multiple chunks)
T+4m:      Period 2026-04-25 complete
T+4m-6m:   Processing period 2026-04-26
T+6m:      Period 2026-04-26 complete
T+6m-7m:   Snapshot rebuild
T+7m-8m:   Cache clear
T+8m:      DONE ✅

Total: 8-10 minutes
```

---

## 🔍 What Gets Fixed

### Before
```
Laporan: Kinerja RM > Mikro (Mantri) 
Periode: 2026-04-26
Status:  EMPTY / "ZONK" ❌ (0 rows)
```

### After
```
Laporan: Kinerja RM > Mikro (Mantri)
Periode: 2026-04-26
Status:  COMPLETE ✓ (shows data with breakdown by category)
```

---

## 📝 Pre-Execution Checklist

Before you run the command, verify:

- [ ] You're in the project directory: `d:\XAMPP\htdocs\project-ABAH`
- [ ] You have CLI/Terminal access
- [ ] PHP is available: `php --version` works
- [ ] Laravel CLI works: `php artisan --version`
- [ ] Database connection works: `php artisan db` connects
- [ ] You have 10-15 minutes free
- [ ] You understand this won't break anything (safe operation)
- [ ] You're ready to wait 6-10 minutes

---

## 🎯 Success Indicators

### ✅ Success Looks Like:

```
✓ Backfill command completes without errors
✓ Progress bar reaches 100%
✓ Snapshot rebuild shows "success"
✓ Cache clear completes
✓ Validation shows all 100%
✓ Reports display data
✓ No NULL values found
```

### ❌ Failure Looks Like:

```
❌ Command returns error (other than timeout)
❌ Progress bar gets stuck
❌ Validation shows columns < 100%
❌ Reports still empty
❌ Database errors in log
```

If failure occurs → Check troubleshooting section above

---

## 🚀 Ready to Execute?

### **Step 1**: Open Terminal/CLI
```bash
cd d:\XAMPP\htdocs\project-ABAH
```

### **Step 2**: Run Command
```bash
# Recommended for XAMPP Windows:
php artisan shadow:backfill --chunk-size=5000 --delay=1000 --retry-count=5
```

### **Step 3**: Wait for Completion
- Watch progress bar
- Let it finish (6-10 minutes)
- Do not interrupt

### **Step 4**: Verify
```bash
php artisan shadow:validate --periods=2026-04-25,2026-04-26
```

### **Step 5**: Test in UI
- Access application
- Check Kinerja RM report for 2026-04-26
- Verify data displays

### **✅ Done!**

---

## 📚 Documentation References

| Need | File |
|------|------|
| Quick reference | [SHADOW_BACKFILL_QUICK_START.md](SHADOW_BACKFILL_QUICK_START.md) |
| Full guide | [SHADOW_BACKFILL_GUIDE.md](SHADOW_BACKFILL_GUIDE.md) |
| Understanding | [ROOT_CAUSE_ANALYSIS_SHADOW_COLUMNS.md](ROOT_CAUSE_ANALYSIS_SHADOW_COLUMNS.md) |
| Manual SQL | [SHADOW_BACKFILL_MANUAL_SQL.sql](SHADOW_BACKFILL_MANUAL_SQL.sql) |
| Navigation | [SHADOW_BACKFILL_FILES_INDEX.md](SHADOW_BACKFILL_FILES_INDEX.md) |
| Tech summary | [SHADOW_BACKFILL_IMPLEMENTATION_SUMMARY.md](SHADOW_BACKFILL_IMPLEMENTATION_SUMMARY.md) |
| Deliverables | [DELIVERABLES_SHADOW_BACKFILL.md](DELIVERABLES_SHADOW_BACKFILL.md) |

---

## 💡 Pro Tips

1. **Monitor Progress**: Open second terminal and run `php artisan shadow:validate --watch`
2. **Save Output**: Capture command output to file: `php artisan shadow:backfill > backfill_log.txt 2>&1`
3. **Off-hours**: Run during non-peak times to minimize impact
4. **Notify Team**: Let stakeholders know reports will be fixed after execution
5. **Document**: Note which parameters worked for your environment

---

## ⏱️ Time Breakdown

| Phase | Duration |
|-------|----------|
| Validation | 0.5 min |
| Dry-run | 0.5 min |
| Backfill period 2026-04-25 | 3-5 min |
| Backfill period 2026-04-26 | 2-3 min |
| Snapshot rebuild | 1-2 min |
| Cache clear | 0.5 min |
| **Total** | **6-10 min** |

---

## 🎉 Final Outcome

After successful execution:

✅ Shadow columns filled 100%
✅ Snapshots rebuilt with fresh data
✅ Cache cleared
✅ Reports display correctly
✅ Kinerja RM > Mikro shows data
✅ Users can access reports

---

**Status**: Ready to execute on your schedule
**Risk**: Low (safe, atomic operation)
**Rollback**: Not needed (idempotent)
**Next Step**: Pick Option A/B/C and execute!

---

Execute whenever you're ready. Questions? Check documentation files listed above.

**Estimated Completion**: 2026-04-29, within 10 minutes of execution
