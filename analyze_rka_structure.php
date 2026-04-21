<?php
$pdo = new PDO('mysql:host=localhost;dbname=project_abah', 'root', '');

echo "=== Analyzing RKA Data Structure ===\n\n";

// First, let's see all unique desc_uker values for KC Ponorogo with DPK Rp Kecil Non Cash Collateral
echo "1. All desc_uker values for 'DPK Rp Kecil Non Cash Collateral' (kanca=KC Ponorogo):\n\n";

$stmt = $pdo->prepare("
    SELECT DISTINCT desc_uker, SUM(apr) as total_apr
    FROM rka
    WHERE kanca = 'KC Ponorogo'
      AND mata_anggaran = 'DPK Rp Kecil Non Cash Collateral'
    GROUP BY desc_uker
    ORDER BY total_apr DESC
");
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $row) {
    echo "  " . $row['desc_uker'] . " => " . number_format($row['total_apr'], 0) . "\n";
}

echo "\n\n2. Breakdown: Ponorogo-specific vs Regional:\n\n";

// Ponorogo specific (doesn't contain regional keywords)
$stmt = $pdo->prepare("
    SELECT SUM(apr) as total
    FROM rka
    WHERE kanca = 'KC Ponorogo'
      AND mata_anggaran = 'DPK Rp Kecil Non Cash Collateral'
      AND desc_uker NOT REGEXP 'MADIUN|NGAWI|MAGETAN|KC MADIUN|KC NGAWI|KC MAGETAN'
");
$stmt->execute();
$ponorogo = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
echo "  KC Ponorogo (excluding regional): " . number_format($ponorogo, 0) . "\n";

// Regional combined
$stmt = $pdo->prepare("
    SELECT SUM(apr) as total
    FROM rka
    WHERE kanca = 'KC Ponorogo'
      AND mata_anggaran = 'DPK Rp Kecil Non Cash Collateral'
      AND desc_uker REGEXP 'MADIUN|NGAWI|MAGETAN|KC MADIUN|KC NGAWI|KC MAGETAN'
");
$stmt->execute();
$regional = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
echo "  Regional combined: " . number_format($regional, 0) . "\n";

echo "  Total: " . number_format($ponorogo + $regional, 0) . "\n";

echo "\n\n3. By Region Breakdown:\n\n";

$stmt = $pdo->prepare("
    SELECT 
        CASE
            WHEN desc_uker LIKE '%MADIUN%' OR desc_uker LIKE '%KC MADIUN%' THEN 'MADIUN'
            WHEN desc_uker LIKE '%NGAWI%' OR desc_uker LIKE '%KC NGAWI%' THEN 'NGAWI'
            WHEN desc_uker LIKE '%MAGETAN%' OR desc_uker LIKE '%KC MAGETAN%' THEN 'MAGETAN'
            ELSE 'PONOROGO'
        END as region,
        COUNT(*) as cnt,
        SUM(apr) as total_apr
    FROM rka
    WHERE kanca = 'KC Ponorogo'
      AND mata_anggaran = 'DPK Rp Kecil Non Cash Collateral'
    GROUP BY region
    ORDER BY total_apr DESC
");
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $row) {
    echo "  " . str_pad($row['region'], 12) . " (" . $row['cnt'] . " rows): " . number_format($row['total_apr'], 0) . "\n";
}

echo "\n\n4. Sample desc_uker values per region:\n\n";

$regions = ['PONOROGO', 'MADIUN', 'NGAWI', 'MAGETAN'];

foreach ($regions as $region) {
    $pattern = $region === 'PONOROGO' 
        ? "NOT REGEXP 'MADIUN|NGAWI|MAGETAN|KC MADIUN|KC NGAWI|KC MAGETAN'"
        : "LIKE '%$region%' OR desc_uker LIKE '%KC $region%'";
    
    $pattern = ($region === 'PONOROGO') 
        ? "NOT REGEXP 'MADIUN|NGAWI|MAGETAN|KC MADIUN|KC NGAWI|KC MAGETAN'"
        : "(desc_uker LIKE '%$region%' OR desc_uker LIKE '%KC $region%')";
    
    $sql = "
        SELECT DISTINCT desc_uker
        FROM rka
        WHERE kanca = 'KC Ponorogo'
          AND mata_anggaran = 'DPK Rp Kecil Non Cash Collateral'
          AND $pattern
        LIMIT 3
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "  $region:\n";
    foreach ($results as $row) {
        echo "    - " . $row['desc_uker'] . "\n";
    }
}

echo "\n";
