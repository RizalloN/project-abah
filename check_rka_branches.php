<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Checking RKA Table Data ===\n\n";

// Check which branches have RKA data
$branches = DB::table('rka')
    ->select('kanca')
    ->distinct()
    ->orderBy('kanca')
    ->pluck('kanca');

echo "Branches in RKA table:\n";
foreach($branches as $b) {
    echo "  - $b\n";
}

echo "\n=== Sample RKA Data for February ===\n";
$sample = DB::table('rka')
    ->select('kanca', 'desc_uker', 'mata_anggaran', 'feb')
    ->where('mata_anggaran', 'like', '%Merchant%')
    ->orWhere('mata_anggaran', 'like', '%QRIS%')
    ->orderBy('kanca', 'desc')
    ->limit(30)
    ->get();

echo "Total rows found: " . count($sample) . "\n\n";
foreach($sample as $row) {
    echo sprintf("Kanca: %-20s | Uker: %-20s | Mata Anggaran: %-40s | Feb: %s\n",
        substr($row->kanca, 0, 20),
        substr($row->desc_uker, 0, 20),
        substr($row->mata_anggaran, 0, 40),
        $row->feb
    );
}

echo "\n=== RKA Data Count by Branch ===\n";
$counts = DB::table('rka')
    ->select('kanca')
    ->selectRaw('COUNT(*) as cnt')
    ->groupBy('kanca')
    ->orderBy('cnt', 'desc')
    ->get();

foreach($counts as $c) {
    echo sprintf("%-20s: %d rows\n", $c->kanca, $c->cnt);
}
