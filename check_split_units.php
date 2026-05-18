<?php
$unit1 = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', '2026-05-15')
    ->where('unit_label', 'like', '%Sudirman Madiun%')
    ->select('unit_label', 'total_simpanan', 'total_os_non_commercial', 'total_sml_abs_non_commercial')
    ->get();
print_r($unit1);
