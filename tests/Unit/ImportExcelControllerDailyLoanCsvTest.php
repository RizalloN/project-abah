<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Tests\TestCase;

class ImportExcelControllerDailyLoanCsvTest extends TestCase
{
    private ImportExcelController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new class extends ImportExcelController {
            protected function resolveExcelTableName(): string
            {
                return 'daily_loan_dinamis';
            }
        };
    }

    public function test_normalize_csv_row_reparses_outer_wrapped_daily_loan_row_without_shifting_columns(): void
    {
        $headers = $this->dailyLoanHeaders();
        $row = $this->makeDailyLoanRow([
            'PERIODE' => '2026-04-04',
            'NOMOR_REKENING1' => '4501060057100',
            'STATUS_REKENING1' => 'AKTIF',
            'NAMA_DEBITUR1' => 'DARTO,              ',
            'RATE' => '0.110000',
            'JANGKA_WAKTU1' => '120',
            'PLAFON' => '185000000.00',
            'BAKI_DEBET1' => '64633760.00',
            'Textbox21' => '64633760.00',
        ]);

        $innerLine = $this->toCsvLine($row);
        $wrappedLine = '"' . str_replace('"', '""', $innerLine) . '"';

        $parsed = $this->invokeMethod('normalizeCsvRow', [[$wrappedLine], ',', count($headers)]);

        $headerMap = array_flip($headers);

        $this->assertCount(count($headers), $parsed);
        $this->assertSame('DARTO,              ', $parsed[$headerMap['NAMA_DEBITUR1']]);
        $this->assertSame('0.110000', $parsed[$headerMap['RATE']]);
        $this->assertSame('120', $parsed[$headerMap['JANGKA_WAKTU1']]);
        $this->assertSame('185000000.00', $parsed[$headerMap['PLAFON']]);
        $this->assertSame('64633760.00', $parsed[$headerMap['BAKI_DEBET1']]);
    }

    public function test_normalize_csv_row_preserves_already_valid_daily_loan_row(): void
    {
        $headers = $this->dailyLoanHeaders();
        $row = $this->makeDailyLoanRow([
            'PERIODE' => '2026-04-04',
            'NOMOR_REKENING1' => '636001011738109',
            'STATUS_REKENING1' => 'AKTIF',
            'NAMA_DEBITUR1' => 'ICHWAN JATMIKO, S.H',
            'RATE' => '0.125000',
            'JANGKA_WAKTU1' => '96',
            'PLAFON' => '250000000.00',
            'BAKI_DEBET1' => '125000000.00',
            'Textbox21' => '125000000.00',
        ]);

        $parsed = $this->invokeMethod('normalizeCsvRow', [$row, ',', count($headers)]);
        $headerMap = array_flip($headers);

        $this->assertCount(count($headers), $parsed);
        $this->assertSame('ICHWAN JATMIKO, S.H', $parsed[$headerMap['NAMA_DEBITUR1']]);
        $this->assertSame('250000000.00', $parsed[$headerMap['PLAFON']]);
        $this->assertSame('125000000.00', $parsed[$headerMap['BAKI_DEBET1']]);
    }

    public function test_daily_loan_field_count_mismatch_is_logged_and_skipped(): void
    {
        Log::spy();

        $headers = $this->dailyLoanHeaders();
        $row = $this->makeDailyLoanRow([
            'PERIODE' => '2026-04-04',
            'NOMOR_REKENING1' => '5701033239106',
            'STATUS_REKENING1' => 'AKTIF',
            'NAMA_DEBITUR1' => 'SUWANDI,SH',
            'RATE' => '0.110000',
            'JANGKA_WAKTU1' => '84',
            'PLAFON' => '175000000.00',
            'BAKI_DEBET1' => '90000000.00',
        ]);

        array_pop($row);

        $isMismatch = $this->invokeMethod('hasDailyLoanFieldCountMismatch', [$headers, $row, 408, 'unit_test', ',']);

        $this->assertTrue($isMismatch);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_prepare_daily_loan_direct_load_source_keeps_rows_with_blank_noncritical_values(): void
    {
        $csvPath = storage_path('framework/testing/daily_loan_direct_load_normalize.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        $innerRow = $this->toCsvLine([
            '2026-04-04',
            'R',
            'KANWIL MALANG',
            '01',
            'KCP',
            'BRANCH',
            'UNIT',
            'IDR',
            'AO',
            '1234567890',
            '4501060057100',
            'AKTIF',
            'KREDIT',
            'DARTO',
            '0.110000',
            '120',
            '185000000.00',
            '64633760.00',
            '',
        ]);
        file_put_contents($csvPath, implode("\n", [
            implode(',', array_slice($this->dailyLoanHeaders(), 0, 19)),
            '"' . str_replace('"', '""', $innerRow) . '"',
        ]));

        $result = [];
        try {
            $result = $this->invokeMethod('prepareDailyLoanDirectLoadSource', [$csvPath, ',']);
        } finally {
            @unlink($csvPath);
            if (!empty($result['path'] ?? '') && file_exists((string) $result['path']) && ($result['cleanup'] ?? false)) {
                @unlink((string) $result['path']);
            }
        }

        $this->assertTrue($result['normalized']);
        $this->assertSame(1, $result['written_rows']);
        $this->assertSame(0, $result['skipped_count']);
    }

    public function test_normalize_excel_value_uses_strict_day_first_date_parsing(): void
    {
        $normalized = $this->invokeMethod('normalizeExcelValue', ['POSISI', '04/04/2026']);
        $usNormalized = $this->invokeMethod('normalizeExcelValue', ['POSISI', '04/20/2024']);

        $this->assertSame('2026-04-04', $normalized);
        $this->assertSame('2024-04-20', $usNormalized);
    }

    private function dailyLoanHeaders(): array
    {
        $reflection = new ReflectionClass(ImportExcelController::class);

        return $reflection->getConstant('DAILY_LOAN_SOURCE_HEADERS');
    }

    private function makeDailyLoanRow(array $overrides): array
    {
        $headers = $this->dailyLoanHeaders();
        $row = array_fill(0, count($headers), '');
        $headerMap = array_flip($headers);

        foreach ($overrides as $header => $value) {
            $row[$headerMap[$header]] = $value;
        }

        return $row;
    }

    private function toCsvLine(array $row): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $row);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return rtrim((string) $csv, "\r\n");
    }

    private function invokeMethod(string $method, array $arguments)
    {
        $reflection = new ReflectionClass($this->controller);
        $methodReflection = $reflection->getMethod($method);
        $methodReflection->setAccessible(true);

        return $methodReflection->invokeArgs($this->controller, $arguments);
    }
}
