<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use ReflectionMethod;
use Tests\TestCase;

class ImportExcelControllerBrihcTest extends TestCase
{
    public function test_brihc_rows_use_uniqueid_brihc_and_map_source_columns(): void
    {
        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return [
                    'uniqueid_brihc',
                    'pn',
                    'nama',
                    'jabatan',
                    'created_at',
                    'updated_at',
                ];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [];
            }
        };

        $headers = ['PN', 'NAMA', 'JABATAN'];

        $contextMethod = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
        $contextMethod->setAccessible(true);
        $context = $contextMethod->invoke($controller, 'brihc', $headers);

        $this->assertSame('uniqueid_brihc', $context['unique_id_col']);
        $this->assertSame('_BRIHC', $context['suffix']);
        $this->assertSame('uuid_brihc', $context['unique_id_prefix']);

        $mapMethod = new ReflectionMethod(ImportExcelController::class, 'mapExcelRowForInsert');
        $mapMethod->setAccessible(true);
        $row = $mapMethod->invoke(
            $controller,
            ['174434', 'Dhiah Pita Sari', 'KAUNIT'],
            $headers,
            $context,
            '2026-04-26 21:00:00'
        );

        $this->assertIsArray($row);
        $this->assertArrayNotHasKey('uniqueid_namareport', $row);
        $this->assertNotEmpty($row['uniqueid_brihc']);
        $this->assertStringStartsWith('uuid_brihc_', $row['uniqueid_brihc']);
        $this->assertStringEndsWith('_BRIHC', $row['uniqueid_brihc']);
        $this->assertSame('174434', $row['pn']);
        $this->assertSame('Dhiah Pita Sari', $row['nama']);
        $this->assertSame('KAUNIT', $row['jabatan']);
    }

    public function test_brihc_pemasar_rows_use_uuid_mantri_and_map_template_columns(): void
    {
        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return [
                    'uniqueid_namareport',
                    'completename',
                    'psadesc',
                    'orgdesc',
                    'positiondesc',
                    'esgdesc',
                    'pn_mantri',
                    'bln_2026',
                    'created_at',
                    'updated_at',
                ];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [];
            }
        };

        $headers = ['COMPLETENAME', 'PSADESC', 'ORGDESC', 'POSITIONDESC', 'ESGDESC', 'PN MANTRI', 'BLN 2026'];

        $contextMethod = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
        $contextMethod->setAccessible(true);
        $context = $contextMethod->invoke($controller, 'brihc_pemasar', $headers);

        $this->assertSame('uniqueid_namareport', $context['unique_id_col']);
        $this->assertSame('', $context['suffix']);
        $this->assertSame('uuid_mantri', $context['unique_id_prefix']);

        $mapMethod = new ReflectionMethod(ImportExcelController::class, 'mapExcelRowForInsert');
        $mapMethod->setAccessible(true);
        $row = $mapMethod->invoke(
            $controller,
            ['Agus Basron', 'KC Madiun', 'BRI Unit Dolopo', 'Associate Mantri 1', 'PT', '6494 | 00164773 - Agus Basron', '5'],
            $headers,
            $context,
            '2026-06-08 09:00:00'
        );

        $this->assertIsArray($row);
        $this->assertNotEmpty($row['uniqueid_namareport']);
        $this->assertStringStartsWith('uuid_mantri_', $row['uniqueid_namareport']);
        $this->assertSame('Agus Basron', $row['completename']);
        $this->assertSame('KC Madiun', $row['psadesc']);
        $this->assertSame('BRI Unit Dolopo', $row['orgdesc']);
        $this->assertSame('Associate Mantri 1', $row['positiondesc']);
        $this->assertSame('PT', $row['esgdesc']);
        $this->assertSame('6494 | 00164773 - Agus Basron', $row['pn_mantri']);
        $this->assertSame('5', $row['bln_2026']);
    }
}
