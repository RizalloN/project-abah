<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$row = DB::table('dashboard_harian_snapshots')
    ->where('kanca_key', 'KC Madiun')
    ->where('period', '2026-04-28')
    ->select('rec_dh_total', 'rec_dh_small', 'rec_dh_consumer', 'rec_dh_micro')
    ->first();

if ($row) {
    echo "Found Madiun:\n";
    print_r($row);
} else {
    echo "No row for Madiun 2026-04-28 yet.\n";
}
