<?php

namespace Tests\Unit;

use App\Services\Presentation\PresentationPrognosaWeeklyService;
use Carbon\Carbon;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PresentationPrognosaWeeklyCsvTest extends TestCase
{
    public function test_presentation_uses_current_august_week_four_for_all_scopes(): void
    {
        Carbon::setTestNow('2026-08-27 12:00:00');
        Cache::flush();
        $fallbackPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'presentation-prognosa-'.uniqid('', true).'.xlsx';
        config(['services.presentation_prognosa.local_path' => $fallbackPath]);

        $fixtures = [
            'Area 6' => $this->csvFixture(1_000),
            'KC Madiun' => $this->csvFixture(2_000, true),
            'KC Magetan' => $this->csvFixture(3_000),
            'KC Ngawi' => $this->csvFixture(4_000),
            'KC Ponorogo' => $this->csvFixture(5_000),
        ];

        Http::fake(function (Request $request) use ($fixtures) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $sheet = (string) ($query['sheet'] ?? '');

            return isset($fixtures[$sheet])
                ? Http::response($fixtures[$sheet], 200, ['Content-Type' => 'text/csv'])
                : Http::response('', 503);
        });

        try {
            $payload = app(PresentationPrognosaWeeklyService::class)->payload(true);
        } finally {
            Carbon::setTestNow();
            @unlink($fallbackPath);
            @unlink(substr($fallbackPath, 0, -5).'.json');
        }

        $this->assertTrue((bool) data_get($payload, 'meta.available'));
        $this->assertFalse((bool) data_get($payload, 'meta.stale'));
        $this->assertSame('2026-08-27', data_get($payload, 'meta.forecast_date'));
        $this->assertSame('2026-08-19', data_get($payload, 'meta.position_date'));
        $this->assertSame(4, data_get($payload, 'meta.week_number'));
        $this->assertSame('W4', data_get($payload, 'meta.week_label'));
        $this->assertSame([1, 2, 3, 4], data_get($payload, 'meta.available_weeks'));
        $this->assertSame('Area 6', data_get($payload, 'meta.source_sheet'));

        $this->assertSame(1_040_000_000.0, data_get($payload, 'scopes.area6.metrics.os.value'));
        $this->assertSame(1_010_000_000.0, data_get($payload, 'scopes.area6.weeks.W1.metrics.os.value'));
        $this->assertSame(1_020_000_000.0, data_get($payload, 'scopes.area6.weeks.W2.metrics.os.value'));
        $this->assertSame(1_030_000_000.0, data_get($payload, 'scopes.area6.weeks.W3.metrics.os.value'));
        $this->assertSame(1_040_000_000.0, data_get($payload, 'scopes.area6.weeks.W4.metrics.os.value'));
        $this->assertSame(1_340_000_000.0, data_get($payload, 'scopes.area6.metrics.sme_os.value'));
        $this->assertSame(1_640_000_000.0, data_get($payload, 'scopes.area6.metrics.simpanan.value'));
        $this->assertSame(
            2_040_000_000.0,
            data_get($payload, ['scopes', 'KC MADIUN', 'metrics', 'os', 'value'])
        );
        $this->assertSame(
            5_040_000_000.0,
            data_get($payload, ['scopes', 'KC PONOROGO', 'metrics', 'os', 'value'])
        );

        Http::assertSentCount(5);
        Http::assertNotSent(
            static fn (Request $request): bool => str_contains($request->url(), 'export?format=xlsx')
        );
    }

    public function test_it_falls_back_to_the_latest_populated_week(): void
    {
        Carbon::setTestNow('2026-08-30 12:00:00');
        Cache::flush();
        $fallbackPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'presentation-prognosa-'.uniqid('', true).'.xlsx';
        config(['services.presentation_prognosa.local_path' => $fallbackPath]);

        $fixtures = [
            'Area 6' => $this->csvFixture(1_000, false, '30 AGUSTUS 2026', true),
            'KC Madiun' => $this->csvFixture(2_000, false, '30 AGUSTUS 2026', true),
            'KC Magetan' => $this->csvFixture(3_000, false, '30 AGUSTUS 2026', true),
            'KC Ngawi' => $this->csvFixture(4_000, false, '30 AGUSTUS 2026', true),
            'KC Ponorogo' => $this->csvFixture(5_000, false, '30 AGUSTUS 2026', true),
        ];

        Http::fake(function (Request $request) use ($fixtures) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $sheet = (string) ($query['sheet'] ?? '');

            return isset($fixtures[$sheet])
                ? Http::response($fixtures[$sheet], 200, ['Content-Type' => 'text/csv'])
                : Http::response('', 503);
        });

        try {
            $payload = app(PresentationPrognosaWeeklyService::class)->payload(true);
        } finally {
            Carbon::setTestNow();
            @unlink($fallbackPath);
            @unlink(substr($fallbackPath, 0, -5).'.json');
        }

        $this->assertSame(3, data_get($payload, 'meta.week_number'));
        $this->assertSame('W3', data_get($payload, 'meta.week_label'));
        $this->assertSame([1, 2, 3], data_get($payload, 'meta.available_weeks'));
        $this->assertSame('2026-08-30', data_get($payload, 'meta.forecast_date'));
        $this->assertSame('2026-08-30', data_get($payload, 'meta.position_date'));
        $this->assertSame(1_030_000_000.0, data_get($payload, 'scopes.area6.metrics.os.value'));
        $this->assertSame(1_730_000_000.0, data_get($payload, 'scopes.area6.metrics.recovery.value'));
    }

    public function test_new_september_source_exposes_and_selects_week_five_by_its_target_date(): void
    {
        Carbon::setTestNow('2026-09-27 12:00:00');
        Cache::flush();
        $fallbackPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'presentation-prognosa-'.uniqid('', true).'.xlsx';
        config(['services.presentation_prognosa.local_path' => $fallbackPath]);

        $fixtures = [
            'Area 6' => $this->csvFixture(1_000, false, '31 AGUSTUS 2026', false, true),
            'KC Madiun' => $this->csvFixture(2_000, false, '31 AGUSTUS 2026', false, true),
            'KC Magetan' => $this->csvFixture(3_000, false, '31 AGUSTUS 2026', false, true),
            'KC Ngawi' => $this->csvFixture(4_000, false, '31 AGUSTUS 2026', false, true),
            'KC Ponorogo' => $this->csvFixture(5_000, false, '31 AGUSTUS 2026', false, true),
        ];

        Http::fake(function (Request $request) use ($fixtures) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $sheet = (string) ($query['sheet'] ?? '');

            return isset($fixtures[$sheet])
                ? Http::response($fixtures[$sheet], 200, ['Content-Type' => 'text/csv'])
                : Http::response('', 503);
        });

        try {
            $payload = app(PresentationPrognosaWeeklyService::class)->payload(true);
        } finally {
            Carbon::setTestNow();
            @unlink($fallbackPath);
            @unlink(substr($fallbackPath, 0, -5).'.json');
        }

        $this->assertSame([1, 2, 3, 4, 5], data_get($payload, 'meta.available_weeks'));
        $this->assertSame(5, data_get($payload, 'meta.week_number'));
        $this->assertSame('W5', data_get($payload, 'meta.week_label'));
        $this->assertSame(1_050_000_000.0, data_get($payload, 'scopes.area6.metrics.os.value'));
        $this->assertSame(1_050_000_000.0, data_get($payload, 'scopes.area6.weeks.W5.metrics.os.value'));
        $this->assertSame(
            '1qta-IbVG5edMAy36Ku-GCvs_sesfmmXt',
            data_get($payload, 'meta.spreadsheet_id')
        );
        Http::assertSent(static fn (Request $request): bool => str_contains(
            $request->url(),
            '/d/1qta-IbVG5edMAy36Ku-GCvs_sesfmmXt/'
        ));
    }

    public function test_workbook_fallback_parses_the_new_five_week_layout(): void
    {
        Carbon::setTestNow('2026-09-27 12:00:00');
        $path = $this->structuredWorkbookFixture();

        try {
            $payload = app(PresentationPrognosaWeeklyService::class)->parseWorkbook($path);
        } finally {
            Carbon::setTestNow();
            @unlink($path);
        }

        $this->assertSame([1, 2, 3, 4, 5], data_get($payload, 'meta.available_weeks'));
        $this->assertSame('W5', data_get($payload, 'meta.week_label'));
        $this->assertSame(1_050_000_000.0, data_get($payload, 'scopes.area6.metrics.os.value'));
        $this->assertSame(2_050_000_000.0, data_get($payload, ['scopes', 'KC MADIUN', 'metrics', 'os', 'value']));
    }

    public function test_dashboard_harian_source_layout_maps_september_week_two_for_every_scope(): void
    {
        Carbon::setTestNow('2026-09-06 12:00:00');
        Cache::flush();
        $fallbackPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'presentation-prognosa-'.uniqid('', true).'.xlsx';
        config(['services.presentation_prognosa.local_path' => $fallbackPath]);

        $fixtures = [
            'Area 6' => $this->dashboardCsvFixture(1_000),
            'KC Madiun' => $this->dashboardCsvFixture(2_000),
            'KC Magetan' => $this->dashboardCsvFixture(3_000),
            'KC Ngawi' => $this->dashboardCsvFixture(4_000),
            'KC Ponorogo' => $this->dashboardCsvFixture(5_000),
        ];

        Http::fake(function (Request $request) use ($fixtures) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $sheet = (string) ($query['sheet'] ?? '');

            return isset($fixtures[$sheet])
                ? Http::response($fixtures[$sheet], 200, ['Content-Type' => 'text/csv'])
                : Http::response('', 503);
        });

        try {
            $payload = app(PresentationPrognosaWeeklyService::class)->payload(true);
        } finally {
            Carbon::setTestNow();
            @unlink($fallbackPath);
            @unlink(substr($fallbackPath, 0, -5).'.json');
        }

        $this->assertSame('W2', data_get($payload, 'meta.week_label'));
        $this->assertSame([1, 2, 3, 4, 5], data_get($payload, 'meta.available_weeks'));
        $this->assertSame('2026-08-31', data_get($payload, 'meta.position_date'));
        $this->assertSame('Area 6', data_get($payload, 'meta.source_sheet'));
        $this->assertSame(1_020_000_000.0, data_get($payload, 'scopes.area6.metrics.os.value'));
        $this->assertSame(2_020_000_000.0, data_get($payload, 'scopes.area6.metrics.sml.value'));
        $this->assertSame(3_020_000_000.0, data_get($payload, 'scopes.area6.metrics.npl.value'));
        $this->assertSame(1_720_000_000.0, data_get($payload, 'scopes.area6.metrics.recovery.value'));
        $this->assertSame(
            2_020_000_000.0,
            data_get($payload, ['scopes', 'KC MADIUN', 'metrics', 'os', 'value'])
        );
    }

    private function dashboardCsvFixture(int $base): string
    {
        $rows = [
            ['DASHBOARD KERAGAAN HARIAN'],
            ['Kanca', 'Area 6', '', 'Unit Kerja', 'Semua Unit Kerja'],
            ['KETERANGAN', 'POSISI', '', '', '', '', '', '', 'PROGNOSA'],
            ['', '31 Aug 25 (YoY)', '31 Dec 25 (YtD)', '30 Jun 26 (M-2)', '31 Jul 26 (MtM)', '31 Jul 26 (MtD)', '30 Aug 26 (DtD)', '31 Aug 26 (Posisi)', 'WEEK 1 (5 Sept 26)', 'WEEK 2 (12 Sep 26)', 'WEEK 3 (19 Sep 26)', 'WEEK 4 (26 Sept 26)', 'WEEK 5 (30 Sep 26)'],
        ];

        foreach ($this->dashboardReportRows($base) as [$label, $value]) {
            $row = array_fill(0, 13, '');
            $row[0] = $label;
            $row[7] = (string) $value;
            for ($week = 1; $week <= 5; $week++) {
                $row[7 + $week] = (string) ($value + ($week * 10));
            }
            $rows[] = $row;
        }

        $stream = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($stream, $row, ',', '"', '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return (string) $csv;
    }

    /** @return array<int, array{0: string, 1: int}> */
    private function dashboardReportRows(int $base): array
    {
        return [
            ['1. Simpanan', $base + 600],
            ['A. Ritel', $base + 500],
            ['Giro', $base + 101],
            ['Tabungan', $base + 102],
            ['Deposito', $base + 103],
            ['B. Mikro', $base + 510],
            ['Giro', $base + 111],
            ['Tabungan', $base + 112],
            ['Deposito', $base + 113],
            ['C. Wholesale', $base + 520],
            ['Giro', $base + 121],
            ['Tabungan', $base + 122],
            ['Deposito', $base + 123],
            ['2. OS Total', $base],
            ['A. SME', $base + 300],
            ['Kecil', $base + 300],
            ['B. Konsumer', $base + 400],
            ['Briguna', $base + 410],
            ['KPR', $base + 420],
            ['D. Mikro', $base + 100],
            ['Briguna Mikro', $base + 120],
            ['Kupedes', $base + 110],
            ['KUR Mikro', $base + 130],
            ['KUR Kecil', $base + 150],
            ['KUR KPP', $base + 140],
            ['3. Total SML (%) Non Commercial', 5],
            ['Total SML (ABS) Non Commercial', $base + 1_000],
            ['A. SME', $base + 1_300],
            ['Kecil', $base + 1_300],
            ['B. Konsumer', $base + 1_400],
            ['Briguna', $base + 1_410],
            ['KPR', $base + 1_420],
            ['D. Mikro', $base + 1_100],
            ['Briguna Mikro', $base + 1_120],
            ['Kupedes', $base + 1_110],
            ['KUR Mikro', $base + 1_130],
            ['KUR Kecil', $base + 1_150],
            ['KUR KPP', $base + 1_140],
            ['4. Total NPL (%) Non Commercial', 4],
            ['Total NPL (ABS) Non Commercial', $base + 2_000],
            ['A. SME', $base + 2_300],
            ['Kecil', $base + 2_300],
            ['B. Konsumer', $base + 2_400],
            ['Briguna', $base + 2_410],
            ['KPR', $base + 2_420],
            ['D. Mikro', $base + 2_100],
            ['Briguna Mikro', $base + 2_120],
            ['Kupedes', $base + 2_110],
            ['KUR Mikro', $base + 2_130],
            ['KUR Kecil', $base + 2_150],
            ['KUR KPP', $base + 2_140],
            ['5. %CASA', 80],
            ['7. Rec DH per Segmen', $base + 700],
        ];
    }

    private function csvFixture(
        int $base,
        bool $legacyHeader = false,
        string $positionDate = '19 AGUSTUS 2026',
        bool $blankWeekFour = false,
        bool $includeWeekFive = false
    ): string {
        $rows = [];
        $header = array_fill(0, $includeWeekFive ? 14 : 13, '');
        $header[2] = 'KETERANGAN';
        if ($legacyHeader) {
            $header[8] = 'UPDATE POSISI';
            $header[9] = 'PROGNOSA';
            $rows[] = $header;
            $dateRow = array_fill(0, 13, '');
            $dateRow[5] = '21 Juli 2026';
            $rows[] = $dateRow;
        } else {
            $header[3] = 'POSISI 31 AGUSTUS 2025';
            $header[4] = '31 DESEMBER 2025';
            $header[5] = '19 JULI 2026';
            $header[6] = '31 JULI 2026';
            $header[7] = $positionDate;
            $header[8] = 'UPDATE POSISI JAM 12.00 WIB';
            $header[9] = $includeWeekFive ? 'PROGNOSA WEEK 1 (5 September 26)' : 'PROGNOSA WEEK 1';
            $header[10] = $includeWeekFive ? 'WEEK 2 (12 September 26)' : 'WEEK 2';
            $header[11] = $includeWeekFive ? 'WEEK 3 (19 September 26)' : 'WEEK 3';
            $header[12] = $includeWeekFive ? 'WEEK 4 (26 September 26)' : 'WEEK 4';
            if ($includeWeekFive) {
                $header[13] = 'WEEK 5 (30 September 26)';
            }
            $rows[] = $header;
        }

        foreach ($this->reportRows($base) as [$label, $value]) {
            $row = array_fill(0, $includeWeekFive ? 14 : 13, '');
            $row[2] = $label;
            $row[6] = (string) ($value - 50);
            $row[7] = '0';
            $row[8] = '0';
            $row[9] = (string) ($value + 10);
            $row[10] = (string) ($value + 20);
            $row[11] = (string) ($value + 30);
            $row[12] = $blankWeekFour ? '' : (string) ($value + 40);
            if ($includeWeekFive) {
                $row[13] = (string) ($value + 50);
            }
            $rows[] = $row;
        }

        $stream = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($stream, $row, ',', '"', '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return (string) $csv;
    }

    /** @return array<int, array{0: string, 1: int}> */
    private function reportRows(int $base): array
    {
        $creditRows = static fn (string $total, int $offset): array => [
            [$total, $base + $offset],
            ['MICRO', $base + $offset + 100],
            ['KUPEDES', $base + $offset + 110],
            ['BRIGUNA MIKRO', $base + $offset + 120],
            ['KUR MIKRO', $base + $offset + 130],
            ['KREDIT MIKRO - KPP', $base + $offset + 140],
            ['KUR KECIL', $base + $offset + 150],
            ['RITEL', $base + $offset + 200],
            ['KECIL KOMERSIAL', $base + $offset + 300],
            ['CONSUMER', $base + $offset + 400],
            ['BRIGUNA RITEL', $base + $offset + 410],
            ['KPR', $base + $offset + 420],
        ];

        return array_merge(
            $creditRows('PINJAMAN', 0),
            $creditRows('SML', 1_000),
            $creditRows('NPL', 2_000),
            [
                ['DANA PIHAK KE TIGA', $base],
                ['RITEL', $base + 500],
                ['GIRO', $base + 501],
                ['TABUNGAN', $base + 502],
                ['DEPOSITO', $base + 503],
                ['MICRO', $base + 510],
                ['GIRO', $base + 511],
                ['TABUNGAN', $base + 512],
                ['DEPOSITO', $base + 513],
                ['WHOLESALE', $base + 520],
                ['GIRO', $base + 521],
                ['TABUNGAN', $base + 522],
                ['DEPOSITO', $base + 523],
                ['TOTAL RITEL, MICRO & WHOLESALE', $base + 600],
                ['GIRO', $base + 601],
                ['TABUNGAN', $base + 602],
                ['DEPOSITO', $base + 603],
                ['RECOVERY DH', $base + 700],
            ]
        );
    }

    private function structuredWorkbookFixture(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheets = [
            'Area 6' => 1_000,
            'KC Madiun' => 2_000,
            'KC Magetan' => 3_000,
            'KC Ngawi' => 4_000,
            'KC Ponorogo' => 5_000,
        ];

        foreach ($sheets as $index => $base) {
            $sheet = $index === array_key_first($sheets)
                ? $spreadsheet->getActiveSheet()
                : $spreadsheet->createSheet();
            $sheet->setTitle($index);
            $sheet->setCellValue('B9', 'NO');
            $sheet->setCellValue('C9', 'KETERANGAN');
            $sheet->setCellValue('D9', 'POSISI');
            $sheet->setCellValue('J9', 'PROGNOSA');
            $sheet->setCellValue('O9', 'DELTA');
            $sheet->setCellValue('D10', '31 Agustus 2025');
            $sheet->setCellValue('E10', '31 Desember 2025');
            $sheet->setCellValue('I10', '31 Agustus 2026');
            $sheet->setCellValue('J10', 'WEEK 1 (5 September 26)');
            $sheet->setCellValue('K10', 'WEEK 2 (12 September 26)');
            $sheet->setCellValue('L10', 'WEEK 3 (19 September 26)');
            $sheet->setCellValue('M10', 'WEEK 4 (26 September 26)');
            $sheet->setCellValue('N10', 'WEEK 5 (30 September 26)');

            foreach ($this->reportRows($base) as $offset => [$label, $value]) {
                $row = 11 + $offset;
                $sheet->setCellValue("C{$row}", $label);
                $sheet->setCellValue("I{$row}", $value);
                for ($week = 1; $week <= 5; $week++) {
                    $column = chr(ord('I') + $week);
                    $sheet->setCellValue($column.$row, $value + ($week * 10));
                }
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'presentation_prognosa_structured_');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}
