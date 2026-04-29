<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Support\RkaLookupService;

$results = DB::table('rka')
    ->where('kanca', 'LIKE', '%Ponorogo%')
    ->where('mata_anggaran', 'LIKE', '%Recovery%')
    ->select('mata_anggaran')
    ->distinct()
    ->pluck('mata_anggaran');

echo json_encode($results, JSON_PRETTY_PRINT);
