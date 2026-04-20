<?php

include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$targetDate = '2026-04-18';

echo "Snapshot Analysis for $targetDate:\n";

$summaryTotal = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $targetDate)
    ->whereColumn('kanca_key', 'unit_key')
    ->sum('total_os');

$detailTotal = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $targetDate)
    ->whereColumn('kanca_key', '<>', 'unit_key')
    ->sum('total_os');

echo "  Summary rows total_os: " . number_format($summaryTotal) . "\n";
echo "  Detail rows total_os:  " . number_format($detailTotal) . "\n";

$summaryCount = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $targetDate)
    ->whereColumn('kanca_key', 'unit_key')
    ->count();

$detailCount = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $targetDate)
    ->whereColumn('kanca_key', '<>', 'unit_key')
    ->count();

echo "  Summary row count: $summaryCount\n";
echo "  Detail row count:  $detailCount\n";
