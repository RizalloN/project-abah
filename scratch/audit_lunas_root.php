<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$prev = '2026-04-30';
$curr = '2026-05-14';

// All accounts present in prev (4 cabang) but missing OR balance=0 in curr — split:
$prevSet = DB::table('dashboard_pinjaman_snapshots')
    ->where('periode',$prev)
    ->whereIn('cabang1',['KC MADIUN','KC MAGETAN','KC NGAWI','KC PONOROGO'])
    ->selectRaw('DISTINCT account_number, cabang1');

// Same account in any cabang in curr period
$missingTotally = DB::query()->fromSub($prevSet,'p')
    ->leftJoin('dashboard_pinjaman_snapshots as cAny', function($j) use($curr){
        $j->on('cAny.account_number','=','p.account_number')->where('cAny.periode',$curr);
    })
    ->whereNull('cAny.account_number')
    ->selectRaw('COUNT(DISTINCT p.account_number) cnt')
    ->first();

echo "Prev accts that COMPLETELY vanish from curr snapshot (any cabang): cnt={$missingTotally->cnt}\n";

// Of those, how many are in daily_loan_dinamis with balance>0 in curr (still active in raw)?
$missingButInRaw = DB::query()->fromSub($prevSet,'p')
    ->leftJoin('dashboard_pinjaman_snapshots as cAny', function($j) use($curr){
        $j->on('cAny.account_number','=','p.account_number')->where('cAny.periode',$curr);
    })
    ->join('daily_loan_dinamis as raw', function($j) use($curr){
        $j->whereRaw('TRIM(raw.nomor_rekening1) = p.account_number')
          ->where('raw.periode',$curr);
    })
    ->whereNull('cAny.account_number')
    ->selectRaw('COUNT(DISTINCT p.account_number) cnt, SUM(raw.baki_debet1) os, SUM(CASE WHEN raw.baki_debet1>0 THEN 1 ELSE 0 END) cnt_with_balance')
    ->first();
echo "  ... of those, present in daily_loan_dinamis curr: cnt={$missingButInRaw->cnt}, with balance>0: {$missingButInRaw->cnt_with_balance}, sum baki=" . number_format($missingButInRaw->os ?? 0,0) . "\n";

// Sample 10 disappearing L-bucket accts
echo "\nSample 10 disappearing L-bucket accts:\n";
$samples = DB::table('dashboard_pinjaman_snapshots as p')
    ->where('p.periode',$prev)->where('p.quality_bucket','L')
    ->whereIn('p.cabang1',['KC MADIUN','KC MAGETAN','KC NGAWI','KC PONOROGO'])
    ->leftJoin('dashboard_pinjaman_snapshots as c', function($j) use($curr){
        $j->on('c.account_number','=','p.account_number')->where('c.periode',$curr);
    })
    ->whereNull('c.account_number')
    ->select('p.account_number','p.cabang1','p.loan_balance')
    ->orderByDesc('p.loan_balance')->limit(10)->get();
foreach ($samples as $s) {
    $raw = DB::table('daily_loan_dinamis')
        ->where('periode',$curr)
        ->whereRaw('TRIM(nomor_rekening1)=?',[$s->account_number])
        ->select('cabang1','status_rekening1','baki_debet1','kolek')->first();
    printf("%-20s prev_cab=%-12s prev_os=%18s | curr=%s\n",
        $s->account_number, $s->cabang1, number_format($s->loan_balance,0),
        $raw ? "cab={$raw->cabang1} status={$raw->status_rekening1} kolek={$raw->kolek} baki=".number_format($raw->baki_debet1,0)
             : '(absent from daily_loan_dinamis curr)');
}
