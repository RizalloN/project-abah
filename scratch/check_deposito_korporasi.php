<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$val = DB::table('rka')
    ->whereYear('created_at', 2026)
    ->where(function($q) {
        $q->where('kanca', 'like', '%MADIUN%')
          ->orWhere('desc_uker', 'like', '%MADIUN%');
    })
    ->where('mata_anggaran', 'A.2.b. Deposito Korporasi')
    ->sum('apr');

echo "Sum A.2.b. Deposito Korporasi: " . $val . "\n";
