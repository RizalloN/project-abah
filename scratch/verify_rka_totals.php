<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$monthCol = 'apr';
$year = 2026;

$results = DB::table('rka')
    ->where('desc_uker', 'LIKE', '%MADIUN%')
    ->where('mata_anggaran', 'LIKE', '%Recovery%')
    ->select('mata_anggaran', DB::raw("SUM($monthCol) as total_value"))
    ->groupBy('mata_anggaran')
    ->get();

echo "MADIUN SEGMENTS:\n";
foreach ($results as $row) {
    echo $row->mata_anggaran . " : " . number_format($row->total_value, 2) . "\n";
}

$ngawiResults = DB::table('rka')
    ->where('desc_uker', 'LIKE', '%NGAWI%')
    ->where('mata_anggaran', 'LIKE', '%Recovery%')
    ->select('mata_anggaran', DB::raw("SUM($monthCol) as total_value"))
    ->groupBy('mata_anggaran')
    ->get();

echo "\nNGAWI SEGMENTS:\n";
foreach ($ngawiResults as $row) {
    echo $row->mata_anggaran . " : " . number_format($row->total_value, 2) . "\n";
}
