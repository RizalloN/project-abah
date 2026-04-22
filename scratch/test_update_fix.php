<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    // Try to update a dummy job or just check the SQL execution
    DB::table('import_jobs')->where('id', 1)->update([
        'status' => 'completed',
        'updated_at' => now(),
    ]);
    echo "Update successful (columns are correct).\n";
} catch (\Throwable $e) {
    echo "Update failed: " . $e->getMessage() . "\n";
}
