<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportFileController;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportFileControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();
    }

    public function test_raw_load_data_fast_path_is_disabled_for_qris_detail_report(): void
    {
        $controller = new ImportFileController();
        $csvPath = storage_path('framework/testing/qris_detail_quotes.csv');

        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'PERIODE|POSISI|REGION|RGDESC|MAINBR|MBDESC',
            '2025-04|2025-04-30|R|KANWIL MALANG|45|TOKO "SUMBER AGUNG"',
        ]) . "\n");

        try {
            $skip = $this->invokeMethod($controller, 'shouldSkipRawLoadDataFastPath', [
                'jumlah_merchant_qris_detail',
                $csvPath,
                '|',
            ]);

            $this->assertTrue($skip);
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_raw_load_data_fast_path_is_disabled_when_sample_contains_quotes(): void
    {
        $controller = new ImportFileController();
        $csvPath = storage_path('framework/testing/generic_quotes.csv');

        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'col1|col2|col3',
            'A|merchant "alpha"|C',
        ]) . "\n");

        try {
            $skip = $this->invokeMethod($controller, 'shouldSkipRawLoadDataFastPath', [
                'jumlah_merchant_detail',
                $csvPath,
                '|',
            ]);

            $this->assertTrue($skip);
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_merchant_import_does_not_fallback_when_configured_table_is_missing(): void
    {
        $controller = new ImportFileController();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('missing_merchant_table');

        $this->invokeMethod($controller, 'resolveTableName', [
            (object) [
                'nama_report' => 'Jumlah Merchant Detail',
                'table_name' => 'missing_merchant_table',
            ],
        ]);
    }

    public function test_qris_detail_posisi_detection_ignores_saldo_columns(): void
    {
        $controller = new ImportFileController();
        $csvPath = storage_path('framework/testing/qris_detail_posisi_detection.csv');

        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'PERIODE|POSISI|MBDESC|MERCHANT_PAN|SALDO_POSISI|RATAS_SALDO',
            '2026-05|2026-05-08|KC Madiun|9360000200422278155|4577320.00|3215184.00',
        ]) . "\n");

        try {
            $meta = $this->invokeMethod($controller, 'collectImportMeta', [
                $csvPath,
                [],
                [],
                '|',
                false,
                false,
            ]);

            $this->assertSame(1, $meta['posisi_index']);
            $this->assertSame('2026-05-08', $meta['sample_posisi']);
            $this->assertSame('2026-05', $meta['sample_periode']);
            $this->assertSame(1, $meta['total_rows']);
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_qris_detail_filtered_meta_uses_exact_posisi_header(): void
    {
        $controller = new ImportFileController();
        $csvPath = storage_path('framework/testing/qris_detail_filtered_posisi_detection.csv');

        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'PERIODE|POSISI|MBDESC|MERCHANT_PAN|SALDO_POSISI|RATAS_SALDO',
            '2026-05|2026-05-08|KC Banyuwangi|9360000200133314471|999.00|888.00',
            '2026-05|2026-05-08|KC Madiun|9360000200422278155|4577320.00|3215184.00',
        ]) . "\n");

        try {
            $meta = $this->invokeMethod($controller, 'collectImportMeta', [
                $csvPath,
                [],
                [2 => ['KC Madiun' => true]],
                '|',
                false,
                false,
            ]);

            $this->assertSame(1, $meta['posisi_index']);
            $this->assertSame('2026-05-08', $meta['sample_posisi']);
            $this->assertSame(1, $meta['total_rows']);
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_generic_import_meta_normalizes_dd_mmm_yy_posisi_for_duplicate_guard(): void
    {
        $controller = new ImportFileController();
        $csvPath = storage_path('framework/testing/generic_dd_mmm_yy_posisi_detection.csv');

        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'POSISI|NAMA_KANCA|NOMINAL',
            '16 Jul 26|KC Madiun|100',
        ]) . "\n");

        try {
            $meta = $this->invokeMethod($controller, 'collectImportMeta', [
                $csvPath,
                [],
                [],
                '|',
                false,
                false,
            ]);

            $this->assertSame(0, $meta['posisi_index']);
            $this->assertSame('2026-07-16', $meta['sample_posisi']);
            $this->assertSame('2026-07-16', $meta['sample_periode']);
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_merchant_detail_meta_normalizes_dd_mmm_yy_period_for_daily_import(): void
    {
        $controller = new ImportFileController();
        $csvPath = storage_path('framework/testing/merchant_detail_dd_mmm_yy_period.csv');

        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'PERIODE|POSISI|TID|NAMA_KANCA',
            '13 Jul 26|13 Jul 26|TID-001|KC Madiun',
        ]) . "\n");

        try {
            $meta = $this->invokeMethod($controller, 'collectImportMeta', [
                $csvPath,
                [],
                [],
                '|',
                false,
                true,
            ]);

            $this->assertSame('2026-07-13', $meta['sample_periode']);
            $this->assertSame('2026-07-13', $meta['sample_posisi']);
            $this->assertSame([
                ['periode' => '2026-07-13', 'tid' => 'TID-001'],
            ], $meta['periode_tid_pairs']);
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_merchant_detail_meta_uses_posisi_when_periode_header_is_missing(): void
    {
        $controller = new ImportFileController();
        $csvPath = storage_path('framework/testing/merchant_detail_posisi_only_period.csv');

        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'POSISI|TID|NAMA_KANCA',
            '14 Jul 26|TID-001|KC Madiun',
        ]) . "\n");

        try {
            $meta = $this->invokeMethod($controller, 'collectImportMeta', [
                $csvPath,
                [],
                [],
                '|',
                false,
                true,
            ]);

            $this->assertSame('2026-07-14', $meta['sample_periode']);
            $this->assertSame('2026-07-14', $meta['sample_posisi']);
            $this->assertSame([
                ['periode' => '2026-07-14', 'tid' => 'TID-001'],
            ], $meta['periode_tid_pairs']);
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_merchant_detail_meta_prefers_posisi_over_monthly_periode_for_duplicate_guard(): void
    {
        $controller = new ImportFileController();
        $csvPath = storage_path('framework/testing/merchant_detail_posisi_daily_period.csv');

        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'PERIODE|POSISI|TID|NAMA_KANCA',
            'Jul 2026|11 Jul 26|TID-001|KC Madiun',
        ]) . "\n");

        try {
            $meta = $this->invokeMethod($controller, 'collectImportMeta', [
                $csvPath,
                [],
                [],
                '|',
                false,
                true,
            ]);

            $this->assertSame([
                ['periode' => '2026-07-11', 'tid' => 'TID-001'],
            ], $meta['periode_tid_pairs']);
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_merchant_detail_duplicate_key_uses_posisi_when_periode_is_empty(): void
    {
        $key = $this->invokeMethod(new ImportFileController(), 'extractJumlahMerchantDuplicateKey', [[
            'POSISI' => '15 Jul 26',
            'TID' => 'TID-001',
        ]]);

        $this->assertSame(['2026-07-15', 'TID-001'], $key);
    }

    public function test_merchant_detail_duplicate_key_prefers_posisi_over_periode(): void
    {
        $key = $this->invokeMethod(new ImportFileController(), 'extractJumlahMerchantDuplicateKey', [[
            'PERIODE' => 'Jul 2026',
            'POSISI' => '11 Jul 26',
            'TID' => 'TID-001',
        ]]);

        $this->assertSame(['2026-07-11', 'TID-001'], $key);
    }

    public function test_merchant_detail_duplicate_lookup_matches_raw_dd_mmm_yy_existing_period(): void
    {
        Schema::create('jumlah_merchant_detail', function ($table): void {
            $table->string('PERIODE')->nullable();
            $table->string('TID')->nullable();
        });

        DB::table('jumlah_merchant_detail')->insert([
            'PERIODE' => '13 Jul 26',
            'TID' => 'TID-001',
        ]);

        $lookup = $this->invokeMethod(new ImportFileController(), 'buildJumlahMerchantDuplicateLookup', [[
            ['periode' => '2026-07-13', 'tid' => 'TID-001'],
            ['periode' => '2026-07-14', 'tid' => 'TID-002'],
        ]]);

        $this->assertArrayHasKey('2026-07-13|TID-001', $lookup);
        $this->assertArrayNotHasKey('2026-07-14|TID-002', $lookup);
    }

    public function test_merchant_detail_duplicate_lookup_matches_existing_posisi_only_for_same_date(): void
    {
        Schema::create('jumlah_merchant_detail', function ($table): void {
            $table->string('PERIODE')->nullable();
            $table->date('POSISI')->nullable();
            $table->string('TID')->nullable();
        });

        DB::table('jumlah_merchant_detail')->insert([
            'PERIODE' => null,
            'POSISI' => '2026-07-13',
            'TID' => 'TID-001',
        ]);

        $lookup = $this->invokeMethod(new ImportFileController(), 'buildJumlahMerchantDuplicateLookup', [[
            ['periode' => '2026-07-13', 'tid' => 'TID-001'],
            ['periode' => '2026-07-14', 'tid' => 'TID-001'],
        ]]);

        $this->assertArrayHasKey('2026-07-13|TID-001', $lookup);
        $this->assertArrayNotHasKey('2026-07-14|TID-001', $lookup);
    }

    public function test_merchant_detail_duplicate_lookup_does_not_match_same_tid_on_different_posisi(): void
    {
        Schema::create('jumlah_merchant_detail', function ($table): void {
            $table->string('PERIODE')->nullable();
            $table->date('POSISI')->nullable();
            $table->string('TID')->nullable();
        });

        DB::table('jumlah_merchant_detail')->insert([
            'PERIODE' => 'Jul 2026',
            'POSISI' => '2026-07-10',
            'TID' => 'TID-001',
        ]);

        $lookup = $this->invokeMethod(new ImportFileController(), 'buildJumlahMerchantDuplicateLookup', [[
            ['periode' => '2026-07-11', 'tid' => 'TID-001'],
        ]]);

        $this->assertArrayNotHasKey('2026-07-11|TID-001', $lookup);
    }

    public function test_import_date_index_normalization_repairs_stale_saldo_posisi_index(): void
    {
        $controller = new ImportFileController();

        [$posisiIndex, $tahunIndex] = $this->invokeMethod($controller, 'normalizeImportDateHeaderIndexes', [
            [
                'TAHUN',
                'PERIODE',
                'POSISI',
                'NAMA_KANCA',
                'SALDO_POSISI',
                'RATAS_SALDO',
                'SALDO_POSISI_BY_CIF',
            ],
            6,
            0,
        ]);

        $this->assertSame(2, $posisiIndex);
        $this->assertSame(0, $tahunIndex);
    }

    public function test_ibbisniz_corp_textbox_headers_map_to_requested_columns(): void
    {
        $controller = new ImportFileController();

        $blueprint = $this->invokeMethod($controller, 'buildColumnImportBlueprint', [
            range(0, 7),
            ['textbox10', 'textbox11', 'textbox12', 'textbox7', 'textbox13', 'JUMLAHTRANSAKSI', 'NOMINAL', 'FEE'],
            'ibbisniz_corp',
        ]);

        $this->assertSame([
            'wilayah',
            'cabang',
            'uker',
            'corporate_id',
            'nama_perusahaan',
            'jml_trx_sukses',
            'nominal',
            'fee_transaksi',
        ], array_column($blueprint, 'column'));

        $this->assertSame('numeric', $blueprint[5]['type']);
        $this->assertSame('numeric', $blueprint[6]['type']);
        $this->assertSame('numeric', $blueprint[7]['type']);
    }

    public function test_ibbisniz_corp_bulk_columns_keep_periode_out_of_main_fastpath(): void
    {
        Schema::create('ibbisniz_corp', function ($table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode')->nullable();
            $table->string('wilayah')->nullable();
            $table->timestamps();
        });

        $controller = new ImportFileController();

        $columns = $this->invokeMethod($controller, 'buildBulkLoadColumnsForMappedRows', [
            'ibbisniz_corp',
            false,
            [
                ['column' => 'wilayah'],
            ],
        ]);

        $this->assertNotContains('periode', $columns);
        $this->assertSame(['uniqueid_namareport', 'wilayah', 'created_at', 'updated_at'], $columns);
    }

    public function test_manual_periode_is_applied_only_for_ibbiz_tables(): void
    {
        $controller = new ImportFileController();

        $ibbizRow = $this->invokeMethod($controller, 'applyManualImportPeriode', [
            ['uniqueid_namareport' => 'row-1', 'wilayah' => 'A'],
            'ibbisniz_corp',
            '2026-05-10',
        ]);

        $genericRow = $this->invokeMethod($controller, 'applyManualImportPeriode', [
            ['uniqueid_namareport' => 'row-2', 'wilayah' => 'A'],
            'jumlah_merchant_detail',
            '2026-05-10',
        ]);

        $this->assertSame('2026-05-10', $ibbizRow['periode']);
        $this->assertArrayNotHasKey('periode', $genericRow);
    }

    public function test_ibbiz_period_bulk_values_use_manual_or_source_date(): void
    {
        $controller = new ImportFileController();

        $manualPeriod = $this->invokeMethod($controller, 'resolveIbbizBulkPeriodeValue', [
            'ibbisniz_corp',
            ['R - KANWIL MALANG', '7 - KC Banyuwangi'],
            '2026-04-01',
        ]);

        $sourcePeriod = $this->invokeMethod($controller, 'resolveIbbizBulkPeriodeValue', [
            'usak_ibbiz_uker',
            ['4/1/2026 12:00:00 AM', '4', 'R - KANWIL MALANG'],
            null,
        ]);

        $sourcePeriodWithManual = $this->invokeMethod($controller, 'resolveIbbizBulkPeriodeValue', [
            'usak_ibbiz_uker',
            ['4/1/2026 12:00:00 AM', '4', 'R - KANWIL MALANG'],
            '2026-05-22',
        ]);

        $this->assertSame('2026-04-01', $manualPeriod);
        $this->assertSame('2026-04-01', $sourcePeriod);
        $this->assertSame('2026-05-22', $sourcePeriodWithManual);
    }

    public function test_usak_ibbiz_uker_bypasses_date_and_no_columns(): void
    {
        $controller = new ImportFileController();

        $blueprint = $this->invokeMethod($controller, 'buildColumnImportBlueprint', [
            range(0, 9),
            ['textbox23', 'textbox10', 'textbox11', 'textbox12', 'textbox7', 'textbox13', 'textbox14', 'textbox5', 'textbox4', 'REFERRAL'],
            'usak_ibbiz_uker',
        ]);

        $this->assertSame([
            'kanwil',
            'kanca',
            'uker',
            'corporate_id',
            'nama_perusahaan',
            'status',
            'deskripsi',
            'referral',
        ], array_column($blueprint, 'column'));

        $this->assertSame([2, 3, 4, 5, 6, 7, 8, 9], array_column($blueprint, 'index'));
    }

    public function test_ibbiz_preview_headers_are_mapped_neatly(): void
    {
        $controller = new ImportFileController();

        $prettyCorp = $this->invokeMethod($controller, 'getPrettyIbbizHeaders', [
            'ibbisniz_corp',
            ['textbox10', 'TEXTBOX11', 'textbox12', 'textbox7', 'textbox13', 'JUMLAHTRANSAKSI', 'NOMINAL', 'FEE', 'other_column'],
        ]);

        $prettyUker = $this->invokeMethod($controller, 'getPrettyIbbizHeaders', [
            'usak_ibbiz_uker',
            ['textbox23', 'textbox10', 'textbox11', 'textbox12', 'textbox7', 'textbox13', 'textbox14', 'textbox5', 'textbox4', 'REFERRAL'],
        ]);

        $this->assertSame([
            'Wilayah', 'Cabang', 'Uker', 'Corporate ID', 'Nama Perusahaan', 'Jml Trx Sukses', 'Nominal', 'Fee Transaksi', 'Other Column'
        ], $prettyCorp);

        $this->assertSame([
            'Periode', 'No', 'Kanwil', 'Kanca', 'Uker', 'Corporate ID', 'Nama Perusahaan', 'Status', 'Deskripsi', 'Referral'
        ], $prettyUker);
    }

    public function test_ibbisniz_corp_preview_enables_portaled_filter_dropdowns(): void
    {
        $response = $this->buildIbbizPreviewResponse(
            'ibbisniz_corp',
            'IBBIS FCORPID',
            implode(',', ['textbox10', 'textbox11', 'textbox12', 'textbox7', 'textbox13', 'JUMLAHTRANSAKSI', 'NOMINAL', 'FEE']),
            implode(',', ['R - KANWIL MALANG', '45 - KC MADIUN', 'UNIT A', 'CORP001', 'PT SAMPLE', '10', '100000', '2500'])
        );

        $this->assertInstanceOf(\Illuminate\View\View::class, $response);
        $this->assertTrue((bool) ($response->getData()['portalFilterDropdowns'] ?? false));
    }

    public function test_usak_ibbiz_uker_preview_enables_portaled_filter_dropdowns(): void
    {
        $response = $this->buildIbbizPreviewResponse(
            'usak_ibbiz_uker',
            'IBBIS By Unit Kerja',
            implode(',', ['textbox23', 'textbox10', 'textbox11', 'textbox12', 'textbox7', 'textbox13', 'textbox14', 'textbox5', 'textbox4', 'REFERRAL']),
            implode(',', ['2026-06-30', '1', 'R - KANWIL MALANG', '45 - KC MADIUN', 'UNIT A', 'CORP001', 'PT SAMPLE', 'AKTIF', 'DESC', 'REF001'])
        );

        $this->assertInstanceOf(\Illuminate\View\View::class, $response);
        $this->assertTrue((bool) ($response->getData()['portalFilterDropdowns'] ?? false));
    }

    public function test_generic_preview_enables_portaled_filter_dropdowns(): void
    {
        $response = $this->buildIbbizPreviewResponse(
            'generic_import_preview_test',
            'Generic Import Preview',
            implode(',', ['POSISI', 'NAMA_KANCA', 'NOMINAL']),
            implode(',', ['2026-07-16', 'KC MADIUN', '100'])
        );

        $this->assertInstanceOf(\Illuminate\View\View::class, $response);
        $this->assertTrue((bool) ($response->getData()['portalFilterDropdowns'] ?? false));
    }

    public function test_filter_options_sorting_is_natural(): void
    {
        $controller = new ImportFileController();

        $values = ['10 - KC Malang', '2 - KC Surabaya', '1 - KC Banyuwangi', '11 - KC Ponorogo'];
        $this->invokeMethod($controller, 'sortFilterValues', [&$values]);

        $this->assertSame([
            '1 - KC Banyuwangi',
            '2 - KC Surabaya',
            '10 - KC Malang',
            '11 - KC Ponorogo',
        ], $values);
    }

    private function buildIbbizPreviewResponse(string $tableName, string $reportName, string $headerLine, string $dataLine)
    {
        Schema::create('nama_report', function ($table): void {
            $table->integer('id_report')->primary();
            $table->string('nama_report')->nullable();
            $table->string('table_name')->nullable();
        });

        Schema::create($tableName, function ($table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode')->nullable();
            $table->timestamps();
        });

        DB::table('nama_report')->insert([
            'id_report' => 91,
            'nama_report' => $reportName,
            'table_name' => $tableName,
        ]);

        $csvPath = storage_path('framework/testing/' . $tableName . '_preview.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, $headerLine . "\n" . $dataLine . "\n");
        session(['active_id_report' => 91]);

        try {
            return (new ImportFileController())->preview(\Illuminate\Http\Request::create('/import/preview/direct', 'GET', [
                'file_path' => $csvPath,
                'delimiter' => ',',
                'periode' => '2026-06-30',
            ]));
        } finally {
            @unlink($csvPath);
        }
    }

    private function invokeMethod(object $target, string $method, array $args = [])
    {
        $reflection = new \ReflectionClass($target);
        $methodRef = $reflection->getMethod($method);
        $methodRef->setAccessible(true);

        return $methodRef->invokeArgs($target, $args);
    }
}
