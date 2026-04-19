<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use App\Services\Import\ExcelImportJobService;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportProgressService;
use App\Services\Import\MySqlBulkLoadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class ImportExcelQueuedFallbackTest extends TestCase
{
    protected function tearDown(): void
    {
        Storage::disk('local')->delete('testing/queued_fallback_daily_loan.csv');
        @rmdir(storage_path('app/private/testing'));
        Mockery::close();

        parent::tearDown();
    }

    public function test_queued_csv_import_uses_staged_fallback_when_native_bulk_load_is_unavailable(): void
    {
        $bulkLoadService = Mockery::mock(MySqlBulkLoadService::class);
        $bulkLoadService->shouldReceive('supportsNativeBulkLoad')->andReturn(false);
        $bulkLoadService->shouldReceive('assertTransactionalTable')->once();
        $this->app->instance(MySqlBulkLoadService::class, $bulkLoadService);

        $relativePath = 'testing/queued_fallback_daily_loan.csv';
        Storage::disk('local')->put($relativePath, "PERIODE,NOMOR_REKENING1,BAKI_DEBET1\n2026-04-04,123,1000\n");

        $jobObject = (object) [
            'id' => 77,
            'status' => 'completed',
            'total_success' => 1,
            'total_failed' => 0,
            'total_files' => 1,
        ];

        $progressService = Mockery::mock(ImportProgressService::class);
        $progressService->shouldReceive('findJob')->andReturn($jobObject, $jobObject, $jobObject);
        $this->app->instance(ImportProgressService::class, $progressService);

        $controller = new class extends ImportExcelController {
            public bool $stagedFallbackCalled = false;

            protected function processStagedCsvStream(
                callable $send,
                string $csvPath,
                string $tableName,
                array $activeFilters,
                array $normalizedHeaders,
                int $jobId,
                ?int $estimatedTotalRows = null,
                ?string $delimiter = null,
                bool $forceDirectLoad = false,
                ?callable $beforeDirectLoad = null,
                array $importOptions = []
            ): bool {
                $this->stagedFallbackCalled = true;

                $send('complete', [
                    'total_success' => 1,
                    'total_failed' => 0,
                    'total_rows' => 1,
                ]);

                return true;
            }
        };

        $events = [];

        $result = $controller->executeQueuedImport([
            'job_id' => 77,
            'params' => [
                'job_id' => 77,
                'file_path' => $relativePath,
                'table_name' => 'daily_loan_dinamis',
                'header_index' => 0,
                'active_filters' => [],
                'total_rows' => 1,
            ],
            'headers' => ['PERIODE', 'NOMOR_REKENING1', 'BAKI_DEBET1'],
        ], function (string $event, array $payload) use (&$events): void {
            $events[] = [$event, $payload];
        });

        $this->assertTrue($controller->stagedFallbackCalled);
        $this->assertSame('completed', $result['status']);
        $this->assertSame(1, $result['total_success']);
        $this->assertSame(0, $result['total_failed']);
        $this->assertSame(1, $result['total_rows']);
    }

    public function test_process_excel_stream_starts_lw325_inline_without_dispatching_queue(): void
    {
        Config::set('import.queue.inline_start_tables', ['lw325_ph']);

        $jobId = 501;
        $request = Request::create('/import/process', 'POST', ['job_id' => $jobId]);
        $session = app('session.store');
        $session->put('excel_import_params', ['job_id' => $jobId]);
        $request->setLaravelSession($session);
        app()->instance('request', $request);

        $jobService = Mockery::mock(ExcelImportJobService::class);
        $jobService->shouldReceive('getImportJobState')
            ->once()
            ->with($jobId)
            ->andReturn([
                'params' => [
                    'table_name' => 'lw325_ph',
                ],
            ]);
        $this->app->instance(ExcelImportJobService::class, $jobService);

        $expectedResponse = response()->stream(function (): void {
        });

        $executionService = Mockery::mock(ImportExecutionService::class);
        $executionService->shouldNotReceive('dispatch');
        $executionService->shouldReceive('streamStatus')
            ->once()
            ->with(Mockery::type(Request::class), $jobId, true)
            ->andReturn($expectedResponse);
        $this->app->instance(ImportExecutionService::class, $executionService);

        $controller = app(ImportExcelController::class);
        $response = $controller->processExcelStream($request);

        $this->assertSame($expectedResponse, $response);
    }

    public function test_process_excel_stream_keeps_queue_dispatch_for_non_lw325_reports(): void
    {
        Config::set('import.queue.inline_start_tables', ['lw325_ph']);

        $jobId = 502;
        $request = Request::create('/import/process', 'POST', ['job_id' => $jobId]);
        $session = app('session.store');
        $session->put('excel_import_params', ['job_id' => $jobId]);
        $request->setLaravelSession($session);
        app()->instance('request', $request);
        $request->attributes->set('queue_message', 'Queue path');

        $jobService = Mockery::mock(ExcelImportJobService::class);
        $jobService->shouldReceive('getImportJobState')
            ->once()
            ->with($jobId)
            ->andReturn([
                'params' => [
                    'table_name' => 'daily_loan_dinamis',
                ],
            ]);
        $this->app->instance(ExcelImportJobService::class, $jobService);

        $expectedResponse = response()->stream(function (): void {
        });

        $executionService = Mockery::mock(ImportExecutionService::class);
        $executionService->shouldReceive('dispatch')
            ->once()
            ->with($jobId, 'Queue path');
        $executionService->shouldReceive('streamStatus')
            ->once()
            ->with(Mockery::type(Request::class), $jobId, false)
            ->andReturn($expectedResponse);
        $this->app->instance(ImportExecutionService::class, $executionService);

        $controller = app(ImportExcelController::class);
        $response = $controller->processExcelStream($request);

        $this->assertSame($expectedResponse, $response);
    }

    public function test_process_excel_stream_dispatches_queue_for_lw325_when_inline_fallback_disabled(): void
    {
        Config::set('import.queue.inline_start_tables', ['lw325_ph']);

        $jobId = 503;
        $request = Request::create('/import/process', 'POST', ['job_id' => $jobId]);
        $session = app('session.store');
        $session->put('excel_import_params', ['job_id' => $jobId]);
        $request->setLaravelSession($session);
        app()->instance('request', $request);
        $request->attributes->set('queue_message', 'Queue path');

        $jobService = Mockery::mock(ExcelImportJobService::class);
        $jobService->shouldReceive('getImportJobState')
            ->once()
            ->with($jobId)
            ->andReturn([
                'params' => [
                    'table_name' => 'lw325_ph',
                    'disable_inline_fallback' => true,
                ],
            ]);
        $this->app->instance(ExcelImportJobService::class, $jobService);

        $expectedResponse = response()->stream(function (): void {
        });

        $executionService = Mockery::mock(ImportExecutionService::class);
        $executionService->shouldReceive('dispatch')
            ->once()
            ->with($jobId, 'Queue path');
        $executionService->shouldReceive('streamStatus')
            ->once()
            ->with(Mockery::type(Request::class), $jobId, false)
            ->andReturn($expectedResponse);
        $this->app->instance(ImportExecutionService::class, $executionService);

        $controller = app(ImportExcelController::class);
        $response = $controller->processExcelStream($request);

        $this->assertSame($expectedResponse, $response);
    }

    public function test_execute_queued_import_for_lw325_forces_direct_load_on_staged_fallback(): void
    {
        $bulkLoadService = Mockery::mock(MySqlBulkLoadService::class);
        $bulkLoadService->shouldReceive('supportsNativeBulkLoad')->andReturn(false);
        $bulkLoadService->shouldReceive('assertTransactionalTable')->once();
        $this->app->instance(MySqlBulkLoadService::class, $bulkLoadService);

        $relativePath = 'testing/queued_fallback_lw325.csv';
        Storage::disk('local')->put($relativePath, "PERIODE,ACCTNO,POKOK\n2026-04-04,123,1000.00\n");

        $jobObject = (object) [
            'id' => 88,
            'status' => 'completed',
            'total_success' => 1,
            'total_failed' => 0,
            'total_files' => 1,
        ];

        $progressService = Mockery::mock(ImportProgressService::class);
        $progressService->shouldReceive('findJob')->andReturn($jobObject, $jobObject, $jobObject);
        $this->app->instance(ImportProgressService::class, $progressService);

        $controller = new class extends ImportExcelController {
            public bool $capturedForceDirectLoad = false;

            protected function processDailyLoanDirectCsvStream(
                callable $send,
                string $csvPath,
                string $tableName,
                array $normalizedHeaders,
                int $jobId,
                int $estimatedTotalRows,
                ?string $delimiter = null,
                bool $emitComplete = true,
                array $importOptions = []
            ): bool {
                return false;
            }

            protected function processStagedCsvStream(
                callable $send,
                string $csvPath,
                string $tableName,
                array $activeFilters,
                array $normalizedHeaders,
                int $jobId,
                ?int $estimatedTotalRows = null,
                ?string $delimiter = null,
                bool $forceDirectLoad = false,
                ?callable $beforeDirectLoad = null,
                array $importOptions = []
            ): bool {
                $this->capturedForceDirectLoad = $forceDirectLoad;

                $send('complete', [
                    'total_success' => 1,
                    'total_failed' => 0,
                    'total_rows' => 1,
                ]);

                return true;
            }
        };

        $result = $controller->executeQueuedImport([
            'job_id' => 88,
            'params' => [
                'job_id' => 88,
                'file_path' => $relativePath,
                'table_name' => 'lw325_ph',
                'header_index' => 0,
                'active_filters' => [],
                'total_rows' => 1,
            ],
            'headers' => ['PERIODE', 'ACCTNO', 'POKOK'],
        ]);

        $this->assertTrue($controller->capturedForceDirectLoad);
        $this->assertSame('completed', $result['status']);
    }
}
