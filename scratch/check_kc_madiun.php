<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$year = 2026;
$monthCol = 'apr';

$rows = DB::table('rka')
    ->whereYear('created_at', $year)
    ->where('desc_uker', 'like', '%KC MADIUN%')
    ->get();

echo "Rows for 'KC MADIUN':\n";
foreach ($rows as $row) {
    echo "MA: " . $row->mata_anggaran . " | Val: " . $row->{$monthCol} . "\n";
}
