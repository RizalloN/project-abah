<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use App\Http\Controllers\Import\ImportFileController;
use App\Services\Import\ExcelQueuedImportService;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportPeriodGuardService;
use App\Services\Import\ImportProgressService;
use App\Services\Import\ImportStrategyFactory;
use App\Services\Import\MonitoringKsbkSourceService as Source;
use App\Services\Import\SchemaDrift\SchemaDriftImportGuard;
use App\Services\Import\Strategies\GenericCsvImportStrategy;
use App\Services\Import\Strategies\MonitoringKsbkImportStrategy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class MonitoringKsbkIntegrationTest extends TestCase
{
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Cache::flush();
        Schema::create('nama_report', function (Blueprint $table): void {
            $table->integer('id_report')->primary();
            foreach (['nama_report', 'table_name', 'import_controller'] as $column) {
                $table->string($column)->nullable();
            }
            $table->boolean('active');
            $table->boolean('requires_manual_periode')->default(false);
            $table->timestamps();
        });
        (require database_path('migrations/2026_11_12_000005_create_monitoring_realisasi_ksbk_report.php'))->up();
        session(['active_id_report' => DB::table('nama_report')->where('table_name', Source::TABLE)->value('id_report')]);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        Mockery::close();
        parent::tearDown();
    }

    public function test_registered_report_resolves_precise_strategy_without_an_invented_manual_period(): void
    {
        $this->assertDatabaseHas('nama_report', ['table_name' => Source::TABLE, 'import_controller' => 'ImportExcelController', 'requires_manual_periode' => 0]);
        $strategy = app(ImportStrategyFactory::class)->resolve((object) ['table_name' => Source::TABLE]);
        $this->assertInstanceOf(MonitoringKsbkImportStrategy::class, $strategy);
        $this->assertSame('monitoring_realisasi_ksbk_exact', $strategy->importMode());
        $this->assertFalse((new GenericCsvImportStrategy)->supports(null, Source::TABLE));
        $this->assertNull(app(ImportPeriodGuardService::class)->policyFor(Source::TABLE));
        $this->assertTrue($strategy->validateSchema(Schema::getColumnListing(Source::TABLE))['ok']);
    }

    public function test_upload_uses_ksbk_staging_without_a_manual_period_argument(): void
    {
        $source = Mockery::mock(Source::class);
        $source->shouldReceive('stage')->once()->with(Storage::path('excel_imports/ksbk.xlsx'), Mockery::on(
            static fn (string $path): bool => str_ends_with($path, '.csv') && str_contains($path, 'excel_imports')
        ))->andReturn(['rows' => 1]);
        $this->app->instance(Source::class, $source);

        $normalized = (new ReflectionMethod(ImportExcelController::class, 'normalizeSsaSpreadsheetUpload'))->invoke(
            new ImportExcelController, Source::TABLE, 'excel_imports/ksbk.xlsx'
        );

        $this->assertStringStartsWith('excel_imports/', $normalized);
        $this->assertStringEndsWith('.csv', $normalized);
    }

    public function test_upload_rejects_non_workbook_sources_before_staging(): void
    {
        $source = Mockery::mock(Source::class);
        $source->shouldNotReceive('stage');
        $this->app->instance(Source::class, $source);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('XLSX asli Monitoring');
        (new ReflectionMethod(ImportExcelController::class, 'normalizeSsaSpreadsheetUpload'))->invoke(
            new ImportExcelController, Source::TABLE, 'excel_imports/ksbk.csv', '2026'
        );
    }

    public function test_controller_preview_keeps_thirteen_source_fields_and_exact_identifiers(): void
    {
        $controller = new class extends ImportExcelController
        {
            protected function resolveActiveTableName(string $default = 'daily_loan_dinamis'): string
            {
                return Source::TABLE;
            }
        };
        $preview = (new ReflectionMethod(ImportExcelController::class, 'prepareCsvPreviewPayload'))->invoke($controller, $this->canonical());
        $this->assertSame(Source::IMPORT_COLUMNS, $preview['headers']);
        $this->assertSame($this->row(), $preview['preview'][0]);
        $this->assertSame('003520067011860000', $preview['preview'][0][2]);
        $this->assertSame('9007199254740993.01', $preview['preview'][0][5]);
        $this->assertSame(2, $preview['total_rows']);
        foreach (['cek_real', 'plafond', 'ln_type', 'ksbk_non'] as $derived) {
            $this->assertNotContains($derived, $preview['headers']);
        }
    }

    public function test_canonical_source_passes_structure_review_with_all_derived_fields_managed(): void
    {
        (require database_path('migrations/2026_10_20_000001_create_import_schema_drift_tables.php'))->up();
        (require database_path('migrations/2026_10_20_000002_create_import_source_header_baselines_table.php'))->up();
        $review = app(SchemaDriftImportGuard::class)->assertReady(Source::TABLE, $this->canonical());
        $this->assertSame(13, $review['stats']['matched']);
        $this->assertFalse($review['has_blocking_issue']);
        $this->assertFalse($review['has_pending_decision']);
        $this->assertSame(0, DB::table(Source::TABLE)->count());
    }

    public function test_legacy_mapping_cannot_bypass_the_dedicated_import(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('alur Excel/CSV presisi');
        (new ReflectionMethod(ImportFileController::class, 'assertImportPeriodMapping'))->invoke(
            new ImportFileController, Source::TABLE,
            array_map(static fn (string $column): array => ['column' => $column], Source::IMPORT_COLUMNS), '2026'
        );
    }

    public function test_browser_chunk_endpoint_cannot_bypass_the_precise_queue(): void
    {
        session(['excel_import_params' => ['job_id' => 44]]);
        $request = Request::create('/import/excel/chunk', 'POST', [
            'job_id' => 44, 'table_name' => Source::TABLE, 'header_index' => 0,
            'start_row' => 1, 'chunk_size' => 100, 'file_path' => 'excel_imports/ksbk.csv',
        ]);
        $request->setLaravelSession(app('session.store'));
        $response = (new ImportExcelController)->processExcelChunk($request);
        $this->assertSame(422, $response->status());
        $this->assertSame('error', $response->getData(true)['status']);
        $this->assertStringContainsString('antrean import presisi', $response->getData(true)['message']);
        $this->assertSame(0, DB::table(Source::TABLE)->count());
    }

    public function test_enqueue_records_exact_headers_and_full_row_count_without_generic_initialization(): void
    {
        Queue::fake();
        Storage::fake('local');
        config(['filesystems.default' => 'local', 'import.queue.inline_start_tables' => []]);
        $relative = 'excel_imports/ksbk-enqueue.csv';
        Storage::put($relative, file_get_contents($this->canonical(3)));
        $absolute = Storage::path($relative);
        $this->files[] = $absolute;
        session(['excel_path' => $relative, 'excel_preview_meta' => [
            'path' => $relative, 'header_index' => 0, 'normalized_headers' => Source::IMPORT_COLUMNS,
            'source_headers' => Source::IMPORT_COLUMNS, 'total_rows' => 4, 'delimiter' => ',',
        ]]);
        $guard = Mockery::mock(SchemaDriftImportGuard::class);
        $guard->shouldReceive('assertReady')->once()->with(Source::TABLE, realpath($absolute))->andReturn([]);
        $this->app->instance(SchemaDriftImportGuard::class, $guard);
        $created = $state = null;
        $progress = Mockery::mock(ImportProgressService::class);
        $progress->shouldReceive('createJob')->once()->andReturnUsing(static function (array $attributes) use (&$created): int {
            $created = $attributes;

            return 123;
        });
        $progress->shouldReceive('cacheJobState')->once()->with(123, Mockery::on(static function (array $value) use (&$state): bool {
            $state = $value;

            return true;
        }));
        $progress->shouldReceive('markQueued')->once()->with(123, Mockery::on(static fn (array $payload): bool => $payload['status'] === 'queued'));
        $progress->shouldReceive('getJobState')->with(123)->andReturnUsing(static fn (): array => $state ?? []);
        $this->app->instance(ImportProgressService::class, $progress);
        $execution = Mockery::mock(ImportExecutionService::class);
        $execution->shouldReceive('dispatch')->once()->with(123)->andReturnTrue();
        $this->app->instance(ImportExecutionService::class, $execution);
        $controller = new class extends ImportExcelController
        {
            public bool $genericInitializerCalled = false;

            public function initializeQueuedImportJobForExecution(int $jobId): bool
            {
                $this->genericInitializerCalled = true;

                return false;
            }
        };
        $response = $controller->initExcelImport(Request::create('/import-excel/init', 'POST', ['active_filters_json' => '{}']));
        $this->assertSame(200, $response->status());
        $this->assertSame('success', $response->getData(true)['status']);
        $this->assertSame(3, $created['total_files']);
        $this->assertSame(Source::IMPORT_COLUMNS, $state['headers']);
        $this->assertSame(0, $state['params']['header_index']);
        $this->assertSame(3, $state['params']['total_rows']);
        $this->assertSame(',', $state['params']['delimiter']);
        $this->assertSame($relative, $state['params']['file_path']);
        $this->assertNull($state['params']['manual_periode']);
        $this->assertFalse($controller->genericInitializerCalled);
        Queue::assertNothingPushed();
    }

    public function test_queue_uses_exact_source_loader_and_only_reports_confirmed_insert_count(): void
    {
        $path = $this->canonical();
        $filters = [9 => ['49']];
        $source = Mockery::mock(Source::class);
        $source->shouldReceive('import')->once()->with($path, Source::IMPORT_COLUMNS, $filters, Mockery::type('callable'))
            ->andReturnUsing(static function ($path, $headers, $filters, $progress): int {
                $progress(1, 1);

                return 1;
            });
        $this->app->instance(Source::class, $source);
        $events = $updates = [];
        $result = (new ExcelQueuedImportService)->execute([
            'job_id' => 123, 'headers' => Source::IMPORT_COLUMNS,
            'params' => ['file_path' => $path, 'table_name' => Source::TABLE, 'active_filters' => $filters, 'total_rows' => 1],
        ], [
            'resolve_import_strategy' => static fn () => new MonitoringKsbkImportStrategy,
            'mark_failed' => fn () => $this->fail('Exact import unexpectedly failed.'),
            'find_job' => static fn () => null,
            'update_job' => static function ($id, $attributes, $payload) use (&$updates): void {
                $updates[] = $attributes;
            },
            'assert_transactional_table' => static fn () => null,
            'assert_duplicate_guard' => static fn () => null,
            'is_csv_file' => fn () => $this->fail('KSBK must not enter generic import.'),
            'cleanup_successful_import_artifacts' => static fn () => null,
        ], static function ($event, $payload) use (&$events): void {
            $events[] = [$event, $payload];
        });
        $this->assertSame('completed', $result['status']);
        $this->assertSame(1, $result['total_success']);
        $this->assertSame(0, $result['total_failed']);
        $this->assertSame('completed', $updates[0]['status']);
        $this->assertSame('progress', $events[0][0]);
        $this->assertSame('complete', $events[array_key_last($events)][0]);
        $this->assertSame(100, $events[array_key_last($events)][1]['percent']);
    }

    public function test_queue_never_marks_a_failed_exact_import_completed_or_falls_back(): void
    {
        $source = Mockery::mock(Source::class);
        $source->shouldReceive('import')->once()->andThrow(new RuntimeException('warning MySQL; transaksi dibatalkan.'));
        $this->app->instance(Source::class, $source);
        $failures = $events = [];
        $result = (new ExcelQueuedImportService)->execute([
            'job_id' => 124, 'headers' => Source::IMPORT_COLUMNS,
            'params' => ['file_path' => $this->canonical(), 'table_name' => Source::TABLE, 'total_rows' => 1],
        ], [
            'resolve_import_strategy' => static fn () => new MonitoringKsbkImportStrategy,
            'mark_failed' => static function ($id, $message) use (&$failures): void {
                $failures[] = [$id, $message];
            },
            'find_job' => static fn () => (object) ['status' => 'processing', 'total_success' => 0, 'total_failed' => 0, 'total_files' => 1],
            'update_job' => fn () => $this->fail('Rejected source cannot complete.'),
            'assert_transactional_table' => static fn () => null,
            'assert_duplicate_guard' => static fn () => null,
            'is_csv_file' => fn () => $this->fail('Rejected source cannot fall back to generic import.'),
            'cleanup_successful_import_artifacts' => fn () => $this->fail('Failed source must be retained.'),
        ], static function ($event) use (&$events): void {
            $events[] = $event;
        });
        $this->assertSame('failed', $result['status']);
        $this->assertSame(0, $result['total_success']);
        $this->assertSame(124, $failures[0][0]);
        $this->assertStringContainsString('warning MySQL', $failures[0][1]);
        $this->assertSame(['error'], $events);
    }

    private function canonical(int $count = 1): string
    {
        $path = storage_path('framework/testing/ksbk-integration-'.bin2hex(random_bytes(8)).'.csv');
        $this->files[] = $path;
        $output = fopen($path, 'wb');
        fputcsv($output, Source::IMPORT_COLUMNS, ',', '"', '');
        for ($i = 0; $i < $count; $i++) {
            fputcsv($output, $this->row(), ',', '"', '');
        }
        fclose($output);

        return $path;
    }

    private function row(): array
    {
        return ['003641', '00130026', '003520067011860000', 'CIF001', 'Nama Nasabah', '9007199254740993.01',
            '2026-09-22 00:00:00', 'Kupedes <= 150juta', 'Lolos Pre-wash', '49', 'R', '1', '2026-09-25'];
    }
}
