<?php

namespace Tests\Unit;

use App\Jobs\ExecuteBatchedSnapshotJob;
use App\Jobs\ProcessSnapshotDirtyPeriodJob;
use App\Jobs\RebuildLoanChartPeriodikSnapshotJob;
use App\Jobs\RebuildLoanDashboardSnapshotJob;
use App\Jobs\RebuildSnapshotHarianBatch;
use App\Jobs\RebuildSnapshotPerformanceRmBatch;
use App\Jobs\RebuildSnapshotRasioBatch;
use App\Jobs\RunManagedReportSnapshotRebuildJob;
use App\Jobs\SmartPartialSnapshotRebuildJob;
use App\Jobs\SyncImportedReportJob;
use Tests\TestCase;

class SnapshotWorkerResilienceTest extends TestCase
{
    public function test_snapshot_control_jobs_survive_import_deferrals(): void
    {
        $this->assertGreaterThan(30, (new SyncImportedReportJob)->tries);
        $this->assertGreaterThan(30, (new ExecuteBatchedSnapshotJob('test'))->tries);

        foreach ([
            new ProcessSnapshotDirtyPeriodJob([]),
            new RebuildSnapshotPerformanceRmBatch('2026-07-19'),
            new RebuildSnapshotHarianBatch('2026-07-19'),
            new RebuildSnapshotRasioBatch('2026-07-19'),
            new RebuildLoanDashboardSnapshotJob('2026-07-19'),
            new RebuildLoanChartPeriodikSnapshotJob('2026-07-19'),
            new SmartPartialSnapshotRebuildJob('daily_loan_dinamis', ['2026-07-19']),
            new RunManagedReportSnapshotRebuildJob,
        ] as $job) {
            $this->assertGreaterThan(30, $job->tries, $job::class.' must survive queue deferrals');
        }
    }

    public function test_import_snapshot_flow_dispatches_performance_rm_to_dedicated_priority_queue(): void
    {
        $source = file_get_contents(app_path('Support/ParallelSnapshotBatchCoordinator.php'));
        $start = strpos($source, 'public static function dispatchDailyLoanParallelRebuild');
        $dailyLoanMethod = substr($source, $start, 3000);

        $this->assertStringContainsString("Bus::dispatch(\$performanceJob->onQueue('snapshots-priority'))", $dailyLoanMethod);
        $this->assertStringContainsString("array_unshift(\$jobs, \$performanceJob->onQueue('snapshots-parallel'))", $dailyLoanMethod);

        $job = new RebuildSnapshotPerformanceRmBatch('2026-07-19');
        $this->assertSame('snapshots-priority', $job->queue);
    }

    public function test_period_jobs_do_not_call_private_snapshot_builder_methods(): void
    {
        foreach ([
            'RebuildChartPeriodikPeriodJob.php',
            'RebuildDashboardPeriodJob.php',
            'RebuildDormantPeriodJob.php',
            'RebuildRasioPeriodJob.php',
            'RebuildSimpananPeriodJob.php',
        ] as $file) {
            $source = file_get_contents(app_path('Jobs/'.$file));
            $this->assertStringNotContainsString('->buildChartPeriodikPeriodSnapshot(', $source);
            $this->assertStringNotContainsString('->buildDashboardPeriodSnapshot(', $source);
            $this->assertStringNotContainsString('->buildDormantPeriodSnapshot(', $source);
            $this->assertStringNotContainsString('->buildRasioPeriodSnapshot(', $source);
            $this->assertStringNotContainsString('->buildRasioUkerPeriodSnapshot(', $source);
            $this->assertStringNotContainsString('->buildDashboardSimpananPeriodSnapshot(', $source);
        }
    }
}
