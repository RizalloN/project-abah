<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

$selectedDate = Carbon::parse('2026-03-31')->endOfDay();
$positions = DB::table('input_rekanan')
    ->whereNotNull('periode')
    ->whereDate('periode', '<=', $selectedDate->toDateString())
    ->selectRaw('DATE(periode) as periode')
    ->groupBy('periode')
    ->orderByDesc('periode')
    ->limit(3)
    ->pluck('periode')
    ->reverse()
    ->values();

$start = microtime(true);
$latestSimpananPerCif = DB::table('simpanan_multipn')
    ->selectRaw('TRIM(CIFNO) as cif_key, MAX(posisi) as latest_posisi')
    ->whereNotNull('CIFNO')
    ->whereRaw("TRIM(CIFNO) <> ''")
    ->whereDate('posisi', '<=', $selectedDate->toDateString())
    ->groupByRaw('TRIM(CIFNO)');

$latestSaldoByCif = DB::table('simpanan_multipn as sm')
    ->joinSub($latestSimpananPerCif, 'latest_sm', function ($join) {
        $join->on(DB::raw('TRIM(sm.CIFNO) COLLATE utf8mb4_unicode_ci'), '=', DB::raw('latest_sm.cif_key COLLATE utf8mb4_unicode_ci'))
            ->on('sm.posisi', '=', 'latest_sm.latest_posisi');
    })
    ->selectRaw('TRIM(sm.CIFNO) as cif_key')
    ->selectRaw("COALESCE(NULLIF(MAX(TRIM(sm.kantor_cabang)), ''), 'Branch Office Belum Terpetakan') as kantor_cabang")
    ->selectRaw('SUM(COALESCE(sm.saldo_idr, 0)) as saldo_idr')
    ->groupByRaw('TRIM(sm.CIFNO)');

$count = DB::table('input_rekanan as src')
    ->leftJoinSub($latestSaldoByCif, 'sm_latest', function ($join) {
        $join->whereRaw('TRIM(src.cif) COLLATE utf8mb4_unicode_ci = sm_latest.cif_key COLLATE utf8mb4_unicode_ci');
    })
    ->whereNotNull('src.periode')
    ->whereIn(DB::raw('DATE(src.periode)'), $positions->all())
    ->count();

echo json_encode(['positions'=>$positions,'count'=>$count,'elapsed'=>microtime(true)-$start], JSON_PRETTY_PRINT);
