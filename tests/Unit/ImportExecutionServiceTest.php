<?php

namespace Tests\Unit;

use App\Jobs\RunImportJob;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportProgressService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class ImportExecutionServiceTest extends TestCase
{
    public function test_dispatch_only_enqueues_once_for_same_job(): void
    {
        Bus::fake();
        Cache::flush();

        $lock = Mockery::mock();
        $lock->shouldReceive('get')->twice()->andReturn(true);
        $lock->shouldReceive('release')->twice();
        Cache::shouldReceive('lock')
            ->twice()
            ->with('import_excel_dispatch_job_55', 30)
            ->andReturn($lock);
        Cache::shouldReceive('has')
            ->twice()
            ->with('import_excel_dispatched_job_55')
            ->andReturn(false, true);
        Cache::shouldReceive('put')
            ->once()
            ->with('import_excel_dispatched_job_55', true, Mockery::type(\Illuminate\Support\Carbon::class))
            ->andReturnTrue();
        Cache::shouldReceive('forget')->never();

        $progressService = Mockery::mock(ImportProgressService::class);
        $progressService->shouldReceive('findJob')
            ->times(8)
            ->andReturn((object) [
                'id' => 55,
                'id_report' => 12,
                'status' => 'queued',
                'updated_at' => now()->toDateTimeString(),
                'total_success' => 0,
                'total_failed' => 0,
                'total_files' => 100,
            ]);
        $progressService->shouldReceive('getJobState')
            ->twice()
            ->andReturn([
                'params' => [
                    'table_name' => 'performance_pis_per_produk',
                ],
            ]);
        $progressService->shouldReceive('purgeStaleQueuedJobs')->twice()->andReturn(0);
        $progressService->shouldReceive('purgeStaleProcessingJobs')->twice()->andReturn(0);
        $progressService->shouldReceive('purgeQueuedImportJobsForQueues')
            ->twice()
            ->with(
                Mockery::on(static function (array $queues): bool {
                    return $queues === ['imports-high'];
                }),
                10
            )
            ->andReturn(0);
        $progressService->shouldReceive('cleanupQueuedImportJobRowsForJob')->once()->with(55);
        $progressService->shouldReceive('markQueued')->once();

        $service = new ImportExecutionService($progressService);

        $service->dispatch(55);
        $service->dispatch(55);

        Bus::assertDispatched(RunImportJob::class, function (RunImportJob $job): bool {
            return $job->jobId === 55 && $job->queue === 'imports-high';
        });
    }

    public function test_dispatch_requeues_stale_queued_job_when_marker_exists(): void
    {
        Bus::fake();
        Cache::flush();

        $jobId = 88;

        $lock = Mockery::mock();
        $lock->shouldReceive('get')->once()->andReturn(true);
        $lock->shouldReceive('release')->once();
        Cache::shouldReceive('lock')
            ->once()
            ->with('import_excel_dispatch_job_' . $jobId, 30)
            ->andReturn($lock);
        Cache::shouldReceive('has')
            ->once()
            ->with('import_excel_dispatched_job_' . $jobId)
            ->andReturn(true);
        Cache::shouldReceive('put')
            ->once()
            ->with('import_excel_dispatched_job_' . $jobId, true, Mockery::type(\Illuminate\Support\Carbon::class))
            ->andReturnTrue();
        Cache::shouldReceive('forget')->never();

        $progressService = Mockery::mock(ImportProgressService::class);
        $progressService->shouldReceive('findJob')
            ->times(4)
            ->andReturn((object) [
                'id' => $jobId,
                'id_report' => 12,
                'status' => 'queued',
                'updated_at' => Carbon::now()->subMinutes(30)->toDateTimeString(),
                'total_success' => 0,
                'total_failed' => 0,
                'total_files' => 100,
            ]);
        $progressService->shouldReceive('getJobState')
            ->once()
            ->andReturn([
                'params' => [
                    'table_name' => 'performance_pis_per_produk',
                ],
            ]);
        $progressService->shouldReceive('purgeStaleQueuedJobs')->once()->andReturn(1);
        $progressService->shouldReceive('purgeStaleProcessingJobs')->once()->andReturn(0);
        $progressService->shouldReceive('purgeQueuedImportJobsForQueues')
            ->once()
            ->with(
                Mockery::on(static function (array $queues): bool {
                    return $queues === ['imports-high'];
                }),
                10
            )
            ->andReturn(0);
        $progressService->shouldReceive('cleanupQueuedImportJobRowsForJob')->once()->with($jobId);
        $progressService->shouldReceive('markQueued')->once();

        $service = new ImportExecutionService($progressService);

        $result = $service->dispatch($jobId);

        $this->assertTrue($result);
        Bus::assertDispatched(RunImportJob::class, function (RunImportJob $job) use ($jobId): bool {
            return $job->jobId === $jobId && $job->queue === 'imports-high';
        });
    }

    public function test_dispatch_routes_daily_loan_jobs_to_priority_queue(): void
    {
        Bus::fake();
        Cache::flush();

        $jobId = 108;

        $lock = Mockery::mock();
        $lock->shouldReceive('get')->once()->andReturn(true);
        $lock->shouldReceive('release')->once();
        Cache::shouldReceive('lock')
            ->once()
            ->with('import_excel_dispatch_job_' . $jobId, 30)
            ->andReturn($lock);
        Cache::shouldReceive('has')
            ->once()
            ->with('import_excel_dispatched_job_' . $jobId)
            ->andReturn(false);
        Cache::shouldReceive('put')
            ->once()
            ->with('import_excel_dispatched_job_' . $jobId, true, Mockery::type(\Illuminate\Support\Carbon::class))
            ->andReturnTrue();
        Cache::shouldReceive('forget')->never();

        $progressService = Mockery::mock(ImportProgressService::class);
        $progressService->shouldReceive('findJob')
            ->times(4)
            ->andReturn((object) [
                'id' => $jobId,
                'id_report' => 8,
                'status' => 'queued',
                'updated_at' => now()->toDateTimeString(),
                'total_success' => 0,
                'total_failed' => 0,
                'total_files' => 250,
            ]);
        $progressService->shouldReceive('getJobState')
            ->once()
            ->andReturn([
                'params' => [
                    'table_name' => 'daily_loan_dinamis',
                ],
            ]);
        $progressService->shouldReceive('purgeStaleQueuedJobs')->once()->andReturn(0);
        $progressService->shouldReceive('purgeStaleProcessingJobs')->once()->andReturn(0);
        $progressService->shouldReceive('purgeQueuedImportJobsForQueues')
            ->once()
            ->with(
                Mockery::on(static function (array $queues): bool {
                    return $queues === ['imports-daily-loan', 'imports-high'];
                }),
                10
            )
            ->andReturn(0);
        $progressService->shouldReceive('cleanupQueuedImportJobRowsForJob')->once()->with($jobId);
        $progressService->shouldReceive('markQueued')->once();

        $service = new ImportExecutionService($progressService);

        $result = $service->dispatch($jobId);

        $this->assertTrue($result);
        Bus::assertDispatched(RunImportJob::class, function (RunImportJob $job) use ($jobId): bool {
            return $job->jobId === $jobId && $job->queue === 'imports-daily-loan';
        });
    }

    public function test_run_marks_stale_queued_job_failed_when_execution_lock_is_unavailable(): void
    {
        Cache::flush();

        $lock = Mockery::mock();
        $lock->shouldReceive('get')->once()->andReturn(false);

        Cache::shouldReceive('lock')
            ->once()
            ->with('import_excel_execute_job_99', 7200)
            ->andReturn($lock);
        Cache::shouldReceive('forget')
            ->once()
            ->with('import_excel_dispatched_job_99');

        $progressService = Mockery::mock(ImportProgressService::class);
        $progressService->shouldReceive('getJobState')
            ->once()
            ->with(99)
            ->andReturn([
                'params' => [
                    'file_path' => 'excel_imports/dummy.csv',
                    'table_name' => 'daily_loan_dinamis',
                    'header_index' => 0,
                    'total_rows' => 100,
                ],
                'headers' => ['PERIODE'],
            ]);
        $progressService->shouldReceive('purgeStaleProcessingJobs')->once()->andReturn(0);
        $progressService->shouldReceive('isTerminationRequested')->once()->with(99)->andReturnFalse();
        $progressService->shouldReceive('findJob')
            ->twice()
            ->with(99)
            ->andReturn((object) [
                'id' => 99,
                'status' => 'queued',
                'updated_at' => Carbon::now()->subMinutes(30)->toDateTimeString(),
                'total_success' => 0,
                'total_failed' => 0,
                'total_files' => 100,
            ]);
        $progressService->shouldReceive('markFailed')
            ->once()
            ->with(
                99,
                Mockery::on(static fn (string $message): bool => str_contains($message, 'terlalu lama')),
                0,
                0,
                'failed'
            );

        $service = new ImportExecutionService($progressService);
        $service->run(99);
    }

    public function test_stream_status_aborts_stale_queued_job_even_when_payload_does_not_change(): void
    {
        Cache::flush();
        Config::set('import.queue.inline_fallback_grace_seconds', 999);

        $progressService = Mockery::mock(ImportProgressService::class);
        $progressService->shouldReceive('getStatusPayload')
            ->once()
            ->with(101)
            ->andReturn([
                'status' => 'queued',
                'job_id' => 101,
                'report_id' => 8,
                'file_name' => 'stale.csv',
                'total_rows' => 100,
                'processed_rows' => 0,
                'total_success' => 0,
                'total_failed' => 0,
                'percent' => 1,
                'message' => 'Import sedang diproses.',
                'updated_at' => now()->toIso8601String(),
                'queued_for_seconds' => 3600,
                'is_stale_queue' => true,
            ]);
        $progressService->shouldReceive('markFailed')
            ->once()
            ->with(
                101,
                Mockery::on(static fn (string $message): bool => str_contains($message, 'terlalu lama')),
                0,
                0,
                'failed'
            );

        $service = new ImportExecutionService($progressService);
        $response = $service->streamStatus(new \Illuminate\Http\Request(), 101);
        $response->sendContent();
    }

    public function test_inline_fallback_grace_seconds_uses_import_config_and_defaults_to_zero(): void
    {
        $progressService = Mockery::mock(ImportProgressService::class);
        $service = new ImportExecutionService($progressService);
        $method = new \ReflectionMethod($service, 'inlineFallbackGraceSeconds');
        $method->setAccessible(true);

        Config::set('import.queue.inline_fallback_grace_seconds', null);
        $this->assertSame(0, $method->invoke($service));

        Config::set('import.queue.inline_fallback_grace_seconds', 7);
        $this->assertSame(7, $method->invoke($service));
    }
}
