<?php
include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$periods = DB::table('lw325_ph')->select('periode')->distinct()->limit(10)->get();
echo "Periods in lw325_ph:\n";
foreach ($periods as $p) {
    echo "  " . $p->periode . "\n";
}
