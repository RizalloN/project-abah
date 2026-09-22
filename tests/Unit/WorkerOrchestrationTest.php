<?php

namespace Tests\Unit;

use App\Jobs\EnsureImportedSnapshotsFreshJob;
use App\Jobs\Middleware\SerializeImportByTable;
use App\Jobs\ProcessSnapshotDirtyPeriodJob;
use App\Jobs\RunImportJob;
use App\Jobs\RunLoadDataJob;
use App\Jobs\SyncImportedReportJob;
use App\Services\Import\ImportProgressService;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class WorkerOrchestrationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_same_table_import_is_released_while_another_worker_holds_the_table_lock(): void
    {
        config(['import.cache_store' => 'array']);

        $progress = Mockery::mock(ImportProgressService::class);
        $progress->shouldReceive('getJobState')->once()->with(140)->andReturn([
            'params' => ['table_name' => 'daily_loan_dinamis'],
        ]);

        $heldLock = Cache::store('array')->lock('import:table-execution:daily_loan_dinamis', 60);
        $this->assertTrue($heldLock->get());

        $job = new class {
            public int $jobId = 140;
            public ?int $releasedAfter = null;

            public function release(int $delay = 0): void
            {
                $this->releasedAfter = $delay;
            }
        };

        $nextCalled = false;
        try {
            (new SerializeImportByTable($progress, 37))->handle($job, function () use (&$nextCalled): void {
                $nextCalled = true;
            });
        } finally {
            $heldLock->release();
        }

        $this->assertFalse($nextCalled);
        $this->assertSame(37, $job->releasedAfter);
    }

    public function test_import_job_allows_lock_deferral_but_only_one_execution_exception(): void
    {
        $job = new RunImportJob(140);

        $this->assertGreaterThan(1, $job->tries);
        $this->assertSame(1, $job->maxExceptions);
        $this->assertInstanceOf(SerializeImportByTable::class, $job->middleware()[0]);
    }

    public function test_later_import_waits_until_earlier_pipeline_for_same_table_is_terminal(): void
    {
        config(['import.cache_store' => 'array']);

        $progress = Mockery::mock(ImportProgressService::class);
        $progress->shouldReceive('getJobState')->once()->with(141)->andReturn([
            'params' => ['table_name' => 'daily_loan_dinamis'],
        ]);
        $progress->shouldReceive('hasActiveProcessingJobsForTable')
            ->once()
            ->with('daily_loan_dinamis', 141)
            ->andReturnTrue();

        $job = new class {
            public int $jobId = 141;
            public ?int $releasedAfter = null;

            public function release(int $delay = 0): void
            {
                $this->releasedAfter = $delay;
            }
        };
        $nextCalled = false;

        (new SerializeImportByTable($progress, 45))->handle($job, function () use (&$nextCalled): void {
            $nextCalled = true;
        });

        $this->assertFalse($nextCalled);
        $this->assertSame(45, $job->releasedAfter);
    }

    public function test_final_load_job_uses_the_same_table_serialization_contract(): void
    {
        $job = new RunLoadDataJob(140, 'staging.csv', 'daily_loan_dinamis', ['periode']);

        $this->assertGreaterThan(1, $job->tries);
        $this->assertSame(1, $job->maxExceptions);
        $this->assertInstanceOf(SerializeImportByTable::class, $job->middleware()[0]);
    }

    public function test_dirty_freshness_and_sync_jobs_share_the_same_source_period_lock(): void
    {
        $ensure = new EnsureImportedSnapshotsFreshJob('daily_loan_dinamis', '2026-09-19', 'unit-test');
        $dirty = new ProcessSnapshotDirtyPeriodJob([
            'source_table' => 'daily_loan_dinamis',
            'period_key' => '2026-09-19',
        ]);
        $sync = new SyncImportedReportJob(140, 'daily_loan_dinamis', '2026-09-19', 'unit-test');

        $expected = 'laravel-queue-overlap:snapshot:source-period:daily_loan_dinamis:2026-09-19';

        $this->assertContains($expected, $this->overlapKeys($ensure));
        $this->assertContains($expected, $this->overlapKeys($dirty));
        $this->assertContains($expected, $this->overlapKeys($sync));
    }

    /** @return array<int, string> */
    private function overlapKeys(object $job): array
    {
        return array_values(array_map(
            static fn (WithoutOverlapping $middleware): string => $middleware->getLockKey($job),
            array_filter(
                $job->middleware(),
                static fn ($middleware): bool => $middleware instanceof WithoutOverlapping
            )
        ));
    }
}
