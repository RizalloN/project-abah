<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$sample = Illuminate\Support\Facades\DB::table('daily_loan_dinamis')
    ->where('periode', '2026-04-19')
    ->orderBy('uniqueid_namareport')
    ->limit(3)
    ->get(['uniqueid_namareport','periode','nomor_rekening1','cifno']);
echo json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
