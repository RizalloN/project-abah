<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$year = 2026;
$monthCol = 'apr';

// Search for anything related to MADIUN in kanca or desc_uker
$rows = DB::table('rka')
    ->whereYear('created_at', $year)
    ->where(function($q) {
        $q->where('kanca', 'like', '%MADIUN%')
          ->orWhere('desc_uker', 'like', '%MADIUN%');
    })
    ->get();

$summary = [
    'Tabungan' => 0,
    'Deposito' => 0,
    'Giro' => 0
];

echo "Detailed RKA rows for MADIUN (April 2026):\n";
echo str_repeat('-', 100) . "\n";
printf("%-40s | %-40s | %15s\n", "Mata Anggaran", "Desc Uker", "Value (Apr)");
echo str_repeat('-', 100) . "\n";

foreach ($rows as $row) {
    $ma = $row->mata_anggaran;
    $uker = $row->desc_uker;
    $val = (float) $row->{$monthCol};
    
    if ($val == 0) continue;

    printf("%-40s | %-40s | %15.2f\n", $ma, $uker, $val);

    if (str_contains($ma, 'Tabungan')) {
        $summary['Tabungan'] += $val;
    } elseif (str_contains($ma, 'Deposito')) {
        $summary['Deposito'] += $val;
    } elseif (str_contains($ma, 'Giro')) {
        $summary['Giro'] += $val;
    }
}

echo "\nSummary Totals (All Madiun rows):\n";
foreach ($summary as $k => $v) {
    echo "$k: " . number_format($v, 3, '.', ',') . "\n";
}
