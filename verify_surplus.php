<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$period = '2026-05-19';
$previousPeriod = '2026-04-30';

echo "=== FINDING RMS WITH POSITIVE CONSUMER SURPLUS ON $period ===\n\n";

$productSql = "CASE WHEN produk_kinerja = 'BRIGUNAKONSUMER' THEN 'BRIGUNA-KONSUMER' ELSE produk_kinerja END";

$previousPlafonByGroup = DB::table('daily_loan_dinamis')
    ->where('periode', $previousPeriod)
    ->whereIn('segmen_kinerja', ['CONSUMER'])
    ->whereIn('produk_kinerja', ['BRIGUNAKONSUMER', 'KPR'])
    ->whereNotNull('pn_pengelola1')
    ->where('pn_pengelola1', '<>', '')
    ->selectRaw("COALESCE(cabang_normalized, UPPER(TRIM(cabang1)), '') as cabang")
    ->selectRaw("COALESCE(unit_normalized, UPPER(TRIM(unit1)), '') as unit")
    ->selectRaw("COALESCE(branch_normalized, '') as branch_code")
    ->selectRaw("COALESCE(rm_normalized, UPPER(TRIM(pn_pengelola1)), '') as rm")
    ->selectRaw("{$productSql} as produk")
    ->selectRaw('SUM(COALESCE(plafon, 0)) as plafon')
    ->groupByRaw("COALESCE(cabang_normalized, UPPER(TRIM(cabang1)), ''), COALESCE(unit_normalized, UPPER(TRIM(unit1)), ''), COALESCE(branch_normalized, ''), COALESCE(rm_normalized, UPPER(TRIM(pn_pengelola1)), ''), {$productSql}")
    ->get()
    ->mapWithKeys(fn ($row): array => [
        implode('|', [
            (string) ($row->cabang ?? ''),
            (string) ($row->unit ?? ''),
            (string) ($row->branch_code ?? ''),
            (string) ($row->rm ?? ''),
            (string) ($row->produk ?? ''),
        ]) => (float) $row->plafon,
    ]);

$currentRows = DB::table('daily_loan_dinamis')
    ->where('periode', $period)
    ->whereIn('segmen_kinerja', ['CONSUMER'])
    ->whereIn('produk_kinerja', ['BRIGUNAKONSUMER', 'KPR'])
    ->whereNotNull('pn_pengelola1')
    ->where('pn_pengelola1', '<>', '')
    ->selectRaw("COALESCE(cabang_normalized, UPPER(TRIM(cabang1)), '') as cabang")
    ->selectRaw("COALESCE(unit_normalized, UPPER(TRIM(unit1)), '') as unit")
    ->selectRaw("COALESCE(branch_normalized, '') as branch_code")
    ->selectRaw("COALESCE(rm_normalized, UPPER(TRIM(pn_pengelola1)), '') as rm")
    ->selectRaw("{$productSql} as produk")
    ->selectRaw('COUNT(DISTINCT nomor_rekening1) as debitur')
    ->selectRaw('SUM(COALESCE(plafon, 0)) as plafon')
    ->groupByRaw("COALESCE(cabang_normalized, UPPER(TRIM(cabang1)), ''), COALESCE(unit_normalized, UPPER(TRIM(unit1)), ''), COALESCE(branch_normalized, ''), COALESCE(rm_normalized, UPPER(TRIM(pn_pengelola1)), ''), {$productSql}")
    ->get();

$surplusCount = 0;
foreach ($currentRows as $row) {
    $key = implode('|', [
        (string) ($row->cabang ?? ''),
        (string) ($row->unit ?? ''),
        (string) ($row->branch_code ?? ''),
        (string) ($row->rm ?? ''),
        (string) ($row->produk ?? ''),
    ]);

    if (!$previousPlafonByGroup->has($key)) {
        continue;
    }

    $previous = (float) $previousPlafonByGroup[$key];
    if ($previous <= 0.0) {
        continue;
    }

    $current = (float) ($row->plafon ?? 0);
    $surplus = $current - $previous;
    if ($surplus > 0.0) {
        echo "RM: {$row->rm} | Cabang: {$row->cabang} | Prod: {$row->produk} | Prev Plafon: $previous | Curr Plafon: $current | Surplus: $surplus\n";
        $surplusCount++;
    }
}

echo "\nTotal groups with surplus: $surplusCount\n";
