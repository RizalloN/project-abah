<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$tableName = 'simpanan_multipn';
$indexes = DB::select("SHOW INDEX FROM `{$tableName}`");
echo "Indexes for {$tableName}:\n";
foreach ($indexes as $index) {
    echo "- {$index->Key_name}: {$index->Column_name}\n";
}

$columns = DB::select("SHOW COLUMNS FROM `{$tableName}`");
echo "\nColumns for {$tableName}:\n";
foreach ($columns as $column) {
    echo "- {$column->Field}: {$column->Type}\n";
}
