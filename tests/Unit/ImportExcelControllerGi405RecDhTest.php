<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use ReflectionMethod;
use Tests\TestCase;

class ImportExcelControllerGi405RecDhTest extends TestCase
{
    public function test_gi405_rows_are_normalized_before_insert(): void
    {
        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return [
                    'uniqueid_namareport',
                    'kode',
                    'pendapatan_koreksi_ppap_dr_angsuran_ph',
                    'recovery_non_klaim',
                    'kc_konsol',
                    'nama_uker',
                    'segmen',
                    'tanggal',
                    'created_at',
                    'updated_at',
                ];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [];
            }
        };

        $contextMethod = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
        $contextMethod->setAccessible(true);
        $context = $contextMethod->invoke($controller, 'gi405_rec_dh', [
            'KODE',
            'Pendapatan Koreksi PPAP-dr Angsuran PH',
            'Recovery Non Klaim',
            'KC Konsol',
            'Nama Uker',
            'Segmen',
            'Tanggal',
        ]);

        $mapMethod = new ReflectionMethod(ImportExcelController::class, 'mapExcelRowForInsert');
        $mapMethod->setAccessible(true);
        $row = $mapMethod->invoke($controller, [
            45,
            '-61806903',
            '61806903',
            '00045 -- KC Madiun (Konsolidasi-MB)',
            '00045 -- KC Madiun',
            'Ritel',
            '19 Januari 2026',
        ], [
            'KODE',
            'Pendapatan Koreksi PPAP-dr Angsuran PH',
            'Recovery Non Klaim',
            'KC Konsol',
            'Nama Uker',
            'Segmen',
            'Tanggal',
        ], $context, '2026-04-17 07:00:00');

        $this->assertIsArray($row);
        $this->assertSame('00045', $row['kode']);
        $this->assertSame('2026-01-19', $row['tanggal']);
        $this->assertNotEmpty($row['uniqueid_namareport']);
        $this->assertStringStartsWith('uuid_405RDH_', $row['uniqueid_namareport']);
    }

    public function test_gi405_rows_without_business_key_are_rejected(): void
    {
        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return [
                    'uniqueid_namareport',
                    'kode',
                    'tanggal',
                    'created_at',
                    'updated_at',
                ];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [];
            }
        };

        $contextMethod = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
        $contextMethod->setAccessible(true);
        $context = $contextMethod->invoke($controller, 'gi405_rec_dh', ['KODE', 'Tanggal']);

        $mapMethod = new ReflectionMethod(ImportExcelController::class, 'mapExcelRowForInsert');
        $mapMethod->setAccessible(true);

        $missingKode = $mapMethod->invoke($controller, ['', '19 Januari 2026'], ['KODE', 'Tanggal'], $context, '2026-04-17 07:00:00');
        $missingTanggal = $mapMethod->invoke($controller, [45, ''], ['KODE', 'Tanggal'], $context, '2026-04-17 07:00:00');

        $this->assertNull($missingKode);
        $this->assertNull($missingTanggal);
    }
}
