<?php

try {
    $db = new PDO('mysql:host=127.0.0.1;dbname=project_abah;charset=utf8mb4', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get columns of import_jobs table
    echo "=== import_jobs table structure ===\n";
    $query = $db->query("DESCRIBE import_jobs");
    $columns = $query->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "{$col['Field']}\n";
    }
    
    echo "\n=== Latest import jobs ===\n";
    $query = $db->query("
        SELECT id, status, total_success, total_failed, created_at, updated_at 
        FROM import_jobs 
        ORDER BY id DESC 
        LIMIT 5
    ");
    $jobs = $query->fetchAll(PDO::FETCH_ASSOC);
    foreach ($jobs as $job) {
        echo "ID: {$job['id']}, Status: {$job['status']}, Success: {$job['total_success']}, Failed: {$job['total_failed']}, Updated: {$job['updated_at']}\n";
    }
    
    echo "\n=== Dashboard Harian latest periods ===\n";
    $query = $db->query("
        SELECT DISTINCT snapshot_period, COUNT(*) as row_count
        FROM dashboard_harian_snapshots
        GROUP BY snapshot_period
        ORDER BY snapshot_period DESC
        LIMIT 5
    ");
    $periods = $query->fetchAll(PDO::FETCH_ASSOC);
    foreach ($periods as $p) {
        echo "Period: {$p['snapshot_period']}, Rows: {$p['row_count']}\n";
    }
    
    echo "\n=== Queue Status ===\n";
    $query = $db->query("SELECT COUNT(*) as count FROM jobs");
    $pending = $query->fetch(PDO::FETCH_ASSOC);
    echo "Pending jobs: {$pending['count']}\n";
    
    $query = $db->query("SELECT COUNT(*) as count FROM failed_jobs");
    $failed = $query->fetch(PDO::FETCH_ASSOC);
    echo "Failed jobs: {$failed['count']}\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
