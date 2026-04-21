<?php
include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== DETAILED INVESTIGATION: WHERE IS THE 60M DIFFERENCE? ===\n";
echo "Date: 2026-04-19\n\n";

// Check for missing/NULL values
echo "1. CHECK NULL VALUES IN SEGMEN_DASHBOARD:\n";
echo str_repeat("-", 80) . "\n";

$nullSegmen = DB::table('daily_loan_dinamis')
    ->where('periode', '2026-04-19')
    ->whereNull('segmen_dashboard')
    ->orWhere('segmen_dashboard', '')
    ->sum('baki_debet1');

echo "Data with NULL/empty segmen_dashboard:\t" . number_format($nullSegmen / 1_000_000, 2, ',', '.') . " M\n";

$nullProduk = DB::table('daily_loan_dinamis')
    ->where('periode', '2026-04-19')
    ->whereRaw("UPPER(TRIM(segmen_dashboard)) = 'CONSUMER'")
    ->whereNull('produk_dashboard')
    ->orWhere('produk_dashboard', '')
    ->sum('baki_debet1');

echo "CONSUMER data with NULL/empty produk_dashboard:\t" . number_format($nullProduk / 1_000_000, 2, ',', '.') . " M\n";

// Check all CONSUMER data including unrecognized products
echo "\n2. ALL CONSUMER DATA (regardless of produk_dashboard):\n";
echo str_repeat("-", 80) . "\n";

$allConsumer = DB::table('daily_loan_dinamis')
    ->where('periode', '2026-04-19')
    ->whereRaw("UPPER(TRIM(segmen_dashboard)) = 'CONSUMER'")
    ->sum('baki_debet1');

echo "Total CONSUMER segment (all products):\t" . number_format($allConsumer / 1_000_000, 2, ',', '.') . " M\n";

// Check by exact product match
echo "\n3. EXACT PRODUCT MATCHING:\n";
echo str_repeat("-", 80) . "\n";

