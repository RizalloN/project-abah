<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$periods = DB::table('performance_rm_snapshots')
    ->whereBetween('periode', ['2025-04-01', '2025-04-30'])
    ->selectRaw('periode, COUNT(*) as row_count, SUM(COALESCE(loan_os, 0)) as loan_os')
    ->groupBy('periode')
    ->orderByDesc('periode')
    ->get();

$nearby = DB::table('performance_rm_snapshots')
    ->where('periode', '<=', '2025-05-05')
    ->selectRaw('periode, COUNT(*) as row_count')
    ->groupBy('periode')
    ->orderByDesc('periode')
    ->limit(10)
    ->get();

$sourcePeriods = DB::table('daily_loan_dinamis')
    ->whereBetween('periode', ['2025-04-01', '2025-04-30'])
    ->selectRaw('periode, COUNT(*) as row_count, SUM(COALESCE(baki_debet1, 0)) as baki_debet')
    ->groupBy('periode')
    ->orderByDesc('periode')
    ->get();

$sourceNearby = DB::table('daily_loan_dinamis')
    ->where('periode', '<=', '2025-05-05')
    ->selectRaw('periode, COUNT(*) as row_count')
    ->groupBy('periode')
    ->orderByDesc('periode')
    ->limit(10)
    ->get();

$allSourcePeriods = DB::table('daily_loan_dinamis')
    ->selectRaw('periode, COUNT(*) as row_count')
    ->groupBy('periode')
    ->orderBy('periode')
    ->get();

$allSnapshotPeriods = DB::table('performance_rm_snapshots')
    ->selectRaw('periode, COUNT(*) as row_count')
    ->groupBy('periode')
    ->orderBy('periode')
    ->get();

echo json_encode([
    'april_2025_periods' => $periods,
    'nearest_before_2025_05_05' => $nearby,
    'source_april_2025_periods' => $sourcePeriods,
    'source_nearest_before_2025_05_05' => $sourceNearby,
    'all_source_periods' => $allSourcePeriods,
    'all_snapshot_periods' => $allSnapshotPeriods,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
