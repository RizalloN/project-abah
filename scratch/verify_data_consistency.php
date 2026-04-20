<?php

include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

echo "--- DATA CONSISTENCY CHECK ---\n\n";

// 1. Check Latest Dates
$latestPinjaman = DB::table('ssa_pinjaman')->max('month_day_year_of_periode');
$latestSimpanan = DB::table('ssa_simpanan')->max('Month_Day_Year_of_Posisi');
$latestSnapshot = DB::table('dashboard_harian_snapshots')->max('snapshot_period');
$latestRecovery = DB::table('cognos_recovery')->max('periode');

echo "Latest Dates:\n";
echo "  ssa_pinjaman: $latestPinjaman\n";
echo "  ssa_simpanan: $latestSimpanan\n";
echo "  dashboard_harian_snapshots: $latestSnapshot\n";
echo "  cognos_recovery: $latestRecovery\n\n";

// 2. Aggregate Source for Latest Date (2026-04-18)
$targetDate = '2026-04-18';
echo "Comparison for Date: $targetDate\n";

// Source Simpanan Total
$sourceSimpananTotal = DB::table('ssa_simpanan')
    ->where('Month_Day_Year_of_Posisi', $targetDate)
    ->sum('saldo');

// Source Pinjaman Total (OS)
$sourcePinjamanTotal = DB::table('ssa_pinjaman')
    ->where('month_day_year_of_periode', $targetDate)
    ->sum('baki_debet');

// Snapshot Totals (sum of all units for that date)
$snapshotSimpananTotal = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $targetDate)
    ->whereColumn('kanca_key', 'unit_key') // Aggregated at kanca level
    ->sum('total_simpanan');

$snapshotPinjamanTotal = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $targetDate)
    ->whereColumn('kanca_key', 'unit_key')
    ->sum('total_os');

echo "  SIMPANAN:\n";
echo "    Source Total: " . number_format($sourceSimpananTotal) . "\n";
echo "    Snapshot Total: " . number_format($snapshotSimpananTotal) . "\n";
echo "    Difference: " . number_format($snapshotSimpananTotal - $sourceSimpananTotal) . "\n";

echo "  PINJAMAN (OS):\n";
echo "    Source Total: " . number_format($sourcePinjamanTotal) . "\n";
echo "    Snapshot Total: " . number_format($snapshotPinjamanTotal) . "\n";
echo "    Difference: " . number_format($snapshotPinjamanTotal - $sourcePinjamanTotal) . "\n\n";

// 3. Check for Outdated Segments in cognos_recovery
echo "Cognos Recovery Status:\n";
if ($latestRecovery < $latestPinjaman) {
    echo "  WARNING: cognos_recovery is outdated ($latestRecovery vs $latestPinjaman).\n";
} else {
    echo "  OK: cognos_recovery is up to date.\n";
}
echo "\n";

// 4. Check Kolek (NPL) - Example logic
// Snapshot NPL Abs total (Total NPL ABS Non Commercial?)
$snapshotNplTotal = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $targetDate)
    ->whereColumn('kanca_key', 'unit_key')
    ->sum('total_npl_abs_non_commercial');

// Source NPL (Kolek 3, 4, 5)
$sourceNplTotal = DB::table('ssa_pinjaman')
    ->where('month_day_year_of_periode', $targetDate)
    ->whereIn('kolektabilitas_one_obligor', [3, 4, 5])
    ->sum('baki_debet');

echo "  NPL (Kolek 3,4,5):\n";
echo "    Source NPL Total: " . number_format($sourceNplTotal) . "\n";
echo "    Snapshot NPL Total: " . number_format($snapshotNplTotal) . "\n";
echo "    Difference: " . number_format($snapshotNplTotal - $sourceNplTotal) . "\n";
