<?php

namespace Tests\Feature\Import;

use App\Services\Import\SchemaDrift\SchemaDriftAnalyzer;
use App\Services\Import\SchemaDrift\SchemaDriftDetector;
use App\Services\Import\SchemaDrift\SchemaDriftImportGuard;
use App\Services\Import\SchemaDrift\SchemaDriftRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BrilinkSummarySchemaDriftTest extends TestCase
{
    private const TABLE = 'brilink_web_laporan_summary_transaksi_brilink_web';

    private const VENDOR_HEADERS = [
        'textbox26', 'textbox1', 'textbox7', 'textbox4', 'textbox16', 'textbox12', 'textbox19', 'textbox13',
        'brilink_web_name', 'BRILINK_WEB_CODE', 'outlet_name', 'OUTLET_CODE', 'Total_transaksi',
        'textbox18', 'textbox20', 'textbox32',
    ];

    private const CANONICAL_HEADERS = [
        'PERIODE', 'FLAG', 'KANWIL', 'KODE_KANCA', 'CABANG', 'KODE_UKER', 'UKER',
        'MERCHANT_NAME', 'MERCHANT_CODE', 'OUTLET_NAME', 'OUTLET_CODE', 'TOTAL_TRANSAKSI',
        'TOTAL_NOMINAL', 'TOTAL_FEE', 'TOTAL_FEE_BRI',
    ];

    private array $sourceFiles = [];

    private array $testTables = [
        self::TABLE, 'nama_report', 'import_jobs', 'import_schema_drift_events',
        'import_column_format_profiles', 'import_source_header_baselines',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite') {
            $this->markTestSkipped('BRILink schema drift regression must run on isolated SQLite.');
        }
        foreach ($this->testTables as $table) {
            Schema::dropIfExists($table);
        }
        (require base_path('database/migrations/2026_10_20_000001_create_import_schema_drift_tables.php'))->up();
        (require base_path('database/migrations/2026_10_20_000002_create_import_source_header_baselines_table.php'))->up();
        Schema::create('nama_report', function (Blueprint $table): void {
            $table->id('id_report');
            $table->string('nama_report');
            $table->string('table_name');
            $table->boolean('active')->default(true);
        });
        Schema::create('import_jobs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('id_report');
            $table->string('status');
            $table->timestamps();
        });
        // Match database/schema/mysql-schema-bootstrap.sql, not the 15 source
        // positions: FLAG, KODE_KANCA and KODE_UKER are vendor metadata only.
        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->string('uniqueid_namareport', 50)->primary();
            $table->string('periode', 20)->nullable();
            $table->string('kanwil', 100)->nullable();
            $table->string('cabang', 100)->nullable();
            $table->string('uker', 100)->nullable();
            $table->string('merchant_name', 150)->nullable();
            $table->string('merchant_code', 50)->nullable();
            $table->string('outlet_name', 150)->nullable();
            $table->string('outlet_code', 50)->nullable();
            $table->bigInteger('total_transaksi')->nullable();
            $table->decimal('total_nominal', 18, 2)->nullable();
            $table->decimal('total_fee', 18, 2)->nullable();
            $table->decimal('total_fee_bri', 18, 2)->nullable();
            $table->timestamps();
        });
        DB::table('nama_report')->insert([
            'id_report' => 98,
            'nama_report' => 'BRILINK Web - Laporan Summary Transaksi',
            'table_name' => self::TABLE,
            'active' => 1,
        ]);
        DB::table(self::TABLE)->insert(['uniqueid_namareport' => 'existing', 'total_nominal' => 123.45]);
    }

    protected function tearDown(): void
    {
        foreach ($this->sourceFiles as $file) {
            @unlink($file);
        }
        if (config('database.default') === 'sqlite') {
            foreach ($this->testTables as $table) {
                Schema::dropIfExists($table);
            }
        }
        parent::tearDown();
    }

    public function test_exact_numbered_vendor_layout_matches_all_twelve_target_columns_and_is_ready(): void
    {
        $path = $this->source(self::VENDOR_HEADERS, [$this->vendorRow()]);
        $report = $this->analyze($path);

        $this->assertSame(12, $report['stats']['matched']);
        $this->assertSame(4, collect($report['columns'])->where('state', 'ignored')->count());
        $this->assertSame(0, $report['stats']['new']);
        $this->assertSame([], $report['missing_columns']);
        $this->assertSame([], $report['new_columns']);
        $this->assertFalse($report['has_blocking_issue']);
        $this->assertFalse($report['has_pending_decision']);
        $this->assertSame([
            'periode', null, null, 'kanwil', null, 'cabang', null, 'uker', 'merchant_name', 'merchant_code',
            'outlet_name', 'outlet_code', 'total_transaksi', 'total_nominal', 'total_fee', 'total_fee_bri',
        ], array_column($report['columns'], 'db_column'));

        $ready = app(SchemaDriftImportGuard::class)->assertReady(self::TABLE, $path, $this->driftOptions());
        $this->assertTrue($ready['source']['structural_scan']['complete']);
        $this->assertFalse($ready['has_blocking_issue']);
        $this->assertTargetUnchanged();
    }

    public function test_vendor_layout_without_row_number_preserves_legacy_compatibility(): void
    {
        $headers = self::VENDOR_HEADERS;
        $row = $this->vendorRow();
        array_splice($headers, 1, 1);
        array_splice($row, 1, 1);
        $report = app(SchemaDriftImportGuard::class)->assertReady(self::TABLE, $this->source($headers, [$row]), $this->driftOptions());

        $this->assertSame(12, $report['stats']['matched']);
        $this->assertSame(3, collect($report['columns'])->where('state', 'ignored')->count());
        $this->assertSame([], $report['missing_columns']);
        $this->assertSame([], $report['new_columns']);
        $this->assertFalse($report['has_pending_decision']);
        $this->assertTargetUnchanged();
    }

    public function test_canonical_fifteen_column_layout_still_passes_the_guard(): void
    {
        $row = $this->vendorRow();
        array_splice($row, 1, 1);
        $row[0] = 'October 2026';
        $report = app(SchemaDriftImportGuard::class)->assertReady(self::TABLE, $this->source(self::CANONICAL_HEADERS, [$row]), $this->driftOptions());

        $this->assertSame(12, $report['stats']['matched']);
        $this->assertSame(3, collect($report['columns'])->where('state', 'ignored')->count());
        $this->assertSame([], $report['missing_columns']);
        $this->assertSame([], $report['new_columns']);
        $this->assertFalse($report['has_blocking_issue']);
        $this->assertTargetUnchanged();
    }

    public function test_reordered_replaced_and_extra_vendor_headers_are_blocking_without_schema_proposals(): void
    {
        $reordered = self::VENDOR_HEADERS;
        [$reordered[8], $reordered[9]] = [$reordered[9], $reordered[8]];
        $replaced = self::VENDOR_HEADERS;
        $replaced[13] = 'nominal_baru';
        $extra = [...self::VENDOR_HEADERS, 'extra_vendor_field'];
        foreach ([$reordered, $replaced, $extra] as $headers) {
            $row = $this->vendorRow();
            if (count($headers) > count($row)) {
                $row[] = 'must not be silently accepted';
            }
            $path = $this->source($headers, [$row]);
            $report = $this->analyze($path);
            $this->assertTrue($report['has_blocking_issue']);
            $this->assertSame([], $report['new_columns']);
            $this->assertSame([], $report['adjustments']);
            $issue = collect($report['review_issues'])->firstWhere('code', 'brilink_layout');
            $this->assertNotNull($issue);
            $this->assertTrue($issue['blocking']);
            $guard = app(SchemaDriftImportGuard::class);
            try {
                $guard->assertReady(self::TABLE, $path, $this->driftOptions());
                $this->fail('A changed positional layout must never pass the server guard.');
            } catch (\RuntimeException) {
                $this->assertTrue($guard->lastReport()['has_blocking_issue']);
            }
            $this->assertTargetUnchanged();
        }
    }

    public function test_reordering_canonical_headers_is_also_blocking(): void
    {
        $headers = self::CANONICAL_HEADERS;
        [$headers[4], $headers[6]] = [$headers[6], $headers[4]];
        $row = $this->vendorRow();
        array_splice($row, 1, 1);
        $report = $this->analyze($this->source($headers, [$row]));

        $this->assertTrue($report['has_blocking_issue']);
        $this->assertSame([], $report['new_columns']);
        $this->assertSame([], $report['adjustments']);
        $this->assertContains('brilink_layout', array_column($report['review_issues'], 'code'));
        $this->assertTargetUnchanged();
    }

    public function test_rows_with_extra_or_missing_values_keep_the_original_width_protection(): void
    {
        $extra = [...$this->vendorRow(), 'unmapped value'];
        $short = array_slice($this->vendorRow(), 0, -1);
        foreach ([$extra, $short] as $row) {
            $path = $this->source(self::VENDOR_HEADERS, [$row]);
            $report = $this->analyze($path);
            $this->assertTrue($report['has_blocking_issue']);
            $this->assertSame(1, $report['source']['misaligned_rows']);
            $this->assertContains('row_width', array_column($report['review_issues'], 'code'));
            $this->assertSame([], $report['new_columns']);
            $this->assertTargetUnchanged();
        }
    }

    public function test_numbered_vendor_rows_with_non_integer_row_numbers_are_blocking(): void
    {
        foreach (['not-a-number', '1.5', '-1', ''] as $number) {
            $row = $this->vendorRow();
            $row[1] = $number;
            $report = $this->analyze($this->source(self::VENDOR_HEADERS, [$row]));
            $this->assertTrue($report['has_blocking_issue'], 'Invalid row number: '.var_export($number, true));
            $this->assertSame([], $report['new_columns']);
            $this->assertSame([], $report['adjustments']);
            $this->assertContains('brilink_layout', array_column($report['review_issues'], 'code'));
            $this->assertTargetUnchanged();
        }
    }

    public function test_corrected_scan_resolves_an_old_false_alert_only_for_the_identical_source_file(): void
    {
        $path = $this->source(self::VENDOR_HEADERS, [$this->vendorRow()]);
        $current = $this->analyze($path);
        // Reproduce the earlier generic detector: opaque vendor textbox names
        // were treated as new database columns without the positional layout.
        $legacy = app(SchemaDriftDetector::class)->detect(self::TABLE, self::VENDOR_HEADERS, [$this->vendorRow()], [
            'canonical_headers' => array_column($current['columns'], 'canonical'),
            'baseline_established' => true,
            'structural_scan' => $current['source']['structural_scan'],
        ]);
        $legacy['source_fingerprint'] = $current['source_fingerprint'];
        $legacy['source'] = $current['source'];
        $this->assertNotEmpty($legacy['new_columns']);
        $this->assertTrue($legacy['has_pending_decision']);
        $registry = app(SchemaDriftRegistry::class);
        $sameFileEvent = $registry->record($legacy, basename($path));
        $this->assertNotNull($sameFileEvent);
        $this->assertSame('pending', $sameFileEvent->status);

        $otherRow = $this->vendorRow();
        $otherRow[9] = '10009999';
        $otherPath = $this->source(self::VENDOR_HEADERS, [$otherRow]);
        $otherSource = $this->analyze($otherPath);
        $this->assertNotSame($current['source_fingerprint'], $otherSource['source_fingerprint']);
        $otherLegacy = $legacy;
        $otherLegacy['source_fingerprint'] = $otherSource['source_fingerprint'];
        $otherLegacy['source'] = $otherSource['source'];
        $otherFileEvent = $registry->record($otherLegacy, basename($otherPath));
        $this->assertNotNull($otherFileEvent);

        $resolved = app(SchemaDriftAnalyzer::class)->analyze(self::TABLE, $path, $this->driftOptions() + ['remember' => false]);

        $this->assertContains((int) $sameFileEvent->id, $resolved['resolved_events']);
        $this->assertNotContains((int) $otherFileEvent->id, $resolved['resolved_events']);
        $this->assertSame('resolved', DB::table(SchemaDriftRegistry::TABLE)->where('id', $sameFileEvent->id)->value('status'));
        $this->assertSame('pending', DB::table(SchemaDriftRegistry::TABLE)->where('id', $otherFileEvent->id)->value('status'));
        $this->assertTargetUnchanged();
    }

    private function driftOptions(): array
    {
        return SchemaDriftImportGuard::delimitedOptions('csv', ',');
    }

    private function analyze(string $path): array
    {
        return app(SchemaDriftAnalyzer::class)->analyze(self::TABLE, $path, $this->driftOptions() + ['record' => false, 'remember' => false]);
    }

    private function vendorRow(): array
    {
        return ['Periode : October 2026', '1', 'R', 'KANWIL MALANG', '0045', 'KC Madiun', '6496', 'UNIT SAMPLE',
            'Agen Madiun', '10004595', 'Outlet Madiun', '10004652', '2', '200.00', '4.00', '10.00'];
    }

    private function source(array $headers, array $rows): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'brilink_drift_');
        $path = $temporary.'.csv';
        rename($temporary, $path);
        $stream = fopen($path, 'w');
        fputcsv($stream, $headers, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, $row, ',', '"', '');
        }
        fclose($stream);
        $this->sourceFiles[] = $path;

        return $path;
    }

    private function assertTargetUnchanged(): void
    {
        $this->assertSame(1, DB::table(self::TABLE)->count());
        $this->assertSame('existing', DB::table(self::TABLE)->value('uniqueid_namareport'));
        $this->assertSame(123.45, (float) DB::table(self::TABLE)->value('total_nominal'));
        $this->assertFalse(Schema::hasColumn(self::TABLE, 'textbox26'));
        $this->assertFalse(Schema::hasColumn(self::TABLE, 'nominal_baru'));
    }
}
