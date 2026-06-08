<?php

define('LARAVEL_START', microtime(true));
require 'd:/XAMPP/htdocs/project-ABAH/vendor/autoload.php';
$app = require_once 'd:/XAMPP/htdocs/project-ABAH/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$posisiCounts = DB::table('performance_pis_per_produk')
    ->select('posisi', DB::raw('count(*) as count'))
    ->groupBy('posisi')
    ->orderBy('posisi', 'desc')
    ->get();

foreach ($posisiCounts as $row) {
    echo "Posisi: {$row->posisi} | Count: {$row->count}\n";
}
