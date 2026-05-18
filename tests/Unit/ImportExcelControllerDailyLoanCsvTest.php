<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportExcelController;
use App\Services\Import\SchemaIntrospectionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
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
        $this->assertFalse($result['source_pre_normalized']);
        $this->assertSame(1, $result['written_rows']);
        $this->assertSame(0, $result['skipped_count']);
        $this->assertNotEmpty($result['headers'] ?? []);
        $this->assertArrayHasKey('period_hints', $result);
        $this->assertIsArray($result['period_hints']);
    }

    public function test_prepare_daily_loan_direct_load_source_rewrites_business_headers_to_canonical_headers(): void
    {
        $csvPath = storage_path('framework/testing/daily_loan_business_headers.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'PERIODE;KODE KANWIL;KANWIL;KODE CABANG;CABANG;BRANCH;UNIT;CURTYP;AO NAME;CIFNO;NOMOR REKENING;STATUS REKENING;LN TYPE;NAMA DEBITUR;RATE;JANGKA WAKTU;PLAFON;BAKI DEBET',
            '31/03/2025;R;KANWIL MALANG;45;KC Madiun;45;KC Madiun;IDR;Regional Office Malang;SDZJ380;5,01E+11;1;WL;SAMINGUN;0,0813;60M;150,000,000.00;89,939,319.00',
        ]) . "\n");

        $result = [];
        try {
            $result = $this->invokeMethod('prepareDailyLoanDirectLoadSource', [$csvPath, ';']);

            $this->assertTrue($result['normalized']);
            $this->assertFalse($result['source_pre_normalized']);
            $this->assertSame(1, $result['written_rows']);
            $this->assertNotEmpty($result['path']);

            $handle = fopen((string) $result['path'], 'r');
            $this->assertNotFalse($handle);
            $header = fgetcsv($handle, 0, ';');
            fclose($handle);

            $this->assertSame('KODE_KANWIL1', $header[1] ?? null);
            $this->assertSame('KANWIL1', $header[2] ?? null);
            $this->assertSame('KODE_CABANG1', $header[3] ?? null);
            $this->assertSame('CABANG1', $header[4] ?? null);
            $this->assertSame('BRANCH1', $header[5] ?? null);
            $this->assertSame('UNIT1', $header[6] ?? null);
            $this->assertSame('NOMOR_REKENING1', $header[10] ?? null);
            $this->assertSame('STATUS_REKENING1', $header[11] ?? null);
            $this->assertSame('NAMA_DEBITUR1', $header[13] ?? null);
            $this->assertSame('JANGKA_WAKTU1', $header[15] ?? null);
            $this->assertSame('BAKI_DEBET1', $header[17] ?? null);
        } finally {
            @unlink($csvPath);
            if (!empty($result['path'] ?? '') && file_exists((string) $result['path']) && ($result['cleanup'] ?? false)) {
                @unlink((string) $result['path']);
            }
        }
    }

    public function test_prepare_daily_loan_direct_load_source_skips_manual_excel_preamble_before_header(): void
    {
        $csvPath = storage_path('framework/testing/daily_loan_manual_excel_preamble.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, implode("\n", [
            'Textbox1,Textbox3',
            'Date Printed : 22 Apr 2026,Laporan Nominatif Pinjaman',
            '',
            'Textbox278,Textbox458',
            ': 20/04/2026,: Aktif',
            '',
            'PERIODE,KODE_KANWIL1,KANWIL1,KODE_CABANG1,CABANG1,BRANCH1,UNIT1,CURTYP,AO_NAME,CIFNO,NOMOR_REKENING1,STATUS_REKENING1,LN_TYPE,NAMA_DEBITUR1,RATE,JANGKA_WAKTU1,PLAFON,BAKI_DEBET1',
            '20-04-2026,R,KANWIL MALANG,49,KC Magetan,3874,UNIT ISWAHYUDI MAGETAN,IDR,Regional Office Malang,BBZ9338,101053983100,1,WL,BAYU AJI TRIWIBOWO,0.082500,24M,"50,000,000.00","17,587,572.00"',
        ]) . "\n");

        $result = [];
        try {
            $result = $this->invokeMethod('prepareDailyLoanDirectLoadSource', [$csvPath, ',']);

            $this->assertTrue($result['normalized']);
            $this->assertFalse($result['source_pre_normalized']);
            $this->assertSame(1, $result['written_rows']);
            $this->assertSame(0, $result['skipped_count']);

            $handle = fopen((string) $result['path'], 'r');
            $this->assertNotFalse($handle);

            $header = fgetcsv($handle, 0, ',');
            $row = fgetcsv($handle, 0, ',');
            fclose($handle);

            $this->assertSame('PERIODE', $header[0] ?? null);
            $this->assertSame('NOMOR_REKENING1', $header[10] ?? null);
            $this->assertContains($row[0] ?? null, ['2026-04-20', '20-04-2026']);
            $this->assertSame('101053983100', $row[10] ?? null);
        } finally {
            @unlink($csvPath);
            if (!empty($result['path'] ?? '') && file_exists((string) $result['path']) && ($result['cleanup'] ?? false)) {
                @unlink((string) $result['path']);
            }
        }
    }

    public function test_prepare_daily_loan_direct_load_source_escapes_backslash_before_comma_for_mysql_load_data(): void
    {
        $headers = $this->dailyLoanHeaders();
        $csvPath = storage_path('framework/testing/daily_loan_backslash_comma_guard.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        $row = $this->makeDailyLoanRow([
            'PERIODE' => '2026-05-07',
            'KODE_KANWIL1' => 'R',
            'NOMOR_REKENING1' => '635601024706100',
            'STATUS_REKENING1' => '1',
            'NAMA_DEBITUR1' => 'KASMI',
            'RATE' => '0.060000',
            'JANGKA_WAKTU1' => '36M',
            'PLAFON' => '25000000.00',
            'BAKI_DEBET1' => '25000000.00',
            'KELURAHAN_T_USAHA' => 'AN\\',
            'KODEPOS_T_USAHA' => '63396',
            'SEGMEN_DASHBOARD' => 'Micro',
            'PRODUK_DASHBOARD' => 'KUR-Mikro',
            'DIVISI_SEGMEN_DASHBOARD' => 'Micro',
            'Textbox21' => '25000000.00',
        ]);

        file_put_contents($csvPath, implode("\n", [
            implode(',', $headers),
            implode(',', $row),
        ]) . "\n");

        $result = [];
        try {
            $result = $this->invokeMethod('prepareDailyLoanDirectLoadSource', [$csvPath, ',']);
            $lines = file((string) $result['path'], FILE_IGNORE_NEW_LINES);

            $this->assertNotFalse($lines);
            $this->assertStringContainsString('AN\\\\', $lines[1] ?? '');
            $this->assertStringNotContainsString('AN\\,63396,Micro', $lines[1] ?? '');
        } finally {
            @unlink($csvPath);
            if (!empty($result['path'] ?? '') && file_exists((string) $result['path']) && ($result['cleanup'] ?? false)) {
                @unlink((string) $result['path']);
            }
        }
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
            $this->assertFalse($result['source_pre_normalized']);
            $this->assertSame(2, $result['written_rows']);
            $this->assertGreaterThanOrEqual(1, $result['skipped_count']);
        } finally {
            @unlink($csvPath);
            if (!empty($result['path'] ?? '') && file_exists((string) $result['path']) && ($result['cleanup'] ?? false)) {
                @unlink((string) $result['path']);
            }
        }
    }

    public function test_build_direct_daily_loan_csv_load_plan_uses_prepared_source_metadata_without_reloading_file(): void
    {
        $schemaService = Mockery::mock(SchemaIntrospectionService::class);
        $schemaService->shouldReceive('hasTable')->with('daily_loan_dinamis')->andReturnTrue();
        $schemaService->shouldReceive('getColumnListing')->with('daily_loan_dinamis')->andReturn($this->dailyLoanHeaders());
        $schemaService->shouldReceive('hasColumn')->andReturnTrue();
        $metadata = array_fill_keys(
            array_map('strtolower', $this->dailyLoanHeaders()),
            [
                'type' => 'varchar(255)',
                'base_type' => 'varchar',
                'max_length' => 255,
                'precision' => null,
                'scale' => null,
                'is_textual' => true,
                'is_decimal' => false,
            ]
        );
        $metadata['rate'] = [
            'type' => 'decimal(20,6)',
            'base_type' => 'decimal',
            'max_length' => null,
            'precision' => 20,
            'scale' => 6,
            'is_textual' => false,
            'is_decimal' => true,
        ];
        $metadata['plafon'] = [
            'type' => 'decimal(20,2)',
            'base_type' => 'decimal',
            'max_length' => null,
            'precision' => 20,
            'scale' => 2,
            'is_textual' => false,
            'is_decimal' => true,
        ];
        $schemaService->shouldReceive('getColumnMetadata')->with('daily_loan_dinamis')->andReturn($metadata);
        app()->instance(SchemaIntrospectionService::class, $schemaService);

        $csvPath = storage_path('framework/testing/daily_loan_invalid_source_for_prepared_plan.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, 'BROKEN,CONTENT,SHOULD,NOT,BE,READ');

        try {
            $plan = $this->invokeMethod('buildDirectDailyLoanCsvLoadPlan', [
                $csvPath,
                $this->dailyLoanHeaders(),
                [
                    'prepared_source' => [
                        'path' => $csvPath,
                        'cleanup' => false,
                        'normalized' => false,
                        'source_pre_normalized' => false,
                        'backend' => 'raw',
                        'headers' => $this->dailyLoanHeaders(),
                        'skipped_rows' => [],
                        'skipped_count' => 0,
                        'written_rows' => 2,
                        'period_hints' => ['2026-04-04'],
                    ],
                    'delimiter' => ',',
                    'source_backend' => 'raw',
                    'source_pre_normalized' => false,
                    'replace_existing_periods' => true,
                    'replace_periods' => ['2026-04-04'],
                ],
            ]);
        } finally {
            @unlink($csvPath);
        }

        $this->assertSame($csvPath, $plan['source_path'] ?? null);
        $this->assertSame(['2026-04-04'], $plan['period_hints'] ?? []);
        $this->assertSame('raw', $plan['source_backend'] ?? null);
        $this->assertSame(2, $plan['validation_written_rows'] ?? null);
        $this->assertNotEmpty(array_filter(
            (array) ($plan['set_clauses'] ?? []),
            static fn (string $clause): bool => str_contains(strtolower($clause), '`jangka_waktu1`')
        ));
        $this->assertNotEmpty(array_filter(
            (array) ($plan['set_clauses'] ?? []),
            static fn (string $clause): bool => str_contains(strtolower($clause), '`rate`') && str_contains($clause, 'DECIMAL(24,6)')
        ));
        $this->assertNotEmpty(array_filter(
            (array) ($plan['set_clauses'] ?? []),
            static fn (string $clause): bool => str_contains(strtolower($clause), '`plafon`') && str_contains($clause, 'DECIMAL(24,2)')
        ));

        $descriptionClauses = array_values(array_filter(
            (array) ($plan['set_clauses'] ?? []),
            static fn (string $clause): bool => str_contains(strtolower($clause), '`description`')
        ));
        $this->assertNotEmpty($descriptionClauses);
        $this->assertStringContainsString('NULLIF(NULLIF', $descriptionClauses[0]);
        $this->assertStringNotContainsString('TRIM', strtoupper($descriptionClauses[0]));
    }

    public function test_daily_loan_import_preserves_nbsp_text_and_sql_direct_load_text(): void
    {
        $this->assertSame(
            "Kredit Mikro - KUR Ritel 2015\xC2\xA0",
            $this->invokeMethod('normalizeExcelValue', ['DESCRIPTION', "Kredit Mikro - KUR Ritel 2015\xC2\xA0"])
        );

        $expression = $this->invokeMethod('buildDirectLoadTextExpression', ['@csv_col_71', true]);

        $this->assertStringNotContainsString('TRIM', $expression);
        $this->assertStringContainsString('@csv_col_71', $expression);
    }

    public function test_daily_loan_direct_load_rejects_rate_schema_that_cannot_store_six_decimals(): void
    {
        $schemaService = Mockery::mock(SchemaIntrospectionService::class);
        $schemaService->shouldReceive('hasTable')->with('daily_loan_dinamis')->andReturnTrue();
        $schemaService->shouldReceive('getColumnListing')->with('daily_loan_dinamis')->andReturn($this->dailyLoanHeaders());
        $schemaService->shouldReceive('hasColumn')->andReturnTrue();
        $metadata = array_fill_keys(
            array_map('strtolower', $this->dailyLoanHeaders()),
            [
                'type' => 'varchar(255)',
                'base_type' => 'varchar',
                'max_length' => 255,
                'precision' => null,
                'scale' => null,
                'is_textual' => true,
                'is_decimal' => false,
            ]
        );
        $metadata['rate'] = [
            'type' => 'decimal(20,2)',
            'base_type' => 'decimal',
            'max_length' => null,
            'precision' => 20,
            'scale' => 2,
            'is_textual' => false,
            'is_decimal' => true,
        ];
        $schemaService->shouldReceive('getColumnMetadata')->with('daily_loan_dinamis')->andReturn($metadata);
        app()->instance(SchemaIntrospectionService::class, $schemaService);

        $csvPath = storage_path('framework/testing/daily_loan_rate_precision_guard.csv');
        if (!is_dir(dirname($csvPath))) {
            @mkdir(dirname($csvPath), 0777, true);
        }

        file_put_contents($csvPath, 'BROKEN,CONTENT,SHOULD,NOT,BE,READ');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('kolom `rate` harus DECIMAL dengan 6 digit desimal');

        try {
            $this->invokeMethod('buildDirectDailyLoanCsvLoadPlan', [
                $csvPath,
                $this->dailyLoanHeaders(),
                [
                    'prepared_source' => [
                        'path' => $csvPath,
                        'cleanup' => false,
                        'normalized' => false,
                        'source_pre_normalized' => false,
                        'backend' => 'raw',
                        'headers' => $this->dailyLoanHeaders(),
                        'skipped_rows' => [],
                        'skipped_count' => 0,
                        'written_rows' => 1,
                        'period_hints' => ['2026-04-04'],
                    ],
                    'delimiter' => ',',
                    'source_backend' => 'raw',
                    'source_pre_normalized' => false,
                    'replace_existing_periods' => true,
                    'replace_periods' => ['2026-04-04'],
                ],
            ]);
        } finally {
            @unlink($csvPath);
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

    public function test_canonicalize_daily_loan_source_headers_maps_business_headers_to_internal_headers(): void
    {
        $sourceHeaders = [
            'PERIODE', 'KODE KANWIL', 'KANWIL', 'KODE CABANG', 'CABANG', 'BRANCH', 'UNIT', 'CURTYP',
            'AO NAME', 'CIFNO', 'NOMOR REKENING', 'STATUS REKENING', 'LN TYPE', 'NAMA DEBITUR', 'RATE', 'JANGKA WAKTU',
            'PLAFON', 'BAKI DEBET', 'NILAI TERCATAT', 'KOL ADK', 'PN NAME', 'PN PEMRAKARSA', 'PN REFERRAL',
            'PN RESTRUK', 'PN PEMUTUS', 'PN CRM', 'PN REFERRAL NAIK KELAS', 'JUMLAH PN', 'JUMLAH PN ALL',
            'RESTRUK KE', 'JENIS RESTRUK', 'FLAG RESTRUK COVID', 'FLAG COMMODITY CHAIN', 'FLAG BRIGUNA DIGITAL',
            'TOTAL DEFERRED INTEREST DITUNDA DAN BELUM DIJADWALKAN', 'TAGIHAN POKOK', 'TAGIHAN BUNGA', 'TAGIHAN DENDA',
        ];
        $canonical = $this->invokeMethod('canonicalizeDailyLoanSourceHeaders', [$sourceHeaders]);

        $this->assertSame('KODE_KANWIL1', $canonical[1]);
        $this->assertSame('KANWIL1', $canonical[2]);
        $this->assertSame('KODE_CABANG1', $canonical[3]);
        $this->assertSame('CABANG1', $canonical[4]);
        $this->assertSame('BRANCH1', $canonical[5]);
        $this->assertSame('UNIT1', $canonical[6]);
        $this->assertSame('NOMOR_REKENING1', $canonical[10]);
        $this->assertSame('STATUS_REKENING1', $canonical[11]);
        $this->assertSame('NAMA_DEBITUR1', $canonical[13]);
        $this->assertSame('JANGKA_WAKTU1', $canonical[15]);
        $this->assertSame('BAKI_DEBET1', $canonical[17]);
        $this->assertSame('NILAI_TERCATAT1', $canonical[18]);
        $this->assertSame('KOL_ADK1', $canonical[19]);
        $this->assertSame('PN_NAME1', $canonical[20]);
        $this->assertSame('PN_PEMRAKARSA1', $canonical[21]);
        $this->assertSame('PN_REFERRAL1', $canonical[22]);
        $this->assertSame('PN_RESTRUK1', $canonical[23]);
        $this->assertSame('PN_PEMUTUS1', $canonical[24]);
        $this->assertSame('PN_CRM1', $canonical[25]);
        $this->assertSame('PN_REFERRAL_NAIK_KELAS1', $canonical[26]);
        $this->assertSame('JUMLAH_PN1', $canonical[27]);
        $this->assertSame('JUMLAH_PN_ALL1', $canonical[28]);
        $this->assertSame('RESTRUK_KE1', $canonical[29]);
        $this->assertSame('JENIS_RESTRUK1', $canonical[30]);
        $this->assertSame('FLAG_RESTRUK_COVID1', $canonical[31]);
        $this->assertSame('FLAG_COMMODITY_CHAIN1', $canonical[32]);
        $this->assertSame('FLAG_BRIGUNA_DIGITAL1', $canonical[33]);
        $this->assertSame('LBDOTU', $canonical[34]);
        $this->assertSame('BILPRN', $canonical[35]);
        $this->assertSame('BILINT', $canonical[36]);
        $this->assertSame('BILLC', $canonical[37]);
    }

    public function test_normalize_excel_value_uses_strict_day_first_date_parsing(): void
    {
        $normalized = $this->invokeMethod('normalizeExcelValue', ['POSISI', '04/04/2026']);
        $usNormalized = $this->invokeMethod('normalizeExcelValue', ['POSISI', '04/20/2024']);

        $this->assertSame('2026-04-04', $normalized);
        $this->assertSame('2024-04-20', $usNormalized);
    }

    public function test_direct_load_integer_expression_accepts_jangka_waktu_unit_suffix(): void
    {
        $expression = $this->invokeMethod('buildDirectLoadIntegerExpression', ['@csv_col_15']);

        $this->assertStringContainsString("REGEXP '^-?[0-9]+'", $expression);
        $this->assertStringContainsString('CAST(', $expression);
        $this->assertSame(24, $this->invokeMethod('normalizeExcelValue', ['JANGKA_WAKTU1', '24M']));
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
