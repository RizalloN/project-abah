<?php

$db = new PDO('mysql:host=127.0.0.1;dbname=project_abah;charset=utf8mb4', 'root', '');

$tables = ['ssa_pinjaman', 'ssa_simpanan', 'lw325_ph'];

foreach ($tables as $table) {
    echo "\n=== $table ===\n";
    $query = $db->query("DESCRIBE $table");
    $columns = $query->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        echo "{$col['Field']}\n";
    }
}
