<?php

define('LARAVEL_START', microtime(true));
require 'd:/XAMPP/htdocs/project-ABAH/vendor/autoload.php';
$app = require_once 'd:/XAMPP/htdocs/project-ABAH/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $row = DB::table('performance_rm_snapshots')->select('w1_realisasi_deb')->first();
    echo "Column w1_realisasi_deb exists and select succeeded.\n";
} catch (\Throwable $e) {
    echo "Select failed: " . $e->getMessage() . "\n";
}
