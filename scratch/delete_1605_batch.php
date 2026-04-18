<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$posisi = '2026-02-28';
$cabang = '00045 -- KC Madiun(Konsolidasi-MB)';
$timestamp = '2026-04-17 16:05:40';
$chunkSize = 50000;

echo "Starting deletion for Madiun $posisi at $timestamp\n";

$totalDeleted = 0;
while (true) {
    try {
        $deleted = DB::table('simpanan_multipn')
            ->where('posisi', $posisi)
            ->where('kantor_cabang', $cabang)
            ->where('created_at', $timestamp)
            ->limit($chunkSize)
            ->delete();
        
        if ($deleted === 0) break;
        
        $totalDeleted += $deleted;
        echo "Deleted $deleted rows (Total: $totalDeleted)\n";
        
        // Brief sleep to yield DB resources if needed
        usleep(100000); // 100ms
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        break;
    }
}

echo "Finished. Total rows deleted: $totalDeleted\n";
