<?php
include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$date = '2026-02-28';
echo "Checking Cognos Branches for $date...\n";

$branches = DB::table('cognos_recovery')
    ->where('periode', $date)
    ->select('cabang')
    ->distinct()
    ->get();

foreach ($branches as $b) {
    echo "  " . $b->cabang . "\n";
}

$area6Filters = ['Madiun', 'Magetan', 'Ngawi', 'Ponorogo'];
$area6Total = DB::table('cognos_recovery')
    ->where('periode', $date)
    ->where(function($q) use ($area6Filters) {
        foreach ($area6Filters as $f) {
            $q->orWhere('cabang', 'LIKE', '%' . $f . '%');
        }
    })
    ->sum('total_recovery');

echo "\nFiltered Area 6 Cognos Total: " . number_format($area6Total) . "\n";
