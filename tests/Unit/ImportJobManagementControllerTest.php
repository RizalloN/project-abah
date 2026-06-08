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

        Schema::create('nama_report', function (Blueprint $table) {
            $table->integer('id_report')->primary();
            $table->string('nama_report')->nullable();
            $table->string('table_name')->nullable();
            $table->boolean('active')->default(true);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
        });

        Schema::create('import_jobs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('id_report')->nullable();
            $table->string('file_name')->nullable();
            $table->string('folder_path')->nullable();
            $table->string('status')->nullable();
            $table->integer('total_files')->default(0);
            $table->integer('total_success')->default(0);
            $table->integer('total_failed')->default(0);
            $table->integer('created_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_force_start_runs_queued_import_in_background_when_launcher_available(): void
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
        $executionService->shouldNotReceive('run');

        $controller = new class(
            app(ManagedReportSnapshotRebuildCoordinator::class),
            app(ImportIndexController::class)
        ) extends ImportJobManagementController {
            protected function launchImportInBackground(int $jobId): bool
            {
                return true;
            }
        };
        $response = $controller->forceStart(77, $progressService, $executionService);
        $payload = $response->getData(true);

        $this->assertSame('success', $payload['status']);
        $this->assertStringContainsString('background', $payload['message']);
    }

    public function test_force_start_falls_back_to_inline_when_background_launcher_fails(): void
    {
        $progressService = Mockery::mock(ImportProgressService::class);
        $executionService = Mockery::mock(ImportExecutionService::class);

        $progressService->shouldReceive('findJob')
            ->once()
            ->with(78)
            ->andReturn((object) [
                'id' => 78,
                'status' => 'queued',
                'total_success' => 0,
                'total_failed' => 0,
            ]);
        $progressService->shouldReceive('cleanupQueuedImportJobRowsForJob')
            ->once()
            ->with(78);
        $executionService->shouldReceive('run')
            ->once()
            ->with(78);

        $controller = new class(
            app(ManagedReportSnapshotRebuildCoordinator::class),
            app(ImportIndexController::class)
        ) extends ImportJobManagementController {
            protected function launchImportInBackground(int $jobId): bool
            {
                return false;
            }
        };
        $response = $controller->forceStart(78, $progressService, $executionService);
        $payload = $response->getData(true);

        $this->assertSame('success', $payload['status']);
        $this->assertStringContainsString('diproses langsung', $payload['message']);
    }

    public function test_force_start_allows_failed_zero_progress_job(): void
    {
        $progressService = Mockery::mock(ImportProgressService::class);
        $executionService = Mockery::mock(ImportExecutionService::class);

        $progressService->shouldReceive('findJob')
            ->once()
            ->with(79)
            ->andReturn((object) [
                'id' => 79,
                'status' => 'failed',
                'total_files' => 100,
                'total_success' => 0,
                'total_failed' => 0,
            ]);
        $progressService->shouldReceive('clearTerminationRequest')
            ->once()
            ->with(79);
        $progressService->shouldReceive('markQueued')
            ->once()
            ->with(79, Mockery::on(fn (array $payload): bool => ($payload['status'] ?? null) === 'queued'
                && ($payload['total_rows'] ?? null) === 100
                && ($payload['total_success'] ?? null) === 0
                && ($payload['total_failed'] ?? null) === 0));
        $progressService->shouldReceive('cleanupQueuedImportJobRowsForJob')
            ->once()
            ->with(79);
        $executionService->shouldNotReceive('run');

        $controller = new class(
            app(ManagedReportSnapshotRebuildCoordinator::class),
            app(ImportIndexController::class)
        ) extends ImportJobManagementController {
            protected function launchImportInBackground(int $jobId): bool
            {
                return true;
            }
        };
        $response = $controller->forceStart(79, $progressService, $executionService);
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

    public function test_terminate_marks_queued_job_terminated_and_cleans_queue_rows(): void
    {
        $progressService = Mockery::mock(ImportProgressService::class);
        $progressService->shouldReceive('findJob')
            ->once()
            ->with(91)
            ->andReturn((object) [
                'id' => 91,
                'status' => 'queued',
                'total_success' => 7,
                'total_failed' => 2,
            ]);
        $progressService->shouldReceive('requestTermination')
            ->once()
            ->with(91, null);
        $progressService->shouldReceive('cleanupQueuedImportJobRowsForJob')
            ->once()
            ->with(91);
        $progressService->shouldReceive('markTerminated')
            ->once()
            ->with(91, 'Job dihentikan melalui Job Management.', 7, 2);

        $controller = new ImportJobManagementController(
            app(ManagedReportSnapshotRebuildCoordinator::class),
            app(ImportIndexController::class)
        );

        $response = $controller->terminate(request(), 91, $progressService);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('success', $payload['status']);
        $this->assertSame('Job queued berhasil dihentikan.', $payload['message']);
    }

    public function test_terminate_processing_job_forces_terminal_status_immediately(): void
    {
        $progressService = Mockery::mock(ImportProgressService::class);
        $progressService->shouldReceive('findJob')
            ->once()
            ->with(92)
            ->andReturn((object) [
                'id' => 92,
                'status' => 'processing',
                'total_success' => 12,
                'total_failed' => 3,
                'total_files' => 25,
            ]);
        $progressService->shouldReceive('requestTermination')
            ->once()
            ->with(92, null);
        $progressService->shouldReceive('cleanupQueuedImportJobRowsForJob')
            ->once()
            ->with(92);
        $progressService->shouldReceive('markTerminated')
            ->once()
            ->with(92, 'Job dihentikan melalui Job Management.', 12, 3);

        $controller = new ImportJobManagementController(
            app(ManagedReportSnapshotRebuildCoordinator::class),
            app(ImportIndexController::class)
        );

        $response = $controller->terminate(request(), 92, $progressService);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('success', $payload['status']);
        $this->assertSame('Job processing dihentikan paksa. Jika worker lama masih aktif, status job tidak akan diaktifkan kembali.', $payload['message']);
    }

    public function test_terminate_rejects_non_active_job_status(): void
    {
        $progressService = Mockery::mock(ImportProgressService::class);
        $progressService->shouldReceive('findJob')
            ->once()
            ->with(93)
            ->andReturn((object) [
                'id' => 93,
                'status' => 'completed',
            ]);
        $progressService->shouldNotReceive('requestTermination');

        $controller = new ImportJobManagementController(
            app(ManagedReportSnapshotRebuildCoordinator::class),
            app(ImportIndexController::class)
        );

        $response = $controller->terminate(request(), 93, $progressService);
        $payload = $response->getData(true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('error', $payload['status']);
        $this->assertSame('Hanya job queued atau processing yang bisa dihentikan.', $payload['message']);
    }

    public function test_data_uses_status_payload_to_expose_job_actions_consistently(): void
    {
        DB::table('nama_report')->insert([
            'id_report' => 8,
            'nama_report' => 'Daily Loan Dinamis',
            'table_name' => 'daily_loan_dinamis',
            'active' => 1,
        ]);

        DB::table('users')->insert([
            'id' => 5,
            'name' => 'Operator Import',
        ]);

        DB::table('import_jobs')->insert([
            [
                'id' => 201,
                'id_report' => 8,
                'file_name' => 'queued.xlsx',
                'folder_path' => 'imports/queued',
                'status' => 'queued',
                'total_files' => 100,
                'total_success' => 0,
                'total_failed' => 0,
                'created_by' => 5,
                'created_at' => now()->subMinutes(5),
                'updated_at' => now()->subMinutes(4),
            ],
            [
                'id' => 202,
                'id_report' => 8,
                'file_name' => 'done.xlsx',
                'folder_path' => 'imports/done',
                'status' => 'processing',
                'total_files' => 50,
                'total_success' => 50,
                'total_failed' => 0,
                'created_by' => 5,
                'created_at' => now()->subMinutes(3),
                'updated_at' => now()->subMinute(),
            ],
        ]);

        $progressService = Mockery::mock(ImportProgressService::class);
        $progressService->shouldReceive('getStatusPayload')
            ->with(201)
            ->once()
            ->andReturn([
                'status' => 'queued',
                'percent' => 0,
                'processed_rows' => 0,
                'total_rows' => 100,
                'message' => 'Menunggu worker.',
            ]);
        $progressService->shouldReceive('getStatusPayload')
            ->with(202)
            ->once()
            ->andReturn([
                'status' => 'terminated',
                'percent' => 100,
                'processed_rows' => 50,
                'total_success' => 50,
                'total_failed' => 0,
                'total_rows' => 50,
                'message' => 'Job sudah dihentikan.',
            ]);

        $controller = new ImportJobManagementController(
            app(ManagedReportSnapshotRebuildCoordinator::class),
            app(ImportIndexController::class)
        );

        $response = $controller->data(request(), $progressService);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('success', $payload['status']);

        $queuedJob = collect($payload['jobs'])->firstWhere('id', 201);
        $terminatedJob = collect($payload['jobs'])->firstWhere('id', 202);

        $this->assertIsArray($queuedJob);
        $this->assertTrue($queuedJob['can_terminate']);
        $this->assertTrue($queuedJob['can_force_start']);
        $this->assertFalse($queuedJob['can_delete']);

        $this->assertIsArray($terminatedJob);
        $this->assertSame('terminated', $terminatedJob['status']);
        $this->assertFalse($terminatedJob['can_terminate']);
        $this->assertFalse($terminatedJob['can_force_start']);
        $this->assertTrue($terminatedJob['can_delete']);
    }
}
