<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$count = Illuminate\Support\Facades\DB::table('daily_loan_dinamis')->where('periode', '2026-04-19')->count();
echo $count, PHP_EOL;
