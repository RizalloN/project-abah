<?php

define('LARAVEL_START', microtime(true));
require 'd:/XAMPP/htdocs/project-ABAH/vendor/autoload.php';
$app = require_once 'd:/XAMPP/htdocs/project-ABAH/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$controller = app()->make(\App\Http\Controllers\Report\KinerjaRmReportController::class);

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

$refFetchBranchRows = new ReflectionMethod($controller, 'fetchBranchRows');
$refFetchBranchRows->setAccessible(true);
$result = $refFetchBranchRows->invoke($controller, 'CONSUMER', $selectedPeriod, $comparisonPeriods, $realisasiPeriod, null, null);

foreach ($result['rows'] as $branch) {
    foreach ($branch['rms'] as $rmName => $rmData) {
        foreach ($rmData['items'] as $item) {
            $ach_os_million = ($item['ach_os'] ?? 0) / 1000000;
            echo "RM: $rmName | Product: {$item['product']} | Ach Os: {$item['ach_os']} ($ach_os_million) | Ach Deb: {$item['ach_deb']}\n";
        }
    }
}
