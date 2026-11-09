<?php

namespace Tests\Unit;

use App\Jobs\RunImportJob;
use App\Services\Import\ImmediateImportRunner;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportProgressService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class ImmediateImportRunnerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'import.cache_store' => 'array']);
        Bus::fake();
    }

    public function test_duplicate_runner_cannot_stage_or_write_again(): void
    {
        $lock = Cache::lock('import:immediate-execution:15', 21600);
        $this->assertTrue($lock->get());
        $progress = Mockery::mock(ImportProgressService::class);
        $execution = Mockery::mock(ImportExecutionService::class);
        $execution->shouldNotReceive('run');

        try {
            $this->assertSame('already_running', app(ImmediateImportRunner::class)->run(15, $progress, $execution));
            Bus::assertNotDispatched(RunImportJob::class);
        } finally {
            $lock->release();
        }
    }

    public function test_busy_table_restores_the_correct_import_queue_without_running(): void
    {
        $lock = Cache::lock('import:table-execution:daily_loan_dinamis', 21600);
        $this->assertTrue($lock->get());
        $progress = Mockery::mock(ImportProgressService::class);
        $progress->shouldReceive('getJobState')->with(15)->andReturn(['params' => ['table_name' => 'daily_loan_dinamis']]);
        $progress->shouldReceive('findJob')->with(15)->once()->andReturn((object) ['id_report' => 8, 'status' => 'queued']);
        $execution = Mockery::mock(ImportExecutionService::class);
        $execution->shouldNotReceive('run');

        try {
            $this->assertSame('deferred', app(ImmediateImportRunner::class)->run(15, $progress, $execution));
            Bus::assertDispatched(RunImportJob::class, fn ($job) => $job->jobId === 15
                && $job->queue === 'imports-daily-loan' && $job->connection === 'database');
        } finally {
            $lock->release();
        }
    }

    public function test_locks_release_after_execution_fails(): void
    {
        $progress = Mockery::mock(ImportProgressService::class);
        $progress->shouldReceive('getJobState')->with(15)->andReturn(['params' => ['table_name' => 'simpanan_multipn']]);
        $progress->shouldReceive('hasActiveProcessingJobsForTable')->with('simpanan_multipn', 15)->andReturnFalse();
        $execution = Mockery::mock(ImportExecutionService::class);
        $execution->shouldReceive('run')->once()->with(15)->andThrow(new \RuntimeException('Write failed'));

        try {
            app(ImmediateImportRunner::class)->run(15, $progress, $execution);
            $this->fail('The execution error must propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Write failed', $e->getMessage());
        }
        foreach (['import:immediate-execution:15', 'import:table-execution:simpanan_multipn'] as $key) {
            $lock = Cache::lock($key, 10);
            $this->assertTrue($lock->get());
            $lock->release();
        }
    }
}
