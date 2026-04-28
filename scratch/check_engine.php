<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$status = DB::select("SHOW TABLE STATUS WHERE Name = 'simpanan_multipn'");
echo json_encode($status[0], JSON_PRETTY_PRINT);
