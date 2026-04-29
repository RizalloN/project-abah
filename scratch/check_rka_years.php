<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$results = DB::table('rka')
    ->selectRaw('YEAR(created_at) as year, COUNT(*) as count')
    ->groupBy('year')
    ->get();

echo "RKA YEAR DISTRIBUTION:\n";
foreach ($results as $row) {
    echo "Year: " . $row->year . " | Count: " . $row->count . "\n";
}
