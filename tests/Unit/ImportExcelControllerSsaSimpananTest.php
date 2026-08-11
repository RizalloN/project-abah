<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use ReflectionMethod;
use Tests\TestCase;

class ImportExcelControllerSsaSimpananTest extends TestCase
{
    public function test_ssa_simpanan_rows_map_every_current_source_column(): void
    {
        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return [
                    'id',
                    'month_day_year_of_posisi',
                    'nama_cabang',
                    'nama_uker',
                    'produk',
                    'segmentasi',
                    'segmen_kategorisasi_bisnis',
                    'saldo',
                    'created_at',
                    'updated_at',
                ];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [
                    'nama_cabang' => ['is_textual' => true, 'max_length' => 150],
                    'nama_uker' => ['is_textual' => true, 'max_length' => 150],
                    'produk' => ['is_textual' => true, 'max_length' => 100],
                    'segmentasi' => ['is_textual' => true, 'max_length' => 100],
                    'segmen_kategorisasi_bisnis' => ['is_textual' => true, 'max_length' => 100],
                    'saldo' => ['scale' => 2],
                ];
            }
        };

        $headers = [
            'Month, Day, Year of Posisi',
            'Nama Cabang',
            'Nama Uker',
            'Produk',
            'Segmentasi',
            'Segmen Kategorisasi Bisnis',
            'Saldo',
        ];

        $contextMethod = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
        $contextMethod->setAccessible(true);
        $context = $contextMethod->invoke($controller, 'ssa_simpanan', $headers);

        $mapMethod = new ReflectionMethod(ImportExcelController::class, 'mapExcelRowForInsert');
        $mapMethod->setAccessible(true);
        $row = $mapMethod->invoke($controller, [
            '10 Agustus 2026',
            '00045 -- KC Madiun(Konsolidasi-MB)',
            '00045 -- KC Madiun',
            'Deposito',
            'Ritel',
            'Consumer',
            '6024347960',
        ], $headers, $context, '2026-08-10 10:00:00');

        $this->assertSame('2026-08-10', $row['month_day_year_of_posisi']);
        $this->assertSame('Consumer', $row['segmen_kategorisasi_bisnis']);
        $this->assertSame('6024347960.00', $row['saldo']);
    }
}
