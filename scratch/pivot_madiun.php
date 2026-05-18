<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$curr = '2026-05-14';
$prev = '2026-04-30';
$branches = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];

$labels = ['New Account', 'L', 'LR', 'DPK 1', 'DPK 2', 'DPK 3', 'KL', 'D1', 'D2', 'M'];
$cols   = ['L', 'LR', 'DPK 1', 'DPK 2', 'DPK 3', 'KL', 'D1', 'D2', 'M'];

// 1) Load curr rows for 4 branches
echo "Loading curr rows..." . PHP_EOL;
$currRows = DB::table('dashboard_pinjaman_snapshots')
    ->where('periode', $curr)
    ->whereIn('cabang1', $branches)
    ->select('account_number', 'cabang1', 'quality_bucket', 'loan_balance')
    ->get();
echo "  curr rows: " . $currRows->count() . PHP_EOL;

// 2) Load prev bucket map (account -> bucket) for ALL branches (any-branch lookup)
echo "Loading prev bucket map (any branch)..." . PHP_EOL;
$prevMap = [];
DB::table('dashboard_pinjaman_snapshots')
    ->where('periode', $prev)
    ->select('account_number', 'quality_bucket')
    ->orderBy('account_number')
    ->chunk(50000, function ($chunk) use (&$prevMap) {
        foreach ($chunk as $r) {
            $prevMap[$r->account_number] = $r->quality_bucket;
        }
    });
echo "  prev map: " . count($prevMap) . PHP_EOL;

// 3) Build matrix per branch
$byBranch = [];
foreach ($currRows as $r) {
    $before = $prevMap[$r->account_number] ?? 'New Account';
    $after = $r->quality_bucket;
    $byBranch[$r->cabang1][$before][$after] = ($byBranch[$r->cabang1][$before][$after] ?? 0) + (float) $r->loan_balance;
}

foreach ($branches as $branch) {
    echo PHP_EOL . "=== $branch | posisi=$curr | delta MTD=$prev ===" . PHP_EOL;
    $mat = $byBranch[$branch] ?? [];

    printf("%-12s", "");
    foreach ($cols as $c) printf(" %18s", $c);
    printf(" %18s\n", "Total");

    $colTotals = array_fill_keys($cols, 0.0);
    foreach ($labels as $row) {
        printf("%-12s", $row);
        $rowTotal = 0;
        foreach ($cols as $c) {
            $v = $mat[$row][$c] ?? 0;
            $rowTotal += $v;
            $colTotals[$c] += $v;
            printf(" %18s", number_format($v, 0));
        }
        printf(" %18s\n", number_format($rowTotal, 0));
    }
    printf("%-12s", 'Grand Total');
    $gt = 0;
    foreach ($cols as $c) {
        $gt += $colTotals[$c];
        printf(" %18s", number_format($colTotals[$c], 0));
    }
    printf(" %18s\n", number_format($gt, 0));
}
