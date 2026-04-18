<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Support\ManagedReportSnapshotRebuildStore;
use App\Support\ManagedReportSnapshotRebuildCoordinator;
use App\Support\ReportDataSyncService;
use App\Jobs\SyncImportedReportJob;

$rebuildId = (string) \Illuminate\Support\Str::uuid();
echo "Testing with Rebuild ID: $rebuildId\n";

// 1. Test Coordinator Registration
echo "1. Registering standalone job...\n";
$coordinator = app(ManagedReportSnapshotRebuildCoordinator::class);
$coordinator->registerStandaloneJob($rebuildId, 'Test Manual Sync', 'test_file.xlsx');

$state = ManagedReportSnapshotRebuildStore::getState($rebuildId);
if ($state && $state['rebuild_id'] === $rebuildId) {
    echo "SUCCESS: Standalone job registered in store.\n";
    print_r($state);
} else {
    echo "FAILED: Standalone job not found in store.\n";
}

// 2. Test Heartbeat in Service
echo "\n2. Emitting heartbeat from service...\n";
$service = app(ReportDataSyncService::class);
$reflector = new ReflectionObject($service);
$method = $reflector->getMethod('heartbeat');
$method->setAccessible(true);
$method->invoke($service, $rebuildId, 'Heartbeat from verification script');

$stateAfter = ManagedReportSnapshotRebuildStore::getState($rebuildId);
if ($stateAfter && $stateAfter['message'] === 'Heartbeat from verification script') {
    echo "SUCCESS: Heartbeat updated the store message.\n";
} else {
    echo "FAILED: Heartbeat didn't update the message.\n";
    print_r($stateAfter);
}

// 3. Cleanup
ManagedReportSnapshotRebuildStore::forgetState($rebuildId);
echo "\nCleanup done.\n";
