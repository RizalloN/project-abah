<?php

namespace Tests\Unit;

use App\Services\Import\MonitoringKsbkSourceService;
use App\Services\Import\MySqlBulkLoadService;
use App\Services\Import\Strategies\MonitoringKsbkImportStrategy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PDO;
use PDOStatement;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class MonitoringKsbkImportTest extends TestCase
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

    public function test_stage_preserves_source_precision_and_discards_all_four_workbook_results(): void
    {
        $source = $this->workbook([$this->row()]);
        $this->cell($source, 'C2', '<c r="C2"><v>123456789012345678901234567890</v></c>');
        $this->cell($source, 'O2', '<c r="O2"><f>1/0</f><v>999999999999</v></c>');
        $this->cell($source, 'P2', '<c r="P2" t="e"><v>#N/A</v></c>');
        $service = new MonitoringKsbkSourceService;
        $canonical = $this->path();
        $metadata = $service->stage($source, $canonical);
        $preview = $service->preview($canonical);

        $this->assertSame(1, $metadata['rows']);
        $this->assertSame(MonitoringKsbkSourceService::IMPORT_COLUMNS, $preview['headers']);
        $this->assertCount(13, $preview['preview'][0]);
        $this->assertSame('003641', $preview['preview'][0][0]);
        $this->assertSame('123456789012345678901234567890', $preview['preview'][0][2]);
        $this->assertSame('9007199254740993.01', $preview['preview'][0][5]);
        $this->assertSame('2026-09-22 00:00:00', $preview['preview'][0][6]);
        $this->assertNotContains('plafond', $preview['headers']);
        $this->assertNotContains('FORGED', $preview['preview'][0]);

        $bulk = $this->path();
        $result = $service->prepareBulk($canonical, $bulk, $preview['headers']);
        $this->assertSame(1, $result['rows']);
        $this->assertStringContainsString('"ksbk_1"', file_get_contents($bulk));
        $this->assertStringNotContainsString('999999999999', file_get_contents($bulk));
        $this->assertStringNotContainsString('FORGED', file_get_contents($bulk));
    }

    public function test_blank_amount_and_cif_remain_unknown_while_excel_dates_are_normalized(): void
    {
        $row = $this->row();
        $row[3] = $row[5] = '';
        $source = $this->workbook([$row]);
        $this->cell($source, 'G2', '<c r="G2"><v>46287</v></c>');
        $service = new MonitoringKsbkSourceService;
        $canonical = $this->path();
        $service->stage($source, $canonical);
        $record = $service->validateCanonical($canonical)['preview'][0];
        $this->assertSame('', $record[3]);
        $this->assertSame('', $record[5]);
        $this->assertSame('2026-09-22 00:00:00', $record[6]);
        $bulk = $this->path();
        $service->prepareBulk($canonical, $bulk, MonitoringKsbkSourceService::IMPORT_COLUMNS);
        $this->assertStringContainsString('\\N,"Nama Nasabah",\\N', file_get_contents($bulk));
    }

    public function test_filters_follow_reordered_preview_columns_and_keep_original_row_identity(): void
    {
        $row = $this->row();
        $row[9] = '57';
        $service = new MonitoringKsbkSourceService;
        $canonical = $this->path();
        $service->stage($this->workbook([$this->row(), $row]), $canonical);
        $headers = array_reverse(MonitoringKsbkSourceService::IMPORT_COLUMNS);
        $bulk = $this->path();
        $result = $service->prepareBulk($canonical, $bulk, $headers, [array_search('bo', $headers, true) => ['57']]);
        $this->assertSame(2, $result['scanned']);
        $this->assertSame(1, $result['rows']);
        $this->assertStringContainsString('"ksbk_2"', file_get_contents($bulk));
    }

    public function test_excel_1904_calendar_is_respected_without_leaking_global_calendar_state(): void
    {
        $source = $this->workbook([$this->row()]);
        $this->cell($source, 'G2', '<c r="G2"><v>44825</v></c>');
        $zip = new ZipArchive;
        $zip->open($source);
        $xml = $zip->getFromName('xl/workbook.xml');
        $zip->addFromString('xl/workbook.xml', str_replace('<sheets>', '<workbookPr date1904="1"/><sheets>', $xml));
        $zip->close();
        $calendar = Date::getExcelCalendar();
        $service = new MonitoringKsbkSourceService;
        $canonical = $this->path();
        $service->stage($source, $canonical);
        $this->assertSame('2026-09-22 00:00:00', $service->preview($canonical)['preview'][0][6]);
        $this->assertSame($calendar, Date::getExcelCalendar());
    }

    public function test_source_formula_rejects_the_entire_workbook_even_after_a_valid_record(): void
    {
        $source = $this->workbook([$this->row(), $this->row()]);
        $this->cell($source, 'F3', '<c r="F3"><f>1+1</f><v>2</v></c>');
        $canonical = $this->path();
        try {
            (new MonitoringKsbkSourceService)->stage($source, $canonical);
            $this->fail('Source formula must reject the entire staging.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('plafond_pengajuan memiliki formula/error', $error->getMessage());
            $this->assertFileDoesNotExist($canonical);
        }
    }

    public function test_a_late_invalid_record_cannot_publish_a_partial_bulk_file(): void
    {
        $service = new MonitoringKsbkSourceService;
        $canonical = $this->path();
        $service->stage($this->workbook([$this->row()]), $canonical);
        $handle = fopen($canonical, 'ab');
        fputcsv($handle, ['BROKEN'], ',', '"', '');
        fclose($handle);
        $bulk = $this->path();
        try {
            $service->prepareBulk($canonical, $bulk, MonitoringKsbkSourceService::IMPORT_COLUMNS);
            $this->fail('Invalid canonical record must fail before the database load.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Jumlah kolom', $error->getMessage());
            $this->assertFileDoesNotExist($bulk);
        }
    }

    public function test_invalid_header_and_calendar_date_are_rejected(): void
    {
        $headers = MonitoringKsbkSourceService::SOURCE_HEADERS;
        $headers[0] = 'unexpected';
        try {
            (new MonitoringKsbkSourceService)->stage($this->workbook([$this->row()], $headers), $this->path());
            $this->fail('Unknown headers must not be silently mapped.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('17 kolom', $error->getMessage());
        }
        $row = $this->row();
        $row[6] = '2026-02-30';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tanggal rencana_real_date');
        (new MonitoringKsbkSourceService)->stage($this->workbook([$row]), $this->path());
    }

    public function test_malformed_xml_cannot_publish_a_partial_workbook(): void
    {
        $source = $this->workbook([$this->row()]);
        $zip = new ZipArchive;
        $zip->open($source);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->addFromString('xl/worksheets/sheet1.xml', str_replace('</sheetData></worksheet>', '<row r="3">', $xml));
        $zip->close();
        $canonical = $this->path();
        try {
            (new MonitoringKsbkSourceService)->stage($source, $canonical);
            $this->fail('Malformed XML must invalidate all staged rows.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('XML', $error->getMessage());
            $this->assertFileDoesNotExist($canonical);
        }
    }

    public function test_data_beyond_the_seventeen_declared_columns_is_rejected(): void
    {
        $source = $this->workbook([$this->row()]);
        $zip = new ZipArchive;
        $zip->open($source);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $xml = str_replace('</sheetData>', '<row r="3"><c r="R3" t="inlineStr"><is><t>unexpected data</t></is></c></row></sheetData>', $xml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();
        $canonical = $this->path();
        try {
            (new MonitoringKsbkSourceService)->stage($source, $canonical);
            $this->fail('Data outside the declared workbook shape must not be discarded.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Jumlah/posisi kolom', $error->getMessage());
            $this->assertFileDoesNotExist($canonical);
        }
    }

    public function test_migration_registers_seventeen_business_columns_without_a_manual_period(): void
    {
        Schema::create('nama_report', function (Blueprint $table): void {
            $table->integer('id_report')->primary();
            foreach (['nama_report', 'table_name', 'import_controller'] as $column) {
                $table->string($column)->nullable();
            }
            $table->integer('active');
            $table->integer('requires_manual_periode');
            $table->timestamps();
        });
        $migration = require database_path('migrations/2026_11_12_000005_create_monitoring_realisasi_ksbk_report.php');
        $migration->up();
        $migration->up();
        $columns = Schema::getColumnListing(MonitoringKsbkSourceService::TABLE);
        $this->assertCount(20, $columns);
        $this->assertTrue((new MonitoringKsbkImportStrategy)->validateSchema($columns)['ok']);
        $this->assertFalse((new MonitoringKsbkImportStrategy)->validateSchema(['cifno'])['ok']);
        $this->assertDatabaseHas('nama_report', ['table_name' => MonitoringKsbkSourceService::TABLE, 'requires_manual_periode' => 0]);
        $this->assertSame(1, DB::table('nama_report')->count());
    }

    public function test_existing_dataset_is_rejected_without_inserting_or_deleting_rows(): void
    {
        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->andReturn('mysql');
        $pdo->shouldReceive('quote')->andReturn("'source.csv'");
        $pdo->shouldReceive('beginTransaction')->once();
        $pdo->shouldReceive('query')->with('SELECT COUNT(*) FROM `monitoring_realisasi_ksbk`')->once()->andReturn($this->scalar(1));
        $pdo->shouldReceive('inTransaction')->andReturn(true);
        $pdo->shouldReceive('rollBack')->once();
        $pdo->shouldNotReceive('exec');
        $this->loader();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('hapus dataset melalui Kelola Report');
        (new MonitoringKsbkSourceService)->loadBulk($this->path(), 1, $pdo);
    }

    public function test_mysql_warning_rolls_back_instead_of_reporting_success(): void
    {
        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('getAttribute')->with(PDO::ATTR_DRIVER_NAME)->andReturn('mysql');
        $pdo->shouldReceive('quote')->andReturn("'source.csv'");
        $pdo->shouldReceive('beginTransaction')->once();
        $pdo->shouldReceive('query')->with('SELECT COUNT(*) FROM `monitoring_realisasi_ksbk`')->once()->andReturn($this->scalar(0));
        $pdo->shouldReceive('exec')->once()->with(Mockery::on(fn (string $sql): bool => str_starts_with($sql, 'LOAD DATA LOCAL INFILE')))->andReturn(1);
        $pdo->shouldReceive('query')->with('SHOW COUNT(*) WARNINGS')->once()->andReturn($this->scalar(1));
        $pdo->shouldReceive('inTransaction')->andReturn(true);
        $pdo->shouldReceive('rollBack')->once();
        $pdo->shouldNotReceive('commit');
        $this->loader();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('warning MySQL');
        (new MonitoringKsbkSourceService)->loadBulk($this->path(), 1, $pdo);
    }

    private function loader(): void
    {
        $loader = Mockery::mock(MySqlBulkLoadService::class);
        $loader->shouldReceive('assertTransactionalTable')->once();
        $loader->shouldReceive('withTableWriteLock')->once()->andReturnUsing(fn ($table, $callback) => $callback());
        $this->app->instance(MySqlBulkLoadService::class, $loader);
    }

    private function scalar(int $value): PDOStatement
    {
        $statement = Mockery::mock(PDOStatement::class);
        $statement->shouldReceive('fetchColumn')->once()->andReturn($value);

        return $statement;
    }

    private function row(): array
    {
        return ['003641', '00130026', '003520067011860000', 'CIF001', 'Nama Nasabah', '9007199254740993.01',
            '2026-09-22T00:00:00.000+07:00', 'Kupedes <= 150juta', 'Lolos Pre-wash', '49', 'R', '1', '2026-09-25', 'FORGED', '999999', '8F', 'FORGED'];
    }

    private function path(string $extension = 'csv'): string
    {
        $path = storage_path('framework/testing/ksbk-'.uniqid('', true).'.'.$extension);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        $this->files[] = $path;

        return $path;
    }

    private function cell(string $source, string $reference, string $replacement): void
    {
        $zip = new ZipArchive;
        $zip->open($source);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->addFromString('xl/worksheets/sheet1.xml', preg_replace('/<c r="'.$reference.'"[^>]*>.*?<\/c>/', $replacement, $xml));
        $zip->close();
    }

    private function workbook(array $rows, ?array $headers = null): string
    {
        $path = $this->path('xlsx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Lolos" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"/></Relationships>');
        $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ([$headers ?? MonitoringKsbkSourceService::SOURCE_HEADERS, ...$rows] as $rowIndex => $row) {
            $record = $rowIndex + 1;
            $xml .= '<row r="'.$record.'">';
            foreach ($row as $index => $value) {
                $letter = chr(65 + $index);
                $xml .= '<c r="'.$letter.$record.'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
            }
            $xml .= '</row>';
        }
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml.'</sheetData></worksheet>');
        $zip->close();

        return $path;
    }
}
