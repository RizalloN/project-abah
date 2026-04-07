<?php

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
