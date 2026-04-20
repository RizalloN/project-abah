<?php

include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$targetDate = '2026-04-18';

echo "Segment Breakdown for $targetDate:\n";

$breakdown = DB::table('ssa_pinjaman')
    ->where('month_day_year_of_periode', $targetDate)
    ->select('segmen_dashboard')
    ->selectRaw('SUM(baki_debet) as total')
    ->groupBy('segmen_dashboard')
    ->get();

foreach ($breakdown as $row) {
    echo "  Segment: " . ($row->segmen_dashboard ?: 'NULL') . " -> " . number_format($row->total) . "\n";
}
