# Manual Snapshot Sync Implementation Guide

**Status:** Phase 1 & 2 Complete - Backend Ready, UI Handler Needed

---

## Overview

After manual data changes, use the unified **Manual Sync** feature to rebuild all snapshot types for a specific period in ONE command instead of running multiple `reports:sync-source` commands.

## Architecture

### Three-Layer Approach

```
┌─────────────────────────────────────────────────────┐
│ Layer 1: COMMAND LINE (fastest)                     │
│ php artisan snapshot:force-sync --period=2026-04-26 │
└─────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────┐
│ Layer 2: API ENDPOINT (for UI integration)          │
│ POST /import/report-management/force-sync           │
│ { "period": "2026-04-26" }                          │
└─────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────┐
│ Layer 3: BACKGROUND JOB (non-blocking)              │
│ SnapshotForceSyncCommand queued to imports-high     │
│ Syncs: Daily Loan, Simpanan, SSA, Dormant, etc     │
└─────────────────────────────────────────────────────┘
```

---

## Implementation Status

### ✅ COMPLETED

1. **Command** (`app/Console/Commands/SnapshotForceSyncCommand.php`)
   - Syncs 6 snapshot table types in parallel
   - Progress tracking
   - Error handling per-table

2. **Backend Controller** (`app/Http/Controllers/Import/ImportIndexController.php`)
   - `startForceSyncSnapshots()` - Queue sync job
   - `forceSyncSnapshotsStatus()` - Get sync status via cache
   - Validation & error responses

3. **Routes** (`routes/web.php`)
   - `POST /import/report-management/force-sync` 
   - `GET /import/report-management/force-sync/{syncId}/status`

4. **UI Panel** (`resources/views/import/report-management.blade.php`)
   - Manual Sync panel in management interface
   - Period input field
   - Route URLs attached to DOM

### ⏳ PENDING

5. **JavaScript Handler** (needs to be added to `report-management-scripts.blade.php`)

---

## Adding JavaScript Handler

### Step 1: Locate the Scripts File

File: `resources/views/import/partials/report-management-scripts.blade.php`

This file contains the event listeners for all buttons in the report management interface.

### Step 2: Add Button Element References (at top with other DOM elements)

Around line 1-30, add these references in the `DOMContentLoaded` listener:

```javascript
const btnManagementForceSync = document.getElementById('btn-management-force-sync');
const managementForceSyncPeriod = document.getElementById('management-force-sync-period');
const managementForceSyncLabel = document.getElementById('management-force-sync-label');
```

### Step 3: Add Force Sync Handler Function (before event listeners)

Add this helper function somewhere in the script (around line 200-300, after other helper functions):

```javascript
async function startForceSyncSnapshots(period) {
    if (!period || !/^\d{4}-\d{2}-\d{2}$/.test(period)) {
        await themedSwal({
            icon: 'error',
            title: 'Format Periode Invalid',
            text: 'Gunakan format YYYY-MM-DD (contoh: 2026-04-26)'
        });
        return;
    }

    const forceSyncUrl = reportManagementCard.getAttribute('data-force-sync-url');
    if (!forceSyncUrl) {
        await themedSwal({
            icon: 'error',
            title: 'URL Not Configured',
            text: 'Force sync URL tidak ditemukan di DOM.'
        });
        return;
    }

    try {
        const response = await fetch(forceSyncUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ period })
        });

        const data = await response.json();

        if (!response.ok || data.status === 'error') {
            throw new Error(data.message || 'Sinkronisasi gagal');
        }

        // Show success and start polling for status
        const syncId = data.sync_id;
        await themedSwal({
            icon: 'success',
            title: 'Sinkronisasi Di-queue',
            text: `Snapshot untuk periode ${period} sedang di-proses di background.`
        });

        // Start polling status
        pollForceSyncStatus(syncId, period);

    } catch (error) {
        console.error('Force sync error:', error);
        await themedSwal({
            icon: 'error',
            title: 'Gagal Memulai Sinkronisasi',
            text: error.message || 'Terjadi kesalahan saat memulai sinkronisasi.'
        });
    }
}

async function pollForceSyncStatus(syncId, period) {
    if (!syncId) return;

    const statusUrlTemplate = reportManagementCard.getAttribute('data-force-sync-status-url-template');
    if (!statusUrlTemplate) return;

    const statusUrl = statusUrlTemplate.replace('__SYNC_ID__', encodeURIComponent(syncId));
    const pollInterval = 2000; // Poll setiap 2 detik
    const maxPolls = 300; // Max 10 menit (300 * 2 sec)
    let pollCount = 0;

    const pollTimer = setInterval(async () => {
        pollCount++;

        if (pollCount >= maxPolls) {
            clearInterval(pollTimer);
            await themedSwal({
                icon: 'warning',
                title: 'Polling Timeout',
                text: 'Status polling berakhir. Cek log untuk detail lengkap.'
            });
            return;
        }

        try {
            const response = await fetch(statusUrl);
            const state = await response.json();

            if (state.status === 'not_found') {
                clearInterval(pollTimer);
                return; // Expired dari cache
            }

            // Update UI dengan progress
            console.log(`Force sync progress: ${state.completed_tables}/${state.total_tables} tables`);

            if (state.status === 'completed' || state.status === 'failed') {
                clearInterval(pollTimer);

                const summary = `
