<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$curr = '2026-05-14';
$prev = '2026-04-30';

// Load snapshot for Madiun curr & prev separately, then PHP-join.
echo "Loading prev (Madiun)..." . PHP_EOL;
$prevByAcc = [];
$prevAnyByAcc = [];

// prev Madiun (filtered)
DB::table('dashboard_pinjaman_snapshots')
    ->where('periode', $prev)
    ->where('cabang1', 'KC Madiun')
    ->select('account_number','loan_balance','quality_bucket')
    ->orderBy('account_number')
    ->chunk(50000, function($chunk) use (&$prevByAcc) {
        foreach ($chunk as $r) {
            $prevByAcc[$r->account_number] = ['balance' => $r->loan_balance, 'bucket' => $r->quality_bucket];
        }
    });
echo "  prev Madiun: " . count($prevByAcc) . PHP_EOL;

echo "Loading curr (Madiun)..." . PHP_EOL;
$currByAcc = [];
DB::table('dashboard_pinjaman_snapshots')
    ->where('periode', $curr)
    ->where('cabang1', 'KC Madiun')
    ->select('account_number','loan_balance','quality_bucket')
    ->orderBy('account_number')
    ->chunk(50000, function($chunk) use (&$currByAcc) {
        foreach ($chunk as $r) {
            $currByAcc[$r->account_number] = ['balance' => $r->loan_balance, 'bucket' => $r->quality_bucket];
        }
    });
echo "  curr Madiun: " . count($currByAcc) . PHP_EOL;

// Load PH at appropriate period (for selectedPeriod=2026-05-14, phPeriod usually = 2026-05-31 EOM)
// But we want exit-account detection on prev->curr.
// Per resolvePhPeriod: takes EOM of selectedPeriod. So phPeriod = 2026-05-31.
// PH table is lw325_ph.
$phPeriod = DB::table('lw325_ph')->where('periode', '<=', $curr)->max('periode');
echo "Loading PH for $phPeriod..." . PHP_EOL;
$phByAcc = [];
foreach (DB::table('lw325_ph')->where('periode', $phPeriod)->where('pokok', '>', 0)->select('acctno')->get() as $r) {
    $phByAcc[trim($r->acctno)] = true;
}
echo "  PH: " . count($phByAcc) . PHP_EOL;

// Find accounts in prev Madiun that are NOT in curr Madiun → exit accounts
$exitAccounts = [];
foreach ($prevByAcc as $acc => $info) {
    if (!isset($currByAcc[$acc])) {
        $exitAccounts[$acc] = $info;
    }
}
echo PHP_EOL . "Exit accounts from Madiun (prev → not in curr Madiun): " . count($exitAccounts) . PHP_EOL;

// Now break down: PH vs Lunas (no PH), grouped by prev_bucket
$summary = [];  // [bucket][type] => ['n'=>x, 'total'=>y]
$lunasTotal = 0;
$phTotal = 0;

foreach ($exitAccounts as $acc => $info) {
    $bucket = $info['bucket'];
    $type = isset($phByAcc[$acc]) ? 'ph' : 'lunas';
    $summary[$bucket][$type]['n'] = ($summary[$bucket][$type]['n'] ?? 0) + 1;
    $summary[$bucket][$type]['total'] = ($summary[$bucket][$type]['total'] ?? 0) + $info['balance'];
    if ($type === 'lunas') $lunasTotal += $info['balance'];
    if ($type === 'ph') $phTotal += $info['balance'];
}

echo PHP_EOL . "Breakdown exit Madiun by prev_bucket:" . PHP_EOL;
printf("%-12s %20s %20s\n", 'prev_bucket', 'PH (n/total)', 'Lunas (n/total)');
foreach (['L','LR','DPK 1','DPK 2','DPK 3','KL','D1','D2','M'] as $b) {
    $ph = $summary[$b]['ph'] ?? ['n'=>0,'total'=>0];
    $lns = $summary[$b]['lunas'] ?? ['n'=>0,'total'=>0];
    printf("%-12s %3d / %14s %3d / %14s\n",
        $b,
        $ph['n'], number_format($ph['total'], 0),
        $lns['n'], number_format($lns['total'], 0));
}
echo PHP_EOL . "Total Lunas exit Madiun: " . number_format($lunasTotal, 0) . PHP_EOL;
echo "Total PH    exit Madiun: " . number_format($phTotal, 0) . PHP_EOL;

// Cross-check: what does controller's metric say for Madiun "Lunas" grand total?
// From earlier output: "Lunas grand total Madiun = 2,527,317,871"
// And "PH grand total Madiun = 6,775,478"
echo PHP_EOL . "Controller live metric showed:" . PHP_EOL;
echo "  Lunas grand total Madiun = 2,527,317,871" . PHP_EOL;
echo "  PH    grand total Madiun = 6,775,478" . PHP_EOL;

// Also check accounts in prev Madiun that moved to OTHER branch in curr (still exist somewhere)
echo PHP_EOL . "Loading curr ALL branches to detect cross-branch moves..." . PHP_EOL;
$currAnyByAcc = [];
DB::table('dashboard_pinjaman_snapshots')
    ->where('periode', $curr)
    ->select('account_number','cabang1')
    ->orderBy('account_number')
    ->chunk(50000, function($chunk) use (&$currAnyByAcc) {
        foreach ($chunk as $r) {
            $currAnyByAcc[$r->account_number] = $r->cabang1;
        }
    });
echo "  curr ANY: " . count($currAnyByAcc) . PHP_EOL;

$movedOut = 0; $movedOutTotal = 0;
$gone = 0; $goneTotal = 0;
foreach ($exitAccounts as $acc => $info) {
    if (isset($currAnyByAcc[$acc])) {
        $movedOut++;
        $movedOutTotal += $info['balance'];
    } else {
        $gone++;
        $goneTotal += $info['balance'];
    }
}
echo PHP_EOL . "Exit Madiun = MOVED to other branch in curr: $movedOut accounts, total " . number_format($movedOutTotal, 0) . PHP_EOL;
echo "Exit Madiun = GONE (not in curr at all): $gone accounts, total " . number_format($goneTotal, 0) . PHP_EOL;
