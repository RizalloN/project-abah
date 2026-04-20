<?php

include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$targetDate = '2026-04-18';
$branches = DB::table('ssa_simpanan')
    ->where('Month_Day_Year_of_Posisi', $targetDate)
    ->select('nama_cabang')
    ->distinct()
    ->pluck('nama_cabang');

echo "Branches in SSA Simpanan: " . $branches->implode(', ') . "\n";
echo "Total Branches: " . $branches->count() . "\n";
