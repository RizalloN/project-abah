<?php

namespace Tests\Unit;

use App\Jobs\RunImportJob;
use App\Http\Controllers\Import\ImportExcelController;
use App\Http\Controllers\Import\ImportReportPhController;
use App\Http\Controllers\Import\ImportSimpananMultiPnCsvController;
use App\Jobs\SyncImportedReportJob;
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
            ->times(4)
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
            ->once()
            ->andReturn([
                'params' => [
                    'table_name' => 'performance_pis_per_produk',
                ],
            ]);
        $progressService->shouldReceive('cleanupQueuedImportJobRowsForJob')->once()->with(55);
        $progressService->shouldReceive('markQueued')->once();

        $service = new ImportExecutionService($progressService);

        $service->dispatch(55);
        $service->dispatch(55);

        Bus::assertDispatched(RunImportJob::class, function (RunImportJob $job): bool {
            return $job->jobId === 55
                && $job->connection === 'database'
                && $job->queue === 'imports-high';
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
            ->twice()
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
        $progressService->shouldReceive('cleanupQueuedImportJobRowsForJob')->once()->with($jobId);
        $progressService->shouldReceive('markQueued')->once();

        $service = new ImportExecutionService($progressService);

        $result = $service->dispatch($jobId);

        $this->assertTrue($result);
        Bus::assertDispatched(RunImportJob::class, function (RunImportJob $job) use ($jobId): bool {
            return $job->jobId === $jobId
                && $job->connection === 'database'
                && $job->queue === 'imports-high';
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
            ->twice()
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
        $progressService->shouldReceive('cleanupQueuedImportJobRowsForJob')->once()->with($jobId);
        $progressService->shouldReceive('markQueued')->once();

        $service = new ImportExecutionService($progressService);

        $result = $service->dispatch($jobId);

        $this->assertTrue($result);
        Bus::assertDispatched(RunImportJob::class, function (RunImportJob $job) use ($jobId): bool {
            return $job->jobId === $jobId
                && $job->connection === 'database'
                && $job->queue === 'imports-daily-loan';
        });
    }

    public function test_dispatch_queues_simpanan_multipn_csv_on_import_worker(): void
    {
        Bus::fake();
        Cache::flush();

        $jobId = 209;

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
            ->with(
                'import_excel_dispatched_job_' . $jobId,
                true,
                Mockery::type(\DateTimeInterface::class)
            );

        $progressService = Mockery::mock(ImportProgressService::class);
        $progressService->shouldReceive('findJob')
            ->twice()
            ->with($jobId)
            ->andReturn((object) [
                'id' => $jobId,
                'id_report' => 0,
                'status' => 'queued',
                'updated_at' => now()->toDateTimeString(),
                'total_success' => 0,
                'total_failed' => 0,
                'total_files' => 100,
            ]);
        $progressService->shouldReceive('getJobState')
            ->once()
            ->with($jobId)
            ->andReturn([
                'params' => [
                    'table_name' => 'simpanan_multipn',
                    'controller' => ImportSimpananMultiPnCsvController::class,
                ],
            ]);
        $progressService->shouldReceive('cleanupQueuedImportJobRowsForJob')->once()->with($jobId);
        $progressService->shouldReceive('markQueued')
            ->once()
            ->with($jobId, Mockery::on(static function (array $payload): bool {
                return ($payload['status'] ?? null) === 'queued'
                    && ($payload['queue'] ?? null) === 'imports-high';
            }));

        $service = new ImportExecutionService($progressService);

        $this->assertTrue($service->dispatch($jobId));
        Bus::assertDispatched(RunImportJob::class, function (RunImportJob $job) use ($jobId): bool {
            return $job->jobId === $jobId
                && $job->connection === 'database'
                && $job->queue === 'imports-high';
        });
    }

    public function test_dispatch_recovers_zero_progress_stale_processing_job_before_queueing(): void
    {
        Bus::fake();
        Cache::flush();

        $jobId = 144;
        $staleProcessingJob = (object) [
            'id' => $jobId,
            'id_report' => 8,
            'status' => 'processing',
            'updated_at' => Carbon::now()->subMinutes(20)->toDateTimeString(),
            'total_success' => 0,
            'total_failed' => 0,
            'total_files' => 125,
        ];
        $queuedJob = (object) [
            'id' => $jobId,
            'id_report' => 8,
            'status' => 'queued',
            'updated_at' => Carbon::now()->toDateTimeString(),
            'total_success' => 0,
            'total_failed' => 0,
            'total_files' => 125,
        ];

        $progressService = Mockery::mock(ImportProgressService::class);
        $progressService->shouldReceive('findJob')
            ->times(3)
            ->with($jobId)
            ->andReturn($staleProcessingJob, $queuedJob, $queuedJob);
        $progressService->shouldReceive('getCachedProgress')
            ->once()
            ->with($jobId)
            ->andReturn([
                'updated_at' => Carbon::now()->subMinutes(20)->toIso8601String(),
            ]);
        $progressService->shouldReceive('updateJob')
            ->once()
            ->with(
                $jobId,
                Mockery::on(static fn (array $attributes): bool => ($attributes['status'] ?? null) === 'queued'
                    && ($attributes['total_success'] ?? null) === 0
                    && ($attributes['total_failed'] ?? null) === 0),
                Mockery::on(static fn (array $payload): bool => ($payload['status'] ?? null) === 'queued'
                    && str_contains((string) ($payload['message'] ?? ''), 'melanjutkan ulang otomatis'))
            );
        $progressService->shouldReceive('getJobState')
            ->once()
            ->with($jobId)
            ->andReturn([
                'params' => [
                    'table_name' => 'daily_loan_dinamis',
                ],
            ]);
        $progressService->shouldReceive('cleanupQueuedImportJobRowsForJob')->once()->with($jobId);
        $progressService->shouldReceive('markQueued')->once();

        $service = new ImportExecutionService($progressService);

        $result = $service->dispatch($jobId);

        $this->assertTrue($result);
        Bus::assertDispatched(RunImportJob::class, function (RunImportJob $job) use ($jobId): bool {
            return $job->jobId === $jobId
                && $job->connection === 'database'
                && $job->queue === 'imports-daily-loan';
        });
    }

    public function test_dispatch_recovers_zero_progress_stale_ssa_simpanan_job_before_queueing(): void
    {
        Bus::fake();
        Cache::flush();

        $jobId = 146;
        $staleProcessingJob = (object) [
            'id' => $jobId,
            'id_report' => 17,
            'status' => 'processing',
            'updated_at' => Carbon::now()->subMinutes(20)->toDateTimeString(),
            'total_success' => 0,
            'total_failed' => 0,
            'total_files' => 1067,
        ];
        $queuedJob = (object) [
            'id' => $jobId,
            'id_report' => 17,
            'status' => 'queued',
            'updated_at' => Carbon::now()->toDateTimeString(),
            'total_success' => 0,
            'total_failed' => 0,
            'total_files' => 1067,
        ];

        $progressService = Mockery::mock(ImportProgressService::class);
        $progressService->shouldReceive('findJob')
            ->times(3)
            ->with($jobId)
            ->andReturn($staleProcessingJob, $queuedJob, $queuedJob);
        $progressService->shouldReceive('getCachedProgress')
            ->once()
            ->with($jobId)
            ->andReturn([
                'updated_at' => Carbon::now()->subMinutes(20)->toIso8601String(),
            ]);
        $progressService->shouldReceive('updateJob')
            ->once()
            ->with(
                $jobId,
                Mockery::on(static fn (array $attributes): bool => ($attributes['status'] ?? null) === 'queued'),
                Mockery::on(static fn (array $payload): bool => ($payload['status'] ?? null) === 'queued'
                    && ($payload['total_rows'] ?? null) === 1067)
            );
        $progressService->shouldReceive('getJobState')
            ->twice()
            ->with($jobId)
            ->andReturn([
                'params' => [
                    'table_name' => 'ssa_simpanan',
                ],
            ]);
        $progressService->shouldReceive('cleanupQueuedImportJobRowsForJob')->once()->with($jobId);
        $progressService->shouldReceive('markQueued')->once();

        $service = new ImportExecutionService($progressService);

        $result = $service->dispatch($jobId);

        $this->assertTrue($result);
        Bus::assertDispatched(RunImportJob::class, function (RunImportJob $job) use ($jobId): bool {
            return $job->jobId === $jobId
                && $job->connection === 'database'
                && $job->queue === 'imports-high';
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

    public function test_run_executes_simpanan_multipn_through_dedicated_worker_request(): void
    {
        Cache::flush();

        $jobId = 210;

        Cache::shouldReceive('lock')->never();
        Cache::shouldReceive('forget')
            ->once()
            ->with('import_excel_dispatched_job_' . $jobId);

        $progressService = Mockery::mock(ImportProgressService::class);
        $progressService->shouldReceive('purgeStaleProcessingJobs')->once()->andReturn(0);
        $progressService->shouldReceive('isTerminationRequested')->once()->with($jobId)->andReturnFalse();
        $queuedJob = (object) [
            'id' => $jobId,
            'id_report' => 0,
            'status' => 'queued',
            'updated_at' => now()->toDateTimeString(),
            'total_success' => 0,
            'total_failed' => 0,
            'total_files' => 100,
        ];
        $failedJob = clone $queuedJob;
        $failedJob->status = 'failed';

        $progressService->shouldReceive('findJob')
            ->twice()
            ->with($jobId)
            ->andReturn($queuedJob, $failedJob);
        $progressService->shouldReceive('getJobState')
            ->once()
            ->with($jobId)
            ->andReturn([
                'params' => [
                    'job_id' => $jobId,
                    'file_path' => 'excel_imports/simpanan.csv',
                    'table_name' => 'simpanan_multipn',
                    'controller' => ImportSimpananMultiPnCsvController::class,
                ],
                'headers' => ['POSISI', 'KANTOR_CABANG', 'NO_REKENING'],
            ]);
        $progressService->shouldReceive('markProcessing')->never();
        $progressService->shouldReceive('markFailed')->never();

        $controller = Mockery::mock(ImportSimpananMultiPnCsvController::class);
        $controller->shouldReceive('processImportStream')
            ->once()
            ->with(Mockery::on(static function ($request) use ($jobId): bool {
                return (int) $request->query('job_id') === $jobId
                    && $request->attributes->getBoolean('import_worker_execution');
            }))
            ->andReturn(response()->stream(static function (): void {}));
        $this->app->instance(ImportSimpananMultiPnCsvController::class, $controller);

        $service = new ImportExecutionService($progressService);
        $service->run($jobId);
    }

    public function test_run_recovers_zero_progress_stale_processing_job_and_executes_import(): void
    {
        Bus::fake();
        Cache::flush();

        $jobId = 145;
        $staleProcessingJob = (object) [
            'id' => $jobId,
            'id_report' => 8,
            'status' => 'processing',
            'updated_at' => Carbon::now()->subMinutes(20)->toDateTimeString(),
            'total_success' => 0,
            'total_failed' => 0,
            'total_files' => 10,
        ];
        $queuedJob = (object) [
            'id' => $jobId,
            'id_report' => 8,
            'status' => 'queued',
            'updated_at' => Carbon::now()->toDateTimeString(),
            'total_success' => 0,
            'total_failed' => 0,
            'total_files' => 10,
        ];

        $progressService = Mockery::mock(ImportProgressService::class);
        $progressService->shouldReceive('purgeStaleProcessingJobs')->once()->andReturn(0);
        $progressService->shouldReceive('isTerminationRequested')->zeroOrMoreTimes()->with($jobId)->andReturnFalse();
        $progressService->shouldReceive('findJob')
            ->times(4)
            ->with($jobId)
            ->andReturn($staleProcessingJob, $queuedJob, $queuedJob, $queuedJob);
        $progressService->shouldReceive('getCachedProgress')
            ->once()
            ->with($jobId)
            ->andReturn([
                'updated_at' => Carbon::now()->subMinutes(20)->toIso8601String(),
            ]);
        $progressService->shouldReceive('updateJob')->once();
        $progressService->shouldReceive('getJobState')
            ->once()
            ->with($jobId)
            ->andReturn([
                'params' => [
                    'job_id' => $jobId,
                    'file_path' => 'excel_imports/daily.csv',
                    'table_name' => 'daily_loan_dinamis',
                    'header_index' => 0,
                    'total_rows' => 10,
                ],
                'headers' => ['PERIODE', 'NOMOR_REKENING1', 'BAKI_DEBET1'],
            ]);
        $progressService->shouldReceive('markProcessing')->once();
        $progressService->shouldReceive('markCompleted')
            ->once()
            ->with(
                $jobId,
                10,
                0,
                10,
                Mockery::on(static fn (array $payload): bool => ($payload['status'] ?? null) === 'completed'
                    && ($payload['percent'] ?? null) === 100)
            );

        $controller = Mockery::mock(ImportExcelController::class);
        $controller->shouldReceive('executeQueuedImport')
            ->once()
            ->andReturn([
                'status' => 'completed',
                'total_success' => 10,
                'total_failed' => 0,
                'total_rows' => 10,
            ]);
        $this->app->instance(ImportExcelController::class, $controller);

        $service = new ImportExecutionService($progressService);
        $service->run($jobId);

        Bus::assertDispatched(SyncImportedReportJob::class);
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

    public function test_stream_status_uses_daily_loan_specific_inline_fallback_message(): void
    {
        $progressService = Mockery::mock(ImportProgressService::class);
        $progressService->shouldReceive('getJobState')
            ->once()
            ->with(101)
            ->andReturn([
                'params' => [
                    'table_name' => 'daily_loan_dinamis',
                ],
            ]);
        $service = new ImportExecutionService($progressService);
        $method = new \ReflectionMethod($service, 'resolveInlineFallbackMessage');
        $method->setAccessible(true);

        $message = $method->invoke(
            $service,
            101,
            [
                'phase' => 'polars',
                'message' => 'Fase Polars dimulai. Menyiapkan import fresh.',
            ]
        );

        $this->assertSame('Menyiapkan sanitasi CSV Daily Loan...', $message);
    }

    public function test_processing_zero_progress_ssa_simpanan_job_can_use_inline_fallback_after_stale_window(): void
    {
        Cache::flush();

        $jobId = 147;
        $staleTimestamp = Carbon::now()->subMinutes(10);

        $progressService = Mockery::mock(ImportProgressService::class);
        $progressService->shouldReceive('getJobState')
            ->twice()
            ->with($jobId)
            ->andReturn([
                'params' => [
                    'table_name' => 'ssa_simpanan',
                ],
            ]);
        $progressService->shouldReceive('findJob')
            ->once()
            ->with($jobId)
            ->andReturn((object) [
                'id' => $jobId,
                'id_report' => 17,
                'status' => 'processing',
                'updated_at' => $staleTimestamp->toDateTimeString(),
                'total_success' => 0,
                'total_failed' => 0,
                'total_files' => 0,
            ]);
        $progressService->shouldReceive('getCachedProgress')
            ->once()
            ->with($jobId)
            ->andReturn([
                'updated_at' => $staleTimestamp->toIso8601String(),
            ]);

        $service = new ImportExecutionService($progressService);
        $method = new \ReflectionMethod($service, 'shouldRunInlineFallback');
        $method->setAccessible(true);

        $shouldFallback = $method->invoke($service, [
            'status' => 'processing',
            'job_id' => $jobId,
            'report_id' => 17,
            'percent' => 8,
            'processed_rows' => 0,
            'total_success' => 0,
            'total_failed' => 0,
            'updated_at' => $staleTimestamp->toIso8601String(),
        ], time(), false);

        $this->assertTrue($shouldFallback);
    }

    public function test_lw325_jobs_never_fall_back_to_generic_import_controller(): void
    {
        $progressService = Mockery::mock(ImportProgressService::class);
        $service = new ImportExecutionService($progressService);
        $method = new \ReflectionMethod($service, 'resolveControllerClass');
        $method->setAccessible(true);

        $job = (object) [
            'job_context' => json_encode([
                'table_name' => 'lw325_ph',
                'controller' => 'Missing\\Controller',
            ]),
        ];

        $this->assertSame(ImportReportPhController::class, $method->invoke($service, $job));
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

    public function test_inline_fallback_does_not_run_while_queue_still_owns_job(): void
    {
        $progressService = Mockery::mock(ImportProgressService::class);
        $service = new ImportExecutionService($progressService);
        $method = new \ReflectionMethod($service, 'shouldRunInlineFallback');
        $method->setAccessible(true);

        $shouldFallback = $method->invoke($service, [
            'status' => 'queued',
            'job_id' => 172,
            'queue_present' => true,
            'queue_reserved' => true,
        ], time() - 60, false);

        $this->assertFalse($shouldFallback);
    }

    public function test_simpanan_multipn_is_not_recovered_by_generic_zero_progress_recovery(): void
    {
        $jobId = 196;
        $progressService = Mockery::mock(ImportProgressService::class);
        $progressService->shouldReceive('getJobState')
            ->once()
            ->with($jobId)
            ->andReturn([
                'params' => [
                    'table_name' => 'simpanan_multipn',
                ],
            ]);

        $service = new ImportExecutionService($progressService);
        $method = new \ReflectionMethod($service, 'isRecoverableZeroProgressImportJob');
        $method->setAccessible(true);

        $recoverable = $method->invoke($service, $jobId, (object) [
            'id' => $jobId,
            'id_report' => 9,
            'status' => 'processing',
            'total_success' => 0,
            'total_failed' => 0,
        ]);

        $this->assertFalse($recoverable);
    }
}
