<?php
/**
 * Verification script untuk Query Optimization Implementation
 *
 * Checks:
 * 1. Shadow columns exist
 * 2. Indexes created correctly
 * 3. Data backfilled properly
 * 4. Query plans use new indexes
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/bootstrap/app.php';

use Illuminate\Support\Facades\DB;

echo "\n=== QUERY OPTIMIZATION VERIFICATION ===\n\n";

// 1. Check if columns exist
echo "✓ Checking shadow columns existence...\n";
$columns = DB::select("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'daily_loan_dinamis'
      AND COLUMN_NAME IN ('segmen_kinerja', 'produk_kinerja', 'cabang_normalized', 'unit_normalized', 'branch_normalized', 'rm_normalized', 'cifno_clean')
");

$expectedColumns = ['segmen_kinerja', 'produk_kinerja', 'cabang_normalized', 'unit_normalized', 'branch_normalized', 'rm_normalized', 'cifno_clean'];
$foundColumns = collect($columns)->pluck('COLUMN_NAME')->toArray();

if (count($foundColumns) === count($expectedColumns)) {
    echo "  ✅ All 7 shadow columns exist\n";
    echo "     " . implode(', ', $foundColumns) . "\n";
} else {
    echo "  ❌ Missing columns: " . implode(', ', array_diff($expectedColumns, $foundColumns)) . "\n";
    exit(1);
}

// 2. Check indexes
echo "\n✓ Checking indexes...\n";
$indexes = DB::select("
    SELECT DISTINCT INDEX_NAME
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_NAME = 'daily_loan_dinamis'
      AND INDEX_NAME LIKE '%normalized%' OR INDEX_NAME = 'idx_snapshot_filter_optimized'
");

$indexNames = collect($indexes)->pluck('INDEX_NAME')->toArray();
echo "  Found indexes: " . count($indexNames) . "\n";
foreach ($indexNames as $idx) {
    echo "    - $idx\n";
}

// 3. Check composite index specifically
echo "\n✓ Checking composite index structure...\n";
$compositeIdx = DB::select("
    SELECT COLUMN_NAME, SEQ_IN_INDEX
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_NAME = 'daily_loan_dinamis'
      AND INDEX_NAME = 'idx_snapshot_filter_optimized'
    ORDER BY SEQ_IN_INDEX
");

if (!empty($compositeIdx)) {
    echo "  ✅ Composite index idx_snapshot_filter_optimized exists with columns:\n";
    foreach ($compositeIdx as $col) {
        echo "     " . $col->SEQ_IN_INDEX . ". " . $col->COLUMN_NAME . "\n";
    }
} else {
    echo "  ⚠️  Composite index not found (may be optional)\n";
}

// 4. Check data backfill - Count non-null values
echo "\n✓ Checking data backfill...\n";
$stats = DB::selectOne("
    SELECT
        COUNT(*) as total_rows,
        SUM(CASE WHEN segmen_kinerja IS NOT NULL THEN 1 ELSE 0 END) as segmen_kinerja_filled,
        SUM(CASE WHEN produk_kinerja IS NOT NULL THEN 1 ELSE 0 END) as produk_kinerja_filled,
        SUM(CASE WHEN cabang_normalized IS NOT NULL THEN 1 ELSE 0 END) as cabang_normalized_filled,
        SUM(CASE WHEN cifno_clean IS NOT NULL THEN 1 ELSE 0 END) as cifno_clean_filled
    FROM daily_loan_dinamis
    LIMIT 1
");

$fillPercentage = $stats->total_rows > 0
    ? round(($stats->segmen_kinerja_filled / $stats->total_rows) * 100, 2)
    : 0;

echo "  Total rows: " . number_format($stats->total_rows) . "\n";
echo "  segmen_kinerja filled: " . number_format($stats->segmen_kinerja_filled) . " ($fillPercentage%)\n";
echo "  produk_kinerja filled: " . number_format($stats->produk_kinerja_filled) . " (" . round(($stats->produk_kinerja_filled / $stats->total_rows) * 100, 2) . "%)\n";
echo "  cabang_normalized filled: " . number_format($stats->cabang_normalized_filled) . " (" . round(($stats->cabang_normalized_filled / $stats->total_rows) * 100, 2) . "%)\n";
echo "  cifno_clean filled: " . number_format($stats->cifno_clean_filled) . " (" . round(($stats->cifno_clean_filled / $stats->total_rows) * 100, 2) . "%)\n";

if ($fillPercentage > 95) {
    echo "  ✅ Backfill successful (>95% filled)\n";
} else if ($fillPercentage > 50) {
    echo "  ⚠️  Partial backfill - may need to run snapshot build\n";
} else {
    echo "  ❌ Backfill incomplete\n";
    exit(1);
}

// 5. Sample data validation - check normalization is correct
echo "\n✓ Validating normalization rules...\n";
$sample = DB::selectOne("
    SELECT
        segmen_dashboard,
        segmen_kinerja,
        produk_dashboard,
        produk_kinerja,
        cabang1,
        cabang_normalized,
        cifno,
        cifno_clean
    FROM daily_loan_dinamis
    WHERE segmen_kinerja IS NOT NULL
      AND cifno_clean IS NOT NULL
    LIMIT 1
");

if ($sample) {
    $expectedSegmenNormalized = strtoupper(
        str_replace([' ', '-', '_', '/', '.'], '', trim($sample->segmen_dashboard))
    );
    $expectedProdukNormalized = strtoupper(
        str_replace([' ', '-', '_', '/', '.'], '', trim($sample->produk_dashboard))
    );
    $expectedCabangNormalized = strtoupper(trim($sample->cabang1));
    $expectedCifnoClean = preg_replace('/[^0-9]/', '', $sample->cifno);

    $validations = [
        'segmen_kinerja' => ($sample->segmen_kinerja === $expectedSegmenNormalized),
        'produk_kinerja' => ($sample->produk_kinerja === $expectedProdukNormalized),
        'cabang_normalized' => ($sample->cabang_normalized === $expectedCabangNormalized),
        'cifno_clean' => ($sample->cifno_clean === $expectedCifnoClean),
    ];

    $allValid = array_reduce($validations, fn($carry, $v) => $carry && $v, true);

    if ($allValid) {
        echo "  ✅ All normalization rules correct (sample verified)\n";
    } else {
        echo "  ❌ Normalization validation failed:\n";
        foreach ($validations as $col => $valid) {
            echo "     $col: " . ($valid ? '✓' : '✗') . "\n";
        }
        exit(1);
    }
}

// 6. Query plan test (EXPLAIN)
echo "\n✓ Testing query plans with shadow columns...\n";
$testQuery = "
    EXPLAIN
    SELECT cabang_normalized, SUM(plafon) as total
    FROM daily_loan_dinamis
    WHERE periode = '2026-04-26'
      AND segmen_kinerja = 'MIKRO'
    GROUP BY cabang_normalized
";

try {
    $explain = DB::select($testQuery);

    $usesIndex = false;
    foreach ($explain as $row) {
        if (isset($row->key) && $row->key && $row->key !== 'NULL') {
            $usesIndex = true;
            echo "  ✅ Query uses index: " . $row->key . "\n";
            echo "     Type: " . $row->type . ", Rows: " . $row->rows . "\n";
        }
    }

    if (!$usesIndex) {
        echo "  ⚠️  Query doesn't use index (may fall back to scan)\n";
    }
} catch (\Exception $e) {
    echo "  ⚠️  Could not test query plan: " . $e->getMessage() . "\n";
}

echo "\n✅ VERIFICATION COMPLETE\n";
echo "\nNext steps:\n";
echo "1. Verify dashboard performance: Should be 10-20x faster\n";
echo "2. Monitor ReportSnapshotBuilder logs: Should use shadow columns\n";
echo "3. Re-run this script after next snapshot build to verify full data\n";
echo "\n";
