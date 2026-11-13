<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use App\Services\Import\EvaluasiKunjunganSourceService;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportProgressService;
use App\Services\Import\SchemaDrift\SchemaDriftImportGuard;
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

class EvaluasiKunjunganPreviewTest extends TestCase
{
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Cache::flush();
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

    public function test_service_counts_the_entire_file_while_preview_and_filter_sets_remain_bounded(): void
    {
        $path = $this->canonical(1200);

        $payload = app(EvaluasiKunjunganSourceService::class)->preview($path);

        $this->assertSame(EvaluasiKunjunganSourceService::IMPORT_COLUMNS, $payload['headers']);
        $this->assertSame(1201, $payload['total_rows']);
        $this->assertSame(0, $payload['header_index']);
        $this->assertSame(',', $payload['delimiter']);
        $this->assertCount(100, $payload['preview']);
        $this->assertSame($this->row(1), array_values($payload['preview'][0]));
        $this->assertSame('00123456', $payload['preview'][0][6]);
        $this->assertSame('-7.602666', $payload['preview'][0][3]);
        $this->assertSame('9007199254740993.01', $payload['preview'][0][21]);
        $this->assertContains('000000000000000000000001', $payload['formattedUniqueValues'][1]);
        $this->assertContains('00123456', $payload['formattedUniqueValues'][6]);
        $this->assertContains('KC Test', $payload['formattedUniqueValues'][12]);
        $this->assertNotContains(0, $payload['formattedUniqueValues'][12], true);
        foreach ($payload['formattedUniqueValues'] as $values) {
            $this->assertLessThanOrEqual(256, count($values));
        }
    }

    public function test_service_rejects_a_malformed_record_after_the_preview_sample(): void
    {
        $path = $this->canonical(150);
        $output = fopen($path, 'ab');
        fputcsv($output, ['truncated', 'record'], ',', '"', '');
        fclose($output);

        $this->expectException(RuntimeException::class);
        app(EvaluasiKunjunganSourceService::class)->preview($path);
    }

    public function test_service_reports_progress_while_scanning_large_files(): void
    {
        $path = $this->canonical(25001);
        $progress = [];

        $payload = app(EvaluasiKunjunganSourceService::class)->preview($path, static function (...$arguments) use (&$progress): void {
            $progress[] = $arguments;
        });

        $this->assertSame(25002, $payload['total_rows']);
        $this->assertNotEmpty($progress);
        $this->assertStringContainsString('25000', json_encode($progress));
    }

    public function test_controller_preview_uses_a_constant_number_of_report_queries_for_large_csv(): void
    {
        $this->createReport();
        $path = $this->canonical(1200);
        DB::enableQueryLog();
        DB::flushQueryLog();

        $payload = (new ReflectionMethod(ImportExcelController::class, 'prepareCsvPreviewPayload'))->invoke(app(ImportExcelController::class), $path);

        $reportQueries = $this->reportQueries();
        $this->assertLessThanOrEqual(3, count($reportQueries), 'Report configuration must not be queried once per CSV row.');
        $this->assertSame(1201, $payload['total_rows']);
        $this->assertSame($this->row(1), array_values($payload['preview'][0]));
    }

    public function test_prime_cache_exposes_exact_headers_and_full_row_count_with_progress(): void
    {
        $this->createReport();
        $path = $this->canonical(1200);
        $key = 'unit-kunjungan-preview-cache';
        $events = [];
        DB::enableQueryLog();
        DB::flushQueryLog();

        (new ReflectionMethod(ImportExcelController::class, 'primeExcelPreviewCache'))->invoke(
            app(ImportExcelController::class),
            'excel_imports/unit-kunjungan.csv',
            $path,
            $key,
            static function ($event, $payload) use (&$events): void {
                $events[] = [$event, $payload];
            },
        );

        $cached = Cache::get($key);
        $this->assertIsArray($cached);
        $this->assertSame(EvaluasiKunjunganSourceService::IMPORT_COLUMNS, $cached['headers']);
        $this->assertSame(EvaluasiKunjunganSourceService::IMPORT_COLUMNS, $cached['normalizedHeaders']);
        $this->assertSame(EvaluasiKunjunganSourceService::IMPORT_COLUMNS, $cached['sourceHeaders']);
        $this->assertSame(1201, $cached['total_rows']);
        $this->assertSame(0, $cached['headerIndex']);
        $this->assertSame($path, $cached['stagedCsvPath']);
        $this->assertSame($this->row(1), array_values($cached['preview'][0]));
        $this->assertNotEmpty($events);
        $this->assertLessThanOrEqual(3, count($this->reportQueries()));
    }

