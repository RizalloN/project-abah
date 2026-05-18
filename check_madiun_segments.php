<?php
$may7 = DB::table('ssa_pinjaman')
    ->where('month_day_year_of_periode', '2026-05-07')
    ->where('nama_uker', '00045 -- KC Madiun')
    ->select('segmen_dashboard', DB::raw('SUM(baki_debet) as baki'))
    ->groupBy('segmen_dashboard')
    ->get()
    ->keyBy('segmen_dashboard')
    ->toArray();

$may8 = DB::table('ssa_pinjaman')
    ->where('month_day_year_of_periode', '2026-05-08')
    ->where('nama_uker', '00045 -- KC Madiun')
    ->select('segmen_dashboard', DB::raw('SUM(baki_debet) as baki'))
    ->groupBy('segmen_dashboard')
    ->get()
    ->keyBy('segmen_dashboard')
    ->toArray();

echo "KC Madiun Segments (May 7 vs May 8):\n";
$allSegs = array_unique(array_merge(array_keys($may7), array_keys($may8)));
foreach ($allSegs as $seg) {
    $b7 = isset($may7[$seg]) ? $may7[$seg]->baki : 0;
    $b8 = isset($may8[$seg]) ? $may8[$seg]->baki : 0;
    echo "$seg: May 7 = $b7, May 8 = $b8, Diff = " . ($b8 - $b7) . "\n";
}
