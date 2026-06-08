<?php

define('LARAVEL_START', microtime(true));
require 'd:/XAMPP/htdocs/project-ABAH/vendor/autoload.php';
$app = require_once 'd:/XAMPP/htdocs/project-ABAH/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = app()->make(\App\Http\Controllers\Report\KinerjaRmReportController::class);

$refMethod = new ReflectionMethod($controller, 'fetchAvailablePeriods');
$refMethod->setAccessible(true);
$availablePeriods = $refMethod->invoke($controller);

$refResolvePeriod = new ReflectionMethod($controller, 'resolveSelectedPeriod');
$refResolvePeriod->setAccessible(true);

$refResolveComparison = new ReflectionMethod($controller, 'resolveKinerjaComparisonPeriods');
$refResolveComparison->setAccessible(true);

$refResolveRealisasi = new ReflectionMethod($controller, 'resolveKinerjaRealisasiPeriod');
$refResolveRealisasi->setAccessible(true);

$refFetchBranchRows = new ReflectionMethod($controller, 'fetchBranchRows');
$refFetchBranchRows->setAccessible(true);

foreach (['2026-03-31', '2026-04-30'] as $period) {
    $selectedPeriod = $refResolvePeriod->invoke($controller, $availablePeriods, $period);
    $comparisonPeriods = $refResolveComparison->invoke($controller, $availablePeriods, $selectedPeriod);
    $realisasiPeriod = $refResolveRealisasi->invoke($controller, $selectedPeriod, $comparisonPeriods);

    $result = $refFetchBranchRows->invoke($controller, 'CONSUMER', $selectedPeriod, $comparisonPeriods, $realisasiPeriod, null, null);

    foreach ($result['rows'] as $branch) {
        foreach ($branch['rms'] as $rmName => $rmData) {
            if (strpos($rmName, 'RONA') !== false) {
                echo "Period: $period | RM: $rmName | Ach Os: {$rmData['items'][0]['ach_os']} | Ach Deb: {$rmData['items'][0]['ach_deb']}\n";
            }
        }
    }
}