$exactProducts = DB::table('daily_loan_dinamis')
    ->where('periode', '2026-04-19')
    ->whereRaw("UPPER(TRIM(segmen_dashboard)) = 'CONSUMER'")
    ->selectRaw("
        produk_dashboard,
        COUNT(*) as count,
        SUM(baki_debet1) as total_os
    ")
    ->groupBy('produk_dashboard')
    ->orderByDesc('total_os')
    ->get();

foreach ($exactProducts as $row) {
    $prod = $row->produk_dashboard ?? '[NULL]';
    echo str_pad($prod, 40) . " | Count: " . str_pad((string)$row->count, 6) . " | OS: " . str_pad(number_format($row->total_os / 1_000_000, 2, ',', '.') . ' M', 15) . "\n";
}

// Check if BRIGUNA-KONSUMER is being matched correctly
echo "\n4. BRIGUNA PRODUCT MATCHING ANALYSIS:\n";
echo str_repeat("-", 80) . "\n";

$brigunaVariations = DB::table('daily_loan_dinamis')
    ->where('periode', '2026-04-19')
    ->whereRaw("UPPER(TRIM(segmen_dashboard)) = 'CONSUMER'")
    ->whereRaw("UPPER(TRIM(COALESCE(produk_dashboard, ''))) LIKE '%BRIGUNA%'")
    ->selectRaw("
        produk_dashboard,
        COUNT(*) as count,
        SUM(baki_debet1) as total_os
    ")
    ->groupBy('produk_dashboard')
    ->orderByDesc('total_os')
    ->get();

foreach ($brigunaVariations as $row) {
    $prod = $row->produk_dashboard ?? '[NULL]';
    echo str_pad($prod, 40) . " | Count: " . str_pad((string)$row->count, 6) . " | OS: " . str_pad(number_format($row->total_os / 1_000_000, 2, ',', '.') . ' M', 15) . "\n";
}

// Check per-cabang breakdown from source
echo "\n5. SOURCE DATA BREAKDOWN PER CABANG:\n";
echo str_repeat("-", 80) . "\n";

$cabangSource = DB::table('daily_loan_dinamis')
    ->where('periode', '2026-04-19')
    ->whereRaw("UPPER(TRIM(segmen_dashboard)) = 'CONSUMER'")
    ->selectRaw("
        cabang1,
        SUM(CASE WHEN produk_dashboard LIKE '%BRIGUNA%' THEN baki_debet1 ELSE 0 END) as briguna_os,
        SUM(CASE WHEN produk_dashboard LIKE '%KPR%' THEN baki_debet1 ELSE 0 END) as kpr_os,
        SUM(baki_debet1) as total_os,
        COUNT(*) as count_records
    ")
    ->groupBy('cabang1')
    ->orderBy('cabang1')
    ->get();

foreach ($cabangSource as $row) {
    $cabang = $row->cabang1 ?? 'Unknown';
    echo str_pad($cabang, 20) . " | Briguna: " . str_pad(number_format($row->briguna_os / 1_000_000, 2, ',', '.') . ' M', 15);
    echo " | KPR: " . str_pad(number_format($row->kpr_os / 1_000_000, 2, ',', '.') . ' M', 15);
    echo " | Total: " . number_format($row->total_os / 1_000_000, 2, ',', '.') . ' M' . "\n";
}

// Compare with dashboard
echo "\n6. COMPARISON: SOURCE vs DASHBOARD\n";
echo str_repeat("-", 80) . "\n";

$cabangDash = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', '2026-04-19')
    ->selectRaw("
        kanca_label,
        SUM(briguna_konsumer_os) as briguna_os,
        SUM(kpr_os) as kpr_os,
        SUM(briguna_konsumer_os + kpr_os) as total_os
    ")
    ->groupBy('kanca_label')
    ->orderBy('kanca_label')
    ->get();

echo "BRANCH                | Source Briguna | Source KPR     | Dashboard Briguna | Dashboard KPR  | Diff Briguna | Diff KPR\n";
echo str_repeat("-", 130) . "\n";

$sourceCabangMap = [];
foreach ($cabangSource as $row) {
    $key = strtoupper(trim((string)$row->cabang1));
    $sourceCabangMap[$key] = [
        'briguna' => $row->briguna_os ?? 0,
        'kpr' => $row->kpr_os ?? 0
    ];
}

$totalBrigDiff = 0;
$totalKprDiff = 0;

foreach ($cabangDash as $row) {
    $branch = $row->kanca_label ?? 'Unknown';
    $branchKey = strtoupper(trim($branch));
    
    $sourceBrig = $sourceCabangMap[$branchKey]['briguna'] ?? 0;
    $sourceKpr = $sourceCabangMap[$branchKey]['kpr'] ?? 0;
    
    $dashBrig = $row->briguna_os ?? 0;
    $dashKpr = $row->kpr_os ?? 0;
    
    $diffBrig = $dashBrig - $sourceBrig;
    $diffKpr = $dashKpr - $sourceKpr;
    
    $totalBrigDiff += $diffBrig;
    $totalKprDiff += $diffKpr;
    
    echo str_pad($branch, 20) . " | " 
        . str_pad(number_format($sourceBrig / 1_000_000, 1, ',', '.') . ' M', 14) . " | "
        . str_pad(number_format($sourceKpr / 1_000_000, 1, ',', '.') . ' M', 14) . " | "
        . str_pad(number_format($dashBrig / 1_000_000, 1, ',', '.') . ' M', 18) . " | "
        . str_pad(number_format($dashKpr / 1_000_000, 1, ',', '.') . ' M', 14) . " | "
        . str_pad(number_format($diffBrig / 1_000_000, 1, ',', '.') . ' M', 12) . " | "
        . number_format($diffKpr / 1_000_000, 1, ',', '.') . ' M'
        . "\n";
}

echo str_repeat("-", 130) . "\n";
echo "TOTAL DIFFERENCE: Briguna = " . number_format($totalBrigDiff / 1_000_000, 1, ',', '.') . " M | KPR = " . number_format($totalKprDiff / 1_000_000, 1, ',', '.') . " M\n";
echo "COMBINED DIFFERENCE = " . number_format(($totalBrigDiff + $totalKprDiff) / 1_000_000, 1, ',', '.') . " M\n";

?>
