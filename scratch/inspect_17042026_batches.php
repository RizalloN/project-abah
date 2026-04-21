<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('daily_loan_dinamis')
  ->where('periode', '2026-04-17')
  ->selectRaw("SUBSTRING_INDEX(uniqueid_namareport, '_', 1) as batch_prefix, COUNT(*) as cnt, MIN(created_at) as min_created_at, MAX(created_at) as max_created_at")
  ->groupBy('batch_prefix')
  ->orderBy('cnt', 'desc')
  ->get();

echo json_encode($rows, JSON_UNESCAPED_UNICODE);
