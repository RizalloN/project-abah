<?php

define('LARAVEL_START', microtime(true));
require 'd:/XAMPP/htdocs/project-ABAH/vendor/autoload.php';
$app = require_once 'd:/XAMPP/htdocs/project-ABAH/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$realisasiDateColumn = Schema::hasColumn('daily_loan_dinamis', 'tgl_realisasi1') ? 'tgl_realisasi1' : 'tgl_realisasi';

foreach (['2026-03-31' => '2026-03-01', '2026-04-30' => '2026-04-01'] as $period => $periodStart) {
    $total = DB::table('daily_loan_dinamis')
        ->where('periode', $period)
        ->where('rm_normalized', 'like', '%RONA%')
        ->count();

    $withRealisasi = DB::table('daily_loan_dinamis')
        ->where('periode', $period)
        ->where('rm_normalized', 'like', '%RONA%')
        ->whereBetween($realisasiDateColumn, [$periodStart, $period])
        ->count();

    echo "Period: $period | Total: $total | Realized in month: $withRealisasi\n";
}
