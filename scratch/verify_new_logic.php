<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Support\DashboardHarianSnapshotService;
use Carbon\Carbon;

$service = app(DashboardHarianSnapshotService::class);
$rc = new ReflectionClass($service);
$method = $rc->getMethod('buildRkaMetrics');
$method->setAccessible(true);

// Period: 2026-04-28
$rkaPeriod = '2026-04-28';
$filterPeriod = '2026-04-28';

echo "BUILDING RKA FOR KC MADIUN...\n";
$madiunResult = $method->invoke($service, $rkaPeriod, $filterPeriod, 'KC Madiun', null, false);
print_r($madiunResult);
echo "Total RKA Madiun: " . number_format($madiunResult['rec_dh_total'] ?? 0, 2) . "\n";

echo "\nBUILDING RKA FOR KC NGAWI...\n";
$ngawiResult = $method->invoke($service, $rkaPeriod, $filterPeriod, 'KC Ngawi', null, false);
echo "Total RKA Ngawi: " . number_format($ngawiResult['rec_dh_total'], 2) . "\n";

echo "\nBUILDING RKA FOR ALL KANCA...\n";
$allResult = $method->invoke($service, $rkaPeriod, $filterPeriod, null, null, false);
echo "Total RKA All Area: " . number_format($allResult['rec_dh_total'], 2) . "\n";
