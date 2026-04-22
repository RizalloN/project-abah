<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$jobId = 6;
DB::table('import_jobs')->where('id', $jobId)->update(['status' => 'terminated', 'updated_at' => now()]);
echo "Job 6 marked as terminated.\n";

$progress = Cache::get('excel_import_progress_' . $jobId);
if ($progress) {
    Cache::forget('excel_import_progress_' . $jobId);
    echo "Progress cache cleared.\n";
}
