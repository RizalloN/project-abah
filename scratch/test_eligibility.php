<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Import\ImportExcelController;

class MockImportExcelController extends ImportExcelController {
    public function testEligibility($params) {
        return $this->resolveDirectCsvFastPathEligibility('simpanan_multipn', $params, ['POSISI'], 0);
    }
}

$controller = new MockImportExcelController();

// Test case 1: Actually empty filters
$params1 = ['active_filters' => []];
echo "Test 1 (Empty Array): " . json_encode($controller->testEligibility($params1)) . "\n";

// Test case 2: Filters with empty strings (the "problem" case)
$params2 = ['active_filters' => ['posisi' => '', 'kantor_cabang' => '']];
echo "Test 2 (Empty Strings): " . json_encode($controller->testEligibility($params2)) . "\n";

// Test case 3: Filters with actual values
$params3 = ['active_filters' => ['posisi' => '2025-12-31']];
echo "Test 3 (Actual Filter): " . json_encode($controller->testEligibility($params3)) . "\n";
