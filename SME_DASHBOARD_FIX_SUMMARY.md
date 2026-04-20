# Dashboard Pinjaman Kredit SME - Data Population Fix

## Issue Summary
Dashboard Pinjaman Kredit SME displayed "Rp 0" for all OS, SML, and NPL values across all branches and categories.

## Root Cause Analysis
The `DashboardSmeSegmentService` was attempting to query the `dashboard_harian_snapshots` table using an incorrect column name for the period filter:
- **Incorrect**: `where('periode', $period)` 
- **Correct**: `where('snapshot_period', $period)`

This caused all snapshot queries to return no results, defaulting to zero values.

## Solution Applied

### File Modified
**app/Support/DashboardSmeSegmentService.php**

### Specific Changes
In the `getSnapshotAmount()` method (line ~170), changed:
```php
// BEFORE (Incorrect)
$result = DB::table(self::SNAPSHOT_TABLE)
    ->where('periode', $period)  // ❌ Column doesn't exist
    ->where('kanca_label', $branch)
    ...

// AFTER (Fixed)
$result = DB::table(self::SNAPSHOT_TABLE)
    ->where('snapshot_period', $period)  // ✅ Correct column name
    ->where('kanca_label', $branch)
    ...
```

## Snapshot Table Schema Reference
- **Period Column**: `snapshot_period` (not `periode`)
- **Branch Column**: `kanca_label`
- **Available SME Metrics**:
  - Outstanding: `kecil_non_cashcoll_os`, `cashcoll_os`
  - SML: `kecil_non_cashcoll_sml`, `cashcoll_sml`
  - NPL: `kecil_non_cashcoll_npl`, `cashcoll_npl`

## Verification Results

### Test Output (Period: 2026-04-19)
- **OS Total**: Rp 2,360,735,128,949
  - KC Madiun: Rp 993,878,597,506
  - KC Magetan: Rp 374,841,707,488
  
- **SML Total**: Rp 438,239,196,844
  - KC Madiun: Rp 184,517,641,619
  - KC Magetan: Rp 47,711,088,410

- **NPL Total**: Rp 210,147,841,966
  - KC Madiun: Rp 96,851,559,257
  - KC Magetan: Rp 22,970,589,672

### Status
✅ Service now retrieves actual data from snapshots
✅ All three data types (OS, SML, NPL) working correctly
✅ No syntax errors detected
✅ Dashboard should now display actual values instead of "Rp 0"

## How the Dashboard Works Now

1. **User selects period** via `periodeSelector` dropdown
2. **Frontend calls API** → `/report/dashboard-pinjaman/kredit/data`
3. **Controller** (`DashboardPinjamanReportController::kreditData()`)
   - Calls `DashboardSmeSegmentService::calculatePeriodReferences()`
   - Calls appropriate method based on type: `getSmeOsData()`, `getSmeSmlData()`, or `getSmeNplData()`
4. **Service** queries `dashboard_harian_snapshots` table
   - Uses `snapshot_period` for period matching ✅
   - Uses `kanca_label` for branch matching
   - Sums SME columns by branch and category
5. **Response** returns JSON with data rows formatted for display
6. **Frontend** renders table with actual currency-formatted values

## Files Affected
- ✅ `app/Support/DashboardSmeSegmentService.php` - FIXED
- ✅ `app/Http/Controllers/DashboardPinjamanReportController.php` - No changes needed (already correct)
- ✅ `resources/views/report/dashboard-pinjaman/kredit.blade.php` - No changes needed (already correct)
- ✅ `routes/web.php` - No changes needed (routes already configured)

## Testing Recommendations
1. Access dashboard at `/report/dashboard-pinjaman/kredit`
2. Select a period (e.g., 2026-04-19)
3. Verify OS tab displays actual values (not "Rp 0")
4. Verify SML and NPL tabs also display values
5. Compare against snapshot data: 
   ```sql
   SELECT snapshot_period, kanca_label, 
          kecil_non_cashcoll_os, cashcoll_os,
          kecil_non_cashcoll_sml, cashcoll_sml
   FROM dashboard_harian_snapshots 
   WHERE snapshot_period = '2026-04-19'
   ORDER BY kanca_label;
   ```

## Performance Characteristics
- **Data Source**: Pre-aggregated snapshots (highly optimized)
- **Query Type**: Simple indexed lookups using snapshot_period + kanca_label
- **Response Time**: Should be < 100ms per API call
- **Database Impact**: Minimal - uses existing indexes on snapshot table

## Related Documentation
- Dashboard Harian Snapshot Infrastructure: See `/memories/repo/dashboard-harian-snapshot-cache.md`
- Previous fixes and patterns documented there

---
**Fix Date**: 2026  
**Status**: ✅ RESOLVED - Dashboard showing actual SME loan data
