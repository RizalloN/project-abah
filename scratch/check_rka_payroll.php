<?php

define('LARAVEL_START', microtime(true));
require 'd:/XAMPP/htdocs/project-ABAH/vendor/autoload.php';
$app = require_once 'd:/XAMPP/htdocs/project-ABAH/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (Schema::hasTable('rka')) {
    $rows = DB::table('rka')
        ->where('mata_anggaran', 'like', '%payroll%')
        ->select('mata_anggaran')
        ->distinct()
        ->get();
    
    foreach ($rows as $row) {
        echo "Mata Anggaran: {$row->mata_anggaran}\n";
    }
} else {
    echo "rka table does not exist.\n";
}
