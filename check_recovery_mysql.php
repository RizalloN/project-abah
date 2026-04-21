<?php
/**
 * Direct MySQL query to verify recovery formula
 * Using PDO to bypass Laravel bootstrap issues
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

echo "=== Recovery DH Formula - Direct MySQL Verification ===\n\n";

$current = '2026-04-19';
$prev = '2026-04-17';  // Changed from 04-18 to 04-17 (previous available period)

echo "Current Period: $current\n";
echo "Previous Period: $prev\n\n";

// Check if data exists
$check = $pdo->query("SELECT COUNT(*) FROM lw325_ph WHERE periode = '$current'")->fetchColumn();
if ($check == 0) {
    die("❌ No data for $current\n");
}
$check_prev = $pdo->query("SELECT COUNT(*) FROM lw325_ph WHERE periode = '$prev'")->fetchColumn();
if ($check_prev == 0) {
    die("❌ No data for $prev\n");
}

echo "✅ Data exists for both periods\n\n";

// ============================================================
// 1. TUPOK CALCULATION
// ============================================================
echo "=== 1. TUPOK (Principal Decreased: o.pokok - n.pokok > 0) ===\n\n";

$tupok_sql = "
SELECT 
    COUNT(*) as cnt,
    SUM(COALESCE(o.pokok, 0)) as amt,
    UPPER(TRIM(COALESCE(n.segmen_dashboard, ''))) as seg
FROM lw325_ph n
INNER JOIN lw325_ph o ON 
    n.acctno = o.acctno 
    AND n.kanca = o.kanca
    AND n.unit = o.unit
WHERE 
    n.periode = '$current'
    AND o.periode = '$prev'
    AND (COALESCE(o.pokok, 0) - COALESCE(n.pokok, 0)) > 0
GROUP BY UPPER(TRIM(COALESCE(n.segmen_dashboard, '')))
ORDER BY seg
";

$tupok_result = $pdo->query($tupok_sql)->fetchAll(PDO::FETCH_ASSOC);

$tupok_total_amount = 0;
$tupok_total_count = 0;

echo "By Segment:\n";
foreach ($tupok_result as $row) {
    $seg = $row['seg'] ?: 'UNKNOWN';
    echo "  $seg: {$row['cnt']} accounts, " . number_format($row['amt'], 0) . "\n";
    $tupok_total_amount += $row['amt'];
    $tupok_total_count += $row['cnt'];
}

echo "\nTUPOK TOTAL:\n";
echo "  Accounts: $tupok_total_count\n";
echo "  Amount: " . number_format($tupok_total_amount, 0) . "\n";

// ============================================================
// 2. LUNAS CALCULATION
// ============================================================
echo "\n=== 2. LUNAS (Paid Off: existed in prev, not in current) ===\n\n";

$lunas_sql = "
SELECT 
    COUNT(*) as cnt,
    SUM(COALESCE(o.pokok, 0)) as amt,
    UPPER(TRIM(COALESCE(o.segmen_dashboard, ''))) as seg
FROM lw325_ph o
LEFT JOIN lw325_ph n ON 
    o.acctno = n.acctno 
    AND o.kanca = n.kanca
    AND o.unit = n.unit
    AND n.periode = '$current'
WHERE 
    o.periode = '$prev'
    AND n.acctno IS NULL
GROUP BY UPPER(TRIM(COALESCE(o.segmen_dashboard, '')))
ORDER BY seg
";

$lunas_result = $pdo->query($lunas_sql)->fetchAll(PDO::FETCH_ASSOC);

$lunas_total_amount = 0;
$lunas_total_count = 0;

echo "By Segment:\n";
foreach ($lunas_result as $row) {
    $seg = $row['seg'] ?: 'UNKNOWN';
    echo "  $seg: {$row['cnt']} accounts, " . number_format($row['amt'], 0) . "\n";
    $lunas_total_amount += $row['amt'];
    $lunas_total_count += $row['cnt'];
}

echo "\nLUNAS TOTAL:\n";
echo "  Accounts: $lunas_total_count\n";
echo "  Amount: " . number_format($lunas_total_amount, 0) . "\n";

// ============================================================
// 3. TOTAL RECOVERY
// ============================================================
$total_recovery = $tupok_total_amount + $lunas_total_amount;
$total_count = $tupok_total_count + $lunas_total_count;

echo "\n=== 3. TOTAL RECOVERY (TUPOK + LUNAS) ===\n";
echo "Total Accounts: $total_count\n";
echo "Total Amount: " . number_format($total_recovery, 0) . "\n";

// ============================================================
// 4. SNAPSHOT VERIFICATION
// ============================================================
echo "\n=== 4. Snapshot Recovery (Branch Level) ===\n\n";

$snap_sql = "
SELECT 
    kanca_key,
    SUM(rec_dh_small) as s,
    SUM(rec_dh_consumer) as c,
    SUM(rec_dh_micro) as m,
    SUM(rec_dh_total) as t
FROM dashboard_harian_snapshots
WHERE snapshot_period = '$current'
AND kanca_key = unit_key
GROUP BY kanca_key
ORDER BY kanca_key
";

$snap_result = $pdo->query($snap_sql)->fetchAll(PDO::FETCH_ASSOC);

$snap_total = 0;
foreach ($snap_result as $row) {
    echo "{$row['kanca_key']}: " . number_format($row['t'], 0) . "\n";
    echo "  (Small: " . number_format($row['s'], 0);
    echo ", Consumer: " . number_format($row['c'], 0);
    echo ", Micro: " . number_format($row['m'], 0) . ")\n";
    $snap_total += $row['t'];
}

echo "\nSnapshot TOTAL: " . number_format($snap_total, 0) . "\n";

// ============================================================
// 5. VERIFICATION
// ============================================================
echo "\n=== 5. Verification ===\n";
echo "Calculated Recovery: " . number_format($total_recovery, 0) . "\n";
echo "Snapshot Recovery:   " . number_format($snap_total, 0) . "\n";
echo "Difference:          " . number_format($total_recovery - $snap_total, 0) . "\n";

if (abs($total_recovery - $snap_total) < 1) {
    echo "Status: ✅ MATCH\n";
} else {
    echo "Status: ❌ MISMATCH\n";
}

// ============================================================
// 6. CHECK FOR 1.73M
// ============================================================
echo "\n=== 6. Looking for 1.73M ===\n";

$target = 1730000000;
$tupok_matches = abs($tupok_total_amount - $target) < 1;
$lunas_matches = abs($lunas_total_amount - $target) < 1;
$total_matches = abs($total_recovery - $target) < 1;

echo "Tupok Amount:  " . number_format($tupok_total_amount, 0) . ($tupok_matches ? " ✅ MATCHES 1.73M" : "") . "\n";
echo "Lunas Amount:  " . number_format($lunas_total_amount, 0) . ($lunas_matches ? " ✅ MATCHES 1.73M" : "") . "\n";
echo "Total Amount:  " . number_format($total_recovery, 0) . ($total_matches ? " ✅ MATCHES 1.73M" : "") . "\n";

// Check by branch in snapshot
echo "\nChecking by Branch:\n";
foreach ($snap_result as $row) {
    $matches = abs($row['t'] - $target) < 1;
    if ($matches) {
        echo "  {$row['kanca_key']}: " . number_format($row['t'], 0) . " ✅ MATCHES 1.73M\n";
    } else {
        echo "  {$row['kanca_key']}: " . number_format($row['t'], 0) . "\n";
    }
}
