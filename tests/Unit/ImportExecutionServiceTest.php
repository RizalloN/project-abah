<?php

namespace Tests\Unit;

use App\Jobs\RunImportJob;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportProgressService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class ImportExecutionServiceTest extends TestCase
{
    public function test_dispatch_only_enqueues_once_for_same_job(): void
    {
        Queue::fake();
        Cache::flush();

        $progressService = Mockery::mock(ImportProgressService::class);
        $progressService->shouldReceive('findJob')
            ->times(6)
            ->andReturn((object) [
                'id' => 55,
                'status' => 'queued',
                'updated_at' => now()->toDateTimeString(),
                'total_success' => 0,
                'total_failed' => 0,
                'total_files' => 100,
            ]);
        $progressService->shouldReceive('purgeStaleQueuedJobs')->twice()->andReturn(0);
        $progressService->shouldReceive('markQueued')->once();

        $service = new ImportExecutionService($progressService);

        $service->dispatch(55);
        $service->dispatch(55);

        Queue::assertPushed(RunImportJob::class, 1);
    }

    public function test_dispatch_requeues_stale_queued_job_when_marker_exists(): void
    {
        Queue::fake();
        Cache::flush();

        $jobId = 88;
        Cache::put('import_excel_dispatched_job_' . $jobId, true, now()->addHours(6));

        $progressService = Mockery::mock(ImportProgressService::class);
        $progressService->shouldReceive('findJob')
            ->times(3)
            ->andReturn((object) [
                'id' => $jobId,
                'status' => 'queued',
                'updated_at' => Carbon::now()->subMinutes(30)->toDateTimeString(),
                'total_success' => 0,
                'total_failed' => 0,
                'total_files' => 100,
            ]);
        $progressService->shouldReceive('purgeStaleQueuedJobs')->once()->andReturn(1);
        $progressService->shouldReceive('markQueued')->once();

        $service = new ImportExecutionService($progressService);

        $result = $service->dispatch($jobId);

        $this->assertTrue($result);
        Queue::assertPushed(RunImportJob::class, 1);
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
        $progressService->shouldReceive('findJob')
            ->once()
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
}
