<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use ReflectionMethod;
use Tests\TestCase;

class ImportExcelControllerSsaPinjamanTest extends TestCase
{
    public function test_ssa_pinjaman_rows_map_source_columns_that_must_be_persisted(): void
    {
        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return [
                    'id',
                    'month_day_year_of_periode',
                    'nama_cabang',
                    'nama_uker',
                    'produk',
                    'produk_dashboard',
                    'segmen',
                    'segmen_lama',
                    'segmen_2025',
                    'segmen_dashboard',
                    'kolektabilitas_one_obligor',
                    'flag_restruk',
                    'baki_debet',
                    'jumlah_debitur_aktif',
                    'jumlah_rekening_aktif',
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
                    'produk_dashboard' => ['is_textual' => true, 'max_length' => 100],
                    'segmen' => ['is_textual' => true, 'max_length' => 100],
                    'segmen_lama' => ['is_textual' => true, 'max_length' => 100],
                    'segmen_2025' => ['is_textual' => true, 'max_length' => 100],
                    'segmen_dashboard' => ['is_textual' => true, 'max_length' => 100],
                    'flag_restruk' => ['is_textual' => true, 'max_length' => 100],
                ];
            }
        };

        $headers = [
            'Month, Day, Year of Periode',
            'Nama Cabang',
            'Nama Uker',
            'Produk',
            'Produk_Dashboard',
            'Segmen',
            'Segmen Lama',
            'SEGMEN_2025',
            'Segmen_Dashboard',
            'Kolektabilitas One Obligor',
            'Flag Restruk',
            'Baki Debet',
            'Jumlah Debitur Aktif',
            'Jumlah Rekening Aktif',
        ];

        $contextMethod = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
        $contextMethod->setAccessible(true);
        $context = $contextMethod->invoke($controller, 'ssa_pinjaman', $headers);

        $mapMethod = new ReflectionMethod(ImportExcelController::class, 'mapExcelRowForInsert');
        $mapMethod->setAccessible(true);

        $row = $mapMethod->invoke($controller, [
            '12 Mei 2026',
            '00045 -- KC Madiun (Konsolidasi-MB)',
            '00045 -- KC Madiun',
            'Kecil Komersial',
            'Commercial',
            'SME',
            'Ritel',
            'Medium',
            'Small',
            '1',
            'Y',
            '30266179892.41',
            '9',
            '11',
        ], $headers, $context, '2026-04-20 14:00:00');

        $this->assertIsArray($row);
        $this->assertSame('2026-05-12', $row['month_day_year_of_periode']);
        $this->assertSame('SME', $row['segmen']);
        $this->assertSame('Ritel', $row['segmen_lama']);
        $this->assertSame('Medium', $row['segmen_2025']);
        $this->assertSame('Small', $row['segmen_dashboard']);
        $this->assertSame('Y', $row['flag_restruk']);
        $this->assertSame(9, $row['jumlah_debitur_aktif']);
        $this->assertSame(11, $row['jumlah_rekening_aktif']);
        $this->assertSame('30266179892.41', $row['baki_debet']);
    }
}
