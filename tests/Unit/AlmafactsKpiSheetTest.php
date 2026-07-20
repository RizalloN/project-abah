<?php

namespace Tests\Unit;

use App\Models\User;
use App\Http\Controllers\Report\AlmafactsDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AlmafactsKpiSheetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cache.default', 'array');
        Cache::flush();
    }

    public function test_kpi_page_defaults_to_mbm_sheet_and_uses_requested_order(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response("\"KEY PERFORMING INDICATOR\",\"MBM\",\"SCORE\",\"\",\"\"\n\"MADIUN\",\"NUR\",\"98%\",\"\",\"\"", 200),
        ]);

        $view = (new AlmafactsDashboardController())->kpi(Request::create('/report/dashboard-almafacts/kpi', 'GET'));
        $data = $view->getData();

        $this->assertSame('mbm', $data['selectedSheetKey']);
        $this->assertSame(['mbm', 'ka-unit', 'rm-mikro', 'rm-sme', 'mantri', 'consumer'], array_keys($data['sheetOptions']));
        $this->assertSame('KPI MBM', $data['selectedSheet']['sheet']);
        $this->assertSame('175qxZv6PZ6Lw3XaN7u1EdPpEjOEXYUsU', $data['selectedSheet']['spreadsheet_id']);
        $this->assertSame(['BO', 'MBM', 'Score'], $data['header']);
        $this->assertCount(3, $data['header']);
        $this->assertCount(3, $data['rows'][0]);
        $this->assertSame('NUR', $data['rows'][0][1]);
        $this->assertSame(1, $data['summary']['row_count']);
    }

    public function test_kpi_page_can_open_ka_unit_sheet(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response(
                "\"KEY PERFORMING INDICATOR UNIT 31 MEI 2026 KAUNIT BO\",\"MBM\",\"BC\",\"UNIT KERJA\",\"LR BOBOT 10%\",\"\",\"OS BOBOT 25%\",\"\"\n"
                . "\"\",\"\",\"\",\"\",\"\",\"\",\"0.00%\",\"\"\n"
                . "\"MADIUN\",\"UNIT TEST\",\"6348\",\"06348 -- UNIT UTERAN MADIUN\",\"110.00%\",\"11.00%\",\"104.11%\",\"26.03%\",\"\",\"\"",
                200
            ),
        ]);

        $view = (new AlmafactsDashboardController())->kpi(Request::create('/report/dashboard-almafacts/kpi/ka-unit', 'GET'), 'ka-unit');
        $data = $view->getData();

        $this->assertSame('ka-unit', $data['selectedSheetKey']);
        $this->assertSame('KPI Kaunit', $data['selectedSheet']['sheet']);
        $this->assertSame('1YlsKFIdwdgm9UVG-r8hgSuUn_qTXThMK', $data['selectedSheet']['spreadsheet_id']);
        $this->assertSame(
            ['BO', 'MBM', 'BC', 'Unit Kerja', 'Pencapaian', 'Score', 'Pencapaian', 'Score'],
            $data['header']
        );
        $this->assertCount(8, $data['header']);
        $this->assertCount(8, $data['rows'][0]);
        $this->assertSame(['BO', 'MBM', 'BC', 'Unit Kerja', 'LR (Bobot 10%)', 'OS (Bobot 25%)'], array_column($data['headerGroups'], 'label'));
        $this->assertSame('UNIT TEST', $data['rows'][0][1]);
        $this->assertSame(1, $data['summary']['row_count']);
        $this->assertSame('KEY PERFORMING INDICATOR UNIT 31 MEI 2026 KAUNIT', $data['summary']['sheet_title']);
    }

    public function test_kpi_page_can_open_mbm_sheet(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response("\"KEY PERFORMING INDICATOR\",\"MBM\",\"SCORE\",\"\",\"\"\n\"MADIUN\",\"NUR\",\"98%\",\"\",\"\"", 200),
        ]);

        $view = (new AlmafactsDashboardController())->kpi(Request::create('/report/dashboard-almafacts/kpi/mbm', 'GET'), 'mbm');
        $data = $view->getData();

        $this->assertSame('mbm', $data['selectedSheetKey']);
        $this->assertSame('KPI MBM', $data['selectedSheet']['sheet']);
        $this->assertSame('175qxZv6PZ6Lw3XaN7u1EdPpEjOEXYUsU', $data['selectedSheet']['spreadsheet_id']);
        $this->assertSame(['BO', 'MBM', 'Score'], $data['header']);
        $this->assertCount(3, $data['header']);
        $this->assertCount(3, $data['rows'][0]);
        $this->assertSame('NUR', $data['rows'][0][1]);
        $this->assertSame(1, $data['summary']['row_count']);
    }

    public function test_kpi_page_can_open_rm_mikro_sheet(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response(
                "\"KPI RM KUR KECIL KANCA\",\"NAMA\",\"BC UKER\",\"UKER\",\"JG\",\"LAMA DI UKER 2026\",\"NETT DISBURSEMENT KUR PENCP\",\"NILAI\",\"DEBITUR MIKRO PENCP\",\"NILAI\",\"NETT DG SML PENCP\",\"NILAI\",\"NETT DG NPL PENCP\",\"NILAI\",\"CASA DEBITUR PENCP\",\"NILAI\",\"QRIS PRODUKTIF PENCP\",\"NILAI\",\"SCORE\",\"RANK\"\n"
                . "\"NGAWI\",\"00172695 - Sugiyono\",\"57\",\"00057--KC Ngawi\",\"JG06\",\"5\",\"103.48%\",\"36.22%\",\"60.00%\",\"9.00%\",\"110.00%\",\"11.00%\",\"110.00%\",\"11.00%\",\"91.65%\",\"18.33%\",\"110.00%\",\"11.00%\",\"96.55%\",\"1\",\"\",\"\"",
                200
            ),
        ]);

        $view = (new AlmafactsDashboardController())->kpi(Request::create('/report/dashboard-almafacts/kpi/rm-mikro', 'GET'), 'rm-mikro');
        $data = $view->getData();

        $this->assertSame('rm-mikro', $data['selectedSheetKey']);
        $this->assertSame('KPI RM Mikro', $data['selectedSheet']['sheet']);
        $this->assertSame('11dzu4edTyp9UFBicNDughtJ43bzvZguh', $data['selectedSheet']['spreadsheet_id']);
        $this->assertSame('KPI RM Mikro', $data['summary']['sheet_name']);
        $this->assertSame(
            ['BO', 'Nama', 'BC Uker', 'Uker', 'JG', 'Lama Di UKER 2026', 'Pencapaian', 'Score'],
            array_slice($data['header'], 0, 8)
        );
        $this->assertCount(20, $data['header']);
        $this->assertCount(20, $data['rows'][0]);
        $this->assertSame(
            ['BO', 'Nama', 'BC Uker', 'Uker', 'JG', 'Lama Di UKER 2026', 'Nett Disbursement KUR', 'Debitur Mikro'],
            array_slice(array_column($data['headerGroups'], 'label'), 0, 8)
        );
        $this->assertContains('Rank', array_column($data['headerGroups'], 'label'));
        $this->assertSame('00172695 - Sugiyono', $data['rows'][0][1]);
        $this->assertSame(1, $data['summary']['row_count']);
    }

    public function test_kpi_page_can_open_rm_sme_sheet_with_weighted_two_row_header(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response(
                "\"KEY PERFORMING INDICATOR RM SME BO\",\"UKER\",\"JG\",\"Avg Balance Small\",\"\",\"Posisi OS Small\",\"\",\"SCORE\"\n"
                . "\"\",\"\",\"\",\"10%\",\"\",\"15%\",\"\",\"100%\"\n"
                . "\"1\",\"2\",\"3\",\"4\",\"5\",\"6\",\"7\",\"8\"\n"
                . "\"00045 -- KC Madiun\",\"00061445 - Unung\",\"JG07\",\"108.17%\",\"10.82\",\"100.46%\",\"15.07\",\"95.89%\"\n"
                . "\"BO\",\"UKER\",\"JG\",\"\",\"\",\"\",\"\",\"\"\n"
                . "\"\",\"\",\"\",\"10%\",\"\",\"15%\",\"\",\"100%\"\n"
                . "\"1\",\"2\",\"3\",\"4\",\"5\",\"6\",\"7\",\"8\"",
                200
            ),
        ]);

        $view = (new AlmafactsDashboardController())->kpi(Request::create('/report/dashboard-almafacts/kpi/rm-sme', 'GET'), 'rm-sme');
        $data = $view->getData();

        $this->assertSame('rm-sme', $data['selectedSheetKey']);
        $this->assertSame('KPI RM SME', $data['selectedSheet']['sheet']);
        $this->assertSame('1B5U9VxPSjOyLvygqwCKWZssoyf6xoEDs', $data['selectedSheet']['spreadsheet_id']);
        $this->assertSame(['BO', 'Uker', 'JG', 'Pencapaian', 'Score', 'Pencapaian', 'Score', 'Score'], $data['header']);
        $this->assertSame(
            ['BO', 'Uker', 'JG', 'AVG Balance Small (Bobot 10%)', 'Posisi OS Small (Bobot 15%)', 'Score'],
            array_column($data['headerGroups'], 'label')
        );
        $this->assertSame([2, 2, 2, 1, 1, 2], array_column($data['headerGroups'], 'rowspan'));
        $this->assertSame(2, $data['headerGroups'][3]['colspan']);
        $this->assertSame(['00045 -- KC Madiun', '00061445 - Unung', 'JG07', '108.17%', '10.82', '100.46%', '15.07', '95.89%'], $data['rows'][0]);
        $this->assertSame(1, $data['summary']['row_count']);
    }

    public function test_kpi_page_can_open_mantri_sheet_with_two_row_header(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response(
                "\"KEY\",\"BO\",\"MBM\",\"UKER\",\"TYPE BRI\",\"BC\",\"NAMA MANTRI\",\"STATUS\",\"JG\",\"LAMA DI UKER 2026\",\"NETT DISBURSEMENT KUPEDES PENCP\",\"SCORE\",\"NETT DISBURSEMENT KUR PENCP\",\"SCORE\",\"DEBITUR BARU PENCP\",\"SCORE\",\"% NPL ** PENCP\",\"SCORE\",\"NETT DG SML PENCP\",\"SCORE\",\"NETT DG NPL PENCP\",\"SCORE\",\"RASIO CASA PENCP\",\"SCORE\",\"RECOVERY DH ** PENCP\",\"SCORE\",\"QRIS PRODUKTIF PENCP\",\"SCORE\",\"SCORE\",\"RANK AREA\",\"RANK CABANG\"\n"
                . "\"3212 | 00057214 - Fithra Nugraha Sari\",\"MADIUN\",\"Nur Elfiana\",\"UNIT DOLOPO MADIUN\",\"TYPE 3\",\"3212\",\"00057214 - Fithra Nugraha Sari\",\"Lainnya/Non Program\",\"JG06\",\"1\",\"110.00%\",\"22.00%\",\"110.00%\",\"22.00%\",\"110.00%\",\"11.00%\",\"88.88%\",\"4.44%\",\"110.00%\",\"11.00%\",\"110.00%\",\"11.00%\",\"60.10%\",\"9.02%\",\"110.00%\",\"0.00%\",\"110.00%\",\"11.00%\",\"101.46%\",\"1\",\"1\",\"\",\"\"",
                200
            ),
        ]);

        $view = (new AlmafactsDashboardController())->kpi(Request::create('/report/dashboard-almafacts/kpi/mantri', 'GET'), 'mantri');
        $data = $view->getData();

        $this->assertSame('mantri', $data['selectedSheetKey']);
        $this->assertSame('KPI', $data['selectedSheet']['sheet']);
        $this->assertSame('1h7XMo46a10a3gC1f_CPtsBUT2V1PcxAE', $data['selectedSheet']['spreadsheet_id']);
        $this->assertSame('KPI', $data['summary']['sheet_name']);
        $this->assertSame(
            ['Key', 'BO', 'MBM', 'Uker', 'Type BRI', 'BC', 'Nama Mantri', 'Status', 'JG', 'Lama Di UKER 2026', 'Pencapaian', 'Score'],
            array_slice($data['header'], 0, 12)
        );
        $this->assertCount(31, $data['header']);
        $this->assertCount(31, $data['rows'][0]);
        $this->assertSame(
            ['Key', 'BO', 'MBM', 'Uker', 'Type BRI', 'BC', 'Nama Mantri', 'Status', 'JG', 'Lama Di UKER 2026', 'Nett Disbursement Kupedes', 'Nett Disbursement KUR'],
            array_slice(array_column($data['headerGroups'], 'label'), 0, 12)
        );
        $this->assertContains('Rank Area', array_column($data['headerGroups'], 'label'));
        $this->assertContains('Rank Cabang', array_column($data['headerGroups'], 'label'));
        $this->assertSame('Nur Elfiana', $data['rows'][0][2]);
        $this->assertSame(1, $data['summary']['row_count']);
    }

    public function test_kpi_consumer_splits_briguna_and_kpr_rows_and_skips_blank_rows(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response(
                "\"NO\",\"KANCA\",\"BC\",\"UKER\",\"SEGMEN\",\"PN PENGELOLA SINGLEPN\",\"SCORE\"\n"
                . "\"1\",\"KC Madiun\",\"45\",\"KC Madiun\",\"BRIGUNA\",\"001 - Briguna\",\"95.50\"\n"
                . "\"\",\"\",\"\",\"\",\"\",\"\",\"\"\n"
                . "\"2\",\"KC Madiun\",\"45\",\"KC Madiun\",\"KPR\",\"002 - KPR\",\"88.25\"",
                200
            ),
        ]);

        $view = (new AlmafactsDashboardController())->kpi(Request::create('/report/dashboard-almafacts/kpi/consumer', 'GET'), 'consumer');
        $data = $view->getData();

        $this->assertSame('consumer', $data['selectedSheetKey']);
        $this->assertSame('KPI Konsumer', $data['selectedSheet']['label']);
        $this->assertSame('KPI', $data['selectedSheet']['sheet']);
        $this->assertSame('1SL6lL9evwbJWzrXi7JDHbD5xVHcw1AEM', $data['selectedSheet']['spreadsheet_id']);
        $this->assertSame(2, $data['summary']['row_count']);
        $this->assertSame(['briguna', 'kpr'], array_column($data['tableSections'], 'key'));
        $this->assertSame('KPI Briguna', $data['tableSections'][0]['title']);
        $this->assertSame('KPI KPR', $data['tableSections'][1]['title']);
        $this->assertCount(1, $data['tableSections'][0]['rows']);
        $this->assertCount(1, $data['tableSections'][1]['rows']);
        $this->assertSame('BRIGUNA', $data['tableSections'][0]['rows'][0][4]);
        $this->assertSame('KPR', $data['tableSections'][1]['rows'][0][4]);

        $this->actingAs(new User([
            'name' => 'KPI Test',
            'pn' => 'test-kpi-consumer',
        ]));
        $html = $view->render();
        $this->assertStringContainsString('data-kpi-section="briguna"', $html);
        $this->assertStringContainsString('data-kpi-section="kpr"', $html);
        $this->assertStringContainsString('KPI Briguna', $html);
        $this->assertStringContainsString('KPI KPR', $html);
    }

    public function test_kpi_page_reads_two_row_header_with_pencp_and_nilai_as_sortable_columns(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response(
                "\"KPI RM MIKRO\",\"NAMA\",\"NETT DISBURSEMENT KUR\",\"\",\"RANK\"\n"
                . "\"BO\",\"\",\"PENCP\",\"NILAI\",\"\"\n"
                . "\"MADIUN\",\"RM UJI\",\"103.48%\",\"36.22%\",\"1\"",
                200
            ),
        ]);

        $view = (new AlmafactsDashboardController())->kpi(Request::create('/report/dashboard-almafacts/kpi/rm-mikro', 'GET'), 'rm-mikro');
        $data = $view->getData();

        $this->assertSame(['BO', 'Nama', 'Pencapaian', 'Score', 'Rank'], $data['header']);
        $this->assertSame(
            ['BO', 'Nama', 'Nett Disbursement KUR', 'Rank'],
            array_column($data['headerGroups'], 'label')
        );
        $this->assertSame([2, 2, 1, 2], array_column($data['headerGroups'], 'rowspan'));
        $this->assertSame(2, $data['headerGroups'][2]['colspan']);
        $this->assertSame(['MADIUN', 'RM UJI', '103.48%', '36.22%', '1'], $data['rows'][0]);

        $this->actingAs(new User([
            'pn' => 'test-kpi',
            'name' => 'KPI Test',
            'role' => 'admin',
        ]));

        $rendered = $view->render();
        $this->assertStringContainsString('data-sort-column="2"', $rendered);
        $this->assertStringContainsString("header.addEventListener('click'", $rendered);
    }
}
