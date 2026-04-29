<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$year = 2026;
$monthCol = 'apr';

$totalDanaTotal = DB::table('rka')
    ->whereYear('created_at', $year)
    ->where(function($q) {
        $q->where('kanca', 'like', '%MADIUN%')
          ->orWhere('desc_uker', 'like', '%MADIUN%');
    })
    ->where('mata_anggaran', 'A. DANA TOTAL')
    ->sum($monthCol);

echo "Sum of 'A. DANA TOTAL' for MADIUN units: " . number_format($totalDanaTotal, 3, '.', ',') . "\n";

$sumRetailFunding = DB::table('rka')
    ->whereYear('created_at', $year)
    ->where(function($q) {
        $q->where('kanca', 'like', '%MADIUN%')
          ->orWhere('desc_uker', 'like', '%MADIUN%');
    })
    ->where('mata_anggaran', 'A.1. DPK Retail Funding Total')
    ->sum($monthCol);

$sumKorporasi = DB::table('rka')
    ->whereYear('created_at', $year)
    ->where(function($q) {
        $q->where('kanca', 'like', '%MADIUN%')
          ->orWhere('desc_uker', 'like', '%MADIUN%');
    })
    ->where('mata_anggaran', 'A.2. DPK Korporasi')
    ->sum($monthCol);

echo "Sum of 'A.1. DPK Retail Funding Total': " . number_format($sumRetailFunding, 3, '.', ',') . "\n";
echo "Sum of 'A.2. DPK Korporasi': " . number_format($sumKorporasi, 3, '.', ',') . "\n";
echo "Total (A.1 + A.2): " . number_format($sumRetailFunding + $sumKorporasi, 3, '.', ',') . "\n";
