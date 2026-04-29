<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('rka')
    ->whereYear('created_at', 2026)
    ->where(function($q) {
        $q->where('kanca', 'like', '%MADIUN%')
          ->orWhere('desc_uker', 'like', '%MADIUN%');
    })
    ->where('desc_uker', 'not like', '%UNIT%')
    ->where('desc_uker', 'not like', '%KC%')
    ->where('desc_uker', 'not like', '%KCP%')
    ->select('desc_uker')
    ->distinct()
    ->get();

echo "Units in Madiun WITHOUT UNIT, KC, or KCP in name:\n";
foreach ($rows as $row) {
    echo "- " . $row->desc_uker . "\n";
}
