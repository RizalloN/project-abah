<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$recentNull = Illuminate\Support\Facades\DB::table('daily_loan_dinamis')
    ->whereNull('periode')
    ->where('created_at', '>=', '2026-04-21 16:45:00')
    ->count();

$recentDates = Illuminate\Support\Facades\DB::table('daily_loan_dinamis')
    ->where('created_at', '>=', '2026-04-21 16:45:00')
    ->selectRaw('periode, COUNT(*) as cnt')
    ->groupBy('periode')
    ->orderByDesc('cnt')
    ->get();

echo json_encode([
    'recent_null_periode' => $recentNull,
    'recent_periods' => $recentDates,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
