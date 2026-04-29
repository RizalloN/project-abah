<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$year = 2026;
$monthCol = 'apr';

$q = DB::table('rka')
    ->whereYear('created_at', $year)
    ->where(function($q) {
        $q->where('kanca', 'like', '%MADIUN%')
          ->orWhere('desc_uker', 'like', '%MADIUN%');
    });

$metrics = [
    'A.1. DPK Retail Funding Total' => 0,
    'Giro Retail Funding Total' => 0,
    'Tabungan Retail Funding Total' => 0,
    'Deposito Retail Funding Total' => 0,
    'A.2. DPK Korporasi' => 0,
    'A.2.a. Giro Korporasi' => 0,
    'A.2.b. Deposito Korporasi' => 0,
];

foreach ($metrics as $ma => &$val) {
    $val = (float) (clone $q)->where('mata_anggaran', $ma)->sum($monthCol);
}

echo "MADIUN RKA COMPONENTS (Apr 2026):\n";
foreach ($metrics as $ma => $val) {
    echo "$ma: " . number_format($val, 2) . "\n";
}

$retail_giro = (float) (clone $q)->where('mata_anggaran', 'Giro Retail Funding Total')->where('desc_uker', 'not like', '%UNIT%')->sum($monthCol);
$micro_giro = (float) (clone $q)->where('mata_anggaran', 'Giro Retail Funding Total')->where('desc_uker', 'like', '%UNIT%')->sum($monthCol);

echo "\nGiro Retail Breakdown:\n";
echo "Retail (KC/KCP): " . number_format($retail_giro, 2) . "\n";
echo "Micro (UNIT): " . number_format($micro_giro, 2) . "\n";

$retail_tab = (float) (clone $q)->where('mata_anggaran', 'Tabungan Retail Funding Total')->where('desc_uker', 'not like', '%UNIT%')->sum($monthCol);
$micro_tab = (float) (clone $q)->where('mata_anggaran', 'Tabungan Retail Funding Total')->where('desc_uker', 'like', '%UNIT%')->sum($monthCol);

echo "\nTabungan Retail Breakdown:\n";
echo "Retail (KC/KCP): " . number_format($retail_tab, 2) . "\n";
echo "Micro (UNIT): " . number_format($micro_tab, 2) . "\n";
