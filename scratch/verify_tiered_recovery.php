<?php
include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Support\DashboardHarianSnapshotService;
use Illuminate\Support\Facades\DB;

$service = app(DashboardHarianSnapshotService::class);

echo "--- TIERED RECOVERY LOGIC VERIFICATION ---\n\n";

// Scenario 1: Date with Cognos Data (2026-02-28)
$dateWithCognos = '2026-02-28';
echo "Scenario 1: Date with Cognos Data ($dateWithCognos)\n";
$cognosCount = DB::table('cognos_recovery')->where('periode', $dateWithCognos)->count();
echo "  Rows in cognos_recovery: $cognosCount\n";

$rebuild = $service->buildPeriodSnapshot($dateWithCognos, true);
$snap1 = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $dateWithCognos)
    ->whereColumn('kanca_key', 'unit_key')
    ->sum('rec_dh_total');
$cognosTotal1 = DB::table('cognos_recovery')->where('periode', $dateWithCognos)->sum('total_recovery');

echo "  Snapshot Rec Total: " . number_format($snap1) . "\n";
echo "  Cognos Raw Total:   " . number_format($cognosTotal1) . "\n";
echo "  Status: " . (abs($snap1 - $cognosTotal1) < 1000 ? "SUCCESS (Matched Cognos)" : "FAILED (Mismatch)") . "\n\n";

// Scenario 2: Date without Cognos Data (2026-04-18)
$dateWithoutCognos = '2026-04-18';
echo "Scenario 2: Date without Cognos Data ($dateWithoutCognos)\n";
$cognosCount2 = DB::table('cognos_recovery')->where('periode', $dateWithoutCognos)->count();
echo "  Rows in cognos_recovery: $cognosCount2\n";

$service->buildPeriodSnapshot($dateWithoutCognos, true);
$snap2 = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $dateWithoutCognos)
    ->whereColumn('kanca_key', 'unit_key')
    ->sum('rec_dh_total');

echo "  Snapshot Rec Total: " . number_format($snap2) . "\n";
echo "  Status: " . ($snap2 > 0 ? "SUCCESS (Fallback to DH logic worked)" : "PENDING/ZERO (Check if DH has data)") . "\n";

if ($snap2 <= 0) {
    $phCount = DB::table('lw325_ph')->where('periode', '18 April 2026')->count();
    echo "  PH Rows (18 April 2026): $phCount\n";
}
