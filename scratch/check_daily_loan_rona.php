<?php

define('LARAVEL_START', microtime(true));
require 'd:/XAMPP/htdocs/project-ABAH/vendor/autoload.php';
$app = require_once 'd:/XAMPP/htdocs/project-ABAH/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$realisasiDateColumn = Schema::hasColumn('daily_loan_dinamis', 'tgl_realisasi1') ? 'tgl_realisasi1' : 'tgl_realisasi';

$period = '2026-02-28';
$periodStart = '2026-02-01';

$total = DB::table('daily_loan_dinamis')
    ->where('periode', $period)
    ->where('rm_normalized', 'like', '%RONA%')
    ->count();

$withRealisasi = DB::table('daily_loan_dinamis')
    ->where('periode', $period)
    ->where('rm_normalized', 'like', '%RONA%')
    ->whereBetween($realisasiDateColumn, [$periodStart, $period])
    ->count();

echo "Period: $period\n";
echo "Total loan accounts for Rona: $total\n";
echo "Loan accounts with tgl_realisasi in Feb: $withRealisasi\n";
