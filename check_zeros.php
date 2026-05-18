<?php
$zeros = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', 'like', '2026-05-%')
    ->where(function($query) {
        $query->where('total_sml_abs_non_commercial', 0)
              ->orWhere('total_npl_abs_non_commercial', 0);
    })
    ->get(['snapshot_period', 'kanca_label', 'unit_label', 'total_sml_abs_non_commercial', 'total_npl_abs_non_commercial']);
print_r($zeros);
