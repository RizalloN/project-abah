<?php
include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Support\DashboardHarianSnapshotService;
use Illuminate\Support\Facades\DB;

$service = app(DashboardHarianSnapshotService::class);

echo "--- REFINED TIERED RECOVERY LOGIC VERIFICATION ---\n\n";

// Scenario 1: Date with Cognos Data (2026-02-28)
$dateWithCognos = '2026-02-28';
echo "Scenario 1: Date with Cognos Data ($dateWithCognos)\n";

$service->buildPeriodSnapshot($dateWithCognos, true);
$snap1 = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $dateWithCognos)
    ->whereColumn('kanca_key', 'unit_key')
    ->sum('rec_dh_total');

// To verify correctly, we must simulate the aggregation logic
$rawCognos = DB::table('cognos_recovery')
    ->where('periode', $dateWithCognos)
    ->get();

$expectedTotal = 0;
foreach ($rawCognos as $r) {
    // Manually simulate normalizeKancaLabel
    $normalized = strtoupper($r->cabang);
    $found = false;
    foreach (['MADIUN', 'MAGETAN', 'NGAWI', 'PONOROGO'] as $branchName) {
        if (str_contains($normalized, $branchName)) { $found = true; break; }
    }
    if (!$found && preg_match('/\bKC[P]?\b/', $normalized) === 1) { $found = true; }
    
    if ($found) {
        $expectedTotal += (float)$r->total_recovery;
    }
}

echo "  Snapshot Rec Total: " . number_format($snap1) . "\n";
echo "  Expected Area Total: " . number_format($expectedTotal) . "\n";
echo "  Status: " . (abs($snap1 - $expectedTotal) < 1000 ? "SUCCESS (Matched Cognos for valid branches)" : "FAILED (Mismatch)") . "\n\n";

// Scenario 2: Date without Cognos but with PH Data (2026-04-17)
$dateWithPH = '2026-04-17';
echo "Scenario 2: Date without Cognos but with PH Data ($dateWithPH)\n";
$cognosCount2 = DB::table('cognos_recovery')->where('periode', $dateWithPH)->count();
echo "  Rows in cognos_recovery: $cognosCount2\n";

$service->buildPeriodSnapshot($dateWithPH, true);
$snap2 = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $dateWithPH)
    ->whereColumn('kanca_key', 'unit_key')
    ->sum('rec_dh_total');

echo "  Snapshot Rec Total: " . number_format($snap2) . "\n";
echo "  Status: " . ($snap2 > 0 ? "SUCCESS (Fallback to DH logic worked)" : "FAILED (Fallback failed or no movement in PH)") . "\n";
