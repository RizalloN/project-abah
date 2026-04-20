<?php

include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$targetDate = '2026-04-18';
$branch = 'kc-ponorogo';

echo "Rows for branch $branch on $targetDate:\n";
$rows = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $targetDate)
    ->where('kanca_key', $branch)
    ->select('unit_key', 'unit_label', 'total_os', 'total_simpanan')
    ->get();

foreach ($rows as $row) {
    echo "  Unit Key: {$row->unit_key} | Label: {$row->unit_label} | OS: " . number_format($row->total_os) . " | Simp: " . number_format($row->total_simpanan) . "\n";
}
