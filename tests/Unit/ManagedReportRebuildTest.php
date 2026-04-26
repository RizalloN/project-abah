<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportIndexController;
use App\Jobs\RunManagedReportSnapshotRebuildJob;
use App\Jobs\WarmReportCacheJob;
use App\Support\DashboardHarianSnapshotService;
use App\Support\ManagedReportSnapshotRebuildCoordinator;
use App\Support\ManagedReportSnapshotRebuildStore;
use App\Support\PartitionMaintenanceService;
use App\Support\ReportDataSyncService;
use App\Support\ReportSnapshotBuilder;
use App\Services\Import\ImportProgressService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class ManagedReportRebuildTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');
        Queue::fake();

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();
        Schema::create('nama_report', function (Blueprint $table) {
            $table->integer('id_report')->primary();
            $table->string('nama_report');
            $table->string('table_name');
            $table->boolean('active')->default(true);
        });
        Schema::create('jobs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('queue')->index();
            $table->integer('reserved_at')->nullable();
            $table->integer('available_at')->nullable();
            $table->integer('created_at')->nullable();
            $table->longText('payload');
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_rebuild_endpoint_recovers_from_stale_pending_slot_and_requeues_job(): void
    {
        Cache::put(
            ManagedReportSnapshotRebuildStore::PENDING_KEY,
            now()->subMinutes(20)->toIso8601String(),
            ManagedReportSnapshotRebuildStore::ttl()
        );

        $controller = new ImportIndexController(app(PartitionMaintenanceService::class));
        $response = $controller->rebuildManagedReportSnapshots(Request::create('/import/report-management/rebuild', 'POST', [
            'force_rebuild' => true,
        ]));

        $payload = $response->getData(true);

        $this->assertSame('queued', $payload['status']);
        $this->assertTrue($payload['force_rebuild']);
        Queue::assertPushed(RunManagedReportSnapshotRebuildJob::class, 1);
    }

    public function test_rebuild_status_runs_inline_fallback_when_queue_never_starts(): void
    {
        $rebuildId = (string) \Illuminate\Support\Str::uuid();

        Cache::put(
            ManagedReportSnapshotRebuildStore::stateKey($rebuildId),
            [
                'rebuild_id' => $rebuildId,
                'status' => 'queued',
                'stage' => 'queued',
                'queued' => true,
                'force_rebuild' => false,
                'source' => 'unit-test',
                'message' => 'Refresh snapshot seluruh report sedang menunggu worker.',
                'progress_percent' => 0,
                'completed_units' => 0,
                'total_units' => 1,
                'build_units' => 0,
                'current_report_key' => null,
                'current_report_label' => null,
                'current_period' => null,
                'report_completed_units' => 0,
                'report_total_units' => 0,
                'reports' => [],
                'results' => [],
                'started_at' => null,
                'finished_at' => null,
                'created_at' => now()->subMinute()->toIso8601String(),
                'updated_at' => now()->subMinute()->toIso8601String(),
            ],
            ManagedReportSnapshotRebuildStore::ttl()
        );

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $builder->shouldReceive('describeRebuildPlan')
            ->once()
            ->andReturn([
                'reports' => [],
                'build_units' => 0,
                'total_units' => 1,
            ]);

        $dashboardHarianSnapshotService = Mockery::mock(DashboardHarianSnapshotService::class);
        $syncService = Mockery::mock(ReportDataSyncService::class);
        $syncService->shouldReceive('invalidateReportCaches')->once()->andReturn(2);

        app()->instance(ReportSnapshotBuilder::class, $builder);
        app()->instance(DashboardHarianSnapshotService::class, $dashboardHarianSnapshotService);
        app()->instance(ReportDataSyncService::class, $syncService);

        $controller = new ImportIndexController(app(PartitionMaintenanceService::class));
        $response = $controller->managedReportRebuildStatus($rebuildId);
        $payload = $response->getData(true);

        $this->assertSame('completed', $payload['status']);
        $this->assertSame('completed', $payload['stage']);
        Queue::assertPushed(WarmReportCacheJob::class, 1);
    }

    public function test_force_start_runs_snapshot_inline_from_queued_state(): void
    {
        $rebuildId = (string) \Illuminate\Support\Str::uuid();

        Cache::put(
            ManagedReportSnapshotRebuildStore::stateKey($rebuildId),
            [
                'rebuild_id' => $rebuildId,
                'status' => 'queued',
                'stage' => 'queued',
                'queued' => true,
                'force_rebuild' => true,
                'source' => 'unit-test-force-start',
                'message' => 'Rebuild snapshot seluruh report sedang menunggu worker.',
                'progress_percent' => 0,
                'completed_units' => 0,
                'total_units' => 1,
                'build_units' => 0,
                'current_report_key' => null,
                'current_report_label' => null,
                'current_period' => null,
                'report_completed_units' => 0,
                'report_total_units' => 0,
                'reports' => [],
                'results' => [],
                'started_at' => null,
                'finished_at' => null,
                'created_at' => now()->subMinute()->toIso8601String(),
                'updated_at' => now()->subMinute()->toIso8601String(),
            ],
            ManagedReportSnapshotRebuildStore::ttl()
        );

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $builder->shouldReceive('describeRebuildPlan')
            ->once()
            ->andReturn([
                'reports' => [],
                'build_units' => 0,
                'total_units' => 1,
            ]);

        $dashboardHarianSnapshotService = Mockery::mock(DashboardHarianSnapshotService::class);
        $syncService = Mockery::mock(ReportDataSyncService::class);
        $syncService->shouldReceive('invalidateReportCaches')->once()->andReturn(2);

        app()->instance(ReportSnapshotBuilder::class, $builder);
        app()->instance(DashboardHarianSnapshotService::class, $dashboardHarianSnapshotService);
        app()->instance(ReportDataSyncService::class, $syncService);

        $resolved = app(ManagedReportSnapshotRebuildCoordinator::class)->forceStart($rebuildId);

        $this->assertSame(200, $resolved['status_code']);
        $this->assertSame('completed', $resolved['payload']['status']);
        Queue::assertPushed(WarmReportCacheJob::class, 1);
    }

    public function test_reconcile_recovers_snapshot_state_from_queue_row_when_cache_state_missing(): void
    {
        $rebuildId = '123e4567-e89b-12d3-a456-426614174001';

        DB::table('jobs')->insert([
            'queue' => 'default',
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
            'payload' => 'a:1:{s:10:"rebuildId";s:36:"' . $rebuildId . '";s:10:"jobClass";s:46:"RunManagedReportSnapshotRebuildJob";}',
        ]);

        Cache::forget(ManagedReportSnapshotRebuildStore::stateKey($rebuildId));

        $state = app(ManagedReportSnapshotRebuildCoordinator::class)->reconcile($rebuildId);

        $this->assertIsArray($state);
        $this->assertSame($rebuildId, $state['rebuild_id']);
        $this->assertSame('queued', $state['status']);
        $this->assertSame('synthetic', $state['state_source']);
    }

    public function test_reconcile_marks_stale_snapshot_state_failed_when_queue_row_missing(): void
    {
        $rebuildId = '123e4567-e89b-12d3-a456-426614174002';

        Cache::put(
            ManagedReportSnapshotRebuildStore::stateKey($rebuildId),
            [
                'rebuild_id' => $rebuildId,
                'status' => 'queued',
                'stage' => 'queued',
                'queued' => true,
                'force_rebuild' => true,
                'source' => 'unit-test-stale',
                'message' => 'Snapshot menunggu worker.',
                'progress_percent' => 0,
                'completed_units' => 0,
                'total_units' => 1,
                'build_units' => 0,
                'current_report_key' => null,
                'current_report_label' => null,
                'current_period' => null,
                'report_completed_units' => 0,
                'report_total_units' => 0,
                'reports' => [],
                'results' => [],
                'started_at' => null,
                'finished_at' => null,
                'created_at' => now()->subMinutes(40)->toIso8601String(),
                'updated_at' => now()->subMinutes(40)->toIso8601String(),
            ],
            ManagedReportSnapshotRebuildStore::ttl()
        );

        $state = app(ManagedReportSnapshotRebuildCoordinator::class)->reconcile($rebuildId);

        $this->assertIsArray($state);
        $this->assertSame('failed', $state['status']);
        $this->assertSame('failed', $state['stage']);
        $this->assertStringContainsString('tidak lagi memiliki job queue aktif', $state['message']);
    }

    public function test_reconcile_keeps_running_snapshot_state_without_queue_row_while_progress_is_recent(): void
    {
        $rebuildId = '123e4567-e89b-12d3-a456-426614174003';

        Cache::put(
            ManagedReportSnapshotRebuildStore::stateKey($rebuildId),
            [
                'rebuild_id' => $rebuildId,
                'status' => 'running',
                'stage' => 'rebuilding',
                'queued' => false,
                'force_rebuild' => true,
                'source' => 'unit-test-running',
                'message' => 'Chart Periodik: 2/8 periode selesai.',
                'progress_percent' => 2,
                'completed_units' => 4,
                'total_units' => 178,
                'build_units' => 177,
                'current_report_key' => 'chart_periodik',
                'current_report_label' => 'Chart Periodik',
                'current_period' => '2026-04-20',
                'report_completed_units' => 2,
                'report_total_units' => 8,
                'reports' => [],
                'results' => [],
                'started_at' => now()->subHours(2)->toIso8601String(),
                'finished_at' => null,
                'created_at' => now()->subHours(2)->toIso8601String(),
                'updated_at' => now()->subMinutes(5)->toIso8601String(),
            ],
            ManagedReportSnapshotRebuildStore::ttl()
        );

        $state = app(ManagedReportSnapshotRebuildCoordinator::class)->reconcile($rebuildId);

        $this->assertIsArray($state);
        $this->assertSame('running', $state['status']);
        $this->assertSame('rebuilding', $state['stage']);
    }

    public function test_sweep_marks_running_snapshot_state_failed_when_progress_is_stale(): void
    {
        $rebuildId = '123e4567-e89b-12d3-a456-426614174004';

        Cache::put(
            ManagedReportSnapshotRebuildStore::stateKey($rebuildId),
            [
                'rebuild_id' => $rebuildId,
                'status' => 'running',
                'stage' => 'rebuilding',
                'queued' => false,
                'force_rebuild' => true,
                'source' => 'unit-test-stale-running',
                'message' => 'Dashboard Harian: 10/155 periode selesai.',
                'progress_percent' => 10,
                'completed_units' => 10,
                'total_units' => 178,
                'build_units' => 177,
                'current_report_key' => 'dashboard_harian',
                'current_report_label' => 'Dashboard Harian',
                'current_period' => '2026-02-20',
                'report_completed_units' => 10,
                'report_total_units' => 155,
                'reports' => [],
                'results' => [],
                'started_at' => now()->subHours(2)->toIso8601String(),
                'finished_at' => null,
                'created_at' => now()->subHours(2)->toIso8601String(),
                'updated_at' => now()->subMinutes(70)->toIso8601String(),
            ],
            ManagedReportSnapshotRebuildStore::ttl()
        );
        ManagedReportSnapshotRebuildStore::setActiveRebuildId($rebuildId);

        $count = app(ManagedReportSnapshotRebuildCoordinator::class)->sweepStaleStates();
        $state = ManagedReportSnapshotRebuildStore::getState($rebuildId);

        $this->assertGreaterThanOrEqual(1, $count);
        $this->assertSame('failed', $state['status']);
        $this->assertStringContainsString('progress tidak bergerak', $state['message']);
    }

    public function test_superseded_snapshot_job_does_not_overwrite_old_running_state(): void
    {
        $oldRebuildId = '123e4567-e89b-12d3-a456-426614174005';
        $activeRebuildId = '123e4567-e89b-12d3-a456-426614174006';

        ManagedReportSnapshotRebuildStore::putState([
            'rebuild_id' => $oldRebuildId,
            'status' => 'running',
            'stage' => 'rebuilding',
            'queued' => false,
            'force_rebuild' => true,
            'source' => 'unit-test-old-worker',
            'message' => 'Rasio CASA Debitur: 1/2 periode selesai.',
            'progress_percent' => 96,
            'completed_units' => 172,
            'total_units' => 178,
            'build_units' => 177,
            'current_report_key' => 'rasio',
            'current_report_label' => 'Rasio CASA Debitur',
            'current_period' => '2026-04-22',
            'report_completed_units' => 1,
            'report_total_units' => 2,
            'reports' => [],
            'results' => [],
            'started_at' => now()->subHour()->toIso8601String(),
            'finished_at' => null,
            'created_at' => now()->subHour()->toIso8601String(),
        ]);
        ManagedReportSnapshotRebuildStore::setActiveRebuildId($activeRebuildId);

        $importProgressService = Mockery::mock(ImportProgressService::class);
        $importProgressService->shouldReceive('hasActiveProcessingJobs')->once()->andReturnFalse();
        app()->instance(ImportProgressService::class, $importProgressService);

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $builder->shouldReceive('describeRebuildPlan')->never();

        (new RunManagedReportSnapshotRebuildJob(true, 'unit-test', $oldRebuildId))->handle(
            $builder,
            Mockery::mock(DashboardHarianSnapshotService::class),
            Mockery::mock(ReportDataSyncService::class)
        );

        $state = ManagedReportSnapshotRebuildStore::getState($oldRebuildId);

        $this->assertSame('running', $state['status']);
        $this->assertSame('Rasio CASA Debitur: 1/2 periode selesai.', $state['message']);
        $this->assertSame($activeRebuildId, ManagedReportSnapshotRebuildStore::getActiveRebuildId());
    }
}
