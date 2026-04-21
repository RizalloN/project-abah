<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "📊 Checking all queues in detail...\n\n";

$allJobs = DB::table('jobs')
    ->select('id', 'queue', 'attempts', 'reserved_at', 'available_at', 'created_at')
    ->orderBy('id', 'desc')
    ->get();

if ($allJobs->count() > 0) {
    echo "✅ Total jobs: " . $allJobs->count() . "\n\n";
    
    $byQueue = $allJobs->groupBy('queue');
    foreach ($byQueue as $queueName => $queueJobs) {
        echo "📍 Queue: '$queueName' ({$queueJobs->count()} jobs)\n";
        foreach ($queueJobs as $job) {
            $status = $job->reserved_at ? '🔄 Processing' : '⏳ Pending';
            echo "  [$status] ID: {$job->id}, Attempts: {$job->attempts}\n";
        }
        echo "\n";
    }
} else {
    echo "✅ No jobs in queue\n";
}

echo "\n--- Log Check ---\n";
$logFile = 'storage/logs/laravel.log';
if (file_exists($logFile)) {
    $lines = array_slice(file($logFile), -20);
    foreach ($lines as $line) {
        if (strpos($line, 'RebuildDashboard') !== false || strpos($line, 'Processing') !== false) {
            echo $line;
        }
    }
}