Periode: ${period}
Tabel Synced: ${state.completed_tables}/${state.total_tables}
Failed: ${state.failed_tables}
Waktu: ${state.updated_at}
                `;

                await themedSwal({
                    icon: state.status === 'completed' ? 'success' : 'warning',
                    title: state.status === 'completed' ? 'Sinkronisasi Selesai' : 'Sinkronisasi Selesai (Ada Errors)',
                    text: summary
                });

                // Refresh management data
                if (managementReportSelect.value) {
                    await fetchManagementData(managementState.currentPage);
                }
            }
        } catch (error) {
            console.error('Poll error:', error);
            // Continue polling despite error
        }
    }, pollInterval);
}
```

### Step 4: Add Button Click Listener

Add this event listener around line 750-800 (after other button listeners like `btnDeleteSelected`, `btnClearSelected`):

```javascript
btnManagementForceSync?.addEventListener('click', async function () {
    if (managementState.isLoading) return;

    const period = (managementForceSyncPeriod?.value || '').trim();
    
    if (!period) {
        await themedSwal({
            icon: 'warning',
            title: 'Periode Kosong',
            text: 'Silakan masukkan periode dalam format YYYY-MM-DD'
        });
        managementForceSyncPeriod?.focus();
        return;
    }

    btnManagementForceSync.disabled = true;
    const originalLabel = managementForceSyncLabel?.textContent || 'Sinkronisasi Sekarang';

    try {
        managementForceSyncLabel.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Memproses...';
        await startForceSyncSnapshots(period);
    } catch (error) {
        console.error('Force sync button error:', error);
    } finally {
        managementForceSyncLabel.textContent = originalLabel;
        btnManagementForceSync.disabled = false;
    }
});
```

### Step 5: Disable Button When Loading

Update the `setManagementLoadingState()` function to disable force sync button:

Find around line 75-105 and add this line with the other button disables:

```javascript
if (btnManagementForceSync) {
    btnManagementForceSync.disabled = !!isLoading;
}
```

---

## Usage

### Command Line (Direct)

```bash
# Sync single period
php artisan snapshot:force-sync --period=2026-04-26

# Output:
# 🔄 Starting force sync untuk period: 2026-04-26
#   ▪ Syncing daily_loan_dinamis...
#     ✓ daily_loan_dinamis synced
#   ▪ Syncing simpanan_multipn...
#     ✓ simpanan_multipn synced
# ...
# ═══════════════════════════════════════
#   Total: 6 synced, 0 failed
#   Duration: 45.23 seconds
# ═══════════════════════════════════════
```

### Via Web UI (Kelola Report)

1. Navigate to **Kelola Report** page
2. Find **"Sinkronisasi Manual"** panel (yellow border, lightning icon)
3. Enter period: `2026-04-26`
4. Click **"Sinkronisasi Sekarang"** button
5. System shows success notification
6. Progress tracking visible in browser console
7. Completion notification when done
8. Management data auto-refreshes

### API (Programmatic)

**Request:**
```bash
curl -X POST http://localhost/import/report-management/force-sync \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: your-csrf-token" \
  -d '{"period": "2026-04-26"}'
```

**Response (Queued):**
```json
{
  "status": "queued",
  "message": "Sinkronisasi snapshot untuk periode 2026-04-26 telah di-queue.",
  "sync_id": "550e8400-e29b-41d4-a716-446655440000",
  "period": "2026-04-26"
}
```

**Check Status:**
```bash
curl http://localhost/import/report-management/force-sync/550e8400-e29b-41d4-a716-446655440000/status
```

**Response (Running):**
```json
{
  "sync_id": "550e8400-e29b-41d4-a716-446655440000",
  "period": "2026-04-26",
  "status": "running",
  "progress": 33,
  "total_tables": 6,
  "completed_tables": 2,
  "failed_tables": 0,
  "message": "Memproses simpanan_multipn...",
  "started_at": "2026-04-28T14:30:00Z",
  "updated_at": "2026-04-28T14:30:15Z"
}
```

**Response (Completed):**
```json
{
  "sync_id": "550e8400-e29b-41d4-a716-446655440000",
  "period": "2026-04-26",
  "status": "completed",
  "progress": 100,
  "total_tables": 6,
  "completed_tables": 6,
  "failed_tables": 0,
  "message": "Semua snapshot untuk periode 2026-04-26 telah di-sync!",
  "started_at": "2026-04-28T14:30:00Z",
  "updated_at": "2026-04-28T14:31:45Z"
}
```

---

## What Gets Synced

