<?php
include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

echo "=== ANALISIS DISCREPANCY KONSUMER OUTSTANDING (BRIGUNA & KPR) ===\n";
echo "Tanggal: 19 April 2026\n\n";

$checkDate = '2026-04-19';
$periodLabel = '2026-04-19';

// 1. Dari dashboard_harian_snapshots (Dashboard Pinjaman Kredit)
echo "1. DATA DARI DASHBOARD HARIAN SNAPSHOTS (Dashboard Pinjaman Kredit):\n";
echo str_repeat("-", 80) . "\n";

$dashboardData = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $periodLabel)
    ->selectRaw('
        SUM(briguna_konsumer_os) as briguna_os,
        SUM(kpr_os) as kpr_os,
        SUM(briguna_konsumer_os + kpr_os) as total_konsumer_os
    ')
    ->first();

if ($dashboardData) {
    $briguna_dashboard = $dashboardData->briguna_os ?? 0;
    $kpr_dashboard = $dashboardData->kpr_os ?? 0;
    $total_dashboard = $dashboardData->total_konsumer_os ?? 0;
    
    echo "Briguna:\t\t" . number_format($briguna_dashboard, 2, ',', '.') . " (Rp " . number_format($briguna_dashboard / 1_000_000, 2, ',', '.') . " M)\n";
    echo "KPR:\t\t" . number_format($kpr_dashboard, 2, ',', '.') . " (Rp " . number_format($kpr_dashboard / 1_000_000, 2, ',', '.') . " M)\n";
    echo "TOTAL DASHBOARD:\t" . number_format($total_dashboard, 2, ',', '.') . " (Rp " . number_format($total_dashboard / 1_000_000, 2, ',', '.') . " M)\n";
} else {
    echo "❌ NO DATA IN SNAPSHOT\n";
}

// 2. Dari daily_loan_dinamis (Source data)
echo "\n2. DATA DARI DAILY_LOAN_DINAMIS (Source):\n";
echo str_repeat("-", 80) . "\n";

$sourceData = DB::table('daily_loan_dinamis')
    ->where('periode', $periodLabel)
    ->whereRaw("UPPER(TRIM(segmen_dashboard)) = 'CONSUMER'")
    ->selectRaw('
        produk_dashboard,
        COUNT(*) as count_records,
        SUM(COALESCE(baki_debet1, 0)) as total_os
    ')
    ->groupBy('produk_dashboard')
    ->orderBy('produk_dashboard')
    ->get();

$briguna_source = 0;
$kpr_source = 0;
$total_source = 0;

foreach ($sourceData as $row) {
    $product = trim((string) $row->produk_dashboard);
    $os = $row->total_os ?? 0;
    $count = $row->count_records ?? 0;
    
    echo "Product: " . str_pad($product ?? 'NULL', 30) . " | Count: " . str_pad((string) $count, 6) . " | OS: " . number_format($os / 1_000_000, 2, ',', '.') . " M\n";
    
    if (strpos(strtoupper($product ?? ''), 'BRIGUNA') !== false) {
        $briguna_source += $os;
    }
    if (strpos(strtoupper($product ?? ''), 'KPR') !== false) {
        $kpr_source += $os;
    }
    $total_source += $os;
}

echo "\nSummary from daily_loan_dinamis:\n";
echo "Briguna (all BRIGUNA products):\t" . number_format($briguna_source / 1_000_000, 2, ',', '.') . " M\n";
echo "KPR (all KPR products):\t\t" . number_format($kpr_source / 1_000_000, 2, ',', '.') . " M\n";
echo "TOTAL SOURCE:\t\t\t" . number_format($total_source / 1_000_000, 2, ',', '.') . " M\n";

// 3. Analisis detail product_dashboard
echo "\n3. DETAIL PRODUK_DASHBOARD (All CONSUMER segment):\n";
echo str_repeat("-", 80) . "\n";

$detailProducts = DB::table('daily_loan_dinamis')
    ->where('periode', $periodLabel)
    ->whereRaw("UPPER(TRIM(segmen_dashboard)) = 'CONSUMER'")
    ->selectRaw('
        produk_dashboard,
        COUNT(DISTINCT CASE WHEN produk_dashboard IS NOT NULL THEN 1 END) as has_value,
        SUM(COALESCE(baki_debet1, 0)) as total_os,
        COUNT(DISTINCT cifno) as unique_debtors
    ')
    ->groupBy('produk_dashboard')
    ->orderByDesc('total_os')
    ->get();

foreach ($detailProducts as $row) {
    $product = $row->produk_dashboard ?? '[NULL]';
    $os = $row->total_os ?? 0;
    echo "  " . str_pad($product, 40) . " | OS: " . str_pad(number_format($os / 1_000_000, 2, ',', '.') . ' M', 20) . " | Debtors: " . $row->unique_debtors . "\n";
}

// 4. Discrepancy Analysis
echo "\n4. ANALISIS DISCREPANCY:\n";
echo str_repeat("-", 80) . "\n";

$diff = $total_dashboard - $total_source;
$diff_pct = $total_source > 0 ? ($diff / $total_source * 100) : 0;

echo "Total Dashboard:\t" . number_format($total_dashboard / 1_000_000, 2, ',', '.') . " M\n";
echo "Total Source:\t\t" . number_format($total_source / 1_000_000, 2, ',', '.') . " M\n";
echo "PERBEDAAN:\t\t" . number_format($diff / 1_000_000, 2, ',', '.') . " M (" . number_format($diff_pct, 2, ',', '.') . "%)\n";

if (abs($diff) > 100_000_000) {
    echo "\n⚠️  PERBEDAAN SIGNIFIKAN DITEMUKAN!\n";
}

// 5. Check data integrity
echo "\n5. DATA INTEGRITY CHECK:\n";
echo str_repeat("-", 80) . "\n";

// Check if snapshot was properly built
$snapshotBriguna = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $periodLabel)
    ->sum('briguna_konsumer_os');

$snapshotKpr = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $periodLabel)
    ->sum('kpr_os');

echo "Snapshot briguna_konsumer_os:\t" . number_format($snapshotBriguna / 1_000_000, 2, ',', '.') . " M (from all branches)\n";
echo "Snapshot kpr_os:\t\t" . number_format($snapshotKpr / 1_000_000, 2, ',', '.') . " M (from all branches)\n";

// 6. Per-branch breakdown
echo "\n6. BREAKDOWN PER CABANG (Dashboard Snapshots):\n";
echo str_repeat("-", 80) . "\n";

$perBranch = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $periodLabel)
    ->whereNotNull('kanca_label')
    ->selectRaw('
        kanca_label,
        SUM(briguna_konsumer_os) as briguna_os,
        SUM(kpr_os) as kpr_os,
        SUM(briguna_konsumer_os + kpr_os) as total_os
    ')
    ->groupBy('kanca_label')
    ->orderBy('kanca_label')
    ->get();

foreach ($perBranch as $row) {
    $branch = $row->kanca_label ?? 'Unknown';
    $briguna = $row->briguna_os ?? 0;
    $kpr = $row->kpr_os ?? 0;
    $total = $row->total_os ?? 0;
    
    echo str_pad($branch, 30) . " | Briguna: " . str_pad(number_format($briguna / 1_000_000, 2, ',', '.') . ' M', 15);
    echo " | KPR: " . str_pad(number_format($kpr / 1_000_000, 2, ',', '.') . ' M', 15);
    echo " | Total: " . number_format($total / 1_000_000, 2, ',', '.') . ' M' . "\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
?>
