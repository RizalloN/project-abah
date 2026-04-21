<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

require_once __DIR__ . '/bootstrap/app.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Checking import_jobs table columns ===\n";
$columns = Schema::getColumnListing('import_jobs');
print_r($columns);

echo "\n\n=== LW325_PH Import Jobs (Last 24 hours) ===\n";
$jobs = DB::table('import_jobs')
    ->where('created_at', '>=', now()->subDays(1))
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

if ($jobs->isEmpty()) {
    echo "No import jobs found in last 24 hours\n";
} else {
    foreach ($jobs as $job) {
        echo "\nJob ID: {$job->id}\n";
        echo "Status: {$job->status}\n";
        echo "Message: {$job->message}\n";
        echo "Created: {$job->created_at}\n";
        echo "Completed: {$job->completed_at}\n";
        echo "---\n";
    }
}

echo "\n\n=== Dashboard Harian Snapshot Status ===\n";
$snapshots = DB::table('dashboard_harian_snapshots')
    ->distinct('snapshot_period')
    ->orderBy('snapshot_period', 'desc')
    ->limit(5)
    ->pluck('snapshot_period');

echo "Latest snapshot periods:\n";
foreach ($snapshots as $period) {
    $count = DB::table('dashboard_harian_snapshots')
        ->where('snapshot_period', $period)
        ->count();
    echo "  $period: $count rows\n";
}

echo "\n\n=== Queue Status ===\n";
$queue = DB::table('jobs')->count();
$failed = DB::table('failed_jobs')->count();
echo "Pending jobs: $queue\n";
echo "Failed jobs: $failed\n";
