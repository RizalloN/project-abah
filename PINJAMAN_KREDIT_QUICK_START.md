# Dashboard Pinjaman Kredit SME - Quick Start Guide

## Access the Dashboard

1. **URL**: Navigate to `http://your-app/report/dashboard-pinjaman/kredit`
2. You should see three sections:
   - Period selector (Periode Terakhir)
   - Category selector (Kategori)
   - Load Data button
   - Three tabs: OS, SML, NPL

## How to Use

### Step 1: Select a Period
- Click on "Periode Terakhir" dropdown
- Choose a date from available periods
- Dates are fetched from your `ssa_pinjaman` data

### Step 2: Select Category (Optional)
- Currently defaults to "SME"
- Other options (Consumer, Mikro) are available for future use

### Step 3: Load Data
- Click "Muat Data" (Load Data) button
- Data will load for all three tables (OS, SML, NPL)
- You can also click individual tabs to load only that section

### Step 4: View Data
- **OS Tab**: Shows all SME outstanding loans
- **SML Tab**: Shows loans with quality level 2
- **NPL Tab**: Shows non-performing loans (quality > 2)

## Understanding the Table

### Columns

| Column | Meaning |
|--------|---------|
| No. | Row number |
| Kantor Cabang | Branch office (KC Madiun, KC Magetan, etc.) |
| Area Head | Area code (currently Area 6 for all) |
| Kategori | Sub-category (Kecil non Cashcoll or Cashcoll) |
| 31 Dec 25 (YtD) | Outstanding as of December 31, previous year |
| 31 Mar 26 (M-2) | Outstanding 2 months ago |
| 18 Apr 26 (MtM) | Outstanding same date previous month |
| Periode | Outstanding for selected date (highlighted) |
| Δ YtD | Change from YtD position |
| Δ MtD | Change from 2-month position |
| Δ DtD | Change from month-ago position |

### Reading the Data

1. **Outstanding Growth**: Look at the last "Periode" column - highest numbers indicate most outstanding loans
2. **Monthly Changes**: Check "Δ MtD" to see month-over-month growth/decline
3. **Year-to-Date Trend**: "Δ YtD" shows overall trend since start of year
4. **Branch Comparison**: Compare across different branches (KC Madiun, KC Magetan, etc.)
5. **Sub-Category Analysis**: Compare Kecil non Cashcoll vs Cashcoll performance

## Interpreting the Data

### OS (Outstanding) Table
- Shows total outstanding loans for SME segment
- Positive delta means loans increased
- Negative delta means loans decreased (early payment/maturity)

### SML (Same-day Matured) Table  
- Shows loans with 30-90 day delinquency
- Should generally be lower than OS
- Rising SML indicates quality deterioration

### NPL (Non-Performing) Table
- Shows loans with >120 day delinquency
- Red flag for portfolio health
- High NPL in any branch needs investigation

## Sample Data Interpretation

```
Branch: KC Madiun
Category: Kecil non Cashcoll

OS Position:
- YtD (Dec 31): 804.575 Million
- MtM (Mar 18): 813.545 Million  
- Periode (Apr 19): 813.223 Million
- Delta YtD: +8.648 Million (1.1% growth YoY)
- Delta MtD: +2.326 Million (0.3% growth MoM)
- Delta DtD: -0.322 Million (slight decline from last month same date)

Interpretation:
- Solid growth YoY but stabilizing
- Small monthly growth indicating market expansion
- Slight pullback day-to-day might be seasonal
```

## Troubleshooting

### "Silakan pilih periode terlebih dahulu" (Please select period first)
- **Solution**: Click period dropdown and select a date before clicking Load Data

### "Tidak ada data" (No data)
- **Cause**: Selected period might not have data in ssa_pinjaman table
- **Solution**: Try a different period or verify data exists

### Table columns show "-" 
- **Cause**: Data not available for that period
- **Solution**: This is normal for comparison periods that might not exist

### Numbers show as "NaN" or incorrect format
- **Cause**: Data formatting issue (usually temporary)
- **Solution**: Refresh page and reload data

## Performance Tips

1. **First Load**: May take a few seconds when loading large datasets
2. **Tab Switching**: Each tab loads independently - can take a moment
3. **Multiple Branches**: Tables showing all 4 branches calculate totals automatically
4. **Large Datasets**: If loading is slow, verify your ssa_pinjaman table is indexed on:
   - month_day_year_of_periode
   - nama_cabang
   - segmen_dashboard
   - produk_dashboard
   - segmen_2025

## Integration with Other Dashboards

This dashboard integrates with:
- **Main Dashboard**: `/report/dashboard-pinjaman` (Summary & Matrix views)
- **Mismatch Report**: `/report/dashboard-pinjaman/kolek-tidak-sesuai` (Quality/Collectability mismatches)

## Data Requirements

For this dashboard to work, your `ssa_pinjaman` table must have:

### Required Columns
- `month_day_year_of_periode` - DATE format (YYYY-MM-DD)
- `nama_cabang` - Branch office names matching KC Madiun, KC Magetan, KC Ngawi, KC Ponorogo
- `segmen_dashboard` - Values including 'SMALL'
- `produk_dashboard` - Values including 'COMMERCIAL', 'CASHCALL', 'CASHCOLL'
- `segmen_2025` - Values including 'SMALL'
- `kolektabilitas_one_obligor` - TINYINT 1-5 (1=Current, 2=SML, 3-5=NPL)
- `baki_debet` - DECIMAL for outstanding balance

### Sample Query to Verify Data
```sql
SELECT 
    DISTINCT month_day_year_of_periode,
    nama_cabang,
    segmen_dashboard,
    produk_dashboard,
    segmen_2025,
    kolektabilitas_one_obligor
FROM ssa_pinjaman
WHERE segmen_dashboard = 'SMALL'
LIMIT 10;
```

## Next Steps

1. ✅ View the dashboard with current data
2. ✅ Verify numbers match your expected portfolio
3. ⏳ [Future] Configure RKA targets for Pencapaian RKA columns
4. ⏳ [Future] Set up area_head mappings for regional analysis
5. ⏳ [Future] Export functionality for reports

## Support & Questions

- **Data Issue?**: Check PINJAMAN_KREDIT_SME_DASHBOARD.md for data source details
- **Calculation Issue?**: Review DashboardSmeSegmentService.php for logic
- **Display Issue?**: Check browser console (F12) for JavaScript errors
