<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require_once dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (!Schema::hasTable('ssa_simpanan')) {
    echo "Table 'ssa_simpanan' does NOT exist.\n";
    exit;
}

echo "Schema for 'ssa_simpanan':\n";
$columns = DB::select("DESCRIBE ssa_simpanan");
foreach ($columns as $column) {
    echo "- {$column->Field}: {$column->Type}\n";
}

echo "\nSample Data (first 5 rows):\n";
$data = DB::table('ssa_simpanan')->limit(5)->get();
print_r($data->toArray());

echo "\nDistinct Periods:\n";
$periods = DB::table('ssa_simpanan')->distinct()->pluck('Month_Day_Year_of_Posisi');
print_r($periods->toArray());
