<?php

use App\Services\JobHealthService;
use App\Support\ManagedReportDeleteRecoveryService;
use App\Support\ReportDataSyncService;
use App\Support\ReportSnapshotBuilder;
use App\Support\DashboardHarianSnapshotService;
use App\Support\SnapshotBatchAggregator;
use App\Support\StrictDateParser;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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

    $allowed = ['daily_loan_dinamis', 'loan_type', 'simpanan_multipn', 'lw325_ph', 'performance_pis_per_produk', 'performance_kurkecil_mikro'];
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
    ->withoutOverlapping();

Artisan::command('reports:dashboard-harian-sync-missing', function () {
    $flushed = app(SnapshotBatchAggregator::class)->flushDueBatches();
    $result = app(DashboardHarianSnapshotService::class)->syncDuePeriods();

    $this->line(json_encode([
        'flushed_batches' => count($flushed),
        'snapshot_sync' => $result,
    ], JSON_UNESCAPED_SLASHES));
})->purpose('Flush pending snapshot batches and build missing/stale Dashboard Harian SSA snapshots');

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
