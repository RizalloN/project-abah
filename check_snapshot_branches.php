<?php
$pdo = new PDO('mysql:host=localhost;dbname=project_abah', 'root', '');

echo "=== Branches in Snapshot Table ===\n\n";

$stmt = $pdo->query("
    SELECT DISTINCT kanca_label
    FROM dashboard_harian_snapshots
    ORDER BY kanca_label
");

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $row) {
    echo "  " . $row['kanca_label'] . "\n";
}

echo "\n\nTotal unique branches: " . count($results) . "\n";

echo "\n\nSample from snapshot table:\n";

$stmt = $pdo->query("
    SELECT DISTINCT snapshot_period, kanca_label, unit_label
    FROM dashboard_harian_snapshots
    LIMIT 20
");

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $row) {
    echo "  Period: " . $row['snapshot_period'] . " | Kanca: " . $row['kanca_label'] . " | Unit: " . $row['unit_label'] . "\n";
}
