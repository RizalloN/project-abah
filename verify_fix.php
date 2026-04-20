<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Verifying Fixed Snapshot Data ===\n\n";

$rows = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', '2026-04-18')
    ->whereRaw('kanca_key = unit_key')
    ->select(
        'kanca_key',
        'briguna_mikro_os', 'kupedes_os', 'kur_mikro_os', 'kur_kecil_os', 'kur_kpp_os',
        'giro_mikro', 'deposito_mikro', 'tabungan_mikro'
    )
    ->orderBy('kanca_key')
    ->get();

echo "Summary Rows (kanca_key === unit_key): " . count($rows) . "\n\n";

foreach ($rows as $row) {
    echo "📍 {$row->kanca_key}\n";
    echo "   Loans: briguna=" . number_format($row->briguna_mikro_os, 0) .
         " | kupedes=" . number_format($row->kupedes_os, 0) .
         " | kur_mikro=" . number_format($row->kur_mikro_os, 0) .
         " | kur_kecil=" . number_format($row->kur_kecil_os, 0) . "\n";
    echo "   Savings: giro=" . number_format($row->giro_mikro, 0) .
         " | deposito=" . number_format($row->deposito_mikro, 0) .
         " | tabungan=" . number_format($row->tabungan_mikro, 0) . "\n\n";
}

echo "✅ If values above show numbers instead of 0, the fix is working!\n";
