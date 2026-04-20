<?php

include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function getCols($table) {
    return Schema::getColumnListing($table);
}

$tables = [
    'ssa_pinjaman',
    'ssa_simpanan',
    'dashboard_harian_snapshots',
    'cognos_recovery'
];

foreach ($tables as $table) {
    echo "Table: $table\n";
    $cols = getCols($table);
    echo "  Columns: " . implode(', ', $cols) . "\n\n";
}
