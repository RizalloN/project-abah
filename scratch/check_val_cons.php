<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require_once dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$data = DB::table('ssa_simpanan_snapshots')->select('periode', 'Month_Day_Year_of_Posisi')->limit(5)->get();
print_r($data->toArray());
