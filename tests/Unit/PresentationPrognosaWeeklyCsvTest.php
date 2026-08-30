<?php

namespace Tests\Unit;

use App\Services\Presentation\PresentationPrognosaWeeklyService;
use Carbon\Carbon;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
            'Report AREA' => $this->csvFixture(1_000),
            'MADIUN' => $this->csvFixture(2_000, true),
            'MAGETAN' => $this->csvFixture(3_000),
            'NGAWI' => $this->csvFixture(4_000),
            'PONOROGO' => $this->csvFixture(5_000),
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
        $this->assertSame('Report AREA', data_get($payload, 'meta.source_sheet'));

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
            'Report AREA' => $this->csvFixture(1_000, false, '30 AGUSTUS 2026', true),
            'MADIUN' => $this->csvFixture(2_000, false, '30 AGUSTUS 2026', true),
            'MAGETAN' => $this->csvFixture(3_000, false, '30 AGUSTUS 2026', true),
            'NGAWI' => $this->csvFixture(4_000, false, '30 AGUSTUS 2026', true),
            'PONOROGO' => $this->csvFixture(5_000, false, '30 AGUSTUS 2026', true),
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

    private function csvFixture(
        int $base,
        bool $legacyHeader = false,
        string $positionDate = '19 AGUSTUS 2026',
        bool $blankWeekFour = false
    ): string
    {
        $rows = [];
        $header = array_fill(0, 13, '');
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
            $header[9] = 'PROGNOSA WEEK 1';
            $header[10] = 'WEEK 2';
            $header[11] = 'WEEK 3';
            $header[12] = 'WEEK 4';
            $rows[] = $header;
        }

        foreach ($this->reportRows($base) as [$label, $value]) {
            $row = array_fill(0, 13, '');
            $row[2] = $label;
            $row[6] = (string) ($value - 50);
            $row[7] = '0';
            $row[8] = '0';
            $row[9] = (string) ($value + 10);
            $row[10] = (string) ($value + 20);
            $row[11] = (string) ($value + 30);
            $row[12] = $blankWeekFour ? '' : (string) ($value + 40);
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
}
