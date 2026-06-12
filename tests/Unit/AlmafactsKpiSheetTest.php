<?php

namespace Tests\Unit;

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
        $this->assertSame(['mbm', 'ka-unit', 'rm-mikro', 'mantri'], array_keys($data['sheetOptions']));
        $this->assertSame('KPI MBM', $data['selectedSheet']['sheet']);
        $this->assertSame(['BO', 'MBM', 'SCORE'], $data['header']);
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
        $this->assertSame(
            ['BO', 'MBM', 'BC', 'UNIT KERJA', 'Pencapaian', 'Bobot 10%', 'Pencapaian', 'Bobot 25%'],
            $data['header']
        );
        $this->assertCount(8, $data['header']);
        $this->assertCount(8, $data['rows'][0]);
        $this->assertSame(['BO', 'MBM', 'BC', 'UNIT KERJA', 'LR', 'OS'], array_column($data['headerGroups'], 'label'));
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
        $this->assertSame(['BO', 'MBM', 'SCORE'], $data['header']);
        $this->assertCount(3, $data['header']);
        $this->assertCount(3, $data['rows'][0]);
        $this->assertSame('NUR', $data['rows'][0][1]);
        $this->assertSame(1, $data['summary']['row_count']);
    }

    public function test_kpi_page_can_open_rm_mikro_sheet_with_rank_sheet(): void
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
        $this->assertSame('rank', $data['selectedSheet']['sheet']);
        $this->assertSame('rank', $data['summary']['sheet_name']);
        $this->assertSame(
            ['KPI RM KUR KECIL KANCA', 'NAMA', 'BC UKER', 'UKER', 'JG', 'LAMA DI UKER 2026', 'PENCP', 'NILAI'],
            array_slice($data['header'], 0, 8)
        );
        $this->assertCount(20, $data['header']);
        $this->assertCount(20, $data['rows'][0]);
        $this->assertSame(
            ['KPI RM KUR KECIL KANCA', 'NAMA', 'BC UKER', 'UKER', 'JG', 'LAMA DI UKER 2026', 'NETT DISBURSEMENT KUR', 'DEBITUR MIKRO'],
            array_slice(array_column($data['headerGroups'], 'label'), 0, 8)
        );
        $this->assertContains('RANK', array_column($data['headerGroups'], 'label'));
        $this->assertSame('00172695 - Sugiyono', $data['rows'][0][1]);
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
        $this->assertSame('RANK KPI', $data['selectedSheet']['sheet']);
        $this->assertSame('RANK KPI', $data['summary']['sheet_name']);
        $this->assertSame(
            ['KEY', 'BO', 'MBM', 'UKER', 'TYPE BRI', 'BC', 'NAMA MANTRI', 'STATUS', 'JG', 'LAMA DI UKER 2026', 'PENCP', 'SCORE'],
            array_slice($data['header'], 0, 12)
        );
        $this->assertCount(31, $data['header']);
        $this->assertCount(31, $data['rows'][0]);
        $this->assertSame(
            ['KEY', 'BO', 'MBM', 'UKER', 'TYPE BRI', 'BC', 'NAMA MANTRI', 'STATUS', 'JG', 'LAMA DI UKER 2026', 'NETT DISBURSEMENT KUPEDES', 'NETT DISBURSEMENT KUR'],
            array_slice(array_column($data['headerGroups'], 'label'), 0, 12)
        );
        $this->assertContains('RANK AREA', array_column($data['headerGroups'], 'label'));
        $this->assertContains('RANK CABANG', array_column($data['headerGroups'], 'label'));
        $this->assertSame('Nur Elfiana', $data['rows'][0][2]);
        $this->assertSame(1, $data['summary']['row_count']);
    }
}
