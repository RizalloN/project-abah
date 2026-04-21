$periode = '2026-04-19';

// Check Caruban in daily_loan_dinamis
$carubanDaily = DB::table('daily_loan_dinamis')
    ->where('periode', $periode)
    ->whereRaw("UPPER(cabang1) LIKE '%CARUBAN%'")
    ->count();

// Check Caruban in ssa_pinjaman  
$carubanSSA = DB::table('ssa_pinjaman')
    ->where('periode', $periode)
    ->whereRaw("UPPER(nama_cabang) LIKE '%CARUBAN%' OR UPPER(nama_uker) LIKE '%CARUBAN%'")
    ->count();

// Check Caruban in dashboard_harian_snapshots
$carubanDash = DB::table('dashboard_harian_snapshots')
    ->where('periode', $periode)
    ->whereRaw("UPPER(unit_label) LIKE '%CARUBAN%'")
    ->count();

echo "Caruban in daily_loan_dinamis: $carubanDaily\n";
echo "Caruban in ssa_pinjaman: $carubanSSA\n";
echo "Caruban in dashboard: $carubanDash\n";
