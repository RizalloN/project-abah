<?php
$may7 = DB::table('ssa_pinjaman')
    ->where('month_day_year_of_periode', '2026-05-07')
    ->where('nama_cabang', 'like', '%Madiun%')
    ->select('nama_uker', DB::raw('SUM(baki_debet) as baki'))
    ->groupBy('nama_uker')
    ->get()
    ->keyBy('nama_uker')
    ->toArray();

$may8 = DB::table('ssa_pinjaman')
    ->where('month_day_year_of_periode', '2026-05-08')
    ->where('nama_cabang', 'like', '%Madiun%')
    ->select('nama_uker', DB::raw('SUM(baki_debet) as baki'))
    ->groupBy('nama_uker')
    ->get()
    ->keyBy('nama_uker')
    ->toArray();

$allUkers = array_unique(array_merge(array_keys($may7), array_keys($may8)));
foreach ($allUkers as $uker) {
    $b7 = isset($may7[$uker]) ? $may7[$uker]->baki : 0;
    $b8 = isset($may8[$uker]) ? $may8[$uker]->baki : 0;
    if (abs($b8 - $b7) > 1000000000) { // Difference > 1 Billion
        echo "$uker: May 7 = $b7, May 8 = $b8, Diff = " . ($b8 - $b7) . "\n";
    }
}
