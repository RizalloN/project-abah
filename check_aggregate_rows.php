<?php
$pdo = new PDO('mysql:host=localhost;dbname=project_abah', 'root', '');

echo "=== Checking Aggregate Rows (where unit_label = kanca_label) ===\n\n";

$branches = ['KC Ponorogo', 'KC Madiun', 'KC Ngawi', 'KC Magetan'];

foreach ($branches as $branch) {
    $stmt = $pdo->prepare("
        SELECT snapshot_period, kanca_label, unit_label, kecil_non_cashcoll_os
        FROM dashboard_harian_snapshots
        WHERE kanca_label = ?
          AND unit_label = kanca_label
        ORDER BY snapshot_period DESC
        LIMIT 3
    ");
    $stmt->execute([$branch]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "$branch:\n";
    if (count($results) > 0) {
        echo "  Found " . count($results) . " aggregate rows\n";
        foreach ($results as $row) {
            echo "    Period: " . $row['snapshot_period'] . " | kecil_non_cashcoll_os: " . $row['kecil_non_cashcoll_os'] . "\n";
        }
    } else {
        echo "  NO aggregate rows found!\n";
    }
    echo "\n";
}

echo "\n\nNow let's check for date 2026-04-20:\n\n";

foreach ($branches as $branch) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as cnt, unit_label
        FROM dashboard_harian_snapshots
        WHERE kanca_label = ?
          AND snapshot_period = '2026-04-20'
        GROUP BY unit_label
        ORDER BY unit_label
    ");
    $stmt->execute([$branch]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "$branch on 2026-04-20:\n";
    if (count($results) > 0) {
        foreach ($results as $row) {
            echo "  - " . $row['unit_label'] . ": " . $row['cnt'] . " row(s)\n";
        }
    } else {
        echo "  NO data found for this date\n";
    }
    echo "\n";
}
