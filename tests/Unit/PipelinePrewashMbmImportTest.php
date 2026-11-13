<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use App\Services\Import\ExcelQueuedImportService;
use App\Services\Import\ImportPeriodGuardService;
use App\Services\Import\ImportStrategyFactory;
use App\Services\Import\PipelinePrewashMbmSourceService;
use App\Services\Import\SchemaDrift\SchemaDriftImportGuard;
use App\Services\Import\Strategies\GenericCsvImportStrategy;
use App\Services\Import\Strategies\PipelinePrewashMbmImportStrategy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class PipelinePrewashMbmImportTest extends TestCase
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
        foreach ($this->files as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        Mockery::close();
        parent::tearDown();
    }

    public function test_partial_month_workbook_imports_only_eighteen_source_fields_and_preserves_precision(): void
    {
        $row = $this->row();
        $row[4] = '00123456789012345678901234567890';
        $row[9] = '9007199254740993.01';
        $headers = [...PipelinePrewashMbmSourceService::BASE_COLUMNS, 'Jumlah Kunjungan', 'Kunjungan', 'Plafond Real', 'Status', 'OKT'];
        $source = $this->workbook($headers, [[...$row, '99', 'FORGED', '999999', 'Real', 'DONE']]);
        $canonical = $this->path();
        $service = new PipelinePrewashMbmSourceService;
        $service->stage($source, $canonical, 2026);
        $preview = $service->preview($canonical);

        $this->assertSame([...$row, '2026'], $preview['preview'][0]);
        $this->assertSame(2, $preview['total_rows']);
        $this->assertSame(PipelinePrewashMbmSourceService::IMPORT_COLUMNS, $preview['headers']);
        $this->assertNotContains('kunjungan', $preview['headers']);
        $bulk = $this->path();
        $result = $service->prepareBulk($canonical, $bulk, $preview['headers']);
        $this->assertSame(1, $result['rows']);
        $this->assertStringContainsString('"9007199254740993.01"', file_get_contents($bulk));
        $this->assertStringContainsString('"00123456789012345678901234567890"', file_get_contents($bulk));
        $this->assertStringContainsString('"prewash_mbm_2026_1"', file_get_contents($bulk));
        $this->assertStringNotContainsString('FORGED', file_get_contents($bulk));
    }

    public function test_full_twelve_month_header_and_whitespace_headers_are_supported(): void
    {
        $headers = array_map(fn ($value) => ' '.$value.' ', PipelinePrewashMbmSourceService::SOURCE_HEADERS);
        $source = $this->workbook($headers, [[...$this->row(), ...array_fill(0, 16, 'DONE')]]);
        $canonical = $this->path();
        (new PipelinePrewashMbmSourceService)->stage($source, $canonical, 2026);
        $this->assertSame([...$this->row(), '2026'], (new PipelinePrewashMbmSourceService)->preview($canonical)['preview'][0]);
    }

    public function test_unknown_header_is_rejected_without_publishing_staging(): void
    {
        $headers = PipelinePrewashMbmSourceService::BASE_COLUMNS;
        $headers[8] = 'unexpected';
        $canonical = $this->path();
        try {
            (new PipelinePrewashMbmSourceService)->stage($this->workbook($headers, [$this->row()]), $canonical, 2026);
            $this->fail('Unknown header must not silently drop kanwil.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('18 kolom', $error->getMessage());
            $this->assertFileDoesNotExist($canonical);
        }
    }

    public function test_a_late_invalid_row_aborts_the_whole_stage_and_is_not_silently_dropped(): void
    {
        $rows = array_fill(0, 120, $this->row());
        $rows[119][9] = '1.001';
        $canonical = $this->path();
        $this->expectException(RuntimeException::class);
        (new PipelinePrewashMbmSourceService)->stage($this->workbook(PipelinePrewashMbmSourceService::BASE_COLUMNS, $rows), $canonical, 2026);
    }

    public function test_filters_follow_reordered_preview_columns_without_truncating_identifiers(): void
    {
        $first = $this->row();
        $second = $this->row();
        $second[7] = 'KC Ngawi';
        $canonical = $this->path();
        $service = new PipelinePrewashMbmSourceService;
        $service->stage($this->workbook(PipelinePrewashMbmSourceService::BASE_COLUMNS, [$first, $second]), $canonical, 2026);
        $headers = array_reverse(PipelinePrewashMbmSourceService::IMPORT_COLUMNS);
        $bulk = $this->path();
        $result = $service->prepareBulk($canonical, $bulk, $headers, [array_search('nama_kanca', $headers, true) => ['KC Madiun']]);
        $this->assertSame(2, $result['scanned']);
        $this->assertSame(1, $result['rows']);
        $this->assertStringNotContainsString('KC Ngawi', file_get_contents($bulk));
        $this->assertStringContainsString('"001234"', file_get_contents($bulk));
    }

    public function test_upload_requires_an_explicit_valid_year_before_staging_and_accepts_the_selected_year(): void
    {
        $method = new ReflectionMethod(ImportExcelController::class, 'normalizeSsaSpreadsheetUpload');
        try {
            $method->invoke(app(ImportExcelController::class), PipelinePrewashMbmSourceService::TABLE, 'missing.xlsx');
            $this->fail('Missing year must fail before any file access.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Tahun laporan', $error->getMessage());
        }
        $source = $this->workbook(PipelinePrewashMbmSourceService::BASE_COLUMNS, [$this->row()]);
        $relativeSource = 'excel_imports/prewash-mbm-test-'.uniqid().'.xlsx';
        $storedSource = \Illuminate\Support\Facades\Storage::path($relativeSource);
        if (! is_dir(dirname($storedSource))) { mkdir(dirname($storedSource), 0777, true); }
        copy($source, $storedSource);
        $this->files[] = $storedSource;
        $staged = $method->invoke(app(ImportExcelController::class), PipelinePrewashMbmSourceService::TABLE, $relativeSource, '2026');
        $absolute = \Illuminate\Support\Facades\Storage::path($staged);
        $this->files[] = $absolute;
        $this->assertSame(2026, (new PipelinePrewashMbmSourceService)->preview($absolute)['tahun']);
    }

    public function test_report_migration_registers_exact_import_and_canonical_source_passes_structure_guard(): void
    {
        Schema::create('nama_report', function (Blueprint $table): void {
            $table->integer('id_report')->primary();
            foreach (['nama_report', 'table_name', 'import_controller', 'manual_periode_type', 'manual_periode_label', 'manual_periode_help'] as $column) {
                $table->string($column)->nullable();
            }
            $table->integer('active');
            $table->integer('requires_manual_periode');
            $table->timestamps();
        });
        (require database_path('migrations/2026_11_12_000004_create_monitoring_tl_pipeline_mbm_report.php'))->up();
        (require database_path('migrations/2026_10_20_000001_create_import_schema_drift_tables.php'))->up();
        (require database_path('migrations/2026_10_20_000002_create_import_source_header_baselines_table.php'))->up();
        $canonical = $this->path();
        (new PipelinePrewashMbmSourceService)->stage($this->workbook(PipelinePrewashMbmSourceService::BASE_COLUMNS, [$this->row()]), $canonical, 2026);
        $result = app(SchemaDriftImportGuard::class)->assertReady(PipelinePrewashMbmSourceService::TABLE, $canonical);
        $this->assertSame(19, $result['stats']['matched']);
        $this->assertFalse($result['has_blocking_issue']);
        $this->assertFalse($result['has_pending_decision']);
        $this->assertDatabaseHas('nama_report', ['table_name' => PipelinePrewashMbmSourceService::TABLE, 'import_controller' => 'ImportExcelController', 'requires_manual_periode' => 1]);
        $strategy = app(ImportStrategyFactory::class)->resolve((object) ['table_name' => PipelinePrewashMbmSourceService::TABLE]);
        $this->assertInstanceOf(PipelinePrewashMbmImportStrategy::class, $strategy);
        $this->assertFalse((new GenericCsvImportStrategy)->supports(null, PipelinePrewashMbmSourceService::TABLE));
        $this->assertSame(['column' => 'tahun', 'type' => 'year'], app(ImportPeriodGuardService::class)->policyFor(PipelinePrewashMbmSourceService::TABLE));
        $this->assertTrue($strategy->validateSchema(Schema::getColumnListing(PipelinePrewashMbmSourceService::TABLE))['ok']);
    }

    public function test_queue_uses_precise_mbm_loader_and_only_completes_after_confirmed_insert_count(): void
    {
        $canonical = $this->path();
        file_put_contents($canonical, 'canonical fixture');
        $headers = PipelinePrewashMbmSourceService::IMPORT_COLUMNS;
        $service = Mockery::mock(PipelinePrewashMbmSourceService::class);
        $service->shouldReceive('import')->once()->with($canonical, $headers, [], Mockery::type('callable'))->andReturn(1221);
        $this->app->instance(PipelinePrewashMbmSourceService::class, $service);
        $events = [];
        $result = (new ExcelQueuedImportService)->execute([
            'job_id' => 111, 'headers' => $headers,
            'params' => ['file_path' => $canonical, 'table_name' => PipelinePrewashMbmSourceService::TABLE, 'total_rows' => 1221],
        ], [
            'resolve_import_strategy' => fn () => new PipelinePrewashMbmImportStrategy,
            'mark_failed' => fn () => $this->fail('Precise MBM import unexpectedly failed.'),
            'find_job' => fn () => null,
            'update_job' => fn () => null,
            'assert_transactional_table' => fn () => null,
            'assert_duplicate_guard' => fn () => null,
            'cleanup_successful_import_artifacts' => fn () => null,
        ], function ($event, $payload) use (&$events): void { $events[] = $event; });
        $this->assertSame('completed', $result['status']);
        $this->assertSame(1221, $result['total_success']);
        $this->assertSame(['complete'], $events);
    }

    public function test_queue_does_not_report_success_when_exact_import_rejects_a_mysql_warning(): void
    {
        $canonical = $this->path();
        file_put_contents($canonical, 'canonical fixture');
        $service = Mockery::mock(PipelinePrewashMbmSourceService::class);
        $service->shouldReceive('import')->once()->andThrow(new RuntimeException('ID duplikat atau warning MySQL; transaksi dibatalkan.'));
        $this->app->instance(PipelinePrewashMbmSourceService::class, $service);
        $events = $failures = [];
        $result = (new ExcelQueuedImportService)->execute([
            'job_id' => 124, 'headers' => PipelinePrewashMbmSourceService::IMPORT_COLUMNS,
            'params' => ['file_path' => $canonical, 'table_name' => PipelinePrewashMbmSourceService::TABLE, 'total_rows' => 1],
        ], [
            'resolve_import_strategy' => fn () => new PipelinePrewashMbmImportStrategy,
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

    public function test_derived_only_worksheet_rows_are_not_imported_as_empty_pipeline_records(): void
    {
        $headers = [...PipelinePrewashMbmSourceService::BASE_COLUMNS, 'Jumlah Kunjungan', 'Kunjungan', 'Plafond Real', 'Status', 'OKT'];
        $source = $this->workbook($headers, [
            [...$this->row(), '1', 'DONE', '999999', 'Real', 'DONE'],
            [...array_fill(0, 18, ''), '1', 'DONE', '999999', 'Real', 'DONE'],
            [...$this->row(), '0', 'NOT', '0', 'Belum', '0'],
        ]);
        $canonical = $this->path();
        $service = new PipelinePrewashMbmSourceService;
        $result = $service->stage($source, $canonical, 2026);
        $this->assertSame(2, $result['rows']);
        $this->assertSame(1, $result['derived_only_rows']);
        $this->assertCount(2, $service->preview($canonical)['preview']);
    }

    public function test_malformed_xml_aborts_staging_instead_of_accepting_partial_records(): void
    {
        $source = $this->workbook(PipelinePrewashMbmSourceService::BASE_COLUMNS, [$this->row()]);
        $zip = new ZipArchive;
        $zip->open($source);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->addFromString('xl/worksheets/sheet1.xml', str_replace('</sheetData></worksheet>', '<row r="3">', $xml));
        $zip->close();
        $canonical = $this->path();
        try {
            (new PipelinePrewashMbmSourceService)->stage($source, $canonical, 2026);
            $this->fail('Partial source records must never be published.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('XML', $error->getMessage());
            $this->assertFileDoesNotExist($canonical);
        }
    }

    public function test_source_formulas_are_rejected_while_derived_formulas_are_ignored(): void
    {
        $headers = [...PipelinePrewashMbmSourceService::BASE_COLUMNS, 'Jumlah Kunjungan'];
        $source = $this->workbook($headers, [[...$this->row(), '1']]);
        $zip = new ZipArchive;
        $zip->open($source);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $xml = preg_replace('/<c r="S2"[^>]*>.*?<\/c>/', '<c r="S2"><f>1/0</f><v>999</v></c>', $xml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();
        $canonical = $this->path();
        $this->assertSame(1, (new PipelinePrewashMbmSourceService)->stage($source, $canonical, 2026)['rows']);

        $zip->open($source);
        $xml = preg_replace('/<c r="J2"[^>]*>.*?<\/c>/', '<c r="J2"><f>100*100</f><v>10000</v></c>', $xml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Kolom sumber plafond memiliki formula/error');
        (new PipelinePrewashMbmSourceService)->stage($source, $this->path(), 2026);
    }

    private function row(): array
    {
        return ['Nama Nasabah', 'Alamat, "satu"', '081234567890', 'CIF001', '0012345678901234', '001234', 'Unit Test', 'KC Madiun', 'Kanwil Test', '250000000.00', '0.00', 'BRILINK', '001234', 'Nama Mantri', 'KUPEDES', '88', 'TPUP', 'SUMBER'];
    }

    private function path(string $extension = 'csv'): string
    {
        $path = storage_path('framework/testing/prewash-mbm-'.uniqid('', true).'.'.$extension);
        if (! is_dir(dirname($path))) { mkdir(dirname($path), 0777, true); }
        $this->files[] = $path;
        return $path;
    }

    private function workbook(array $headers, array $rows): string
    {
        $path = $this->path('xlsx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Pipeline PIC MBM" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"/></Relationships>');
        $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ([$headers, ...$rows] as $rowIndex => $row) {
            $record = $rowIndex + 1;
            $xml .= '<row r="'.$record.'">';
            foreach ($row as $index => $value) {
                $number = $index + 1;
                $letters = '';
                while ($number > 0) { $letters = chr(65 + (($number - 1) % 26)).$letters; $number = intdiv($number - 1, 26); }
                $xml .= '<c r="'.$letters.$record.'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
            }
            $xml .= '</row>';
        }
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml.'</sheetData></worksheet>');
        $zip->close();
        return $path;
    }
}
