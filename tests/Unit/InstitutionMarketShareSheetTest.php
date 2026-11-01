<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardSimpananController;
use App\Support\InstitutionMarketShareSheet;
use App\Support\LandingConsumerOperationalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class InstitutionMarketShareSheetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();
        Queue::fake();
    }

    private function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    private function source(): array
    {
        return [
            ['', 'KEPALA INSTANSI', '31 AGUSTUS 2026', '', '31 SEPTEMBER 2026', '', 'MTD', '', 'RM PIC 1', 'RM PIC 2', 'PEGAWAI', 'Potensi Briguna', 'Sudah Terlayani', 'PAYROLL'],
            ['Nama Instansi'],
            ['', '', 'Deb', 'Outstanding', 'Deb', 'Outstanding', 'Deb', 'Outstanding'],
            ['Instansi A', 'Pimpinan A', '9', '1.234.567,89', '10', '1.300.000,50', '1', '65.432,61', 'RM A', 'RM B', '100', '90', '10%', 'PAYROLL'],
            ['Instansi B', '', '4', '400.000', '3', '300.000', '(1)', '-100.000', 'RM C', '', '50', '47', '6%', 'NON PAYROLL'],
            ['', '', '13', '1.634.567,89', '13', '1.600.000,50', '-', '-34.567,39'],
        ];
    }

    public function test_three_row_headers_keep_september_and_mtd_separate_without_changing_cells(): void
    {
        $matrix = $this->source();
        $parsed = InstitutionMarketShareSheet::parse($this->csv($matrix));
        $this->assertSame('2026-09-30', $parsed['period']);
        $this->assertSame('30 September 2026 Deb', $parsed['columns'][4]);
        $this->assertSame('2026-09-30:outstanding', $parsed['column_keys'][5]);
        $this->assertSame('mtd:outstanding', $parsed['column_keys'][7]);
        $this->assertSame('employees', $parsed['column_keys'][10]);
        $this->assertSame(array_slice($matrix, 3, 2), array_slice($parsed['rows'], 0, 2));
        $this->assertCount(3, $parsed['rows']);
        $this->assertSame('-34.567,39', $parsed['rows'][2][7]);
    }

    public function test_column_reordering_does_not_mix_identity_or_financial_values(): void
    {
        $matrix = $this->source();
        $order = [8, 10, 0, 1, 4, 5, 2, 3, 9, 6, 7, 11, 12, 13];
        $reordered = array_map(static fn ($row) => array_map(static fn ($index) => $row[$index] ?? '', $order), $matrix);
        $parsed = InstitutionMarketShareSheet::parse($this->csv($reordered));
        $row = array_combine($parsed['column_keys'], $parsed['rows'][0]);
        $this->assertSame('Instansi A', $row['nama_instansi']);
        $this->assertSame('RM A', $row['rm_pic_1']);
        $this->assertSame('100', $row['employees']);
        $this->assertSame('1.300.000,50', $row['2026-09-30:outstanding']);
        $this->assertSame('65.432,61', $row['mtd:outstanding']);
    }

    public function test_area_aligns_different_months_aliases_and_extra_columns_by_key(): void
    {
        $madiun = InstitutionMarketShareSheet::parse($this->csv($this->source()));
        $other = InstitutionMarketShareSheet::parse($this->csv([
            ['Nama Instansi', '31 AGT 2026 Deb', 'Outstanding', 'MTD Deb', 'Outstanding', 'Total Pegawai', 'Potensi Briguna', 'KETERANGAN'],
            ['Instansi X', '2', '200', '1', '50', '100', '98', 'Catatan'],
        ]));
        $area = InstitutionMarketShareSheet::consolidate(['KC Madiun' => $madiun, 'KC Lain' => $other]);
        $row = array_combine($area['column_keys'], $area['rows'][3]);
        $this->assertSame('200', $row['2026-08-31:outstanding']);
        $this->assertSame('', $row['2026-09-30:outstanding']);
        $this->assertSame('50', $row['mtd:outstanding']);
        $this->assertSame('100', $row['employees']);
        $this->assertSame('Catatan', $row['keterangan']);
        $this->assertCount(count($area['columns']), $area['rows'][0]);
    }

    public function test_date_pair_without_child_labels_stays_in_its_own_period(): void
    {
        $matrix = $this->source();
        $matrix[2][4] = $matrix[2][5] = '';
        $parsed = InstitutionMarketShareSheet::parse($this->csv($matrix));
        $this->assertSame('2026-09-30:deb', $parsed['column_keys'][4]);
        $this->assertSame('2026-09-30:outstanding', $parsed['column_keys'][5]);
    }

    public function test_duplicate_semantic_columns_reject_refresh(): void
    {
        $this->expectException(RuntimeException::class);
        InstitutionMarketShareSheet::parse($this->csv([
            ['Nama Instansi', 'Potensi Briguna', 'PEGAWAI', 'Total Pegawai'],
            ['A', '5', '7', '9'],
        ]));
    }

    public function test_header_only_response_is_not_valid_data(): void
    {
        $this->expectException(RuntimeException::class);
        InstitutionMarketShareSheet::parse($this->csv(array_slice($this->source(), 0, 3)));
    }

    public function test_period_without_outstanding_is_rejected_instead_of_showing_zero(): void
    {
        $this->expectException(RuntimeException::class);
        InstitutionMarketShareSheet::parse($this->csv([
            ['Nama Instansi', '30 September 2026 Deb', 'Potensi Briguna'],
            ['A', '5', '9'],
        ]));
    }

    public function test_pipeline_uses_actual_september_balance_not_mtd_and_reads_employee_alias(): void
    {
        $payload = app(LandingConsumerOperationalService::class)->parseInstitutionCsv($this->csv($this->source()), ['spreadsheet_id' => 'test']);
        $this->assertSame(100, $payload['rows'][0]['employees']);
        $this->assertSame(1300000.5, $payload['rows'][0]['latest_outstanding']);
        $this->assertSame(1, $payload['rows'][0]['mtd_debtors']);
        $this->assertSame(300000.0, $payload['rows'][1]['latest_outstanding']);
        $this->assertSame(-1, $payload['rows'][1]['mtd_debtors']);
        $this->assertSame(2, $payload['row_count']);
    }

    public function test_refresh_publishes_aligned_cache_and_retains_it_when_source_becomes_invalid(): void
    {
        $valid = true;
        Http::fake(function ($request) use (&$valid) {
            $this->assertStringContainsString('/export?format=csv&gid=1224308739', $request->url());

            return Http::response($valid ? $this->csv($this->source()) : '<html>Sign in</html>');
        });
        $controller = new DashboardSimpananController;
        $result = $controller->refreshMarketShareInstansiSources();
        $this->assertTrue($result['success']);
        $this->assertSame(4, $result['refreshed']);
        $area = Cache::get('marketshare_instansi_data:v3:area6');
        $this->assertSame(12, $area['rowCount']);
        $payload = $controller->marketShareInstansiData(Request::create('/', 'GET', ['cabang' => 'kc-madiun']))->getData(true);
        $this->assertSame('2026-09-30', $payload['period']);
        $this->assertSame('1.300.000,50', $payload['rows'][0][5]);
        $valid = false;
        $this->assertFalse($controller->refreshMarketShareInstansiSources()['success']);
        $this->assertSame($area['rows'], Cache::get('marketshare_instansi_data:v3:area6')['rows']);
        $this->assertSame($payload, $controller->marketShareInstansiData(Request::create('/', 'GET', ['cabang' => 'kc-madiun']))->getData(true));
    }

    private function zeroOpportunitySource(): string
    {
        return $this->csv([
            ['Nama Instansi', '30 September 2026 Deb', 'Outstanding', 'Total Pegawai', 'Potensi Briguna', 'RM PIC 1'],
            ['Valid', '2', '1.000', '100', '98', 'RM A'],
            ['Zero potential', '2', '1.000', '100', '0', 'RM A'],
            ['Zero employees', '2', '1.000', '0,00', '98', 'RM A'],
            ['Missing employees', '2', '1.000', '', '98', 'RM A'],
            ['Missing potential', '2', '1.000', '100', '', 'RM A'],
            ['Accounting zero', '2', '1.000', '100', '-', 'RM A'],
            ['Parenthesized zero', '2', '1.000', '(0.000)', '98', 'RM A'],
            ['Negative potential', '2', '1.000', '100', '(2)', 'RM A'],
            ['Decimal nonzero', '2', '1.000', '100', '0,50', 'RM A'],
            ['TOTAL', '18', '9.000', '800', '472,50', ''],
        ]);
    }

    public function test_magetan_hides_zero_or_blank_in_either_column_but_preserves_nonzero_values_and_source(): void
    {
        $source = InstitutionMarketShareSheet::parse($this->zeroOpportunitySource());
        $visible = InstitutionMarketShareSheet::forDisplay($source, 'kc-magetan');
        $this->assertSame(['Valid', 'Negative potential', 'Decimal nonzero', 'TOTAL'], array_column($visible['rows'], 0));
        $this->assertSame(6, $visible['hiddenRowCount']);
        $this->assertSame(10, $visible['sourceRowCount']);
        $this->assertSame(4, $visible['rowCount']);
        $this->assertCount(10, $source['rows']);
        $this->assertSame($source['rows'][9], $visible['rows'][3]);
        $reordered = $source;
        $reordered['column_keys'] = array_reverse($source['column_keys']);
        $reordered['rows'] = array_map('array_reverse', $source['rows']);
        $this->assertSame(array_map('array_reverse', $visible['rows']), InstitutionMarketShareSheet::forDisplay($reordered, 'magetan')['rows']);
    }

    public function test_area_only_hides_magetan_rows_and_other_branches_are_unchanged(): void
    {
        $source = InstitutionMarketShareSheet::parse($this->zeroOpportunitySource());
        $area = InstitutionMarketShareSheet::consolidate(['KC Madiun' => $source, 'KC Magetan' => $source]);
        $visible = InstitutionMarketShareSheet::forDisplay($area, 'area6');
        $this->assertSame(14, $visible['rowCount']);
        $this->assertSame(array_slice($area['rows'], 0, 10), array_slice($visible['rows'], 0, 10));
        foreach (['kc-madiun', 'ngawi', 'ponorogo', 'caruban'] as $scope) {
            $this->assertSame($source, InstitutionMarketShareSheet::forDisplay($source, $scope));
        }
    }

    public function test_magetan_pipeline_filters_before_top_ten_and_keeps_source_totals_out_of_ranking(): void
    {
        $payload = app(LandingConsumerOperationalService::class)->parseInstitutionCsv($this->zeroOpportunitySource(), ['key' => 'magetan', 'spreadsheet_id' => 'test']);
        $this->assertSame(['Valid', 'Decimal nonzero', 'Negative potential'], array_column($payload['rows'], 'institution'));
        $this->assertSame(3, $payload['row_count']);
    }

    public function test_magetan_endpoint_filters_old_raw_cache_and_successful_refresh_without_mutating_source_cache(): void
    {
        Http::fake(fn () => Http::response($this->zeroOpportunitySource()));
        $controller = new DashboardSimpananController;
        $this->assertTrue($controller->refreshMarketShareInstansiSources()['success']);
        $magetan = $controller->marketShareInstansiData(Request::create('/', 'GET', ['cabang' => 'kc-magetan']))->getData(true);
        $area = $controller->marketShareInstansiData(Request::create('/', 'GET', ['cabang' => 'area6']))->getData(true);
        $this->assertSame(4, $magetan['rowCount']);
        $this->assertSame(34, $area['rowCount']);
        $this->assertSame(6, $area['hiddenRowCount']);
        $this->assertCount(40, Cache::get('marketshare_instansi_data:v3:area6')['rows']);
    }

    public function test_all_zero_rows_produce_valid_empty_view_not_stale_old_rows(): void
    {
        $source = InstitutionMarketShareSheet::parse($this->csv([
            ['Nama Instansi', 'PEGAWAI', 'Potensi Briguna'], ['A', '0', '10'],
        ]));
        $visible = InstitutionMarketShareSheet::forDisplay($source, 'magetan');
        $this->assertSame([], $visible['rows']);
        $this->assertSame(0, $visible['rowCount']);
        $this->assertSame(1, $visible['hiddenRowCount']);
    }
}
