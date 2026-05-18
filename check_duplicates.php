<?php
$duplicates = DB::table('dashboard_harian_snapshots')
    ->select('snapshot_period', 'kanca_key', 'unit_key', DB::raw('COUNT(*) as count'))
    ->where('snapshot_period', 'like', '2026-05-%')
    ->groupBy('snapshot_period', 'kanca_key', 'unit_key')
    ->having('count', '>', 1)
    ->get();
print_r($duplicates);
