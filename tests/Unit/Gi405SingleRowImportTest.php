<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use App\Services\Import\ExcelStagingService;
use App\Services\Import\Gi405SingleRowValueNormalizer;
use App\Services\Import\ImportStrategyFactory;
use App\Services\Import\Strategies\Gi405SingleRowImportStrategy;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class Gi405SingleRowImportTest extends TestCase
{
    public function test_source_values_are_validated_without_changing_their_text(): void
    {
        $normalizer = app(Gi405SingleRowValueNormalizer::class);

        $this->assertSame('-1192219013541.47', $normalizer->normalizeDecimal('-1192219013541.47'));
        $this->assertSame('2.455700983978E+10', $normalizer->normalizeDecimal('2.455700983978E+10'));
        $this->assertSame('-0', $normalizer->normalizeDecimal('-0'));
        $this->assertSame('1E+11', $normalizer->normalizeForStaging('ACCOUNT NUMBER', '1E+11'));
        $this->assertSame('31/07/2025', $normalizer->normalizeForStaging('PERIODE', '31/07/2025'));
        $this->assertSame('    ', $normalizer->normalizeForStaging('F/C', '    '));
        $this->assertNull($normalizer->normalizeForStaging('EQUIVALENTS IDR', ''));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tidak valid');
        $normalizer->normalizeDecimal('1.2x');
    }

    public function test_extra_precision_and_scientific_notation_remain_exact(): void
    {
        $normalizer = app(Gi405SingleRowValueNormalizer::class);

        $this->assertSame('-4.8099999999999996', $normalizer->normalizeDecimal('-4.8099999999999996'));
        $this->assertSame('-16203643.619999999', $normalizer->normalizeDecimal('-16203643.619999999'));
        $this->assertSame('1663203047307.1699', $normalizer->normalizeDecimal('1663203047307.1699'));

        foreach (['1.2301', '1234567890123.451'] as $value) {
            $this->assertSame($value, $normalizer->normalizeDecimal($value));
        }
    }

    public function test_literal_mysql_null_marker_cannot_silently_replace_source_text(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('penanda NULL');

        app(Gi405SingleRowValueNormalizer::class)->normalizeForStaging('DESCRIPTION', '\\N');
    }

    public function test_post_load_audit_rejects_a_changed_numeric_value(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE gi405_singlerow (uniqueid_namareport TEXT PRIMARY KEY, periode TEXT, source_periode TEXT, branch TEXT, nama_cabang TEXT, nama_uker TEXT, begining_balance TEXT)');
        $pdo->exec("INSERT INTO gi405_singlerow VALUES ('row-1', '2025-08-31', '31/08/2025', '552', 'KC Madiun', 'KCP Caruban', '1.2301')");

        $columns = ['uniqueid_namareport', 'periode', 'source_periode', 'branch', 'nama_cabang', 'nama_uker', 'begining_balance'];
        $csvPath = storage_path('framework/testing/gi405-post-load-audit.csv');
        $handle = fopen($csvPath, 'wb');
        fputcsv($handle, ['row-1', '2025-08-31', '31/08/2025', '552', 'KC Madiun', 'KCP Caruban', '1.2301']);
        fclose($handle);

        try {
            $method = new ReflectionMethod(ImportExcelController::class, 'buildGi405SingleRowAfterLoadCallback');
            $callback = $method->invoke(new ImportExcelController, ['2025-08-31' => 1], $csvPath, $columns);
            $callback($pdo, 1);

            $pdo->exec("UPDATE gi405_singlerow SET begining_balance = '1.23' WHERE uniqueid_namareport = 'row-1'");
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('begining_balance');
            $callback($pdo, 1);
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_xlsx_streaming_staging_preserves_identifiers_blanks_and_financial_values(): void
    {
        $sourcePath = storage_path('framework/testing/gi405-single-row-source.xlsx');
        $csvPath = storage_path('framework/testing/gi405-single-row-stage.csv');
        @mkdir(dirname($sourcePath), 0777, true);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(Gi405SingleRowValueNormalizer::SOURCE_HEADERS, null, 'A1');
        $sheet->fromArray([
            '31/08/2025',
            45,
            'IDR',
            '*POST',
            '100010000000',
            110,
            90001,
            '',
            'Kas Kantor',
            '-1192219013541.47',
            '',
            '',
            '24557009839.78',
            '-28288705636.11',
            '-1190098022733.48',
        ], null, 'A2');
        foreach ([
            'E2' => '100010000000',
            'J2' => '-1192219013541.47',
            'K2' => '',
            'L2' => '',
            'M2' => '24557009839.78',
            'N2' => '-28288705636.11',
            'O2' => '-1190098022733.48',
        ] as $coordinate => $value) {
            $sheet->setCellValueExplicit($coordinate, $value, DataType::TYPE_STRING);
        }
        (new Xlsx($spreadsheet))->save($sourcePath);
        $spreadsheet->disconnectWorksheets();

        try {
            $strategy = app(Gi405SingleRowImportStrategy::class);
            $headers = $strategy->transformHeaders(Gi405SingleRowValueNormalizer::SOURCE_HEADERS);
            $result = app(ExcelStagingService::class)->stageGi405SingleRowXlsxToCsv(
                static function (): void {
                },
                $sourcePath,
                0,
                $headers,
                $csvPath
            );

            $this->assertSame(1, $result['total_rows']);
            $handle = fopen($csvPath, 'rb');
            $this->assertIsResource($handle);
            $this->assertSame($headers, fgetcsv($handle));
            $row = fgetcsv($handle);
            fclose($handle);

            $this->assertSame('31/08/2025', $row[0]);
            $this->assertSame('45', $row[1]);
            $this->assertSame('100010000000', $row[4]);
            $this->assertSame('', $row[7]);
            $this->assertSame('-1192219013541.47', $row[9]);
            $this->assertSame('', $row[10]);
            $this->assertSame('24557009839.78', $row[12]);
            $this->assertSame('-28288705636.11', $row[13]);
            $this->assertSame('-1190098022733.48', $row[14]);
        } finally {
            @unlink($sourcePath);
            @unlink($csvPath);
        }
    }

    public function test_staging_rejects_a_filename_that_points_to_another_period(): void
    {
        $sourcePath = storage_path('framework/testing/gi405-30-04-2026.xlsx');
        $csvPath = storage_path('framework/testing/gi405-wrong-period.csv');
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(Gi405SingleRowValueNormalizer::SOURCE_HEADERS, null, 'A1');
        $sheet->fromArray(['31/03/2026', '45', 'IDR', '*POST', '100010000000', '110', '90001', '', 'Kas', '1.2301', '', '', '0', '0', '1.2301'], null, 'A2');
        (new Xlsx($spreadsheet))->save($sourcePath);
        $spreadsheet->disconnectWorksheets();

        try {
            $headers = app(Gi405SingleRowImportStrategy::class)->transformHeaders(Gi405SingleRowValueNormalizer::SOURCE_HEADERS);
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('berbeda dari isi Excel');
            app(ExcelStagingService::class)->stageGi405SingleRowXlsxToCsv(static function (): void {}, $sourcePath, 0, $headers, $csvPath);
        } finally {
            @unlink($sourcePath);
            @unlink($csvPath);
        }
    }

    public function test_staging_rejects_more_than_one_source_period(): void
    {
        $sourcePath = storage_path('framework/testing/gi405-mixed-periods.xlsx');
        $csvPath = storage_path('framework/testing/gi405-mixed-periods.csv');
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(Gi405SingleRowValueNormalizer::SOURCE_HEADERS, null, 'A1');
        $base = ['30/04/2026', '45', 'IDR', '*POST', '100010000000', '110', '90001', '', 'Kas', '1.2301', '', '', '0', '0', '1.2301'];
        $sheet->fromArray($base, null, 'A2');
        $base[0] = '31/03/2026';
        $sheet->fromArray($base, null, 'A3');
        (new Xlsx($spreadsheet))->save($sourcePath);
        $spreadsheet->disconnectWorksheets();

        try {
            $headers = app(Gi405SingleRowImportStrategy::class)->transformHeaders(Gi405SingleRowValueNormalizer::SOURCE_HEADERS);
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('tepat satu periode');
            app(ExcelStagingService::class)->stageGi405SingleRowXlsxToCsv(static function (): void {}, $sourcePath, 0, $headers, $csvPath);
        } finally {
            @unlink($sourcePath);
            @unlink($csvPath);
        }
    }

    public function test_strategy_is_specialized_and_requires_complete_schema(): void
    {
        $strategy = app(ImportStrategyFactory::class)->resolve(null, 'gi405_singlerow');

        $this->assertInstanceOf(Gi405SingleRowImportStrategy::class, $strategy);
        $this->assertSame('bulk_csv_staging', $strategy->importMode());
        $this->assertSame([
            'periode',
            'branch',
            'currency',
            'posting_control',
            'account_number',
            'c_c',
            'p_c',
            'f_c',
            'description',
            'begining_balance',
            'equivalents_idr',
            'equivalents_usd',
            'today_debit',
            'today_credit',
            'ending_balance',
        ], $strategy->transformHeaders(Gi405SingleRowValueNormalizer::SOURCE_HEADERS));

        $validation = $strategy->validateSchema([
            'uniqueid_namareport',
            'source_periode',
            'periode',
            'branch',
            'nama_cabang',
            'nama_uker',
            'currency',
            'posting_control',
            'account_number',
            'c_c',
            'p_c',
            'f_c',
            'description',
            'begining_balance',
            'equivalents_idr',
            'equivalents_usd',
            'today_debit',
            'today_credit',
            'ending_balance',
            'created_at',
            'updated_at',
        ]);

        $this->assertTrue($validation['ok']);
    }

    public function test_completion_message_identifies_missing_months_between_imported_periods(): void
    {
        Schema::dropIfExists('gi405_singlerow');
        Schema::create('gi405_singlerow', function (Blueprint $table): void {
            $table->date('periode');
        });
        \Illuminate\Support\Facades\DB::table('gi405_singlerow')->insert([
            ['periode' => '2025-06-30'],
            ['periode' => '2025-08-31'],
        ]);

        try {
            $method = new ReflectionMethod(ImportExcelController::class, 'gi405SingleRowCompletionMessage');
            $message = $method->invoke(new ImportExcelController, ['2025-08-31' => 1], 1);
            $this->assertStringContainsString('31/08/2025', $message);
            $this->assertStringContainsString('Periode GI405 belum tersedia: 07/2025', $message);
        } finally {
            Schema::dropIfExists('gi405_singlerow');
        }
    }

    public function test_controller_mapping_keeps_all_source_values_without_float_conversion(): void
    {
        Schema::dropIfExists('referensi_uker');
        Schema::create('referensi_uker', function (Blueprint $table): void {
            $table->string('kode_uker', 5)->primary();
            $table->string('nama_cabang', 180);
            $table->string('nama_uker', 180);
        });
        \Illuminate\Support\Facades\DB::table('referensi_uker')->insert([
            [
                'kode_uker' => '00045',
                'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)',
                'nama_uker' => '00045 -- KC Madiun',
            ],
            [
                'kode_uker' => '00552',
                'nama_cabang' => '00045 -- KC Madiun (Konsolidasi-MB)',
                'nama_uker' => '00552 -- KCP Caruban',
            ],
        ]);
        Schema::dropIfExists('gi405_singlerow');
        Schema::create('gi405_singlerow', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode');
            $table->string('source_periode', 32);
            $table->string('branch');
            $table->string('nama_cabang', 180);
            $table->string('nama_uker', 180);
            $table->string('currency');
            $table->string('posting_control');
            $table->string('account_number');
            $table->string('c_c')->nullable();
            $table->string('p_c')->nullable();
            $table->string('f_c')->nullable();
            $table->string('description');
            $table->string('begining_balance', 80);
            $table->string('equivalents_idr', 80)->nullable();
            $table->string('equivalents_usd', 80)->nullable();
            $table->string('today_debit', 80);
            $table->string('today_credit', 80);
            $table->string('ending_balance', 80);
            $table->timestamps();
        });

        try {
            $headers = app(Gi405SingleRowImportStrategy::class)
                ->transformHeaders(Gi405SingleRowValueNormalizer::SOURCE_HEADERS);
            $controller = new class extends ImportExcelController
            {
                protected function schemaColumnsForBulkImport(string $tableName): array
                {
                    return [
                        'uniqueid_namareport', 'periode', 'source_periode', 'branch', 'nama_cabang', 'nama_uker', 'currency', 'posting_control', 'account_number',
                        'c_c', 'p_c', 'f_c', 'description', 'begining_balance', 'equivalents_idr', 'equivalents_usd',
                        'today_debit', 'today_credit', 'ending_balance', 'created_at', 'updated_at',
                    ];
                }

                protected function tableColumnMetadataForBulkImport(string $tableName): array
                {
                    $metadata = [];
                    foreach (['source_periode', 'branch', 'nama_cabang', 'nama_uker', 'currency', 'posting_control', 'account_number', 'c_c', 'p_c', 'f_c', 'description'] as $column) {
                        $metadata[$column] = ['is_textual' => true, 'max_length' => 255, 'scale' => null];
                    }
                    foreach (Gi405SingleRowValueNormalizer::DECIMAL_COLUMNS as $column) {
                        $metadata[strtolower($column)] = ['is_textual' => true, 'max_length' => 80, 'scale' => null];
                    }

                    return $metadata;
                }
            };
            $buildContext = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
            $mapRow = new ReflectionMethod(ImportExcelController::class, 'mapExcelRowForInsert');
            $context = $buildContext->invoke($controller, 'gi405_singlerow', $headers);
            $bulkColumns = new ReflectionMethod(ImportExcelController::class, 'buildBulkLoadColumns');
            $this->assertContains('source_periode', $bulkColumns->invoke($controller, 'gi405_singlerow', $headers));
            $this->assertContains('nama_cabang', $bulkColumns->invoke($controller, 'gi405_singlerow', $headers));
            $this->assertContains('nama_uker', $bulkColumns->invoke($controller, 'gi405_singlerow', $headers));
            $row = $mapRow->invoke($controller, [
                '31/08/2025', '45', 'IDR', '*POST', '100010000000', '110', '90001', null,
                'Kas Kantor', '-1192219013541.47', null, null, '24557009839.78', '-28288705636.11', '-1190098022733.48',
            ], $headers, $context, '2026-09-30 00:00:00');

            $this->assertSame('100010000000', $row['account_number']);
            $this->assertSame('00045 -- KC Madiun (Konsolidasi-MB)', $row['nama_cabang']);
            $this->assertSame('00045 -- KC Madiun', $row['nama_uker']);
            $this->assertSame('31/08/2025', $row['source_periode']);
            $this->assertSame('2025-08-31', $row['periode']);
            $this->assertSame('-1192219013541.47', $row['begining_balance']);
            $this->assertSame('', $row['equivalents_idr']);
            $this->assertSame('', $row['equivalents_usd']);
            $this->assertSame('24557009839.78', $row['today_debit']);
            $this->assertSame('-28288705636.11', $row['today_credit']);
            $this->assertSame('-1190098022733.48', $row['ending_balance']);

            $kcpRow = $mapRow->invoke($controller, [
                '31/08/2025', '552', 'IDR', '*POST', '100010000001', '110', '90001', null,
                'Kas KCP', '1.2301', null, null, '0', '0', '1.2301',
            ], $headers, $context, '2026-09-30 00:00:00');
            $this->assertSame('00045 -- KC Madiun (Konsolidasi-MB)', $kcpRow['nama_cabang']);
            $this->assertSame('00552 -- KCP Caruban', $kcpRow['nama_uker']);
            $this->assertSame('552', $kcpRow['branch']);
            $this->assertSame('1.2301', $kcpRow['begining_balance']);

            try {
                $mapRow->invoke($controller, [
                    '31/08/2025', '9999', 'IDR', '*POST', '100010000002', '110', '90001', null,
                    'Kas Lain', '1', null, null, '0', '0', '1',
                ], $headers, $context, '2026-09-30 00:00:00');
                $this->fail('Kode BRANCH yang tidak ada di referensi_uker harus ditolak.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('9999', $e->getMessage());
            }
        } finally {
            Schema::dropIfExists('gi405_singlerow');
            Schema::dropIfExists('referensi_uker');
        }
    }
}
