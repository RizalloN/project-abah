<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$dates = DB::table('rka')
    ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
    ->groupBy('date')
    ->get();

echo "RKA Import Dates:\n";
foreach ($dates as $d) {
    echo "- {$d->date}: {$d->count} rows\n";
}
