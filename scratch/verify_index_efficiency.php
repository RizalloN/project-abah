<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$periode = DB::table('daily_loan_dinamis')->max('periode');

if (!$periode) {
    echo "Tidak ada data di daily_loan_dinamis untuk diuji.\n";
    exit;
}

echo "Testing Query EXPLAIN untuk Dashboard (Periode: $periode)...\n";
echo str_repeat("-", 80) . "\n";

$query = "EXPLAIN SELECT 
            cabang1, 
            SUM(baki_debet1) as total_os, 
            SUM(plafon) as total_plafon 
          FROM daily_loan_dinamis 
          WHERE periode = '$periode' 
          GROUP BY cabang1";

$results = DB::select($query);

foreach ($results as $row) {
    foreach ($row as $key => $val) {
        echo "$key: $val | ";
    }
    echo "\n";
}

echo str_repeat("-", 80) . "\n";
if (str_contains($results[0]->Extra ?? '', 'Using index')) {
    echo "✅ SUCCESS: Query menggunakan INDEX-ONLY SCAN (Sangat Cepat).\n";
} else {
    echo "⚠️ WARNING: Query masih menyentuh tabel fisik. Perlu audit indeks lagi.\n";
}
