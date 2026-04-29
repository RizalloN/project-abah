<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$start = microtime(true);
echo "Starting Managed Rebuild...\n";
$job = new App\Jobs\RunManagedReportSnapshotRebuildJob(true);
$job->handle(
    app(App\Support\ReportSnapshotBuilder::class),
    app(App\Support\DashboardHarianSnapshotService::class),
    app(App\Support\ReportDataSyncService::class)
);
$time = microtime(true) - $start;
echo "Time: {$time} seconds\n";
