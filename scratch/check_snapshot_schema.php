<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;

$cols = Schema::getColumnListing('dashboard_harian_snapshots');
echo "Columns in dashboard_harian_snapshots:\n";
foreach ($cols as $col) {
    echo "- $col\n";
}
