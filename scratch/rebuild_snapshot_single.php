<?php

include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Support\DashboardHarianSnapshotService;

$service = app(DashboardHarianSnapshotService::class);
$targetDate = '2026-04-18';

echo "Rebuilding snapshot for $targetDate...\n";
$service->buildPeriodSnapshot($targetDate, true); // force rebuild
echo "Done.\n";
