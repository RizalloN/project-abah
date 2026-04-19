<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Support\RkaLookupService;
use Illuminate\Support\Facades\DB;

$service = app(RkaLookupService::class);
$rkaDefinitions = [
    'micro' => ['mata_anggaran' => ['C. 1. a. Recovery Ekstrakomtabel Mikro']],
    'total' => ['mata_anggaran' => ['C. RECOVERY EKSTRAKOMTABEL']],
];

// Area 6 branches for testing
$area6 = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];

// Test one month
$rkaData = $service->aggregateByGroup($rkaDefinitions, 'jan', [], [], 'uker', 2026);

echo "RKA Data Keys (First 10):\n";
print_r(array_slice(array_keys($rkaData['total']), 0, 10));

// Check specifically for a unit in Madiun from cognos_recovery
$unitHeader = DB::table('cognos_recovery')
    ->whereIn('cabang', $area6)
    ->select('unit_kerja')
    ->first();

if ($unitHeader) {
    echo "\nSample Unit from Recovery: " . $unitHeader->unit_kerja . "\n";
} else {
    echo "\nNo units found in recovery table.\n";
}
