<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$period = '2026-04-17';
$grouped = DB::table('daily_loan_dinamis')
    ->where('periode', $period)
    ->selectRaw('nomor_rekening1, COUNT(*) as cnt')
    ->groupBy('nomor_rekening1');

$result = [
  'rows' => DB::table('daily_loan_dinamis')->where('periode', $period)->count(),
  'distinct_rekening' => DB::table('daily_loan_dinamis')->where('periode', $period)->distinct('nomor_rekening1')->count('nomor_rekening1'),
  'max_dupes' => DB::query()->fromSub($grouped, 'x')->max('cnt'),
  'non_2x_groups' => DB::query()->fromSub($grouped, 'x')->where('cnt', '<>', 2)->count(),
  'created_at_span' => DB::table('daily_loan_dinamis')->where('periode', $period)
      ->selectRaw('MIN(created_at) as min_created_at, MAX(created_at) as max_created_at')->first(),
  'sample_dupes' => DB::table('daily_loan_dinamis')->where('periode', $period)
      ->whereIn('nomor_rekening1', ['101053983100','501061071105'])
      ->orderBy('nomor_rekening1')->orderBy('uniqueid_namareport')
      ->get(['uniqueid_namareport','nomor_rekening1','created_at','updated_at'])->all(),
];
echo json_encode($result, JSON_UNESCAPED_UNICODE);
