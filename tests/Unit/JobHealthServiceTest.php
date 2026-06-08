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
            $table->integer('reserved_at')->nullable();
            $table->integer('available_at')->nullable();
            $table->integer('created_at')->nullable();
            $table->longText('payload');
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
}
