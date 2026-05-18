<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$prev = '2026-04-30';
$curr = '2026-05-14';
$ph   = '2026-05-14';

// Per-account aggregated snapshots (worst bucket + sum balance)
$rankExpr = "CASE quality_bucket
    WHEN 'L' THEN 0 WHEN 'LR' THEN 1
    WHEN 'DPK 1' THEN 2 WHEN 'DPK 2' THEN 3 WHEN 'DPK 3' THEN 4
    WHEN 'KL' THEN 5 WHEN 'D1' THEN 6 WHEN 'D2' THEN 7 WHEN 'M' THEN 8 ELSE NULL END";

$prevAgg = DB::table('dashboard_pinjaman_snapshots')
    ->where('periode', $prev)
    ->whereIn('cabang1', ['KC MADIUN','KC MAGETAN','KC NGAWI','KC PONOROGO'])
    ->selectRaw("account_number, cabang1, SUM(loan_balance) os, MAX($rankExpr) rnk")
    ->groupBy('account_number','cabang1');

$currAccts = DB::table('dashboard_pinjaman_snapshots')
    ->where('periode', $curr)
    ->whereIn('cabang1', ['KC MADIUN','KC MAGETAN','KC NGAWI','KC PONOROGO'])
    ->selectRaw('DISTINCT account_number');

$disappeared = DB::query()->fromSub($prevAgg, 'p')
    ->leftJoinSub($currAccts,'c', fn($j)=>$j->on('p.account_number','=','c.account_number'))
    ->whereNull('c.account_number')
    ->selectRaw('COUNT(*) cnt, SUM(p.os) os')
    ->first();

echo "Accts in prev ($prev) but missing in curr ($curr): cnt={$disappeared->cnt}, os=" . number_format($disappeared->os,0) . "\n";

// How many of those are PH?
$inPh = DB::query()->fromSub($prevAgg,'p')
    ->leftJoinSub($currAccts,'c', fn($j)=>$j->on('p.account_number','=','c.account_number'))
    ->join('lw325_ph as ph', function($j) use($ph) {
        $j->on(DB::raw('TRIM(ph.acctno)'),'=','p.account_number')
          ->where('ph.periode',$ph)->where('ph.pokok','>',0);
    })
    ->whereNull('c.account_number')
    ->selectRaw('COUNT(*) cnt, SUM(p.os) os')->first();

echo "  ... and present in PH ($ph): cnt={$inPh->cnt}, os=" . number_format($inPh->os,0) . "\n";
echo "  ... so 'lunas' bucket = disappeared MINUS in-PH = " . number_format($disappeared->os - $inPh->os, 0) . "\n";

// By before_bucket
$labelExpr = "CASE p.rnk
    WHEN 0 THEN 'L' WHEN 1 THEN 'LR' WHEN 2 THEN 'DPK 1' WHEN 3 THEN 'DPK 2' WHEN 4 THEN 'DPK 3'
    WHEN 5 THEN 'KL' WHEN 6 THEN 'D1' WHEN 7 THEN 'D2' WHEN 8 THEN 'M' END";

$byBucket = DB::query()->fromSub($prevAgg,'p')
    ->leftJoinSub($currAccts,'c', fn($j)=>$j->on('p.account_number','=','c.account_number'))
    ->whereNull('c.account_number')
    ->selectRaw("$labelExpr as bucket, COUNT(*) cnt, SUM(p.os) os")
    ->groupByRaw($labelExpr)->orderByRaw($labelExpr)->get();

echo "\nDisappeared accounts by previous bucket:\n";
foreach ($byBucket as $r) printf("%-10s %6d %22s\n", $r->bucket, $r->cnt, number_format($r->os,0));
