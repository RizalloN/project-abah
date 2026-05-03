<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use ReflectionMethod;
use Tests\TestCase;

class ImportExcelControllerGi405RecDhTest extends TestCase
{
    public function test_gi405_single_row_rows_are_normalized_before_insert(): void
    {
        $controller = new class extends ImportExcelController {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return [
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
                ];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                return [];
            }
        };

        $headers = [
            'PERIODE',
            'BRANCH',
            'CURRENCY',
            'POSTING CONTROL',
            'ACCOUNT NUMBER',
            'C/C',
            'P/C',
            'F/C',
            'DESCRIPTION',
            'BEGINING BALANCE',
            'EQUIVALENTS IDR',
            'EQUIVALENTS USD',
            'TODAY DEBIT',
            'TODAY CREDIT',
            'ENDING BALANCE',
        ];

        $contextMethod = new ReflectionMethod(ImportExcelController::class, 'buildImportContext');
        $contextMethod->setAccessible(true);
        $context = $contextMethod->invoke($controller, 'gi405_singlerow', $headers);

        $mapMethod = new ReflectionMethod(ImportExcelController::class, 'mapExcelRowForInsert');
        $mapMethod->setAccessible(true);
        $row = $mapMethod->invoke($controller, [
            '01/05/2026',
            45,
            'AED',
            '*POST',
            '100010992000',
            '',
            '',
            'AED',
            'Kas - Money Changer',
            '35.00',
            '164,937.50',
            '9.52',
            '0.00',
            '0.00',
            '35.00',
        ], $headers, $context, '2026-05-01 07:00:00');

        $this->assertIsArray($row);
        $this->assertSame('2026-05-01', $row['periode']);
        $this->assertSame('45', $row['branch']);
        $this->assertSame('*POST', $row['posting_control']);
        $this->assertSame('100010992000', $row['account_number']);
        $this->assertStringStartsWith('uuid_gi405_', $row['uniqueid_namareport']);
        $this->assertSame('164937.50', $row['equivalents_idr']);
        $this->assertSame('35.00', $row['ending_balance']);
    }
}
