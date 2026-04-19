<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$rows = DB::table('lw325_ph')
    ->where('periode', '2026-04-04')
    ->limit(5)
    ->get();
print_r($rows);
