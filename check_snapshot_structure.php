<?php
$dbhost = '127.0.0.1';
$dbuser = 'root';
$dbpass = '';
$dbname = 'project_abah';

try {
    $pdo = new PDO("mysql:host=$dbhost;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed\n");
}

echo "=== SNAPSHOT STRUCTURE ANALYSIS ===\n\n";

// Check snapshot schema
$columns = $pdo->query("
    DESCRIBE dashboard_harian_snapshots
")->fetchAll(PDO::FETCH_ASSOC);

echo "Snapshot Columns (relevant):\n";
foreach ($columns as $col) {
    if (in_array($col['Field'], ['kanca_key', 'unit_key', 'rec_dh_total', 'rec_dh_small', 'rec_dh_consumer', 'rec_dh_micro'])) {
        echo "  {$col['Field']}: {$col['Type']}\n";
    }
}

echo "\n=== SAMPLE SNAPSHOT ROWS ===\n\n";

$sample = $pdo->query("
    SELECT 
        kanca_key,
        unit_key,
        rec_dh_total,
        rec_dh_small,
        rec_dh_consumer,
        rec_dh_micro
    FROM dashboard_harian_snapshots
    WHERE snapshot_period = '2026-04-19'
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($sample as $row) {
    echo "Kanca: {$row['kanca_key']}, Unit: {$row['unit_key']}\n";
    echo "  Rec Total: {$row['rec_dh_total']}, Small: {$row['rec_dh_small']}, Consumer: {$row['rec_dh_consumer']}, Micro: {$row['rec_dh_micro']}\n";
}

echo "\n=== BRANCH-LEVEL ROWS (kanca_key == unit_key) ===\n\n";

$branch_rows = $pdo->query("
    SELECT 
        kanca_key,
        unit_key,
        SUM(rec_dh_total) as total
    FROM dashboard_harian_snapshots
    WHERE snapshot_period = '2026-04-19'
    AND kanca_key = unit_key
    GROUP BY kanca_key
    ORDER BY kanca_key
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($branch_rows as $row) {
    echo "{$row['kanca_key']}: " . number_format($row['total'], 0) . "\n";
}
