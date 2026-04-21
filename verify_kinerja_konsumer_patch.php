<?php
include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== VERIFY KINERJA KONSUMER DATA - 2026-04-19 ===\n\n";

$periode = '2026-04-19';
$selectedCabang = 'KC MADIUN';

echo "Periode: $periode\n";
echo "Selected Cabang: $selectedCabang\n";
echo "Expected: Include KC MADIUN + sub-units (KCP Caruban, etc)\n\n";

// Check with NEW pattern matching logic
echo "1. QUERY WITH NEW PATTERN MATCHING (extractBranchPattern):\n";
echo str_repeat("-", 100) . "\n";

$branchPattern = strtoupper(trim($selectedCabang));
$parts = explode(' ', $branchPattern);
if (count($parts) >= 2) {
    $branchPattern = $parts[count($parts) - 1];
}

echo "Pattern to match: %{$branchPattern}%\n\n";

$newQuery = DB::table('daily_loan_dinamis')
    ->where('periode', $periode)
    ->whereRaw("UPPER(TRIM(cabang1)) LIKE ?", ["%{$branchPattern}%"])
    ->whereRaw("UPPER(TRIM(segmen_dashboard)) = 'CONSUMER'")
    ->selectRaw("
        cabang1,
        SUM(CASE WHEN UPPER(TRIM(produk_dashboard)) LIKE '%BRIGUNA%' THEN baki_debet1 ELSE 0 END) as briguna_os,
        SUM(CASE WHEN UPPER(TRIM(produk_dashboard)) LIKE '%KPR%' THEN baki_debet1 ELSE 0 END) as kpr_os,
        SUM(baki_debet1) as total_os,
        COUNT(*) as count_records
    ")
    ->groupBy('cabang1')
    ->orderBy('cabang1')
    ->get();

foreach ($newQuery as $row) {
    echo "  " . str_pad((string)$row->cabang1, 30) 
        . " | Briguna: " . str_pad(number_format($row->briguna_os / 1_000_000, 1, ',', '.') . ' M', 12)
        . " | KPR: " . str_pad(number_format($row->kpr_os / 1_000_000, 1, ',', '.') . ' M', 12)
        . " | Total: " . str_pad(number_format($row->total_os / 1_000_000, 1, ',', '.') . ' M', 12)
        . " | Records: " . $row->count_records . "\n";
}

// Calculate total
$totalWithPattern = DB::table('daily_loan_dinamis')
    ->where('periode', $periode)
    ->whereRaw("UPPER(TRIM(cabang1)) LIKE ?", ["%{$branchPattern}%"])
    ->whereRaw("UPPER(TRIM(segmen_dashboard)) = 'CONSUMER'")
    ->selectRaw("
        SUM(CASE WHEN UPPER(TRIM(produk_dashboard)) LIKE '%BRIGUNA%' THEN baki_debet1 ELSE 0 END) as briguna_total,
        SUM(CASE WHEN UPPER(TRIM(produk_dashboard)) LIKE '%KPR%' THEN baki_debet1 ELSE 0 END) as kpr_total,
        SUM(baki_debet1) as grand_total,
        COUNT(*) as total_records
    ")
    ->first();

echo "\nTOTAL (with new pattern):\n";
echo "  Briguna: " . number_format($totalWithPattern->briguna_total / 1_000_000, 1, ',', '.') . " M\n";
echo "  KPR: " . number_format($totalWithPattern->kpr_total / 1_000_000, 1, ',', '.') . " M\n";
echo "  TOTAL: " . number_format($totalWithPattern->grand_total / 1_000_000, 1, ',', '.') . " M\n";
echo "  Records: " . $totalWithPattern->total_records . "\n\n";

// Compare with expected (dari dashboard harian)
echo "2. COMPARISON WITH DASHBOARD HARIAN SNAPSHOTS:\n";
echo str_repeat("-", 100) . "\n";

$dashboardData = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $periode)
    ->whereRaw("UPPER(kanca_label) LIKE ?", ["%MADIUN%"])
    ->selectRaw("
        SUM(briguna_konsumer_os) as briguna,
        SUM(kpr_os) as kpr,
        SUM(briguna_konsumer_os + kpr_os) as total
    ")
    ->first();

echo "Dashboard value:\n";
echo "  Briguna: " . number_format($dashboardData->briguna / 1_000_000, 1, ',', '.') . " M\n";
echo "  KPR: " . number_format($dashboardData->kpr / 1_000_000, 1, ',', '.') . " M\n";
echo "  TOTAL: " . number_format($dashboardData->total / 1_000_000, 1, ',', '.') . " M\n\n";

echo "3. VARIANCE CHECK:\n";
echo str_repeat("-", 100) . "\n";

$diffBriguna = $dashboardData->briguna - $totalWithPattern->briguna_total;
$diffKpr = $dashboardData->kpr - $totalWithPattern->kpr_total;
$diffTotal = $dashboardData->total - $totalWithPattern->grand_total;

$varBriguna = $totalWithPattern->briguna_total > 0 ? ($diffBriguna / $totalWithPattern->briguna_total * 100) : 0;
$varKpr = $totalWithPattern->kpr_total > 0 ? ($diffKpr / $totalWithPattern->kpr_total * 100) : 0;
$varTotal = $totalWithPattern->grand_total > 0 ? ($diffTotal / $totalWithPattern->grand_total * 100) : 0;

echo "Briguna variance: " . number_format($varBriguna, 2, ',', '.') . "% (diff: " . number_format($diffBriguna / 1_000_000, 1, ',', '.') . " M)\n";
echo "KPR variance: " . number_format($varKpr, 2, ',', '.') . "% (diff: " . number_format($diffKpr / 1_000_000, 1, ',', '.') . " M)\n";
echo "Total variance: " . number_format($varTotal, 2, ',', '.') . "% (diff: " . number_format($diffTotal / 1_000_000, 1, ',', '.') . " M)\n\n";

// Check if matches
$threshold = 5; // 5% acceptable threshold
if (abs($varTotal) <= $threshold) {
    echo "✅ DATA MATCHES! Variance within acceptable threshold (" . $threshold . "%)\n";
    echo "No rebuild needed. New patch is working correctly.\n";
} else {
    echo "⚠️  DATA MISMATCH! Variance exceeds threshold.\n";
    echo "May need to rebuild or investigate further.\n";
}

// Check if there are any recent builds
echo "\n4. CHECKING SNAPSHOT BUILD STATUS:\n";
echo str_repeat("-", 100) . "\n";

$latestSnapshot = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $periode)
    ->max('created_at');

echo "Latest snapshot build for $periode: " . $latestSnapshot . "\n";

// Check when the new code was deployed (roughly)
echo "\nTo rebuild snapshots, run:\n";
echo "  php artisan dashboard:rebuild-snapshots --date=$periode\n";
echo "or\n";
echo "  php artisan dashboard:rebuild-snapshots --date=$periode --force\n";

?>
