<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$count = DB::table('lw325_ph')->where('periode', '2026-04-04')->count();
$sum_pokok = DB::table('lw325_ph')->where('periode', '2026-04-04')->sum('pokok');
$sum_bunga = DB::table('lw325_ph')->where('periode', '2026-04-04')->sum('bunga');

echo "Total Records: " . $count . PHP_EOL;
echo "Total Pokok: " . $sum_pokok . PHP_EOL;
echo "Total Bunga: " . $sum_bunga . PHP_EOL;
