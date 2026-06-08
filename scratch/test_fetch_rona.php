<?php

define('LARAVEL_START', microtime(true));
require 'd:/XAMPP/htdocs/project-ABAH/vendor/autoload.php';
$app = require_once 'd:/XAMPP/htdocs/project-ABAH/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

// Mock KinerjaRmReportController and call fetchBranchRows
$controller = app()->make(\App\Http\Controllers\Report\KinerjaRmReportController::class);

// We need to resolve availablePeriods, comparisonPeriods, realisasiPeriod
// Let's do similar steps as index method:
$refMethod = new ReflectionMethod($controller, 'fetchAvailablePeriods');
$refMethod->setAccessible(true);
$availablePeriods = $refMethod->invoke($controller);

$refResolvePeriod = new ReflectionMethod($controller, 'resolveSelectedPeriod');
$refResolvePeriod->setAccessible(true);
$selectedPeriod = $refResolvePeriod->invoke($controller, $availablePeriods, '2026-05-31');

$refResolveComparison = new ReflectionMethod($controller, 'resolveKinerjaComparisonPeriods');
$refResolveComparison->setAccessible(true);
$comparisonPeriods = $refResolveComparison->invoke($controller, $availablePeriods, $selectedPeriod);

$refResolveRealisasi = new ReflectionMethod($controller, 'resolveKinerjaRealisasiPeriod');
$refResolveRealisasi->setAccessible(true);
$realisasiPeriod = $refResolveRealisasi->invoke($controller, $selectedPeriod, $comparisonPeriods);

echo "Selected Period: $selectedPeriod\n";
echo "Realisasi Period: $realisasiPeriod\n";
echo "Comparison Periods: " . json_encode($comparisonPeriods) . "\n\n";

$refFetchBranchRows = new ReflectionMethod($controller, 'fetchBranchRows');
$refFetchBranchRows->setAccessible(true);
$result = $refFetchBranchRows->invoke($controller, 'CONSUMER', $selectedPeriod, $comparisonPeriods, $realisasiPeriod, null, null);

// Find RONA
$foundRona = null;
foreach ($result['rows'] as $branch) {
    foreach ($branch['rms'] as $rmName => $rmData) {
        if (strpos($rmName, 'RONA') !== false) {
            $foundRona = $rmData;
            echo "RM Name: $rmName\n";
            print_r($rmData);
            break 2;
        }
    }
}
