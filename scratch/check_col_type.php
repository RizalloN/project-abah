<?php

include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

$table = 'ssa_pinjaman';
$col = 'baki_debet';

$type = Schema::getColumnType($table, $col);
echo "Column $col in table $table type: $type\n";

// Also check sample values
$sample = DB::table($table)->whereNotNull($col)->limit(5)->pluck($col);
echo "Sample values: " . $sample->implode(', ') . "\n";
