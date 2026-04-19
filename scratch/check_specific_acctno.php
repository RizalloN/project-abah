<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$row = DB::table('lw325_ph')
    ->where('acctno', '814601007586100')
    ->where('periode', '2026-04-04')
    ->first();
print_r($row);
