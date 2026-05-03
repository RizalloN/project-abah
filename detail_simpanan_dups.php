<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$periode = '2026-04-30';

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  DETAILED DUPLIKAT ANALYSIS - SIMPANAN MULTIPN                 ║\n";
echo "║  Period: {$periode}                                          ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Get one example of duplicate from Madiun
$example = DB::table('simpanan_multipn')
    ->where('posisi', $periode)
    ->where('kantor_cabang', 'like', '%Madiun%')
    ->where('no_rekening', '210901000000567')
    ->where('CIFNO', 'RAVG698')
    ->orderBy('created_at')
    ->get();

if ($example->count() > 0) {
    echo "[CONTOH DUPLIKAT - MADIUN]\n";
    echo "Account: 210901000000567 | CIF: RAVG698\n";
    echo "Total records: " . $example->count() . "\n\n";

    foreach ($example as $i => $record) {
        echo "Record #" . ($i + 1) . ":\n";
        echo json_encode((array)$record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
    }
}

// Check if there's a fingerprint/import_batch_token field
$columns = DB::getSchemaBuilder()->getColumnListing('simpanan_multipn');
echo "[TABLE COLUMNS]\n";
echo json_encode($columns, JSON_PRETTY_PRINT) . "\n\n";

// Check import jobs table for simpanan multipn imports
echo "[RECENT IMPORT JOBS FOR SIMPANAN MULTIPN]\n";
$jobs = DB::table('import_jobs')
    ->where('report_id', 9)  // Assuming simpanan multipn is report ID 9
    ->orderBy('id', 'DESC')
    ->limit(10)
    ->get();

if ($jobs->count() > 0) {
    foreach ($jobs as $job) {
        echo "Job ID: {$job->id}\n";
        echo "  Status: {$job->status}\n";
        echo "  Created: {$job->created_at}\n";
        echo "  Rows processed: " . number_format($job->total_rows ?? 0) . "\n";
        echo "  File path: {$job->file_path}\n\n";
    }
} else {
    echo "No import jobs found for report ID 9\n\n";
}
