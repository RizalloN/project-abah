<?php
$may7 = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', '2026-05-07')
    ->where('kanca_key', '=', DB::raw('unit_key'))
    ->select('kanca_label', 'total_os_non_commercial', 'total_sml_abs_non_commercial')
    ->get()
    ->keyBy('kanca_label')
    ->toArray();

$may8 = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', '2026-05-08')
    ->where('kanca_key', '=', DB::raw('unit_key'))
    ->select('kanca_label', 'total_os_non_commercial', 'total_sml_abs_non_commercial')
    ->get()
    ->keyBy('kanca_label')
    ->toArray();

echo "May 7 vs May 8 Snapshots:\n";
foreach (array_keys($may7) as $k) {
    echo "$k: OS (7) = " . $may7[$k]->total_os_non_commercial . ", OS (8) = " . $may8[$k]->total_os_non_commercial . "\n";
    echo "$k: SML (7) = " . $may7[$k]->total_sml_abs_non_commercial . ", SML (8) = " . $may8[$k]->total_sml_abs_non_commercial . "\n";
}
