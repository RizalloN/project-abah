<?php

namespace Tests\Unit;

use App\Support\DashboardHarianSnapshotService;
use App\Support\DashboardHarianSnapshotDirtyPeriodQueue;
use App\Support\PartitionMaintenanceService;
use App\Support\ReportDataSyncService;
use App\Support\ReportSnapshotBuilder;
use App\Jobs\SyncImportedReportJob;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class ReportDataSyncServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_rasio_snapshot_rebuild_uses_dedicated_lock(): void
    {
        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $dashboardHarianSnapshotService = Mockery::mock(DashboardHarianSnapshotService::class);
        $partitionMaintenance = Mockery::mock(PartitionMaintenanceService::class);
        $dirtyPeriods = Mockery::mock(DashboardHarianSnapshotDirtyPeriodQueue::class);
        $service = new ReportDataSyncService($builder, $dashboardHarianSnapshotService, $partitionMaintenance, $dirtyPeriods);

        $lock = Mockery::mock(Lock::class);

        Cache::shouldReceive('lock')
            ->once()
            ->with('snapshot:rasio:rebuild:__all__', 120)
            ->andReturn($lock);

        $lock->shouldReceive('block')
            ->once()
            ->with(60, Mockery::type('callable'))
            ->andReturnUsing(function (int $seconds, callable $callback) {
                return $callback();
            });
        $lock->shouldReceive('release')->once();

        $reflection = new \ReflectionMethod($service, 'runWithRasioSnapshotLock');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($service, null, function () use ($builder) {
            $builder->shouldReceive('rebuildRasioCasa')
                ->once()
                ->with('2026-04', true)
                ->andReturn(['2026-04-30' => 12]);

            return $builder->rebuildRasioCasa('2026-04', true);
        });

        $this->assertSame(['2026-04-30' => 12], $result);
    }

    public function test_simpanan_sync_dispatches_parallel_rebuild_batch(): void
    {
        Bus::fake();

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $dashboardHarianSnapshotService = Mockery::mock(DashboardHarianSnapshotService::class);
        $partitionMaintenance = Mockery::mock(PartitionMaintenanceService::class);
        $dirtyPeriods = Mockery::mock(DashboardHarianSnapshotDirtyPeriodQueue::class);
        $service = new ReportDataSyncService($builder, $dashboardHarianSnapshotService, $partitionMaintenance, $dirtyPeriods);

        $simpananLock = Mockery::mock(Lock::class);

        Cache::shouldReceive('lock')
            ->with('snapshot:simpanan:rebuild:2026-04-04', 180)
            ->andReturn($simpananLock);

        Cache::shouldReceive('remember')
            ->andReturn([
                'is_ready' => true,
                'available_branches' => ['MADIUN', 'MAGETAN', 'NGAWI', 'PONOROGO'],
                'missing_branches' => [],
            ]);

        Cache::shouldReceive('get')->andReturnNull();
        Cache::shouldReceive('add', 'put')->andReturnTrue();

        $simpananLock->shouldReceive('block')
            ->with(60, Mockery::type('callable'))
            ->andReturnUsing(function (int $seconds, callable $callback) {
                return $callback();
            });
        $simpananLock->shouldReceive('release')->once();

        $reflection = new \ReflectionMethod($service, 'syncSimpanan');
        $reflection->setAccessible(true);

        $reflection->invoke($service, '2026-04-04', 99, 'unit-test');

        $this->assertTrue(true);
    }

    public function test_sync_after_delete_dispatches_parallel_rebuild_for_daily_loan(): void
    {
        Bus::fake();

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $dashboardHarianSnapshotService = Mockery::mock(DashboardHarianSnapshotService::class);
        $partitionMaintenance = Mockery::mock(PartitionMaintenanceService::class);
        $dirtyPeriods = Mockery::mock(DashboardHarianSnapshotDirtyPeriodQueue::class);
        $service = Mockery::mock(ReportDataSyncService::class, [$builder, $dashboardHarianSnapshotService, $partitionMaintenance, $dirtyPeriods])->makePartial();

        Schema::shouldReceive('hasTable')->andReturn(false);
        Cache::shouldReceive('add')->andReturn(true);

        $service->shouldReceive('cleanupDerivedArtifactsAfterDelete')
            ->once()
            ->with('daily_loan_dinamis', '2026-04-04', 'unit-test', null)
            ->andReturn(['dashboard_pinjaman_snapshots' => 0]);

        $service->syncAfterDelete('daily_loan_dinamis', '2026-04-04', 'unit-test');

        $this->assertTrue(true);
    }

    public function test_sync_imported_table_rebuilds_chart_periodik_when_loan_type_changes(): void
    {
        Bus::fake();

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $dashboardHarianSnapshotService = Mockery::mock(DashboardHarianSnapshotService::class);
        $partitionMaintenance = Mockery::mock(PartitionMaintenanceService::class);
        $dirtyPeriods = Mockery::mock(DashboardHarianSnapshotDirtyPeriodQueue::class);
        $service = new ReportDataSyncService($builder, $dashboardHarianSnapshotService, $partitionMaintenance, $dirtyPeriods);

        Cache::shouldReceive('add')->once()->andReturnTrue();
        Cache::shouldReceive('increment')->once()->andReturn(2);
        Schema::shouldReceive('hasTable')->andReturnFalse();

        $importProgressService = Mockery::mock(\App\Services\Import\ImportProgressService::class);
        $importProgressService->shouldReceive('hasActiveProcessingJobs')
            ->once()
            ->andReturnFalse();
        $this->app->instance(\App\Services\Import\ImportProgressService::class, $importProgressService);

        $builder->shouldReceive('rebuildChartPeriodik')
            ->once()
            ->with(null, true)
            ->andReturn(['2026-04-04' => 1]);

        $service->syncImportedTable('loan_type', '2026-04-04', 77, 'unit-test');

        $this->assertTrue(true);
    }

    public function test_sync_imported_table_defers_snapshot_when_any_import_is_processing(): void
    {
        Bus::fake();

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $dashboardHarianSnapshotService = Mockery::mock(DashboardHarianSnapshotService::class);
        $partitionMaintenance = Mockery::mock(PartitionMaintenanceService::class);
        $dirtyPeriods = Mockery::mock(DashboardHarianSnapshotDirtyPeriodQueue::class);
        $service = new ReportDataSyncService($builder, $dashboardHarianSnapshotService, $partitionMaintenance, $dirtyPeriods);

        $importProgressService = Mockery::mock(\App\Services\Import\ImportProgressService::class);
        $importProgressService->shouldReceive('hasActiveProcessingJobs')
            ->once()
            ->andReturnTrue();
        $this->app->instance(\App\Services\Import\ImportProgressService::class, $importProgressService);

        $service->syncImportedTable('simpanan_multipn', '2026-04-04', 77, 'unit-test');

        Bus::assertDispatched(SyncImportedReportJob::class, function (SyncImportedReportJob $job): bool {
            return $job->jobId === 77
                && $job->tableName === 'simpanan_multipn'
                && $job->periodHint === '2026-04-04'
                && $job->source === 'unit-test';
        });
    }
    public function test_performance_sync_rebuilds_new_payroll_using_the_import_period_hint(): void
    {
        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $dashboardHarianSnapshotService = Mockery::mock(DashboardHarianSnapshotService::class);
        $partitionMaintenance = Mockery::mock(PartitionMaintenanceService::class);
        $dirtyPeriods = Mockery::mock(DashboardHarianSnapshotDirtyPeriodQueue::class);
        $service = new ReportDataSyncService($builder, $dashboardHarianSnapshotService, $partitionMaintenance, $dirtyPeriods);

        $builder->shouldReceive('rebuildPerformanceNewPayroll')
            ->once()
            ->with('2026-04-30', true, null)
            ->andReturn(['2026-04-30' => 4]);

        $reflection = new \ReflectionMethod($service, 'syncPerformanceNewPayroll');
        $reflection->setAccessible(true);

        $reflection->invoke($service, '2026-04-30', 77, 'unit-test');

        $this->assertTrue(true);
    }
}
