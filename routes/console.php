<?php

use App\Services\JobHealthService;
use App\Services\Network\PublicAccessHealthService;
use App\Jobs\EnsureImportedSnapshotsFreshJob;
use App\Support\ManagedReportDeleteRecoveryService;
use App\Support\ReportDataSyncService;
use App\Support\ReportSnapshotBuilder;
use App\Support\DashboardHarianSnapshotService;
use App\Support\SnapshotBatchAggregator;
use App\Support\StrictDateParser;
use App\Services\Import\SnapshotQueuePauseService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Symfony\Component\Process\Process;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('network:update-duckdns', function () {
    if (!config('services.public_access_health.enabled', false)) {
        $this->line('DuckDNS update dinonaktifkan karena akses publik memakai Cloudflare Tunnel.');

        return 0;
    }

    $lock = Cache::lock('network:duckdns-update', 120);
    if (!$lock->get()) {
        $this->warn('DuckDNS update sedang berjalan di proses lain.');

        return 0;
    }

    try {
        $scriptPath = base_path('ddns-update.bat');
        if (!is_file($scriptPath)) {
            $this->error('ddns-update.bat tidak ditemukan.');

            return 1;
        }

        $process = new Process(['cmd.exe', '/c', $scriptPath], base_path());
        $process->setTimeout(90);
        $process->run();

        $output = trim($process->getOutput() . "\n" . $process->getErrorOutput());
        $output = preg_replace('/token=[^&"\s]+/i', 'token=***', $output) ?? $output;
        $output = preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', '***', $output) ?? $output;

        if ($output !== '') {
            $this->line($output);
        }

        if (!$process->isSuccessful()) {
            $this->error('DuckDNS update gagal.');

            return $process->getExitCode() ?: 1;
        }

        return 0;
    } finally {
        $lock->release();
    }
})->purpose('Update DuckDNS public IP for asixdashboard.duckdns.org');

