<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use ReflectionMethod;
use Tests\TestCase;

class ImportExcelControllerLocaleDateScopeTest extends TestCase
{
    public function test_ssa_simpanan_accepts_indonesian_textual_period_dates(): void
    {
        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return [
                    'Month_Day_Year_of_Posisi',
                    'nama_cabang',
                    'nama_uker',
                    'produk',
                    'saldo',
                ];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [
                    'nama_cabang' => ['is_textual' => true, 'max_length' => 150],
                    'nama_uker' => ['is_textual' => true, 'max_length' => 150],
                    'produk' => ['is_textual' => true, 'max_length' => 100],
                    'saldo' => ['scale' => 2],
                ];
            }
        };

        $headers = [
            'Month, Day, Year of Posisi',
            'Nama Cabang',
            'Nama Uker',
            'Produk',
            'Saldo',
        ];

        $contextMethod = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
        $contextMethod->setAccessible(true);
        $context = $contextMethod->invoke($controller, 'ssa_simpanan', $headers);

        $mapMethod = new ReflectionMethod(ImportExcelController::class, 'mapExcelRowForInsert');
        $mapMethod->setAccessible(true);
        $row = $mapMethod->invoke($controller, [
            '12 Mei 2026',
            '00045 -- KC Madiun (Konsolidasi-MB)',
            '00045 -- KC Madiun',
            'GIRO',
            '1000',
        ], $headers, $context, '2026-05-12 08:00:00');

        $this->assertIsArray($row);
        $this->assertSame('2026-05-12', $row['Month_Day_Year_of_Posisi']);
    }
}
