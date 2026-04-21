<?php
include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;

$cols = Schema::getColumnListing('ssa_pinjaman');
echo 'SSA_PINJAMAN COLUMNS: ' . count($cols) . PHP_EOL . PHP_EOL;
foreach ($cols as $c) {
    echo '  • ' . $c . PHP_EOL;
}
?>
