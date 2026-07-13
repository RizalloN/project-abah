<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    Illuminate\Http\Request::capture()
);

use Illuminate\Support\Facades\DB;

$latestPeriod = DB::table('dashboard_harian_snapshots')->max('snapshot_period');
echo "Latest period in snapshot: " . $latestPeriod . "\n";

$rows = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $latestPeriod)
    ->whereColumn('kanca_key', 'unit_key')
    ->get(['kanca_label', 'kur_kecil_os', 'kur_kecil_sml', 'kur_kecil_npl', 'kur_mikro_os', 'micro_os']);

foreach ($rows as $row) {
    echo sprintf(
        "Kanca: %s | KUR Kecil OS: %s, SML: %s, NPL: %s | KUR Mikro OS: %s | Micro OS: %s\n",
        $row->kanca_label,
        $row->kur_kecil_os,
        $row->kur_kecil_sml,
        $row->kur_kecil_npl,
        $row->kur_mikro_os,
        $row->micro_os
    );
}
