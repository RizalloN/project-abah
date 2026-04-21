<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$prefix = 'imp69e6fec6a17c5702338989_%';
$summary = DB::table('daily_loan_dinamis')
  ->where('periode', '2026-04-17')
  ->where('uniqueid_namareport', 'like', $prefix)
  ->selectRaw('COUNT(*) as cnt, ROUND(SUM(baki_debet1),2) as total')
  ->first();

echo json_encode($summary, JSON_UNESCAPED_UNICODE);
