<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('rka')
    ->whereYear('created_at', 2026)
    ->where('kanca', 'like', '%MADIUN%')
    ->where('mata_anggaran', 'A.2. DPK Korporasi')
    ->get();

foreach ($rows as $row) {
    echo "UKER: " . $row->desc_uker . " | Val: " . $row->apr . "\n";
}
