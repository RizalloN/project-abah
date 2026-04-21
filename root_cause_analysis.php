<?php
include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== ROOT CAUSE ANALYSIS: CONSUMER OUTSTANDING DISCREPANCY ===\n\n";

// The user's specific issue: KC Madiun
echo "USER'S REPORT (KC MADIUN):\n";
echo str_repeat("-", 80) . "\n";
echo "Period value (dari daily_loan_dinamis):\t1.020.375,4 M\n";
echo "Dashboard date 19 (dari dashboard snapshot):\t1.172.402,99 M\n"; 
echo "DISCREPANCY:\t\t\t\t152.027,6 M\n\n";

// Check the snapshot calculation
echo "1. INVESTIGATING SNAPSHOT CALCULATION:\n";
echo str_repeat("-", 80) . "\n";

// Check if there's a view or stored procedure
$snapshots = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', '2026-04-19')
    ->where('kanca_label', 'KC MADIUN')
    ->selectRaw('
        unit_label,
        briguna_konsumer_os,
        kpr_os,
        briguna_konsumer_os + kpr_os as total
    ')
    ->orderBy('unit_label')
    ->get();

echo "Unit-level breakdown in dashboard_harian_snapshots:\n";
foreach ($snapshots as $row) {
    echo "  " . str_pad($row->unit_label ?? 'KC MADIUN', 25) . " | Briguna: " . str_pad(number_format($row->briguna_konsumer_os / 1_000_000, 1, ',', '.') . ' M', 12) . " | KPR: " . str_pad(number_format($row->kpr_os / 1_000_000, 1, ',', '.') . ' M', 12) . " | Total: " . number_format($row->total / 1_000_000, 1, ',', '.') . ' M' . "\n";
}

// Check for RKA data
echo "\n2. CHECKING RKA DATA (Budget/Planning):\n";
echo str_repeat("-", 80) . "\n";

// Check if RKA is being added to the snapshot
$rkaData = DB::table('rka')
    ->where('kanca', 'KC Madiun')
    ->selectRaw("
        mata_anggaran,
        SUM(apr) as total_apr
    ")
    ->groupBy('mata_anggaran')
    ->orderByDesc('total_apr')
    ->get();

$brigunaRka = 0;
$kprRka = 0;

foreach ($rkaData as $row) {
    $desc = $row->mata_anggaran ?? '';
    if (strpos($desc, 'Briguna') !== false) {
        $brigunaRka += $row->total_apr ?? 0;
    }
    if (strpos($desc, 'KPR') !== false) {
        $kprRka += $row->total_apr ?? 0;
    }
}

echo "RKA Briguna (KC Madiun, all months):\t" . number_format($brigunaRka / 1_000_000, 1, ',', '.') . " M\n";
echo "RKA KPR (KC Madiun, all months):\t\t" . number_format($kprRka / 1_000_000, 1, ',', '.') . " M\n";

// Check if source_rka or another table is involved
echo "\n3. CHECKING SSA_PINJAMAN (Source Reality):\n";
echo str_repeat("-", 80) . "\n";

$ssaPinjaman = DB::table('ssa_pinjaman')
    ->where('month_day_year_of_periode', '2026-04-19')
    ->where('nama_cabang', 'LIKE', '%MADIUN%')
    ->selectRaw("
        produk_dashboard,
        COUNT(*) as count,
        SUM(baki_debet) as total_os
    ")
    ->groupBy('produk_dashboard')
    ->orderByDesc('total_os')
    ->get();

$ssaBriguna = 0;
$ssaKpr = 0;

foreach ($ssaPinjaman as $row) {
    $prod = $row->produk_dashboard ?? 'Unknown';
    $os = $row->total_os ?? 0;
    
    echo str_pad($prod, 30) . " | Count: " . str_pad((string)$row->count, 6) . " | OS: " . str_pad(number_format($os / 1_000_000, 1, ',', '.') . ' M', 12) . "\n";
    
    if (strpos(strtoupper($prod), 'BRIGUNA') !== false) {
        $ssaBriguna += $os;
    }
    if (strpos(strtoupper($prod), 'KPR') !== false) {
        $ssaKpr += $os;
    }
}

echo "\nSSA Pinjaman Briguna Total: " . number_format($ssaBriguna / 1_000_000, 1, ',', '.') . " M\n";
echo "SSA Pinjaman KPR Total:     " . number_format($ssaKpr / 1_000_000, 1, ',', '.') . " M\n";

// Now compare all three sources
echo "\n4. COMPARISON: THREE DATA SOURCES (KC MADIUN)\n";
echo str_repeat("-", 80) . "\n";

$dailyLoan = DB::table('daily_loan_dinamis')
    ->where('periode', '2026-04-19')
    ->where('cabang1', 'LIKE', '%MADIUN%')
    ->whereRaw("UPPER(TRIM(segmen_dashboard)) = 'CONSUMER'")
    ->selectRaw("
        SUM(CASE WHEN produk_dashboard LIKE '%BRIGUNA%' THEN baki_debet1 ELSE 0 END) as briguna,
        SUM(CASE WHEN produk_dashboard LIKE '%KPR%' THEN baki_debet1 ELSE 0 END) as kpr,
        SUM(baki_debet1) as total
    ")
    ->first();

echo "Source 1: daily_loan_dinamis:\n";
echo "  Briguna: " . number_format(($dailyLoan->briguna ?? 0) / 1_000_000, 1, ',', '.') . " M\n";
echo "  KPR:     " . number_format(($dailyLoan->kpr ?? 0) / 1_000_000, 1, ',', '.') . " M\n";
echo "  TOTAL:   " . number_format(($dailyLoan->total ?? 0) / 1_000_000, 1, ',', '.') . " M\n\n";

echo "Source 2: ssa_pinjaman:\n";
echo "  Briguna: " . number_format($ssaBriguna / 1_000_000, 1, ',', '.') . " M\n";
echo "  KPR:     " . number_format($ssaKpr / 1_000_000, 1, ',', '.') . " M\n";
echo "  TOTAL:   " . number_format(($ssaBriguna + $ssaKpr) / 1_000_000, 1, ',', '.') . " M\n\n";

echo "Source 3: dashboard_harian_snapshots:\n";
$dashboardKcMadiun = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', '2026-04-19')
    ->where('kanca_label', 'KC MADIUN')
    ->selectRaw("
        SUM(briguna_konsumer_os) as briguna,
        SUM(kpr_os) as kpr,
        SUM(briguna_konsumer_os + kpr_os) as total
    ")
    ->first();

echo "  Briguna: " . number_format(($dashboardKcMadiun->briguna ?? 0) / 1_000_000, 1, ',', '.') . " M\n";
echo "  KPR:     " . number_format(($dashboardKcMadiun->kpr ?? 0) / 1_000_000, 1, ',', '.') . " M\n";
echo "  TOTAL:   " . number_format(($dashboardKcMadiun->total ?? 0) / 1_000_000, 1, ',', '.') . " M\n\n";

// Calculate differences
$dailyTotal = $dailyLoan->total ?? 0;
$ssaTotal = $ssaBriguna + $ssaKpr;
$dashTotal = $dashboardKcMadiun->total ?? 0;

echo "DIFFERENCES:\n";
echo "  Dashboard vs Daily_Loan_Dinamis: " . number_format(($dashTotal - $dailyTotal) / 1_000_000, 1, ',', '.') . " M\n";
echo "  Dashboard vs SSA_Pinjaman:       " . number_format(($dashTotal - $ssaTotal) / 1_000_000, 1, ',', '.') . " M\n";
echo "  Daily_Loan vs SSA_Pinjaman:      " . number_format(($dailyTotal - $ssaTotal) / 1_000_000, 1, ',', '.') . " M\n";

// Check if loans are being counted multiple times
echo "\n5. CHECKING FOR DUPLICATE RECORDS:\n";
echo str_repeat("-", 80) . "\n";

$dailyCount = DB::table('daily_loan_dinamis')
    ->where('periode', '2026-04-19')
    ->where('cabang1', 'LIKE', '%MADIUN%')
    ->whereRaw("UPPER(TRIM(segmen_dashboard)) = 'CONSUMER'")
    ->count();

$ssaCount = DB::table('ssa_pinjaman')
    ->where('month_day_year_of_periode', '2026-04-19')
    ->where('nama_cabang', 'LIKE', '%MADIUN%')
    ->count();

echo "Loan count in daily_loan_dinamis: " . $dailyCount . "\n";
echo "Loan count in ssa_pinjaman:       " . $ssaCount . "\n";

if ($dailyCount > $ssaCount) {
    echo "\n⚠️  daily_loan_dinamis has MORE records (possible duplication or filtering issue)\n";
} elseif ($ssaCount > $dailyCount) {
    echo "\n⚠️  ssa_pinjaman has MORE records (daily_loan_dinamis may have filters applied)\n";
}

?>
