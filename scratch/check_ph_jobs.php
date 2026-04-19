<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$jobs = DB::table('import_jobs')
    ->where('id_report', 15)
    ->orderBy('created_at', 'desc')
    ->get();
print_r($jobs);
