<?php

include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$targetDate = '2026-04-18';
$area6 = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];

echo "Checking Branches for $targetDate...\n";

$allBranches = DB::table('ssa_pinjaman')
    ->where('month_day_year_of_periode', $targetDate)
    ->select('nama_cabang')
    ->distinct()
    ->pluck('nama_cabang');

echo "Total Branches in Source: " . $allBranches->count() . "\n";

$area6Found = [];
$others = [];

foreach ($allBranches as $b) {
    $match = false;
    foreach ($area6 as $a) {
        if (str_contains(strtoupper($b), strtoupper(str_replace('KC ', '', $a)))) {
            $area6Found[] = $b;
            $match = true;
            break;
        }
    }
    if (!$match) $others[] = $b;
}

echo "Area 6 Matches Found: " . count($area6Found) . "\n";
echo "Other Branches Found: " . count($others) . "\n";

if (count($others) > 0) {
    echo "First 5 Other Branches: " . implode(', ', array_slice($others, 0, 5)) . "...\n";
}

$sourceArea6Total = DB::table('ssa_pinjaman')
    ->where('month_day_year_of_periode', $targetDate)
    ->where(function($q) use ($area6) {
        foreach ($area6 as $a) {
            $q->orWhere('nama_cabang', 'LIKE', '%' . str_replace('KC ', '', $a) . '%');
        }
    })
    ->sum('baki_debet');

$snapshotTotal = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $targetDate)
    ->whereColumn('kanca_key', 'unit_key')
    ->sum('total_os');

echo "\nArea 6 Source Total: " . number_format($sourceArea6Total) . "\n";
echo "Snapshot Total: " . number_format($snapshotTotal) . "\n";
echo "Difference for Area 6: " . number_format($snapshotTotal - $sourceArea6Total) . "\n";
