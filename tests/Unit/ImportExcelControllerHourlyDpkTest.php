<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use ReflectionMethod;
use Tests\TestCase;

class ImportExcelControllerHourlyDpkTest extends TestCase
{
    public function test_hourly_dpk_raw_workbook_headers_map_posisi_and_unique_id_without_timestamp_columns(): void
    {
        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return [
                    'uniqueid_namareport',
                    'posisi',
                    'mbname',
                    'brname',
                    'segmen',
                    'produk',
                    'saldo',
                ];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [
                    'mbname' => ['is_textual' => true, 'max_length' => 150],
                    'brname' => ['is_textual' => true, 'max_length' => 150],
                    'segmen' => ['is_textual' => true, 'max_length' => 50],
                    'produk' => ['is_textual' => true, 'max_length' => 100],
                    'saldo' => ['scale' => 2],
                ];
            }
        };

        $headers = [
            'Month, Day, Year of POSISI',
            'MBNAME',
            'BRNAME',
            'SEGMEN',
            'PRODUK',
            'Saldo',
        ];

        $contextMethod = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
        $contextMethod->setAccessible(true);
        $context = $contextMethod->invoke($controller, 'hourly_dpk', $headers);

        $this->assertSame('uniqueid_namareport', $context['unique_id_col']);
        $this->assertSame('', $context['suffix']);
        $this->assertStringStartsWith('uuid_hourly_dpk_', $context['unique_id_prefix']);

        $mapMethod = new ReflectionMethod(ImportExcelController::class, 'mapExcelRowForInsert');
        $mapMethod->setAccessible(true);
        $row = $mapMethod->invoke(
            $controller,
            [
                'May 10, 2026',
                '00045 -- KC Madiun(Konsolidasi-MB)',
                '00045 -- KC Madiun',
                'KORPORASI',
                'GIRO',
                '13833797168.82',
            ],
            $headers,
            $context,
            '2026-05-11 20:45:00'
        );

        $this->assertIsArray($row);
        $this->assertArrayNotHasKey('id_report', $row);
        $this->assertArrayNotHasKey('created_at', $row);
        $this->assertArrayNotHasKey('updated_at', $row);
        $this->assertStringStartsWith('uuid_hourly_dpk_', $row['uniqueid_namareport']);
        $this->assertFalse(str_ends_with($row['uniqueid_namareport'], '_DLD'));
        $this->assertSame('2026-05-10', $row['posisi']);
        $this->assertSame('00045 -- KC Madiun(Konsolidasi-MB)', $row['mbname']);
        $this->assertSame('00045 -- KC Madiun', $row['brname']);
        $this->assertSame('KORPORASI', $row['segmen']);
        $this->assertSame('GIRO', $row['produk']);
        $this->assertSame('13833797168.82', $row['saldo']);
    }
}
