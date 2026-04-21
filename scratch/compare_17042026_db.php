<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$period = '2026-04-17';
$sampleAccounts = ['101053983100','501061071105','501075596105','901046834100','901051467100'];
$result = [
    'count' => DB::table('daily_loan_dinamis')->where('periode', $period)->count(),
    'sum_baki' => (string) DB::table('daily_loan_dinamis')->where('periode', $period)->selectRaw('ROUND(SUM(baki_debet1), 2) AS total')->value('total'),
    'distinct_rekening' => DB::table('daily_loan_dinamis')->where('periode', $period)->distinct('nomor_rekening1')->count('nomor_rekening1'),
    'duplicate_rows' => DB::table('daily_loan_dinamis')->where('periode', $period)
        ->selectRaw('COUNT(*) - COUNT(DISTINCT nomor_rekening1) as dupes')->value('dupes'),
    'samples' => DB::table('daily_loan_dinamis')
        ->where('periode', $period)
        ->whereIn('nomor_rekening1', $sampleAccounts)
        ->orderBy('nomor_rekening1')
        ->get(['periode','cifno','nomor_rekening1','nama_debitur1','baki_debet1','cabang1','produk_dashboard','segmen_dashboard'])
        ->map(function ($row) {
            return [
                'periode' => (string) $row->periode,
                'cifno' => (string) $row->cifno,
                'nomor_rekening1' => (string) $row->nomor_rekening1,
                'nama_debitur1' => (string) $row->nama_debitur1,
                'baki_debet1' => number_format((float) $row->baki_debet1, 2, '.', ''),
                'cabang1' => (string) $row->cabang1,
                'produk_dashboard' => (string) $row->produk_dashboard,
                'segmen_dashboard' => (string) $row->segmen_dashboard,
            ];
        })->values()->all(),
];
echo json_encode($result, JSON_UNESCAPED_UNICODE);
