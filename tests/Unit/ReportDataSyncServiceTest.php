<?php

namespace Tests\Unit;

use App\Jobs\EnsureImportedSnapshotsFreshJob;
use App\Jobs\RebuildDashboardHarianSnapshotJob;
use App\Support\DashboardHarianSnapshotService;
use App\Support\DashboardHarianSnapshotDirtyPeriodQueue;
use App\Support\PartitionMaintenanceService;
use App\Support\ReportDataSyncService;
use App\Support\ReportSnapshotBuilder;
use App\Jobs\SyncImportedReportJob;
use App\Jobs\WarmReportCacheJob;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
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
        $importProgressService->shouldReceive('hasActiveProcessingJobsForTable')
            ->once()
            ->with('loan_type', 77)
            ->andReturnFalse();
        $this->app->instance(\App\Services\Import\ImportProgressService::class, $importProgressService);

        $builder->shouldReceive('rebuildChartPeriodik')
            ->once()
            ->with('2026-04-04', false)
            ->andReturn(['2026-04-04' => 1]);

        $service->syncImportedTable('loan_type', '2026-04-04', 77, 'unit-test');

        $this->assertTrue(true);
    }

    public function test_sync_imported_table_defers_snapshot_when_same_table_import_is_processing(): void
    {
        Bus::fake();

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $dashboardHarianSnapshotService = Mockery::mock(DashboardHarianSnapshotService::class);
        $partitionMaintenance = Mockery::mock(PartitionMaintenanceService::class);
        $dirtyPeriods = Mockery::mock(DashboardHarianSnapshotDirtyPeriodQueue::class);
        $service = new ReportDataSyncService($builder, $dashboardHarianSnapshotService, $partitionMaintenance, $dirtyPeriods);

        $importProgressService = Mockery::mock(\App\Services\Import\ImportProgressService::class);
        $importProgressService->shouldReceive('hasActiveProcessingJobsForTable')
            ->once()
            ->with('simpanan_multipn', 77)
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

    public function test_sync_imported_table_uses_lightweight_path_for_merchant_reports(): void
    {
        Bus::fake();

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $dashboardHarianSnapshotService = Mockery::mock(DashboardHarianSnapshotService::class);
        $partitionMaintenance = Mockery::mock(PartitionMaintenanceService::class);
        $dirtyPeriods = Mockery::mock(DashboardHarianSnapshotDirtyPeriodQueue::class);
        $service = new ReportDataSyncService($builder, $dashboardHarianSnapshotService, $partitionMaintenance, $dirtyPeriods);

        $importProgressService = Mockery::mock(\App\Services\Import\ImportProgressService::class);
        $importProgressService->shouldNotReceive('hasActiveProcessingJobs');
        $this->app->instance(\App\Services\Import\ImportProgressService::class, $importProgressService);

        Cache::shouldReceive('add')->once()->andReturnTrue();
        Cache::shouldReceive('increment')->once()->andReturn(2);
        Schema::shouldReceive('hasTable')->andReturnFalse();

        $service->syncImportedTable('jumlah_merchant_qris_detail', '2026-04-04', 77, 'unit-test');

        Bus::assertNotDispatched(SyncImportedReportJob::class);
        Bus::assertNotDispatched(WarmReportCacheJob::class);
    }

    public function test_hourly_dpk_import_dispatches_freshness_and_dashboard_rebuild_when_fallback_loan_ready(): void
    {
        Bus::fake();
        Config::set('cache.default', 'array');
        Config::set('import.snapshot.enable_analyze_table', false);

        Schema::dropIfExists('dly_kap_resegmentasi');
        Schema::create('dly_kap_resegmentasi', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable();
        });
        DB::table('dly_kap_resegmentasi')->insert(['periode' => '2026-05-11']);

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $dashboardHarianSnapshotService = Mockery::mock(DashboardHarianSnapshotService::class);
        $partitionMaintenance = Mockery::mock(PartitionMaintenanceService::class);
        $dirtyPeriods = Mockery::mock(DashboardHarianSnapshotDirtyPeriodQueue::class);
        $dirtyPeriods->shouldReceive('register')
            ->once()
            ->with(['2026-05-11'])
            ->andReturnTrue();
        $dirtyPeriods->shouldReceive('debounceSeconds')
            ->twice()
            ->andReturn(0);

        $importProgressService = Mockery::mock(\App\Services\Import\ImportProgressService::class);
        $importProgressService->shouldReceive('hasActiveProcessingJobsForTable')
            ->once()
            ->with('hourly_dpk', 77)
            ->andReturnFalse();
        $this->app->instance(\App\Services\Import\ImportProgressService::class, $importProgressService);

        $service = new ReportDataSyncService($builder, $dashboardHarianSnapshotService, $partitionMaintenance, $dirtyPeriods);
        $service->syncImportedTable('hourly_dpk', '2026-05-11', 77, 'unit-test');

        Bus::assertDispatched(EnsureImportedSnapshotsFreshJob::class);
        Bus::assertDispatched(RebuildDashboardHarianSnapshotJob::class);
    }

    public function test_ssa_pinjaman_import_dispatches_harian_rebuild_when_simpanan_is_ready(): void
    {
        Bus::fake();
        Config::set('cache.default', 'array');
        Config::set('import.snapshot.enable_analyze_table', false);

        Schema::dropIfExists('ssa_pinjaman');
        Schema::dropIfExists('ssa_simpanan');
        Schema::dropIfExists('gi405_recovery');
        Schema::create('ssa_pinjaman', function (Blueprint $table): void {
            $table->id();
            $table->date('month_day_year_of_periode')->nullable();
        });
        Schema::create('ssa_simpanan', function (Blueprint $table): void {
            $table->id();
            $table->date('Month_Day_Year_of_Posisi')->nullable();
        });
        Schema::create('gi405_recovery', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable();
        });

        DB::table('ssa_pinjaman')->insert(['month_day_year_of_periode' => '2026-05-31']);
        DB::table('ssa_simpanan')->insert(['Month_Day_Year_of_Posisi' => '2026-05-31']);
        DB::table('gi405_recovery')->insert(['periode' => '2026-05-31']);

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $dashboardHarianSnapshotService = Mockery::mock(DashboardHarianSnapshotService::class);
        $partitionMaintenance = Mockery::mock(PartitionMaintenanceService::class);
        $dirtyPeriods = Mockery::mock(DashboardHarianSnapshotDirtyPeriodQueue::class);
        $dirtyPeriods->shouldReceive('register')
            ->once()
            ->with(['2026-05-31'])
            ->andReturnTrue();
        $dirtyPeriods->shouldReceive('debounceSeconds')
            ->twice()
            ->andReturn(0);

        $importProgressService = Mockery::mock(\App\Services\Import\ImportProgressService::class);
        $importProgressService->shouldReceive('hasActiveProcessingJobsForTable')
            ->once()
            ->with('ssa_pinjaman', 77)
            ->andReturnFalse();
        $this->app->instance(\App\Services\Import\ImportProgressService::class, $importProgressService);

        $service = new ReportDataSyncService($builder, $dashboardHarianSnapshotService, $partitionMaintenance, $dirtyPeriods);
        $service->syncImportedTable('ssa_pinjaman', '2026-05-31', 77, 'unit-test');

        Bus::assertDispatched(EnsureImportedSnapshotsFreshJob::class);
        Bus::assertDispatched(RebuildDashboardHarianSnapshotJob::class);
    }

    public function test_hourly_dpk_import_waits_for_fallback_loan_before_dashboard_rebuild(): void
    {
        Bus::fake();
        Config::set('cache.default', 'array');
        Config::set('import.snapshot.enable_analyze_table', false);

        Schema::dropIfExists('dly_kap_resegmentasi');
        Schema::dropIfExists('l1133');

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $dashboardHarianSnapshotService = Mockery::mock(DashboardHarianSnapshotService::class);
        $partitionMaintenance = Mockery::mock(PartitionMaintenanceService::class);
        $dirtyPeriods = Mockery::mock(DashboardHarianSnapshotDirtyPeriodQueue::class);
        $dirtyPeriods->shouldNotReceive('register');

        $importProgressService = Mockery::mock(\App\Services\Import\ImportProgressService::class);
        $importProgressService->shouldReceive('hasActiveProcessingJobsForTable')
            ->once()
            ->with('hourly_dpk', 77)
            ->andReturnFalse();
        $this->app->instance(\App\Services\Import\ImportProgressService::class, $importProgressService);

        $service = new ReportDataSyncService($builder, $dashboardHarianSnapshotService, $partitionMaintenance, $dirtyPeriods);
        $service->syncImportedTable('hourly_dpk', '2026-05-11', 77, 'unit-test');

        Bus::assertDispatched(EnsureImportedSnapshotsFreshJob::class);
        Bus::assertNotDispatched(RebuildDashboardHarianSnapshotJob::class);
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
            ->with('2026-04-30', false, null)
            ->andReturn(['2026-04-30' => 4]);

        $reflection = new \ReflectionMethod($service, 'syncPerformanceNewPayroll');
        $reflection->setAccessible(true);

        $reflection->invoke($service, '2026-04-30', 77, 'unit-test');

        $this->assertTrue(true);
    }

    public function test_daily_loan_shadow_gate_fails_when_backfill_leaves_required_columns_missing(): void
    {
        Config::set('cache.default', 'array');
        Cache::flush();

        Schema::dropIfExists('daily_loan_dinamis');
        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable();
            $table->string('segmen_kinerja')->nullable();
            $table->string('produk_kinerja')->nullable();
            $table->string('cabang_normalized')->nullable();
            $table->string('unit_normalized')->nullable();
            $table->string('branch_normalized')->nullable();
            $table->string('rm_normalized')->nullable();
            $table->string('pn_pemutus_normalized')->nullable();
            $table->string('cifno_clean')->nullable();
            $table->timestamp('shadow_built_at')->nullable();
            $table->timestamps();
        });

        DB::table('daily_loan_dinamis')->insert([
            'periode' => '2026-05-10',
            'segmen_kinerja' => null,
            'produk_kinerja' => 'SMALL',
            'cabang_normalized' => 'KC MADIUN',
            'unit_normalized' => 'BRI UNIT',
            'branch_normalized' => 'KC MADIUN',
            'rm_normalized' => 'RM TEST',
            'pn_pemutus_normalized' => 'PN TEST',
            'cifno_clean' => '123',
            'shadow_built_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Artisan::shouldReceive('call')
            ->once()
            ->with('shadow:backfill', Mockery::on(function (array $args): bool {
                return ($args['--periods'] ?? null) === '2026-05-10'
                    && ($args['--skip-snapshot'] ?? null) === true;
            }))
            ->andReturn(0);

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $dashboardHarianSnapshotService = Mockery::mock(DashboardHarianSnapshotService::class);
        $partitionMaintenance = Mockery::mock(PartitionMaintenanceService::class);
        $dirtyPeriods = Mockery::mock(DashboardHarianSnapshotDirtyPeriodQueue::class);
        $service = new ReportDataSyncService($builder, $dashboardHarianSnapshotService, $partitionMaintenance, $dirtyPeriods);

        $reflection = new \ReflectionMethod($service, 'ensureDailyLoanShadowColumnsReady');
        $reflection->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('belum siap untuk snapshot');

        $reflection->invoke($service, '2026-05-10', 77, 'unit-test');
    }

    public function test_daily_loan_shadow_gate_allows_blank_pn_pemutus_source(): void
    {
        Schema::dropIfExists('daily_loan_dinamis');
        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable();
            $table->string('segmen_kinerja')->nullable();
            $table->string('produk_kinerja')->nullable();
            $table->string('cabang_normalized')->nullable();
            $table->string('unit_normalized')->nullable();
            $table->string('branch_normalized')->nullable();
            $table->string('rm_normalized')->nullable();
            $table->string('pn_pemutus1')->nullable();
            $table->string('pn_pemutus_normalized')->nullable();
            $table->string('cifno_clean')->nullable();
            $table->timestamps();
        });

        DB::table('daily_loan_dinamis')->insert([
            'periode' => '2026-05-10',
            'segmen_kinerja' => 'SMALL',
            'produk_kinerja' => 'KUPEDES',
            'cabang_normalized' => 'KC MADIUN',
            'unit_normalized' => 'BRI UNIT',
            'branch_normalized' => 'KC MADIUN',
            'rm_normalized' => 'RM TEST',
            'pn_pemutus1' => '',
            'pn_pemutus_normalized' => null,
            'cifno_clean' => '123',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Artisan::shouldReceive('call')->never();

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $dashboardHarianSnapshotService = Mockery::mock(DashboardHarianSnapshotService::class);
        $partitionMaintenance = Mockery::mock(PartitionMaintenanceService::class);
        $dirtyPeriods = Mockery::mock(DashboardHarianSnapshotDirtyPeriodQueue::class);
        $service = new ReportDataSyncService($builder, $dashboardHarianSnapshotService, $partitionMaintenance, $dirtyPeriods);

        $reflection = new \ReflectionMethod($service, 'ensureDailyLoanShadowColumnsReady');
        $reflection->setAccessible(true);

        $reflection->invoke($service, '2026-05-10', 77, 'unit-test');

        $this->assertTrue(true);
    }

    public function test_gi405_recovery_import_dispatches_harian_rebuild(): void
    {
        Bus::fake();
        Config::set('cache.default', 'array');
        Config::set('import.snapshot.enable_analyze_table', false);

        Schema::dropIfExists('gi405_recovery');
        Schema::create('gi405_recovery', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable();
        });

        DB::table('gi405_recovery')->insert(['periode' => '2026-05-21']);

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $dashboardHarianSnapshotService = Mockery::mock(DashboardHarianSnapshotService::class);
        $partitionMaintenance = Mockery::mock(PartitionMaintenanceService::class);
        $dirtyPeriods = Mockery::mock(DashboardHarianSnapshotDirtyPeriodQueue::class);

        $importProgressService = Mockery::mock(\App\Services\Import\ImportProgressService::class);
        $importProgressService->shouldReceive('hasActiveProcessingJobsForTable')
            ->once()
            ->with('gi405_recovery', 77)
            ->andReturnFalse();
        $this->app->instance(\App\Services\Import\ImportProgressService::class, $importProgressService);

        $dashboardHarianSnapshotService->shouldReceive('rebuild')
            ->once()
            ->with('2026-05-21', false)
            ->andReturn(['2026-05-21' => 1]);

        $service = new ReportDataSyncService($builder, $dashboardHarianSnapshotService, $partitionMaintenance, $dirtyPeriods);
        $service->syncImportedTable('gi405_recovery', '2026-05-21', 77, 'unit-test');

        Bus::assertDispatched(EnsureImportedSnapshotsFreshJob::class);
    }
}
