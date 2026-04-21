<?php
/**
 * Monitoring Script: Consumer Outstanding Data Consistency Check
 * Checks for discrepancies between data sources and alerts if variance > threshold
 */

include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

// Configuration
define('VARIANCE_THRESHOLD_PCT', 5); // Alert if variance > 5%
define('MIN_REQUIRED_RECORDS_RATIO', 0.8); // daily_loan should have at least 80% of ssa_pinjaman records

echo "=== CONSUMER OUTSTANDING DATA CONSISTENCY MONITOR ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "Threshold: " . VARIANCE_THRESHOLD_PCT . "% variance, min record ratio: " . (MIN_REQUIRED_RECORDS_RATIO * 100) . "%\n\n";

$latestDate = DB::table('ssa_pinjaman')
    ->max('month_day_year_of_periode');

echo "Checking data for: $latestDate\n";
echo str_repeat("-", 80) . "\n\n";

// Check each cabang
$cabangs = DB::table('ssa_pinjaman')
    ->where('month_day_year_of_periode', $latestDate)
    ->whereRaw("UPPER(TRIM(segmen_dashboard)) = 'CONSUMER'")
    ->distinct()
    ->pluck('nama_cabang');

$issues = [];

foreach ($cabangs as $cabang) {
    // SSA Pinjaman (Single source of truth)
    $ssaData = DB::table('ssa_pinjaman')
        ->where('month_day_year_of_periode', $latestDate)
        ->where('nama_cabang', $cabang)
        ->whereRaw("UPPER(TRIM(segmen_dashboard)) = 'CONSUMER'")
        ->selectRaw("
            SUM(CASE WHEN produk_dashboard LIKE '%BRIGUNA%' THEN baki_debet ELSE 0 END) as briguna,
            SUM(CASE WHEN produk_dashboard LIKE '%KPR%' THEN baki_debet ELSE 0 END) as kpr,
            SUM(baki_debet) as total,
            COUNT(*) as count_records
        ")
        ->first();

    // Daily Loan Dinamis
    $dailyData = DB::table('daily_loan_dinamis')
        ->where('periode', $latestDate)
        ->where('cabang1', 'LIKE', "%$cabang%")
        ->whereRaw("UPPER(TRIM(segmen_dashboard)) = 'CONSUMER'")
        ->selectRaw("
            SUM(CASE WHEN produk_dashboard LIKE '%BRIGUNA%' THEN baki_debet1 ELSE 0 END) as briguna,
            SUM(CASE WHEN produk_dashboard LIKE '%KPR%' THEN baki_debet1 ELSE 0 END) as kpr,
            SUM(baki_debet1) as total,
            COUNT(*) as count_records
        ")
        ->first();

    // Dashboard Snapshots
    $dashboardData = DB::table('dashboard_harian_snapshots')
        ->where('snapshot_period', $latestDate)
        ->whereRaw("UPPER(kanca_label) = ?", [strtoupper($cabang)])
        ->selectRaw("
            SUM(briguna_konsumer_os) as briguna,
            SUM(kpr_os) as kpr,
            SUM(briguna_konsumer_os + kpr_os) as total
        ")
        ->first();

    // Calculate variances
    $ssaBriguna = $ssaData->briguna ?? 0;
    $ssaKpr = $ssaData->kpr ?? 0;
    $ssaTotal = $ssaData->total ?? 0;
    $ssaRecords = $ssaData->count_records ?? 0;

    $dailyBriguna = $dailyData->briguna ?? 0;
    $dailyKpr = $dailyData->kpr ?? 0;
    $dailyTotal = $dailyData->total ?? 0;
    $dailyRecords = $dailyData->count_records ?? 0;

    $dashBriguna = $dashboardData->briguna ?? 0;
    $dashKpr = $dashboardData->kpr ?? 0;
    $dashTotal = $dashboardData->total ?? 0;

    // Variance calculations
    $varDailyVsSsa = $ssaTotal > 0 ? abs(($dailyTotal - $ssaTotal) / $ssaTotal * 100) : 0;
    $varDashVsSsa = $ssaTotal > 0 ? abs(($dashTotal - $ssaTotal) / $ssaTotal * 100) : 0;
    $recordRatio = $ssaRecords > 0 ? ($dailyRecords / $ssaRecords) : 1;

    // Check for issues
    $cabangIssues = [];

    if ($varDailyVsSsa > VARIANCE_THRESHOLD_PCT) {
        $cabangIssues[] = "⚠️  daily_loan variance: " . number_format($varDailyVsSsa, 1, ',', '.') . "% (threshold: " . VARIANCE_THRESHOLD_PCT . "%)";
    }

    if ($varDashVsSsa > VARIANCE_THRESHOLD_PCT) {
        $cabangIssues[] = "⚠️  dashboard variance: " . number_format($varDashVsSsa, 1, ',', '.') . "% (threshold: " . VARIANCE_THRESHOLD_PCT . "%)";
    }

    if ($recordRatio < MIN_REQUIRED_RECORDS_RATIO) {
        $cabangIssues[] = "⚠️  record ratio: " . number_format($recordRatio * 100, 1, ',', '.') . "% (minimum: " . (MIN_REQUIRED_RECORDS_RATIO * 100) . "%)";
    }

    // Print result
    echo str_pad($cabang, 25) . " | SSA: " . str_pad(number_format($ssaTotal / 1_000_000, 0, ',', '.') . ' M', 12);
    
    if ($cabangIssues) {
        echo " | ❌ ISSUES FOUND:\n";
        foreach ($cabangIssues as $issue) {
            echo str_repeat(" ", 27) . " $issue\n";
        }
        $issues[$cabang] = $cabangIssues;
    } else {
        echo " | ✅ OK\n";
    }
}

// Summary
echo "\n" . str_repeat("=", 80) . "\n";
echo "SUMMARY:\n";
echo "========\n";

if (count($issues) > 0) {
    echo "❌ Found " . count($issues) . " branch(es) with issues:\n";
    foreach ($issues as $cabang => $issueList) {
        echo "  • $cabang: " . count($issueList) . " issue(s)\n";
    }
    echo "\n🔧 RECOMMENDED ACTIONS:\n";
    echo "1. Run daily_loan_dinamis reconciliation for affected branches\n";
    echo "2. Check if snapshots were properly rebuilt\n";
    echo "3. Verify data loading timestamps and sequences\n";
} else {
    echo "✅ All branches passed consistency checks!\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
?>
