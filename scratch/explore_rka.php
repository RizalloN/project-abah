<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$mata_anggaran = DB::table('rka')
    ->select('mata_anggaran')
    ->distinct()
    ->get()
    ->pluck('mata_anggaran');

echo "All Mata Anggaran in RKA:\n";
foreach ($mata_anggaran as $ma) {
    echo "- $ma\n";
}

$simpanan_ma = DB::table('rka')
    ->select('mata_anggaran')
    ->where(function($query) {
        $query->where('mata_anggaran', 'like', '%Funding%')
              ->orWhere('mata_anggaran', 'like', '%Giro%')
              ->orWhere('mata_anggaran', 'like', '%Tabungan%')
              ->orWhere('mata_anggaran', 'like', '%Deposito%');
    })
    ->distinct()
    ->get()
    ->pluck('mata_anggaran');

echo "\nFiltered Simpanan Mata Anggaran:\n";
foreach ($simpanan_ma as $ma) {
    echo "- $ma\n";
}
