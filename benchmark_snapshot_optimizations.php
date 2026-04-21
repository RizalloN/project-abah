<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Support\DashboardHarianSnapshotService;
use App\Support\OptimizedDashboardHarianSnapshotServiceV2;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Dashboard Harian Snapshot Performance Benchmark ===\n\n";

$periods = ['2026-04-20', '2026-04-19', '2026-04-18'];

foreach ($periods as $period) {
    echo "Testing period: $period\n";
    echo str_repeat("-", 60) . "\n";
    
    // Original service
    echo "1. Original Service:\n";
    $service = $app->make(DashboardHarianSnapshotService::class);
    
    $start = microtime(true);
    $result1 = $service->buildPeriodSnapshot($period, true);
    $duration1 = microtime(true) - $start;
    
    echo "   Rows: $result1\n";
    echo "   Duration: {$duration1}s\n\n";
    
    // Optimized service
    echo "2. Optimized Service (V2):\n";
    $optimizedService = new OptimizedDashboardHarianSnapshotServiceV2();
    
    $start = microtime(true);
    $result2 = $optimizedService->buildPeriodSnapshotOptimized($period, true);
    $duration2 = microtime(true) - $start;
    
    echo "   Rows: $result2\n";
    echo "   Duration: {$duration2}s\n";
    echo "   Speedup: " . round($duration1 / $duration2, 1) . "x faster\n";
    echo "   Improvement: " . round(((($duration1 - $duration2) / $duration1) * 100), 1) . "% faster\n";
    
    echo "\n";
}

echo "=== Summary ===\n";
echo "Optimized version should be 3-5x faster than original.\n";
echo "Target: Reduce from ~11.5s to ~2-3s per period.\n";
