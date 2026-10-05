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
    public function test_decimal_normalization_is_exact_and_never_silently_rounds(): void
    {
        $normalizer = app(Gi405SingleRowValueNormalizer::class);

        $this->assertSame('-1192219013541.47', $normalizer->normalizeDecimal('-1192219013541.47'));
        $this->assertSame('24557009839.78', $normalizer->normalizeDecimal('2.455700983978E+10'));
        $this->assertSame('0.00', $normalizer->normalizeDecimal('-0'));
        $this->assertSame('100000000000', $normalizer->normalizeForStaging('ACCOUNT NUMBER', '1E+11'));
        $this->assertNull($normalizer->normalizeForStaging('EQUIVALENTS IDR', ''));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tidak dibulatkan');
        $normalizer->normalizeDecimal('1.234', 2);
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

            $this->assertSame('2025-08-31', $row[0]);
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
            'created_at',
            'updated_at',
        ]);

        $this->assertTrue($validation['ok']);
    }

    public function test_controller_mapping_keeps_all_source_values_without_float_conversion(): void
    {
        Schema::dropIfExists('gi405_singlerow');
        Schema::create('gi405_singlerow', function (Blueprint $table): void {
            $table->string('uniqueid_namareport')->primary();
            $table->date('periode');
            $table->string('branch');
            $table->string('currency');
            $table->string('posting_control');
            $table->string('account_number');
            $table->string('c_c')->nullable();
            $table->string('p_c')->nullable();
            $table->string('f_c')->nullable();
            $table->string('description');
            $table->decimal('begining_balance', 24, 2);
            $table->decimal('equivalents_idr', 24, 2)->nullable();
            $table->decimal('equivalents_usd', 24, 2)->nullable();
            $table->decimal('today_debit', 24, 2);
            $table->decimal('today_credit', 24, 2);
            $table->decimal('ending_balance', 24, 2);
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
                        'uniqueid_namareport', 'periode', 'branch', 'currency', 'posting_control', 'account_number',
                        'c_c', 'p_c', 'f_c', 'description', 'begining_balance', 'equivalents_idr', 'equivalents_usd',
                        'today_debit', 'today_credit', 'ending_balance', 'created_at', 'updated_at',
                    ];
                }

                protected function tableColumnMetadataForBulkImport(string $tableName): array
                {
                    $metadata = [];
                    foreach (['branch', 'currency', 'posting_control', 'account_number', 'c_c', 'p_c', 'f_c', 'description'] as $column) {
                        $metadata[$column] = ['is_textual' => true, 'max_length' => 255, 'scale' => null];
                    }
                    foreach (Gi405SingleRowValueNormalizer::DECIMAL_COLUMNS as $column) {
                        $metadata[strtolower($column)] = ['is_textual' => false, 'max_length' => null, 'scale' => 2];
                    }

                    return $metadata;
                }
            };
            $buildContext = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
            $mapRow = new ReflectionMethod(ImportExcelController::class, 'mapExcelRowForInsert');
            $context = $buildContext->invoke($controller, 'gi405_singlerow', $headers);
            $row = $mapRow->invoke($controller, [
                '2025-08-31', '45', 'IDR', '*POST', '100010000000', '110', '90001', null,
                'Kas Kantor', '-1192219013541.47', null, null, '24557009839.78', '-28288705636.11', '-1190098022733.48',
            ], $headers, $context, '2026-09-30 00:00:00');

            $this->assertSame('100010000000', $row['account_number']);
            $this->assertSame('-1192219013541.47', $row['begining_balance']);
            $this->assertNull($row['equivalents_idr']);
            $this->assertNull($row['equivalents_usd']);
            $this->assertSame('24557009839.78', $row['today_debit']);
            $this->assertSame('-28288705636.11', $row['today_credit']);
            $this->assertSame('-1190098022733.48', $row['ending_balance']);
        } finally {
            Schema::dropIfExists('gi405_singlerow');
        }
    }
}
