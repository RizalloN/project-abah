<?php

namespace Tests\Unit;

use App\Jobs\Middleware\DeferSnapshotJobsDuringImport;
use App\Jobs\SyncImportedReportJob;
use App\Services\Import\ImportProgressService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class DeferSnapshotJobsDuringImportTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_middleware_releases_snapshot_job_when_import_is_active(): void
    {
        Config::set('import.snapshot.defer_seconds', 42);

        $importProgressService = Mockery::mock(ImportProgressService::class);
        $importProgressService->shouldReceive('hasActiveProcessingJobs')
            ->once()
            ->andReturnTrue();

        $middleware = new DeferSnapshotJobsDuringImport($importProgressService);

        $job = new class {
            public ?int $releasedAfter = null;

            public function release(int $delay = 0): void
            {
                $this->releasedAfter = $delay;
            }

            public function resolveName(): string
            {
                return 'tests/unit/snapshot-job';
            }
        };

        $nextCalled = false;

        $result = $middleware->handle($job, function () use (&$nextCalled) {
            $nextCalled = true;
        });

        $this->assertNull($result);
        $this->assertFalse($nextCalled);
        $this->assertSame(42, $job->releasedAfter);
    }

    public function test_sync_imported_report_job_keeps_import_deferral_middleware_even_without_table_name(): void
    {
        $job = new SyncImportedReportJob(123, null, null, 'unit-test');
        $middleware = $job->middleware();

        $this->assertNotEmpty(array_filter(
            $middleware,
            static fn ($item): bool => $item instanceof DeferSnapshotJobsDuringImport
        ));
    }

    public function test_stale_import_is_reconciled_before_snapshot_job_continues(): void
    {
        $importProgressService = Mockery::mock(ImportProgressService::class);
        $importProgressService->shouldReceive('hasActiveProcessingJobs')->once()->andReturnTrue();
        $importProgressService->shouldReceive('getStatusPayload')
            ->once()
            ->with(44)
            ->andReturn([
                'status' => 'completed',
                'total_rows' => 319332,
                'total_success' => 319332,
                'total_failed' => 0,
            ]);
        $importProgressService->shouldNotReceive('markFailed');

        $query = Mockery::mock();
        $query->shouldReceive('whereIn')->once()->with('status', ['staging', 'processing'])->andReturnSelf();
        $query->shouldReceive('where')->once()->with('updated_at', '<', Mockery::type(\DateTimeInterface::class))->andReturnSelf();
        $query->shouldReceive('orderByDesc')->once()->with('updated_at')->andReturnSelf();
        $query->shouldReceive('first')->once()->andReturn((object) [
            'id' => 44,
            'updated_at' => now()->subHours(5)->toDateTimeString(),
            'total_success' => 319332,
            'total_failed' => 0,
        ]);
        DB::shouldReceive('table')->once()->with('import_jobs')->andReturn($query);

        $middleware = new DeferSnapshotJobsDuringImport($importProgressService);
        $nextCalled = false;

        $result = $middleware->handle(new \stdClass(), function () use (&$nextCalled): string {
            $nextCalled = true;

            return 'continued';
        });

        $this->assertTrue($nextCalled);
        $this->assertSame('continued', $result);
    }
}
