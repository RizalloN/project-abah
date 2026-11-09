<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\Concerns\SmartCsvImportSupport;
use App\Http\Controllers\Import\ImportExcelController;
use App\Http\Controllers\Import\ImportFileController;
use App\Models\User;
use App\Services\Import\SchemaIntrospectionService;
use App\Services\Import\SmartContentHeaderGuardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class ImportPreviewDetectionTest extends TestCase
{
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Bus::fake();
        Schema::create('preview_sample', function ($table): void {
            $table->date('periode')->nullable();
            $table->string('nomor_rekening1')->nullable();
            $table->decimal('saldo', 20, 2)->nullable();
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    #[DataProvider('delimiterSources')]
    public function test_delimiter_detection_uses_consistent_structure(string $source, string $expected): void
    {
        $detector = new class
        {
            use SmartCsvImportSupport;

            public function detect(string $path): string
            {
                return $this->smartDetectCsvDelimiter($path, [',', ';', "\t", '|', '.']);
            }
        };
        $this->assertSame($expected, $detector->detect($this->source($source)));
    }

    public static function delimiterSources(): array
    {
        return [
            'pipe with comma-heavy preamble' => [str_repeat('metadata,', 30)."\nPERIODE|REKENING|SALDO\n2026-10-31|001201000123501|1500.50\n2026-10-31|001201000123502|2500.50\n", '|'],
            'semicolon with quoted commas' => ["PERIODE;NAMA;SALDO\n2026-10-31;\"A, B, C, D\";1500.50\n2026-10-31;\"E, F, G, H\";2500.50\n", ';'],
            'tab with BOM and blank line' => ["\xEF\xBB\xBF\r\nPERIODE\tREKENING\tSALDO\r\n2026-10-31\t001201000123501\t1500.50\r\n", "\t"],
            'comma with trailing empty columns' => ["PERIODE,REKENING,SALDO,,\n2026-10-31,001201000123501,1500.50,,\n", ','],
            'serialized pipe export' => ["\"PERIODE|REKENING|SALDO\"\n\"2026-10-31|001201000123501|1500.50\"\n", '|'],
            'period delimiter remains supported' => ["PERIODE.REKENING.SALDO\n2026-10-31.001201000123501.1500\n", '.'],
            'empty source' => ['', ','],
        ];
    }

    public function test_preview_auto_detection_and_manual_override_ignore_stale_delimiter_cache(): void
    {
        Schema::create('nama_report', function ($table): void {
            $table->integer('id_report')->primary();
            $table->string('nama_report');
            $table->string('table_name');
        });
        DB::table('nama_report')->insert(['id_report' => 101, 'nama_report' => 'Preview sample', 'table_name' => 'preview_sample']);
        $path = $this->source("\"PERIODE;REKENING;SALDO\"\n\"2026-10-31;001201000123501;1500.50\"\n");
        $this->be(User::factory()->make(['role' => 'admin']));
        session()->start();
        session()->put(['active_id_report' => 101, 'import_files' => [['name' => basename($path), 'path' => $path]]]);
        Cache::put('import_csv_delimiter:'.md5(realpath($path).filesize($path)), ',', 3600);

        foreach (['auto', ';', null, ''] as $delimiter) {
            $request = Request::create('/import/preview/direct', 'GET', ['file_path' => $path, 'delimiter' => $delimiter]);
            $request->setLaravelSession(session()->driver());
            $payload = app(ImportFileController::class)->preview($request)->getData();
            $this->assertSame(['PERIODE', 'REKENING', 'SALDO'], $payload['headers']);
            $this->assertSame([['2026-10-31', '001201000123501', '1500.50']], $payload['previewData']);
        }
    }

    public function test_invalid_preview_delimiter_returns_validation_error(): void
    {
        $this->expectException(ValidationException::class);
        app(ImportFileController::class)->preview(Request::create('/import/preview/direct', 'GET', [
            'file_path' => 'unused.csv', 'delimiter' => 'invalid',
        ]));
    }

    public function test_preview_can_read_spacer_lines_between_valid_records(): void
    {
        Schema::create('nama_report', function ($table): void {
            $table->integer('id_report')->primary();
            $table->string('nama_report');
            $table->string('table_name');
        });
        DB::table('nama_report')->insert(['id_report' => 101, 'nama_report' => 'Preview sample', 'table_name' => 'preview_sample']);
        $path = $this->source("\nPERIODE;REKENING;SALDO\n\n2026-10-31;001201000123501;1500.50\n");
        $this->be(User::factory()->make(['role' => 'admin']));
        session()->start();
        session()->put(['active_id_report' => 101, 'import_files' => [['name' => basename($path), 'path' => $path]]]);
        $request = Request::create('/import/preview/direct', 'GET', ['file_path' => $path]);
        $request->setLaravelSession(session()->driver());
        $payload = app(ImportFileController::class)->preview($request)->getData();
        $this->assertSame(['PERIODE', 'REKENING', 'SALDO'], $payload['headers']);
        $this->assertCount(1, $payload['previewData']);
        $this->assertStringContainsString('001201000123501', app(ImportFileController::class)->preview($request)->render());
    }

    public function test_metadata_with_period_label_does_not_hide_real_header(): void
    {
        $rows = [['PERIODE', '2026-10-31'], ['PERIODE', 'NOMOR_REKENING1', 'SALDO']];
        $this->assertSame(1, (new ReflectionMethod(ImportExcelController::class, 'detectHeaderIndex'))->invoke(new ImportExcelController, $rows, 'preview_sample'));
    }

    public function test_blank_csv_records_do_not_terminate_preview_reader(): void
    {
        $path = $this->source("\n\r\nPERIODE;REKENING;SALDO\n\n2026-10-31;001201000123501;1500.50\n");
        $handle = fopen($path, 'rb');
        $reader = new ReflectionMethod(ImportFileController::class, 'readCsvRecord');
        $controller = new ImportFileController;
        try {
            $this->assertSame(['PERIODE', 'REKENING', 'SALDO'], $reader->invoke($controller, $handle, ';'));
            $this->assertSame(['2026-10-31', '001201000123501', '1500.50'], $reader->invoke($controller, $handle, ';'));
            $this->assertFalse($reader->invoke($controller, $handle, ';'));
        } finally {
            fclose($handle);
        }
    }

    public function test_duplicate_column_labels_do_not_count_as_distinct_header_evidence(): void
    {
        $rows = [['SALDO', 'SALDO', 'SALDO'], ['PERIODE', 'NOMOR_REKENING1', 'SALDO']];
        $this->assertSame(1, (new ReflectionMethod(ImportExcelController::class, 'detectHeaderIndex'))->invoke(new ImportExcelController, $rows, 'preview_sample'));
    }

    public function test_excel_csv_preview_preserves_data_after_period_metadata_and_old_cache(): void
    {
        $controller = new class extends ImportExcelController
        {
            protected function resolveActiveTableName(string $default = 'daily_loan_dinamis'): string
            {
                return 'preview_sample';
            }
        };
        $path = $this->source("PERIODE,2026-10-31\nPERIODE,NOMOR_REKENING1,SALDO\n2026-10-31,001201000123501,1500.50\n");
        Cache::put('csv_delimiter:'.md5($path.filesize($path)), ';', 3600);
        $payload = (new ReflectionMethod($controller, 'prepareCsvPreviewPayload'))->invoke($controller, $path);
        $this->assertSame(',', $payload['delimiter']);
        $this->assertSame(1, $payload['header_index']);
        $this->assertSame(['PERIODE', 'NOMOR_REKENING1', 'SALDO'], $payload['headers']);
        $this->assertSame(1, $payload['total_rows']);
        $this->assertSame('001201000123501', $payload['preview'][0][1]);
    }

    public function test_content_header_detection_preserves_sparse_row_indexes(): void
    {
        $schema = $this->createMock(SchemaIntrospectionService::class);
        $schema->method('hasTable')->willReturn(false);
        $guard = new SmartContentHeaderGuardService($schema);
        $rows = [5 => ['Report preview'], 8 => ['TEXTBOX1', 'TEXTBOX2', 'TEXTBOX3'], 9 => ['2026-10-31', '001201000123501', '1500.50']];
        $this->assertSame(8, $guard->detectHeaderRow($rows));
    }

    public function test_content_detection_rejects_text_only_data_without_header_evidence(): void
    {
        $this->assertNull(app(SmartContentHeaderGuardService::class)->detectHeaderRow([
            ['KC MADIUN', 'BUDI SANTOSO', 'AKTIF'],
        ], 'preview_sample'));
    }

    private function source(string $contents): string
    {
        $directory = storage_path('app/imports');
        if (! is_dir($directory)) {
            mkdir($directory, 0750, true);
        }
        $path = $directory.'/preview-detection-'.bin2hex(random_bytes(8)).'.csv';
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return $path;
    }
}
