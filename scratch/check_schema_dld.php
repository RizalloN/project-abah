<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require_once dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$columns = DB::select("DESCRIBE daily_loan_dinamis");
foreach ($columns as $col) {
    echo "{$col->Field}: {$col->Type}\n";
}
