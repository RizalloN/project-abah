<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Support\DashboardHarianSnapshotService;

$service = app(DashboardHarianSnapshotService::class);

$reflection = new ReflectionClass($service);
$method = $reflection->getMethod('buildRkaMetrics');
$method->setAccessible(true);

$rkaData = $method->invoke($service, '2026-04-01', '2026-04-26', 'kc-madiun', null, false);

echo "RKA Metrics for Madiun (April 2026):\n";
echo "- total_simpanan: " . number_format($rkaData['total_simpanan'], 2) . "\n";
echo "- simpanan_ritel: " . number_format($rkaData['simpanan_ritel'], 2) . "\n";
echo "- simpanan_mikro: " . number_format($rkaData['simpanan_mikro'], 2) . "\n";
echo "- simpanan_wholesale: " . number_format($rkaData['simpanan_wholesale'], 2) . "\n";
