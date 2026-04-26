<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require_once dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$table = 'rka';
echo "Checking table: $table\n";
if (!Schema::hasTable($table)) {
    echo "ERROR: Table $table does not exist!\n";
    exit;
}

echo "Schema for 'rka':\n";
$columns = DB::select("DESCRIBE rka");
foreach ($columns as $column) {
    echo "- {$column->Field}: {$column->Type}\n";
}

echo "\nSample Data (first 3 rows):\n";
$data = DB::table($table)->limit(3)->get();
print_r($data->toArray());

echo "\nAvailable Years:\n";
$years = DB::table($table)->selectRaw('YEAR(created_at) as year')->distinct()->pluck('year');
print_r($years->toArray());