Artisan::command('network:public-health {--fix} {--force} {--json}', function () {
    if (!config('services.public_access_health.enabled', false)) {
        $status = [
            'healthy' => true,
            'disabled' => true,
            'message' => 'Public access health dinonaktifkan karena akses publik memakai Cloudflare Tunnel.',
        ];

        if ($this->option('json')) {
            $this->line(json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 0;
        }

        $this->line($status['message']);

        return 0;
    }

    $status = app(PublicAccessHealthService::class)->check(
        fix: (bool) $this->option('fix'),
        force: (bool) $this->option('force')
    );

    if ($this->option('json')) {
        $this->line(json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $status['healthy'] ? 0 : 1;
    }

    $this->table(
        ['Metric', 'Value'],
        [
            ['healthy', $status['healthy'] ? 'yes' : 'no'],
            ['domain', $status['domain']],
            ['public_ip', $status['public_ip'] ?? '-'],
            ['dns_ip', $status['dns_ip'] ?? '-'],
            ['ip_matches', $status['ip_matches'] ? 'yes' : 'no'],
            ['port_80', ($status['ports'][80] ?? false) ? 'open' : 'closed'],
            ['port_443', ($status['ports'][443] ?? false) ? 'open' : 'closed'],
            ['http_status', (string) ($status['http_status'] ?? '-')],
            ['fix_attempted', $status['fix_attempted'] ? 'yes' : 'no'],
            ['fix_exit_code', (string) ($status['fix_exit_code'] ?? '-')],
            ['duration_ms', (string) $status['duration_ms']],
        ]
    );

    return $status['healthy'] ? 0 : 1;
})->purpose('Check public dashboard access and repair DuckDNS when IP changes');

Artisan::command('reports:snapshot {report=all} {--period=} {--force}', function () {
    $report = (string) $this->argument('report');
    $period = StrictDateParser::normalize((string) $this->option('period')) ?? $this->option('period');
    $force = (bool) $this->option('force');

    $startedAt = microtime(true);
    $result = app(ReportSnapshotBuilder::class)->rebuild($report, $period, $force);

    foreach ($result as $group => $snapshots) {
        $this->info(strtoupper($group));

        foreach ($snapshots as $snapshotPeriod => $rowCount) {
            $this->line("  {$snapshotPeriod}: {$rowCount} baris snapshot");
        }
    }

    $this->info('Selesai dalam ' . number_format(microtime(true) - $startedAt, 2) . ' detik.');
})->purpose('Build materialized snapshots for heavy reports');

Artisan::command('reports:sync-source {table} {--period=}', function () {
    $table = strtolower(trim((string) $this->argument('table')));
    $period = StrictDateParser::normalize((string) $this->option('period')) ?? $this->option('period');

    $allowed = ['daily_loan_dinamis', 'loan_type', 'simpanan_multipn', 'ssa_simpanan', 'hourly_dpk', 'ssa_pinjaman', 'lw325_ph', 'performance_pis_per_produk'];
    if (!in_array($table, $allowed, true)) {
        $this->error('Table tidak didukung. Pilih: ' . implode(', ', $allowed));
        return;
    }

    $startedAt = microtime(true);

    $syncService = app(ReportDataSyncService::class);

    if ($table === 'loan_type') {
        $syncService->syncImportedTable(
            tableName: $table,
            periodHint: $period ? (string) $period : null,
            jobId: null,
            source: 'artisan:reports:sync-source'
        );
    } else {
        $syncService->syncAfterDelete(
            $table,
            $period ? (string) $period : null,
            'artisan:reports:sync-source'
        );
    }

    $this->info("Sinkronisasi selesai untuk {$table}.");
    $this->info('Durasi: ' . number_format(microtime(true) - $startedAt, 2) . ' detik.');
})->purpose('Refresh optimizer stats + snapshots setelah perubahan/hapus data sumber');

Artisan::command('daily-loan:audit-shifted-plafon {--period=*} {--account=*} {--limit=200}', function () {
    $periods = array_values(array_filter(array_map(
        static fn ($value) => trim((string) $value),
        (array) $this->option('period')
    )));
    $accounts = array_values(array_filter(array_map(
        static fn ($value) => trim((string) $value),
        (array) $this->option('account')
    )));
    $limit = max(1, (int) $this->option('limit'));

    $query = DB::table('daily_loan_dinamis')
        ->select([
            'periode',
            'nomor_rekening1',
            'nama_debitur1',
            'jangka_waktu1',
            'plafon',
            'baki_debet1',
            'os_idr',
        ])
        ->where(function ($builder) {
            $builder
                ->where('nama_debitur1', 'like', '%,%')
                ->orWhere(function ($numeric) {
                    $numeric
                        ->whereNotNull('plafon')
                        ->whereBetween('plafon', [1, 600])
                        ->where(function ($balance) {
                            $balance
                                ->where('baki_debet1', '>=', 1000000)
                                ->orWhere('os_idr', '>=', 1000000);
                        });
                });
        });

    if ($periods !== []) {
        $query->whereIn('periode', $periods);
    }

    if ($accounts !== []) {
        $query->whereIn('nomor_rekening1', $accounts);
    }

    $rows = $query
        ->orderByDesc('periode')
        ->orderBy('nomor_rekening1')
        ->limit($limit)
        ->get();

    if ($rows->isEmpty()) {
        $this->info('Tidak ada row mencurigakan yang cocok dengan filter.');
        return;
    }

    $tableRows = $rows->map(function ($row) {
        $flags = [];

        if (str_contains((string) ($row->nama_debitur1 ?? ''), ',')) {
            $flags[] = 'name_has_comma';
        }

        if ($row->plafon !== null && (float) $row->plafon >= 1 && (float) $row->plafon <= 600) {
            $flags[] = 'plafon_looks_like_term';
        }

        if ($row->baki_debet1 !== null && $row->os_idr !== null && abs((float) $row->baki_debet1 - (float) $row->os_idr) < 0.01) {
            $flags[] = 'baki_matches_os';
        }

        return [
            'periode' => (string) $row->periode,
            'rekening' => (string) $row->nomor_rekening1,
            'debitur' => (string) $row->nama_debitur1,
            'jangka_waktu1' => $row->jangka_waktu1,
            'plafon' => $row->plafon,
            'baki_debet1' => $row->baki_debet1,
            'os_idr' => $row->os_idr,
            'flags' => implode(',', $flags),
        ];
    })->all();

    $this->table(
        ['periode', 'rekening', 'debitur', 'jangka_waktu1', 'plafon', 'baki_debet1', 'os_idr', 'flags'],
        $tableRows
    );

    $this->info('Total row audit: ' . count($tableRows));
})->purpose('Audit row Daily Loan yang dicurigai mengalami pergeseran kolom akibat nama debitur berkoma');

Artisan::command('queue:health-sweep', function () {
    $summary = app(JobHealthService::class)->sweepNow();

    if ($summary === []) {
        $this->line('Queue health sweep tidak jalan karena lock masih aktif.');
        return;
    }

    $this->table(
        ['metric', 'value'],
        collect($summary)
            ->map(function ($value, $key) {
                if (is_array($value)) {
                    return [$key, json_encode($value, JSON_UNESCAPED_SLASHES)];
                }

                return [$key, (string) $value];
            })
            ->values()
            ->all()
    );
})->purpose('Bersihkan state queue stale dan terminasi job pending yang tidak lagi sehat');

Schedule::command('queue:health-sweep')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->runInBackground();

if (config('services.public_access_health.enabled', false)) {
    Schedule::command('network:update-duckdns')
        ->everyFiveMinutes()
        ->withoutOverlapping(10)
        ->runInBackground();

    Schedule::command('network:public-health --fix')
        ->everyMinute()
        ->withoutOverlapping(2)
        ->runInBackground();
}

Schedule::command('logs:maintenance')
    ->everyThirtyMinutes()
    ->withoutOverlapping(35)
    ->runInBackground();

Schedule::command('cache:maintenance')
    ->hourly()
    ->withoutOverlapping(120)
    ->runInBackground();

Schedule::command('optimize')
    ->dailyAt('03:00')
    ->withoutOverlapping(180)
    ->runInBackground();

Schedule::command(
    'queue:ensure-running --once --timeout=900 --memory=512'
    . ' --max-jobs=' . (int) config('queue.worker_max_jobs', 25)
    . ' --max-time=' . (int) config('queue.worker_max_time', 3600)
)
    ->everyMinute()
    ->withoutOverlapping(2)
    ->runInBackground();

Schedule::command('import:health-check --fix --hours=1')
    ->everyTenMinutes()
    ->withoutOverlapping(15)
    ->runInBackground();

Artisan::command('reports:resume-snapshot-queues-if-idle', function () {
    app(SnapshotQueuePauseService::class)->resumeWhenNoActiveImports();
    $flushed = app(SnapshotBatchAggregator::class)->flushDueBatches();

    $this->line(json_encode([
        'resumed_if_idle' => true,
        'flushed_batches' => count($flushed),
    ], JSON_UNESCAPED_SLASHES));
})->purpose('Resume paused snapshot/shadow queues and flush pending snapshot batches when imports are idle');

Schedule::command('reports:resume-snapshot-queues-if-idle')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->runInBackground();

Schedule::command('snapshot:validate-integrity --report=performance_rm')
    ->dailyAt('09:00')
    ->withoutOverlapping(120)
    ->runInBackground();

Schedule::command('snapshot:validate-integrity --report=ssa_simpanan')
    ->dailyAt('09:05')
    ->withoutOverlapping(120)
    ->runInBackground();

Schedule::command('snapshot:validate-integrity --report=dashboard_simpanan')
    ->dailyAt('09:10')
    ->withoutOverlapping(120)
    ->runInBackground();

Schedule::command('snapshot:validate-integrity --report=dashboard_harian')
    ->dailyAt('09:15')
    ->withoutOverlapping(120)
    ->runInBackground();

Schedule::command('snapshot:validate-integrity --report=dormant_account')
    ->dailyAt('09:20')
    ->withoutOverlapping(120)
    ->runInBackground();

Artisan::command('reports:dashboard-harian-sync-missing', function () {
    $flushed = app(SnapshotBatchAggregator::class)->flushDueBatches();
    $result = app(DashboardHarianSnapshotService::class)->syncDuePeriods();

    $this->line(json_encode([
        'flushed_batches' => count($flushed),
        'snapshot_sync' => $result,
    ], JSON_UNESCAPED_SLASHES));
})->purpose('Flush pending snapshot batches and build missing/stale Dashboard Harian SSA snapshots');

Artisan::command('reports:ensure-fresh-snapshots {--period=}', function () {
    $period = StrictDateParser::normalize((string) $this->option('period')) ?? trim((string) $this->option('period'));
    $period = $period !== '' ? $period : null;

    app(SnapshotBatchAggregator::class)->flushDueBatches();
    Artisan::call('reports:snapshot:drain-dirty', [
        '--max-runtime' => 1,
        '--period' => $period,
    ]);

    EnsureImportedSnapshotsFreshJob::dispatch('daily_loan_dinamis', $period, 'schedule:reports:ensure-fresh-snapshots')
        ->onQueue('snapshots-parallel');
    EnsureImportedSnapshotsFreshJob::dispatch('simpanan_multipn', $period, 'schedule:reports:ensure-fresh-snapshots')
        ->onQueue('snapshots-parallel');
    EnsureImportedSnapshotsFreshJob::dispatch('ssa_simpanan', $period, 'schedule:reports:ensure-fresh-snapshots')
        ->onQueue('snapshots-parallel');
    EnsureImportedSnapshotsFreshJob::dispatch('hourly_dpk', $period, 'schedule:reports:ensure-fresh-snapshots')
        ->onQueue('snapshots-parallel');
    EnsureImportedSnapshotsFreshJob::dispatch('ssa_pinjaman', $period, 'schedule:reports:ensure-fresh-snapshots')
        ->onQueue('snapshots-parallel');
    EnsureImportedSnapshotsFreshJob::dispatch('lw325_ph', $period, 'schedule:reports:ensure-fresh-snapshots')
        ->onQueue('snapshots-parallel');
    EnsureImportedSnapshotsFreshJob::dispatch('gi405_recovery', $period, 'schedule:reports:ensure-fresh-snapshots')
        ->onQueue('snapshots-parallel');

    $this->info('Snapshot freshness check dispatched.');
})->purpose('Dispatch self-healing checks for imported report snapshots');

Schedule::command('reports:ensure-fresh-snapshots')
    ->hourly()
    ->withoutOverlapping(10)
    ->runInBackground();

Schedule::command('reports:snapshot:drain-dirty --max-runtime=55')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->runInBackground();

Schedule::command('reports:dashboard-harian-sync-missing')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();

// Auto-discover and backfill shadow columns for daily_loan_dinamis every 15 minutes.
// Runs shadow:backfill with no --periods flag, which now auto-discovers periods that have NULL shadow columns.
// Uses --queue to dispatch to the shadow-backfill queue so it doesn't block the scheduler.
// Skips snapshot rebuild (--skip-snapshot) because the snapshot will be triggered separately by ensureDailyLoanShadowColumnsReady.
Artisan::command('shadow:auto-backfill-scheduler', function () {
    $hasActiveImports = app(\App\Services\Import\ImportProgressService::class)->hasActiveProcessingJobs();

    if ($hasActiveImports) {
        $this->line('Shadow auto-backfill skipped: import still active.');
        return 0;
    }

    $exitCode = \Illuminate\Support\Facades\Artisan::call('shadow:backfill', [
        '--queue' => true,
        '--skip-snapshot' => true,
        '--chunk-size' => 50000,
        '--retry-count' => 3,
    ]);

    $this->line('Shadow auto-backfill dispatched (exit: ' . $exitCode . ').');
    return $exitCode;
})->purpose('Auto-discover and queue shadow column backfill for daily_loan_dinamis');

Schedule::command('shadow:auto-backfill-scheduler')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30)
    ->runInBackground();

Artisan::command('reports:delete-scope {table} {--period=} {--blank-kanca} {--chunk=10000}', function () {
    $table = strtolower(trim((string) $this->argument('table')));
    $period = trim((string) $this->option('period'));
    $blankKanca = (bool) $this->option('blank-kanca');
    $chunkSize = max(1, (int) $this->option('chunk'));

    if ($table !== 'daily_loan_dinamis') {
        $this->error('Command recovery ini hanya mendukung daily_loan_dinamis.');
        return 1;
    }

    if ($period === '' || !$blankKanca) {
        $this->error('Gunakan --period=YYYY-MM-DD dan --blank-kanca untuk recovery scope blank/null.');
        return 1;
    }

    $candidateRows = (int) DB::table('daily_loan_dinamis')
        ->where('periode', $period)
        ->where(function ($query) {
            $query->whereNull('cabang1')
                ->orWhereRaw("TRIM(COALESCE(cabang1, '')) = ''");
        })
        ->count();

    $this->info("Kandidat row: " . number_format($candidateRows, 0, ',', '.'));

    $startedAt = microtime(true);
    $service = app(ManagedReportDeleteRecoveryService::class);
    $result = $service->deleteBlankKancaPeriodScope(
        'daily_loan_dinamis',
        'periode',
        'cabang1',
        $period,
        'uniqueid_namareport',
        $chunkSize,
        function (int $affectedRows, int $totalDeleted, int $batchNumber): void {
            $this->line(sprintf(
                'Batch %d: hapus %s row (total %s)',
                $batchNumber,
                number_format($affectedRows, 0, ',', '.'),
                number_format($totalDeleted, 0, ',', '.')
            ));
        }
    );

    app(ReportDataSyncService::class)->syncAfterDelete(
        'daily_loan_dinamis',
        $period,
        'artisan:reports:delete-scope'
    );

    $remainingRows = (int) DB::table('daily_loan_dinamis')
        ->where('periode', $period)
        ->where(function ($query) {
            $query->whereNull('cabang1')
                ->orWhereRaw("TRIM(COALESCE(cabang1, '')) = ''");
        })
        ->count();

    $this->info('Deleted rows: ' . number_format((int) ($result['deleted_rows'] ?? 0), 0, ',', '.'));
    $this->info('Remaining rows: ' . number_format($remainingRows, 0, ',', '.'));
    $this->info('Durasi: ' . number_format(microtime(true) - $startedAt, 2) . ' detik.');

    return 0;
})->purpose('Recovery delete untuk scope daily_loan_dinamis periode eksplisit dengan kanca blank/null');
