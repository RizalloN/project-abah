<?php

$db = new PDO('mysql:host=127.0.0.1;dbname=project_abah;charset=utf8mb4', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Checking Recovery Data (rec_dh) per Segment - April 19, 2026 ===\n\n";

// Area 6 kancas (Madiun, Ngawi, Magetan, Ponorogo)
$area6_kancas = ['KC Madiun', 'KC Ngawi', 'KC Magetan', 'KC Ponorogo'];
$placeholders = implode(',', array_fill(0, count($area6_kancas), '?'));

echo "Area 6 Kancas: " . implode(', ', $area6_kancas) . "\n";
echo "Period: 2026-04-19\n\n";

// Query snapshot data
$query = $db->prepare("
    SELECT 
        snapshot_period,
        kanca_key,
        unit_key,
        rec_dh_total,
        rec_dh_small,
        rec_dh_consumer,
        rec_dh_micro,
        unit_label,
        kanca_label
    FROM dashboard_harian_snapshots
    WHERE snapshot_period = '2026-04-19'
    AND kanca_key IN ($placeholders)
    ORDER BY kanca_key, unit_key
");

$query->execute($area6_kancas);
$rows = $query->fetchAll(PDO::FETCH_ASSOC);

echo "=== Recovery Data per Row ===\n\n";

$totals = [
    'rec_dh_total' => 0,
    'rec_dh_small' => 0,
    'rec_dh_consumer' => 0,
    'rec_dh_micro' => 0,
];

foreach ($rows as $row) {
    echo "Kanca: {$row['kanca_label']}\n";
    echo "Unit: {$row['unit_label']}\n";
    echo "  rec_dh_total:    " . number_format((float)$row['rec_dh_total'], 0) . "\n";
    echo "  rec_dh_small:    " . number_format((float)$row['rec_dh_small'], 0) . "\n";
    echo "  rec_dh_consumer: " . number_format((float)$row['rec_dh_consumer'], 0) . "\n";
    echo "  rec_dh_micro:    " . number_format((float)$row['rec_dh_micro'], 0) . "\n";
    echo "\n";
    
    $totals['rec_dh_total'] += (float)$row['rec_dh_total'];
    $totals['rec_dh_small'] += (float)$row['rec_dh_small'];
    $totals['rec_dh_consumer'] += (float)$row['rec_dh_consumer'];
    $totals['rec_dh_micro'] += (float)$row['rec_dh_micro'];
}

echo "=== TOTALS (All Area 6) ===\n";
echo "rec_dh_total:    " . number_format($totals['rec_dh_total'], 0) . " (" . number_format($totals['rec_dh_total']/1000000, 2) . "M)\n";
echo "rec_dh_small:    " . number_format($totals['rec_dh_small'], 0) . " (" . number_format($totals['rec_dh_small']/1000000, 2) . "M)\n";
echo "rec_dh_consumer: " . number_format($totals['rec_dh_consumer'], 0) . " (" . number_format($totals['rec_dh_consumer']/1000000, 2) . "M)\n";
echo "rec_dh_micro:    " . number_format($totals['rec_dh_micro'], 0) . " (" . number_format($totals['rec_dh_micro']/1000000, 2) . "M)\n";

echo "\n\n=== Breakdown by Segment ===\n";
$percentTotal = 100;
echo "Total: " . number_format($percentTotal, 1) . "%\n";
echo "  Small:    " . number_format(($totals['rec_dh_small']/$totals['rec_dh_total'])*100, 1) . "%\n";
echo "  Consumer: " . number_format(($totals['rec_dh_consumer']/$totals['rec_dh_total'])*100, 1) . "%\n";
echo "  Micro:    " . number_format(($totals['rec_dh_micro']/$totals['rec_dh_total'])*100, 1) . "%\n";

echo "\n\n=== Check Source Data (lw325_ph) ===\n";
$phQuery = $db->prepare("
    SELECT 
        periode,
        COUNT(*) as row_count,
        SUM(ph_tupok) as tupok_total,
        SUM(ph_lunas) as lunas_total,
        SUM(rec_dh_total) as rec_dh_total,
        SUM(rec_dh_small) as rec_dh_small,
        SUM(rec_dh_consumer) as rec_dh_consumer,
        SUM(rec_dh_micro) as rec_dh_micro
    FROM lw325_ph
    WHERE periode = '2026-04-19'
    AND kanca IN ($placeholders)
    GROUP BY periode
");

$phQuery->execute($area6_kancas);
$phData = $phQuery->fetch(PDO::FETCH_ASSOC);

if ($phData) {
    echo "LW325_PH for April 19, Area 6:\n";
    echo "Rows: {$phData['row_count']}\n";
    echo "rec_dh_total:    " . number_format((float)$phData['rec_dh_total'], 0) . " (" . number_format((float)$phData['rec_dh_total']/1000000, 2) . "M)\n";
    echo "rec_dh_small:    " . number_format((float)$phData['rec_dh_small'], 0) . " (" . number_format((float)$phData['rec_dh_small']/1000000, 2) . "M)\n";
    echo "rec_dh_consumer: " . number_format((float)$phData['rec_dh_consumer'], 0) . " (" . number_format((float)$phData['rec_dh_consumer']/1000000, 2) . "M)\n";
    echo "rec_dh_micro:    " . number_format((float)$phData['rec_dh_micro'], 0) . " (" . number_format((float)$phData['rec_dh_micro']/1000000, 2) . "M)\n";
} else {
    echo "❌ No LW325_PH data found for April 19, Area 6\n";
}

echo "\n\n=== Comparison: Snapshot vs Source ===\n";
if ($phData && $totals['rec_dh_total'] > 0) {
    $diff = abs((float)$phData['rec_dh_total'] - $totals['rec_dh_total']);
    $diffPct = ($diff / max((float)$phData['rec_dh_total'], 1)) * 100;
    
    echo "Difference: " . number_format($diff, 0) . " (" . number_format($diffPct, 2) . "%)\n";
    
    if ($diff < 1000) {
        echo "✅ Data matches (within rounding)\n";
    } else {
        echo "⚠️ Data mismatch detected\n";
    }
}
