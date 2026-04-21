<?php
include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== INVESTIGATING BRANCH STRUCTURE IN daily_loan_dinamis ===\n\n";

$periode = '2026-04-19';

echo "Periode: $periode\n";
echo "Checking all unique cabang1 values for CONSUMER segment:\n\n";

$branches = DB::table('daily_loan_dinamis')
    ->where('periode', $periode)
    ->whereRaw("UPPER(TRIM(segmen_dashboard)) = 'CONSUMER'")
    ->selectRaw("
        DISTINCT cabang1,
        COUNT(*) as count_records,
        SUM(baki_debet1) as total_os
    ")
    ->groupBy('cabang1')
    ->orderBy('cabang1')
    ->get();

echo "Branch | Records | Total OS\n";
echo str_repeat("-", 80) . "\n";

foreach ($branches as $row) {
    echo str_pad((string)$row->cabang1, 30) 
        . " | " . str_pad((string)$row->count_records, 7)
        . " | " . number_format($row->total_os / 1_000_000, 1, ',', '.') . " M\n";
}

// Now check dashboard structure
echo "\n\nCOMPARE WITH dashboard_harian_snapshots:\n";
echo "Checking unique kanca_label + unit_label combinations:\n\n";

$dashboardBranches = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $periode)
    ->where('unit_label', '!=', DB::raw('kanca_label'))  // Show only where unit is different
    ->selectRaw("
        DISTINCT kanca_label,
        unit_label,
        SUM(briguna_konsumer_os + kpr_os) as total_os
    ")
    ->groupBy('kanca_label', 'unit_label')
    ->orderBy('kanca_label')
    ->orderBy('unit_label')
    ->get();

echo "Kanca | Unit | Total OS\n";
echo str_repeat("-", 80) . "\n";

foreach ($dashboardBranches as $row) {
    if (strpos(strtoupper($row->kanca_label), 'MADIUN') !== false) {
        echo str_pad((string)$row->kanca_label, 20) 
            . " | " . str_pad((string)$row->unit_label, 30)
            . " | " . number_format($row->total_os / 1_000_000, 1, ',', '.') . " M\n";
    }
}

// Check if KCP Caruban is a separate branch in daily_loan
echo "\n\nKCP CARUBAN Details:\n";
echo str_repeat("-", 80) . "\n";

$kcpCaruban = DB::table('daily_loan_dinamis')
    ->where('periode', $periode)
    ->where('cabang1', 'LIKE', '%Caruban%')
    ->whereRaw("UPPER(TRIM(segmen_dashboard)) = 'CONSUMER'")
    ->selectRaw("
        cabang1,
        COUNT(*) as count_records,
        SUM(CASE WHEN UPPER(TRIM(produk_dashboard)) LIKE '%BRIGUNA%' THEN baki_debet1 ELSE 0 END) as briguna_os,
        SUM(CASE WHEN UPPER(TRIM(produk_dashboard)) LIKE '%KPR%' THEN baki_debet1 ELSE 0 END) as kpr_os,
        SUM(baki_debet1) as total_os
    ")
    ->groupBy('cabang1')
    ->first();

if ($kcpCaruban) {
    echo "Found KCP Caruban:\n";
    echo "  Briguna: " . number_format($kcpCaruban->briguna_os / 1_000_000, 1, ',', '.') . " M\n";
    echo "  KPR: " . number_format($kcpCaruban->kpr_os / 1_000_000, 1, ',', '.') . " M\n";
    echo "  Total: " . number_format($kcpCaruban->total_os / 1_000_000, 1, ',', '.') . " M\n";
    echo "  Records: " . $kcpCaruban->count_records . "\n";
} else {
    echo "No KCP Caruban found in daily_loan_dinamis for this date\n";
}

// Check dashboard total for KC Madiun region
echo "\n\nDashboard Total (KC Madiun + all sub-units):\n";
echo str_repeat("-", 80) . "\n";

$dashboardTotal = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $periode)
    ->whereRaw("UPPER(kanca_label) LIKE '%MADIUN%'")
    ->selectRaw("
        SUM(briguna_konsumer_os) as briguna,
        SUM(kpr_os) as kpr,
        SUM(briguna_konsumer_os + kpr_os) as total
    ")
    ->first();

echo "Total: " . number_format($dashboardTotal->total / 1_000_000, 1, ',', '.') . " M\n";
echo "  - Briguna: " . number_format($dashboardTotal->briguna / 1_000_000, 1, ',', '.') . " M\n";
echo "  - KPR: " . number_format($dashboardTotal->kpr / 1_000_000, 1, ',', '.') . " M\n";

echo "\n\n=== CONCLUSION ===\n";
echo "If KCP Caruban is separate branch in daily_loan_dinamis,\n";
echo "need to add it explicitly to the filter when selecting KC Madiun.\n";
?>
