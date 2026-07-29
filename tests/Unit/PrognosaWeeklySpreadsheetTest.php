<?php

namespace Tests\Unit;

use App\Http\Controllers\PrognosaWeeklyController;
use App\Models\User;
use App\Services\Presentation\PresentationPrognosaWeeklyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PrognosaWeeklySpreadsheetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_weekly_prognosa_reads_selected_google_sheet_with_two_row_header(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response(
                "\"DASHBOARD KERAGAAN HARIAN AREA\",\"AREA 6 MADIUN\",\"\"\n"
                . "\"Posisi Terakhir\",\"24 Juli 2026\",\"\"\n"
                . "\"Sumber\",\"\",\"\"\n"
                . "\"KETERANGAN\",\"\",\"\"\n"
                . "\"\",\"23 Juli 2026\",\"24 Juli 2026\"\n"
                . "\"1. Simpanan\",\"100\",\"110\"",
                200
            ),
        ]);
        $this->actingAs(new User(['pn' => 'test-prognosa-area', 'name' => 'Prognosa Area']));

        $view = (new PrognosaWeeklyController())->index(Request::create('/prognosa/weekly', 'GET', ['sheet' => 'area']));
        $data = $view->getData();

        $this->assertSame('area', $data['selectedSheetKey']);
        $this->assertFalse($data['isLocked']);
        $this->assertSame('Area', $data['selectedSheet']['sheet']);
        $this->assertSame('DASHBOARD KERAGAAN HARIAN AREA - AREA 6 MADIUN', $data['title']);
        $this->assertSame('24 Jul 26', $data['latestDate']);
        $this->assertSame(['Keterangan', 'Posisi'], array_column($data['headerGroups'], 'label'));
        $this->assertSame('23 Jul 26', $data['headerColumns'][1]['label']);
        $this->assertSame(['1. Simpanan', '100', '110'], $data['rows'][0]);

        $html = $view->render();
        $this->assertStringContainsString('<thead>', $html);
        $this->assertStringContainsString('data-group="indicator"', $html);
        $this->assertStringContainsString('class="prognosa-summary"', $html);
        $this->assertStringContainsString('class="prognosa-table-wrap"', $html);
        $this->assertStringContainsString('scope="colgroup"', $html);
        $this->assertStringContainsString('--prognosa-indicator-width:', $html);
        $this->assertStringContainsString('class="prognosa-table-legend"', $html);
        $this->assertStringContainsString('class="prognosa-summary__icon"', $html);
        $this->assertStringContainsString('data-group="position"', $html);
    }

    public function test_weekly_prognosa_locks_sheet_to_authenticated_branch(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response(
                "\"DASHBOARD KERAGAAN HARIAN Kanca\",\"MADIUN\",\"\"\n"
                . "\"Posisi Terakhir\",\"24 Juli 2026\",\"\"\n"
                . "\"Sumber\",\"\",\"\"\n"
                . "\"KETERANGAN\",\"\",\"\"\n"
                . "\"\",\"23 Juli 2026\",\"24 Juli 2026\"\n"
                . "\"1. Simpanan\",\"100\",\"110\"",
                200
            ),
        ]);
        $this->actingAs(new User(['pn' => 'test-prognosa', 'branch_scope' => 'madiun']));

        $view = (new PrognosaWeeklyController())->index(Request::create('/prognosa/weekly', 'GET', ['sheet' => 'area']));
        $data = $view->getData();

        $this->assertTrue($data['isLocked']);
        $this->assertSame('madiun', $data['selectedSheetKey']);
        $this->assertSame(['madiun'], array_keys($data['sheetOptions']));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sheet=Madiun'));
    }

    public function test_weekly_prognosa_uses_workbook_headers_for_dates_and_prognosa_column(): void
    {
        $path = $this->weeklyPrognosaWorkbookFixture();
        $contents = file_get_contents($path);

        try {
            Http::fake(function ($request) use ($contents) {
                return str_contains($request->url(), '/export?format=xlsx')
                    ? Http::response($contents, 200, [
                        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    : Http::response('', 503);
            });
            $this->actingAs(new User(['pn' => 'test-prognosa-workbook', 'name' => 'Prognosa Workbook']));

            $view = (new PrognosaWeeklyController())->index(
                Request::create('/prognosa/weekly', 'GET', ['sheet' => 'area'])
            );
            $data = $view->getData();

            $this->assertSame('27 Jul 26', $data['latestDate']);
            $this->assertSame(
                ['Keterangan', 'Posisi', 'Prognosa', 'Delta', 'RKA', 'RKA'],
                array_column($data['headerGroups'], 'label')
            );
            $this->assertSame('31 Jul 25', $data['headerColumns'][1]['label']);
            $this->assertSame('27 Jul 26', $data['headerColumns'][8]['label']);
            $this->assertSame('31 Jul 26', $data['headerColumns'][9]['label']);
            $this->assertSame('Δ 31 Jul 25', $data['headerColumns'][10]['label']);
            $this->assertSame('Δ 25 Jun 26', $data['headerColumns'][12]['label']);
            $this->assertSame('31 Jul 26', $data['headerColumns'][15]['label']);
            $this->assertSame('Δ 31 Jul 26', $data['headerColumns'][16]['label']);
            $this->assertSame('31 Dec 26', $data['headerColumns'][18]['label']);
            $this->assertSame('1000', $data['rows'][0][9]);

            $html = $view->render();
            $this->assertStringContainsString('data-group="prognosa"', $html);
            $this->assertStringContainsString('>Prognosa<', $html);
        } finally {
            @unlink($path);
        }
    }

    public function test_weekly_prognosa_view_keeps_responsive_sticky_table_guardrails(): void
    {
        $source = file_get_contents(resource_path('views/report/prognosa-weekly.blade.php'));

        $this->assertStringContainsString("@section('styles')", $source);
        $this->assertStringNotContainsString("@push('styles')", $source);
        $this->assertStringContainsString('.prognosa-col-indicator', $source);
        $this->assertStringNotContainsString('.prognosa-table th:first-child', $source);
        $this->assertStringContainsString('.prognosa-corner-header', $source);
        $this->assertStringContainsString('z-index: 60', $source);
        $this->assertStringContainsString('scrollSurface.scrollLeft', $source);
        $this->assertStringContainsString('window.requestAnimationFrame', $source);
        $this->assertStringContainsString('@media (max-width: 991.98px)', $source);
        $this->assertStringContainsString('@media (max-width: 767.98px)', $source);
        $this->assertStringContainsString('scrollbar-gutter: stable', $source);
        $this->assertStringContainsString('--prognosa-delta: #0f766e', $source);
        $this->assertStringContainsString('--prognosa-rka: #2f6f4e', $source);
        $this->assertStringContainsString('.prognosa-table tbody td[data-group="position"]', $source);
        $this->assertStringContainsString('data-group="{{ $cellGroup }}"', $source);
    }

    public function test_presentation_uses_latest_written_week_from_workbook_header(): void
    {
        $path = $this->presentationPrognosaFixture();

        try {
            $payload = app(PresentationPrognosaWeeklyService::class)->parseWorkbook($path);
        } finally {
            @unlink($path);
        }

        $this->assertTrue((bool) data_get($payload, 'meta.available'));
        $this->assertSame('2026-07-25', data_get($payload, 'meta.forecast_date'));
        $this->assertSame('2026-07-24', data_get($payload, 'meta.position_date'));
        $this->assertSame('W4', data_get($payload, 'meta.week_label'));
        $this->assertSame(
            14_144_099_000_000.0,
            data_get($payload, 'scopes.area6.metrics.simpanan.value')
        );
        $this->assertSame(
            4_188_553_000_000.0,
            data_get($payload, ['scopes', 'KC MADIUN', 'metrics', 'simpanan', 'value'])
        );
        $this->assertSame(
            2_034_941_000_000.0,
            data_get($payload, 'scopes.area6.metrics.sml.value')
        );
        $this->assertSame(
            741_761_000_000.0,
            data_get($payload, 'scopes.area6.metrics.npl.value')
        );
    }

    public function test_presentation_falls_back_to_last_local_workbook_when_drive_is_unavailable(): void
    {
        $path = $this->presentationPrognosaFixture();
        config(['services.presentation_prognosa.local_path' => $path]);
        Http::fake([
            'docs.google.com/*' => Http::response('', 503),
        ]);

        try {
            $payload = app(PresentationPrognosaWeeklyService::class)->payload(true);
        } finally {
            @unlink($path);
        }

        $this->assertTrue((bool) data_get($payload, 'meta.available'));
        $this->assertTrue((bool) data_get($payload, 'meta.stale'));
        $this->assertSame('local-workbook', data_get($payload, 'meta.fallback'));
        $this->assertSame('W4', data_get($payload, 'meta.week_label'));
        $this->assertSame('2026-07-25', data_get($payload, 'meta.forecast_date'));
        $this->assertSame(
            14_144_099_000_000.0,
            data_get($payload, 'scopes.area6.metrics.simpanan.value')
        );
    }

    private function presentationPrognosaFixture(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheetNames = ['area', 'madiun', 'magetan', 'ngawi', 'ponorogo'];
        $labels = [
            '1. Simpanan',
            'A. Ritel',
            'Giro',
            'Deposito',
            'Tabungan',
            'B. Mikro',
            'Giro',
            'Deposito',
            'Tabungan',
            'C. Wholesale',
            'Giro',
            'Deposito',
            'Tabungan',
            '2. OS Total',
            'Total OS Non Commercial',
            'A. Commercial',
            'B. SME',
            'Kecil',
            'Kecil Non Cashcoll',
            'Cashcoll',
            'Medium',
            'C. Konsumer',
            'Briguna',
            'KPR',
            'KKB',
            'D. Mikro',
            'Briguna Mikro',
            'Kupedes',
            'KUR Mikro',
            'KUR Kecil',
            'KUR KPP',
            '3. Total SML (%) Non Commercial',
            'Total SML (ABS) Non Commercial',
            'A. Commercial',
            'B. SME',
            'Kecil',
            'Kecil Non Cashcoll',
            'Cashcoll',
            'Medium',
            'C. Konsumer',
            'Briguna',
            'KPR',
            'KKB',
            'D. Mikro',
            'Briguna Mikro',
            'Kupedes',
            'KUR Mikro',
            'KUR Kecil',
            'KUR KPP',
            '4. Total NPL (%) Non Commercial',
            'Total NPL (ABS) Non Commercial',
            'A. Commercial',
            'B. SME',
            'Kecil',
            'Kecil Non Cashcoll',
            'Cashcoll',
            'Medium',
            'C. Konsumer',
            'Briguna',
            'KPR',
            'KKB',
            'D. Mikro',
            'Briguna Mikro',
            'Kupedes',
            'KUR Mikro',
            'KUR Kecil',
            'KUR KPP',
            '5. %CASA',
        ];
        $scopeValues = [
            'area' => [
                '1. Simpanan' => [14_162_913, 14_144_099],
                'Total SML (ABS) Non Commercial' => [2_031_018, 2_034_941],
                'Total NPL (ABS) Non Commercial' => [751_310, 741_761],
            ],
            'madiun' => [
                '1. Simpanan' => [4_188_552, 4_188_553],
            ],
        ];

        foreach ($sheetNames as $index => $sheetName) {
            $sheet = $index === 0
                ? $spreadsheet->getActiveSheet()
                : $spreadsheet->createSheet();
            $sheet->setTitle($sheetName);
            $sheet->setCellValue('A7', 'KETERANGAN');
            $sheet->setCellValue('G7', '23 July 2026');
            $sheet->setCellValue('H7', '24 July 2026');
            $sheet->setCellValue('I7', 'UPDATE POSISI 25 JULI 2026');
            $sheet->setCellValue('J7', 'PROGNOSA JULI 26');

            foreach ($labels as $offset => $label) {
                $row = 8 + $offset;
                $values = $scopeValues[$sheetName][$label] ?? [100 + $row, 110 + $row];
                $sheet->setCellValue("A{$row}", $label);
                $sheet->setCellValue("H{$row}", $values[0]);
                $sheet->setCellValue("I{$row}", $values[1]);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'presentation_prognosa_');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    private function weeklyPrognosaWorkbookFixture(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('area');
        $sheet->setCellValue('A1', 'DASHBOARD KERAGAAN HARIAN');
        $sheet->setCellValue('B2', 'AREA 6 MADIUN');
        $sheet->setCellValue('B3', '24 July 2026');
        $sheet->setCellValue('A6', 'KETERANGAN');
        $sheet->setCellValue('B6', 'POSISI');
        $sheet->setCellValue('K6', 'UPDATE POSISI');
        $sheet->setCellValue('P6', 'RKA TERPILIH');
        $sheet->setCellValue('S6', 'RKA DES TAHUN BERJALAN');

        $headers = [
            'B7' => '31 JULI 2025',
            'C7' => '31 DES 2025',
            'D7' => '31 MEI 2026',
            'E7' => '25 JUNI 2026',
            'F7' => '30 JUNI 2026',
            'G7' => '23 July 2026',
            'H7' => '24 July 2026',
            'I7' => 'UPDATE POSISI 27 JULI 2026',
            'J7' => 'PROGNOSA JULI 26',
            'K7' => 'Delta YoY',
            'L7' => 'Delta YtD',
            'M7' => 'Delta MtM',
            'N7' => 'Delta MtD',
            'O7' => 'Delta DtD',
            'P7' => 'RKA Jul 2026',
            'Q7' => 'Delta RKA',
            'R7' => 'Penc RKA (%)',
            'S7' => 'RKA Dec 2026',
            'T7' => 'Delta RKA Des',
            'U7' => 'Penc RKA Des (%)',
        ];
        foreach ($headers as $coordinate => $value) {
            $sheet->setCellValue($coordinate, $value);
        }

        $sheet->setCellValue('A8', '1. Simpanan');
        for ($column = 2; $column <= 21; $column++) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($column) . '8', (string) ($column * 100));
        }

        $path = tempnam(sys_get_temp_dir(), 'weekly_prognosa_workbook_');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}
