<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require_once dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$table = 'ssa_simpanan_snapshots';
echo "Checking table: $table\n";
if (!Schema::hasTable($table)) {
    echo "ERROR: Table $table does not exist!\n";
    exit;
}

echo "Schema for '$table':\n";
$columns = DB::select("DESCRIBE $table");
foreach ($columns as $column) {
    echo "- {$column->Field}: {$column->Type}\n";
}
