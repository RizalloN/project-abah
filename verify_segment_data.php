<?php

require 'bootstrap/autoload.php';
$app = require_once 'bootstrap/app.php';

use Illuminate\Support\Facades\DB;

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$branches = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];

// Get latest period
$latestPeriod = DB::table('dashboard_harian_snapshots')
    ->orderBy('snapshot_period', 'DESC')
    ->limit(1)
    ->value('snapshot_period');

echo "=== VERIFYING SEGMENT DATA ===\n";
echo "Latest Period: {$latestPeriod}\n\n";

// Check for SME data
echo "--- SME SEGMENT ---\n";
$smeData = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $latestPeriod)
    ->whereIn('kanca_label', $branches)
    ->whereRaw('unit_label = kanca_label')
    ->select([
        'kanca_label',
        'kecil_non_cashcoll_os', 'cashcoll_os',
        'kecil_non_cashcoll_sml', 'cashcoll_sml',
        'kecil_non_cashcoll_npl', 'cashcoll_npl'
    ])
    ->get();

echo "Count: " . count($smeData) . "\n";
foreach ($smeData as $row) {
    echo "  {$row->kanca_label}: OS=" . number_format($row->kecil_non_cashcoll_os + $row->cashcoll_os, 0) 
        . " SML=" . number_format($row->kecil_non_cashcoll_sml + $row->cashcoll_sml, 0)
        . " NPL=" . number_format($row->kecil_non_cashcoll_npl + $row->cashcoll_npl, 0) . "\n";
}

// Check for Konsumer data
echo "\n--- KONSUMER SEGMENT ---\n";
$konsumerData = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $latestPeriod)
    ->whereIn('kanca_label', $branches)
    ->whereRaw('unit_label = kanca_label')
    ->select([
        'kanca_label',
        'briguna_konsumer_os', 'kpr_os',
        'briguna_konsumer_sml', 'kpr_sml',
        'briguna_konsumer_npl', 'kpr_npl'
    ])
    ->get();

echo "Count: " . count($konsumerData) . "\n";
foreach ($konsumerData as $row) {
    echo "  {$row->kanca_label}: OS=" . number_format($row->briguna_konsumer_os + $row->kpr_os, 0)
        . " SML=" . number_format($row->briguna_konsumer_sml + $row->kpr_sml, 0)
        . " NPL=" . number_format($row->briguna_konsumer_npl + $row->kpr_npl, 0) . "\n";
}

// Check for Micro data
echo "\n--- MICRO SEGMENT ---\n";
$microData = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $latestPeriod)
    ->whereIn('kanca_label', $branches)
    ->whereRaw('unit_label = kanca_label')
    ->select([
        'kanca_label',
        'briguna_mikro_os', 'kupedes_os', 'kur_mikro_os', 'kur_kecil_os', 'kur_kpp_os',
        'briguna_mikro_sml', 'kupedes_sml', 'kur_mikro_sml', 'kur_kecil_sml', 'kur_kpp_sml',
        'briguna_mikro_npl', 'kupedes_npl', 'kur_mikro_npl', 'kur_kecil_npl', 'kur_kpp_npl'
    ])
    ->get();

echo "Count: " . count($microData) . "\n";
foreach ($microData as $row) {
    $os_total = $row->briguna_mikro_os + $row->kupedes_os + $row->kur_mikro_os + $row->kur_kecil_os + $row->kur_kpp_os;
    $sml_total = $row->briguna_mikro_sml + $row->kupedes_sml + $row->kur_mikro_sml + $row->kur_kecil_sml + $row->kur_kpp_sml;
    $npl_total = $row->briguna_mikro_npl + $row->kupedes_npl + $row->kur_mikro_npl + $row->kur_kecil_npl + $row->kur_kpp_npl;
    
    echo "  {$row->kanca_label}: OS=" . number_format($os_total, 0)
        . " SML=" . number_format($sml_total, 0)
        . " NPL=" . number_format($npl_total, 0) . "\n";
}

echo "\n=== DATA AVAILABILITY CHECK ===\n";
echo "SME: " . (count($smeData) === 4 && $smeData->sum(function($r) { return $r->kecil_non_cashcoll_os + $r->cashcoll_os; }) > 0 ? "✓ OK" : "✗ ISSUE") . "\n";
echo "Konsumer: " . (count($konsumerData) === 4 && $konsumerData->sum(function($r) { return $r->briguna_konsumer_os + $r->kpr_os; }) > 0 ? "✓ OK" : "✗ ISSUE") . "\n";
echo "Micro: " . (count($microData) === 4 && $microData->sum(function($r) { return $r->briguna_mikro_os + $r->kupedes_os + $r->kur_mikro_os + $r->kur_kecil_os + $r->kur_kpp_os; }) > 0 ? "✓ OK" : "✗ ISSUE") . "\n";
