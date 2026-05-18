<?php
$row = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', '2026-05-06')
    ->where('kanca_key', 'kc-madiun')
    ->where('unit_key', 'kc-madiun')
    ->first();
print_r($row);
