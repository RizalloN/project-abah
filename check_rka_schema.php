<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== RKA Table Schema ===\n";
$columns = DB::select("DESCRIBE rka");
foreach($columns as $col) {
    echo sprintf("  - %s (%s) %s %s\n", 
        $col->Field, 
        $col->Type, 
        $col->Null === 'YES' ? 'NULL' : 'NOT NULL',
        $col->Key ? "KEY: {$col->Key}" : ''
    );
}