    public function test_repeated_report_reads_are_memoized_only_for_the_active_kunjungan_report(): void
    {
        $this->createReport();
        DB::table('nama_report')->insert([
            'id_report' => 999,
            'nama_report' => 'Other report',
            'table_name' => 'other_report',
            'active' => 1,
            'import_controller' => 'ImportExcelController',
            'requires_manual_periode' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $controller = app(ImportExcelController::class);
        $resolve = new ReflectionMethod(ImportExcelController::class, 'resolveActiveReport');
        DB::enableQueryLog();
        DB::flushQueryLog();
        for ($index = 0; $index < 20; $index++) {
            $this->assertSame(EvaluasiKunjunganSourceService::TABLE, $resolve->invoke($controller)->table_name);
        }
        $this->assertCount(1, $this->reportQueries());

        session(['active_id_report' => 999]);
        $this->assertSame('other_report', $resolve->invoke($controller)->table_name);
    }

    public function test_cached_preview_disables_automatic_filter_prefetch_for_kunjungan(): void
    {
        $this->createReport();
        $path = $this->canonical(3);
        $controller = app(ImportExcelController::class);
        $key = 'kunjungan-cached-view';
        (new ReflectionMethod(ImportExcelController::class, 'primeExcelPreviewCache'))->invoke($controller, 'excel_imports/cached-view.csv', $path, $key);

        $view = $controller->previewExcel(Request::create('/import-excel/preview', 'GET', ['ck' => $key]));

        $this->assertSame('import.preview_excel', $view->getName());
        $this->assertTrue($view->getData()['disableFilterPrefetch']);
        $this->assertSame(4, $view->getData()['total_rows']);
        $this->assertSame(EvaluasiKunjunganSourceService::IMPORT_COLUMNS, $view->getData()['headers']);
        $this->assertSame(4, session('excel_preview_meta.total_rows'));
    }

    public function test_init_queues_ready_canonical_headers_and_data_count_without_generic_initialization(): void
    {
        $this->createReport();
        Queue::fake();
        Storage::fake('local');
        config(['filesystems.default' => 'local', 'import.queue.inline_start_tables' => []]);
        $canonical = $this->canonical(3);
        $relativePath = 'excel_imports/kunjungan-init.csv';
        Storage::put($relativePath, file_get_contents($canonical));
        $absolutePath = Storage::path($relativePath);
        $this->files[] = $absolutePath;
        session([
            'excel_path' => $relativePath,
            'excel_preview_meta' => [
                'path' => $relativePath,
                'header_index' => 0,
                'normalized_headers' => EvaluasiKunjunganSourceService::IMPORT_COLUMNS,
                'source_headers' => EvaluasiKunjunganSourceService::IMPORT_COLUMNS,
                'total_rows' => 4,
                'delimiter' => ',',
            ],
        ]);

        $guard = Mockery::mock(SchemaDriftImportGuard::class);
        $guard->shouldReceive('assertReady')->once()->with(EvaluasiKunjunganSourceService::TABLE, realpath($absolutePath))->andReturn([]);
        $this->app->instance(SchemaDriftImportGuard::class, $guard);
        $created = $queuedState = $queuedProgress = null;
        $progress = Mockery::mock(ImportProgressService::class);
        $progress->shouldReceive('createJob')->once()->withArgs(static function (array $attributes) use (&$created): bool {
            $created = $attributes;

            return true;
        })->andReturn(123);
        $progress->shouldReceive('cacheJobState')->once()->with(123, Mockery::on(static function (array $state) use (&$queuedState): bool {
            $queuedState = $state;

            return true;
        }))->andReturnNull();
        $progress->shouldReceive('markQueued')->once()->with(123, Mockery::on(static function (array $payload) use (&$queuedProgress): bool {
            $queuedProgress = $payload;

            return true;
        }))->andReturnNull();
        $progress->shouldReceive('getJobState')->with(123)->andReturnUsing(static function () use (&$queuedState): array {
            return $queuedState ?? [];
        });
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
        $this->assertSame(123, $response->getData(true)['job_id']);
        $this->assertSame(3, $created['total_files']);
        $this->assertSame(EvaluasiKunjunganSourceService::IMPORT_COLUMNS, $queuedState['headers']);
        $this->assertSame(0, $queuedState['params']['header_index']);
        $this->assertSame(3, $queuedState['params']['total_rows']);
        $this->assertSame(',', $queuedState['params']['delimiter']);
        $this->assertSame($relativePath, $queuedState['params']['file_path']);
        $this->assertSame([], $queuedState['params']['active_filters']);
        $this->assertSame('queued', $queuedProgress['status']);
        $this->assertFalse($controller->genericInitializerCalled);
        Queue::assertNothingPushed();
    }

    private function createReport(): void
    {
        Schema::create('nama_report', function (Blueprint $table): void {
            $table->unsignedInteger('id_report')->primary();
            $table->string('nama_report');
            $table->string('table_name');
            $table->boolean('active');
            $table->string('import_controller');
            $table->boolean('requires_manual_periode');
            $table->timestamps();
        });
        (require database_path('migrations/2026_11_12_000001_create_evaluasi_aktivitas_kunjungan_pemasar_report.php'))->up();
        (require database_path('migrations/2026_11_12_000002_add_printed_period_to_evaluasi_kunjungan_pemasar.php'))->up();
        session(['active_id_report' => DB::table('nama_report')->where('table_name', EvaluasiKunjunganSourceService::TABLE)->value('id_report')]);
    }

    private function reportQueries(): array
    {
        return array_values(array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains(strtolower($query['query']), 'nama_report')));
    }

    private function canonical(int $count): string
    {
        $path = storage_path('framework/testing/kunjungan-preview-'.bin2hex(random_bytes(8)).'.csv');
        $this->files[] = $path;
        $output = fopen($path, 'wb');
        fputcsv($output, EvaluasiKunjunganSourceService::IMPORT_COLUMNS, ',', '"', '');
        for ($index = 1; $index <= $count; $index++) {
            fputcsv($output, $this->row($index), ',', '"', '');
        }
        fclose($output);

        return $path;
    }

    private function row(int $index): array
    {
        return [
            (string) $index, str_pad((string) $index, 24, '0', STR_PAD_LEFT), '2026-11-12', '-7.602666', '111.591637', 'kunjungan_pemasaran_pinjaman',
            '00123456', 'Mantri Test', 'Mantri', '0034', 'UNIT TEST', '0045', 'KC Test', 'R', 'KANWIL TEST', 'DONE',
            ' C:\\lokasi\\folder\\, "rumah" ', '000CIF', 'Debitur Test', "baris satu\nbaris dua", 'REFERRAL', '9007199254740993.01', 'Kelolaan', '\\N', '2026-11-12', '2026-11-12 09:16:45',
        ];
    }
}
