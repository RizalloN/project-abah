<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use App\Http\Controllers\Import\ImportFileController;
use App\Http\Controllers\Import\ImportIndexController;
use App\Services\Import\ExcelQueuedImportService;
use App\Services\Import\ImportPeriodGuardService;
use App\Services\Import\ImportStrategyFactory;
use App\Services\Import\PipelinePrewashSourceService as Source;
use App\Services\Import\SchemaDrift\SchemaDriftImportGuard;
use App\Services\Import\Strategies\PipelinePrewashImportStrategy;
use App\Support\PipelinePrewashService;
use App\Support\ReportDataSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class PipelinePrewashIntegrationTest extends TestCase
{
    private array $files = [];

    private int $reportId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Cache::flush();
        Queue::fake();

        Schema::create('nama_report', function (Blueprint $table): void {
            $table->integer('id_report')->primary();
            $table->string('nama_report');
            $table->string('table_name');
            $table->boolean('active');
            $table->string('import_controller')->nullable();
            $table->boolean('requires_manual_periode')->default(false);
            $table->string('manual_periode_type')->nullable();
            $table->string('manual_periode_label')->nullable();
            $table->string('manual_periode_help')->nullable();
            $table->timestamps();
        });
        Schema::create('import_jobs', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('id_report');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('jobs', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('queue');
            $table->integer('reserved_at')->nullable();
            $table->integer('available_at')->nullable();
            $table->integer('created_at')->nullable();
            $table->longText('payload');
        });
        (require database_path('migrations/2026_11_12_000003_create_pipeline_prewash_report.php'))->up();
        $this->reportId = (int) DB::table('nama_report')->where('table_name', Source::TABLE)->value('id_report');

        $sync = Mockery::mock(ReportDataSyncService::class);
        $sync->shouldReceive('resolvePostDeleteMaintenanceMode')->with(Source::TABLE)->andReturn('lightweight');
        $sync->shouldReceive('syncAfterDeleteLightweight')->andReturnNull();
        app()->instance(ReportDataSyncService::class, $sync);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    public function test_registry_factory_period_policy_and_schema_guard_agree_on_the_exact_year_contract(): void
    {
        $this->assertDatabaseHas('nama_report', [
            'table_name' => Source::TABLE, 'nama_report' => 'Pipeline Prewash',
            'import_controller' => 'ImportExcelController', 'requires_manual_periode' => 1,
            'manual_periode_type' => 'number',
        ]);
        $strategy = app(ImportStrategyFactory::class)->resolve((object) ['table_name' => Source::TABLE]);
        $this->assertInstanceOf(PipelinePrewashImportStrategy::class, $strategy);
        $this->assertSame('pipeline_prewash_exact', $strategy->importMode());
        $this->assertSame(['column' => 'tahun', 'type' => 'year'], app(ImportPeriodGuardService::class)->policyFor(Source::TABLE));
        (require database_path('migrations/2026_10_20_000001_create_import_schema_drift_tables.php'))->up();
        (require database_path('migrations/2026_10_20_000002_create_import_source_header_baselines_table.php'))->up();

        $review = app(SchemaDriftImportGuard::class)->assertReady(Source::TABLE, $this->canonical());

        $this->assertSame(18, $review['stats']['matched']);
        $this->assertFalse($review['has_blocking_issue']);
        $this->assertFalse($review['has_pending_decision']);
        $this->assertSame(0, DB::table(Source::TABLE)->count());
    }

    public function test_controller_preview_preserves_every_source_digit_and_only_exposes_imported_columns(): void
    {
        $controller = new class extends ImportExcelController
        {
            protected function resolveActiveTableName(string $default = 'daily_loan_dinamis'): string
            {
                return Source::TABLE;
            }
        };
        $values = $this->sourceValues();

        $preview = (new ReflectionMethod(ImportExcelController::class, 'prepareCsvPreviewPayload'))->invoke($controller, $this->canonical($values));

        $this->assertSame(Source::IMPORT_COLUMNS, $preview['headers']);
        $this->assertSame(array_values($values), $preview['preview'][0]);
        $this->assertSame('000123456789012', $preview['preview'][0][4]);
        $this->assertSame('9007199254740993.01', $preview['preview'][0][8]);
        $this->assertSame('2026', $preview['preview'][0][17]);
        $this->assertSame(2, $preview['total_rows']);
        $this->assertArrayNotHasKey('jan', array_flip($preview['headers']));
        $this->assertArrayNotHasKey('plafond_real', array_flip($preview['headers']));
    }

    #[DataProvider('invalidYears')]
    public function test_upload_rejects_an_invalid_year_before_calling_any_source_parser(string $year): void
    {
        $source = Mockery::mock(Source::class);
        $source->shouldNotReceive('stage');
        app()->instance(Source::class, $source);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tahun');
        (new ReflectionMethod(ImportExcelController::class, 'normalizeSsaSpreadsheetUpload'))->invoke(
            new ImportExcelController, Source::TABLE, 'excel_imports/pipeline-source.xlsx', $year,
        );
    }

    public static function invalidYears(): array
    {
        return [
            'missing' => [''], 'short' => ['26'], 'decimal' => ['2026.0'],
            'date' => ['2026-11-12'], 'outside lower bound' => ['1899'],
            'outside upper bound' => ['2200'], 'scientific notation' => ['2.026e3'],
        ];
    }

    public function test_valid_year_still_rejects_a_non_xlsx_upload_before_parsing(): void
    {
        $source = Mockery::mock(Source::class);
        $source->shouldNotReceive('stage');
        app()->instance(Source::class, $source);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('XLSX');
        (new ReflectionMethod(ImportExcelController::class, 'normalizeSsaSpreadsheetUpload'))->invoke(
            new ImportExcelController, Source::TABLE, 'excel_imports/pipeline-source.csv', '2026',
        );
    }

    public function test_period_guard_rejects_a_missing_mapping_and_invalid_row_year(): void
    {
        $guard = app(ImportPeriodGuardService::class);
        $guard->assertRow(Source::TABLE, ['tahun' => '2026']);
        foreach ([['columns' => Source::BASE_COLUMNS], ['row' => ['tahun' => '2026-11-12']], ['row' => ['tahun' => null]]] as $case) {
            try {
                isset($case['columns'])
                    ? $guard->assertMappedColumns(Source::TABLE, $case['columns'])
                    : $guard->assertRow(Source::TABLE, $case['row'], 2);
                $this->fail('Invalid year contract was accepted.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('tahun', $error->getMessage());
            }
        }
    }

    public function test_queue_calls_the_exact_service_once_and_emits_success_only_after_verified_import(): void
    {
        $canonical = $this->canonical();
        $filters = [7 => ['KC Madiun']];
        $service = Mockery::mock(Source::class);
        $service->shouldReceive('import')->once()->with($canonical, Source::IMPORT_COLUMNS, $filters, Mockery::type('callable'))
            ->andReturnUsing(static function ($path, $headers, $activeFilters, $progress): int {
                $progress(1, 1);

                return 1;
            });
        app()->instance(Source::class, $service);
        $analytics = Mockery::mock(PipelinePrewashService::class);
        $analytics->shouldReceive('refresh')->once()->with(2026, true)->andReturn(['available' => true]);
        app()->instance(PipelinePrewashService::class, $analytics);
        $events = $updates = [];
        $result = (new ExcelQueuedImportService)->execute([
            'job_id' => 123, 'headers' => Source::IMPORT_COLUMNS,
            'params' => ['file_path' => $canonical, 'table_name' => Source::TABLE, 'active_filters' => $filters, 'total_rows' => 1],
        ], [
            'resolve_import_strategy' => static fn () => new PipelinePrewashImportStrategy,
            'mark_failed' => fn () => $this->fail('Verified exact import must not be marked failed.'),
            'find_job' => static fn () => null,
            'update_job' => static function ($jobId, $attributes, $payload) use (&$updates): void {
                $updates[] = [$jobId, $attributes, $payload];
            },
            'assert_transactional_table' => static fn () => null,
            'assert_duplicate_guard' => static fn () => null,
            'is_csv_file' => fn () => $this->fail('Pipeline Prewash must never enter generic CSV conversion.'),
            'cleanup_successful_import_artifacts' => static fn () => null,
        ], static function ($event, $payload) use (&$events): void {
            $events[] = [$event, $payload];
        });

        $this->assertSame('completed', $result['status']);
        $this->assertSame(1, $result['total_success']);
        $this->assertSame(0, $result['total_failed']);
        $this->assertSame('completed', $updates[0][1]['status']);
        $this->assertSame(1, count(array_filter($events, static fn (array $event): bool => $event[0] === 'complete')));
        $this->assertSame('progress', $events[0][0]);
        $this->assertSame('complete', $events[array_key_last($events)][0]);
        $this->assertSame(100, $events[array_key_last($events)][1]['percent']);
    }

    public function test_queue_keeps_a_rejected_exact_source_failed_and_never_falls_back_to_generic_import(): void
    {
        $service = Mockery::mock(Source::class);
        $service->shouldReceive('import')->once()->andThrow(new RuntimeException('Presisi angka tidak valid; transaksi dibatalkan.'));
        app()->instance(Source::class, $service);
        $events = $failures = [];
        $result = (new ExcelQueuedImportService)->execute([
            'job_id' => 124, 'headers' => Source::IMPORT_COLUMNS,
            'params' => ['file_path' => $this->canonical(), 'table_name' => Source::TABLE, 'total_rows' => 1],
        ], [
            'resolve_import_strategy' => static fn () => new PipelinePrewashImportStrategy,
            'mark_failed' => static function ($jobId, $message, $success, $failed) use (&$failures): void {
                $failures[] = [$jobId, $message, $success, $failed];
            },
            'find_job' => static fn () => (object) ['status' => 'processing', 'total_success' => 0, 'total_failed' => 0, 'total_files' => 1],
            'update_job' => fn () => $this->fail('Rejected exact import must not mark the job completed.'),
            'assert_transactional_table' => static fn () => null,
            'assert_duplicate_guard' => static fn () => null,
            'is_csv_file' => fn () => $this->fail('Rejected source must not use generic import.'),
            'cleanup_successful_import_artifacts' => fn () => $this->fail('Rejected source must remain available for inspection.'),
        ], static function ($event, $payload) use (&$events): void {
            $events[] = [$event, $payload];
        });

        $this->assertSame('failed', $result['status']);
        $this->assertSame(0, $result['total_success']);
        $this->assertSame(124, $failures[0][0]);
        $this->assertStringContainsString('Presisi angka tidak valid', $failures[0][1]);
        $this->assertSame(['error'], array_column($events, 0));
    }

    public function test_browser_chunk_endpoint_rejects_this_report_after_target_and_session_checks(): void
    {
        $this->withSession(['active_id_report' => $this->reportId, 'excel_import_params' => ['job_id' => 44]]);
        $request = Request::create('/import/excel/chunk', 'POST', [
            'job_id' => 44, 'table_name' => Source::TABLE, 'header_index' => 0,
            'start_row' => 1, 'chunk_size' => 100, 'file_path' => 'excel_imports/pipeline-source.csv',
        ]);
        $request->setLaravelSession(app('session.store'));

        $response = (new ImportExcelController)->processExcelChunk($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('error', $response->getData(true)['status']);
        $this->assertStringContainsString('antrean import presisi', $response->getData(true)['message']);
        $this->assertSame(0, DB::table('import_jobs')->count());
    }

    public function test_legacy_mapping_cannot_bypass_the_exact_source_import(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('alur Excel/CSV presisi');
        (new ReflectionMethod(ImportFileController::class, 'assertImportPeriodMapping'))->invoke(
            new ImportFileController, Source::TABLE,
            array_map(static fn ($column): array => ['column' => $column], Source::IMPORT_COLUMNS),
        );
    }

    public function test_management_groups_by_year_and_branch_and_scoped_delete_preserves_all_survivor_values(): void
    {
        $this->seedRows();
        $controller = app(ImportIndexController::class);
        $management = $controller->reportManagementData(Request::create('/import/report-management/data', 'POST', ['id_report' => $this->reportId]));
        $groups = $management->getData(true);
        $this->assertSame(200, $management->status());
        $this->assertSame('tahun', $groups['period_column']);
        $this->assertSame('nama_kanca', $groups['kanca_column']);
        $this->assertSame(3, $groups['total_groups']);
        $madiun = collect($groups['rows'])->first(static fn (array $row): bool => (string) $row['period'] === '2026' && $row['kanca'] === 'KC Madiun');
        $this->assertNotNull($madiun);
        $this->assertSame(2, $madiun['row_count']);
        $survivors = DB::table(Source::TABLE)->whereIn('uniqueid_namareport', ['p3', 'p4'])->orderBy('uniqueid_namareport')->get()->toArray();

        $response = $this->deleteScopes([['period' => '2026', 'kanca' => 'KC Madiun']]);

        $this->assertSame('completed', $response->getData(true)['status']);
        $this->assertSame(2, $response->getData(true)['deleted_rows']);
        $this->assertEquals($survivors, DB::table(Source::TABLE)->orderBy('uniqueid_namareport')->get()->toArray());
        Queue::assertNothingPushed();
    }

    #[DataProvider('invalidDeleteScopes')]
    public function test_invalid_year_delete_scopes_fail_closed(array $scopes): void
    {
        $this->seedRows();

        $response = $this->deleteScopes($scopes);

        $this->assertSame(422, $response->status());
        $this->assertSame(4, DB::table(Source::TABLE)->count());
    }

    public static function invalidDeleteScopes(): array
    {
        return [
            'missing dimensions' => [[[]]],
            'branch without year' => [[['kanca' => 'KC Madiun']]],
            'month supplied for year' => [[['period' => '2026-11', 'kanca' => 'KC Madiun']]],
            'invalid year' => [[['period' => '2026.0', 'kanca' => 'KC Madiun']]],
            'outside year range' => [[['period' => '2200', 'kanca' => 'KC Madiun']]],
            'mixed valid and invalid' => [[['period' => '2026', 'kanca' => 'KC Madiun'], ['period' => 'invalid', 'kanca' => 'KC Ngawi']]],
        ];
    }

    #[DataProvider('activeImportStatuses')]
    public function test_active_import_of_this_report_blocks_delete_before_mutation(string $status): void
    {
        $this->seedRows();
        DB::table('import_jobs')->insert(['id_report' => $this->reportId, 'status' => $status]);

        $response = $this->deleteScopes([['period' => '2026', 'kanca' => 'KC Madiun']]);

        $this->assertSame(409, $response->status());
        $this->assertSame(4, DB::table(Source::TABLE)->count());
        Queue::assertNothingPushed();
    }

    public static function activeImportStatuses(): array
    {
        return [['queued'], ['staging'], ['processing']];
    }

    public function test_completed_own_import_and_active_other_report_do_not_block_a_scoped_delete(): void
    {
        $this->seedRows();
        DB::table('import_jobs')->insert([
            ['id_report' => $this->reportId, 'status' => 'completed'],
            ['id_report' => $this->reportId + 1, 'status' => 'processing'],
        ]);

        $result = $this->deleteScopes([['period' => '2026', 'kanca' => 'KC Madiun']])->getData(true);

        $this->assertSame('completed', $result['status']);
        $this->assertSame(2, $result['deleted_rows']);
    }

    public function test_import_starting_after_delete_preparation_is_rechecked_before_batch_mutation(): void
    {
        $this->seedRows();
        $controller = app(ImportIndexController::class);
        $request = Request::create('/import/report-management/delete', 'POST', [
            'id_report' => $this->reportId, 'scopes' => [['period' => '2026', 'kanca' => 'KC Madiun']],
            'force' => true, 'hard_force' => true,
        ]);
        [$prepared, $error] = (new ReflectionMethod($controller, 'prepareManagedDelete'))->invoke($controller, $request);
        $this->assertNull($error);
        $this->assertSame(2, $prepared['candidate_rows']);
        DB::table('import_jobs')->insert(['id_report' => $this->reportId, 'status' => 'processing']);

        try {
            (new ReflectionMethod($controller, 'deleteScopedRows'))->invoke(
                $controller, Source::TABLE, DB::table(Source::TABLE)->where('tahun', 2026)->where('nama_kanca', 'KC Madiun'),
                'uniqueid_namareport', 2, 'tahun', 'nama_kanca', ['period_filter' => '2026', 'kanca_filter' => 'KC Madiun'],
            );
            $this->fail('An import starting after preparation must stop deletion.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('import', strtolower($error->getMessage()));
        }
        $this->assertSame(4, DB::table(Source::TABLE)->count());
    }

    public function test_year_delete_predicates_keep_exact_bindings_and_indexable_sql(): void
    {
        $controller = app(ImportIndexController::class);
        [$query] = (new ReflectionMethod($controller, 'buildDeleteScopeQuery'))->invoke(
            $controller, Source::TABLE, 'tahun', 'nama_kanca', '2026', 'KC Madiun', false, false,
        );
        $this->assertSame(['2026', 'KC Madiun'], array_map('strval', $query->getBindings()));
        $sql = strtoupper($query->toSql());
        foreach (['DATE(', 'SUBSTR(', 'CAST(', 'LIKE'] as $nonExactPredicate) {
            $this->assertStringNotContainsString($nonExactPredicate, $sql);
        }
    }

    private function canonical(?array $values = null): string
    {
        $path = tempnam(storage_path('framework/testing'), 'prewash_integration_');
        $this->files[] = $path;
        $handle = fopen($path, 'wb');
        fputcsv($handle, Source::IMPORT_COLUMNS);
        fputcsv($handle, array_values($values ?? $this->sourceValues()));
        fclose($handle);

        return $path;
    }

    private function sourceValues(): array
    {
        return [
            'nama_debt' => 'Debitur asli', 'alamat' => 'Alamat asli, Madiun', 'nomorhandphone' => '081234567890',
            'cif' => '0000123', 'norek_pinjaman' => '000123456789012', 'branch' => '00123',
            'nama_unit' => 'Unit Gemarang', 'nama_kanca' => 'KC Madiun', 'plafond' => '9007199254740993.01',
            'os' => '12345.67', 'sumberpipeline' => 'SLIK Hijau', 'pn_mantri' => '001234',
            'nama_mantri' => 'Mantri asli', 'product_rekomendasi' => 'KUPEDES', 'score' => '01.25',
            'idpipeline_lama' => '0001', 'sumberpipeline_lama' => 'Pipeline lama', 'tahun' => '2026',
        ];
    }

    private function seedRows(): void
    {
        foreach ([['p1', 2026, 'KC Madiun'], ['p2', 2026, 'KC Madiun'], ['p3', 2026, 'KC Ngawi'], ['p4', 2025, 'KC Madiun']] as [$id, $year, $branch]) {
            DB::table(Source::TABLE)->insert(array_replace($this->sourceValues(), [
                'uniqueid_namareport' => $id, 'tahun' => $year, 'nama_kanca' => $branch,
                'plafond' => '1000.01', 'os' => '500.02', 'jan' => 'DONE', 'jumlah_kunjungan' => 1,
                'kunjungan' => 'DONE', 'plafond_real' => '250.03', 'status' => 'Sudah',
                'created_at' => '2026-11-12 09:00:00', 'updated_at' => '2026-11-12 09:00:00',
            ]));
        }
    }

    private function deleteScopes(array $scopes)
    {
        return app(ImportIndexController::class)->deleteManagedReportRows(Request::create('/import/report-management/delete', 'POST', [
            'id_report' => $this->reportId, 'scopes' => $scopes, 'force' => true, 'hard_force' => true,
        ]));
    }
}
