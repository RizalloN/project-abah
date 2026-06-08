<?php

define('LARAVEL_START', microtime(true));
require 'd:/XAMPP/htdocs/project-ABAH/vendor/autoload.php';
$app = require_once 'd:/XAMPP/htdocs/project-ABAH/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('performance_rm_snapshots')
    ->where('rm', 'like', '%RONA%')
    ->orderBy('periode')
    ->get(['periode', 'produk', 'realisasi_os', 'realisasi_deb', 'created_at', 'updated_at']);

foreach ($rows as $row) {
    echo "Periode: {$row->periode} | Produk: {$row->produk} | Realisasi OS: {$row->realisasi_os} | Realisasi Deb: {$row->realisasi_deb} | Created: {$row->created_at} | Updated: {$row->updated_at}\n";
}
