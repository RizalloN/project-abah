<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use App\Services\Import\ImportProgressService;
use App\Services\Import\MySqlBulkLoadService;
use Illuminate\Support\Facades\Storage;
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
                ?callable $beforeDirectLoad = null
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
}
