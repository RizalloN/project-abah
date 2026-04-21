<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "📊 Final Queue Status Check\n\n";

$jobs = DB::table('jobs')->count();
$failedJobs = DB::table('failed_jobs')->count();

echo "✅ Jobs in queue: $jobs\n";
echo "✅ Failed jobs: $failedJobs\n";

$recentSnapshots = DB::table('dashboard_harian_snapshots')
    ->select('snapshot_period')
    ->distinct()
    ->orderBy('snapshot_period', 'desc')
    ->limit(5)
    ->pluck('snapshot_period');

echo "\n✅ Latest snapshots:\n";
foreach ($recentSnapshots as $p) {
    echo "   - $p\n";
}

echo "\n✅ Snapshot for 2026-04-20:\n";
$snap20 = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', '2026-04-20')
    ->count();
echo "   Rows: $snap20\n";
