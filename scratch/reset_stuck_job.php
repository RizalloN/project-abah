<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Cache;

$deleteId = '109ee03e-b045-4ddb-9278-862be68c56ac';
$cacheKey = 'report_management_delete:' . $deleteId;

$state = Cache::store('file')->get($cacheKey);

if ($state) {
    echo "Current status: " . ($state['status'] ?? 'unknown') . "\n";
    
    $state['status'] = 'completed';
    $state['stage'] = 'completed';
    $state['message'] = 'Delete selesai secara manual melalui intervensi sistem.';
    $state['updated_at'] = now()->toIso8601String();
    unset($state['error']);
    unset($state['error_code']);

    Cache::store('file')->put($cacheKey, $state, 3600);
    
    // Clear lock just in case
    Cache::store('file')->forget('report_management_delete_lock:' . $deleteId);
    
    echo "Job forcefully marked as COMPLETED.\n";
} else {
    echo "Job not found.\n";
}

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "Opcache reset.\n";
}
