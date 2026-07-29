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

    public function test_mapped_bulk_csv_is_not_reparsed_as_a_source_csv(): void
    {
        $source = file_get_contents(base_path('app/Http/Controllers/Import/ImportExcelController.php'));

        $this->assertStringContainsString(
            'Mapping Simpanan MultiPN siap. Melanjutkan langsung ke MySQL...',
            $source
        );
        $this->assertStringNotContainsString(
            'prepareSimpananMultiPnDirectLoadSource($outputCsvPath',
            $source
        );
        $this->assertStringNotContainsString('$writeBuffer[] = $outputRow;', $source);
    }

    public function test_preview_hides_internal_simpanan_placeholder_columns_without_shifting_source_indexes(): void
    {
        $controller = new class extends ImportExcelController
        {
            public function exposeStripPreviewColumns(array $headers, array $rows, array $uniqueValues): array
            {
                return $this->stripIgnoredPreviewColumns($headers, $rows, $uniqueValues, 'simpanan_multipn');
            }

            public function exposeRemapPreviewFilters(array $displayFilterMap, array $sourceIndexes): array
            {
                return $this->remapPreviewDisplayFilterMap($displayFilterMap, $sourceIndexes);
            }

            public function exposePreparePreviewDisplayPayload(array $headers, array $uniqueValues, array $rows): array
            {
                return $this->preparePreviewDisplayPayload($headers, $uniqueValues, $rows, 'simpanan_multipn');
            }

            public function exposeSanitizeCachedPreview(array $payload): array
            {
                return $this->sanitizeSimpananMultiPnPreviewPayload($payload, null, 'simpanan_multipn');
            }
        };

        $headers = ['No', 'Posisi', 'COL_2', 'Kantor Cabang', 'COL_4', 'CIFNO'];
        $rows = [[
            'No' => '1',
            'Posisi' => '19-07-2026',
            'COL_2' => null,
            'Kantor Cabang' => '00045 -- KC Madiun',
            'COL_4' => null,
            'CIFNO' => '00CIF001',
        ]];
        $uniqueValues = [
            0 => ['1'],
            1 => ['19-07-2026'],
            2 => ['(Blank)'],
            3 => ['00045 -- KC Madiun'],
            4 => ['(Blank)'],
            5 => ['00CIF001'],
        ];

        $filtered = $controller->exposeStripPreviewColumns($headers, $rows, $uniqueValues);

        $this->assertSame(['Posisi', 'Kantor Cabang', 'CIFNO'], $filtered['headers']);
        $this->assertSame([1, 3, 5], $filtered['source_indexes']);
        $this->assertSame([
            'Posisi' => '19-07-2026',
            'Kantor Cabang' => '00045 -- KC Madiun',
            'CIFNO' => '00CIF001',
        ], $filtered['rows'][0]);
        $this->assertSame([0 => 1, 1 => 3, 2 => 5], $controller->exposeRemapPreviewFilters([0 => 0, 1 => 1, 2 => 2], $filtered['source_indexes']));

        $displayPayload = $controller->exposePreparePreviewDisplayPayload($headers, $uniqueValues, $rows);
        $this->assertSame(['Posisi', 'Kantor Cabang', 'CIFNO'], $displayPayload['headers']);
        $this->assertSame($headers, $displayPayload['source_headers']);
        $this->assertSame([0 => 1, 1 => 3, 2 => 5], $displayPayload['display_filter_map']);

        $cachedPayload = $controller->exposeSanitizeCachedPreview([
            'headers' => $headers,
            'preview' => $rows,
            'formattedUniqueValues' => $uniqueValues,
            'sourceHeaders' => $headers,
            'displayFilterMap' => array_combine(array_keys($headers), array_keys($headers)),
        ]);
        $this->assertSame(['Posisi', 'Kantor Cabang', 'CIFNO'], $cachedPayload['headers']);
        $this->assertSame([0 => 1, 1 => 3, 2 => 5], $cachedPayload['displayFilterMap']);
        $this->assertSame($headers, $cachedPayload['sourceHeaders']);

        $uniqueValues[2] = ['Ada nilai'];
        $withData = $controller->exposeStripPreviewColumns($headers, $rows, $uniqueValues);
        $this->assertNotContains('COL_2', $withData['headers']);
        $this->assertSame($headers, $displayPayload['source_headers']);
    }
}
