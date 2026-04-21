<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$sample = Illuminate\Support\Facades\DB::table('daily_loan_dinamis')
    ->where('uniqueid_namareport', 'like', 'imp69e748adde3fa830331821_%')
    ->whereNull('periode')
    ->limit(20)
    ->get(['uniqueid_namareport','periode','nomor_rekening1','cifno','cabang1','unit1','created_at']);
echo json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
