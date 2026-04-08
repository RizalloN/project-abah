<?php

use App\Support\ReportDataSyncService;
use App\Support\ReportSnapshotBuilder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('reports:snapshot {report=all} {--period=} {--force}', function () {
    $report = (string) $this->argument('report');
    $period = $this->option('period');
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
    $period = $this->option('period');

    $allowed = ['daily_loan_dinamis', 'simpanan_multipn', 'lw325_ph', 'performance_pis_per_produk'];
    if (!in_array($table, $allowed, true)) {
        $this->error('Table tidak didukung. Pilih: ' . implode(', ', $allowed));
        return;
    }

    $startedAt = microtime(true);

    app(ReportDataSyncService::class)->syncAfterDelete(
        $table,
        $period ? (string) $period : null,
        'artisan:reports:sync-source'
    );

    $this->info("Sinkronisasi selesai untuk {$table}.");
    $this->info('Durasi: ' . number_format(microtime(true) - $startedAt, 2) . ' detik.');
})->purpose('Refresh optimizer stats + snapshots setelah perubahan/hapus data sumber');
