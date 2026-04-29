<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$results = DB::table('rka')
    ->where('kanca', 'KC Magetan')
    ->where('mata_anggaran', 'C. RECOVERY EKSTRAKOMTABEL')
    ->select('desc_uker', 'apr')
    ->get();

echo "MAGETAN UNITS IN RKA TABLE (by kanca 'KC Magetan'):\n";
foreach ($results as $row) {
    echo $row->desc_uker . " : " . number_format($row->apr, 2) . "\n";
}
echo "Total: " . number_format($results->sum('apr'), 2) . "\n";
