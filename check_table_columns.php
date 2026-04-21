<?php

$db = new PDO('mysql:host=127.0.0.1;dbname=project_abah;charset=utf8mb4', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Checking table columns ===\n\n";

$tables = ['ssa_pinjaman', 'lw325_ph', 'ssa_simpanan'];

foreach ($tables as $table) {
    echo "Table: $table\n";
    $query = $db->query("DESCRIBE $table");
    $columns = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Look for period-related columns
    foreach ($columns as $col) {
        if (stripos($col['Field'], 'periode') !== false || 
            stripos($col['Field'], 'date') !== false ||
            stripos($col['Field'], 'month') !== false ||
            stripos($col['Field'], 'kantor') !== false ||
            stripos($col['Field'], 'kanca') !== false ||
            stripos($col['Field'], 'unit') !== false ||
            stripos($col['Field'], 'cabang') !== false) {
            echo "  {$col['Field']} ({$col['Type']})\n";
        }
    }
    echo "\n";
}
