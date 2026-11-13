<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use App\Services\Import\SchemaDrift\SchemaDriftAnalyzer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class SsaAlmafactsPreviewMappingTest extends TestCase
{
    private const HEADERS = ['Month, Day, Year of 00. Posisi', '03. Kanca Konsolidasi', '05. Kode Unit Kerja', '04. Unit Kerja', 'Keterangan', 'Saldo'];

    private const COLUMNS = ['month_day_year_of_posisi', 'kanca_konsolidasi', 'kode_unit_kerja', 'unit_kerja', 'keterangan', 'saldo'];

    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        foreach (['ssa_almafacts', 'demo_almafacts'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->string('uniqueid_namareport')->primary();
                $table->date('month_day_year_of_posisi')->nullable();
                $table->string('kanca_konsolidasi')->nullable();
                $table->string('kode_unit_kerja')->nullable();
                $table->string('unit_kerja')->nullable();
                $table->string('keterangan')->nullable();
                $table->decimal('saldo', 30, 12)->nullable();
                $table->timestamps();
            });
        }
        (require database_path('migrations/2026_10_20_000001_create_import_schema_drift_tables.php'))->up();
        (require database_path('migrations/2026_10_20_000002_create_import_source_header_baselines_table.php'))->up();
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    public static function formats(): array
    {
        return [['xlsx'], ['csv']];
    }

    #[DataProvider('formats')]
    public function test_renamed_source_columns_match_all_six_import_destinations(string $format): void
    {
        $path = $this->source($format);
        $report = app(SchemaDriftAnalyzer::class)->analyze('ssa_almafacts', $path, ['record' => false, 'remember' => false]);
        $this->assertSame(6, $report['stats']['matched']);
        $this->assertSame(0, $report['stats']['unmapped']);
        $this->assertSame(0, $report['stats']['missing']);
        $this->assertSame(0, $report['stats']['new']);
        $this->assertSame(self::COLUMNS, array_column($report['columns'], 'db_column'));
        $this->assertSame(self::HEADERS, array_column($report['columns'], 'header'));
        $this->assertFalse($report['has_blocking_issue']);

        $controller = new ImportExcelController;
        $context = (new ReflectionMethod($controller, 'buildImportContext'))->invoke($controller, 'ssa_almafacts', self::HEADERS);
        $mapper = new ReflectionMethod($controller, 'mapExcelRowForInsert');
        foreach ($this->rows() as $sourceRow) {
            $mapped = $mapper->invoke($controller, $sourceRow, self::HEADERS, $context, '2026-11-09 12:00:00');
            $this->assertIsArray($mapped);
            foreach (self::COLUMNS as $column) {
                $this->assertNotNull($mapped[$column]);
            }
            DB::table('ssa_almafacts')->insert($mapped);
        }
        $stored = DB::table('ssa_almafacts')->orderBy('keterangan')->get();
        $this->assertCount(4, $stored);
        $this->assertSame(['00. Amount', '00. Amount RKA', '01. Pendapatan Bunga', '02. Beban FTP'], $stored->pluck('keterangan')->all());
        $this->assertSame([10708003287371.0, 4160858350353.0, 116243917441.0, -79525406954.0], $stored->pluck('saldo')->map(fn ($value): float => (float) $value)->all());
        $this->assertSame(['2026-10-31'], $stored->pluck('month_day_year_of_posisi')->unique()->values()->all());
        $this->assertSame(['KC Madiun'], $stored->pluck('kanca_konsolidasi')->unique()->values()->all());
        $this->assertSame(['45'], $stored->pluck('kode_unit_kerja')->unique()->values()->all());
        $this->assertSame(['KC Madiun'], $stored->pluck('unit_kerja')->unique()->values()->all());
    }

    public function test_positional_mapping_does_not_apply_to_other_report_tables(): void
    {
        $report = app(SchemaDriftAnalyzer::class)->analyze('demo_almafacts', $this->source('csv'), ['record' => false, 'remember' => false]);
        $this->assertSame(2, $report['stats']['matched']);
        $this->assertSame(4, $report['stats']['missing']);
    }

    public function test_preamble_is_not_mapped_as_the_six_column_header(): void
    {
        $report = app(SchemaDriftAnalyzer::class)->analyze('ssa_almafacts', $this->source('csv', true), ['record' => false, 'remember' => false]);
        $this->assertSame(3, $report['source']['header_row']);
        $this->assertSame(6, $report['stats']['matched']);
        $this->assertSame(4, $report['stats']['sample_rows']);
    }

    public function test_all_six_labels_can_change_without_losing_positional_values(): void
    {
        $headers = ['Tanggal Data', 'Cabang Induk', 'Kode Kantor', 'Nama Kantor', 'Status Indikator', 'Nilai'];
        $report = app(SchemaDriftAnalyzer::class)->analyze('ssa_almafacts', $this->source('csv', false, $headers), ['record' => false, 'remember' => false]);
        $this->assertSame(6, $report['stats']['matched']);
        $this->assertSame(self::COLUMNS, array_column($report['columns'], 'db_column'));
        $this->assertSame($headers, array_column($report['columns'], 'header'));
        $controller = new ImportExcelController;
        $context = (new ReflectionMethod($controller, 'buildImportContext'))->invoke($controller, 'ssa_almafacts', $headers);
        $mapped = (new ReflectionMethod($controller, 'mapExcelRowForInsert'))->invoke($controller, $this->rows()[0], $headers, $context, '2026-11-09 12:00:00');
        $this->assertSame('2026-10-31', $mapped['month_day_year_of_posisi']);
        $this->assertSame('00. Amount', $mapped['keterangan']);
        $this->assertSame(10708003287371.0, (float) $mapped['saldo']);
    }

    private function rows(): array
    {
        return array_map(static fn (array $metric): array => ['October 31, 2026', 'KC Madiun', '45', 'KC Madiun', ...$metric], [
            ['00. Amount', '10.708.003.287.371'], ['00. Amount RKA', '4.160.858.350.353'],
            ['01. Pendapatan Bunga', '116.243.917.441'], ['02. Beban FTP', '-79.525.406.954'],
        ]);
    }

    private function source(string $format, bool $preamble = false, array $headers = self::HEADERS): string
    {
        $path = storage_path('framework/testing/ssa-almafacts-drift-'.bin2hex(random_bytes(8)).'.'.$format);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0750, true);
        }
        $this->files[] = $path;
        $rows = [...($preamble ? [['SSA Analisis'], ['PERIODE', 'October 31, 2026']] : []), $headers, ...$this->rows()];
        if ($format === 'xlsx') {
            $book = new Spreadsheet;
            $book->getActiveSheet()->fromArray($rows, null, 'A1');
            (new Xlsx($book))->save($path);
            $book->disconnectWorksheets();
        } else {
            $handle = fopen($path, 'wb');
            foreach ($rows as $row) {
                fputcsv($handle, $row, ';', '"', '');
            }
            fclose($handle);
        }

        return $path;
    }
}
