<?php

include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$targetDate = '2026-04-18';
$snap = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $targetDate)
    ->where('unit_label', 'NOT LIKE', '%KC%') // find a unit
    ->first();

if ($snap) {
    echo "Unit: {$snap->unit_label}\n";
    echo "  Total OS: " . number_format($snap->total_os) . "\n";
    echo "  Micro OS: " . number_format($snap->micro_os) . "\n";
    echo "  Small OS: " . number_format($snap->kecil_os) . "\n";
    echo "  Cons OS:  " . number_format($snap->consumer_os) . "\n";
    echo "  Med OS:   " . number_format($snap->medium_os) . "\n";
    
    $sum = $snap->micro_os + $snap->kecil_os + $snap->consumer_os + $snap->medium_os;
    echo "  Sum of segments: " . number_format($sum) . "\n";
} else {
    echo "No row found.\n";
}
