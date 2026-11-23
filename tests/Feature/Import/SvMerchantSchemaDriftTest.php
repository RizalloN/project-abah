<?php

namespace Tests\Feature\Import;

use App\Http\Controllers\Import\ImportFileController;
use App\Services\Import\ImportProgressService;
use App\Services\Import\SchemaDrift\SchemaDriftAnalyzer;
use App\Services\Import\SchemaDrift\SchemaDriftImportGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SvMerchantSchemaDriftTest extends TestCase
{
    private const TABLE = 'sv_merchant';

    private const MIGRATION = 'database/migrations/2026_11_23_000001_add_source_columns_to_sv_merchant.php';

    private const HEADERS = [
        'TAHUN', 'PERIODE', 'POSISI', 'KODE KANWIL', 'NAMA KANWIL', 'KODE KCI',
        'NAMA KCI', 'KODE BRANCH', 'NAMA BRANCH', 'JENIS', 'SEGMENTASI JENIS', 'SV_MERCHANT',
    ];

    private const ROW = [
        '2026', '2026-11', '2026-11-21', '06', 'KANWIL 6', '023',
        'KC Contoh', '0034', 'UNIT Contoh', 'EDC', 'RETAIL', '1025000.00',
    ];

    private array $sources = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', config('database.default'));
        config(['database.connections.sqlite.database' => ':memory:', 'import.cache_store' => 'array']);
        DB::purge('sqlite');

        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->string('PERIODE', 20)->nullable();
            $table->date('POSISI')->nullable();
            $table->string('NAMA_KCI', 150)->nullable();
            $table->string('NAMA_BRANCH', 150)->nullable();
            $table->decimal('SV_MERCHANT', 20, 2)->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->sources as $path) {
            unlink($path);
        }

        parent::tearDown();
    }

    public function test_migration_changes_the_seven_missing_destinations_into_matches(): void
    {
        $path = $this->source();
        $report = $this->analyze($path);
        $this->assertSame(
            ['tahun', 'kode_kanwil', 'nama_kanwil', 'kode_kci', 'kode_branch', 'jenis', 'segmentasi_jenis'],
            array_column($report['new_columns'], 'canonical'),
        );
        $this->assertTrue($report['has_pending_decision']);
        $existing = ['uniqueid_namareport' => 'existing', 'POSISI' => '2026-11-20', 'SV_MERCHANT' => 1250];
        DB::table(self::TABLE)->insert($existing);

        (require base_path(self::MIGRATION))->up();

        $report = app(SchemaDriftImportGuard::class)->assertReady(self::TABLE, $path, $this->csvOptions());
        $this->assertSame(12, $report['stats']['matched']);
        $this->assertSame(array_map(static fn (string $header): string => strtolower(str_replace(' ', '_', $header)), self::HEADERS), array_column($report['columns'], 'db_column'));
        $this->assertSame([], $report['new_columns']);
        $this->assertSame([], $report['missing_columns']);
        $row = (array) DB::table(self::TABLE)->first();
        $this->assertSame($existing, array_intersect_key($row, $existing));
        $this->assertNull($row['TAHUN']);
        $this->assertNull($row['SEGMENTASI_JENIS']);
    }

    public function test_real_new_headers_and_misaligned_rows_are_still_detected(): void
    {
        (require base_path(self::MIGRATION))->up();

        $report = $this->analyze($this->source([...self::HEADERS, 'FLAG BARU'], [...self::ROW, 'Y']));
        $this->assertSame(1, $report['stats']['new']);
        $this->assertSame(['flag_baru'], array_column($report['new_columns'], 'canonical'));
        $this->assertTrue($report['has_pending_decision']);

        $report = $this->analyze($this->source(self::HEADERS, [...self::ROW, 'extra cell']));
        $this->assertSame(1, $report['source']['misaligned_rows']);
        $this->assertContains('row_width', array_column($report['review_issues'], 'code'));
    }

    public function test_staging_preserves_all_source_fields_and_leading_zeroes(): void
    {
        $migration = require base_path(self::MIGRATION);
        $migration->up();
        $migration->up();
        $staged = app(ImportFileController::class)->prepareCsvStaging(101, [
            'file_path' => $this->source(),
            'table_name' => self::TABLE,
            'headers' => self::HEADERS,
            'selected_columns' => array_keys(self::HEADERS),
            'delimiter' => '|',
            'total_rows' => 1,
        ], app(ImportProgressService::class));
        $this->sources[] = $staged;

        $prepared = Cache::store('array')->get('csv_import_params_101');
        $this->assertSame(1, $prepared['prepared_rows']);
        $records = new \SplFileObject($staged);
        $values = array_combine($prepared['bulkColumns'], $records->fgetcsv(',', '"', '\\'));
        unset($records);
        $expected = array_combine(str_replace(' ', '_', self::HEADERS), self::ROW);
        $this->assertSame($expected, array_intersect_key($values, $expected));
    }

    private function source(array $headers = self::HEADERS, array $row = self::ROW): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sv-merchant-');
        $this->sources[] = $path;
        file_put_contents($path, implode('|', $headers)."\r\n".implode('|', $row)."\r\n");

        return $path;
    }

    private function csvOptions(): array
    {
        return SchemaDriftImportGuard::delimitedOptions('csv', '|');
    }

    private function analyze(string $path): array
    {
        return app(SchemaDriftAnalyzer::class)->analyze(self::TABLE, $path, $this->csvOptions() + [
            'record' => false, 'remember' => false,
        ]);
    }
}
