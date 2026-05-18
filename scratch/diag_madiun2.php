<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$curr = '2026-05-14';
$branch = 'KC Madiun';

// All rows including null/empty rekening
$allRows = DB::table('daily_loan_dinamis')
    ->where('periode', $curr)
    ->where('cabang1', $branch)
    ->sum('baki_debet1');
echo "Madiun $curr SUM(baki_debet1) NO rek-filter = " . number_format($allRows, 0) . PHP_EOL;

$nullRek = DB::table('daily_loan_dinamis')
    ->where('periode', $curr)
    ->where('cabang1', $branch)
    ->where(function ($q) {
        $q->whereNull('nomor_rekening1')->orWhere('nomor_rekening1', '');
    })
    ->selectRaw('COUNT(*) as n, COALESCE(SUM(baki_debet1), 0) as total')
    ->first();
echo "Madiun $curr null/empty rek: n={$nullRek->n} total=" . number_format($nullRek->total, 0) . PHP_EOL;

// Group by segmen for context
echo PHP_EOL . "Per segmen $curr Madiun:" . PHP_EOL;
$segRows = DB::table('daily_loan_dinamis')
    ->where('periode', $curr)
    ->where('cabang1', $branch)
    ->selectRaw('COALESCE(segmen_dashboard, "(null)") as seg, COUNT(*) as n, SUM(baki_debet1) as total')
    ->groupBy('seg')
    ->orderByDesc('total')
    ->get();
foreach ($segRows as $r) {
    echo sprintf("  %-30s n=%6d total=%s" . PHP_EOL, $r->seg, $r->n, number_format($r->total, 0));
}

// Per produk
echo PHP_EOL . "Per produk $curr Madiun:" . PHP_EOL;
$prodRows = DB::table('daily_loan_dinamis')
    ->where('periode', $curr)
    ->where('cabang1', $branch)
    ->selectRaw('COALESCE(produk_dashboard, "(null)") as prod, COUNT(*) as n, SUM(baki_debet1) as total')
    ->groupBy('prod')
    ->orderByDesc('total')
    ->get();
foreach ($prodRows as $r) {
    echo sprintf("  %-30s n=%6d total=%s" . PHP_EOL, $r->prod, $r->n, number_format($r->total, 0));
}

// kolek distribution at row-level (no bucket logic)
echo PHP_EOL . "Per kolek raw $curr Madiun:" . PHP_EOL;
$kolekRows = DB::table('daily_loan_dinamis')
    ->where('periode', $curr)
    ->where('cabang1', $branch)
    ->selectRaw('COALESCE(kolek, "(null)") as k, COUNT(*) as n, SUM(baki_debet1) as total')
    ->groupBy('k')
    ->orderBy('k')
    ->get();
foreach ($kolekRows as $r) {
    echo sprintf("  kolek=%-8s n=%6d total=%s" . PHP_EOL, $r->k, $r->n, number_format($r->total, 0));
}

// Excel kolom totals per cabang Madiun (from screenshots)
echo PHP_EOL . "Excel grand total per kolom Madiun curr:" . PHP_EOL;
$excel = [
    'L'     => 3756583500255,
    'LR'    => 85720661617,
    'SML 1' => 374720476921,
    'SML 2' => 56988657326,
    'SML 3' => 83473048780,
    'KL'    => 31096203573,
    'D1'    => 18952356510,
    'D2'    => 17481872714,
    'M'     => 149012403205,
];
$excelTotal = 0;
foreach ($excel as $b => $v) {
    echo sprintf("  %-6s %s" . PHP_EOL, $b, number_format($v, 0));
    $excelTotal += $v;
}
echo "  GrandTotal " . number_format($excelTotal, 0) . PHP_EOL;
