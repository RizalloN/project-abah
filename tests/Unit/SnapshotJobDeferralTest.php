<?php

namespace Tests\Unit;

use App\Jobs\RebuildDashboardHarianSnapshotJob;
use App\Jobs\RunManagedReportSnapshotRebuildJob;
use App\Services\Import\ImportProgressService;
use App\Support\DashboardHarianSnapshotDirtyPeriodQueue;
use App\Support\DashboardHarianSnapshotService;
use App\Support\ReportDataSyncService;
use App\Support\ReportSnapshotBuilder;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class SnapshotJobDeferralTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_managed_report_snapshot_rebuild_job_requeues_itself_when_import_is_active(): void
    {
        Bus::fake();

        $importProgressService = Mockery::mock(ImportProgressService::class);
        $importProgressService->shouldReceive('hasActiveProcessingJobs')
            ->once()
            ->andReturnTrue();
        $this->app->instance(ImportProgressService::class, $importProgressService);

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $builder->shouldNotReceive('describeRebuildPlan');

        $dashboardHarianSnapshotService = Mockery::mock(DashboardHarianSnapshotService::class);
        $dashboardHarianSnapshotService->shouldNotReceive('rebuild');

        $syncService = Mockery::mock(ReportDataSyncService::class);

        app()->call([
            new RunManagedReportSnapshotRebuildJob(true, 'unit-test', 'rebuild-id-123'),
            'handle',
        ], [
            'snapshotBuilder' => $builder,
            'dashboardHarianSnapshotService' => $dashboardHarianSnapshotService,
            'syncService' => $syncService,
        ]);

        Bus::assertDispatched(RunManagedReportSnapshotRebuildJob::class, 1);
    }

    public function test_dashboard_harian_snapshot_job_deferred_by_middleware_when_import_is_active(): void
    {
        $importProgressService = Mockery::mock(ImportProgressService::class);
        $importProgressService->shouldReceive('hasActiveProcessingJobs')
            ->once()
            ->andReturnTrue();
        $this->app->instance(ImportProgressService::class, $importProgressService);

        $dashboardHarianSnapshotService = Mockery::mock(DashboardHarianSnapshotService::class);
        $dashboardHarianSnapshotService->shouldNotReceive('syncDuePeriods');
        $dashboardHarianSnapshotService->shouldNotReceive('buildPeriodSnapshot');

        $dirtyQueue = Mockery::mock(DashboardHarianSnapshotDirtyPeriodQueue::class);
        $dirtyQueue->shouldNotReceive('consume');

        $job = new RebuildDashboardHarianSnapshotJob(['2026-04-01'], false, true);
        $job = Mockery::mock($job);
        $job->shouldReceive('release')
            ->once()
            ->with(Mockery::on(fn ($delay) => is_int($delay) && $delay > 0));

        $middleware = $job->middleware()[1];
        $next = fn ($j) => $j;

        $result = $middleware->handle($job, $next);

        $this->assertNull($result);
    }
}
