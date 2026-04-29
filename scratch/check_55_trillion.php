<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$sumAll = DB::table('rka')
    ->whereYear('created_at', 2026)
    ->where(function($q) {
        $q->where('kanca', 'like', '%MADIUN%')
          ->orWhere('desc_uker', 'like', '%MADIUN%');
    })
    ->sum('apr');

echo "Sum of ALL RKA rows for Madiun: " . number_format($sumAll, 2, '.', ',') . "\n";
