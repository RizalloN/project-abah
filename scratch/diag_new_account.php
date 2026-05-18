<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$curr = '2026-05-14';
$prev = '2026-04-30';
$branch = 'KC Madiun';

// Compare 3 definitions of "NEW" for Madiun:
//   A) Account does not exist in prev period AT ALL  -> truly new
//   B) Account does not exist in prev period AT THIS BRANCH -> branch-scoped new
//   C) Account exists in prev period in DIFFERENT branch (moved into Madiun)

$sql = "
SELECT
    c.quality_bucket AS after_bucket,
    SUM(CASE WHEN p_any.account_number IS NULL THEN c.loan_balance ELSE 0 END) AS new_any,
    SUM(CASE WHEN p_brn.account_number IS NULL THEN c.loan_balance ELSE 0 END) AS new_branch_scope,
    SUM(CASE WHEN p_any.account_number IS NOT NULL AND p_brn.account_number IS NULL THEN c.loan_balance ELSE 0 END) AS moved_in
FROM dashboard_pinjaman_snapshots c
LEFT JOIN dashboard_pinjaman_snapshots p_any
    ON p_any.account_number = c.account_number AND p_any.periode = ?
LEFT JOIN dashboard_pinjaman_snapshots p_brn
    ON p_brn.account_number = c.account_number AND p_brn.periode = ? AND p_brn.cabang1 = c.cabang1
WHERE c.periode = ?
    AND c.cabang1 = ?
GROUP BY c.quality_bucket
ORDER BY c.quality_bucket
";

$rows = DB::select($sql, [$prev, $prev, $curr, $branch]);

echo "Madiun $curr - NEW breakdown by after_bucket:" . PHP_EOL;
printf("%-10s %20s %20s %20s\n", 'bucket', 'NEW(any)', 'NEW(branch)', 'moved_in');
$totA = $totB = $totC = 0;
foreach ($rows as $r) {
    $totA += $r->new_any;
    $totB += $r->new_branch_scope;
    $totC += $r->moved_in;
    printf("%-10s %20s %20s %20s\n",
        $r->after_bucket,
        number_format($r->new_any, 0),
        number_format($r->new_branch_scope, 0),
        number_format($r->moved_in, 0));
}
printf("%-10s %20s %20s %20s\n", 'TOTAL',
    number_format($totA, 0),
    number_format($totB, 0),
    number_format($totC, 0));

echo PHP_EOL . "Excel says NEW row: L=66,435,140,000  SML1=428,886,242  GT=66,864,026,242" . PHP_EOL;
