<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Import\ImportIndexController;

$deleteId = 'audit_test_' . uniqid();
$cacheKey = 'report_management_delete:' . $deleteId;

// 1. Create a fake state
$state = [
    'delete_id' => $deleteId,
    'status' => 'running',
    'stage' => 'cleanup',
    'created_at' => now()->subMinutes(10)->toIso8601String(),
    'updated_at' => now()->subMinutes(10)->toIso8601String(),
];
Cache::store('file')->put($cacheKey, $state, 3600);

echo "Initial updated_at: " . $state['updated_at'] . "\n";

// 2. Call heartbeat
$controller = app(ImportIndexController::class);
$result = $controller->heartbeatManagedDeleteState($deleteId, 'Testing heartbeat...');

if ($result) {
    echo "Heartbeat called successfully.\n";
    $newState = Cache::store('file')->get($cacheKey);
    echo "New updated_at: " . $newState['updated_at'] . "\n";
    echo "New message: " . $newState['message'] . "\n";
    
    if (Carbon\Carbon::parse($newState['updated_at'])->gt(Carbon\Carbon::parse($state['updated_at']))) {
        echo "VERIFICATION SUCCESS: Timestamp updated.\n";
    } else {
        echo "VERIFICATION FAILED: Timestamp NOT updated.\n";
    }
} else {
    echo "Heartbeat call failed.\n";
}

// Cleanup
Cache::store('file')->forget($cacheKey);
Cache::store('file')->forget('report_management_delete_lock:' . $deleteId);
