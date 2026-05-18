<?php
$data = DB::table('dashboard_harian_snapshots')
    ->select('snapshot_period', 'kanca_label', DB::raw('SUM(total_sml_abs_non_commercial) as sml'), DB::raw('SUM(total_npl_abs_non_commercial) as npl'))
    ->where('snapshot_period', 'like', '2026-05-%')
    ->whereRaw('kanca_key = unit_key')
    ->groupBy('snapshot_period', 'kanca_label')
    ->orderBy('snapshot_period')
    ->get();
print_r($data);
