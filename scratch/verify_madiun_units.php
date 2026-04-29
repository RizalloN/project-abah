<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$results = DB::table('rka')
    ->where('desc_uker', 'LIKE', '%MADIUN%')
    ->select('desc_uker', 'kanca')
    ->distinct()
    ->get();

echo "MADIUN UKERS:\n";
foreach ($results as $row) {
    echo $row->desc_uker . " (Kanca: " . $row->kanca . ")\n";
}

$results2 = DB::table('rka')
    ->where('kanca', 'KC Ponorogo')
    ->where('mata_anggaran', 'C. RECOVERY EKSTRAKOMTABEL')
    ->where('apr', '>', 0)
    ->select('desc_uker', 'apr')
    ->get();

echo "\nALL PONOROGO SUB-UNITS FOR C. RECOVERY EKSTRAKOMTABEL:\n";
foreach ($results2 as $row) {
    echo $row->desc_uker . " : " . number_format($row->apr, 2) . "\n";
}
