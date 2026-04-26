<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = ['performance_mantri', 'daily_loan_dinamis'];
foreach ($tables as $table) {
    echo "\n--- $table ---\n";
    $indexes = DB::select("SHOW INDEX FROM $table");
    printf("%-30s | %-30s | %-5s\n", "Key_name", "Column_name", "Seq");
    echo str_repeat("-", 70) . "\n";
    foreach ($indexes as $idx) {
        printf("%-30s | %-30s | %-5s\n", $idx->Key_name, $idx->Column_name, $idx->Seq_in_index);
    }
}
