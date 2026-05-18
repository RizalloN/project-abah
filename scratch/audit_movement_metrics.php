<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$curr = '2026-05-14';
$prev = '2026-04-30';
$ph   = '2026-05-14';

$rankExpr = "CASE quality_bucket
    WHEN 'L' THEN 0 WHEN 'LR' THEN 1
    WHEN 'DPK 1' THEN 2 WHEN 'DPK 2' THEN 3 WHEN 'DPK 3' THEN 4
    WHEN 'KL' THEN 5 WHEN 'D1' THEN 6 WHEN 'D2' THEN 7 WHEN 'M' THEN 8 ELSE NULL END";
$labelExpr = "CASE rnk
    WHEN 0 THEN 'L' WHEN 1 THEN 'LR' WHEN 2 THEN 'DPK 1' WHEN 3 THEN 'DPK 2' WHEN 4 THEN 'DPK 3'
    WHEN 5 THEN 'KL' WHEN 6 THEN 'D1' WHEN 7 THEN 'D2' WHEN 8 THEN 'M' END";

$buildAgg = function (string $period) use ($rankExpr, $labelExpr) {
    $sub = DB::table('dashboard_pinjaman_snapshots')
        ->where('periode', $period)
        ->whereIn('cabang1', ['KC MADIUN','KC MAGETAN','KC NGAWI','KC PONOROGO'])
        ->selectRaw("account_number, cabang1, SUM(loan_balance) os, MAX($rankExpr) rnk")
        ->groupBy('account_number','cabang1');
    return DB::query()->fromSub($sub,'a')->selectRaw("account_number, cabang1, os, $labelExpr bucket");
};

$currAgg = $buildAgg($curr);
$prevAgg = $buildAgg($prev);

$movement = DB::query()
    ->fromSub($currAgg, 'curr')
    ->leftJoinSub($prevAgg,'prev', fn($j)=>$j->on('curr.account_number','=','prev.account_number'))
    ->selectRaw("
        COALESCE(prev.bucket,'New Account') as before_bucket,
        CASE
            WHEN COALESCE(prev.os,0) > 0 AND curr.os > 0 AND prev.os > curr.os THEN 'principal_reduction'
            WHEN curr.os > 0 THEN 'suplesi'
        END as metric_type,
        CASE
            WHEN COALESCE(prev.os,0)>0 AND curr.os>0 AND prev.os>curr.os THEN prev.os - curr.os
            WHEN COALESCE(prev.os,0)<=0 AND curr.os>0 THEN curr.os
            WHEN curr.os > COALESCE(prev.os,0) THEN curr.os - COALESCE(prev.os,0)
            ELSE 0
        END as amount
    ");

$exit = DB::query()
    ->fromSub($prevAgg,'prev')
    ->leftJoinSub($currAgg,'curr', fn($j)=>$j->on('prev.account_number','=','curr.account_number'))
    ->leftJoin('lw325_ph as ph', function($j) use($ph) {
        $j->on(DB::raw('TRIM(ph.acctno)'),'=','prev.account_number')
          ->where('ph.periode',$ph)
          ->where('ph.pokok','>',0);
    })
    ->whereNull('curr.account_number')
    ->whereNotNull('prev.bucket')
    ->selectRaw("
        prev.bucket as before_bucket,
        CASE WHEN ph.acctno IS NOT NULL THEN 'ph' ELSE 'lunas' END as metric_type,
        prev.os as amount
    ");

$union = $movement->unionAll($exit);
$rows = DB::query()->fromSub($union,'m')
    ->whereNotNull('metric_type')->where('amount','>',0)
    ->selectRaw('before_bucket, metric_type, SUM(amount) total')
    ->groupBy('before_bucket','metric_type')
    ->orderBy('before_bucket')->orderBy('metric_type')->get();

echo "=== MTD $prev -> $curr (4 cabang KC MADIUN/MAGETAN/NGAWI/PONOROGO) ===\n";
printf("%-14s %-22s %22s\n",'Before','Metric','Total OS');
foreach ($rows as $r) {
    printf("%-14s %-22s %22s\n", $r->before_bucket, $r->metric_type, number_format($r->total,0));
}

$tot = [];
foreach ($rows as $r) { $tot[$r->metric_type] = ($tot[$r->metric_type]??0) + $r->total; }
echo "\n=== Grand totals per metric ===\n";
foreach ($tot as $m=>$v) printf("%-22s %22s\n",$m,number_format($v,0));
