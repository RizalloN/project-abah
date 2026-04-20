# Dashboard Pinjaman Kredit - SME Implementation

## Overview
A new dashboard for monitoring loan credit performance of the SME (Small and Medium Enterprise) segment with detailed tracking of:
- OS (Outstanding - all loans)
- SML (Same-day Matured Loans - quality = 2)
- NPL (Non-Performing Loans - quality > 2)

## Access
**URL**: `/report/dashboard-pinjaman/kredit`

## Features

### 1. Period & Category Selection
- **Periode Terakhir (Latest Period)**: Dropdown selector to choose reporting period
- **Kategori**: Currently supports SME (with Consumer and Mikro placeholders for future)

### 2. Three Data Tables
Each table displays data grouped by:
- **Kantor Cabang (Branch Offices)**:
  - KC Madiun
  - KC Magetan  
  - KC Ngawi
  - KC Ponorogo

- **Sub-Categories (Kategori)**:
  - Kecil non Cashcoll
  - Cashcoll

### 3. Columns Displayed

#### Period Positions
- **YtD (Year-to-Date)**: December 31, previous year
- **M-2 (2 months ago)**: End of 2 months prior
- **MtM (Month-to-Month)**: Same date, previous month
- **Periode (Selected Period)**: Current/selected reporting date

#### Deltas (Changes)
- **YtD**: Change from YtD position
- **MtD**: Change from M-2 position
- **DtD**: Change from MtM position

## SME Segment Definitions

### Kecil non Cashcoll Filter Criteria
```sql
segmen_dashboard = 'SMALL' 
AND produk_dashboard = 'COMMERCIAL'
AND segmen_2025 = 'SMALL'
```

### Cashcoll Filter Criteria
```sql
segmen_dashboard = 'SMALL'
AND produk_dashboard IN ('CASHCALL', 'CASHCOLL')
AND segmen_2025 = 'SMALL'
```

### Quality/Kolektabilitas Filters
- **OS**: No filter (all loans)
- **SML**: `kolektabilitas_one_obligor = 2`
- **NPL**: `kolektabilitas_one_obligor > 2`

## Technical Implementation

### Service Layer
**File**: `app/Support/DashboardSmeSegmentService.php`

Methods:
- `getSmeOsData()` - Fetch OS data
- `getSmeSmlData()` - Fetch SML data (quality=2)
- `getSmeNplData()` - Fetch NPL data (quality>2)
- `calculatePeriodReferences()` - Calculate YtD, M-2, MtM dates
- `calculateAmount()` - Calculate outstanding amounts with filters
- `formatTableData()` - Format data for display with totals

### Controller Layer
**File**: `app/Http/Controllers/DashboardPinjamanReportController.php`

Methods:
- `kreditIndex()` - Render main dashboard view
- `kreditData()` - API endpoint for data fetching (JSON response)

### Routes
**File**: `routes/web.php`

```php
Route::get('/report/dashboard-pinjaman/kredit', [DashboardPinjamanReportController::class, 'kreditIndex'])
    ->name('report.dashboard-pinjaman.kredit');
Route::get('/report/dashboard-pinjaman/kredit/data', [DashboardPinjamanReportController::class, 'kreditData'])
    ->name('report.dashboard-pinjaman.kredit.data');
```

### View Layer
**File**: `resources/views/report/dashboard-pinjaman/kredit.blade.php`

Features:
- Bootstrap tabs for OS/SML/NPL switching
- Responsive table layout
- Currency and date formatting
- Lazy loading with loading indicators
- Fetch API calls with error handling

## Data Source
**Table**: `ssa_pinjaman`

**Key Columns Used**:
- `nama_cabang` - Branch office name
- `segmen_dashboard` - Dashboard segment classification
- `produk_dashboard` - Dashboard product category
- `segmen_2025` - 2025 segment classification
- `kolektabilitas_one_obligor` - Loan quality/collectability indicator
- `baki_debet` - Outstanding balance amount
- `month_day_year_of_periode` - Period date

## API Response Format

### Request
```
GET /report/dashboard-pinjaman/kredit/data
Parameters:
  - periode: YYYY-MM-DD
  - kategori: SME|Consumer|Mikro
  - type: os|sml|npl
```

### Response
```json
{
  "selected_period": "2026-04-19",
  "category": "SME",
  "type": "os",
  "header_dates": {
    "ytd": "2025-12-31",
    "m2": "2026-02-28",
    "mtm": "2026-03-19",
    "selected": "2026-04-19"
  },
  "rows": [
    {
      "no": 1,
      "branch": "KC Madiun",
      "area_head": "Area 6",
      "category": "Kecil non Cashcoll",
      "ytd": 804575000,
      "m2": 810897000,
      "mtm": 813545000,
      "selected": 813223000,
      "delta_ytd": 8648000,
      "delta_mtd": 2326000,
      "delta_dtd": -322000
    },
    ...
  ]
}
```

## Testing Checklist

- [ ] Verify period selector shows available periods from `ssa_pinjaman`
- [ ] Verify data loads correctly for each branch office
- [ ] Verify OS table shows all SME loans (no quality filter)
- [ ] Verify SML table filters to quality = 2 only
- [ ] Verify NPL table filters to quality > 2 only
- [ ] Verify delta calculations are correct (selected - comparison)
- [ ] Verify totals row sums match individual rows
- [ ] Verify currency formatting with Indonesian locale
- [ ] Verify date formatting with Indonesian locale
- [ ] Verify tab switching loads correct data
- [ ] Verify error handling when no data available
- [ ] Test with different branch combinations
- [ ] Test with no period selected (should show message)
- [ ] Performance test with large data volumes

## Future Enhancements

1. **Consumer & Mikro Segments**: Implement filtering logic similar to SME
2. **RKA Integration**: Add RKA (Budget Target) columns and achievement % calculations
3. **Export Functionality**: Add Excel/PDF export for dashboard data
4. **Comparison Period Selection**: Allow custom comparison periods
5. **Area Head Data**: Integrate area_head from master data (currently placeholder)
6. **Drill-down Reports**: Click rows to see underlying transaction details
7. **Trend Charts**: Add visualization of OS/SML/NPL trends
8. **Performance Metrics**: Add efficiency ratios and KPIs

## Known Issues / Limitations

1. **Area Head**: Currently mapped from branch office (placeholder from config). Needs actual master data integration.
2. **Category Filter**: Only SME implemented. Consumer and Mikro are UI placeholders.
3. **Caching**: Data queries hit database directly. Consider implementing snapshot caching for large datasets.
4. **RKA Columns**: Not yet implemented (mentioned in requirements but deferred for phase 2).

## Performance Notes

- Queries are grouped by branch and sub-category
- Uses efficient CASE WHEN aggregation for quality filtering
- No N+1 queries - single aggregation query per table type
- Consider adding indexed lookups if dataset grows beyond 1M records

## Support

For issues or enhancements, refer to:
- SME Segment Service: `app/Support/DashboardSmeSegmentService.php`
- Filtering Logic: Verify segmen_dashboard, produk_dashboard, segmen_2025 values in ssa_pinjaman
- Quality Codes: Verify kolektabilitas_one_obligor distribution in ssa_pinjaman
