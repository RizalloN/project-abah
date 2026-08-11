<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportIndexController;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportProgressService;
use App\Services\JobHealthService;
use App\Support\ManagedReportLoadCoordinator;
use App\Support\ManagedReportSnapshotRebuildCoordinator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class JobHealthServiceTest extends TestCase
{
    private string $originalDefaultConnection;
    private mixed $originalSqliteDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefaultConnection = (string) Config::get('database.default');
        $this->originalSqliteDatabase = Config::get('database.connections.sqlite.database');

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Cache::flush();

        Schema::create('jobs', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('queue')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->integer('reserved_at')->nullable();
            $table->integer('available_at')->nullable();
            $table->integer('created_at')->nullable();
            $table->longText('payload');
        });
        Schema::create('job_batches', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids')->nullable();
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Config::set('database.default', $this->originalDefaultConnection);
        Config::set('database.connections.sqlite.database', $this->originalSqliteDatabase);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        parent::tearDown();
    }

    public function test_health_sweep_requeues_orphans_before_stale_import_cleanup(): void
    {
        $progressService = Mockery::mock(ImportProgressService::class);
        $executionService = Mockery::mock(ImportExecutionService::class);
        $loadCoordinator = Mockery::mock(ManagedReportLoadCoordinator::class);
        $rebuildCoordinator = Mockery::mock(ManagedReportSnapshotRebuildCoordinator::class);
        $importIndexController = Mockery::mock(ImportIndexController::class);

        $executionService->shouldReceive('recoverOrphanedZeroProgressJobs')
            ->once()
            ->ordered()
            ->andReturn([172]);
        $progressService->shouldReceive('purgeStaleQueuedJobs')
            ->once()
            ->ordered()
            ->andReturn(0);
        $progressService->shouldReceive('purgeStaleProcessingJobs')
            ->once()
            ->ordered()
            ->andReturn(0);
        $loadCoordinator->shouldReceive('sweepStaleStates')->once()->andReturn(0);
        $rebuildCoordinator->shouldReceive('sweepStaleStates')->once()->andReturn(0);
        $importIndexController->shouldReceive('sweepManagedReportDeleteStates')->once()->andReturn(0);

        $service = new JobHealthService(
            $progressService,
            $executionService,
            $loadCoordinator,
            $rebuildCoordinator,
            $importIndexController
        );

        $summary = $service->sweepNow();

        $this->assertSame(1, $summary['orphaned_imports_requeued']);
        $this->assertSame([172], $summary['orphaned_import_job_ids']);
        $this->assertSame(0, $summary['purged_queue_rows']['imports']);
        $this->assertSame(0, $summary['purged_queue_rows']['reserved_imports']);
    }

    public function test_health_sweep_closes_orphaned_snapshot_batch_and_queues_freshness_check(): void
    {
        Config::set('queue.default', 'database');
        Config::set('queue.connections.database.connection', 'sqlite');
        Config::set('queue.connections.database.table', 'jobs');

        DB::table('job_batches')->insert([
            'id' => 'stale-daily-loan-batch',
            'name' => 'daily_loan:2026-07-19',
            'total_jobs' => 5,
            'pending_jobs' => 1,
            'failed_jobs' => 1,
            'failed_job_ids' => '[]',
            'options' => '{}',
            'created_at' => now()->subHours(3)->timestamp,
            'finished_at' => null,
            'cancelled_at' => null,
        ]);
        $this->assertSame(1, DB::table('job_batches')
            ->where('pending_jobs', '>', 0)
            ->whereNull('finished_at')
            ->whereNull('cancelled_at')
            ->where('created_at', '<=', now()->subHours(2)->timestamp)
            ->count());
        $this->assertTrue(Schema::hasTable('job_batches'));
        $this->assertSame(0, DB::table('jobs')->count());

        $progressService = Mockery::mock(ImportProgressService::class);
        $executionService = Mockery::mock(ImportExecutionService::class);
        $loadCoordinator = Mockery::mock(ManagedReportLoadCoordinator::class);
        $rebuildCoordinator = Mockery::mock(ManagedReportSnapshotRebuildCoordinator::class);
        $importIndexController = Mockery::mock(ImportIndexController::class);

        $executionService->shouldReceive('recoverOrphanedZeroProgressJobs')->once()->andReturn([]);
        $progressService->shouldReceive('purgeStaleQueuedJobs')->once()->andReturn(0);
        $progressService->shouldReceive('purgeStaleProcessingJobs')->once()->andReturn(0);
        $loadCoordinator->shouldReceive('sweepStaleStates')->once()->andReturn(0);
        $rebuildCoordinator->shouldReceive('sweepStaleStates')->once()->andReturn(0);
        $importIndexController->shouldReceive('sweepManagedReportDeleteStates')->once()->andReturn(0);

        $service = new JobHealthService(
            $progressService,
            $executionService,
            $loadCoordinator,
            $rebuildCoordinator,
            $importIndexController
        );

        $summary = $service->sweepNow();
        $batch = DB::table('job_batches')->where('id', 'stale-daily-loan-batch')->first();

        $this->assertSame(1, $summary['orphaned_snapshot_batches_recovered']);
        $this->assertSame(['stale-daily-loan-batch'], $summary['orphaned_snapshot_batch_ids']);
        $this->assertSame(0, (int) $batch->pending_jobs);
        $this->assertNotNull($batch->finished_at);
        $this->assertTrue(DB::table('jobs')->where('queue', 'snapshots-parallel')->exists());
    }
}
