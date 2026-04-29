<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$year = 2026;
$monthCol = 'apr';

// Load all rows related to Simpanan/DPK/Funding
$rows = DB::table('rka')
    ->whereYear('created_at', $year)
    ->where(function($query) {
        $query->where('mata_anggaran', 'like', '%Funding%')
              ->orWhere('mata_anggaran', 'like', '%Korporasi%')
              ->orWhere('mata_anggaran', 'like', '%Giro%')
              ->orWhere('mata_anggaran', 'like', '%Tabungan%')
              ->orWhere('mata_anggaran', 'like', '%Deposito%');
    })
    ->get();

$results = [];

foreach ($rows as $row) {
    $uker = strtoupper(trim($row->desc_uker));
    $kanca = strtoupper(trim($row->kanca));
    $ma = trim($row->mata_anggaran);
    $val = (float) $row->{$monthCol};
    
    if ($val == 0) continue;
    
    $segment = 'RETAIL';
    if (str_contains($uker, 'UNIT')) {
        $segment = 'MICRO';
    } elseif (str_contains($ma, 'Korporasi')) {
        $segment = 'WHOLESALE';
    }
    
    $sub_segment = 'OTHER';
    if (str_contains($ma, 'Giro')) {
        $sub_segment = 'GIRO';
    } elseif (str_contains($ma, 'Tabungan')) {
        $sub_segment = 'TABUNGAN';
    } elseif (str_contains($ma, 'Deposito')) {
        $sub_segment = 'DEPOSITO';
    }
    
    if ($sub_segment === 'OTHER') continue;

    $results[$uker]['KANCA'] = $kanca;
    $results[$uker]['SEGMENTS'][$segment][$sub_segment] = ($results[$uker]['SEGMENTS'][$segment][$sub_segment] ?? 0) + $val;
}

// Format Output
$output = [];
foreach ($results as $uker => $data) {
    foreach ($data['SEGMENTS'] as $seg => $subs) {
        $giro = $subs['GIRO'] ?? 0;
        $tab = $subs['TABUNGAN'] ?? 0;
        $dep = $subs['DEPOSITO'] ?? 0;
        $total = $giro + $tab + $dep;
        
        $output[] = [
            'UKER' => $uker,
            'KANCA' => $data['KANCA'],
            'SEGMENT' => $seg,
            'GIRO' => $giro,
            'TABUNGAN' => $tab,
            'DEPOSITO' => $dep,
            'TOTAL' => $total
        ];
    }
}

echo json_encode($output, JSON_PRETTY_PRINT);
