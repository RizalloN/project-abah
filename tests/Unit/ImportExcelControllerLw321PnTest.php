<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
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
        $where = $whereMethod->invoke($controller, $context, []);

        $this->assertStringContainsString('src.`periode` IS NOT NULL', $where);
        $this->assertStringContainsString('src.`no_rekening` IS NOT NULL', $where);
        $this->assertStringContainsString('src.`balance_dalam_idr` IS NOT NULL', $where);
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

    public function test_lw321pn_rejects_a_period_owned_by_daily_loan(): void
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

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sudah ada di tabel daily_loan_dinamis');

        $method->invoke(new ImportExcelController(), ['23/08/2026']);
    }
}
