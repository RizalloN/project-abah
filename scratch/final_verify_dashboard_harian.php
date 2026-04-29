<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Support\DashboardHarianSnapshotService;

$service = app(DashboardHarianSnapshotService::class);

// Simulate the call that would happen during snapshot building
// For "All Kanca" view, it aggregates everything
$rkaData = $service->fetchMetricsForRequest(new \Illuminate\Http\Request([
    'period' => '2026-04-29', // SSA period
    'posisi_rka' => '2026-04',
    'kanca' => 'all',
    'unit_kerja' => 'all'
]));

foreach ($rkaData['rows'] as $row) {
    if ($row['key'] === 'total_simpanan') {
        echo "Total Simpanan RKA: " . number_format($row['rka'], 2) . "\n";
    }
}
