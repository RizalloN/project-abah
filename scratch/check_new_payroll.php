<?php

define('LARAVEL_START', microtime(true));
require 'd:/XAMPP/htdocs/project-ABAH/vendor/autoload.php';
$app = require_once 'd:/XAMPP/htdocs/project-ABAH/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$columns = Schema::getColumnListing('performance_pis_per_produk');
echo "Columns:\n" . implode(', ', $columns) . "\n\n";

$sample = DB::table('performance_pis_per_produk')
    ->limit(5)
    ->get();

foreach ($sample as $index => $row) {
    echo "Row $index:\n";
    foreach ((array)$row as $col => $val) {
        echo "  $col: $val\n";
    }
}
