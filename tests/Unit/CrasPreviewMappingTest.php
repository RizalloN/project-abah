<?php

namespace Tests\Unit;

use App\Services\Import\CrasSourceService;
use App\Services\Import\SchemaDrift\SchemaDriftAnalyzer;
use App\Services\Import\SchemaDrift\SourceHeaderBaseline;
use App\Services\Import\Strategies\CrasImportStrategy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrasPreviewMappingTest extends TestCase
{
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::create('cras', function (Blueprint $table): void {
            $table->string('cras_uuid')->primary();
            $table->date('cras_periode');
            foreach (CrasSourceService::SOURCE_COLUMNS as $column) {
                $table->text($column)->nullable();
            }
            $table->timestamps();
        });
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
        return [['txt'], ['utf16'], ['xlsx']];
    }

    #[DataProvider('formats')]
    public function test_preview_maps_all_source_columns_and_excludes_generated_fields(string $format): void
    {
        $path = $this->source($format);
        $report = app(SchemaDriftAnalyzer::class)->analyze('cras', $path, ['record' => false, 'remember' => false]);
        $this->assertSame(33, $report['stats']['matched']);
        $this->assertSame(0, $report['stats']['unmapped']);
        $this->assertSame(0, $report['stats']['missing']);
        $this->assertSame(0, $report['stats']['new']);
        $this->assertSame(CrasSourceService::SOURCE_COLUMNS, array_column($report['columns'], 'db_column'));
        $this->assertSame('BR#', $report['columns'][2]['header']);
        $this->assertSame('br_number', $report['columns'][2]['db_column']);
        $this->assertSame('matched', $report['columns'][2]['state']);

        // Use the real CRAS parser/stager and the same column order as LOAD DATA.
        $service = new CrasSourceService;
        $inspection = $service->inspect($path);
        $this->assertSame('2026-09-30', $inspection['period']);
        $this->assertSame($this->rows(), $inspection['preview_rows']);
        $staged = $this->temporaryPath('csv');
        $stage = $service->stageForImport($path, $staged, ['KC Madiun'], '2026-09-30');
        $this->assertSame(3, $stage['imported_rows']);
        $handle = fopen($staged, 'rb');
        try {
            foreach ($this->rows() as $index => $expected) {
                $row = fgetcsv($handle, 0, ',', '"', '\\');
                $this->assertSame($expected, $row);
                DB::table('cras')->insert(array_combine(CrasSourceService::SOURCE_COLUMNS, $row) + [
                    'cras_uuid' => 'test-'.($index + 1), 'cras_periode' => $inspection['period'],
                ]);
            }
        } finally {
            fclose($handle);
        }
        $stored = DB::table('cras')->orderBy('cras_uuid')->get();
        $this->assertCount(3, $stored);
        $this->assertSame(['45', '45', '45'], $stored->pluck('br_number')->all());
        foreach ($stored as $index => $row) {
            $this->assertSame($this->rows()[$index], array_map(fn ($column) => $row->{$column}, CrasSourceService::SOURCE_COLUMNS));
        }
    }

    public function test_strategy_uses_source_names_without_hiding_unknown_or_reordering_columns(): void
    {
        $strategy = new CrasImportStrategy;
        $this->assertSame(CrasSourceService::SOURCE_COLUMNS, $strategy->transformHeaders(CrasSourceService::SOURCE_HEADERS));
        $this->assertSame(['produk', 'br_number', 'Kolom Tambahan'], $strategy->transformHeaders(['Produk', 'BR#', 'Kolom Tambahan']));
    }

    public function test_rescan_corrects_a_branch_header_previously_recorded_as_unmapped(): void
    {
        app(SourceHeaderBaseline::class)->record('cras', [[
            'canonical' => 'br', 'header' => 'BR#', 'state' => 'unmapped', 'db_column' => null,
        ]]);
        $report = app(SchemaDriftAnalyzer::class)->analyze('cras', $this->source('txt'), ['record' => false, 'remember' => false]);
        $this->assertTrue($report['baseline_established']);
        $this->assertSame(33, $report['stats']['matched']);
        $this->assertSame(0, $report['stats']['unmapped']);
        $this->assertSame(0, $report['stats']['missing']);
        $this->assertSame('br_number', $report['columns'][2]['db_column']);
    }

    private function rows(): array
    {
        $base = array_fill_keys(CrasSourceService::SOURCE_COLUMNS, '0');
        $base = array_replace($base, [
            'month_day_year_of_posisi' => 'September 30, 2026', 'ket_kanca' => 'KC Madiun', 'br_number' => '45',
            'ket_unit_kerja' => 'KC Madiun', 'status_rekening' => 'AKTIF', 'segmen' => 'Commercial', 'produk' => 'Commercial',
            'loan_type' => '0L', 'tahun_realisasi' => '2026', 'ket_produk_tiering' => '25M sd 50M',
            'kualitas_bulan_lalu' => 'L', 'kualitas' => 'L', 'flag_movement_kualitas' => 'STAY',
            'detail_movement_kualitas' => 'STAY', 'kol_adk' => '1', 'flag_restruk' => 'N',
            'jumlah_debitur' => '1', 'jumlah_rekening' => '1',
        ]);

        return array_map(static fn (array $values): array => array_values(array_replace($base, $values)), [
            ['sektor_ekonomi' => 'JASA KEMASYARAKATAN, SOSIAL BUDAYA, HIBURAN DAN PERORANGAN LAINNYA', 'sub_sektor_ekonomi' => 'Jasa Kegiatan Lainnya', 'accint' => '36.507.716', 'baki_debet' => '8.766.655.368', 'ckpn_mo' => '94.896.508', 'nilai_tercatat' => '8.803.163.084', 'plafond' => '8.766.655.368'],
            ['sektor_ekonomi' => 'KONSTRUKSI', 'sub_sektor_ekonomi' => 'Konstruksi Khusus', 'accint' => '75.543.429', 'baki_debet' => '17.233.344.632', 'ckpn_mo' => '186.273.292', 'jumlah_rekening' => '3', 'nilai_tercatat' => '17.308.888.061', 'plafond' => '17.233.344.632'],
            ['sektor_ekonomi' => 'PERDAGANGAN BESAR DAN ECERAN', 'sub_sektor_ekonomi' => 'Perdagangan Dalam Negeri Semen', 'loan_type' => 'DL', 'tahun_realisasi' => '2025', 'ket_produk_tiering' => '100M sd 200M', 'accint' => '43.768.393', 'baki_debet' => '24.961.661.631', 'ckpn_mo' => '399.554.923', 'nilai_tercatat' => '25.005.430.024', 'plafond' => '30.000.000.000'],
        ]);
    }

    private function temporaryPath(string $extension): string
    {
        $directory = storage_path('framework/testing');
        if (! is_dir($directory)) {
            mkdir($directory, 0750, true);
        }
        $path = $directory.'/cras-preview-'.bin2hex(random_bytes(8)).'.'.$extension;
        $this->files[] = $path;

        return $path;
    }

    private function source(string $format): string
    {
        $path = $this->temporaryPath($format === 'utf16' ? 'txt' : $format);
        $rows = [CrasSourceService::SOURCE_HEADERS, ...$this->rows()];
        if ($format === 'xlsx') {
            $book = new Spreadsheet;
            foreach ($rows as $rowIndex => $row) {
                foreach ($row as $column => $value) {
                    $book->getActiveSheet()->setCellValueExplicit([$column + 1, $rowIndex + 1], $value, DataType::TYPE_STRING);
                }
            }
            (new Xlsx($book))->save($path);
            $book->disconnectWorksheets();
        } else {
            $handle = fopen($path, 'wb');
            foreach ($rows as $row) {
                fputcsv($handle, $row, "\t", '"', '');
            }
            fclose($handle);
            if ($format === 'utf16') {
                file_put_contents($path, "\xFF\xFE".mb_convert_encoding(file_get_contents($path), 'UTF-16LE', 'UTF-8'));
            }
        }

        return $path;
    }
}
