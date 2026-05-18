<?php
$anomalies = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', 'like', '2026-05-%')
    ->where(function($query) {
        $query->where('total_sml_abs_non_commercial', '<', 0)
              ->orWhere('total_npl_abs_non_commercial', '<', 0)
              ->orWhereNull('total_sml_abs_non_commercial')
              ->orWhereNull('total_npl_abs_non_commercial');
    })
    ->get();
echo "Anomalies based on < 0 or NULL:\n";
print_r($anomalies);

$zeros = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', 'like', '2026-05-%')
    ->where(function($query) {
        $query->where('total_sml_abs_non_commercial', 0)
              ->orWhere('total_npl_abs_non_commercial', 0);
    })
    ->count();
echo "Zero counts:\n" . $zeros . "\n";

$duplicates = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', 'like', '2026-05-%')
    ->select('snapshot_period', 'kanca_key', 'unit_key', DB::raw('COUNT(*) as count'))
    ->groupBy('snapshot_period', 'kanca_key', 'unit_key')
    ->having('count', '>', 1)
    ->get();
echo "Duplicates:\n";
print_r($duplicates);
