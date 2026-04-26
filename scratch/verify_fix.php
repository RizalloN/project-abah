<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require_once dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Support\DashboardDanaService;
use Illuminate\Support\Facades\Log;

try {
    $service = app(DashboardDanaService::class);
    
    // Get available periods
    $periods = $service->fetchPeriods();
    if ($periods->isEmpty()) {
        echo "No periods available to test.\n";
        exit;
    }
    
    $testPeriod = $periods->first();
    $testRka = '2026';
    $testCategory = 'all';
    
    echo "Testing getDashboardData for period: $testPeriod, RKA: $testRka, Category: $testCategory...\n";
    
    $data = $service->getDashboardData($testPeriod, $testCategory, $testRka);
    
    echo "SUCCESS: Data loaded successfully.\n";
    echo "Rows count: " . count($data['rows']) . "\n";
    echo "Grand Total Selected: " . $data['total']['selected'] . "\n";
    
} catch (\Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " line " . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
