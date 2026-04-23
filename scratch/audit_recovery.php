<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$tupok = DB::table('lw325_ph as n')
    ->join('lw325_ph as o', function($join) {
        $join->on('n.acctno', '=', 'o.acctno')
            ->where('n.periode', '=', '2026-04-20')
            ->where('o.periode', '=', '2026-04-19');
    })
    ->whereRaw('(o.pokok - n.pokok) > 0')
    ->sum(DB::raw('o.pokok - n.pokok'));

$lunas = DB::table('lw325_ph as o')
    ->leftJoin('lw325_ph as n', function($join) {
        $join->on('o.acctno', '=', 'n.acctno')
            ->where('n.periode', '=', '2026-04-20');
    })
    ->where('o.periode', '2026-04-19')
    ->whereNull('n.acctno')
    ->sum('o.pokok');

echo "Audit for 2026-04-20:\n";
echo "Tupok: " . number_format($tupok, 2) . "\n";
echo "Lunas: " . number_format($lunas, 2) . "\n";
echo "Total Recovery: " . number_format($tupok + $lunas, 2) . "\n";

$snapshotTotal = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', '2026-04-20')
    ->sum('rec_dh_total');

echo "Snapshot Total: " . number_format($snapshotTotal, 2) . "\n";
echo "Difference: " . number_format(($tupok + $lunas) - $snapshotTotal, 2) . "\n";
