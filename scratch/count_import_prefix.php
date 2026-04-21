<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$prefix = 'imp69e748adde3fa830331821_%';
$rows = Illuminate\Support\Facades\DB::table('daily_loan_dinamis')
    ->where('uniqueid_namareport', 'like', $prefix)
    ->selectRaw('COUNT(*) as total, SUM(CASE WHEN periode IS NULL THEN 1 ELSE 0 END) as null_periode, SUM(CASE WHEN periode = ? THEN 1 ELSE 0 END) as exact_period', ['2026-04-19'])
    ->first();

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
