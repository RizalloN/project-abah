<?php

include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = [
    'ssa_pinjaman',
    'ssa_simpanan',
    'dashboard_harian_snapshots',
    'cognos_recovery'
];

foreach ($tables as $table) {
    try {
        $dateColumn = match($table) {
            'ssa_pinjaman' => 'Month_Day_Year_of_Posisi',
            'ssa_simpanan' => 'Month_Day_Year_of_Posisi',
            'dashboard_harian_snapshots' => 'snapshot_period',
            'cognos_recovery' => 'periode',
            default => 'created_at'
        };
        
        $maxDate = DB::table($table)->max($dateColumn);
        $count = DB::table($table)->where($dateColumn, $maxDate)->count();
        
        echo "Table: $table\n";
        echo "  Latest Date: $maxDate\n";
        echo "  Rows at Latest Date: $count\n\n";
    } catch (\Throwable $e) {
        echo "Table: $table - Error: " . $e->getMessage() . "\n\n";
    }
}
