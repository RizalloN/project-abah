<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== RKA Table Analysis ===\n\n";

// Total count
$total = DB::table('rka')->count();
echo "Total rows in RKA table: $total\n";

// Count by unique branches
$branches = DB::table('rka')->distinct('kanca')->pluck('kanca');
echo "\nUnique branches in RKA:\n";
foreach($branches as $b) {
    $count = DB::table('rka')->where('kanca', $b)->count();
    echo "  - $b: $count rows\n";
}

// Check created_at dates
echo "\n=== RKA Data Dates ===\n";
$dates = DB::table('rka')
    ->selectRaw('DATE(created_at) as created_date')
    ->selectRaw('COUNT(*) as cnt')
    ->groupBy('created_date')
    ->orderBy('created_date', 'desc')
    ->limit(10)
    ->get();

foreach($dates as $d) {
    echo "  - {$d->created_date}: {$d->cnt} rows\n";
}

// Check if other branches have any data at all in the data tables
echo "\n=== Checking data tables for other branches ===\n";

$branches_in_edc = DB::table('jumlah_merchant_detail')
    ->select('NAMA_KANCA')
    ->distinct()
    ->orderBy('NAMA_KANCA')
    ->pluck('NAMA_KANCA');

echo "Branches in jumlah_merchant_detail (EDC):\n";
foreach($branches_in_edc as $b) {
    echo "  - $b\n";
}

$branches_in_qris = DB::table('jumlah_merchant_qris_detail')
    ->select('MBDESC')
    ->distinct()
    ->orderBy('MBDESC')
    ->pluck('MBDESC');

echo "\nBranches in jumlah_merchant_qris_detail (QRIS):\n";
foreach($branches_in_qris as $b) {
    echo "  - $b\n";
}
