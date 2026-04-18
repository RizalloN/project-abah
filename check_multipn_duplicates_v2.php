<?php
use Illuminate\Support\Facades\DB;

$periode = '2026-02-28';
$kanca = '00045 -- KC Madiun(Konsolidasi-MB)';

echo "Checking duplicates for $periode, Kanca $kanca...\n";

// Count total for this kanca/period
$totalCount = DB::table('simpanan_multipn')
    ->where('posisi', $periode)
    ->where('kantor_cabang', $kanca)
    ->count();

echo "Total rows for Madiun in this period: $totalCount\n";

if ($totalCount == 0) {
    echo "No data found for this scope. Trying LIKE match...\n";
    $kanca = 'KC Madiun';
}

// Find duplicates by no_rekening
$duplicates = DB::table('simpanan_multipn')
    ->where('posisi', $periode)
    ->where('kantor_cabang', 'LIKE', "%$kanca%")
    ->select('no_rekening', DB::raw('COUNT(*) as count'))
    ->groupBy('no_rekening')
    ->having('count', '>', 1)
    ->limit(50)
    ->get();

echo "Found " . count($duplicates) . " duplicate account numbers (limited to first 50).\n";

if (count($duplicates) > 0) {
    foreach ($duplicates as $row) {
        $details = DB::table('simpanan_multipn')
            ->where('posisi', $periode)
            ->where('no_rekening', $row->no_rekening)
            ->get();
            
        echo "\nNo Rekening: {$row->no_rekening} (Count: {$row->count})\n";
        foreach ($details as $d) {
            echo "  ID: {$d->uniqueid_SMPN}, Kanca: {$d->kantor_cabang}, Saldo: {$d->saldo_idr}\n";
        }
    }
} else {
    echo "No duplicates found based on account number.\n";
}
