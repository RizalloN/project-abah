<?php
$pdo = new PDO('mysql:host=localhost;dbname=project_abah', 'root', '');

echo "=== VERIFICATION: RKA mata_anggaran values in database ===\n\n";

// Check SME definitions
echo "1. SME RKA Data (OS):\n";
$stmt = $pdo->query("SELECT DISTINCT mata_anggaran, COUNT(*) as cnt FROM rka WHERE mata_anggaran LIKE '%Kecil%' GROUP BY mata_anggaran");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo "   - '" . $row['mata_anggaran'] . "': " . $row['cnt'] . " rows\n";
}

echo "\n2. SME SML definitions:\n";
$stmt = $pdo->query("SELECT DISTINCT mata_anggaran, COUNT(*) as cnt FROM rka WHERE mata_anggaran LIKE 'DPK%Kecil%' GROUP BY mata_anggaran");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo "   - '" . $row['mata_anggaran'] . "': " . $row['cnt'] . " rows\n";
}

echo "\n3. SME NPL definitions:\n";
$stmt = $pdo->query("SELECT DISTINCT mata_anggaran, COUNT(*) as cnt FROM rka WHERE mata_anggaran LIKE 'NPL%Kecil%' GROUP BY mata_anggaran");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo "   - '" . $row['mata_anggaran'] . "': " . $row['cnt'] . " rows\n";
}

echo "\n4. Consumer definitions (Briguna, KPR):\n";
$stmt = $pdo->query("SELECT DISTINCT mata_anggaran, COUNT(*) as cnt FROM rka WHERE (mata_anggaran LIKE 'B.5%' OR mata_anggaran LIKE 'DPK%Briguna%' OR mata_anggaran LIKE 'DPK%KPR%' OR mata_anggaran LIKE 'NPL%KPR%') GROUP BY mata_anggaran");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo "   - '" . $row['mata_anggaran'] . "': " . $row['cnt'] . " rows\n";
}

echo "\n5. Mikro definitions:\n";
$stmt = $pdo->query("SELECT DISTINCT mata_anggaran, COUNT(*) as cnt FROM rka WHERE mata_anggaran LIKE 'B.1%' GROUP BY mata_anggaran");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo "   - '" . $row['mata_anggaran'] . "': " . $row['cnt'] . " rows\n";
}

echo "\n6. Sample RKA rows with mat_anggaran for regional branches:\n";
$stmt = $pdo->query("SELECT kanca, desc_uker, mata_anggaran, apr FROM rka WHERE (desc_uker LIKE '%MADIUN%' OR desc_uker LIKE '%NGAWI%') LIMIT 10");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo "   Kanca: " . $row['kanca'] . " | desc_uker: " . $row['desc_uker'] . " | mata_anggaran: " . $row['mata_anggaran'] . " | APR: " . $row['apr'] . "\n";
}

echo "\n";
