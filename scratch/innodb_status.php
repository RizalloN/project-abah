<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$status = Illuminate\Support\Facades\DB::selectOne('SHOW ENGINE INNODB STATUS');
$text = $status->Status ?? $status->status ?? '';
echo substr($text, 0, 5000), PHP_EOL;
