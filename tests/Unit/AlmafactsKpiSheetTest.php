<?php

namespace Tests\Unit;

use App\Models\User;
use App\Http\Controllers\Report\AlmafactsDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AlmafactsKpiSheetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cache.default', 'array');
        Cache::flush();
        Queue::fake();
    }

    public function test_kpi_page_defaults_to_mbm_sheet_and_uses_requested_order(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response("\"KEY PERFORMING INDICATOR\",\"MBM\",\"SCORE\",\"\",\"\"\n\"MADIUN\",\"NUR\",\"98%\",\"\",\"\"", 200),
        ]);

        $view = $this->kpiView('mbm');
        $data = $view->getData();

        $this->assertSame('mbm', $data['selectedSheetKey']);
        $this->assertSame(['mbm', 'ka-unit', 'rm-mikro', 'rm-sme', 'mantri', 'consumer'], array_keys($data['sheetOptions']));
        $this->assertSame('KPI MBM', $data['selectedSheet']['sheet']);
        $this->assertSame('1OmIag7zJ3MdlKMP4hDyUqEvnP1tbbL7j', $data['selectedSheet']['spreadsheet_id']);
        $this->assertSame('2026-07', $data['selectedPeriod']);
        $this->assertSame('Juli 2026', $data['selectedPeriodLabel']);
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

        $view = $this->kpiView('ka-unit');
        $data = $view->getData();

        $this->assertSame('ka-unit', $data['selectedSheetKey']);
        $this->assertSame('KPI KaUnit', $data['selectedSheet']['sheet']);
        $this->assertSame('1wI-dsRmkzWb4d0oqILxnh1r4UkM3tGJE', $data['selectedSheet']['spreadsheet_id']);
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

        $view = $this->kpiView('mbm');
        $data = $view->getData();

        $this->assertSame('mbm', $data['selectedSheetKey']);
        $this->assertSame('KPI MBM', $data['selectedSheet']['sheet']);
        $this->assertSame('1OmIag7zJ3MdlKMP4hDyUqEvnP1tbbL7j', $data['selectedSheet']['spreadsheet_id']);
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

        $view = $this->kpiView('rm-mikro');
        $data = $view->getData();

        $this->assertSame('rm-mikro', $data['selectedSheetKey']);
        $this->assertSame('KPI RM Mikro', $data['selectedSheet']['sheet']);
        $this->assertSame('1NNMRC8w2Z35n6WfKHL9Q9i575jwkMRzF', $data['selectedSheet']['spreadsheet_id']);
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

    public function test_kpi_page_can_open_rm_sme_dashboard_from_three_source_sheets(): void
    {
        Http::fake(fn ($request) => $this->rmSmeResponse($request->url()));

        $view = $this->kpiView('rm-sme');
        $data = $view->getData();

        $this->assertSame('rm-sme', $data['selectedSheetKey']);
        $this->assertSame('report.almafacts.kpi-rm-sme', $view->name());
        $this->assertSame('Sheet1', $data['selectedSheet']['sheet']);
        $this->assertSame('13s9SkGMC0ShjlEZGog1uBtgLxFY1RqPN5ZvMjzIVRUg', $data['selectedSheet']['spreadsheet_id']);
        $this->assertSame(
            ['NAMA KANCA KONSOL', 'NAMA UKO', 'NAMA MANTRI', 'JG'],
            $data['header'],
            json_encode([
                'error' => $data['error'],
                'requests' => collect(Http::recorded())->map(fn (array $entry): string => $entry[0]->url())->all(),
            ], JSON_PRETTY_PRINT)
        );
        $this->assertSame(1, $data['summary']['row_count']);
        $this->assertSame('2026-07-31', $data['rmSmeDashboard']['latest_period']);
        $this->assertSame(1, $data['rmSmeDashboard']['stats']['rm_count']);
        $this->assertCount(7, $data['rmSmeDashboard']['metrics']);

        $this->actingAs(new User(['pn' => 'test-rm-sme', 'name' => 'RM SME Test', 'role' => 'admin']));
        $html = $view->render();
        $this->assertStringContainsString('Dashboard KPI RM SME', $html);
        $this->assertStringContainsString('Dashboard Individu', $html);
        $this->assertStringContainsString('Summary Kinerja', $html);
        $this->assertStringContainsString('id="rmsme-summary-period"', $html);
        $this->assertStringContainsString('id="rmsme-ranking-caption"', $html);
        $this->assertStringContainsString('"summary_by_period"', $html);
        $this->assertStringContainsString('rmsme-growth-chart', $html);
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

        $view = $this->kpiView('mantri');
        $data = $view->getData();

        $this->assertSame('mantri', $data['selectedSheetKey']);
        $this->assertSame('rank', $data['selectedSheet']['sheet']);
        $this->assertSame('14As5M-bVMRa9OSFEo1mcaH1M1Derm7ca', $data['selectedSheet']['spreadsheet_id']);
        $this->assertSame('rank', $data['summary']['sheet_name']);
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
        $this->assertTrue($data['kpiBranchFilter']['enabled']);
        $this->assertSame(['all', 'MADIUN'], array_column($data['kpiBranchFilter']['options'], 'value'));
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

        $view = $this->kpiView('consumer');
        $data = $view->getData();

        $this->assertSame('consumer', $data['selectedSheetKey']);
        $this->assertSame('KPI Konsumer', $data['selectedSheet']['label']);
        $this->assertSame('KPI', $data['selectedSheet']['sheet']);
        $this->assertSame('1a-fr7OnoTIa_aJZ_b-yt_KkLJiJu92McQxv93ab58qQ', $data['selectedSheet']['spreadsheet_id']);
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

    public function test_kpi_branch_filter_limits_consumer_rows_to_the_selected_branch(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response(
                "\"NO\",\"KANCA\",\"BC\",\"UKER\",\"SEGMEN\",\"SCORE\"\n"
                . "\"1\",\"KC Madiun\",\"45\",\"KC Madiun\",\"BRIGUNA\",\"95.50\"\n"
                . "\"2\",\"KC Ngawi\",\"57\",\"KC Ngawi\",\"KPR\",\"88.25\"",
                200
            ),
        ]);

        $controller = new AlmafactsDashboardController();
        $controller->refreshKpiSourceCaches(['consumer']);
        $view = $controller->kpi(
            Request::create('/report/dashboard-almafacts/kpi/consumer', 'GET', ['cabang' => 'KC Ngawi']),
            'consumer'
        );
        $data = $view->getData();

        $this->assertTrue($data['kpiBranchFilter']['enabled']);
        $this->assertFalse($data['kpiBranchFilter']['locked']);
        $this->assertSame('KC Ngawi', $data['kpiBranchFilter']['selected']);
        $this->assertSame(1, $data['summary']['row_count']);
        $this->assertCount(0, $data['tableSections'][0]['rows']);
        $this->assertCount(1, $data['tableSections'][1]['rows']);
    }

    public function test_kpi_branch_filter_is_locked_to_the_authenticated_user_branch(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response(
                "\"NO\",\"KANCA\",\"BC\",\"UKER\",\"SEGMEN\",\"SCORE\"\n"
                . "\"1\",\"KC Madiun\",\"45\",\"KC Madiun\",\"BRIGUNA\",\"95.50\"\n"
                . "\"2\",\"KC Ngawi\",\"57\",\"KC Ngawi\",\"KPR\",\"88.25\"",
                200
            ),
        ]);
        $this->actingAs(new User(['pn' => 'test-kpi-scope', 'branch_scope' => 'madiun']));

        $controller = new AlmafactsDashboardController();
        $controller->refreshKpiSourceCaches(['consumer']);
        $view = $controller->kpi(
            Request::create('/report/dashboard-almafacts/kpi/consumer', 'GET', ['cabang' => 'KC Ngawi']),
            'consumer'
        );
        $data = $view->getData();

        $this->assertTrue($data['kpiBranchFilter']['locked']);
        $this->assertSame('KC Madiun', $data['kpiBranchFilter']['selected']);
        $this->assertSame(['KC Madiun'], array_column($data['kpiBranchFilter']['options'], 'value'));
        $this->assertSame(1, $data['summary']['row_count']);

        $rendered = $view->render();
        $this->assertSame(1, preg_match('/<select[^>]+id="kpi-branch-filter"[^>]*>/', $rendered, $matches));
        $this->assertStringContainsString('disabled', $matches[0]);
        $this->assertStringNotContainsString('onchange=', $matches[0]);
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

        $view = $this->kpiView('rm-mikro');
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

    public function test_kpi_page_reads_warmed_cache_without_remote_http_call(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response("\"KEY PERFORMING INDICATOR\",\"MBM\",\"SCORE\"\n\"MADIUN\",\"NUR\",\"98%\"", 200),
        ]);

        $controller = new AlmafactsDashboardController();
        $controller->refreshKpiSourceCaches(['mbm']);
        Http::preventStrayRequests();

        $view = $controller->kpi(Request::create('/report/dashboard-almafacts/kpi/mbm', 'GET'), 'mbm');

        $this->assertSame(['BO', 'MBM', 'Score'], $view->getData()['header']);
        Http::assertSentCount(1);
    }

    public function test_kpi_page_keeps_june_and_july_sources_in_separate_periods(): void
    {
        Http::fake(fn ($request) => str_contains($request->url(), '1B5U9VxPSjOyLvygqwCKWZssoyf6xoEDs')
            ? Http::response(
                "\"KEY PERFORMING INDICATOR RM SME BO\",\"UKER\",\"JG\",\"Posisi OS Small\",\"\"\n"
                . "\"\",\"\",\"\",\"15%\",\"\"\n"
                . "\"1\",\"2\",\"3\",\"4\",\"5\"\n"
                . "\"00045 -- KC Madiun\",\"00061445 - Unung\",\"JG07\",\"100%\",\"15\"",
                200
            )
            : $this->rmSmeResponse($request->url()));

        $controller = new AlmafactsDashboardController();
        $controller->refreshKpiSourceCaches(['rm-sme'], '2026-06');
        $june = $controller->kpi(
            Request::create('/report/dashboard-almafacts/kpi/rm-sme', 'GET', ['periode' => '2026-06']),
            'rm-sme'
        )->getData();

        $controller->refreshKpiSourceCaches(['rm-sme'], '2026-07');
        $july = $controller->kpi(
            Request::create('/report/dashboard-almafacts/kpi/rm-sme', 'GET', ['periode' => '2026-07']),
            'rm-sme'
        )->getData();

        $this->assertSame('1B5U9VxPSjOyLvygqwCKWZssoyf6xoEDs', $june['selectedSheet']['spreadsheet_id']);
        $this->assertSame('Juni 2026', $june['selectedPeriodLabel']);
        $this->assertSame('13s9SkGMC0ShjlEZGog1uBtgLxFY1RqPN5ZvMjzIVRUg', $july['selectedSheet']['spreadsheet_id']);
        $this->assertSame('Juli 2026', $july['selectedPeriodLabel']);
        $this->assertSame('report.almafacts.kpi', $controller->kpi(
            Request::create('/report/dashboard-almafacts/kpi/rm-sme', 'GET', ['periode' => '2026-06']),
            'rm-sme'
        )->name());
        $this->assertSame('report.almafacts.kpi-rm-sme', $controller->kpi(
            Request::create('/report/dashboard-almafacts/kpi/rm-sme', 'GET', ['periode' => '2026-07']),
            'rm-sme'
        )->name());

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '1B5U9VxPSjOyLvygqwCKWZssoyf6xoEDs'));
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '13s9SkGMC0ShjlEZGog1uBtgLxFY1RqPN5ZvMjzIVRUg'));
    }

    public function test_kpi_sheet_replaces_error_cells_and_renders_period_selector(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response(
                "\"KEY PERFORMING INDICATOR\",\"MBM\",\"SCORE\"\n\"MADIUN\",\"NUR\",\"#ERROR!\"",
                200
            ),
        ]);

        $view = $this->kpiView('mbm');
        $data = $view->getData();
        $this->assertSame('-', $data['rows'][0][2]);
        $this->assertSame(['2026-07', '2026-06'], array_keys($data['periodOptions']));

        $this->actingAs(new User([
            'pn' => 'test-kpi-period',
            'name' => 'KPI Period Test',
            'role' => 'admin',
        ]));
        $html = $view->render();
        $this->assertStringContainsString('id="kpi-period-filter"', $html);
        $this->assertStringContainsString('Juli 2026', $html);
        $this->assertStringContainsString('Juni 2026', $html);
        $this->assertStringNotContainsString('#ERROR!', $html);
    }

    public function test_kpi_sticky_header_and_columns_use_runtime_geometry_on_every_viewport(): void
    {
        $source = file_get_contents(resource_path('views/report/almafacts/kpi.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('top: var(--kpi-sticky-header-row-height);', $source);
        $this->assertStringContainsString('left: var(--kpi-sticky-first-column-width) !important;', $source);
        $this->assertStringContainsString('Math.ceil(firstRow.getBoundingClientRect().height)', $source);
        $this->assertStringContainsString("table.style.setProperty('--kpi-sticky-header-row-height'", $source);
        $this->assertStringContainsString("table.style.setProperty('--kpi-sticky-first-column-width'", $source);
        $this->assertStringContainsString("table.querySelector('tbody tr .kpi-sticky-col-0')", $source);
        $this->assertStringContainsString("window.addEventListener('orientationchange', adjustStickyHeaders)", $source);
        $this->assertStringContainsString('new ResizeObserver(adjustStickyHeaders)', $source);
        $this->assertStringNotContainsString('if (window.innerWidth < 768)', $source);
        $this->assertStringNotContainsString('top: 28px; /* Height of row 1 th fallback */', $source);
    }

    public function test_generic_kpi_pages_share_the_rm_sme_visual_and_interaction_contract(): void
    {
        $viewSource = file_get_contents(resource_path('views/report/almafacts/kpi.blade.php'));
        $themeSource = file_get_contents(resource_path('views/report/almafacts/partials/kpi-unified-theme.blade.php'));

        $this->assertIsString($viewSource);
        $this->assertIsString($themeSource);
        $this->assertStringContainsString("@include('report.almafacts.partials.kpi-unified-theme')", $viewSource);
        $this->assertStringContainsString('id="kpi-dashboard"', $viewSource);
        $this->assertStringContainsString('Ruang Analisis', $viewSource);
        $this->assertStringContainsString('id="kpi-table-search"', $viewSource);
        $this->assertStringContainsString("searchInput.addEventListener('input', filterTableRows)", $viewSource);
        $this->assertStringContainsString("panel.querySelectorAll('tbody tr')", $viewSource);
        $this->assertStringContainsString('aria-current="page"', $viewSource);
        $this->assertStringContainsString("'ka-unit' => ['UNIT KERJA', 'UKER']", $viewSource);
        $this->assertStringContainsString("'consumer' => ['PN PENGELOLA SINGLEPN', 'NAMA', 'UKER']", $viewSource);

        $this->assertStringContainsString('min-height: 44px;', $themeSource);
        $this->assertStringContainsString('@media (max-width: 1199.98px)', $themeSource);
        $this->assertStringContainsString('@media (max-width: 991.98px)', $themeSource);
        $this->assertStringContainsString('@media (max-width: 767.98px)', $themeSource);
        $this->assertStringContainsString('@media (max-width: 420px)', $themeSource);
        $this->assertStringContainsString('overflow: hidden;', $themeSource);
        $this->assertStringContainsString(':focus-visible', $themeSource);
    }

    public function test_generic_kpi_summary_uses_the_final_available_score_without_mutating_rows(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response(
                "\"KEY PERFORMING INDICATOR\",\"MBM\",\"SCORE\"\n"
                . "\"MADIUN\",\"NUR\",\"98%\"\n"
                . "\"NGAWI\",\"RINA\",\"92%\"",
                200
            ),
        ]);
        $this->actingAs(new User(['pn' => 'test-kpi-unified', 'name' => 'KPI Unified', 'role' => 'admin']));

        $html = $this->kpiView('mbm')->render();

        $this->assertStringContainsString('Dashboard KPI MBM', $html);
        $this->assertStringContainsString('95,00', $html);
        $this->assertStringContainsString('98,00', $html);
        $this->assertStringContainsString('NUR', $html);
        $this->assertStringContainsString('98%', $html);
        $this->assertStringContainsString('92%', $html);
    }

    public function test_rm_sme_dashboard_has_responsive_layout_contracts(): void
    {
        $source = file_get_contents(resource_path('views/report/almafacts/kpi-rm-sme.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('@media (max-width: 1199.98px)', $source);
        $this->assertStringContainsString('@media (max-width: 991.98px)', $source);
        $this->assertStringContainsString('@media (max-width: 767.98px)', $source);
        $this->assertStringContainsString('@media (max-width: 420px)', $source);
        $this->assertStringContainsString('grid-template-columns: repeat(3, minmax(180px, 1fr));', $source);
        $this->assertStringContainsString('overflow: auto;', $source);
        $this->assertStringContainsString('min-height: 44px;', $source);
        $this->assertStringContainsString('maintainAspectRatio:false', $source);
        $this->assertStringContainsString("document.querySelectorAll('[data-rmsme-view]')", $source);
    }

    private function kpiView(string $sheet): \Illuminate\View\View
    {
        $controller = new AlmafactsDashboardController();
        $refresh = $controller->refreshKpiSourceCaches([$sheet]);
        $this->assertTrue(
            (bool) data_get($refresh, $sheet . '.success', false),
            json_encode($refresh, JSON_PRETTY_PRINT)
        );

        return $controller->kpi(
            Request::create('/report/dashboard-almafacts/kpi/' . $sheet, 'GET'),
            $sheet
        );
    }

    private function rmSmeResponse(string $url)
    {
        if (str_contains($url, 'sheet=Sheet3')) {
            return Http::response(
                "\"NAMA KANCA KONSOL\",\"NAMA UKO\",\"NAMA MANTRI\",\"JG\"\n"
                . "\"00045 -- KC Madiun (Konsolidasi-MB)\",\"00045 -- KC Madiun\",\"00123456 - RM Test\",\"JG07\"",
                200
            );
        }

        $header = '"PERIODE","NAMA KANCA KONSOL","NAMA UKO","NAMA MANTRI","JG","Avg Balance Small","RKA Avg Balance Small","OS Small","RKA OS Small","Jumlah Debitur Small","RKA Jumlah Debitur Small","Downgrade to Kol 2 %","RKA Downgrade to Kol 2 %","Rasio DPK Debitur Kelolaan to Loan SME","RKA Rasio DPK Debitur Kelolaan to Loan SME","% Booking Value Chain Cash Loan","RKA % Booking Value Chain Cash Loan","Product Holding Nasabah Kelolaan","RKA Product Holding Nasabah Kelolaan","POSISI LANCAR","POSISI SML","POSISI NPL"';
        $period = str_contains($url, 'sheet=Sheet2') ? '31 Dec 2026' : '31 Jul 2026';
        $values = [$period, '00045 -- KC Madiun (Konsolidasi-MB)', '00045 -- KC Madiun', '00123456 - RM Test', 'JG07', '120', '100', '220', '200', '12', '10', '0,50%', '1,00%', '80,00%', '75,00%', '5,25', '5,00', '85,00%', '80,00%', '900', '80', '20'];
        $csvRow = implode(',', array_map(static fn (string $value): string => '"' . str_replace('"', '""', $value) . '"', $values));

        return Http::response($header . "\n" . $csvRow, 200);
    }
}
