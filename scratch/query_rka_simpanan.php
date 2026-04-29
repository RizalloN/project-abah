<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Support\RkaLookupService;

$service = app(RkaLookupService::class);
$year = 2026;
$monthCol = 'apr';

$definitions = [
    // RETAIL / MICRO (Depends on UKER)
    'RETAIL_GIRO' => ['mata_anggaran' => ['Giro Retail Funding Total']],
    'RETAIL_TABUNGAN' => ['mata_anggaran' => ['Tabungan Retail Funding Total']],
    'RETAIL_DEPOSITO' => ['mata_anggaran' => ['Deposito Retail Funding Total']],
    
    // WHOLESALE
    'WHOLESALE_GIRO' => ['mata_anggaran' => ['A.2.a. Giro Korporasi']],
    'WHOLESALE_DEPOSITO' => ['mata_anggaran' => ['A.2.b. Deposito Korporasi']],
    'WHOLESALE_TOTAL' => ['mata_anggaran' => ['A.2. DPK Korporasi']],
];

// Load all rows
$rows = DB::table('rka')
    ->select('kanca', 'desc_uker', 'mata_anggaran', $monthCol)
    ->whereYear('created_at', $year)
    ->get();

$results = [];

foreach ($rows as $row) {
    $uker = strtoupper(trim($row->desc_uker));
    $kanca = strtoupper(trim($row->kanca));
    $ma = trim($row->mata_anggaran);
    $val = (float) $row->{$monthCol};
    
    if ($val == 0) continue;
    
    $segment = 'UNKNOWN';
    $sub_segment = 'UNKNOWN';
    
    // Logic for Segment
    if (str_contains($uker, 'UNIT')) {
        $segment = 'MICRO';
    } elseif (str_contains($ma, 'Korporasi') || str_contains($ma, 'Wholesale')) {
        $segment = 'WHOLESALE';
    } else {
        $segment = 'RETAIL';
    }
    
    // Logic for Sub-segment
    if (str_contains($ma, 'Giro')) {
        $sub_segment = 'GIRO';
    } elseif (str_contains($ma, 'Tabungan')) {
        $sub_segment = 'TABUNGAN';
    } elseif (str_contains($ma, 'Deposito')) {
        $sub_segment = 'DEPOSITO';
    }
    
    if ($segment !== 'UNKNOWN' && $sub_segment !== 'UNKNOWN') {
        $results[$uker]['KANCA'] = $kanca;
        $results[$uker][$segment][$sub_segment] = ($results[$uker][$segment][$sub_segment] ?? 0) + $val;
    }
}

// Print header
echo "UKER | KANCA | SEGMENT | GIRO | TABUNGAN | DEPOSITO | TOTAL\n";
echo str_repeat('-', 80) . "\n";

foreach ($results as $uker => $data) {
    foreach (['RETAIL', 'MICRO', 'WHOLESALE'] as $seg) {
        if (isset($data[$seg])) {
            $giro = $data[$seg]['GIRO'] ?? 0;
            $tab = $data[$seg]['TABUNGAN'] ?? 0;
            $dep = $data[$seg]['DEPOSITO'] ?? 0;
            $total = $giro + $tab + $dep;
            
            if ($total > 0) {
                printf("%-30s | %-15s | %-10s | %10.2f | %10.2f | %10.2f | %10.2f\n", 
                    $uker, $data['KANCA'], $seg, $giro, $tab, $dep, $total);
            }
        }
    }
}
