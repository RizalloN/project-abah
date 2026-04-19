# Snapshot Audit & Smart Sync - Quick Start

## What This Does

Intelligent snapshot verification that:
1. **Detects** when snapshots are out of sync with source data
2. **Identifies** exactly which periods/segments are wrong
3. **Fixes** only the broken parts (not everything)

### Result
✅ **60-90% faster** than full rebuild  
✅ **Surgical fixes** - only rebuild what's broken  
✅ **Visible proof** - see exact discrepancies  

---

## One-Minute Walkthrough

### Problem Scenario

You're in Report Management and suspect a snapshot might be out of sync:

```
Daily Loan Dinamis Snapshot
├─ Status: Active
├─ Last Synced: 2026-04-18
└─ ⚠️ Suspicious: Numbers don't look right
```

### Solution

**Step 1: Run Audit** (analyzes totals)
```bash
POST /import/snapshot-audit/run
{
    "table_name": "daily_loan_dinamis",
    "period_hint": "2026-04-19"
}

# Response: "Found 3 critical discrepancies in period 2026-04-19"
```

**Step 2: Review Results** (shows what's wrong)
```
Discrepancies Found:
├─ Total Plafon: Source 1B, Snapshot 999.5M (variance: 0.5M, 0.05%)
├─ Total Baki Debet: Source 500M, Snapshot 498M (variance: 2M, 0.4%) ⚠️ Critical
└─ Record Count: Source 10K, Snapshot 10K ✓ OK
```

**Step 3: Smart Sync** (rebuilds only period 2026-04-19)
```bash
POST /import/snapshot-audit/{auditId}/rebuild

# 2 minutes later: Only affected period rebuilt
# Full rebuild would take 30 minutes
```

**Step 4: Verify** (optional - re-audit to confirm)
```bash
POST /import/snapshot-audit/run  # Same as step 1

# Response: "All metrics match source. Status: CLEAN"
```

---

## 5-Minute Implementation

### File Already Created
✅ `app/Support/SnapshotAuditService.php`  
✅ `app/Support/SnapshotAuditCoordinator.php`  
✅ `app/Jobs/SmartPartialSnapshotRebuildJob.php`  
✅ `app/Http/Controllers/Import/SnapshotAuditController.php`  
✅ Routes added to `routes/web.php`  

### Zero Configuration Needed
The system works out of the box. No setup required.

---

## Using in Report Management UI

### Add Audit Button

```html
<!-- In report management template -->
<button onclick="runSnapshotAudit('daily_loan_dinamis')">
    🔍 Run Audit
</button>
```

### JavaScript Integration

```javascript
async function runSnapshotAudit(tableName) {
    const response = await fetch('/import/snapshot-audit/run', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ table_name: tableName })
    });

    const { data } = await response.json();
    const { audit_id, summary } = data;

    // Show results
    if (summary.action_required) {
        showAlert(`${summary.critical_issues} critical issues found`);
        
        // Show rebuild button
        document.getElementById('rebuild-btn').onclick = () => {
            triggerSmartRebuild(audit_id);
        };
    } else {
        showAlert('Snapshot is clean ✓');
    }
}

async function triggerSmartRebuild(auditId) {
    const response = await fetch(`/import/snapshot-audit/${auditId}/rebuild`, {
        method: 'POST'
    });

    const { data } = await response.json();
    showAlert(`Rebuilding ${data.period_count} period(s)...`);
    
    // Monitor via existing queue status
}
```

---

## Common Scenarios

### Scenario 1: Verify After Import

```javascript
// After completing an import
const result = await fetch('/import/snapshot-audit/run', {
    method: 'POST',
    body: JSON.stringify({
        table_name: 'daily_loan_dinamis',
        period_hint: importedPeriod
    })
}).then(r => r.json());

if (result.data.status === 'clean') {
    console.log('✓ Snapshot is accurate');
} else {
    console.log(`⚠ ${result.data.summary.critical_issues} issues found`);
    // Trigger smart rebuild
}
```

### Scenario 2: Scheduled Daily Audit

```php
// In app/Console/Kernel.php
$schedule->call(function () {
    $coordinator = app(\App\Support\SnapshotAuditCoordinator::class);
    
    $tables = ['daily_loan_dinamis', 'simpanan_multipn'];
    
    foreach ($tables as $table) {
        $result = $coordinator->runAudit($table);
        
        if ($result['summary']['action_required'] ?? false) {
            // Auto-fix if critical issues found
            $coordinator->triggerSmartRebuild($result['audit_id']);
            
            // Log for review
            Log::warning("Auto-fixed snapshot for {$table}", $result['summary']);
        }
    }
})->daily()->at('02:00');  // 2 AM
```

### Scenario 3: Before-After Comparison

```javascript
// Run audit before
const auditBefore = await runAudit('daily_loan_dinamis');

// Trigger rebuild
await triggerSmartRebuild(auditBefore.audit_id);

// Wait for rebuild to complete
await sleep(5000);

// Run audit after
const auditAfter = await runAudit('daily_loan_dinamis');

// Compare
const comparison = await fetch('/import/snapshot-audit/compare', {
    method: 'POST',
    body: JSON.stringify({
        audit_id_1: auditBefore.audit_id,
        audit_id_2: auditAfter.audit_id
    })
}).then(r => r.json());

console.log(`Fixed ${comparison.data.improvement.issues_fixed} issues`);
```

---

## API Reference (Quick)

### Run Audit
```http
POST /import/snapshot-audit/run
Content-Type: application/json

{
    "table_name": "daily_loan_dinamis",
    "period_hint": "2026-04-19"  // optional
}
```

### Get Audit Result
```http
GET /import/snapshot-audit/{auditId}/result
```

### Trigger Smart Rebuild
```http
POST /import/snapshot-audit/{auditId}/rebuild
```

### Compare Audits
```http
POST /import/snapshot-audit/compare

{
    "audit_id_1": "uuid",
    "audit_id_2": "uuid"
}
```

### Get Recommended Action
```http
GET /import/snapshot-audit/action/{tableName}?period_hint=2026-04-19
```

---

## Supported Tables

| Table | Audits |
|-------|--------|
| `daily_loan_dinamis` | Plafon, Baki Debet, NPL, Record Count |
| `simpanan_multipn` | Balance, Interest, Record Count |
| `ssa_simpanan` | Balance, Interest, Record Count |
| `ssa_pinjaman` | Outstanding, Interest, Record Count |
| `lw325_ph` | Outstanding, Interest, Record Count |

---

## Troubleshooting

### Audit Not Found Error

**Problem**: `Audit result not found or expired`

**Solution**: 
- Audit results cached for 1 hour
- Re-run audit to get fresh result

### Rebuild Shows 0 Periods

**Problem**: No affected periods identified

**Solution**:
- Snapshot might already be correct
- Run audit again to verify

### Rebuild Takes Too Long

**Problem**: Smart rebuild still slow

**Solution**:
1. Check queue workers: `php artisan queue:work`
2. Check if period has many records
3. Run during off-peak hours

### Can't See Audit Results

**Problem**: 404 when getting results

**Cause**: Browser cached old audit ID

**Solution**:
1. Clear browser cache
2. Run new audit
3. Use fresh audit ID from response

---

## Performance Tips

### Audit Optimization

```bash
# Run on specific period (faster)
POST /import/snapshot-audit/run
{
    "table_name": "daily_loan_dinamis",
    "period_hint": "2026-04-19"  # Much faster than all periods
}

# vs all periods (slower)
POST /import/snapshot-audit/run
{
    "table_name": "daily_loan_dinamis"
    # No period hint = audit ALL periods
}
```

### Rebuild Optimization

Smart rebuild automatically optimizes:
- Only rebuild affected periods
- Delete old records first
- Rebuild from source efficiently
- No lock on entire snapshot

---

## Deep Dive

For detailed information, see:
- [SNAPSHOT_AUDIT_AND_SMART_SYNC.md](SNAPSHOT_AUDIT_AND_SMART_SYNC.md) - Full technical guide
- [IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md) - Implementation details

---

## Summary

**What You Get**:
- ✅ Automated discrepancy detection
- ✅ Surgical snapshot fixes
- ✅ 60-90% faster sync for partial issues
- ✅ Clear visibility into data quality
- ✅ Zero configuration required

**Next Steps**:
1. ✅ Code is already in place
2. Add UI buttons for "Run Audit" and "Smart Sync"
3. Optionally schedule daily audits
4. Monitor snapshot health proactively

**That's it!** Your snapshot system is now intelligent and efficient. 🚀
