<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$period = '2026-05-19';

echo "=== VERIFYING SNAPSHOT VS DAILY LOAN DINAMIS FOR $period ===\n\n";

// Segment CONSUMER
echo "--- Segment CONSUMER ---\n";
// Let's get one RM from snapshot
$snapshotConsumer = DB::table('performance_rm_snapshots')
    ->where('periode', $period)
    ->where('segmen', 'CONSUMER')
    ->first();

if ($snapshotConsumer) {
    $rm = $snapshotConsumer->rm;
    $cabang = $snapshotConsumer->cabang;
    $produk = $snapshotConsumer->produk;
    
    echo "Found RM in Snapshot: $rm | Branch: $cabang | Product: $produk\n";
    echo "Snapshot Values: loan_os={$snapshotConsumer->loan_os}, lancar_os={$snapshotConsumer->lancar_os}, sml_os={$snapshotConsumer->sml_os}, npl_os={$snapshotConsumer->npl_os}, restruk_os={$snapshotConsumer->restruk_os}, total_deb={$snapshotConsumer->total_deb}\n";
    
    // Query directly from daily_loan_dinamis
    // Mapping:
    // d.segmen_kinerja = 'CONSUMER' AND d.produk_kinerja IN ('BRIGUNA-KONSUMER', 'KPR')
    // Wait, let's see how the products are mapped for CONSUMER
    $direct = DB::table('daily_loan_dinamis')
        ->where('periode', $period)
        ->where('rm_normalized', $rm)
        ->where('cabang_normalized', $cabang)
        ->where('segmen_kinerja', 'CONSUMER')
        ->whereIn('produk_kinerja', ['BRIGUNAKONSUMER', 'KPR']) // In daily_loan_dinamis, produk_kinerja is BRIGUNAKONSUMER or KPR
        ->selectRaw('SUM(COALESCE(baki_debet1, 0)) as loan_os')
        ->selectRaw('SUM(CASE WHEN kolek = 1 THEN COALESCE(baki_debet1, 0) ELSE 0 END) as lancar_os')
        ->selectRaw('SUM(CASE WHEN kolek = 2 THEN COALESCE(baki_debet1, 0) ELSE 0 END) as sml_os')
        ->selectRaw('SUM(CASE WHEN kolek > 2 THEN COALESCE(baki_debet1, 0) ELSE 0 END) as npl_os')
        ->selectRaw("SUM(CASE WHEN kolek = 1 AND COALESCE(flag_restruk, '') = 'Y' THEN COALESCE(baki_debet1, 0) ELSE 0 END) as restruk_os")
        ->selectRaw('COUNT(DISTINCT nomor_rekening1) as total_deb')
        ->first();
        
    echo "Direct Query Values: loan_os={$direct->loan_os}, lancar_os={$direct->lancar_os}, sml_os={$direct->sml_os}, npl_os={$direct->npl_os}, restruk_os={$direct->restruk_os}, total_deb={$direct->total_deb}\n";
    
    if (
        (float)$snapshotConsumer->loan_os === (float)$direct->loan_os &&
        (float)$snapshotConsumer->lancar_os === (float)$direct->lancar_os &&
        (float)$snapshotConsumer->sml_os === (float)$direct->sml_os &&
        (float)$snapshotConsumer->npl_os === (float)$direct->npl_os &&
        (float)$snapshotConsumer->restruk_os === (float)$direct->restruk_os &&
        (int)$snapshotConsumer->total_deb === (int)$direct->total_deb
    ) {
        echo "=> SUCCESS: CONSUMER segment matches perfectly!\n";
    } else {
        echo "=> ERROR: CONSUMER segment mismatch!\n";
    }
} else {
    echo "No CONSUMER snapshot found for $period\n";
}

echo "\n--- Segment SMALL ---\n";
// Let's get one RM from snapshot for SMALL
$snapshotSmall = DB::table('performance_rm_snapshots')
    ->where('periode', $period)
    ->where('segmen', 'SMALL')
    ->first();

if ($snapshotSmall) {
    $rm = $snapshotSmall->rm;
    $cabang = $snapshotSmall->cabang;
    $produk = $snapshotSmall->produk;
    
    echo "Found RM in Snapshot: $rm | Branch: $cabang | Product: $produk\n";
    echo "Snapshot Values: loan_os={$snapshotSmall->loan_os}, lancar_os={$snapshotSmall->lancar_os}, sml_os={$snapshotSmall->sml_os}, npl_os={$snapshotSmall->npl_os}, restruk_os={$snapshotSmall->restruk_os}, total_deb={$snapshotSmall->total_deb}\n";
    
    // Query directly from daily_loan_dinamis for SMALL
    $direct = DB::table('daily_loan_dinamis')
        ->where('periode', $period)
        ->where('rm_normalized', $rm)
        ->where('cabang_normalized', $cabang)
        ->where('segmen_kinerja', 'SMALL')
        ->whereIn('produk_kinerja', ['COMMERCIAL', 'CASHCALL', 'CASHCOLLATERAL', 'CASHCOLL', 'SMALL'])
        ->selectRaw('SUM(COALESCE(baki_debet1, 0)) as loan_os')
        ->selectRaw('SUM(CASE WHEN kolek = 1 THEN COALESCE(baki_debet1, 0) ELSE 0 END) as lancar_os')
        ->selectRaw('SUM(CASE WHEN kolek = 2 THEN COALESCE(baki_debet1, 0) ELSE 0 END) as sml_os')
        ->selectRaw('SUM(CASE WHEN kolek > 2 THEN COALESCE(baki_debet1, 0) ELSE 0 END) as npl_os')
        ->selectRaw("SUM(CASE WHEN kolek = 1 AND COALESCE(flag_restruk, '') = 'Y' THEN COALESCE(baki_debet1, 0) ELSE 0 END) as restruk_os")
        ->selectRaw('COUNT(DISTINCT nomor_rekening1) as total_deb')
        ->first();
        
    echo "Direct Query Values: loan_os={$direct->loan_os}, lancar_os={$direct->lancar_os}, sml_os={$direct->sml_os}, npl_os={$direct->npl_os}, restruk_os={$direct->restruk_os}, total_deb={$direct->total_deb}\n";
    
    if (
        (float)$snapshotSmall->loan_os === (float)$direct->loan_os &&
        (float)$snapshotSmall->lancar_os === (float)$direct->lancar_os &&
        (float)$snapshotSmall->sml_os === (float)$direct->sml_os &&
        (float)$snapshotSmall->npl_os === (float)$direct->npl_os &&
        (float)$snapshotSmall->restruk_os === (float)$direct->restruk_os &&
        (int)$snapshotSmall->total_deb === (int)$direct->total_deb
    ) {
        echo "=> SUCCESS: SMALL segment matches perfectly!\n";
    } else {
        echo "=> ERROR: SMALL segment mismatch!\n";
    }
} else {
    echo "No SMALL snapshot found for $period\n";
}

