<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "📊 Checking job queue status...\n\n";

$jobs = DB::table('jobs')
    ->select('id', 'queue', 'attempts', 'reserved_at', 'created_at')
    ->orderBy('id', 'desc')
    ->limit(5)
    ->get();

if ($jobs->count() > 0) {
    echo "✅ Found " . $jobs->count() . " jobs in queue:\n\n";
    foreach ($jobs as $job) {
        $status = $job->reserved_at ? '🔄 Processing' : '⏳ Pending';
        echo "  [$status] ID: {$job->id}, Queue: {$job->queue}, Attempts: {$job->attempts}\n";
        echo "       Created: {$job->created_at}\n";
    }
} else {
    echo "✅ No jobs in queue (already processed)\n";
}

echo "\n";

$failed = DB::table('failed_jobs')
    ->select('id', 'queue', 'failed_at')
    ->orderBy('failed_at', 'desc')
    ->limit(3)
    ->get();

if ($failed->count() > 0) {
    echo "⚠️  Failed jobs:\n";
    foreach ($failed as $job) {
        echo "  - ID: {$job->id}, Queue: {$job->queue}, Failed: {$job->failed_at}\n";
    }
}
