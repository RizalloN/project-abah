<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$result = [
  'count' => DB::table('daily_loan_dinamis')->where('periode', '2026-04-17')->count(),
  'sum_baki' => (string) DB::table('daily_loan_dinamis')->where('periode', '2026-04-17')->selectRaw('ROUND(SUM(baki_debet1),2) as total')->value('total'),
  'distinct_rekening' => DB::table('daily_loan_dinamis')->where('periode', '2026-04-17')->distinct('nomor_rekening1')->count('nomor_rekening1'),
  'duplicate_rows' => DB::table('daily_loan_dinamis')->where('periode', '2026-04-17')->selectRaw('COUNT(*) - COUNT(DISTINCT nomor_rekening1) as dupes')->value('dupes'),
];
echo json_encode($result, JSON_UNESCAPED_UNICODE);
