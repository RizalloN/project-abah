<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportIndexController;
use App\Jobs\RunManagedReportSnapshotRebuildJob;
use App\Jobs\WarmReportCacheJob;
use App\Support\DashboardHarianSnapshotService;
use App\Support\ManagedReportSnapshotRebuildStore;
use App\Support\PartitionMaintenanceService;
use App\Support\ReportDataSyncService;
use App\Support\ReportSnapshotBuilder;
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
}
