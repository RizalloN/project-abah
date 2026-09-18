<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use Tests\TestCase;

class ImportPreviewSourceColumnOrderTest extends TestCase
{
    public function test_preview_preserves_uploaded_column_order_when_database_order_differs(): void
    {
        $controller = new class extends ImportExcelController
        {
            public function exposeReorderPreviewPayload(
                array $headers,
                array $formattedUniqueValues,
                array $preview,
                array $dbColumns
            ): array {
                return $this->reorderPreviewPayload($headers, $formattedUniqueValues, $preview, $dbColumns);
            }
        };

        $headers = ['Nama RM', 'Baki Debet', 'PN Pengelola', 'Posisi'];
        $uniqueValues = [
            ['Ridho Ardianto'],
            ['1110000000'],
            ['00012345'],
            ['2026-09-12'],
        ];
        $preview = [[
            'Posisi' => '2026-09-12',
            'PN Pengelola' => '00012345',
            'Nama RM' => 'Ridho Ardianto',
            'Baki Debet' => '1110000000',
        ]];

        $result = $controller->exposeReorderPreviewPayload(
            $headers,
            $uniqueValues,
            $preview,
            ['posisi', 'pn_pengelola', 'baki_debet', 'nama_rm']
        );

        $this->assertSame($headers, $result['headers']);
        $this->assertSame($uniqueValues, $result['formattedUniqueValues']);
        $this->assertSame($headers, array_keys($result['preview'][0]));
        $this->assertSame([
            'Ridho Ardianto',
            '1110000000',
            '00012345',
            '2026-09-12',
        ], array_values($result['preview'][0]));
    }

    public function test_preview_keeps_filter_values_aligned_when_they_are_keyed_by_header(): void
    {
        $controller = new class extends ImportExcelController
        {
            public function exposeReorderPreviewPayload(
                array $headers,
                array $formattedUniqueValues,
                array $preview,
                array $dbColumns
            ): array {
                return $this->reorderPreviewPayload($headers, $formattedUniqueValues, $preview, $dbColumns);
            }
        };

        $result = $controller->exposeReorderPreviewPayload(
            ['Kolom B', 'Kolom A'],
            [
                'Kolom A' => ['nilai-a'],
                'Kolom B' => ['nilai-b'],
            ],
            [['Kolom A' => 'nilai-a', 'Kolom B' => 'nilai-b']],
            ['kolom_a', 'kolom_b']
        );

        $this->assertSame(['Kolom B', 'Kolom A'], $result['headers']);
        $this->assertSame([['nilai-b'], ['nilai-a']], $result['formattedUniqueValues']);
        $this->assertSame(['nilai-b', 'nilai-a'], array_values($result['preview'][0]));
    }
}
