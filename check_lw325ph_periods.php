<?php
/**
 * Check what periods exist in LW325_PH
 */

$dbhost = '127.0.0.1';
$dbuser = 'root';
$dbpass = '';
$dbname = 'project_abah';

try {
    $pdo = new PDO("mysql:host=$dbhost;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

echo "=== LW325_PH Periods Available ===\n\n";

// Get all distinct periods
$periods = $pdo->query("
    SELECT DISTINCT periode 
    FROM lw325_ph 
    ORDER BY periode DESC 
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

echo "Available Periods (Recent 20):\n";
foreach ($periods as $row) {
    $p = $row['periode'];
    $count = $pdo->query("SELECT COUNT(*) FROM lw325_ph WHERE periode = '$p'")->fetchColumn();
    echo "  $p: $count rows\n";
}

echo "\nTotal periods: " . count($periods) . "\n";

// Now check April 20
echo "\n=== April 20 (2026-04-20) Calculation ===\n\n";

$current = '2026-04-20';
$prev = '2026-04-19';

$curr_exists = $pdo->query("SELECT COUNT(*) FROM lw325_ph WHERE periode = '$current'")->fetchColumn();
$prev_exists = $pdo->query("SELECT COUNT(*) FROM lw325_ph WHERE periode = '$prev'")->fetchColumn();

echo "Current ($current): $curr_exists rows\n";
echo "Previous ($prev): $prev_exists rows\n\n";

if ($curr_exists > 0 && $prev_exists > 0) {
    echo "✅ Can calculate recovery for $current\n";
} else {
    echo "❌ Cannot calculate recovery for $current - missing period data\n";
}
