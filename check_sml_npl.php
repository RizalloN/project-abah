<?php
$data = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', 'like', '2026-05-%')
    ->whereRaw('kanca_key = unit_key')
    ->select('snapshot_period', DB::raw('SUM(total_sml_abs_non_commercial) as sml'), DB::raw('SUM(total_npl_abs_non_commercial) as npl'))
    ->groupBy('snapshot_period')
    ->orderBy('snapshot_period')
    ->get();
print_r($data);
