<?php

define('LARAVEL_START', microtime(true));
require 'd:/XAMPP/htdocs/project-ABAH/vendor/autoload.php';
$app = require_once 'd:/XAMPP/htdocs/project-ABAH/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$builder = app()->make(\App\Support\ReportSnapshotBuilder::class);
$ref = new ReflectionMethod($builder, 'buildPerformanceRmPeriodSnapshot');
$ref->setAccessible(true);

echo "Before rebuild:\n";
$rows = DB::table('performance_rm_snapshots')
    ->where('rm', 'like', '%RONA%')
    ->where('periode', '2026-03-31')
    ->get(['periode', 'produk', 'realisasi_os', 'realisasi_deb', 'created_at', 'updated_at']);
foreach ($rows as $row) {
    echo "Periode: {$row->periode} | Produk: {$row->produk} | Realisasi OS: {$row->realisasi_os} | Realisasi Deb: {$row->realisasi_deb} | Created: {$row->created_at}\n";
}

echo "\nRunning buildPerformanceRmPeriodSnapshot('2026-03-31', true)...\n";
$rowCount = $ref->invoke($builder, '2026-03-31', true);
echo "Result row count: $rowCount\n\n";

echo "After rebuild:\n";
$rows = DB::table('performance_rm_snapshots')
    ->where('rm', 'like', '%RONA%')
    ->where('periode', '2026-03-31')
    ->get(['periode', 'produk', 'realisasi_os', 'realisasi_deb', 'created_at', 'updated_at']);
foreach ($rows as $row) {
    echo "Periode: {$row->periode} | Produk: {$row->produk} | Realisasi OS: {$row->realisasi_os} | Realisasi Deb: {$row->realisasi_deb} | Created: {$row->created_at}\n";
}
