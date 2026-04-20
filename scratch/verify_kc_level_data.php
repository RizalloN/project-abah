<?php

include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$targetDate = '2026-04-18';

echo "Loans at KC level for $targetDate:\n";
$kcLoans = DB::table('ssa_pinjaman')
    ->where('month_day_year_of_periode', $targetDate)
    ->where('nama_uker', 'LIKE', '%KC %')
    ->sum('baki_debet');

echo "  Total: " . number_format($kcLoans) . "\n";

echo "Simpanan at KC level for $targetDate:\n";
$kcSimpanan = DB::table('ssa_simpanan')
    ->where('Month_Day_Year_of_Posisi', $targetDate)
    ->where('nama_uker', 'LIKE', '%KC %')
    ->sum('saldo');

echo "  Total: " . number_format($kcSimpanan) . "\n";
