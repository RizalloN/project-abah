<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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

    public function test_daily_loan_row_with_date_like_kode_kanwil_is_rejected(): void
    {
        $headers = array_map('strtolower', $this->dailyLoanHeaders());
        $row = $this->makeDailyLoanRow([
            'PERIODE' => '2026-04-04',
            'KODE_KANWIL1' => '04/04/2026',
            'NOMOR_REKENING1' => '5701033239106',
            'STATUS_REKENING1' => 'AKTIF',
            'NAMA_DEBITUR1' => 'SUWANDI,SH',
            'RATE' => '0.110000',
            'JANGKA_WAKTU1' => '84',
            'PLAFON' => '175000000.00',
            'BAKI_DEBET1' => '90000000.00',
        ]);

        $valuesByHeader = array_combine($headers, $row);

        $this->assertNotFalse($valuesByHeader);
        $this->assertFalse($this->invokeMethod('isValidDailyLoanRowValues', [(array) $valuesByHeader]));
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

    public function test_prepare_daily_loan_direct_load_source_skips_malformed_rows_when_normalizing(): void
    {
        $csvPath = storage_path('framework/testing/daily_loan_direct_load_malformed.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        $validRow1 = $this->toCsvLine([
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

        $validRow2 = $this->toCsvLine([
            '2026-04-05',
            'R',
            'KANWIL MALANG',
            '01',
            'KCP',
            'BRANCH',
            'UNIT',
            'IDR',
            'AO',
            '1234567891',
            '4501060057101',
            'AKTIF',
            'KREDIT',
            'SAMPLE',
            '0.110000',
            '120',
            '195000000.00',
            '74633760.00',
            '',
        ]);

        file_put_contents($csvPath, implode("\n", [
            implode(',', array_slice($this->dailyLoanHeaders(), 0, 19)),
            $validRow1,
            'BROKEN,ROW,WITH,TOO,MANY,COLUMNS,EXTRA',
            $validRow2,
        ]) . "\n");

        try {
            $result = $this->invokeMethod('prepareDailyLoanDirectLoadSource', [$csvPath, ',']);

            $this->assertTrue($result['normalized']);
            $this->assertSame(2, $result['written_rows']);
            $this->assertGreaterThanOrEqual(1, $result['skipped_count']);
        } finally {
            @unlink($csvPath);
            if (!empty($result['path'] ?? '') && file_exists((string) $result['path']) && ($result['cleanup'] ?? false)) {
                @unlink((string) $result['path']);
            }
        }
    }

    public function test_estimate_csv_import_total_rows_ignores_malformed_daily_loan_rows(): void
    {
        $headers = $this->dailyLoanHeaders();
        $csvPath = storage_path('framework/testing/daily_loan_total_rows_estimate.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        $row1 = $this->makeDailyLoanRow([
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

        $row2 = $this->makeDailyLoanRow([
            'PERIODE' => '2026-04-05',
            'NOMOR_REKENING1' => '636001011738110',
            'STATUS_REKENING1' => 'AKTIF',
            'NAMA_DEBITUR1' => 'SAMPLE DEBITUR',
            'RATE' => '0.125000',
            'JANGKA_WAKTU1' => '96',
            'PLAFON' => '150000000.00',
            'BAKI_DEBET1' => '75000000.00',
            'Textbox21' => '75000000.00',
        ]);

        file_put_contents($csvPath, implode("\n", [
            implode(',', $headers),
            $this->toCsvLine($row1),
            'BROKEN,ROW',
            $this->toCsvLine($row2),
        ]) . "\n");

        try {
            $estimate = $this->invokeMethod('estimateCsvImportTotalRows', [$csvPath, 0]);

            $this->assertSame(3, $estimate);
        } finally {
            @unlink($csvPath);
        }
    }

    public function test_prepare_csv_preview_payload_accepts_daily_loan_source_headers_without_suffixes(): void
    {
        $csvPath = storage_path('framework/testing/daily_loan_preview_source_headers.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        $headers = array_map(static function (string $header): string {
            if (preg_match('/^Textbox\d+$/i', $header) === 1) {
                return $header;
            }

            $header = preg_replace('/\d+$/', '', $header);

            return str_replace('_', ' ', $header);
        }, $this->dailyLoanHeaders());

        $row = array_fill(0, count($headers), '');
        $headerIndexes = array_flip($this->dailyLoanHeaders());
        $row[$headerIndexes['PERIODE']] = '31/03/2025';
        $row[$headerIndexes['KODE_KANWIL1']] = 'R';
        $row[$headerIndexes['KANWIL1']] = 'KANWIL MALANG';
        $row[$headerIndexes['KODE_CABANG1']] = '45';
        $row[$headerIndexes['CABANG1']] = 'KC Madiun';
        $row[$headerIndexes['NOMOR_REKENING1']] = '5,01E+11';
        $row[$headerIndexes['STATUS_REKENING1']] = '1';
        $row[$headerIndexes['LN_TYPE']] = 'WL';
        $row[$headerIndexes['NAMA_DEBITUR1']] = 'SAMINGUN';
        $row[$headerIndexes['RATE']] = '0,0813';
        $row[$headerIndexes['JANGKA_WAKTU1']] = '60M';
        $row[$headerIndexes['PLAFON']] = '150,000,000.00';
        $row[$headerIndexes['BAKI_DEBET1']] = '89,939,319.00';

        file_put_contents($csvPath, implode("\n", [
            implode(';', $headers),
            implode(';', $row),
        ]) . "\n");

        $createdNamaReportTable = false;
        if (!Schema::hasTable('nama_report')) {
            Schema::create('nama_report', function ($table) {
                $table->integer('id_report')->primary();
                $table->string('nama_report')->nullable();
                $table->string('table_name')->nullable();
            });
            $createdNamaReportTable = true;
        }

        try {
            session(['active_id_report' => 8]);
            $payload = $this->invokeMethod('prepareCsvPreviewPayload', [$csvPath]);

            $this->assertNotEmpty($payload['headers'] ?? []);
            $this->assertNotEmpty($payload['preview'] ?? []);
            $this->assertSame('2025-03-31', (string) ($payload['preview'][0]['PERIODE'] ?? ''));
            $this->assertSame('5,01E+11', (string) ($payload['preview'][0]['NOMOR_REKENING1'] ?? ''));
        } finally {
            @unlink($csvPath);
            if ($createdNamaReportTable) {
                Schema::drop('nama_report');
            }
        }
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

        if (isset($headerMap['KODE_KANWIL1'])) {
            $row[$headerMap['KODE_KANWIL1']] = 'R';
        }

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
