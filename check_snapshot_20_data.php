<?php
use Illuminate\Support\Facades\DB;
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$periods = DB::table('dashboard_harian_snapshots')
    ->select('snapshot_period')
    ->distinct()
    ->orderBy('snapshot_period','desc')
    ->limit(5)
    ->pluck('snapshot_period');

echo "Latest 5 snapshot periods:\n";
foreach ($periods as $p) {
    echo "  - $p\n";
}

$rows20 = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', '2026-04-20')
    ->count();

echo "\nSnapshot rows for 2026-04-20: $rows20\n";

$totalRows = DB::table('dashboard_harian_snapshots')->count();
echo "Total snapshot rows: $totalRows\n";
