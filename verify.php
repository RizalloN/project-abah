<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$periods = DB::table('daily_loan_dinamis')
    ->select('periode')
    ->distinct()
    ->orderByDesc('periode')
    ->pluck('periode')
    ->toArray();

echo "Periods in daily_loan_dinamis:\n";
print_r($periods);

$segments = DB::table('daily_loan_dinamis')
    ->select('segmen_kinerja', 'segmen_dashboard')
    ->distinct()
    ->get()
    ->toArray();

echo "\nSegments in daily_loan_dinamis:\n";
print_r($segments);