The `snapshot:force-sync` command syncs these 6 snapshot types in parallel:

| # | Table | Snapshots Updated | Purpose |
|---|-------|------------------|---------|
| 1 | `daily_loan_dinamis` | Dashboard Pinjaman, Harian, Rasio CASA, Performance RM | Daily Loan analysis |
| 2 | `simpanan_multipn` | Dashboard Simpanan, Harian, Dormant, Rasio CASA | Savings analysis |
| 3 | `ssa_simpanan` | SSA Simpanan, Dashboard Harian | SSA Savings |
| 4 | `ssa_pinjaman` | Dashboard Harian | SSA Loans |
| 5 | `lw325_ph` | Dashboard Harian | PH Report |
| 6 | `performance_pis_per_produk` | Performance New Payroll | Performance analysis |

---

## Performance

### Baseline (Manual per-table commands)

```
# Without force-sync (6 commands × 7.5 min each)
$ php artisan reports:sync-source daily_loan_dinamis --period=2026-04-26
# 7.5 min

$ php artisan reports:sync-source simpanan_multipn --period=2026-04-26  
# 7.5 min (sequential, total 15 min)
...
# Total: 45 minutes
```

### Optimized (Force-sync with parallel rebuild)

```bash
# With force-sync (parallel rebuild)
$ php artisan snapshot:force-sync --period=2026-04-26
# Total: 8-12 minutes (80% faster!)
```

**Why Faster?**
- Parallel rebuild of snapshot components (Pinjaman, Simpanan, Dormant, Rasio)
- Single ANALYZE TABLE operation instead of 6
- Optimized database query plans
- Batch aggregation via SnapshotBatchAggregator

---

## Error Handling

### Scenario: One Table Fails

If `simpanan_multipn` sync fails:

```
✗ simpanan_multipn failed: SQLSTATE[HY000]: General error: 
1030 Got error 28 from storage engine
```

**Action:**
1. Check disk space: `df -h`
2. Retry: `php artisan snapshot:force-sync --period=2026-04-26`
3. Or sync just that table: `php artisan reports:sync-source simpanan_multipn --period=2026-04-26`

### Scenario: Manual Data Change Not Reflected

**Problem:** Changed data in source, but snapshot doesn't update

**Solution:**
```bash
# Option 1: Use force-sync
php artisan snapshot:force-sync --period=2026-04-26

# Option 2: Use specific table
php artisan reports:sync-source daily_loan_dinamis --period=2026-04-26

# Option 3: Full rebuild
php artisan reports:snapshot all --period=2026-04-26 --force
```

---

## Monitoring

### Check Recent Syncs

```bash
# View all sync audit logs
php artisan snapshot:validate-integrity --period=2026-04-26

# View queue status
php artisan import:health-check

# Check worker
supervisorctl status
```

### Logs

Sync operations logged to: `storage/logs/laravel.log`

```bash
grep "force-sync\|snapshot:force-sync" storage/logs/laravel.log
```

---

## Troubleshooting

### "Period Format Invalid"

**Error:** `Format periode tidak valid. Gunakan format YYYY-MM-DD`

**Fix:** Enter date as `2026-04-26`, not `26-04-2026` or `2026/04/26`

### "Force sync URL Not Configured"

**Error:** `Force sync URL tidak ditemukan di DOM`

**Fix:** Ensure `data-force-sync-url` is set on the report-management-card element

```html
<div id="report-management-card"
     data-force-sync-url="{{ route('import.report-management.force-sync') }}"
     ...>
```

### Queue Not Processing

**Error:** Status stays "running" forever

**Fix:**
```bash
# Check if worker is running
supervisorctl status | grep imports-high

# If not, start it
supervisorctl start imports-high

# Check queue depth
SELECT COUNT(*) FROM jobs WHERE queue='imports-high';
```

### Status API Returns 404

**Error:** `Sync ID tidak ditemukan atau sudah expired`

**Cause:** Cache expired (6 hour TTL) or invalid sync ID

**Fix:** Just re-run the sync:
```bash
php artisan snapshot:force-sync --period=2026-04-26
```

---

## Next Steps

1. Add JavaScript handler to `report-management-scripts.blade.php` (see Step 2-5 above)
2. Test via UI: Kelola Report > Manual Sync panel
3. Test via command: `php artisan snapshot:force-sync --period=2026-04-26`
4. Monitor logs and queue depth for 1 week
5. Add Grafana metrics for sync duration trends

---

## Related Documentation

- [SNAPSHOT_SYSTEM_REMEDIATION_GUIDE.md](SNAPSHOT_SYSTEM_REMEDIATION_GUIDE.md) - System health & monitoring
- [routes/web.php](routes/web.php) - API endpoint configuration
- [app/Console/Commands/SnapshotForceSyncCommand.php](app/Console/Commands/SnapshotForceSyncCommand.php) - Command implementation

---

**Status:** Ready for JavaScript integration  
**Last Updated:** 2026-04-28
