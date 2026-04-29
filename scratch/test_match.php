<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Support\RkaLookupService;

$service = new RkaLookupService();
$rc = new ReflectionClass($service);
$matchesDef = $rc->getMethod('matchesDefinition');
$matchesDef->setAccessible(true);

$row = DB::table('rka')
    ->where('kanca', 'KC Madiun')
    ->where('mata_anggaran', 'C. RECOVERY EKSTRAKOMTABEL')
    ->first();

if (!$row) {
    die("Row not found\n");
}

$rc_service = new ReflectionClass($service);
$normScope = $rc_service->getMethod('normalizeScopeValue');
$normScope->setAccessible(true);
$normLookup = $rc_service->getMethod('normalizeLookupValue');
$normLookup->setAccessible(true);

$kanca_key = $normScope->invoke($service, $row->kanca);
$uker_key = $normScope->invoke($service, $row->desc_uker);
$ma_key = $normLookup->invoke($service, $row->mata_anggaran);

$processedRow = [
    'kanca_key' => $kanca_key,
    'uker_key' => $uker_key,
    'mata_anggaran_key' => $ma_key,
    'months' => ['apr' => $row->apr]
];

echo "Row Kanca: [" . $row->kanca . "]\n";
echo "Generated Kanca Key: [" . $kanca_key . "]\n";
echo "Row MA: [" . $row->mata_anggaran . "]\n";
echo "Generated MA Key: [" . $ma_key . "]\n";

$definition = [
    'mata_anggaran' => ['C. RECOVERY EKSTRAKOMTABEL']
];

echo "Row MA: " . $row->mata_anggaran . "\n";
echo "Row MA Key: " . $processedRow['mata_anggaran_key'] . "\n";
echo "Matches Definition? " . ($matchesDef->invoke($service, $processedRow, $definition) ? 'YES' : 'NO') . "\n";

$definitions = [
    'rec_dh_total' => ['mata_anggaran' => ['C. RECOVERY EKSTRAKOMTABEL']]
];

$result = $service->aggregateForScope($definitions, 'apr', 'KC Madiun', null, 2026);
echo "\nAggregate result:\n";
print_r($result);
