<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$columns = DB::select("DESCRIBE simpanan_multipn");
foreach ($columns as $col) {
    if (in_array($col->Field, ['uniqueid_SMPN', 'created_at'])) {
        print_r($col);
    }
}
print_r(DB::select("SHOW TABLE STATUS LIKE 'simpanan_multipn'"));
