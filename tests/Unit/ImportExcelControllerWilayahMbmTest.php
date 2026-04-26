<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use ReflectionMethod;
use Tests\TestCase;

class ImportExcelControllerWilayahMbmTest extends TestCase
{
    public function test_wilayah_mbm_skips_no_column_and_uses_uniqueid_mbm(): void
    {
        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return [
                    'uniqueid_mbm',
                    'bc',
                    'nama_uker',
                    'cabang',
                    'nama_mbm',
                    'created_at',
                    'updated_at',
                ];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [];
            }
        };

        $headers = ['NO', 'BC', 'NAMA UKER', 'CABANG', 'NAMA MBM'];

        $contextMethod = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
        $contextMethod->setAccessible(true);
        $context = $contextMethod->invoke($controller, 'wilayah_mbm', $headers);

        $this->assertSame('uniqueid_mbm', $context['unique_id_col']);
        $this->assertSame('_MBM', $context['suffix']);
        $this->assertSame('uuid_mbm', $context['unique_id_prefix']);

        $bulkColumnsMethod = new ReflectionMethod(ImportExcelController::class, 'buildBulkLoadColumns');
        $bulkColumnsMethod->setAccessible(true);
        $bulkColumns = $bulkColumnsMethod->invoke($controller, 'wilayah_mbm', $headers);

        $this->assertContains('uniqueid_mbm', $bulkColumns);
        $this->assertContains('bc', $bulkColumns);
        $this->assertContains('nama_uker', $bulkColumns);
        $this->assertContains('cabang', $bulkColumns);
        $this->assertContains('nama_mbm', $bulkColumns);
        $this->assertNotContains('no', array_map('strtolower', $bulkColumns));

        $mapMethod = new ReflectionMethod(ImportExcelController::class, 'mapExcelRowForInsert');
        $mapMethod->setAccessible(true);
        $row = $mapMethod->invoke(
            $controller,
            ['1', '6125', '06125--UNIT KETAPANG BANYUWANGI', 'BANYUWANGI', 'Dian Anis Setyorini'],
            $headers,
            $context,
            '2026-04-26 22:00:00'
        );

        $this->assertIsArray($row);
        $this->assertArrayNotHasKey('no', array_change_key_case($row, CASE_LOWER));
        $this->assertArrayNotHasKey('uniqueid_namareport', $row);
        $this->assertNotEmpty($row['uniqueid_mbm']);
        $this->assertStringStartsWith('uuid_mbm_', $row['uniqueid_mbm']);
        $this->assertStringEndsWith('_MBM', $row['uniqueid_mbm']);
        $this->assertSame('6125', $row['bc']);
        $this->assertSame('06125--UNIT KETAPANG BANYUWANGI', $row['nama_uker']);
        $this->assertSame('BANYUWANGI', $row['cabang']);
        $this->assertSame('Dian Anis Setyorini', $row['nama_mbm']);
    }
}
