<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use App\Services\Import\ExcelImportJobService;
use App\Services\Import\ExcelQueuedImportService;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportPipelineService;
use App\Services\Import\ImportProgressService;
use App\Services\Import\SchemaIntrospectionService;
use App\Services\Import\MySqlBulkLoadService;
use App\Services\Import\Strategies\DailyLoanImportStrategy;
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
        Storage::disk('local')->delete('testing/queued_fallback_ssa_pinjaman.xlsx');
        Storage::disk('local')->delete('testing/queued_fallback_ssa_pinjaman_staged.csv');
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
                array $importOptions = [],
                bool $fullVectorization = false
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

    public function test_daily_loan_direct_exception_does_not_start_staged_fallback(): void
    {
        $relativePath = 'testing/queued_fallback_daily_loan.csv';
        Storage::disk('local')->put($relativePath, "PERIODE,NOMOR_REKENING1,BAKI_DEBET1\n2026-04-04,123,1000\n");

        $service = new ExcelQueuedImportService();
        $stagedFallbackCalled = false;
        $failedMessage = '';

        $result = $service->execute([
            'job_id' => 78,
            'params' => [
                'job_id' => 78,
                'file_path' => $relativePath,
                'table_name' => 'daily_loan_dinamis',
                'header_index' => 0,
                'active_filters' => [],
                'total_rows' => 2,
                'delimiter' => ',',
            ],
            'headers' => ['PERIODE', 'NOMOR_REKENING1', 'BAKI_DEBET1'],
        ], [
            'resolve_import_strategy' => fn (string $tableName) => new class {
                public function importMode(array $context = []): string
                {
                    return 'bulk_csv_direct';
                }
            },
            'mark_failed' => function (
                int $jobId,
                string $message,
                int $success = 0,
                int $failed = 0
            ) use (&$failedMessage): void {
                $failedMessage = $message;
            },
            'find_job' => fn (int $jobId) => (object) [
                'status' => 'processing',
                'total_success' => 0,
                'total_failed' => 0,
                'total_files' => 1,
            ],
            'update_job' => fn (int $jobId, array $attributes, ?array $progressPayload = null) => null,
            'assert_transactional_table' => fn (string $tableName, string $context) => null,
            'assert_duplicate_guard' => fn (string $tableName) => null,
            'is_csv_file' => fn (string $path) => true,
            'detect_csv_delimiter' => fn (string $path) => ',',
            'count_csv_data_rows' => fn (string $path, ?string $tableName = null) => 1,
            'resolve_csv_data_row_estimate' => fn (?int $totalRows, int $headerIndex) => 1,
            'run_csv_pipeline' => fn (array $payload) => app(ImportPipelineService::class)->runCsvPipeline($payload),
            'process_daily_loan_direct_csv_stream' => function (): bool {
                throw new \RuntimeException('Direct load berhenti setelah commit.');
            },
            'process_staged_csv_stream' => function () use (&$stagedFallbackCalled): bool {
                $stagedFallbackCalled = true;

                return true;
            },
        ]);

        $this->assertFalse($stagedFallbackCalled);
        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('Direct load berhenti setelah commit.', $failedMessage);
    }

    public function test_daily_loan_strategy_always_forces_direct_mode_even_if_filters_present(): void
    {
        $strategy = new DailyLoanImportStrategy();

        $this->assertSame('bulk_csv_direct', $strategy->importMode([
            'table_name' => 'daily_loan_dinamis',
            'active_filters' => [
                0 => ['2026-04-23'],
            ],
        ]));
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
                array $importOptions = [],
                bool $fullVectorization = false
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

    public function test_execute_queued_import_for_ssa_pinjaman_stages_excel_inside_worker_when_staged_csv_is_missing(): void
    {
        $relativePath = 'testing/queued_fallback_ssa_pinjaman.xlsx';
        Storage::disk('local')->put($relativePath, 'placeholder excel payload');

        $jobObject = (object) [
            'id' => 99,
            'status' => 'completed',
            'total_success' => 1,
            'total_failed' => 0,
            'total_files' => 1,
        ];

        $service = new ExcelQueuedImportService();
        $events = [];
        $stageExcelToCsvCalled = false;
        $stagedCsvStreamCalled = false;
        $capturedStageSourcePath = '';
        $capturedStagedCsvPath = '';

        $result = $service->execute([
            'job_id' => 99,
            'params' => [
                'job_id' => 99,
                'file_path' => $relativePath,
                'table_name' => 'ssa_pinjaman',
                'header_index' => 0,
                'active_filters' => [],
                'total_rows' => 1,
            ],
            'headers' => ['SEGMENTASI', 'BAKI_DEBET'],
        ], [
            'resolve_import_strategy' => fn(string $tableName) => new class {
                public function importMode(array $context = []): string
                {
                    return 'bulk_csv_staging';
                }
            },
            'mark_failed' => function (int $jobId, string $message, int $success = 0, int $failed = 0): void {
                $this->fail('Queued SSA staging should not fail: ' . $message);
            },
            'find_job' => fn(int $jobId) => $jobObject,
            'update_job' => fn(int $jobId, array $attributes, ?array $progressPayload = null) => null,
            'assert_transactional_table' => fn(string $tableName, string $context) => null,
            'assert_duplicate_guard' => fn(string $tableName) => null,
            'is_csv_file' => fn(string $path) => str_ends_with(strtolower($path), '.csv'),
            'detect_csv_delimiter' => fn(string $path) => ',',
            'count_csv_data_rows' => fn(string $path, ?string $tableName = null) => 1,
            'resolve_csv_data_row_estimate' => fn(?int $totalRows, int $headerIndex) => max(0, (int) $totalRows - ($headerIndex + 1)),
            'stage_excel_to_csv' => function (callable $send, string $path, int $headerIndex, array $normalizedHeaders, string $tableName) use (&$stageExcelToCsvCalled, &$capturedStageSourcePath): array {
                $stageExcelToCsvCalled = true;
                $capturedStageSourcePath = $path;
                $generatedStagedCsvPath = storage_path('app/testing/queued_fallback_ssa_pinjaman_staged.csv');
                file_put_contents($generatedStagedCsvPath, "SEGMENTASI,BAKI_DEBET\nKONSUMER,1000\n");

                return [
                    'staged_csv_path' => $generatedStagedCsvPath,
                    'total_rows' => 1,
                ];
            },
            'run_csv_pipeline' => fn(array $payload) => (new \App\Services\Import\ImportPipelineService())->runCsvPipeline($payload),
            'process_daily_loan_direct_csv_stream' => fn($send, string $workingPath, string $tableName, array $normalizedHeaders, int $jobId, int $totalDataRows, ?string $delimiter, array $importOptions = []) => false,
            'process_daily_loan_bulk_csv_stream' => fn($send, string $workingPath, string $tableName, array $normalizedHeaders, array $activeFilters, int $jobId, int $totalDataRows, ?string $delimiter, array $importOptions = []) => false,
            'process_staged_csv_stream' => function (
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
            ) use (&$stagedCsvStreamCalled, &$capturedStagedCsvPath): bool {
                $stagedCsvStreamCalled = true;
                $capturedStagedCsvPath = $csvPath;

                $send('complete', [
                    'total_success' => 1,
                    'total_failed' => 0,
                    'total_rows' => 1,
                ]);

                return true;
            },
            'try_python_bulk_load' => fn($send, string $path, int $headerIndex, string $tableName, array $activeFilters, array $normalizedHeaders, int $jobId, array $importOptions = []) => false,
            'try_python_gpu' => fn($send, string $path, int $headerIndex, string $tableName, array $activeFilters, array $normalizedHeaders, int $jobId, array $importOptions = []) => false,
            'build_import_context' => fn(string $tableName, array $normalizedHeaders, array $activeFilters = [], array $importOptions = []) => [],
            'map_excel_row_for_insert' => fn(array $row, array $normalizedHeaders, array $context, string $timestamp) => [],
            'fallback_insert_batch_size' => fn(): int => 1000,
            'insert_batch_with_fallback' => function (array $batch, string $tableName, int &$totalInserted, int &$totalFailed): void {
            },
            'cleanup_successful_import_artifacts' => fn(int $jobId, string $relativePath, string $path, array $extraPaths = []) => null,
            'cleanup_service_dispatch_imported_job_sync' => fn(int $jobId, string $status) => null,
        ], function (string $event, array $payload) use (&$events): void {
            $events[] = [$event, $payload];
        });

        $this->assertTrue($stageExcelToCsvCalled);
        $this->assertTrue($stagedCsvStreamCalled);
        $this->assertSame(Storage::path($relativePath), $capturedStageSourcePath);
        $this->assertStringEndsWith('queued_fallback_ssa_pinjaman_staged.csv', $capturedStagedCsvPath);
        $this->assertSame('progress', $events[0][0] ?? null);
        $this->assertStringContainsString('Menyiapkan CSV staging dari Excel', (string) ($events[0][1]['message'] ?? ''));
        $this->assertSame('completed', $result['status']);
        $this->assertSame(1, $result['total_success']);
        $this->assertSame(0, $result['total_failed']);
    }

    public function test_resolve_staging_target_columns_falls_back_to_schema_service(): void
    {
        $bulkLoadService = new class extends MySqlBulkLoadService {
            public function getColumnListing(string $tableName): array
            {
                throw new \RuntimeException('bulk metadata unavailable');
            }
        };
        $this->app->instance(MySqlBulkLoadService::class, $bulkLoadService);

        $schemaService = Mockery::mock(SchemaIntrospectionService::class);
        $schemaService->shouldReceive('getColumnListing')
            ->once()
            ->with('simpanan_multipn')
            ->andReturn(['uniqueid_SMPN', 'posisi', 'saldo']);
        $this->app->instance(SchemaIntrospectionService::class, $schemaService);

        $controller = new class extends ImportExcelController {
            public function exposeResolveStagingTargetColumns(string $tableName, int $jobId = 0): array
            {
                return $this->resolveStagingTargetColumns($tableName, $jobId);
            }
        };

        $this->assertSame(
            ['uniqueid_SMPN', 'posisi', 'saldo'],
            $controller->exposeResolveStagingTargetColumns('simpanan_multipn', 19)
        );
    }

    public function test_resolve_staging_target_columns_returns_empty_instead_of_crashing(): void
    {
        $bulkLoadService = new class extends MySqlBulkLoadService {
            public function getColumnListing(string $tableName): array
            {
                throw new \RuntimeException('bulk metadata unavailable');
            }
        };
        $this->app->instance(MySqlBulkLoadService::class, $bulkLoadService);

        $schemaService = Mockery::mock(SchemaIntrospectionService::class);
        $schemaService->shouldReceive('getColumnListing')
            ->once()
            ->with('simpanan_multipn')
            ->andThrow(new \RuntimeException('schema metadata unavailable'));
        $this->app->instance(SchemaIntrospectionService::class, $schemaService);

        $controller = new class extends ImportExcelController {
            public function exposeResolveStagingTargetColumns(string $tableName, int $jobId = 0): array
            {
                return $this->resolveStagingTargetColumns($tableName, $jobId);
            }
        };

        $this->assertSame([], $controller->exposeResolveStagingTargetColumns('simpanan_multipn', 19));
    }

    public function test_initialize_queued_import_job_for_execution_uses_import_job_state_service_for_ssa_pinjaman_csv(): void
    {
        $relativePath = 'testing/queued_init_ssa_pinjaman.csv';
        Storage::disk('local')->put(
            $relativePath,
            "Month_Day_Year_of_Periode,Nama Cabang,Nama Uker,Produk,Produk_Dashboard,Segmen,Segmen Lama,Segmen_2025,Segmen_Dashboard,Kolektabilitas One Obligor,Flag Restruk,Baki Debet,Jumlah Debitur Aktif,Jumlah Rekening Aktif\n" .
            "2026-04-14,00045 -- KC Madiun (Konsolidasi-MB),00045 -- KC Madiun,Kecil Komersial,Commercial,SME,Ritel,Medium,Small,1,Y,30266179892.41,9,11\n"
        );

        $schemaService = Mockery::mock(SchemaIntrospectionService::class);
        $schemaService->shouldReceive('hasTable')->with('ssa_pinjaman')->andReturn(true);
        $schemaService->shouldReceive('getColumnListing')->with('ssa_pinjaman')->andReturn([
            'id',
            'month_day_year_of_periode',
            'nama_cabang',
            'nama_uker',
            'produk',
            'produk_dashboard',
            'segmen',
            'segmen_lama',
            'segmen_2025',
            'segmen_dashboard',
            'kolektabilitas_one_obligor',
            'flag_restruk',
            'baki_debet',
            'jumlah_debitur_aktif',
            'jumlah_rekening_aktif',
        ]);
        $this->app->instance(SchemaIntrospectionService::class, $schemaService);

        $jobObject = new class {
            public int $id = 123;
            public int $status = 0;
            public int $total_success = 0;
            public int $total_failed = 0;
            public int $total_files = 0;

            public function update(array $attributes): void
            {
                foreach ($attributes as $key => $value) {
                    $this->{$key} = $value;
                }
            }
        };

        $progressService = Mockery::mock(ImportProgressService::class);
        $progressService->shouldReceive('findJob')->once()->with(123)->andReturn($jobObject);
        $progressService->shouldReceive('updateJob')
            ->once()
            ->withArgs(function (int $jobId, array $payload): bool {
                return $jobId === 123 && array_key_exists('total_files', $payload);
            });
        $progressService->shouldReceive('markQueued')->once();
        $this->app->instance(ImportProgressService::class, $progressService);

        $jobService = Mockery::mock(ExcelImportJobService::class);
        $jobService->shouldReceive('getImportJobState')
            ->once()
            ->with(123)
            ->andReturn([
                'params' => [
                    'file_path' => $relativePath,
                    'table_name' => 'ssa_pinjaman',
                    'disable_inline_fallback' => false,
                ],
            ]);
        $jobService->shouldReceive('putImportJobState')->once();
        $this->app->instance(ExcelImportJobService::class, $jobService);

        $controller = new ImportExcelController();

        $result = $controller->initializeQueuedImportJobForExecution(123);

        $this->assertTrue($result);
        $this->assertSame(0, $jobObject->status);
    }
}
