<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use App\Http\Controllers\Import\ImportFileController;
use App\Services\Import\EvaluasiKunjunganSourceService;
use App\Services\Import\ExcelQueuedImportService;
use App\Services\Import\ImportPeriodGuardService;
use App\Services\Import\ImportStrategyFactory;
use App\Services\Import\SchemaDrift\SchemaDriftAnalyzer;
use App\Services\Import\SchemaDrift\SchemaDriftImportGuard;
use App\Services\Import\Strategies\EvaluasiKunjunganImportStrategy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class EvaluasiKunjunganImportTest extends TestCase
{
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
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

    public function test_original_export_preamble_is_removed_while_every_source_cell_is_preserved(): void
    {
        $row = $this->row();
        $row[16] = ' C:\\lokasi\\folder\\, "rumah" ';
        $row[19] = "baris satu\nbaris dua\tbertemu";
        $row[23] = '\\N';
        $source = $this->source([$row]);
        $destination = $this->path();

        $result = (new EvaluasiKunjunganSourceService)->stage($source, $destination);
        $stored = $this->readCsv($destination);

        $this->assertSame(1, $result['rows']);
        $this->assertSame(EvaluasiKunjunganSourceService::IMPORT_COLUMNS, $stored[0]);
        $this->assertSame($row, array_slice($stored[1], 0, 24));
        $this->assertSame(['2026-11-12', '2026-11-12 09:16:45'], array_slice($stored[1], 24));
        $this->assertSame('00123456789012345678901234567890', $stored[1][1]);
        $this->assertSame('00144675', $stored[1][6]);
        $this->assertSame('-7.602666', $stored[1][3]);
        $this->assertSame('9007199254740993.01', $stored[1][21]);
        $this->assertCount(4, $this->readCsv($source));
    }

    public function test_bulk_distinguishes_empty_numeric_null_from_zero_and_preserves_special_text(): void
    {
        $first = $this->row();
        $first[3] = $first[4] = $first[21] = '';
        $first[19] = '';
        $first[23] = "literal \\N, \"quote\", slash\\\nline\tend\r";
        $second = $this->row();
        $second[1] = '2';
        $second[3] = '-0.000001';
        $second[4] = '0.000000';
        $second[21] = '0.00';
        $canonical = $this->canonical([$first, $second]);
        $bulk = $this->path();

        $result = (new EvaluasiKunjunganSourceService)->prepareBulk($canonical, $bulk, EvaluasiKunjunganSourceService::IMPORT_COLUMNS);
        $lines = file($bulk, FILE_IGNORE_NEW_LINES);

        $this->assertSame(2, $result['rows']);
        $this->assertSame(2, $result['scanned']);
        $this->assertCount(2, $lines);
        $this->assertStringContainsString(',\\N,\\N,', $lines[0]);
        $this->assertStringContainsString(',"",', $lines[0]);
        $this->assertStringContainsString(',\\N,"Kelolaan",', $lines[0]);
        $this->assertStringContainsString('"literal \\\\N, \\"quote\\", slash\\\\\\nline\\tend\\r"', $lines[0]);
        $this->assertStringContainsString('"-0.000001","0.000000"', $lines[1]);
        $this->assertStringContainsString('"0.00"', $lines[1]);
        $this->assertStringContainsString('"kunjungan_2"', $lines[1]);
        $this->assertCount(29, $result['columns']);
    }

    public function test_bulk_large_decimal_and_leading_zero_identifiers_are_not_cast_or_rounded(): void
    {
        $row = $this->row();
        $row[21] = '-9999999999999999999999999999.99';
        $canonical = $this->canonical([$row]);
        $bulk = $this->path();

        (new EvaluasiKunjunganSourceService)->prepareBulk($canonical, $bulk, EvaluasiKunjunganSourceService::IMPORT_COLUMNS);

        $contents = file_get_contents($bulk);
        $this->assertStringContainsString('"-9999999999999999999999999999.99"', $contents);
        $this->assertStringContainsString('"00144675"', $contents);
        $this->assertStringContainsString('"00123456789012345678901234567890"', $contents);
        $this->assertStringContainsString('"0045"', $contents);
    }

    public function test_filters_follow_reordered_preview_headers_without_changing_source_values(): void
    {
        $selected = $this->row();
        $selected[12] = ' KC Madiun ';
        $other = $this->row();
        $other[1] = '2';
        $other[12] = 'KC Ngawi';
        $canonical = $this->canonical([$selected, $other]);
        $bulk = $this->path();
        $headers = array_reverse(EvaluasiKunjunganSourceService::IMPORT_COLUMNS);
        $filterIndex = array_search('main_branch', $headers, true);

        $result = (new EvaluasiKunjunganSourceService)->prepareBulk($canonical, $bulk, $headers, [$filterIndex => ['KC Madiun']]);

        $this->assertSame(2, $result['scanned']);
        $this->assertSame(1, $result['rows']);
        $this->assertStringContainsString('" KC Madiun "', file_get_contents($bulk));
        $this->assertStringNotContainsString('KC Ngawi', file_get_contents($bulk));
    }

    public function test_invalid_unselected_row_still_aborts_staging_before_database_write(): void
    {
        $selected = $this->row();
        $invalid = $this->row();
        $invalid[1] = '2';
        $invalid[12] = 'KC Ngawi';
        $invalid[21] = '1,000.00';
        $canonical = $this->canonical([$selected, $invalid]);
        $bulk = $this->path();
        try {
            (new EvaluasiKunjunganSourceService)->prepareBulk($canonical, $bulk, EvaluasiKunjunganSourceService::IMPORT_COLUMNS, [12 => ['KC Madiun']]);
            $this->fail('Malformed source number was silently accepted.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('PERKIRAAN_PLAFOND', $error->getMessage());
            $this->assertFileDoesNotExist($bulk);
        }
    }

    public static function invalidCells(): array
    {
        return [
            'non numeric activity ID' => [1, 'ID-12'],
            'impossible date' => [2, '2026-02-30'],
            'non ISO date' => [2, '12/11/2026'],
            'latitude excess scale' => [3, '-7.6026661'],
            'longitude excess integer precision' => [4, '1000000000000.000000'],
            'scientific notation' => [21, '1e6'],
            'ambiguous thousands comma' => [21, '1,000.00'],
            'ambiguous thousands dot' => [21, '1.000.000,00'],
            'amount excess decimals' => [21, '1000.001'],
            'amount overflow' => [21, '10000000000000000000000000000.00'],
            'text overflow' => [7, ''],
            'invalid UTF8' => [7, "\xC3\x28"],
            'NUL text' => [19, "teks\0lain"],
        ];
    }

    #[DataProvider('invalidCells')]
    public function test_invalid_cells_abort_without_leaving_partial_staging(int $column, string $value): void
    {
        $row = $this->row();
        $row[$column] = $column === 7 && $value === '' ? str_repeat('a', 256) : $value;
        $source = $this->source([$this->row(), $row]);
        $destination = $this->path();

        try {
            (new EvaluasiKunjunganSourceService)->stage($source, $destination);
            $this->fail('Invalid source cell was silently accepted.');
        } catch (RuntimeException $error) {
            $this->assertNotSame('', $error->getMessage());
            $this->assertFileDoesNotExist($destination);
            $this->assertFileExists($source);
        }
    }

    public function test_nonblank_short_row_is_rejected_instead_of_padded(): void
    {
        $source = $this->source([array_slice($this->row(), 0, 23)]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Jumlah kolom');
        (new EvaluasiKunjunganSourceService)->stage($source, $this->path());
    }

    public function test_unknown_header_cannot_shift_or_drop_a_source_column(): void
    {
        $headers = EvaluasiKunjunganSourceService::SOURCE_HEADERS;
        $headers[21] = 'NOMINAL_LAIN';
        $source = $this->csv([['Date Printed : 12 Nov 2026 09:16:45 AM'], $headers, $this->row()]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Header CSV');
        (new EvaluasiKunjunganSourceService)->stage($source, $this->path());
    }

    public function test_factory_and_period_guard_select_this_reports_own_contract(): void
    {
        $strategy = app(ImportStrategyFactory::class)->resolve((object) ['table_name' => EvaluasiKunjunganSourceService::TABLE]);
        $this->assertInstanceOf(EvaluasiKunjunganImportStrategy::class, $strategy);
        $this->assertSame(['ok' => true], $strategy->validateSchema([...EvaluasiKunjunganSourceService::IMPORT_COLUMNS, 'uniqueid_namareport', 'created_at', 'updated_at']));
        $this->assertFalse($strategy->validateSchema(['id_aktivitas'])['ok']);
        $this->assertSame(['column' => 'periode', 'type' => 'date'], app(ImportPeriodGuardService::class)->policyFor(EvaluasiKunjunganSourceService::TABLE));
    }

    public function test_actual_csv_preview_keeps_all_digits_in_identifiers_coordinates_and_amounts(): void
    {
        Schema::create(EvaluasiKunjunganSourceService::TABLE, function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            foreach (EvaluasiKunjunganSourceService::IMPORT_COLUMNS as $column) {
                $table->text($column)->nullable();
            }
            $table->timestamps();
        });
        $canonical = $this->canonical([$this->row()]);
        $controller = new class extends ImportExcelController
        {
            protected function resolveActiveTableName(string $default = 'daily_loan_dinamis'): string
            {
                return EvaluasiKunjunganSourceService::TABLE;
            }
        };

        $preview = (new ReflectionMethod(ImportExcelController::class, 'prepareCsvPreviewPayload'))->invoke($controller, $canonical);

        $this->assertSame(EvaluasiKunjunganSourceService::IMPORT_COLUMNS, $preview['headers']);
        $this->assertSame([...$this->row(), '2026-11-12', '2026-11-12 09:16:45'], $preview['preview'][0]);
        $this->assertSame(2, $preview['total_rows']);
    }

    public function test_actual_migration_and_schema_review_guard_accept_valid_canonical_source_without_pending_decisions(): void
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
        (require database_path('migrations/2026_10_20_000001_create_import_schema_drift_tables.php'))->up();
        (require database_path('migrations/2026_10_20_000002_create_import_source_header_baselines_table.php'))->up();
        $canonical = $this->path();
        (new EvaluasiKunjunganSourceService)->stage($this->source([$this->row()]), $canonical);

        $report = app(SchemaDriftAnalyzer::class)->analyze(EvaluasiKunjunganSourceService::TABLE, $canonical, ['record' => false, 'remember' => false]);
        $this->assertSame(26, $report['stats']['matched']);
        $this->assertSame(0, $report['stats']['unmapped']);
        $this->assertFalse($report['has_blocking_issue'], json_encode($report['adaptations']));
        $this->assertFalse($report['has_pending_decision'], json_encode($report['adaptations']));
        $this->assertTrue($report['source']['structural_scan']['complete']);

        $guarded = app(SchemaDriftImportGuard::class)->assertReady(EvaluasiKunjunganSourceService::TABLE, $canonical);
        $this->assertSame(26, $guarded['stats']['matched']);
        $this->assertFalse($guarded['has_blocking_issue']);
        $this->assertFalse($guarded['has_pending_decision']);
        $this->assertDatabaseHas('nama_report', [
            'table_name' => EvaluasiKunjunganSourceService::TABLE,
            'nama_report' => 'Evaluasi Aktivitas Kunjungan Pemasar',
            'import_controller' => 'ImportExcelController',
        ]);
    }

    public function test_queue_uses_exact_import_service_and_emits_completed_only_after_service_success(): void
    {
        $canonical = $this->canonical([$this->row()]);
        $headers = EvaluasiKunjunganSourceService::IMPORT_COLUMNS;
        $service = Mockery::mock(EvaluasiKunjunganSourceService::class);
        $service->shouldReceive('import')->once()->with($canonical, $headers, [12 => ['KC Madiun']], Mockery::type('callable'))->andReturn(1);
        $this->app->instance(EvaluasiKunjunganSourceService::class, $service);
        $events = $updates = [];
        $result = (new ExcelQueuedImportService)->execute([
            'job_id' => 123, 'headers' => $headers,
            'params' => ['file_path' => $canonical, 'table_name' => EvaluasiKunjunganSourceService::TABLE, 'active_filters' => [12 => ['KC Madiun']], 'total_rows' => 1],
        ], [
            'resolve_import_strategy' => fn () => new EvaluasiKunjunganImportStrategy,
            'mark_failed' => fn () => $this->fail('Exact import unexpectedly marked failed.'),
            'find_job' => fn () => null,
            'update_job' => function ($jobId, $attributes, $payload) use (&$updates): void {
                $updates[] = [$jobId, $attributes, $payload];
            },
            'assert_transactional_table' => fn () => null,
            'assert_duplicate_guard' => fn () => null,
            'cleanup_successful_import_artifacts' => fn () => null,
        ], function ($event, $payload) use (&$events): void {
            $events[] = [$event, $payload];
        });

        $this->assertSame('completed', $result['status']);
        $this->assertSame(1, $result['total_success']);
        $this->assertSame(0, $result['total_failed']);
        $this->assertSame(123, $updates[0][0]);
        $this->assertSame('completed', $updates[0][1]['status']);
        $this->assertSame('complete', $events[0][0]);
    }

    public function test_queue_does_not_report_success_when_exact_import_rejects_a_mysql_warning(): void
    {
        $canonical = $this->canonical([$this->row()]);
        $service = Mockery::mock(EvaluasiKunjunganSourceService::class);
        $service->shouldReceive('import')->once()->andThrow(new RuntimeException('ID duplikat atau warning MySQL; transaksi dibatalkan.'));
        $this->app->instance(EvaluasiKunjunganSourceService::class, $service);
        $events = $failures = [];
        $result = (new ExcelQueuedImportService)->execute([
            'job_id' => 124, 'headers' => EvaluasiKunjunganSourceService::IMPORT_COLUMNS,
            'params' => ['file_path' => $canonical, 'table_name' => EvaluasiKunjunganSourceService::TABLE, 'total_rows' => 1],
        ], [
            'resolve_import_strategy' => fn () => new EvaluasiKunjunganImportStrategy,
            'mark_failed' => function ($jobId, $message, $success, $failed) use (&$failures): void {
                $failures[] = [$jobId, $message, $success, $failed];
            },
            'find_job' => fn () => (object) ['status' => 'processing', 'total_success' => 0, 'total_failed' => 0, 'total_files' => 1],
            'update_job' => fn () => $this->fail('Failed exact import must not mark the job completed.'),
            'assert_transactional_table' => fn () => null,
            'assert_duplicate_guard' => fn () => null,
            'cleanup_successful_import_artifacts' => fn () => $this->fail('Failed source must be retained for inspection.'),
        ], function ($event, $payload) use (&$events): void {
            $events[] = [$event, $payload];
        });

        $this->assertSame('failed', $result['status']);
        $this->assertSame(0, $result['total_success']);
        $this->assertSame(124, $failures[0][0]);
        $this->assertStringContainsString('transaksi dibatalkan', $failures[0][1]);
        $this->assertSame(['error'], array_column($events, 0));
    }

    public function test_browser_chunk_endpoint_rejects_this_report_after_matching_session_and_target_checks(): void
    {
        Schema::create('nama_report', function (Blueprint $table): void {
            $table->unsignedInteger('id_report')->primary();
            $table->string('table_name');
        });
        DB::table('nama_report')->insert(['id_report' => 987, 'table_name' => EvaluasiKunjunganSourceService::TABLE]);
        $this->withSession(['active_id_report' => 987, 'excel_import_params' => ['job_id' => 44]]);
        $request = Request::create('/import/excel/chunk', 'POST', [
            'job_id' => 44, 'table_name' => EvaluasiKunjunganSourceService::TABLE,
            'header_index' => 0, 'start_row' => 1, 'chunk_size' => 100,
            'file_path' => 'excel_imports/evaluasi-source.csv',
        ]);
        $request->setLaravelSession(app('session.store'));

        $response = (new ImportExcelController)->processExcelChunk($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('error', $response->getData(true)['status']);
        $this->assertStringContainsString('antrean import presisi', $response->getData(true)['message']);
        // No import_jobs table exists: the guarded endpoint never reaches a write path.
        $this->assertFalse(Schema::hasTable('import_jobs'));
    }

    public function test_legacy_generic_mapping_cannot_bypass_the_exact_import_path(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('alur Excel/CSV presisi');
        (new ReflectionMethod(ImportFileController::class, 'assertImportPeriodMapping'))->invoke(
            new ImportFileController,
            EvaluasiKunjunganSourceService::TABLE,
            array_map(static fn ($column): array => ['column' => $column], EvaluasiKunjunganSourceService::IMPORT_COLUMNS),
        );
    }

    private function row(): array
    {
        return [
            '000001', '00123456789012345678901234567890', '2026-11-12', '-7.602666', '111.591637', 'kunjungan_pemasaran_pinjaman',
            '00144675', 'Mantri Contoh', 'Mantri', '0034', 'UNIT CONTOH', '0045', 'KC Madiun', 'R', 'KANWIL MALANG', 'DONE',
            'Jalan Contoh, Jawa Timur', '0000001', 'Debitur Contoh', 'bertemu', 'REFERRAL', '9007199254740993.01', 'Kelolaan', 'selesai',
        ];
    }

    private function source(array $rows): string
    {
        return $this->csv([
            ['ReportName', 'DatePrinted', ...array_fill(0, 22, '')],
            ['Evaluasi Aktivitas Kunjungan Pemasaran', 'Date Printed : 12 Nov 2026 09:16:45 AM', ...array_fill(0, 22, '')],
            EvaluasiKunjunganSourceService::SOURCE_HEADERS, ...$rows,
        ]);
    }

    private function canonical(array $rows): string
    {
        return $this->csv([EvaluasiKunjunganSourceService::IMPORT_COLUMNS, ...array_map(static fn (array $row): array => [...$row, '2026-11-12', '2026-11-12 09:16:45'], $rows)]);
    }

    private function csv(array $rows): string
    {
        $path = $this->path();
        $handle = fopen($path, 'wb');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }
        fclose($handle);

        return $path;
    }

    private function readCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'rb');
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    private function path(): string
    {
        $path = storage_path('framework/testing/evaluasi-kunjungan-'.bin2hex(random_bytes(8)).'.csv');
        $this->files[] = $path;

        return $path;
    }
}
