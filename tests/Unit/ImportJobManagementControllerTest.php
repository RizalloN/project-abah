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
        $progressService->shouldReceive('getJobState')->with(78)->andReturn([]);

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

    public function test_force_start_repeated_click_does_not_launch_another_process(): void
    {
        $progress = Mockery::mock(ImportProgressService::class);
        $execution = Mockery::mock(ImportExecutionService::class);
        $progress->shouldReceive('findJob')->twice()->with(80)->andReturn((object) [
            'id' => 80, 'status' => 'queued', 'total_success' => 0, 'total_failed' => 0,
        ]);
        $progress->shouldReceive('cleanupQueuedImportJobRowsForJob')->once()->with(80);
        $execution->shouldNotReceive('run');
        $controller = new class(app(ManagedReportSnapshotRebuildCoordinator::class), app(ImportIndexController::class)) extends ImportJobManagementController {
            public int $launchCount = 0;
            protected function launchImportInBackground(int $jobId): bool
            {
                $this->launchCount++;

                return true;
            }
        };

        $this->assertSame(200, $controller->forceStart(80, $progress, $execution)->getStatusCode());
        $this->assertSame(409, $controller->forceStart(80, $progress, $execution)->getStatusCode());
        $this->assertSame(1, $controller->launchCount);
    }

    public function test_force_start_does_not_touch_a_reserved_worker_job(): void
    {
        $progress = Mockery::mock(ImportProgressService::class);
        $execution = Mockery::mock(ImportExecutionService::class);
        $progress->shouldReceive('findJob')->once()->with(81)->andReturn((object) [
            'id' => 81, 'status' => 'queued', 'total_success' => 0, 'total_failed' => 0,
        ]);
        $progress->shouldNotReceive('cleanupQueuedImportJobRowsForJob');
        $execution->shouldNotReceive('run');
        DB::table('jobs')->insert([
            'queue' => 'imports-high', 'reserved_at' => time(),
            'payload' => json_encode(['displayName' => \App\Jobs\RunImportJob::class,
                'data' => ['command' => serialize(new \App\Jobs\RunImportJob(81))]]),
        ]);

        $response = app(ImportJobManagementController::class)->forceStart(81, $progress, $execution);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame(1, DB::table('jobs')->count());
    }

    public function test_force_start_rejects_partially_imported_job(): void
    {
        $progress = Mockery::mock(ImportProgressService::class);
        $execution = Mockery::mock(ImportExecutionService::class);
        $progress->shouldReceive('findJob')->once()->with(82)->andReturn((object) [
            'id' => 82, 'status' => 'failed_partial', 'total_success' => 20, 'total_failed' => 1,
        ]);
        $progress->shouldNotReceive('cleanupQueuedImportJobRowsForJob');
        $execution->shouldNotReceive('run');

        $this->assertSame(422, app(ImportJobManagementController::class)->forceStart(82, $progress, $execution)->getStatusCode());
    }

    public function test_force_start_respects_file_lock_after_launch_marker_expires(): void
    {
        $directory = storage_path('framework/testing/force-start-lock-'.uniqid());
        Config::set('cache.stores.force-start-test', ['driver' => 'file', 'path' => $directory, 'lock_path' => $directory]);
        Config::set('import.cache_store', 'force-start-test');
        $cache = Cache::store('force-start-test');
        $lock = $cache->lock('import:immediate-execution:83', 21600);
        $this->assertTrue($lock->get());
        $cache->put('import:force-start-launched:83', true, 60);
        $this->travel(61)->seconds();
        $this->assertFalse($cache->has('import:force-start-launched:83'));
        $progress = Mockery::mock(ImportProgressService::class);
        $execution = Mockery::mock(ImportExecutionService::class);
        $progress->shouldReceive('findJob')->once()->with(83)->andReturn((object) [
            'id' => 83, 'status' => 'queued', 'total_success' => 0, 'total_failed' => 0,
        ]);
        $progress->shouldNotReceive('cleanupQueuedImportJobRowsForJob');
        $execution->shouldNotReceive('run');

        try {
            $this->assertSame(409, app(ImportJobManagementController::class)->forceStart(83, $progress, $execution)->getStatusCode());
        } finally {
            $lock->release();
            $this->travelBack();
        }
    }

    public function test_force_start_timeout_restores_queue_without_inline_replay(): void
    {
        $progress = Mockery::mock(ImportProgressService::class);
        $execution = Mockery::mock(ImportExecutionService::class);
        $progress->shouldReceive('findJob')->twice()->with(84)->andReturn((object) [
            'id' => 84, 'id_report' => 9, 'status' => 'queued', 'total_success' => 0, 'total_failed' => 0,
        ]);
        $progress->shouldReceive('cleanupQueuedImportJobRowsForJob')->once()->with(84);
        $progress->shouldReceive('getJobState')->with(84)->andReturn(['params' => ['table_name' => 'simpanan_multipn']]);
        $execution->shouldNotReceive('run');
        $controller = new class(app(ManagedReportSnapshotRebuildCoordinator::class), app(ImportIndexController::class)) extends ImportJobManagementController {
            protected function launchImportInBackground(int $jobId): bool
            {
                throw new \Illuminate\Process\Exceptions\ProcessTimedOutException(
                    new \Symfony\Component\Process\Exception\ProcessTimedOutException(
                        new \Symfony\Component\Process\Process(['php']),
                        \Symfony\Component\Process\Exception\ProcessTimedOutException::TYPE_GENERAL
                    ),
                    \Illuminate\Support\Facades\Process::result()
                );
            }
        };

        $response = $controller->forceStart(84, $progress, $execution);

        $this->assertSame(202, $response->getStatusCode());
        Queue::assertPushed(\App\Jobs\RunImportJob::class, fn ($job) => $job->jobId === 84 && $job->queue === 'imports-high');
    }

    public function test_queue_health_reports_stale_reserved_snapshot_without_mutating_queue(): void
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
        $this->assertSame(0, $health['purged_reserved_snapshot_jobs']);
        $this->assertSame(1, $health['stale_reserved_snapshot_jobs']);
        $this->assertSame(1, DB::table('jobs')->count());
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

    public function test_managed_delete_history_lists_finished_and_failed_deletes_but_skips_active_ones(): void
    {
        Schema::create('report_sync_audits', function (Blueprint $table) {
            $table->increments('id');
            $table->string('table_name')->nullable();
            $table->string('period_hint')->nullable();
            $table->string('action');
            $table->string('status');
            $table->bigInteger('affected_rows')->nullable();
            $table->text('message')->nullable();
            $table->text('context')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        $audit = fn (string $deleteId, string $action, string $status, int $rows, int $remaining, string $at, ?string $message = null) => DB::table('report_sync_audits')->insert([
            'table_name' => 'ssa_pinjaman', 'period_hint' => '2026-10-14', 'action' => $action, 'status' => $status,
            'affected_rows' => $rows, 'message' => $message, 'created_at' => $at,
            'context' => json_encode(['delete_id' => $deleteId, 'scope_count' => 2, 'remaining_rows' => $remaining]),
        ]);
        $audit('done-1', 'managed_delete_chunk', 'success', 600, 400, now()->subMinutes(5)->toDateTimeString());
        $audit('done-1', 'managed_delete_chunk', 'success', 400, 0, now()->subMinutes(4)->toDateTimeString());
        $audit('fail-1', 'managed_delete_failed', 'failed', 0, 0, now()->subMinutes(3)->toDateTimeString(), "Lock wait timeout
SQL");
        $audit('active-1', 'managed_delete_chunk', 'success', 10, 90, now()->subMinute()->toDateTimeString());
        $audit('old-1', 'managed_delete_chunk', 'success', 10, 0, now()->subDays(30)->toDateTimeString());

        $controller = new ImportJobManagementController(
            app(ManagedReportSnapshotRebuildCoordinator::class),
            app(ImportIndexController::class)
        );
        $method = new \ReflectionMethod($controller, 'resolveManagedDeleteHistory');
        $jobs = collect($method->invoke($controller, ['active-1']))->keyBy('id');

        $this->assertSame(['fail-1', 'done-1'], $jobs->keys()->all());
        $this->assertSame('completed', $jobs['done-1']['status']);
        $this->assertSame(1000, $jobs['done-1']['processed_rows']);
        $this->assertSame(100, $jobs['done-1']['percent']);
        $this->assertStringContainsString('1.000 baris dihapus', $jobs['done-1']['message']);
        $this->assertSame('failed', $jobs['fail-1']['status']);
        $this->assertSame('Delete gagal: Lock wait timeout', $jobs['fail-1']['message']);
        $this->assertFalse($jobs['done-1']['can_cancel']);
        $this->assertSame('managed_delete_history', $jobs['done-1']['kind']);
    }

    public function test_clear_delete_history_respects_filters_and_keeps_audit_and_active_jobs(): void
    {
        Schema::create('report_sync_audits', function (Blueprint $table) {
            $table->increments('id');
            $table->string('table_name');
            $table->string('period_hint')->nullable();
            $table->string('action');
            $table->string('status');
            $table->bigInteger('affected_rows')->nullable();
            $table->text('message')->nullable();
            $table->text('context')->nullable();
            $table->timestamps();
        });
        foreach (['done' => 'success', 'failed' => 'failed', 'active' => 'success'] as $id => $status) {
            DB::table('report_sync_audits')->insert([
                'table_name' => 'loan', 'action' => $status === 'failed' ? 'managed_delete_failed' : 'managed_delete_chunk',
                'status' => $status, 'affected_rows' => 1, 'context' => json_encode(['delete_id' => $id]), 'created_at' => now(),
            ]);
        }
        DB::table('import_jobs')->insert(['id' => 123, 'status' => 'completed']);
        $index = Mockery::mock(ImportIndexController::class);
        $index->shouldReceive('resolveManagedReportDeleteJobs')->andReturn([
            ['id' => 'active', 'status' => 'processing', 'table_name' => 'loan'],
            ['id' => 'live-done', 'status' => 'completed', 'table_name' => 'loan', 'updated_at' => now()->toIso8601String()],
        ]);
        $controller = new ImportJobManagementController(app(ManagedReportSnapshotRebuildCoordinator::class), $index);
        $progress = Mockery::mock(ImportProgressService::class);
        $progress->shouldNotReceive('deleteJobsByIds');

        $filtered = $controller->data(\Illuminate\Http\Request::create('/', 'GET', [
            'status' => 'failed', 'search' => 'loan',
        ]), $progress)->getData(true);
        $this->assertSame(['failed'], array_column($filtered['managed_delete_history'], 'id'));

        $response = $controller->clear(\Illuminate\Http\Request::create('/', 'POST', [
            'history_view' => 'delete', 'status' => 'completed', 'search' => 'loan',
        ]), $progress)->getData(true);
        $this->assertSame(2, $response['deleted_count']);
        $this->assertSame(3, DB::table('report_sync_audits')->whereIn('action', ['managed_delete_chunk', 'managed_delete_failed'])->count());
        $this->assertSame(1, DB::table('import_jobs')->count());

        $payload = $controller->data(\Illuminate\Http\Request::create('/', 'GET', ['search' => 'loan']), $progress)->getData(true);
        $this->assertSame(['failed'], array_column($payload['managed_delete_history'], 'id'));
        $this->assertSame(['active'], array_column($payload['managed_delete_jobs'], 'id'));
        $repeated = $controller->clear(\Illuminate\Http\Request::create('/', 'POST', [
            'history_view' => 'delete', 'status' => 'completed', 'search' => 'loan',
        ]), $progress)->getData(true);
        $this->assertSame(0, $repeated['deleted_count']);
        $activeOnly = $controller->clear(\Illuminate\Http\Request::create('/', 'POST', [
            'history_view' => 'delete', 'active_only' => true,
        ]), $progress)->getData(true);
        $this->assertSame(0, $activeOnly['deleted_count']);
    }

    public function test_finished_deletes_move_from_active_cards_to_execution_history(): void
    {
        Schema::create('report_sync_audits', function (Blueprint $table) {
            $table->increments('id');
            $table->string('table_name')->nullable();
            $table->string('period_hint')->nullable();
            $table->string('action');
            $table->string('status');
            $table->bigInteger('affected_rows')->nullable();
            $table->text('message')->nullable();
            $table->text('context')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        DB::table('report_sync_audits')->insert([
            'table_name' => 'gi405_recovery', 'period_hint' => '2026-10-04', 'action' => 'managed_delete_chunk',
            'status' => 'success', 'affected_rows' => 222, 'created_at' => now()->subHour()->toDateTimeString(),
            'context' => json_encode(['delete_id' => 'audit-done', 'scope_count' => 1, 'remaining_rows' => 0]),
        ]);

        $liveJob = fn (string $id, string $status, string $updatedAt): array => [
            'id' => $id, 'status' => $status, 'table_name' => 'ssa_pinjaman', 'updated_at' => $updatedAt, 'kind' => 'managed_delete',
        ];
        $importIndex = Mockery::mock(ImportIndexController::class);
        $importIndex->shouldReceive('resolveManagedReportDeleteJobs')->once()->andReturn([
            $liveJob('live-active', 'processing', now()->toIso8601String()),
            $liveJob('live-done', 'completed', now()->subMinute()->toIso8601String()),
        ]);

        $controller = new ImportJobManagementController(app(ManagedReportSnapshotRebuildCoordinator::class), $importIndex);
        $payload = $controller->data(request(), Mockery::mock(ImportProgressService::class))->getData(true);

        $this->assertSame(['live-active'], array_column($payload['managed_delete_jobs'], 'id'));
        $this->assertSame(1, $payload['managed_delete_summary']['active_jobs']);
        $this->assertSame(['live-done', 'audit-done'], array_column($payload['managed_delete_history'], 'id'));
    }
}
