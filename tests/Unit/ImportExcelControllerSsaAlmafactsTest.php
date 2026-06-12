<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tests\TestCase;

class ImportExcelControllerSsaAlmafactsTest extends TestCase
{
    public function test_ssa_almafacts_fast_preview_stops_after_sample_limit(): void
    {
        $relativePath = 'excel_imports/ssa_almafacts_preview_' . uniqid() . '.csv';
        $rows = [
            "Month, Day, Year of 00. Posisi\t03. Kanca Konsolidasi\t06. Jenis Unit Kerja\t05. Kode Unit Kerja\t04. Unit Kerja\ta. Keterangan 1\tb. Keterangan 2\tNominal",
        ];
        for ($index = 1; $index <= 500; $index++) {
            $rows[] = "January 31, 2023\tKC Madiun\tBRI Unit\t3212\tUNIT DOLOPO MADIUN\t01. Pendapatan Bunga\tINTEREST INCOME\t{$index},000";
        }
        Storage::put($relativePath, implode("\n", $rows) . "\n");

        try {
            $controller = new ImportExcelController();
            $method = new ReflectionMethod(ImportExcelController::class, 'prepareSsaAlmafactsCsvPreviewFastPath');
            $method->setAccessible(true);
            $payload = $method->invoke($controller, Storage::path($relativePath));

            $this->assertCount(8, $payload['headers']);
            $this->assertCount(100, $payload['preview']);
            $this->assertNull($payload['total_rows']);
            $this->assertSame("\t", $payload['delimiter']);
            $this->assertSame('January 31, 2023', $payload['preview'][0][0]);
            $this->assertSame('100,000', $payload['preview'][99][7]);
        } finally {
            Storage::delete($relativePath);
        }
    }

    public function test_ssa_almafacts_utf16_csv_is_converted_to_utf8_without_changing_columns(): void
    {
        $relativePath = 'excel_imports/ssa_almafacts_utf16_' . uniqid() . '.csv';
        $utf8Content = "Month, Day, Year of 00. Posisi\t03. Kanca Konsolidasi\t\n"
            . "January 31, 2023\tKC Madiun\t990,745,525\n";
        $utf16Content = "\xFF\xFE" . mb_convert_encoding($utf8Content, 'UTF-16LE', 'UTF-8');
        Storage::put($relativePath, $utf16Content);

        try {
            $controller = new ImportExcelController();
            $method = new ReflectionMethod(ImportExcelController::class, 'normalizeSsaAlmafactsCsvEncoding');
            $method->setAccessible(true);
            $method->invoke($controller, 'ssa_almafacts', $relativePath);

            $converted = Storage::get($relativePath);
            $this->assertFalse(str_starts_with($converted, "\xFF\xFE"));
            $this->assertSame($utf8Content, $converted);
        } finally {
            Storage::delete($relativePath);
        }
    }

