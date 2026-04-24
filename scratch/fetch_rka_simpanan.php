<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$results = DB::table('rka')
    ->select('mata_anggaran')
    ->distinct()
    ->where('mata_anggaran', 'LIKE', '%Giro%')
    ->orWhere('mata_anggaran', 'LIKE', '%Tabungan%')
    ->orWhere('mata_anggaran', 'LIKE', '%Deposito%')
    ->limit(100)
    ->pluck('mata_anggaran');

foreach ($results as $r) {
    echo $r . PHP_EOL;
}
