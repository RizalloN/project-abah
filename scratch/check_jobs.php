<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$jobs = DB::table('import_jobs')->orderBy('created_at', 'desc')->limit(10)->get();
print_r($jobs);
