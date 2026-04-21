<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$procs = Illuminate\Support\Facades\DB::select("SHOW FULL PROCESSLIST");
$filtered = array_values(array_filter($procs, function($row) {
    return in_array((int) $row->Id, [1824, 1825], true);
}));
echo json_encode($filtered, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
