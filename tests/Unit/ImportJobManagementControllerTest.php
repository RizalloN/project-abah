<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportIndexController;
use App\Http\Controllers\Import\ImportJobManagementController;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportProgressService;
use App\Support\DashboardHarianSnapshotService;
use App\Support\ManagedReportSnapshotRebuildCoordinator;
use App\Support\ManagedReportSnapshotRebuildStore;
use App\Support\PartitionMaintenanceService;
use App\Support\ReportDataSyncService;
use App\Support\ReportSnapshotBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class ImportJobManagementControllerTest extends TestCase
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

    public function test_force_start_runs_queued_import_inline(): void
    {
        $progressService = Mockery::mock(ImportProgressService::class);
        $executionService = Mockery::mock(ImportExecutionService::class);

        $progressService->shouldReceive('findJob')
            ->once()
            ->with(77)
            ->andReturn((object) [
                'id' => 77,
                'status' => 'queued',
                'total_success' => 0,
                'total_failed' => 0,
            ]);
        $progressService->shouldReceive('cleanupQueuedImportJobRowsForJob')
            ->once()
            ->with(77);
        $executionService->shouldReceive('run')
            ->once()
            ->with(77);

        $controller = new ImportJobManagementController(
            app(ManagedReportSnapshotRebuildCoordinator::class),
            app(ImportIndexController::class)
        );
        $response = $controller->forceStart(77, $progressService, $executionService);
        $payload = $response->getData(true);

        $this->assertSame('success', $payload['status']);
    }

    public function test_queue_health_purges_stale_reserved_snapshot_jobs_without_active_state(): void
    {
        $rebuildId = '123e4567-e89b-12d3-a456-426614174000';

        DB::table('jobs')->insert([
            'queue' => 'default',
            'reserved_at' => now()->subMinutes(20)->timestamp,
            'available_at' => now()->subMinutes(20)->timestamp,
            'created_at' => now()->subMinutes(30)->timestamp,
            'payload' => 'a:1:{s:10:"rebuildId";s:36:"' . $rebuildId . '";s:10:"jobClass";s:46:"RunManagedReportSnapshotRebuildJob";}',
        ]);

        Cache::forget(ManagedReportSnapshotRebuildStore::stateKey($rebuildId));
        Cache::forget(ManagedReportSnapshotRebuildStore::ACTIVE_KEY);
        Cache::forget(ManagedReportSnapshotRebuildStore::PENDING_KEY);

        $controller = new ImportJobManagementController(
            app(ManagedReportSnapshotRebuildCoordinator::class),
            app(ImportIndexController::class)
        );
        $method = new \ReflectionMethod($controller, 'resolveQueueHealth');
        $method->setAccessible(true);

        $health = $method->invoke($controller);

        $this->assertSame('ok', $health['status']);
        $this->assertSame(1, $health['purged_reserved_snapshot_jobs']);
        $this->assertSame(0, $health['stale_reserved_snapshot_jobs']);
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_force_start_snapshot_recovers_from_queue_row_when_cache_state_missing(): void
    {
        $rebuildId = '123e4567-e89b-12d3-a456-426614174003';

        DB::table('jobs')->insert([
            'queue' => 'default',
            'reserved_at' => null,
            'available_at' => now()->subMinute()->timestamp,
            'created_at' => now()->subMinute()->timestamp,
            'payload' => 'a:1:{s:10:"rebuildId";s:36:"' . $rebuildId . '";s:10:"jobClass";s:46:"RunManagedReportSnapshotRebuildJob";}',
        ]);

        Cache::forget(ManagedReportSnapshotRebuildStore::stateKey($rebuildId));

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

        $controller = new ImportJobManagementController(
            app(ManagedReportSnapshotRebuildCoordinator::class),
            app(ImportIndexController::class)
        );
        $response = $controller->forceStartSnapshot($rebuildId);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('completed', $payload['status']);
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_force_start_snapshot_does_not_delete_queue_row_when_rebuild_not_found(): void
    {
        $existingRebuildId = '123e4567-e89b-12d3-a456-426614174004';

        DB::table('jobs')->insert([
            'queue' => 'default',
            'reserved_at' => null,
            'available_at' => now()->subMinute()->timestamp,
            'created_at' => now()->subMinute()->timestamp,
            'payload' => 'a:1:{s:10:"rebuildId";s:36:"' . $existingRebuildId . '";s:10:"jobClass";s:46:"RunManagedReportSnapshotRebuildJob";}',
        ]);

        $controller = new ImportJobManagementController(
            app(ManagedReportSnapshotRebuildCoordinator::class),
            app(ImportIndexController::class)
        );
        $response = $controller->forceStartSnapshot('123e4567-e89b-12d3-a456-426614174099');
        $payload = $response->getData(true);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Snapshot rebuild tidak ditemukan.', $payload['message']);
        $this->assertSame(1, DB::table('jobs')->count());
    }

    public function test_resolve_snapshot_jobs_uses_synthetic_state_from_queue_row(): void
    {
        $rebuildId = '123e4567-e89b-12d3-a456-426614174005';

        DB::table('jobs')->insert([
            'queue' => 'default',
            'reserved_at' => null,
            'available_at' => now()->subMinute()->timestamp,
            'created_at' => now()->subMinute()->timestamp,
            'payload' => 'a:1:{s:10:"rebuildId";s:36:"' . $rebuildId . '";s:10:"jobClass";s:46:"RunManagedReportSnapshotRebuildJob";}',
        ]);

        Cache::forget(ManagedReportSnapshotRebuildStore::stateKey($rebuildId));

        $controller = new ImportJobManagementController(
            app(ManagedReportSnapshotRebuildCoordinator::class),
            app(ImportIndexController::class)
        );
        $method = new \ReflectionMethod($controller, 'resolveSnapshotJobs');
        $method->setAccessible(true);

        $jobs = $method->invoke($controller);

        $this->assertCount(1, $jobs);
        $this->assertSame($rebuildId, $jobs[0]['id']);
        $this->assertSame('queued', $jobs[0]['status']);
        $this->assertTrue($jobs[0]['can_force_start']);
    }
}
