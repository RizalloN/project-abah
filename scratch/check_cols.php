<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require_once dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$table = 'ssa_simpanan';
$requiredColumns = ['Month_Day_Year_of_Posisi', 'nama_cabang', 'produk', 'saldo', 'segmentasi'];

echo "Checking table: $table\n";
if (!Schema::hasTable($table)) {
    echo "ERROR: Table $table does not exist!\n";
    exit;
}

foreach ($requiredColumns as $col) {
    if (Schema::hasColumn($table, $col)) {
        echo "OK: Column $col exists.\n";
    } else {
        echo "ERROR: Column $col is MISSING!\n";
    }
}

$sample = DB::table($table)->first();
echo "\nSample Row Keys:\n";
print_r(array_keys((array)$sample));
