<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$year = 2026;
$monthCol = 'apr';

$q = DB::table('rka')
    ->whereYear('created_at', $year)
    ->where(function($q) {
        $q->where('kanca', 'like', '%MADIUN%')
          ->orWhere('desc_uker', 'like', '%MADIUN%');
    });

$simpanan_ritel = (clone $q)->where('mata_anggaran', 'A.1. DPK Retail Funding Total')->where('desc_uker', 'not like', '%UNIT%')->sum($monthCol);
$simpanan_mikro = (clone $q)->where('mata_anggaran', 'A.1. DPK Retail Funding Total')->where('desc_uker', 'like', '%UNIT%')->sum($monthCol);
$simpanan_wholesale = (clone $q)->where('mata_anggaran', 'A.2. DPK Korporasi')->sum($monthCol);

echo "RKA Segmentation for MADIUN (Apr 2026):\n";
echo "Simpanan Ritel (A.1 - KC/KCP): " . number_format($simpanan_ritel, 2) . "\n";
echo "Simpanan Mikro (A.1 - UNIT): " . number_format($simpanan_mikro, 2) . "\n";
echo "Simpanan Wholesale (A.2): " . number_format($simpanan_wholesale, 2) . "\n";
echo "TOTAL: " . number_format($simpanan_ritel + $simpanan_mikro + $simpanan_wholesale, 2) . "\n";
