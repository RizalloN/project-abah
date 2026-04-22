<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Jobs\EnsureDashboardSimpananSnapshotJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

function checkJobs($label) {
    $jobs = DB::table('jobs')
        ->where('payload', 'like', '%EnsureDashboardSimpananSnapshotJob%')
        ->get(['id', 'payload']);
    echo "[$label] Count in 'jobs' table: " . $jobs->count() . "\n";
    foreach ($jobs as $j) {
        $p = json_decode($j->payload, true);
        $command = $p['data']['command'] ?? '';
        if (preg_match('/s:10:"(\d{4}-\d{2}-\d{2})"/', $command, $matches)) {
            echo "  - Job ID " . $j->id . " for period " . $matches[1] . "\n";
        }
    }
}

// 1. Clear existing jobs for this class to start fresh
DB::table('jobs')->where('payload', 'like', '%EnsureDashboardSimpananSnapshotJob%')->delete();
// Also clear cache locks for this job class
// Laravel uses 'laravel_unique_job:' prefix for ShouldBeUnique locks
Cache::flush(); 

echo "Starting verification...\n";

// 2. Dispatch first job
$period = '2026-04-25';
echo "Dispatching first job for $period...\n";
EnsureDashboardSimpananSnapshotJob::dispatch($period);
checkJobs("After 1st dispatch");

// 3. Dispatch second job (same period)
echo "Dispatching second job for $period (should be ignored)...\n";
EnsureDashboardSimpananSnapshotJob::dispatch($period);
checkJobs("After 2nd dispatch (same period)");

// 4. Dispatch third job (different period)
$otherPeriod = '2026-04-26';
echo "Dispatching third job for $otherPeriod (should be allowed)...\n";
EnsureDashboardSimpananSnapshotJob::dispatch($otherPeriod);
checkJobs("After 3rd dispatch (different period)");

echo "Verification complete.\n";
