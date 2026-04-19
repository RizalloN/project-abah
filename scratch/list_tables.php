<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$tables = DB::select('SHOW TABLES');
foreach ($tables as $table) {
    foreach ($table as $key => $name) {
        echo $name . PHP_EOL;
    }
}
