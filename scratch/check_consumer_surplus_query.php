<?php

define('LARAVEL_START', microtime(true));
require 'd:/XAMPP/htdocs/project-ABAH/vendor/autoload.php';
$app = require_once 'd:/XAMPP/htdocs/project-ABAH/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$realisasiDateColumn = Schema::hasColumn('daily_loan_dinamis', 'tgl_realisasi1') ? 'tgl_realisasi1' : 'tgl_realisasi';
$period = '2026-02-28';
$previousPeriod = '2026-01-31';
$periodStart = '2026-02-01';

$rmName = 'RONA ROHANA TALIBATA';
$rmKeys = ['00187063']; // Wait, what is the normalized RM key for RONA? Let's check the database.
$rmNormalizedList = DB::table('daily_loan_dinamis')
    ->where('rm_normalized', 'like', '%RONA%')
    ->select('rm_normalized')
    ->distinct()
    ->pluck('rm_normalized')
    ->all();

echo "RM normalized keys found: " . implode(', ', $rmNormalizedList) . "\n";

foreach ($rmNormalizedList as $rmKey) {
    // Run the subquery logic
    $currentBaseQuery = DB::table('daily_loan_dinamis as d')
        ->where('d.periode', $period)
        ->where('d.segmen_kinerja', 'CONSUMER')
        ->whereIn('d.produk_kinerja', ['BRIGUNAKONSUMER', 'KPR'])
        ->where('d.rm_normalized', $rmKey)
        ->whereNotNull('d.pn_pengelola1')
        ->where('d.pn_pengelola1', '<>', '')
        ->whereNotNull('d.nomor_rekening1')
        ->where('d.nomor_rekening1', '<>', '')
        ->whereBetween($realisasiDateColumn, [$periodStart, $period])
        ->select([
            'd.cabang_normalized as cabang',
            'd.unit_normalized as unit',
            'd.branch_normalized as branch_code',
            'd.rm_normalized as rm',
            'd.produk_kinerja as produk',
            'd.nomor_rekening1 as account_key',
            'd.plafon',
            'd.baki_debet1'
        ]);

    $currentBase = $currentBaseQuery->get();
    echo "Current Base count for $rmKey: " . $currentBase->count() . "\n";

    // Let's compute previous OS
    $previousOsQuery = DB::table('daily_loan_dinamis as d')
        ->where('d.periode', $previousPeriod)
        ->where('d.segmen_kinerja', 'CONSUMER')
        ->whereIn('d.produk_kinerja', ['BRIGUNAKONSUMER', 'KPR'])
        ->whereNotNull('d.nomor_rekening1')
        ->where('d.nomor_rekening1', '<>', '')
        ->groupBy('account_key')
        ->selectRaw("UPPER(TRIM(nomor_rekening1)) as account_key, SUM(COALESCE(baki_debet1, 0)) as previous_os");
    
    $previousOs = $previousOsQuery->pluck('previous_os', 'account_key')->all();

    $surplusDeb = 0;
    $surplusOs = 0.0;
    foreach ($currentBase as $row) {
        $acc = strtoupper(trim($row->account_key));
        $prevVal = $previousOs[$acc] ?? null;
        $currentOs = (float) $row->baki_debet1;
        $plafon = (float) $row->plafon;

        if ($prevVal === null) {
            $totalRealization = max(0.0, $plafon);
        } elseif ($currentOs - $prevVal > 0.0) {
            $totalRealization = max(0.0, $plafon - $prevVal);
        } else {
            $totalRealization = 0.0;
        }

        if ($totalRealization > 0.0) {
            $surplusDeb++;
            $surplusOs += $totalRealization;
        }
    }

    echo "Computed surplus_deb: $surplusDeb, surplus_os: $surplusOs\n";
}
