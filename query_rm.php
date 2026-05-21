<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$rm_like = '%00071807%';
$rows = DB::table('daily_loan_dinamis')
    ->where('pn_pengelola1', 'like', $rm_like)
    ->orWhere('rm_normalized', 'like', $rm_like)
    ->select('periode', 'pn_pengelola1', 'rm_normalized', 'cabang1', 'cabang_normalized', 'produk_dashboard', 'produk_kinerja', 'segmen_kinerja', 'plafon')
    ->limit(10)
    ->get()
    ->toArray();

echo "Rows in daily_loan_dinamis for 00071807:\n";
print_r($rows);
