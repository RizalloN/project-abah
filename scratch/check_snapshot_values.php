<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$snapshots = DB::table('dashboard_harian_snapshots')
    ->where('kanca_label', 'like', '%Madiun%')
    ->where('unit_label', 'like', '%Madiun%') // Branch summary row usually has same kanca/unit label
    ->where('snapshot_period', '2026-04-26')
    ->get();

echo "Snapshots for Madiun (2026-04-26):\n";
foreach ($snapshots as $s) {
    echo "Kanca: {$s->kanca_label} | Unit: {$s->unit_label}\n";
    echo "- total_simpanan: " . number_format($s->total_simpanan, 2) . "\n";
    echo "- simpanan_ritel: " . number_format($s->simpanan_ritel, 2) . "\n";
    echo "- simpanan_mikro: " . number_format($s->simpanan_mikro, 2) . "\n";
    echo "- simpanan_wholesale: " . number_format($s->simpanan_wholesale, 2) . "\n";
}
