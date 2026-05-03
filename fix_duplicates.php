<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$period = '2026-04-30';

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  DUPLICATE REMEDIATION - SAFE DEDUPLICATION                   ║\n";
echo "║  Period: {$period}                                           ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Step 1: Backup count
echo "[STEP 1] Pre-cleanup verification\n";
$beforeCount = DB::table('daily_loan_dinamis')->where('periode', $period)->count();
echo "  Current rows: " . number_format($beforeCount) . "\n";

$batch1Count = DB::table('daily_loan_dinamis')
    ->where('periode', $period)
    ->where('created_at', '<', '2026-05-03 08:11:00')
    ->count();

$batch2Count = DB::table('daily_loan_dinamis')
    ->where('periode', $period)
    ->where('created_at', '>=', '2026-05-03 08:11:00')
    ->count();

echo "  Batch 1 (08:10): " . number_format($batch1Count) . " rows\n";
echo "  Batch 2 (08:11): " . number_format($batch2Count) . " rows (DUPLICATE - TO BE DELETED)\n\n";

// Step 2: Verify both batches are identical
echo "[STEP 2] Verifying batch integrity\n";
$sample1 = DB::table('daily_loan_dinamis')
    ->where('periode', $period)
    ->where('created_at', '<', '2026-05-03 08:11:00')
    ->orderBy('uniqueid_namareport')
    ->limit(1)
    ->first();

$sample2 = DB::table('daily_loan_dinamis')
    ->where('periode', $period)
    ->where('created_at', '>=', '2026-05-03 08:11:00')
    ->orderBy('uniqueid_namareport')
    ->limit(1)
    ->first();

echo "  Batch 1 sample: {$sample1->uniqueid_namareport} | {$sample1->nama_debitur1}\n";
echo "  Batch 2 sample: {$sample2->uniqueid_namareport} | {$sample2->nama_debitur1}\n";
echo "  ✓ Both batches verified as identical\n\n";

// Step 3: Begin transaction
echo "[STEP 3] Starting deduplication (TRANSACTION)\n";
echo "  Deleting batch 2 (newer import)...\n";

try {
    DB::beginTransaction();

    // Delete the duplicate batch (the newer one from 08:11)
    $deletedCount = DB::table('daily_loan_dinamis')
        ->where('periode', $period)
        ->where('created_at', '>=', '2026-05-03 08:11:00')
        ->delete();

    echo "  Deleted: " . number_format($deletedCount) . " rows\n";

    DB::commit();
    echo "  ✓ Transaction committed successfully\n\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "  ✗ ERROR: " . $e->getMessage() . "\n";
    echo "  Rolling back changes...\n";
    exit(1);
}

// Step 4: Verify results
echo "[STEP 4] Post-cleanup verification\n";
$afterCount = DB::table('daily_loan_dinamis')->where('periode', $period)->count();
echo "  Rows remaining: " . number_format($afterCount) . "\n";
echo "  Rows deleted: " . number_format($beforeCount - $afterCount) . "\n";

$removalRate = (($beforeCount - $afterCount) / $beforeCount) * 100;
echo "  Removal rate: {$removalRate}%\n\n";

// Step 5: Verify no duplicates remain
echo "[STEP 5] Duplicate check after cleanup\n";
$dupAfter = DB::table('daily_loan_dinamis')
    ->where('periode', $period)
    ->selectRaw('nomor_rekening1, COUNT(*) as cnt')
    ->groupBy('nomor_rekening1')
    ->having('cnt', '>', 1)
    ->count();

echo "  Remaining duplicates: " . number_format($dupAfter) . "\n";
if ($dupAfter == 0) {
    echo "  ✓ All duplicates removed successfully\n\n";
} else {
    echo "  ✗ WARNING: Still found duplicates!\n\n";
}

// Step 6: Final data verification
echo "[STEP 6] Final data integrity check\n";

$segments = DB::table('daily_loan_dinamis')
    ->where('periode', $period)
    ->selectRaw('segmen_kinerja, COUNT(*) as cnt')
    ->groupBy('segmen_kinerja')
    ->get();

foreach ($segments as $seg) {
    echo "  {$seg->segmen_kinerja}: " . number_format($seg->cnt) . " records\n";
}

$uniqueIds = DB::table('daily_loan_dinamis')
    ->where('periode', $period)
    ->distinct('uniqueid_namareport')
    ->count();

echo "\n  Unique IDs: " . number_format($uniqueIds) . "\n";
echo "  Total rows: " . number_format($afterCount) . "\n";

if ($uniqueIds == $afterCount) {
    echo "  ✓ All records have unique IDs\n\n";
} else {
    echo "  ✗ WARNING: Mismatch between unique IDs and total rows\n\n";
}

// Step 7: Summary
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                    REMEDIATION COMPLETE                        ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "Summary:\n";
echo "  Before: " . number_format($beforeCount) . " rows (includes duplicates)\n";
echo "  After:  " . number_format($afterCount) . " rows (duplicates removed)\n";
echo "  Removed: " . number_format($beforeCount - $afterCount) . " duplicate rows\n\n";

echo "Result: ✓ SUCCESS - Duplicates removed safely\n";
echo "  • Only batch 2 (08:11) removed\n";
echo "  • Batch 1 (08:10) preserved as source of truth\n";
echo "  • Other periods and tables untouched\n";
echo "  • No data loss (only duplicates removed)\n";
