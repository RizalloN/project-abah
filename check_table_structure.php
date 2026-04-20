<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Get table structure
$columns = DB::getSchemaBuilder()->getColumnListing('dashboard_harian_snapshots');
echo "Snapshot table columns:\n";
foreach ($columns as $col) {
    echo "  - $col\n";
}

echo "\n\nSample rows from snapshot table (2026-04-19, KC Madiun):\n";
$data = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', '2026-04-19')
    ->where('kanca_label', 'KC Madiun')
    ->limit(5)
    ->get();

foreach ($data as $row) {
    $arr = (array)$row;
    echo "\nRow with: ";
    // Show key columns
    foreach (['id', 'snapshot_period', 'kanca_label', 'area_id', 'product_id', 'produk', 'kategori'] as $col) {
        if (isset($arr[$col])) {
            echo "$col={$arr[$col]}, ";
        }
    }
    echo "\n";
}
