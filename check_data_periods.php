<?php

try {
    $db = new PDO('mysql:host=127.0.0.1;dbname=project_abah;charset=utf8mb4', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== LW325_PH data periods ===\n";
    $query = $db->query("
        SELECT DISTINCT periode, COUNT(*) as row_count
        FROM lw325_ph
        ORDER BY periode DESC
        LIMIT 10
    ");
    $periods = $query->fetchAll(PDO::FETCH_ASSOC);
    foreach ($periods as $p) {
        echo "Period: {$p['periode']}, Rows: {$p['row_count']}\n";
    }
    
    echo "\n=== Dashboard Harian snapshot periods ===\n";
    $query = $db->query("
        SELECT DISTINCT snapshot_period, COUNT(*) as row_count
        FROM dashboard_harian_snapshots
        ORDER BY snapshot_period DESC
        LIMIT 10
    ");
    $snap_periods = $query->fetchAll(PDO::FETCH_ASSOC);
    foreach ($snap_periods as $p) {
        echo "Period: {$p['snapshot_period']}, Rows: {$p['row_count']}\n";
    }
    
    echo "\n=== Check what's missing ===\n";
    echo "LW325_PH has data for: ";
    $lw325Periods = $db->query("SELECT GROUP_CONCAT(DISTINCT periode ORDER BY periode DESC) as periods FROM lw325_ph LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    echo $lw325Periods['periods'] . "\n";
    
    echo "Dashboard snapshots exist for: ";
    $snapPeriods = $db->query("SELECT GROUP_CONCAT(DISTINCT snapshot_period ORDER BY snapshot_period DESC) as periods FROM dashboard_harian_snapshots LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    echo $snapPeriods['periods'] . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
