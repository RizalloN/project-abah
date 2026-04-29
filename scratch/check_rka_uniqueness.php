<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$uniqueUkers = DB::table('rka')->distinct()->count('desc_uker');
$uniqueMAs = DB::table('rka')->distinct()->count('mata_anggaran');
$totalRows = DB::table('rka')->count();

echo "Total Rows: $totalRows\n";
echo "Unique Ukers: $uniqueUkers\n";
echo "Unique Mata Anggaran: $uniqueMAs\n";
echo "Expected Rows (Ukers * MAs): " . ($uniqueUkers * $uniqueMAs) . "\n";
