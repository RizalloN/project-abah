<?php
include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$segments = Illuminate\Support\Facades\DB::table('cognos_recovery')->select('segmen_bisnis_2025')->distinct()->get();
print_r($segments->toArray());

$sample = Illuminate\Support\Facades\DB::table('cognos_recovery')->limit(1)->first();
print_r($sample);
