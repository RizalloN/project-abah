<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$periods = DB::table('ssa_simpanan')
    ->select('Month_Day_Year_of_Posisi')
    ->distinct()
    ->orderByDesc('Month_Day_Year_of_Posisi')
    ->limit(10)
    ->pluck('Month_Day_Year_of_Posisi');

echo json_encode($periods, JSON_PRETTY_PRINT) . PHP_EOL;
