<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

foreach (['2026-05-14', '2026-04-30'] as $p) {
    $r = DB::table('daily_loan_dinamis')
        ->where('periode', $p)
        ->whereIn('cabang1', ['KC Madiun','KC Magetan','KC Ngawi','KC Ponorogo'])
        ->selectRaw('MIN(updated_at) as min_u, MAX(updated_at) as max_u, MIN(created_at) as min_c, MAX(created_at) as max_c, COUNT(*) as n')
        ->first();
    echo "Periode $p:\n";
    echo "  n=$r->n  created_at: $r->min_c .. $r->max_c\n";
    echo "                  updated_at: $r->min_u .. $r->max_u\n";
}

// Last import date for daily_loan_dinamis (via report metadata if exists)
echo PHP_EOL . "Schema tables for source signature:" . PHP_EOL;
$tables = DB::select("SHOW TABLES LIKE '%source%signature%'");
foreach ($tables as $t) {
    $name = array_values((array)$t)[0];
    echo "  $name\n";
    $sample = DB::table($name)->limit(5)->get();
    foreach ($sample as $s) {
        echo "    " . json_encode($s) . PHP_EOL;
    }
}
