<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Support\DashboardHarianSnapshotService;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = $app->make(DashboardHarianSnapshotService::class);

echo "=== Rebuilding April 19, 2026 Snapshot ===\n\n";

$startTime = microtime(true);

try {
    // Rebuild for April 19 specifically
    $period = '2026-04-19';
    echo "Rebuilding snapshot for period: $period\n";
    
    $result = $service->buildPeriodSnapshot($period, true);
    
    $duration = microtime(true) - $startTime;
    
    echo "✅ Snapshot rebuilt successfully!\n";
    echo "Rows created: $result\n";
    echo "Duration: {$duration}s\n";
    
    // Verify
    $count = DB::table('dashboard_harian_snapshots')
        ->where('snapshot_period', $period)
        ->count();
    
    echo "\nVerification:\n";
    echo "Total rows in snapshot for $period: $count\n";
    
    // Show latest periods
    echo "\nLatest snapshot periods:\n";
    $periods = DB::table('dashboard_harian_snapshots')
        ->distinct('snapshot_period')
        ->orderBy('snapshot_period', 'desc')
        ->limit(5)
        ->pluck('snapshot_period');
    
    foreach ($periods as $p) {
        $rows = DB::table('dashboard_harian_snapshots')
            ->where('snapshot_period', $p)
            ->count();
        echo "  $p: $rows rows\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
