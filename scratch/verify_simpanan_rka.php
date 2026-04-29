<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$val = DB::table('rka')
    ->where('kanca', 'KC Madiun')
    ->where('mata_anggaran', 'A.1. DPK Retail Funding Total')
    ->sum('apr');

echo "Madiun Total Simpanan RKA: " . number_format($val, 2) . "\n";

$totalVal = DB::table('rka')
    ->where('mata_anggaran', 'A.1. DPK Retail Funding Total')
    ->sum('apr');

echo "Total Area Simpanan RKA: " . number_format($totalVal, 2) . "\n";
