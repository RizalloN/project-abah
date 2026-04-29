<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$s = DB::table('dashboard_harian_snapshots')
    ->where('kanca_label', 'KC Madiun')
    ->where('unit_label', 'KC Madiun')
    ->where('snapshot_period', '2026-04-26')
    ->first();

if ($s) {
    echo "Branch Summary for KC Madiun (2026-04-26):\n";
    echo "- total_simpanan: " . number_format($s->total_simpanan, 2) . "\n";
    echo "- simpanan_ritel: " . number_format($s->simpanan_ritel, 2) . "\n";
    echo "- simpanan_mikro: " . number_format($s->simpanan_mikro, 2) . "\n";
    echo "- simpanan_wholesale: " . number_format($s->simpanan_wholesale, 2) . "\n";
} else {
    echo "No branch summary found for KC Madiun.\n";
}
