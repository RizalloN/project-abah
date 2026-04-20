<?php
include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$period = Illuminate\Support\Facades\DB::table('cognos_recovery')->select('periode')->distinct()->limit(5)->get();
print_r($period->toArray());
