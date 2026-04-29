<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$result = \Illuminate\Support\Facades\DB::select("
SELECT 
  periode,
  COUNT(*) AS total_rows,
  SUM(CASE WHEN segmen_kinerja IS NOT NULL THEN 1 ELSE 0 END) AS segmen_filled,
  SUM(CASE WHEN produk_kinerja IS NOT NULL THEN 1 ELSE 0 END) AS produk_filled,
  SUM(CASE WHEN cifno_clean IS NOT NULL THEN 1 ELSE 0 END) AS cifno_filled,
  ROUND(100 * SUM(CASE WHEN segmen_kinerja IS NOT NULL THEN 1 ELSE 0 END) / COUNT(*), 2) AS fill_pct
FROM daily_loan_dinamis
WHERE periode IN ('2026-04-25', '2026-04-26')
GROUP BY periode
");

echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║          BACKFILL VERIFICATION RESULTS                     ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

foreach ($result as $row) {
    echo "Period: {$row->periode}\n";
    echo "  Total Rows: " . number_format($row->total_rows) . "\n";
    echo "  Segmen Filled: " . number_format($row->segmen_filled) . "\n";
    echo "  Produk Filled: " . number_format($row->produk_filled) . "\n";
    echo "  CIF Clean Filled: " . number_format($row->cifno_filled) . "\n";
    echo "  Fill %: {$row->fill_pct}%\n\n";
}

// Check if ANY null values remain
$nullCheck = \Illuminate\Support\Facades\DB::selectOne("
SELECT COUNT(*) AS rows_still_null
FROM daily_loan_dinamis
WHERE periode IN ('2026-04-25', '2026-04-26')
AND (
  segmen_kinerja IS NULL
  OR produk_kinerja IS NULL
  OR cabang_normalized IS NULL
  OR rm_normalized IS NULL
  OR cifno_clean IS NULL
)
");

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║          NULL VERIFICATION                                 ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";
echo "Rows with any NULL shadow columns: " . $nullCheck->rows_still_null . "\n";
echo ($nullCheck->rows_still_null == 0 ? "✅ ALL SHADOW COLUMNS POPULATED!" : "⚠ Some rows still have NULLs") . "\n";
?>
