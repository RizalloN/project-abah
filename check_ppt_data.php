<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "--- RESETTING CIFNO_CLEAN TO NULL IN DATABASE ---\n";
try {
    $startTime = microtime(true);
    $affected = DB::table('daily_loan_dinamis')->update(['cifno_clean' => null]);
    $duration = microtime(true) - $startTime;
    echo "Reset {$affected} rows of cifno_clean successfully in " . round($duration, 2) . " seconds.\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
