<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use App\Services\Import\Strategies\Lw321PnImportStrategy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class ImportExcelControllerLw321PnTest extends TestCase
{
    protected function tearDown(): void
    {
        Schema::dropIfExists('tmp_bulk_csv_stage_1_footer');
        Schema::dropIfExists('lw321pn');
        Schema::dropIfExists('daily_loan_dinamis');

        parent::tearDown();
    }

    public function test_lw321pn_mapping_preserves_source_text_and_normalizes_only_typed_values(): void
    {
        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return [
                    'uniqueid_namareport',
                    'periode',
                    'kanwil',
                    'no_rekening',
                    'balance_dalam_idr',
                    'created_at',
                    'updated_at',
                ];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [
                    'periode' => ['is_textual' => false, 'max_length' => null, 'scale' => null],
                    'kanwil' => ['is_textual' => true, 'max_length' => 150, 'scale' => null],
                    'no_rekening' => ['is_textual' => true, 'max_length' => 50, 'scale' => null],
                    'balance_dalam_idr' => ['is_textual' => false, 'max_length' => null, 'scale' => 2],
                ];
            }
        };

        $headers = ['PERIODE', 'KANWIL', 'NOMOR_REKENING', 'BALANCE DALAM IDR'];
        $sourceRow = ['23/08/2026', 'KANWIL MALANG   ', '000501061071105', '48824693'];

        $contextMethod = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
        $contextMethod->setAccessible(true);
        $context = $contextMethod->invoke($controller, 'lw321pn', $headers);

        $mapMethod = new ReflectionMethod(ImportExcelController::class, 'mapExcelRowForInsert');
        $mapMethod->setAccessible(true);
        $mapped = $mapMethod->invoke(
            $controller,
            $sourceRow,
            $headers,
            $context,
            '2026-08-23 10:00:00'
        );

        $this->assertSame('2026-08-23', $mapped['periode']);
        $this->assertSame('KANWIL MALANG   ', $mapped['kanwil']);
        $this->assertSame('000501061071105', $mapped['no_rekening']);
        $this->assertSame('48824693.00', $mapped['balance_dalam_idr']);
        $this->assertSame(['23/08/2026', 'KANWIL MALANG   ', '000501061071105', '48824693'], $sourceRow);

        $kanwilRule = $context['header_rules'][1];
        $this->assertTrue($kanwilRule['preserve_source_text_exact']);

        $sqlMethod = new ReflectionMethod(ImportExcelController::class, 'buildDirectLoadSqlExpression');
        $sqlMethod->setAccessible(true);
        $sql = $sqlMethod->invoke($controller, $kanwilRule, '`c1`', 'kanwil', $context);

        $this->assertStringContainsString('ELSE `c1` END', $sql);
        $this->assertStringNotContainsString("NULLIF(`c1`, '')", $sql);
        $this->assertStringNotContainsString('TRIM(', $sql);
        $this->assertStringNotContainsString('REPLACE(', $sql);

        $whereMethod = new ReflectionMethod(ImportExcelController::class, 'buildFastPathBulkWhereClauses');
        $whereMethod->setAccessible(true);
        $mappedColumns = array_fill_keys(Lw321PnImportStrategy::requiredColumns(), true);
        unset($mappedColumns['uniqueid_namareport']);
        $where = $whereMethod->invoke($controller, $context, $mappedColumns);

        $this->assertStringContainsString('src.`periode` IS NOT NULL', $where);
        $this->assertStringContainsString('src.`no_rekening` IS NOT NULL', $where);
        $this->assertStringContainsString('src.`balance_dalam_idr` IS NOT NULL', $where);
    }

    public function test_lw321pn_fast_path_fails_closed_when_a_required_header_is_not_mapped(): void
    {
        $mappedColumns = array_fill_keys(Lw321PnImportStrategy::requiredColumns(), true);
        unset($mappedColumns['uniqueid_namareport'], $mappedColumns['balance_dalam_idr']);

        $method = new ReflectionMethod(ImportExcelController::class, 'buildFastPathBulkWhereClauses');
        $method->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('balance_dalam_idr');

        $method->invoke(new ImportExcelController(), ['table_name' => 'lw321pn'], $mappedColumns);
    }

    public function test_lw321pn_rejects_an_existing_period_before_import(): void
    {
        Schema::create('lw321pn', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode')->nullable();
        });

        DB::table('lw321pn')->insert([
            'uniqueid_namareport' => 'existing-row',
            'periode' => '2026-08-23',
        ]);

        $method = new ReflectionMethod(ImportExcelController::class, 'assertLw321PnImportPeriodsEmptyOrFail');
        $method->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sudah ada di tabel lw321pn');

        $method->invoke(new ImportExcelController(), ['23/08/2026']);
    }

    public function test_lw321pn_allows_a_period_owned_by_daily_loan(): void
    {
        Schema::create('lw321pn', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode')->nullable();
        });
        Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode')->nullable();
        });

        DB::table('daily_loan_dinamis')->insert([
            'uniqueid_namareport' => 'daily-existing-row',
            'periode' => '2026-08-23',
        ]);

        $method = new ReflectionMethod(ImportExcelController::class, 'assertLw321PnImportPeriodsEmptyOrFail');
        $method->setAccessible(true);

        $method->invoke(new ImportExcelController(), ['23/08/2026']);

        $this->assertSame(1, DB::table('daily_loan_dinamis')->where('periode', '2026-08-23')->count());
    }

    public function test_lw321pn_maps_cbal_and_orgamt_base_and_kolek_aliases(): void
    {
        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return [
                    'uniqueid_namareport',
                    'periode',
                    'no_rekening',
                    'plafon_dalam_idr',
                    'balance_dalam_idr',
                    'kolektibilitas_lancar',
                    'pn_referral',
                ];
            }
        };

        $headers = [
            'PERIODE',
            'NO_REKENING',
            'ORGAMT_Base',
            'CBAL_Base',
            'KOLEK_LANCAR',
            'PN_REFERAL',
        ];

        $contextMethod = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
        $contextMethod->setAccessible(true);
        $context = $contextMethod->invoke($controller, 'lw321pn', $headers);

        $rulesByHeader = [];
        foreach ($context['header_rules'] as $rule) {
            $rulesByHeader[$rule['header_name']] = $rule['db_candidates'] ?? [];
        }

        $this->assertContains('balance_dalam_idr', $rulesByHeader['balance_dalam_idr'] ?? $rulesByHeader['CBAL_Base'] ?? []);
        $this->assertContains('plafon_dalam_idr', $rulesByHeader['plafon_dalam_idr'] ?? $rulesByHeader['ORGAMT_Base'] ?? []);
        $this->assertContains('kolektibilitas_lancar', $rulesByHeader['kolektibilitas_lancar'] ?? $rulesByHeader['KOLEK_LANCAR'] ?? []);
        $this->assertContains('pn_referral', $rulesByHeader['pn_referral'] ?? $rulesByHeader['PN_REFERAL'] ?? []);
    }

    public function test_lw321pn_ignores_only_empty_or_separator_only_staging_rows(): void
    {
        Schema::create('tmp_bulk_csv_stage_1_footer', function (Blueprint $table): void {
            $table->id();
            $table->text('c0')->nullable();
            $table->text('c1')->nullable();
            $table->text('c2')->nullable();
        });

        DB::table('tmp_bulk_csv_stage_1_footer')->insert([
            ['c0' => '---', 'c1' => '---', 'c2' => '---'],
            ['c0' => '', 'c1' => ' ', 'c2' => null],
            ['c0' => "\r", 'c1' => null, 'c2' => null],
            ['c0' => '25/09/2026', 'c1' => '055201007777102', 'c2' => '4602164.00'],
            ['c0' => '25/09/2026', 'c1' => '', 'c2' => '4602164.00'],
        ]);

        $method = new ReflectionMethod(ImportExcelController::class, 'deleteIgnorableLw321PnStagingRows');
        $method->setAccessible(true);
        $deleted = $method->invoke(new ImportExcelController(), 'tmp_bulk_csv_stage_1_footer', 3);

        $this->assertSame(3, $deleted);
        $this->assertSame(2, DB::table('tmp_bulk_csv_stage_1_footer')->count());
        $this->assertTrue(DB::table('tmp_bulk_csv_stage_1_footer')->where('c1', '055201007777102')->exists());
        $this->assertTrue(DB::table('tmp_bulk_csv_stage_1_footer')->where('c1', '')->exists());
    }

    public function test_lw321pn_actual_source_headers_map_exactly_one_to_one(): void
    {
        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return array_merge(
                    Lw321PnImportStrategy::requiredColumns(),
                    ['created_at', 'updated_at']
                );
            }
        };
        $contextMethod = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
        $contextMethod->setAccessible(true);
        $context = $contextMethod->invoke($controller, 'lw321pn', $this->actualSourceHeaders());

        $guard = new ReflectionMethod(ImportExcelController::class, 'assertLw321PnFastPathMappingIsOneToOne');
        $guard->setAccessible(true);
        $guard->invoke($controller, $context);

        $this->addToAssertionCount(1);
    }

    public function test_lw321pn_rejects_duplicate_source_mapping_for_the_same_target_column(): void
    {
        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return array_merge(
                    Lw321PnImportStrategy::requiredColumns(),
                    ['created_at', 'updated_at']
                );
            }
        };
        $headers = [...$this->actualSourceHeaders(), 'CBAL_Base'];
        $contextMethod = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
        $contextMethod->setAccessible(true);
        $context = $contextMethod->invoke($controller, 'lw321pn', $headers);

        $guard = new ReflectionMethod(ImportExcelController::class, 'assertLw321PnFastPathMappingIsOneToOne');
        $guard->setAccessible(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Kolom target ganda');

        $guard->invoke($controller, $context);
    }

    /**
     * @return array<int, string>
     */
    private function actualSourceHeaders(): array
    {
        return [
            'PERIODE', 'KODE_KANWIL', 'KANWIL', 'KODE_KANCA', 'KANCA', 'KODE_UKER', 'UKER',
            'CURRENCY', 'LN_TYPE', 'NO_REKENING', 'NAMA_DEBITUR', 'PLAFON', 'NEXT_PMT_DATE',
            'NEXT_INT_PMT_DATE', 'RATE', 'TGL_MENUNGGAK', 'TGL_REALISASI', 'TGL_JATUH_TEMPO',
            'JANGKA_WAKTU', 'FLAG_RESTRUK', 'CIFNO', 'KOLEK_LANCAR', 'KOLEK_DPK',
            'KOLEK_KURANG_LANCAR', 'KOLEK_DIRAGUKAN', 'KOLEK_MACET', 'TUNGGAKAN_POKOK',
            'TUNGGAKAN_BUNGA', 'TUNGGAKAN_PINALTI', 'FREQ_PAYMENT', 'FREQ_INT_PAYMENT', 'CODE',
            'DESCRIPTION', 'SEGMEN_LV1', 'DESC_SEGMEN_LV1', 'KOL_ADK', 'PN_PENGELOLA_SINGLEPN',
            'PN_PENGELOLA_1', 'PN_PEMRAKARSA', 'PN_REFERAL', 'PN_RESTRUK', 'PN_PENGELOLA_2',
            'PN_Pemutus', 'PN_CRM', 'PN_RM_Referral_Naik_Segmentasi', 'PN_RM_CRR', 'ORGAMT_Base',
            'CBAL_Base',
        ];
    }
}
