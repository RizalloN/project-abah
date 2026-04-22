<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Removing incorrect duplicate RKA data ===\n\n";

// Delete the data I copied to other branches
$branches_to_delete = ['KC Madiun', 'KC Magetan', 'KC Ngawi'];

foreach($branches_to_delete as $branch) {
    $deleted = DB::table('rka')
        ->where('kanca', $branch)
        ->delete();
    echo "Deleted $deleted rows for $branch\n";
}

echo "\n=== Verifying RKA table after cleanup ===\n";
$remaining = DB::table('rka')
    ->select('kanca')
    ->distinct()
    ->orderBy('kanca')
    ->pluck('kanca');

foreach($remaining as $b) {
    $count = DB::table('rka')->where('kanca', $b)->count();
    echo "  - $b: $count rows\n";
}

echo "\n=== Sample desc_uker patterns for regional branches ===\n";
$patterns = DB::table('rka')
    ->select('desc_uker')
    ->distinct()
    ->whereRaw("desc_uker LIKE '%KC Madiun%' OR desc_uker LIKE '%KC Ngawi%' OR desc_uker LIKE '%KC Magetan%'")
    ->orderBy('desc_uker')
    ->limit(30)
    ->pluck('desc_uker');

foreach($patterns as $p) {
    echo "  - $p\n";
}
