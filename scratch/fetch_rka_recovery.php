<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Support\RkaLookupService;
use Carbon\Carbon;

$rkaService = app(RkaLookupService::class);
$availableYears = $rkaService->availableYears();
$latestYear = !empty($availableYears) ? max($availableYears) : (int)date('Y');
$monthCol = 'apr'; // Defaulting to April as it is currently April 2026 in the system

$rkaDefinitions = [
    'micro' => [
        'mata_anggaran' => ['C. 1. a. Recovery Ekstrakomtabel Mikro'],
    ],
    'small' => [
        'mata_anggaran' => ['C. 2. Recovery Ekstrakomtabel Small'],
    ],
    'consumer' => [
        'mata_anggaran' => ['C. 4. Recovery Ekstrakomtabel Konsumer'],
    ],
    'total' => [
        'mata_anggaran' => ['C. RECOVERY EKSTRAKOMTABEL'],
    ],
];

$branchOffices = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];
$results = [];

foreach ($branchOffices as $branchOffice) {
    if ($branchOffice === 'KC Ponorogo') {
        $direct = $rkaService->aggregateByGroup(
            $rkaDefinitions,
            $monthCol,
            ['KC Ponorogo'],
            [],
            'kanca',
            $latestYear
        );

        foreach (array_keys($rkaDefinitions) as $definitionKey) {
            $val = 0;
            if (isset($direct[$definitionKey])) {
                $val = (float) array_sum($direct[$definitionKey]);
            }
            $results[$branchOffice][$definitionKey] = $val;
        }
    } else {
        $regionPatterns = match ($branchOffice) {
            'KC Madiun' => ['MADIUN'],
            'KC Magetan' => ['MAGETAN'],
            'KC Ngawi' => ['NGAWI'],
            default => [],
        };

        if (!empty($regionPatterns)) {
            $regional = $rkaService->aggregateByGroupWithRegionalFilter(
                $rkaDefinitions,
                $monthCol,
                $regionPatterns,
                $latestYear
            );

            $regionKey = $regionPatterns[0];
            foreach (array_keys($rkaDefinitions) as $definitionKey) {
                $results[$branchOffice][$definitionKey] = (float) ($regional[$definitionKey][$regionKey] ?? 0);
            }
        }
    }
}

echo json_encode(['year' => $latestYear, 'month' => $monthCol, 'data' => $results], JSON_PRETTY_PRINT);
