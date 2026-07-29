<?php

use App\Jobs\RebuildSnapshotPerformanceRmBatch;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$action = $argv[1] ?? 'status';
$period = $argv[2] ?? '2026-07-19';

if ($action === 'dispatch') {
    RebuildSnapshotPerformanceRmBatch::dispatch($period)->onQueue('snapshots-priority');
}

if ($action === 'dispatch-dashboard') {
    \App\Jobs\RebuildLoanDashboardSnapshotJob::dispatch($period)->onQueue('snapshots-priority');
}

if ($action === 'release') {
    DB::table('jobs')
        ->where('queue', 'snapshots-priority')
        ->whereNotNull('reserved_at')
        ->update([
            'reserved_at' => null,
            'available_at' => time(),
        ]);
}

if ($action === 'unlock') {
    $job = new RebuildSnapshotPerformanceRmBatch($period);
    foreach ($job->middleware() as $middleware) {
        if ($middleware instanceof \Illuminate\Queue\Middleware\WithoutOverlapping) {
            Cache::lock($middleware->getLockKey($job))->forceRelease();
        }
    }
    DB::table('jobs')
        ->where('queue', 'snapshots-priority')
        ->update([
            'reserved_at' => null,
            'available_at' => time(),
        ]);
}

if ($action === 'launch-test') {
    $command = new \App\Console\Commands\EnsureQueueWorkerRunning();
    $method = new ReflectionMethod($command, 'startQueueWorker');
    $launchResult = $method->invoke($command, 'launcher-test', 'snapshots-priority', '1200', '512', 1, 3, 1);
}

echo json_encode([
    'period' => $period,
    'action' => $action,
    'launch_result' => $launchResult ?? null,
    'priority_jobs' => DB::table('jobs')->where('queue', 'snapshots-priority')->count(),
    'priority_job_state' => DB::table('jobs')->where('queue', 'snapshots-priority')->get(['id', 'attempts', 'reserved_at', 'available_at', 'created_at']),
    'priority_reserved_jobs' => DB::table('jobs')->where('queue', 'snapshots-priority')->whereNotNull('reserved_at')->count(),
    'priority_paused' => app('queue')->isPaused('database', 'snapshots-priority'),
    'priority_heartbeats' => Cache::get('queue:worker-pool:heartbeats:' . sha1('snapshots-priority')),
    'priority_pids' => Cache::get('queue:worker-pool:pids:' . sha1('snapshots-priority')),
    'snapshot_rows' => DB::table('performance_rm_snapshots')->whereDate('periode', $period)->count(),
    'snapshot_sums' => DB::table('performance_rm_snapshots')
        ->whereDate('periode', $period)
        ->selectRaw('COALESCE(SUM(realisasi_deb), 0) realisasi_deb, COALESCE(SUM(realisasi_os), 0) realisasi_os, COALESCE(SUM(total_deb), 0) total_deb, COALESCE(SUM(loan_os), 0) loan_os')
        ->first(),
    'segments' => DB::table('performance_rm_snapshots')
        ->whereDate('periode', $period)
        ->selectRaw('segmen, COUNT(*) rows_count, COALESCE(SUM(realisasi_deb), 0) realisasi_deb, COALESCE(SUM(realisasi_os), 0) realisasi_os, COALESCE(SUM(total_deb), 0) total_deb, COALESCE(SUM(loan_os), 0) loan_os')
        ->groupBy('segmen')
        ->orderBy('segmen')
        ->get(),
], JSON_PRETTY_PRINT), PHP_EOL;
