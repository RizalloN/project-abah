<?php
$snapshots = DB::table('dashboard_harian_snapshots')
    ->select('snapshot_period')
    ->selectRaw('SUM(total_os_non_commercial) as total_os')
    ->selectRaw('SUM(total_sml_abs_non_commercial) as sml')
    ->selectRaw('SUM(total_npl_abs_non_commercial) as npl')
    ->where('kanca_key', '=', DB::raw('unit_key'))
    ->where('snapshot_period', 'like', '2026-05-%')
    ->groupBy('snapshot_period')
    ->orderBy('snapshot_period')
    ->get();
print_r($snapshots);
