<?php

// Bootstrapping Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('ssa_pinjaman')
    ->where('month_day_year_of_periode', '2026-05-20')
    ->where('nama_cabang', 'like', '%Madiun%')
    ->selectRaw("segmen_dashboard, produk_dashboard, produk, count(*), sum(baki_debet) as sum_baki")
    ->groupBy('segmen_dashboard', 'produk_dashboard', 'produk')
    ->get();

echo "=== ALL PRODUCTS FOR KC MADIUN IN ssa_pinjaman (2026-05-20) ===\n";
foreach ($rows as $r) {
    echo "Segmen: {$r->segmen_dashboard} | ProdDash: {$r->produk_dashboard} | Prod: {$r->produk} | Count: {$r->{'count(*)'}} | Baki: " . number_format($r->sum_baki, 2, ',', '.') . "\n";
}