    public function test_ssa_almafacts_tab_delimited_csv_is_detected_correctly(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ssa_almafacts_');
        $this->assertNotFalse($path);

        try {
            file_put_contents(
                $path,
                "Month, Day, Year of 00. Posisi\t03. Kanca Konsolidasi\t06. Jenis Unit Kerja\t05. Kode Unit Kerja\t04. Unit Kerja\ta. Keterangan 1\tb. Keterangan 2\t\n"
                . "January 31, 2023\tKC Madiun\tBRI Unit\t3212\tUNIT DOLOPO MADIUN\t01. Pendapatan Bunga\tINTEREST INCOME\t990,745,525\n"
            );

            $controller = new ImportExcelController();
            $method = new ReflectionMethod(ImportExcelController::class, 'detectCsvDelimiter');
            $method->setAccessible(true);

            $this->assertSame("\t", $method->invoke($controller, $path));
        } finally {
            if (is_string($path) && file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function test_ssa_almafacts_maps_positional_headers_and_generates_expected_uuid(): void
    {
        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return [
                    'uniqueid_namareport',
                    'month_day_year_of_posisi',
                    'kanca_konsolidasi',
                    'jenis_unit_kerja',
                    'kode_unit_kerja',
                    'unit_kerja',
                    'keterangan_1',
                    'keterangan_2',
                    'nominal',
                    'created_at',
                    'updated_at',
                ];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [];
            }
        };

        $headers = [
            'Month, Day, Year of 00. Posisi',
            '03. Kanca Konsolidasi',
            '06. Jenis Unit Kerja',
            '05. Kode Unit Kerja',
            '04. Unit Kerja',
            'a. Keterangan 1',
            'b. Keterangan 2',
            'COL_7',
        ];

        $contextMethod = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
        $contextMethod->setAccessible(true);
        $context = $contextMethod->invoke($controller, 'ssa_almafacts', $headers);

        $this->assertSame('uniqueid_namareport', $context['unique_id_col']);
        $this->assertSame('', $context['suffix']);
        $this->assertSame('uuid_ssaalmafacts', $context['unique_id_prefix']);
        $this->assertSame(range(0, 7), $context['import_indexes']);

        $sqlMethod = new ReflectionMethod(ImportExcelController::class, 'buildDirectLoadSqlExpression');
        $sqlMethod->setAccessible(true);
        $dateSqlExpression = $sqlMethod->invoke(
            $controller,
            $context['header_rules'][0],
            '`c0`',
            'month_day_year_of_posisi',
            $context
        );
        $this->assertStringContainsString("'%M %e, %Y'", $dateSqlExpression);

        $mapMethod = new ReflectionMethod(ImportExcelController::class, 'mapExcelRowForInsert');
        $mapMethod->setAccessible(true);
        $row = $mapMethod->invoke(
            $controller,
            [
                'January 31, 2023',
                'KC Madiun',
                'BRI Unit',
                '3212',
                'UNIT DOLOPO MADIUN',
                '01. Pendapatan Bunga',
                'INTEREST INCOME',
                '990,745,525',
            ],
            $headers,
            $context,
            '2026-06-09 12:00:00'
        );

        $this->assertIsArray($row);
        $this->assertStringStartsWith('uuid_ssaalmafacts_', $row['uniqueid_namareport']);
        $this->assertSame('2023-01-31', $row['month_day_year_of_posisi']);
        $this->assertSame('KC Madiun', $row['kanca_konsolidasi']);
        $this->assertSame('BRI Unit', $row['jenis_unit_kerja']);
        $this->assertSame('3212', $row['kode_unit_kerja']);
        $this->assertSame('UNIT DOLOPO MADIUN', $row['unit_kerja']);
        $this->assertSame('01. Pendapatan Bunga', $row['keterangan_1']);
        $this->assertSame('INTEREST INCOME', $row['keterangan_2']);
        $this->assertSame('990745525.00', $row['nominal']);

        $smallNominalRow = $mapMethod->invoke(
            $controller,
            [
                'January 31, 2023',
                'KC Madiun',
                'BRI Unit',
                '3212',
                'UNIT DOLOPO MADIUN',
                '14. Pajak',
                'Pajak',
                '6,710',
            ],
            $headers,
            $context,
            '2026-06-09 12:00:00'
        );

        $this->assertSame('6710.00', $smallNominalRow['nominal']);

        $sqlExpression = $sqlMethod->invoke(
            $controller,
            $context['header_rules'][7],
            '`c7`',
            'nominal',
            $context
        );

        $this->assertStringContainsString("REPLACE(`c7`, ',', '')", $sqlExpression);
    }

    public function test_ssa_almafacts_keeps_valid_rows_with_blank_nominal(): void
    {
        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return [
                    'uniqueid_namareport',
                    'month_day_year_of_posisi',
                    'kanca_konsolidasi',
                    'jenis_unit_kerja',
                    'kode_unit_kerja',
                    'unit_kerja',
                    'keterangan_1',
                    'keterangan_2',
                    'nominal',
                ];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [];
            }
        };

        $headers = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'COL_7'];
        $contextMethod = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
        $contextMethod->setAccessible(true);
        $context = $contextMethod->invoke($controller, 'ssa_almafacts', $headers);

        $mapMethod = new ReflectionMethod(ImportExcelController::class, 'mapExcelRowForInsert');
        $mapMethod->setAccessible(true);
        $row = $mapMethod->invoke(
            $controller,
            [
                'January 31, 2023',
                'KC Madiun',
                'BRI Unit',
                '6339',
                'UNIT ALOON - ALOON MADIUN',
                '14. Pajak',
                'Pajak',
                '',
            ],
            $headers,
            $context,
            '2026-06-09 12:00:00'
        );

        $this->assertIsArray($row);
        $this->assertArrayHasKey('nominal', $row);
        $this->assertNull($row['nominal']);
    }
}
