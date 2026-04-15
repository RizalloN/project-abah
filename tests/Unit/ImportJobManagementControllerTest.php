<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportJobManagementController;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportProgressService;
use App\Support\ManagedReportSnapshotRebuildCoordinator;
use App\Support\ManagedReportSnapshotRebuildStore;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
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

        $controller = new ImportJobManagementController(app(ManagedReportSnapshotRebuildCoordinator::class));
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

        $controller = new ImportJobManagementController(app(ManagedReportSnapshotRebuildCoordinator::class));
        $method = new \ReflectionMethod($controller, 'resolveQueueHealth');
        $method->setAccessible(true);

        $health = $method->invoke($controller);

        $this->assertSame('ok', $health['status']);
        $this->assertSame(1, $health['purged_reserved_snapshot_jobs']);
        $this->assertSame(0, $health['stale_reserved_snapshot_jobs']);
        $this->assertSame(0, DB::table('jobs')->count());
    }
}
