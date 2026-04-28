<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "--- Top 10 Largest Tables ---\n";
$res = DB::select("
    SELECT table_name, 
           round((data_length / 1024 / 1024), 2) as data_mb,
           round((index_length / 1024 / 1024), 2) as index_mb,
           round(((data_length + index_length) / 1024 / 1024), 2) as total_mb 
    FROM information_schema.TABLES 
    WHERE table_schema = DATABASE() 
    ORDER BY (data_length + index_length) DESC 
    LIMIT 10
");

foreach($res as $r) {
    printf("%-30s | Data: %8s MB | Index: %8s MB | Total: %8s MB\n", 
        $r->table_name, $r->data_mb, $r->index_mb, $r->total_mb);
}

echo "\n--- simpanan_multipn Indexes ---\n";
$indexes = DB::select("SHOW INDEX FROM simpanan_multipn");
foreach ($indexes as $idx) {
    printf("%-30s | %-30s | %-5s\n", $idx->Key_name, $idx->Column_name, $idx->Seq_in_index);
}
