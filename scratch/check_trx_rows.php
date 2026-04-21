<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$trx = Illuminate\Support\Facades\DB::select("SELECT trx_id, trx_started, trx_state, trx_mysql_thread_id, trx_rows_locked, trx_rows_modified FROM information_schema.innodb_trx ORDER BY trx_started ASC");
echo json_encode($trx, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
