<?php

try {
    $db = new PDO('mysql:host=127.0.0.1;dbname=project_abah;charset=utf8mb4', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Failed Jobs ===\n";
    $query = $db->query("
        SELECT id, queue, payload, failed_at, exception 
        FROM failed_jobs 
        LIMIT 5
    ");
    $failed = $query->fetchAll(PDO::FETCH_ASSOC);
    foreach ($failed as $f) {
        echo "\nFailed Job ID: {$f['id']}\n";
        echo "Queue: {$f['queue']}\n";
        echo "Failed at: {$f['failed_at']}\n";
        echo "Exception preview: " . substr($f['exception'], 0, 200) . "...\n";
    }
    
    echo "\n\n=== Check LW325_PH_SNAPSHOTS table ===\n";
    $query = $db->query("
        SELECT DISTINCT periode, COUNT(*) as row_count
        FROM lw325_ph_snapshots
        ORDER BY periode DESC
        LIMIT 10
    ");
    $snap = $query->fetchAll(PDO::FETCH_ASSOC);
    if (empty($snap)) {
        echo "No LW325_PH snapshots found!\n";
    } else {
        foreach ($snap as $s) {
            echo "Period: {$s['periode']}, Rows: {$s['row_count']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
