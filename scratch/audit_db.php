<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require_once dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

function checkTable($name) {
    echo "--- $name ---\n";
    try {
        $indexes = DB::select("SHOW INDEX FROM $name");
        foreach ($indexes as $idx) {
            echo "Index: {$idx->Key_name}, Column: {$idx->Column_name}\n";
        }
        $count = DB::table($name)->count();
        echo "Total Rows: $count\n";
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

checkTable('daily_loan_dinamis');
checkTable('brihc');
checkTable('performance_rm_snapshots');
checkTable('wilayah_mbm');
