<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$result = [
    'snapshot_queue_paused' => app('queue')->isPaused('database', 'snapshots-parallel'),
    'pause_registry' => Cache::get('import:snapshot:paused_queues'),
    'snapshot_pool_lease' => Cache::get('queue:worker-pool:lease:' . sha1('snapshots')),
    'snapshot_auto_ensure_throttle' => Cache::get('queue_worker_auto_ensure:throttle:' . sha1('snapshots')),
    'snapshot_batches' => app(\App\Support\SnapshotBatchAggregator::class)->getActiveBatches(),
    'source_0719' => DB::table('daily_loan_dinamis')->whereDate('periode', '2026-07-19')->count(),
    'snapshot_0719' => Schema::hasTable('performance_rm_snapshots')
        ? DB::table('performance_rm_snapshots')->whereDate('periode', '2026-07-19')->count()
        : null,
    'snapshot_sums' => Schema::hasTable('performance_rm_snapshots')
        ? DB::table('performance_rm_snapshots')
            ->whereDate('periode', '2026-07-19')
            ->selectRaw('COUNT(*) cnt, COALESCE(SUM(realisasi_deb), 0) realisasi_deb, COALESCE(SUM(realisasi_os), 0) realisasi_os, COALESCE(SUM(total_deb), 0) total_deb, COALESCE(SUM(loan_os), 0) loan_os')
            ->first()
        : null,
    'jobs_by_queue' => Schema::hasTable('jobs')
        ? DB::table('jobs')
            ->selectRaw('queue, COUNT(*) cnt, MIN(created_at) oldest, SUM(reserved_at IS NOT NULL) reserved_count')
            ->groupBy('queue')
            ->get()
        : [],
    'queued_snapshot_jobs' => Schema::hasTable('jobs')
        ? DB::table('jobs')
            ->where('queue', 'snapshots-parallel')
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'queue', 'attempts', 'reserved_at', 'available_at', 'created_at', 'payload'])
            ->map(function ($row): array {
                $payload = json_decode((string) $row->payload, true);

                return [
                    'id' => $row->id,
                    'attempts' => $row->attempts,
                    'reserved_at' => $row->reserved_at,
                    'available_at' => $row->available_at,
                    'created_at' => $row->created_at,
                    'display_name' => $payload['displayName'] ?? null,
                ];
            })
        : [],
    'failed_recent' => Schema::hasTable('failed_jobs')
        ? DB::table('failed_jobs')
            ->orderByDesc('id')
            ->limit(15)
            ->get(['id', 'queue', 'failed_at', 'exception'])
            ->map(fn ($row): array => [
                'id' => $row->id,
                'queue' => $row->queue,
                'failed_at' => $row->failed_at,
                'error' => substr((string) $row->exception, 0, 500),
            ])
        : [],
    'import_job_columns' => Schema::hasTable('import_jobs') ? Schema::getColumnListing('import_jobs') : [],
    'active_imports' => Schema::hasTable('import_jobs')
        ? DB::table('import_jobs')
            ->whereIn('status', ['staging', 'processing', 'queued'])
            ->orderByDesc('id')
            ->limit(20)
            ->get()
        : [],
    'dirty_0719' => Schema::hasTable('snapshot_dirty_periods')
        ? DB::table('snapshot_dirty_periods')
            ->where('period_key', '2026-07-19')
            ->get()
        : [],
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
