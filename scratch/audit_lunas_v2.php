<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$prev = '2026-04-30';
$curr = '2026-05-14';

// 1) Total prev accts in 4 cabang (snapshot)
$totPrev = DB::table('dashboard_pinjaman_snapshots')
    ->where('periode',$prev)
    ->whereIn('cabang1',['KC MADIUN','KC MAGETAN','KC NGAWI','KC PONOROGO'])
    ->selectRaw('COUNT(DISTINCT account_number) cnt, SUM(loan_balance) os')->first();
echo "Prev snapshot accts (4 cab): {$totPrev->cnt}, os=".number_format($totPrev->os,0).PHP_EOL;

// 2) Total curr accts in 4 cabang (snapshot)
$totCurr = DB::table('dashboard_pinjaman_snapshots')
    ->where('periode',$curr)
    ->whereIn('cabang1',['KC MADIUN','KC MAGETAN','KC NGAWI','KC PONOROGO'])
    ->selectRaw('COUNT(DISTINCT account_number) cnt, SUM(loan_balance) os')->first();
echo "Curr snapshot accts (4 cab): {$totCurr->cnt}, os=".number_format($totCurr->os,0).PHP_EOL;

// 3) Prev accts also in curr snapshot in ANY cabang
$prevInCurrAny = DB::table('dashboard_pinjaman_snapshots as p')
    ->where('p.periode',$prev)
    ->whereIn('p.cabang1',['KC MADIUN','KC MAGETAN','KC NGAWI','KC PONOROGO'])
    ->whereExists(function($q) use($curr){
        $q->from('dashboard_pinjaman_snapshots as c')
          ->whereColumn('c.account_number','p.account_number')
          ->where('c.periode',$curr);
    })
    ->selectRaw('COUNT(DISTINCT p.account_number) cnt')->first();
echo "Prev accts also present in curr (any cabang): {$prevInCurrAny->cnt}\n";

// 4) Prev accts NOT in curr snapshot at all
$disappeared = DB::table('dashboard_pinjaman_snapshots as p')
    ->where('p.periode',$prev)
    ->whereIn('p.cabang1',['KC MADIUN','KC MAGETAN','KC NGAWI','KC PONOROGO'])
    ->whereNotExists(function($q) use($curr){
        $q->from('dashboard_pinjaman_snapshots as c')
          ->whereColumn('c.account_number','p.account_number')
          ->where('c.periode',$curr);
    })
    ->selectRaw('COUNT(DISTINCT p.account_number) cnt, SUM(p.loan_balance) os')->first();
echo "Prev accts NOT in curr snapshot at all: {$disappeared->cnt}, os=".number_format($disappeared->os,0).PHP_EOL;

// 5) Of those vanished — are they in daily_loan_dinamis curr?
$vanishedAccts = DB::table('dashboard_pinjaman_snapshots as p')
    ->where('p.periode',$prev)
    ->whereIn('p.cabang1',['KC MADIUN','KC MAGETAN','KC NGAWI','KC PONOROGO'])
    ->whereNotExists(function($q) use($curr){
        $q->from('dashboard_pinjaman_snapshots as c')
          ->whereColumn('c.account_number','p.account_number')
          ->where('c.periode',$curr);
    })
    ->select('p.account_number','p.cabang1','p.loan_balance','p.quality_bucket');

$tmpTable = 'tmp_vanished_'.uniqid();
DB::statement("CREATE TEMPORARY TABLE {$tmpTable} (account_number VARCHAR(50) PRIMARY KEY, cabang1 VARCHAR(100), prev_os DECIMAL(20,2), prev_bucket VARCHAR(20)) ENGINE=MEMORY");
DB::statement("INSERT INTO {$tmpTable} (account_number, cabang1, prev_os, prev_bucket) " . $vanishedAccts->toSql(), $vanishedAccts->getBindings());
$tmpCnt = DB::table($tmpTable)->count();
echo "TempTable filled: {$tmpCnt} rows\n";

// 5a) Of vanished — present in daily_loan_dinamis curr with balance>0?
$inRawWithBal = DB::table($tmpTable.' as t')
    ->join('daily_loan_dinamis as d', function($j) use($curr){
        $j->whereRaw('TRIM(d.nomor_rekening1)=t.account_number')->where('d.periode',$curr);
    })
    ->where('d.baki_debet1','>',0)
    ->selectRaw('COUNT(DISTINCT t.account_number) cnt, SUM(d.baki_debet1) os')->first();
echo "  Vanished accts present in daily_loan_dinamis curr WITH balance>0: cnt={$inRawWithBal->cnt}, os=".number_format($inRawWithBal->os ?? 0,0).PHP_EOL;

// 5b) Present with balance=0
$inRawZero = DB::table($tmpTable.' as t')
    ->join('daily_loan_dinamis as d', function($j) use($curr){
        $j->whereRaw('TRIM(d.nomor_rekening1)=t.account_number')->where('d.periode',$curr);
    })
    ->where(function($q){$q->where('d.baki_debet1','<=',0)->orWhereNull('d.baki_debet1');})
    ->selectRaw('COUNT(DISTINCT t.account_number) cnt')->first();
echo "  Vanished accts in raw with balance<=0: cnt={$inRawZero->cnt}\n";

// 5c) Absent from daily_loan_dinamis entirely
$absent = DB::table($tmpTable.' as t')
    ->whereNotExists(function($q) use($curr){
        $q->from('daily_loan_dinamis as d')
          ->whereRaw('TRIM(d.nomor_rekening1)=t.account_number')
          ->where('d.periode',$curr);
    })
    ->selectRaw('COUNT(*) cnt, SUM(t.prev_os) os')->first();
echo "  Vanished accts NOT in daily_loan_dinamis curr at all: cnt={$absent->cnt}, prev_os=".number_format($absent->os,0).PHP_EOL;

// 6) Among the "in raw with balance>0" — why did snapshot drop them? Maybe nomor_rekening1 NULL/empty?
$emptyInRaw = DB::table($tmpTable.' as t')
    ->join('daily_loan_dinamis as d', function($j) use($curr){
        $j->whereRaw('TRIM(d.nomor_rekening1)=t.account_number')->where('d.periode',$curr);
    })
    ->where('d.baki_debet1','>',0)
    ->selectRaw('d.status_rekening1, COUNT(*) cnt, SUM(d.baki_debet1) os')
    ->groupBy('d.status_rekening1')->get();
echo "\n  Status_rekening1 breakdown of vanished accts still in raw with balance>0:\n";
foreach ($emptyInRaw as $r) printf("    status=%-12s cnt=%6d os=%18s\n", $r->status_rekening1, $r->cnt, number_format($r->os,0));

// 6b) Same but per cabang1 in raw (to see if cabang differs from snapshot prev)
$cabBreakdown = DB::table($tmpTable.' as t')
    ->join('daily_loan_dinamis as d', function($j) use($curr){
        $j->whereRaw('TRIM(d.nomor_rekening1)=t.account_number')->where('d.periode',$curr);
    })
    ->where('d.baki_debet1','>',0)
    ->selectRaw('t.cabang1 as prev_cab, d.cabang1 as curr_cab, COUNT(*) cnt')
    ->groupBy('t.cabang1','d.cabang1')->get();
echo "\n  Vanished accts with balance>0 — prev_cab vs curr_cab:\n";
foreach ($cabBreakdown as $r) printf("    prev=%-12s curr=%-30s cnt=%d\n", $r->prev_cab, $r->curr_cab ?? '(null)', $r->cnt);
