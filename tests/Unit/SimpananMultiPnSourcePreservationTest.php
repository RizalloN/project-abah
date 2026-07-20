<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use ReflectionClass;
use Tests\TestCase;

class SimpananMultiPnSourcePreservationTest extends TestCase
{
    public function test_mapping_normalizes_only_typed_fields_and_preserves_source_text(): void
    {
        $controller = new class extends ImportExcelController
        {
            protected function schemaColumnsForBulkImport(string $tableName): array
            {
                return [
                    'uniqueid_SMPN', 'posisi', 'regional_office', 'kantor_cabang', 'unit_kerja',
                    'CIFNO', 'no_rekening', 'jenis_simpanan', 'status', 'saldo_idr', 'created_at', 'updated_at',
                ];
            }

            protected function tableColumnMetadataForBulkImport(string $tableName): array
            {
                $text = static fn (int $length = 255): array => [
                    'is_textual' => true,
                    'max_length' => $length,
                    'scale' => null,
                ];

                return [
                    'uniqueid_smpn' => $text(50),
                    'posisi' => ['is_textual' => false, 'max_length' => null, 'scale' => null],
                    'regional_office' => $text(255),
                    'kantor_cabang' => $text(255),
                    'unit_kerja' => $text(255),
                    'cifno' => $text(50),
                    'no_rekening' => $text(50),
                    'jenis_simpanan' => $text(50),
                    'status' => $text(50),
                    'saldo_idr' => ['is_textual' => false, 'max_length' => null, 'scale' => 2],
                    'created_at' => ['is_textual' => false, 'max_length' => null, 'scale' => null],
                    'updated_at' => ['is_textual' => false, 'max_length' => null, 'scale' => null],
                ];
            }
        };
        $reflection = new ReflectionClass(ImportExcelController::class);
        $headers = [
            'COL_0', 'Posisi', 'COL_2', 'Regional Office', 'Kantor Cabang', 'COL_5',
            'Unit Kerja', 'CIFNO', 'No Rekening', 'Status', 'Jenis Simpanan', 'Saldo IDR',
        ];

        $buildContext = $reflection->getMethod('buildImportContext');
        $context = $buildContext->invoke($controller, 'simpanan_multipn', $headers);

        $this->assertFalse($context['header_rules'][7]['cache_normalized_value']);
        $this->assertFalse($context['header_rules'][8]['cache_normalized_value']);
        $this->assertFalse($context['header_rules'][11]['cache_normalized_value']);
        $this->assertTrue($context['header_rules'][7]['preserve_source_text']);
        $this->assertTrue($context['header_rules'][8]['preserve_source_text']);

        $sourceRegional = " R, MALANG; \"AREA\" | SATU\tDUA\nTIGA ";
        $row = [
            '1', '30-06-2026', '', $sourceRegional, '00049 -- KC Magetan(Konsolidasi-MB)', '',
            '00049 -- KC Magetan', '00CIF001', '0004901009801538', '01', 'TABUNGAN', '1.234,50',
        ];

        $mapRow = $reflection->getMethod('mapExcelRowForInsert');
        $mapped = $mapRow->invoke($controller, $row, $headers, $context, '2026-07-18 21:00:00');

        $this->assertIsArray($mapped);
        $this->assertSame('2026-06-30', $mapped['posisi']);
        $this->assertSame('1234.50', $mapped['saldo_idr']);
        $this->assertSame($sourceRegional, $mapped['regional_office']);
        $this->assertSame('00CIF001', $mapped['CIFNO']);
        $this->assertSame('0004901009801538', $mapped['no_rekening']);
        $this->assertSame('01', $mapped['status']);
    }
}
