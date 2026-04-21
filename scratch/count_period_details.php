<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$rows = Illuminate\Support\Facades\DB::table('daily_loan_dinamis')
    ->where('periode', '2026-04-19')
    ->selectRaw('COUNT(*) as total, COUNT(DISTINCT nomor_rekening1) as uniq_rekening, COUNT(DISTINCT cifno) as uniq_cif')
    ->first();
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
