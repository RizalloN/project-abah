<?php
$pdo = new PDO('mysql:host=localhost;dbname=project_abah', 'root', '');

echo "=== RKA DATA ANALYSIS ===\n\n";

echo "1. All unique kanca values in RKA table:\n";
$stmt = $pdo->query("SELECT DISTINCT kanca FROM rka ORDER BY kanca");
$results = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($results as $r) {
    $count = $pdo->query("SELECT COUNT(*) FROM rka WHERE kanca = '" . addslashes($r) . "'")->fetchColumn();
    echo "   - '" . $r . "': " . $count . " rows\n";
}

echo "\n2. Check if Madiun/Ngawi/Magetan appears in desc_uker:\n";
$search = ['MADIUN', 'NGAWI', 'MAGETAN'];
foreach ($search as $term) {
    $count = $pdo->query("SELECT COUNT(*) FROM rka WHERE desc_uker LIKE '%" . addslashes($term) . "%'")->fetchColumn();
    echo "   - Rows with '" . $term . "' in desc_uker: " . $count . "\n";
}

echo "\n3. Sample rows with MADIUN in desc_uker:\n";
$stmt = $pdo->query("SELECT kanca, desc_uker, mar, apr FROM rka WHERE desc_uker LIKE '%MADIUN%' LIMIT 3");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
if ($results) {
    foreach ($results as $r) {
        echo "   - Kanca: '" . $r['kanca'] . "' | desc_uker: '" . $r['desc_uker'] . "' | MAR: " . $r['mar'] . " | APR: " . $r['apr'] . "\n";
    }
} else {
    echo "   - No rows found\n";
}

echo "\n4. Check for 'KC Madiun', 'KC Ngawi', 'KC Magetan' anywhere:\n";
foreach (['KC Madiun', 'KC Ngawi', 'KC Magetan'] as $branch) {
    $count = $pdo->query("SELECT COUNT(*) FROM rka WHERE kanca LIKE '%" . addslashes($branch) . "%' OR desc_uker LIKE '%" . addslashes($branch) . "%'")->fetchColumn();
    echo "   - '" . $branch . "': " . $count . " rows\n";
}

echo "\n";
