<?php

namespace Tests\Unit;

use App\Services\Import\MySqlBulkLoadService;
use App\Services\Import\PipelinePrewashDecimal;
use App\Services\Import\PipelinePrewashSourceService;
use App\Services\Import\Strategies\PipelinePrewashImportStrategy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class PipelinePrewashImportTest extends TestCase
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

    public function test_streamed_workbook_preserves_source_precision_and_ignores_every_derived_field(): void
    {
        $row = $this->row();
        $row['nama_debt'] = ' Debitur satu ';
        $row['alamat'] = "Jalan \\rumah, \"A&B\"\nMadiun";
        $row['nomorhandphone'] = ['n', '628123456789012'];
        $row['cif'] = '0000123';
        $row['norek_pinjaman'] = ['n', '1.23456789012345E+14'];
        $row['pn_mantri'] = '001234';
        $row['plafond'] = ['n', '9.00719925474099301E15'];
        $row['os'] = '19173048.00';
        $row['score'] = '1,070';
        $row['JAN'] = ['e', '#VALUE!'];
        $row['Status'] = ['f', '999'];
        $source = $this->workbook([$row]);
        $canonical = $this->path();
        $service = new PipelinePrewashSourceService;

        $metadata = $service->stage($source, $canonical, 2026);
        $preview = $service->preview($canonical);
        $values = array_combine(PipelinePrewashSourceService::IMPORT_COLUMNS, $preview['preview'][0]);

        $this->assertSame(1, $metadata['rows']);
        $this->assertSame(hash_file('sha256', $source), $metadata['source_sha256']);
        $this->assertSame(18, count($values));
        $this->assertArrayNotHasKey('kanwil', $values);
        $this->assertArrayNotHasKey('jumlah_kunjungan', $values);
        $this->assertArrayNotHasKey('jan', $values);
        $this->assertSame(' Debitur satu ', $values['nama_debt']);
        $this->assertSame($row['alamat'], $values['alamat']);
        $this->assertSame('628123456789012', $values['nomorhandphone']);
        $this->assertSame('123456789012345', $values['norek_pinjaman']);
        $this->assertSame('0000123', $values['cif']);
        $this->assertSame('001234', $values['pn_mantri']);
        $this->assertSame('9007199254740993.01', $values['plafond']);
        $this->assertSame('19173048.00', $values['os']);
        $this->assertSame('1,070', $values['score']);
        $this->assertSame('2026', $values['tahun']);
    }

    public function test_missing_optional_fields_become_null_while_duplicate_cif_rows_remain_distinct(): void
    {
        $row = $this->row();
        foreach (['cif', 'norek_pinjaman', 'pn_mantri', 'nama_mantri', 'score', 'idpipeline_lama', 'sumberpipeline_lama'] as $column) {
            $row[$column] = '';
        }
        $canonical = $this->path();
        $bulk = $this->path();
        $service = new PipelinePrewashSourceService;
        $service->stage($this->workbook([$row, $row]), $canonical, 2026);

        $result = $service->prepareBulk($canonical, $bulk, PipelinePrewashSourceService::IMPORT_COLUMNS);
        $lines = file($bulk, FILE_IGNORE_NEW_LINES);

        $this->assertSame(2, $result['rows']);
        $this->assertCount(2, $lines);
        $this->assertStringContainsString(',\\N,\\N,', $lines[0]);
        $this->assertStringContainsString('"prewash_2026_1"', $lines[0]);
        $this->assertStringContainsString('"prewash_2026_2"', $lines[1]);
        $this->assertStringNotContainsString('STALE', file_get_contents($bulk));
    }

    public function test_bulk_filter_retains_original_ordinals_and_mysql_escapes_literal_text(): void
    {
        $first = $this->row();
        $second = $this->row();
        $second['nama_kanca'] = ' KC Ngawi ';
        $second['alamat'] = "literal \\N, \"quote\", slash\\\nline\tend\r";
        $canonical = $this->path();
        $bulk = $this->path();
        $service = new PipelinePrewashSourceService;
        $service->stage($this->workbook([$first, $second]), $canonical, 2026);
        $headers = array_reverse(PipelinePrewashSourceService::IMPORT_COLUMNS);

        $result = $service->prepareBulk($canonical, $bulk, $headers, [array_search('nama_kanca', $headers, true) => ['KC Ngawi']]);
        $contents = file_get_contents($bulk);

        $this->assertSame(2, $result['scanned']);
        $this->assertSame(1, $result['rows']);
        $this->assertStringContainsString('"prewash_2026_2"', $contents);
        $this->assertStringContainsString('" KC Ngawi "', $contents);
        $this->assertStringContainsString('"literal \\\\N, \\"quote\\", slash\\\\\\nline\\tend\\r"', $contents);
    }

    public function test_preview_counts_header_and_bounds_raw_samples_and_filter_options(): void
    {
        $rows = [];
        for ($i = 1; $i <= 300; $i++) {
            $row = $this->row();
            $row['cif'] = str_pad((string) $i, 7, '0', STR_PAD_LEFT);
            $rows[] = $row;
        }
        $canonical = $this->path();
        $service = new PipelinePrewashSourceService;
        $service->stage($this->workbook($rows), $canonical, 2026);

        $preview = $service->preview($canonical);

        $this->assertSame(301, $preview['total_rows']);
        $this->assertSame(0, $preview['header_index']);
        $this->assertCount(100, $preview['preview']);
        $this->assertCount(256, $preview['formattedUniqueValues'][3]);
        $this->assertSame('0000001', $preview['preview'][0][3]);
    }

    public function test_unknown_source_header_fails_without_partial_canonical(): void
    {
        $headers = PipelinePrewashSourceService::SOURCE_HEADERS;
        $headers[9] = 'plafon_lain';
        $canonical = $this->path();
        try {
            (new PipelinePrewashSourceService)->stage($this->workbook([$this->row()], $headers), $canonical, 2026);
            $this->fail('Unknown source header was accepted.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Header', $error->getMessage());
            $this->assertFileDoesNotExist($canonical);
        }
    }

    public function test_malformed_workbook_xml_is_rejected_instead_of_committing_a_partial_dataset(): void
    {
        $source = $this->workbook([$this->row()]);
        $zip = new ZipArchive;
        $zip->open($source);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->addFromString('xl/worksheets/sheet1.xml', str_replace('</worksheet>', '', $xml));
        $zip->close();
        $canonical = $this->path();
        try {
            (new PipelinePrewashSourceService)->stage($source, $canonical, 2026);
            $this->fail('Malformed XML was accepted.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('XML', $error->getMessage());
            $this->assertFileDoesNotExist($canonical);
        }
    }

    public function test_shared_and_ooxml_escaped_text_values_are_decoded_once(): void
    {
        $row = $this->row();
        $row['nama_debt'] = ['s', '0'];
        $row['alamat'] = 'baris_x000D_baru dan literal _x005F_x000D_';
        $canonical = $this->path();
        $service = new PipelinePrewashSourceService;
        $service->stage($this->workbook([$row]), $canonical, 2026);

        $values = $service->preview($canonical)['preview'][0];

        $this->assertSame('STALE DERIVED VALUE', $values[0]);
        $this->assertSame("baris\rbaru dan literal _x000D_", $values[1]);
    }

    public function test_invalid_year_is_rejected_before_workbook_processing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tahun laporan');
        (new PipelinePrewashSourceService)->stage($this->workbook([$this->row()]), $this->path(), 26);
    }

    public function test_mixed_canonical_years_fail_before_database_write(): void
    {
        $canonical = $this->path();
        $bulk = $this->path();
        $service = new PipelinePrewashSourceService;
        $service->stage($this->workbook([$this->row(), $this->row()]), $canonical, 2026);
        $records = $this->readCsv($canonical);
        $records[2][17] = '2025';
        $this->writeCsv($canonical, $records);
        try {
            $service->prepareBulk($canonical, $bulk, PipelinePrewashSourceService::IMPORT_COLUMNS);
            $this->fail('Mixed years were accepted.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Tahun staging', $error->getMessage());
            $this->assertFileDoesNotExist($bulk);
        }
    }

    public static function invalidSourceCells(): array
    {
        return [
            'missing required name' => ['nama_debt', ''],
            'excess decimal places' => ['os', '100.001'],
            'ambiguous comma thousands' => ['plafond', '1,000.00'],
            'overflow amount' => ['plafond', '10000000000000000000000000000.00'],
            'fractional numeric account' => ['norek_pinjaman', ['n', '123456789012345.5']],
            'numeric source formula' => ['plafond', ['f', '100']],
            'source error' => ['os', ['e', '#VALUE!']],
            'text overflow' => ['score', str_repeat('a', 65)],
        ];
    }

    #[DataProvider('invalidSourceCells')]
    public function test_invalid_base_source_cell_never_leaves_partial_staging(string $column, mixed $value): void
    {
        $row = $this->row();
        $row[$column] = $value;
        $canonical = $this->path();
        try {
            (new PipelinePrewashSourceService)->stage($this->workbook([$this->row(), $row]), $canonical, 2026);
            $this->fail('Invalid source was accepted.');
        } catch (RuntimeException $error) {
            $this->assertNotSame('', $error->getMessage());
            $this->assertFileDoesNotExist($canonical);
        }
    }

    public function test_exact_decimal_normalization_and_signed_arbitrary_precision_addition(): void
    {
        $this->assertNull(PipelinePrewashDecimal::normalize(null));
        $this->assertNull(PipelinePrewashDecimal::normalize(''));
        $this->assertSame('123.40', PipelinePrewashDecimal::normalize('00123.4'));
        $this->assertSame('0.00', PipelinePrewashDecimal::normalize('-0'));
        $this->assertSame('123456789012345', PipelinePrewashDecimal::integer('1.23456789012345e14'));
        $this->assertSame('100000000000000000000000000000000000000.00', PipelinePrewashDecimal::add('99999999999999999999999999999999999999.99', '0.01'));
        $this->assertSame('-0.01', PipelinePrewashDecimal::add('-100.00', '99.99'));
        $this->assertSame('0.01', PipelinePrewashDecimal::add('100.00', '-99.99'));
        $this->assertSame('0.00', PipelinePrewashDecimal::add('-100.00', '100.00'));
        $this->assertSame('-100.01', PipelinePrewashDecimal::add('-100.00', '-0.01'));
        $this->expectException(RuntimeException::class);
        PipelinePrewashDecimal::normalize('1.001');
    }

    public function test_existing_year_rejects_import_without_running_load_and_rolls_back(): void
    {
        $loader = Mockery::mock(MySqlBulkLoadService::class);
        $loader->shouldReceive('assertTransactionalTable')->once();
        $loader->shouldReceive('withTableWriteLock')->once()->andReturnUsing(static fn ($table, $callback) => $callback());
        $this->app->instance(MySqlBulkLoadService::class, $loader);
        $pdo = $this->createMock(PDO::class);
        $statement = $this->createMock(PDOStatement::class);
        $pdo->method('getAttribute')->willReturn('mysql');
        $pdo->method('quote')->willReturn("'test.csv'");
        $pdo->expects($this->once())->method('beginTransaction')->willReturn(true);
        $pdo->expects($this->once())->method('prepare')->willReturn($statement);
        $statement->expects($this->once())->method('execute')->with([2026])->willReturn(true);
        $statement->expects($this->once())->method('fetchColumn')->willReturn(1);
        $pdo->expects($this->never())->method('exec');
        $pdo->method('inTransaction')->willReturn(true);
        $pdo->expects($this->once())->method('rollBack')->willReturn(true);
        $pdo->expects($this->never())->method('commit');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tahun 2026 sudah ada');
        (new PipelinePrewashSourceService)->loadBulk($this->path(), 1, 2026, $pdo);
    }

    public function test_strategy_keeps_exact_text_and_migration_registers_only_requested_report(): void
    {
        $strategy = new PipelinePrewashImportStrategy;
        $this->assertTrue($strategy->supports(null, 'pipeline_prewash'));
        $this->assertFalse($strategy->supports(null, 'daily_loan_dinamis'));
        $this->assertTrue($strategy->validateSchema([...PipelinePrewashSourceService::IMPORT_COLUMNS, 'uniqueid_namareport', 'created_at', 'updated_at'])['ok']);
        $this->assertFalse($strategy->validateSchema(['tahun'])['ok']);
        $context = $strategy->prepareContext(['header_rules' => [['is_decimal' => true]]]);
        $this->assertTrue($context['header_rules'][0]['preserve_source_text_exact']);
        $this->assertFalse($context['header_rules'][0]['is_decimal']);
        Schema::create('nama_report', function (Blueprint $table): void {
            $table->integer('id_report')->primary();
            $table->string('nama_report');
            $table->string('table_name');
            $table->integer('active');
            $table->string('import_controller');
            $table->integer('requires_manual_periode');
            $table->string('manual_periode_type');
            $table->string('manual_periode_label');
            $table->string('manual_periode_help');
            $table->timestamps();
        });
        $migration = require base_path('database/migrations/2026_11_12_000003_create_pipeline_prewash_report.php');
        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasColumns('pipeline_prewash', ['kanwil', 'jumlah_kunjungan', 'kunjungan', 'plafond_real', 'status', ...PipelinePrewashSourceService::MONTH_COLUMNS]));
        $this->assertSame(1, DB::table('nama_report')->count());
        $report = DB::table('nama_report')->first();
        $this->assertSame('number', $report->manual_periode_type);
        $this->assertSame('Tahun laporan', $report->manual_periode_label);
        $this->assertCount(3, Schema::getIndexes('pipeline_prewash'));
    }

    private function row(): array
    {
        return [
            'nama_debt' => 'Debitur', 'alamat' => 'Madiun', 'nomorhandphone' => '08123456789',
            'cif' => 'AB00123', 'norek_pinjaman' => '000123456789012', 'branch' => '0045',
            'nama_unit' => 'Unit Gemarang', 'nama_kanca' => 'KC Madiun', 'kanwil' => 'STALE KANWIL',
            'plafond' => ['n', '100000000'], 'os' => '19173048.00', 'sumberpipeline' => 'SLIK Hijau',
            'pn_mantri' => '001234', 'nama_mantri' => 'Mantri', 'product_rekomendasi' => 'KUR',
            'score' => '100', 'idpipeline_lama' => '0001', 'sumberpipeline_lama' => 'Pipeline',
        ];
    }

    /** Tiny native OOXML fixture exercises shared, inline, numeric and formula cell encodings. */
    private function workbook(array $rows, ?array $headers = null): string
    {
        $headers ??= PipelinePrewashSourceService::SOURCE_HEADERS;
        $path = $this->path();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Pipeline SLIK Hijau" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"/></Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>STALE DERIVED VALUE</t></si></sst>');
        $xml = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1">';
        foreach ($headers as $index => $header) {
            $xml .= $this->cell($index, 1, $header);
        }
        $xml .= '</row>';
        foreach ($rows as $index => $row) {
            $number = $index + 2;
            $xml .= '<row r="'.$number.'">';
            foreach (PipelinePrewashSourceService::SOURCE_HEADERS as $column => $header) {
                $value = $row[$header] ?? ($column < 18 ? '' : ['s', '0']);
                $xml .= $this->cell($column, $number, $value);
            }
            $xml .= '</row>';
        }
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml.'</sheetData></worksheet>');
        $zip->close();

        return $path;
    }

    private function cell(int $index, int $row, mixed $value): string
    {
        $letters = '';
        for ($number = $index + 1; $number > 0; $number = intdiv($number - 1, 26)) {
            $letters = chr(65 + ($number - 1) % 26).$letters;
        }
        [$type, $raw] = is_array($value) ? $value : ['inlineStr', $value];
        $text = str_replace("\r", '&#13;', htmlspecialchars((string) $raw, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        $body = $type === 'inlineStr' ? '<is><t xml:space="preserve">'.$text.'</t></is>' : ($type === 'f' ? '<f>1+1</f><v>'.$text.'</v>' : '<v>'.$text.'</v>');

        return '<c r="'.$letters.$row.'" t="'.($type === 'f' ? 'n' : $type).'">'.$body.'</c>';
    }

    private function path(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'prewash_test_');
        $this->files[] = $path;

        return $path;
    }

    private function readCsv(string $path): array
    {
        $input = fopen($path, 'rb');
        $rows = [];
        while (($row = fgetcsv($input, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($input);

        return $rows;
    }

    private function writeCsv(string $path, array $rows): void
    {
        $output = fopen($path, 'wb');
        foreach ($rows as $row) {
            fputcsv($output, $row, ',', '"', '');
        }
        fclose($output);
    }
}
