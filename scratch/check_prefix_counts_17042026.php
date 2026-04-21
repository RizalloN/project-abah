<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$result = [
  'driver' => DB::connection()->getDriverName(),
  'db' => DB::selectOne('SELECT DATABASE() as db')->db ?? null,
  'prefix1' => DB::table('daily_loan_dinamis')->where('periode','2026-04-17')->where('uniqueid_namareport','like','imp69e6fb940af53160650633_%')->count(),
  'prefix2' => DB::table('daily_loan_dinamis')->where('periode','2026-04-17')->where('uniqueid_namareport','like','imp69e6fec6a17c5702338989_%')->count(),
  'total' => DB::table('daily_loan_dinamis')->where('periode','2026-04-17')->count(),
];
echo json_encode($result, JSON_UNESCAPED_UNICODE);
