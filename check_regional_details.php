<?php
$pdo = new PDO('mysql:host=localhost;dbname=project_abah', 'root', '');

echo "=== Detailed RKA Regional Data ===\n\n";

echo "Checking DPK Rp Kecil Non Cash Collateral with regional patterns:\n\n";

$stmt = $pdo->prepare("
    SELECT 
        desc_uker,
        SUM(apr) as total_apr,
        COUNT(*) as cnt
    FROM rka
    WHERE mata_anggaran = 'DPK Rp Kecil Non Cash Collateral'
      AND kanca = 'KC Ponorogo'
      AND (desc_uker LIKE '%KC%' OR desc_uker LIKE '%KCP%')
      AND (desc_uker LIKE '%MADIUN%' OR desc_uker LIKE '%NGAWI%' OR desc_uker LIKE '%MAGETAN%')
    GROUP BY desc_uker
    ORDER BY SUM(apr) DESC
");
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $row) {
    echo "  - " . $row['desc_uker'] . ": " . number_format($row['total_apr'], 0) . " (" . $row['cnt'] . " rows)\n";
}

echo "\nTotal across all regions:\n";
$sum = array_sum(array_column($results, 'total_apr'));
echo "  " . number_format($sum, 0) . "\n";

echo "\nFor comparison, total 'DPK Rp Kecil Non Cash Collateral' in table:\n";
$stmt = $pdo->query("SELECT SUM(apr) as total FROM rka WHERE mata_anggaran = 'DPK Rp Kecil Non Cash Collateral'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "  " . number_format($row['total'], 0) . "\n";

echo "\n";
