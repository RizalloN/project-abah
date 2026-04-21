<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('daily_loan_dinamis')->where('periode', '2026-04-17')
    ->whereIn('nomor_rekening1', ['101053983100','501061071105','501075596105','901046834100','901051467100'])
    ->orderBy('nomor_rekening1')
    ->get(['periode','cifno','nomor_rekening1','nama_debitur1','baki_debet1','cabang1','produk_dashboard','segmen_dashboard']);

echo json_encode($rows, JSON_UNESCAPED_UNICODE);
