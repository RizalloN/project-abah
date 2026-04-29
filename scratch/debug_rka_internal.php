<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Support\RkaLookupService;

class DebugRka extends RkaLookupService {
    public function debugLoad() {
        return $this->loadRows(['apr'], 2026);
    }
}

$debug = new DebugRka();
$rows = $debug->debugLoad();

$ponorogoRows = $rows->filter(fn($r) => str_contains($r['kanca_key'], 'PONOROGO'));

echo "Total Ponorogo Rows: " . $ponorogoRows->count() . "\n";
echo "Sample Row:\n";
print_r($ponorogoRows->first());

$definitions = [
    'total' => [
        'mata_anggaran' => ['C. RECOVERY EKSTRAKOMTABEL'],
    ],
];

$results = $debug->aggregateByGroup($definitions, 'apr', ['KC Ponorogo'], [], 'kanca', 2026);
echo "\nAggregate Results:\n";
print_r($results);
