<?php

namespace Tests\Unit;

use App\Jobs\Middleware\DeferSnapshotJobsDuringImport;
use App\Jobs\SyncImportedReportJob;
use App\Services\Import\ImportProgressService;
use Illuminate\Support\Facades\Config;
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
        $importProgressService->shouldNotReceive('purgeStaleProcessingJobs');

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

    public function test_middleware_never_mutates_active_import_state(): void
    {
        Config::set('import.snapshot.defer_seconds', 60);

        $importProgressService = Mockery::mock(ImportProgressService::class);
        $importProgressService->shouldReceive('hasActiveProcessingJobs')
            ->once()
            ->andReturnTrue();
        $importProgressService->shouldNotReceive('purgeStaleProcessingJobs');
        $importProgressService->shouldNotReceive('markFailed');

        $middleware = new DeferSnapshotJobsDuringImport($importProgressService);
        $nextCalled = false;
        $job = new class {
            public ?int $releasedAfter = null;

            public function release(int $delay = 0): void
            {
                $this->releasedAfter = $delay;
            }
        };

        $result = $middleware->handle($job, function () use (&$nextCalled): string {
            $nextCalled = true;

            return 'continued';
        });

        $this->assertNull($result);
        $this->assertFalse($nextCalled);
        $this->assertSame(60, $job->releasedAfter);
    }
}
