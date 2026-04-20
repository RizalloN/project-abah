<?php
require 'vendor/autoload.php';

$pdo = new PDO('mysql:host=localhost;dbname=project_abah', 'root', '');

$branches = ['KC Ponorogo', 'KC Magetan', 'KC Ngawi', 'KC Madiun'];

echo "=== FINAL VERIFICATION - RITEL DATA ===\n\n";
foreach ($branches as $branch) {
    $stmt = $pdo->prepare('SELECT 
        SUM(simpanan_ritel) as total_ritel,
        SUM(giro_ritel) as giro,
        SUM(deposito_ritel) as deposito,
        SUM(tabungan_ritel) as tabungan
    FROM dashboard_harian_snapshots
    WHERE snapshot_period = "2026-04-18"
    AND kanca_label = ?');
    $stmt->execute([$branch]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "[$branch]\n";
    echo "  Total Ritel: " . number_format($result['total_ritel'], 0) . "\n";
    echo "  Giro: " . number_format($result['giro'], 0) . "\n";
    echo "  Deposito: " . number_format($result['deposito'], 0) . "\n";
    echo "  Tabungan: " . number_format($result['tabungan'], 0) . "\n\n";
}
