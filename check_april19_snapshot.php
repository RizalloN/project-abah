<?php

$db = new PDO('mysql:host=127.0.0.1;dbname=project_abah;charset=utf8mb4', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Snapshot Data for April 19, 2026 ===\n\n";

// First, check what kanca values exist
$query = $db->query("
    SELECT DISTINCT 
        kanca_key,
        kanca_label,
        COUNT(*) as row_count
    FROM dashboard_harian_snapshots
    WHERE snapshot_period = '2026-04-19'
    GROUP BY kanca_key, kanca_label
    ORDER BY kanca_key
");

$kancas = $query->fetchAll(PDO::FETCH_ASSOC);

echo "Available Kancas in snapshot:\n";
foreach ($kancas as $k) {
    echo "  {$k['kanca_key']}: {$k['kanca_label']} ({$k['row_count']} rows)\n";
}

echo "\n\n=== All Rows for April 19 (Summary) ===\n";

$query2 = $db->query("
    SELECT 
        kanca_label,
        unit_label,
        rec_dh_total,
        rec_dh_small,
        rec_dh_consumer,
        rec_dh_micro
    FROM dashboard_harian_snapshots
    WHERE snapshot_period = '2026-04-19'
    ORDER BY kanca_label, unit_label
");

$rows = $query2->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    echo "❌ No rows found for April 19!\n";
} else {
    echo "Found " . count($rows) . " rows\n\n";
    
    $totals = [
        'rec_dh_total' => 0,
        'rec_dh_small' => 0,
        'rec_dh_consumer' => 0,
        'rec_dh_micro' => 0,
    ];
    
    foreach ($rows as $row) {
        echo "Kanca: {$row['kanca_label']}, Unit: {$row['unit_label']}\n";
        echo "  Total: " . number_format((float)$row['rec_dh_total'], 0) . " | ";
        echo "Small: " . number_format((float)$row['rec_dh_small'], 0) . " | ";
        echo "Consumer: " . number_format((float)$row['rec_dh_consumer'], 0) . " | ";
        echo "Micro: " . number_format((float)$row['rec_dh_micro'], 0) . "\n";
        
        $totals['rec_dh_total'] += (float)$row['rec_dh_total'];
        $totals['rec_dh_small'] += (float)$row['rec_dh_small'];
        $totals['rec_dh_consumer'] += (float)$row['rec_dh_consumer'];
        $totals['rec_dh_micro'] += (float)$row['rec_dh_micro'];
    }
    
    echo "\n=== SUMMARY ===\n";
    echo "Total rec_dh_total:    " . number_format($totals['rec_dh_total'], 0) . " (" . number_format($totals['rec_dh_total']/1000000, 2) . "M)\n";
    echo "Total rec_dh_small:    " . number_format($totals['rec_dh_small'], 0) . " (" . number_format($totals['rec_dh_small']/1000000, 2) . "M)\n";
    echo "Total rec_dh_consumer: " . number_format($totals['rec_dh_consumer'], 0) . " (" . number_format($totals['rec_dh_consumer']/1000000, 2) . "M)\n";
    echo "Total rec_dh_micro:    " . number_format($totals['rec_dh_micro'], 0) . " (" . number_format($totals['rec_dh_micro']/1000000, 2) . "M)\n";
    
    if ($totals['rec_dh_total'] > 0) {
        echo "\n=== Breakdown ===\n";
        echo "Small:    " . number_format(($totals['rec_dh_small']/$totals['rec_dh_total'])*100, 2) . "%\n";
        echo "Consumer: " . number_format(($totals['rec_dh_consumer']/$totals['rec_dh_total'])*100, 2) . "%\n";
        echo "Micro:    " . number_format(($totals['rec_dh_micro']/$totals['rec_dh_total'])*100, 2) . "%\n";
    }
}

echo "\n\n=== Check Madiun specifically (if exists) ===\n";
$madiun = $db->query("
    SELECT 
        kanca_label,
        unit_label,
        rec_dh_total,
        rec_dh_small,
        rec_dh_consumer,
        rec_dh_micro
    FROM dashboard_harian_snapshots
    WHERE snapshot_period = '2026-04-19'
    AND (kanca_label LIKE '%Madiun%' OR kanca_key LIKE '%Madiun%' OR kanca_label LIKE '%MADIUN%')
    ORDER BY unit_label
");

$madiun_rows = $madiun->fetchAll(PDO::FETCH_ASSOC);

if (!empty($madiun_rows)) {
    echo "Madiun rows found:\n";
    foreach ($madiun_rows as $row) {
        echo "  Unit: {$row['unit_label']}, Micro: " . number_format((float)$row['rec_dh_micro'], 0) . "\n";
    }
} else {
    echo "No Madiun data found\n";
}
