<?php

namespace Tests\Unit;

use App\Services\Import\SchemaIntrospectionService;
use App\Services\Import\SmartContentHeaderGuardService;
use Tests\TestCase;

class SmartContentHeaderGuardServiceTest extends TestCase
{
    private SmartContentHeaderGuardService $guardService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guardService = app(SmartContentHeaderGuardService::class);
    }

    public function test_profile_column_values_detects_date_formats(): void
    {
        $isoDates = ['2026-03-31', '2026-03-31', '2026-03-31'];
        $isoProfile = $this->guardService->profileColumnValues($isoDates);
        $this->assertSame('date', $isoProfile['dominant_type']);
        $this->assertTrue($isoProfile['has_constant_value']);

        $slashDates = ['31/03/2026', '28/02/2026', '15/01/2026'];
        $slashProfile = $this->guardService->profileColumnValues($slashDates);
        $this->assertSame('date', $slashProfile['dominant_type']);
        $this->assertFalse($slashProfile['has_constant_value']);

        $indoTextualDates = ['31 Maret 2026', '28 Februari 2026', '15 Januari 2026'];
        $indoProfile = $this->guardService->profileColumnValues($indoTextualDates);
        $this->assertSame('date', $indoProfile['dominant_type']);

        $engTextualDates = ['June 26, 2026 at 6:00 AM', 'June 26, 2026 at 7:00 AM'];
        $engProfile = $this->guardService->profileColumnValues($engTextualDates);
        $this->assertSame('date', $engProfile['dominant_type']);
    }

    public function test_profile_column_values_detects_account_numbers(): void
    {
        $accounts = ['001201000123501', '612001004567503', '041301009876508'];
        $profile = $this->guardService->profileColumnValues($accounts);
        $this->assertSame('account_number', $profile['dominant_type']);
        $this->assertGreaterThanOrEqual(0.9, $profile['confidence']);
    }

    public function test_profile_column_values_detects_currency_and_decimals(): void
    {
        $amounts = ['1,250,000.50', '500,000.00', '13833797168.82', '-25000'];
        $profile = $this->guardService->profileColumnValues($amounts);
        $this->assertSame('currency_amount', $profile['dominant_type']);
        $this->assertGreaterThanOrEqual(0.9, $profile['confidence']);
    }

    public function test_profile_column_values_detects_branch_codes_and_names(): void
    {
        $codes = ['0012', '6120', '0413', '0045'];
        $codeProfile = $this->guardService->profileColumnValues($codes);
        $this->assertSame('branch_code', $codeProfile['dominant_type']);

        $names = ['KC Madiun', 'KCP Caruban', 'UNIT Jiwan', 'KC Ngawi'];
        $nameProfile = $this->guardService->profileColumnValues($names);
        $this->assertSame('branch_name', $nameProfile['dominant_type']);
    }

    public function test_detect_date_column_index_identifies_date_column_from_samples(): void
    {
        $headers = ['Kode', 'Tanggal Data', 'No Rekening', 'Saldo'];
        $sampleRows = [
            ['0012', '2026-03-31', '001201000123501', '1,500,000'],
            ['0012', '2026-03-31', '001201000123502', '2,500,000'],
            ['0012', '2026-03-31', '001201000123503', '3,500,000'],
        ];

        $dateIndex = $this->guardService->detectDateColumnIndex($headers, $sampleRows);
        $this->assertSame(1, $dateIndex);
    }

    public function test_detect_header_row_handles_renamed_or_generic_headers(): void
    {
        $rows = [
            ['Laporan Internal PT Bank BRI', '', '', ''],
            ['Tanggal Cetak: 2026-03-31', '', '', ''],
            ['TEXTBOX1', 'TEXTBOX2', 'TEXTBOX3', 'TEXTBOX4'], // Header row at index 2
            ['0012', '2026-03-31', '001201000123501', '1,500,000.00'],
            ['0012', '2026-03-31', '001201000123502', '2,500,000.00'],
            ['0012', '2026-03-31', '001201000123503', '3,500,000.00'],
        ];

        $headerRow = $this->guardService->detectHeaderRow($rows, 'daily_loan_dinamis');
        $this->assertSame(2, $headerRow);
    }

    public function test_match_columns_maps_renamed_headers_using_content(): void
    {
        $mockSchema = $this->createMock(SchemaIntrospectionService::class);
        $mockSchema->method('getColumnListing')->willReturn([
            'id', 'periode', 'cabang1', 'nomor_rekening1', 'baki_debet1', 'created_at', 'updated_at',
        ]);
        $mockSchema->method('getColumnMetadata')->willReturn([
            'periode' => ['base_type' => 'date'],
            'cabang1' => ['base_type' => 'varchar'],
            'nomor_rekening1' => ['base_type' => 'varchar'],
            'baki_debet1' => ['base_type' => 'decimal', 'scale' => 2],
        ]);

        $service = new SmartContentHeaderGuardService($mockSchema);

        // Headers are renamed from internal web source: "TGL", "CABANG", "NOREK", "OUTSTANDING"
        $headers = ['TGL', 'CABANG', 'NOREK', 'OUTSTANDING'];
        $sampleRows = [
            ['2026-03-31', 'KC Madiun', '001201000123501', '15000000.00'],
            ['2026-03-31', 'KC Ngawi',  '001201000123502', '25000000.00'],
            ['2026-03-31', 'KC Ponorogo','001201000123503', '35000000.00'],
        ];

        $result = $service->matchColumns($headers, $sampleRows, 'daily_loan_dinamis');

        $this->assertSame('periode', $result['mapping'][0]);
        $this->assertSame('nomor_rekening1', $result['mapping'][2]);
        $this->assertSame('baki_debet1', $result['mapping'][3]);
        $this->assertSame(0, $result['posisi_index']);
    }

    public function test_match_columns_handles_generic_textbox_headers(): void
    {
        $mockSchema = $this->createMock(SchemaIntrospectionService::class);
        $mockSchema->method('getColumnListing')->willReturn([
            'id', 'posisi', 'cifno', 'no_rekening', 'saldo_idr', 'created_at', 'updated_at',
        ]);
        $mockSchema->method('getColumnMetadata')->willReturn([
            'posisi' => ['base_type' => 'date'],
            'cifno' => ['base_type' => 'varchar'],
            'no_rekening' => ['base_type' => 'varchar'],
            'saldo_idr' => ['base_type' => 'decimal', 'scale' => 2],
        ]);

        $service = new SmartContentHeaderGuardService($mockSchema);

        // Internal SSRS/Cognos generated generic textbox headers
        $headers = ['TEXTBOX1', 'TEXTBOX2', 'TEXTBOX3', 'TEXTBOX4'];
        $sampleRows = [
            ['2026-03-31', '12345678', '001201000123501', '15,000,000.00'],
            ['2026-03-31', '23456789', '001201000123502', '25,000,000.00'],
            ['2026-03-31', '34567890', '001201000123503', '35,000,000.00'],
        ];

        $result = $service->matchColumns($headers, $sampleRows, 'simpanan_multipn');

        $this->assertSame('posisi', $result['mapping'][0]);
        $this->assertSame('cifno', $result['mapping'][1]);
        $this->assertSame('no_rekening', $result['mapping'][2]);
        $this->assertSame('saldo_idr', $result['mapping'][3]);
    }

    public function test_is_header_or_content_valid_for_table_accepts_renamed_headers_with_valid_content(): void
    {
        $mockSchema = $this->createMock(SchemaIntrospectionService::class);
        $mockSchema->method('getColumnListing')->willReturn([
            'id', 'posisi', 'saldo', 'produk', 'mbname', 'brname', 'segmen2',
        ]);

        $service = new SmartContentHeaderGuardService($mockSchema);

        $headers = ['TANGGAL', 'NOMINAL_SALDO', 'JENIS_PRODUK'];
        $sampleRows = [
            ['2026-03-31', '1,250,000.00', 'TABUNGAN'],
            ['2026-03-31', '5,000,000.00', 'GIRO'],
        ];

        $this->assertTrue($service->isHeaderOrContentValidForTable($headers, 'hourly_dpk', $sampleRows));
    }

    public function test_resolve_alias_candidate(): void
    {
        $dailyLoanCols = ['periode', 'nomor_rekening1', 'baki_debet1', 'cifno', 'cabang1'];
        $this->assertSame('nomor_rekening1', $this->guardService->resolveAliasCandidate('NOREK', $dailyLoanCols));
        $this->assertSame('baki_debet1', $this->guardService->resolveAliasCandidate('OUTSTANDING', $dailyLoanCols));
        $this->assertSame('baki_debet1', $this->guardService->resolveAliasCandidate('OS', $dailyLoanCols));
        $this->assertSame('periode', $this->guardService->resolveAliasCandidate('TANGGAL', $dailyLoanCols));

        $simpananCols = ['posisi', 'no_rekening', 'saldo_idr', 'cifno', 'nama_cabang'];
        $this->assertSame('no_rekening', $this->guardService->resolveAliasCandidate('ACCTNO', $simpananCols));
        $this->assertSame('saldo_idr', $this->guardService->resolveAliasCandidate('SALDO', $simpananCols));
        $this->assertSame('posisi', $this->guardService->resolveAliasCandidate('PERIODE', $simpananCols));
    }

    public function test_casa_brilink_headers_reconciled_with_aliases(): void
    {
        $controller = new \App\Http\Controllers\Import\ImportCasaBrilinkController();
        $reflector = new \ReflectionClass($controller);
        $method = $reflector->getMethod('reconcileCasaBrilinkHeaders');
        $method->setAccessible(true);

        // Standard 15 columns with renamed headers (e.g. no, wilayah, kanca, norek, saldo)
        $renamedHeaders = [
            'no', 'wilayah', 'nama_wilayah', 'kanca', 'nama_kanca',
            'branch', 'nama_unit', 'agen', 'mid', 'norek',
            'nama_agen', 'sumber', 'saldo', 'textbox9', 'cif',
        ];

        $reconciled = $method->invoke($controller, '', ',', $renamedHeaders);
        $this->assertNotNull($reconciled);
        $this->assertSame('account', $reconciled[9]);
        $this->assertSame('jml_nominal_casa', $reconciled[12]);
        $this->assertSame('cifno', $reconciled[14]);
    }
}
