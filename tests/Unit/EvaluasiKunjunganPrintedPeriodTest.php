<?php

namespace Tests\Unit;

use App\Services\Import\EvaluasiKunjunganSourceService;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class EvaluasiKunjunganPrintedPeriodTest extends TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    public function test_printed_timestamp_with_export_whitespace_supplies_period_without_changing_visit_date_or_numbers(): void
    {
        $row = $this->row();
        $source = $this->csv([
            ['ReportName', 'DatePrinted'],
            ['Evaluasi Aktivitas Kunjungan Pemasaran', "Date Printed : 12 Nov   2026\t09:16:45 AM"],
            EvaluasiKunjunganSourceService::SOURCE_HEADERS,
            $row,
        ]);
        $destination = $this->path();

        app(EvaluasiKunjunganSourceService::class)->stage($source, $destination);
        $preview = app(EvaluasiKunjunganSourceService::class)->preview($destination);

        $this->assertCount(24, EvaluasiKunjunganSourceService::COLUMNS);
        $this->assertSame(EvaluasiKunjunganSourceService::IMPORT_COLUMNS, $preview['headers']);
        $this->assertSame($row, array_slice($preview['preview'][0], 0, 24));
        $this->assertSame('2026-01-02', $preview['preview'][0][2]);
        $this->assertSame(['2026-11-12', '2026-11-12 09:16:45'], array_slice($preview['preview'][0], 24));
    }

    public function test_printed_pm_timestamp_keeps_time_and_uses_the_same_calendar_day(): void
    {
        $source = $this->csv([
            ['Date Printed : 12 Nov 2026 09:16:45 PM'],
            EvaluasiKunjunganSourceService::SOURCE_HEADERS,
            $this->row(),
        ]);
        $destination = $this->path();

        app(EvaluasiKunjunganSourceService::class)->stage($source, $destination);

        $preview = app(EvaluasiKunjunganSourceService::class)->preview($destination);
        $this->assertSame(['2026-11-12', '2026-11-12 21:16:45'], array_slice($preview['preview'][0], 24));
    }

    public static function invalidPreambles(): array
    {
        return [
            'missing metadata' => [[]],
            'missing timestamp' => [[['Date Printed :']]],
            'date only' => [[['Date Printed : 12 Nov 2026']]],
            'impossible date' => [[['Date Printed : 31 Nov 2026 09:16:45 AM']]],
            'invalid time' => [[['Date Printed : 12 Nov 2026 13:16:45 PM']]],
            'trailing content' => [[['Date Printed : 12 Nov 2026 09:16:45 AM other']]],
            'conflicting metadata' => [[['Date Printed : 12 Nov 2026 09:16:45 AM'], ['Date Printed : 13 Nov 2026 09:16:45 AM']]],
        ];
    }

    #[DataProvider('invalidPreambles')]
    public function test_invalid_printed_metadata_does_not_fall_back_to_visit_date_or_leave_staging(array $preamble): void
    {
        $source = $this->csv([...$preamble, EvaluasiKunjunganSourceService::SOURCE_HEADERS, $this->row()]);
        $destination = $this->path();
        try {
            app(EvaluasiKunjunganSourceService::class)->stage($source, $destination);
            $this->fail('Invalid printed metadata was accepted.');
        } catch (RuntimeException $error) {
            $this->assertNotSame('', $error->getMessage());
            $this->assertFileDoesNotExist($destination);
            $this->assertFileExists($source);
        }
    }

    public static function invalidCanonicalMetadata(): array
    {
        return [
            'legacy canonical without metadata' => [[], []],
            'missing period' => [['', '2026-11-12 09:16:45'], null],
            'impossible printed timestamp' => [['2026-11-12', '2026-11-31 09:16:45'], null],
            'period disagrees with printed timestamp' => [['2026-11-11', '2026-11-12 09:16:45'], null],
            'mixed report periods' => [['2026-11-12', '2026-11-12 09:16:45'], ['2026-11-13', '2026-11-13 09:16:45']],
            'mixed timestamps on the same day' => [['2026-11-12', '2026-11-12 09:16:45'], ['2026-11-12', '2026-11-12 10:16:45']],
        ];
    }

    #[DataProvider('invalidCanonicalMetadata')]
    public function test_preview_and_bulk_reject_missing_invalid_or_inconsistent_printed_metadata(array $firstMetadata, ?array $secondMetadata): void
    {
        $header = $firstMetadata === [] ? EvaluasiKunjunganSourceService::COLUMNS : EvaluasiKunjunganSourceService::IMPORT_COLUMNS;
        $rows = [$header, [...$this->row(), ...$firstMetadata]];
        if ($secondMetadata !== null && $secondMetadata !== []) {
            $second = $this->row();
            $second[1] = '2';
            $rows[] = [...$second, ...$secondMetadata];
        }
        $canonical = $this->csv($rows);
        $service = app(EvaluasiKunjunganSourceService::class);
        try {
            $service->preview($canonical);
            $this->fail('Preview accepted invalid printed metadata.');
        } catch (RuntimeException $error) {
            $this->assertNotSame('', $error->getMessage());
        }
        $bulk = $this->path();
        try {
            $service->prepareBulk($canonical, $bulk, EvaluasiKunjunganSourceService::IMPORT_COLUMNS);
            $this->fail('Bulk load accepted invalid printed metadata.');
        } catch (RuntimeException $error) {
            $this->assertNotSame('', $error->getMessage());
            $this->assertFileDoesNotExist($bulk);
        }
    }

    private function row(): array
    {
        return [
            '000001', '00123456789012345678901234567890', '2026-01-02', '-7.602666', '111.591637', 'kunjungan_pemasaran_pinjaman',
            '00144675', 'Mantri Contoh', 'Mantri', '0034', 'UNIT CONTOH', '0045', 'KC Madiun', 'R', 'KANWIL MALANG', 'DONE',
            'Jalan Contoh, Jawa Timur', '0000001', 'Debitur Contoh', 'bertemu', 'REFERRAL', '9007199254740993.01', 'Kelolaan', 'selesai',
        ];
    }

    private function csv(array $rows): string
    {
        $path = $this->path();
        $handle = fopen($path, 'wb');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }
        fclose($handle);

        return $path;
    }

    private function path(): string
    {
        $path = storage_path('framework/testing/kunjungan-printed-period-'.bin2hex(random_bytes(8)).'.csv');
        $this->files[] = $path;

        return $path;
    }
}
