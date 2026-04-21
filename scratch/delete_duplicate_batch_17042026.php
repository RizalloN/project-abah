<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$deleted = DB::table('daily_loan_dinamis')
  ->where('periode', '2026-04-17')
  ->where('uniqueid_namareport', 'like', 'imp69e6fec6a17c5702338989_%')
  ->delete();

echo json_encode(['deleted' => $deleted], JSON_UNESCAPED_UNICODE);
