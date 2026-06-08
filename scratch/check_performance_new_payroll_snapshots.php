<?php

define('LARAVEL_START', microtime(true));
require 'd:/XAMPP/htdocs/project-ABAH/vendor/autoload.php';
$app = require_once 'd:/XAMPP/htdocs/project-ABAH/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('performance_new_payroll_snapshots')
    ->orderBy('snapshot_posisi', 'desc')
    ->get();

echo "Row count: " . count($rows) . "\n\n";

foreach ($rows as $row) {
    echo "Posisi: {$row->snapshot_posisi} | Branch: {$row->branch} | Rek Curr: {$row->rekening_curr} | Rek Prev: {$row->rekening_prev} | Rek YoY: {$row->rekening_yoy_prev} | Saldo Curr: {$row->saldo_curr} | Saldo Prev: {$row->saldo_prev} | Saldo YoY: {$row->saldo_yoy_prev}\n";
}
