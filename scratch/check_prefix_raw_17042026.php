<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$sql = "SELECT COUNT(*) AS total FROM daily_loan_dinamis WHERE periode = '2026-04-17' AND uniqueid_namareport LIKE 'imp69e6fec6a17c5702338989_%'";
$result = DB::selectOne($sql);
var_export($result);
