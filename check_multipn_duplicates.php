<?php
use Illuminate\Support\Facades\DB;

$periode = '2026-02-28';
$kanca = 'Madiun';

echo "Checking duplicates for $periode, Kanca $kanca...\n";

$rows = DB::table('simpanan_multipn')
    ->where('posisi', $periode)
    ->where('kantor_cabang', 'LIKE', "%$kanca%")
    ->select('no_rekening', DB::raw('COUNT(*) as count'), DB::raw('GROUP_CONCAT(uniqueid_SMPN) as unique_ids'))
    ->groupBy('no_rekening')
    ->having('count', '>', 1)
    ->get();

echo "Found " . count($rows) . " duplicate accounts.\n";
foreach ($rows as $row) {
    echo "No Rekenig: {$row->no_rekening}, Count: {$row->count}, IDs: {$row->unique_ids}\n";
}

$totalRows = DB::table('simpanan_multipn')
    ->where('posisi', $periode)
    ->where('kantor_cabang', 'LIKE', "%$kanca%")
    ->count();

echo "\nTotal rows for this scope: $totalRows\n";
