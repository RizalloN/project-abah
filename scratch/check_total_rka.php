<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$totalA1 = DB::table('rka')
    ->whereYear('created_at', 2026)
    ->where('mata_anggaran', 'A.1. DPK Retail Funding Total')
    ->sum('apr');

echo "Total A.1. for ALL units: " . number_format($totalA1, 2) . "\n";
