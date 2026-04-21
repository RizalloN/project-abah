<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo json_encode([
    'db_count' => Illuminate\Support\Facades\DB::table('daily_loan_dinamis')->where('periode', '2026-04-19')->count(),
    'max_period' => Illuminate\Support\Facades\DB::table('daily_loan_dinamis')->max('periode'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
