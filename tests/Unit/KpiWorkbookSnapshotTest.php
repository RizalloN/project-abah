<?php

namespace Tests\Unit;

use App\Http\Controllers\Report\AlmafactsDashboardController;
use App\Models\User;
use App\Services\Reports\KpiWorkbookSnapshotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\View\View;
use Mockery;
use Tests\TestCase;

class KpiWorkbookSnapshotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();
        Http::preventStrayRequests();
        Queue::fake();
        $this->actingAs(new User(['pn' => 'kpi-preview', 'name' => 'KPI Test', 'role' => 'admin']));
    }

    public function test_all_four_sources_default_to_september_and_preserve_every_source_column(): void
    {
        $service = new KpiWorkbookSnapshotService;
        foreach (['mantri' => [464, 44], 'mantri-briguna' => [2, 35], 'ka-unit' => [101, 28], 'mbm' => [12, 24]] as $key => [$count, $columns]) {
            $snapshot = $service->read($key, '2026-09');
            $view = $this->monitorView($key);
            $data = $view->getData();
            $this->assertSame('2026-09', $data['selectedPeriod']);
            $this->assertSame('September 2026', $data['selectedPeriodLabel']);
            $this->assertSame('report.almafacts.kpi', $view->name());
            $this->assertSame($snapshot['rows'], $data['rows']);
            $this->assertCount($count, $data['rows']);
            $this->assertCount($columns, $data['header']);
            $this->assertSame($snapshot['source']['sha256'], $data['summary']['source_sha256']);
            foreach ($data['rows'] as $row) {
                $this->assertCount($columns, $row);
            }
            $html = $view->render();
            $this->assertStringContainsString('Excel · 30 Sep 2026', $html);
            $this->assertStringNotContainsString('Google Sheets live', $html);
            $this->assertNull($data['spreadsheetUrl']);
            $this->assertNull($data['csvUrl']);
        }
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_source_numbers_use_excel_formats_without_recomputing_scores(): void
    {
        $briguna = $this->monitorView('mantri-briguna')->getData();
        $this->assertSame('00390609 - Handoyo Wahyu Nurcahyo', $briguna['rows'][0][7]);
        $this->assertSame('18.73%', $briguna['rows'][0][24]);
        $this->assertSame('6.56', $briguna['rows'][0][25]);
        $this->assertSame('71.06', $briguna['rows'][0][34]);
        $this->assertSame('54.14', $briguna['rows'][1][34]);
        $this->assertSame('', $briguna['rows'][0][0]);
        $this->assertSame('', $briguna['rows'][0][23]);
        $this->assertSame('SERIES BC', strtoupper($briguna['headerGroups'][11]['label']));
        $this->assertSame(13, $briguna['headerGroups'][11]['colspan']);
        $this->assertStringContainsString('Handoyo Wahyu Nurcahyo', $this->monitorView('mantri-briguna')->render());
        $mantri = $this->monitorView('mantri')->getData();
        $this->assertSame('93.04', $mantri['rows'][0][42]);
        $this->assertSame('1', $mantri['rows'][0][43]);
        $ka = $this->monitorView('ka-unit')->getData();
        $this->assertSame('106.90', $ka['rows'][0][26]);
        $this->assertSame('11.000', $ka['rows'][0][7]);
        $mbm = $this->monitorView('mbm')->getData();
        $this->assertSame('99.92%', $mbm['rows'][0][22]);
        $this->assertSame('OS (Bobot 25%)', $mbm['headerGroups'][2]['label']);
    }

    public function test_branch_filter_and_locked_user_apply_to_the_workbook_rows(): void
    {
        $data = $this->monitorView('mantri', ['cabang' => 'MAGETAN'])->getData();
        $this->assertCount(107, $data['rows']);
        $this->assertSame(['MAGETAN'], array_values(array_unique(array_column($data['rows'], 1))));
        $this->actingAs(new User(['pn' => 'kc-test', 'name' => 'KC Test', 'role' => 'user', 'branch_scope' => 'ngawi']));
        $data = $this->monitorView('mantri-briguna', ['cabang' => 'MADIUN'])->getData();
        $this->assertTrue($data['kpiBranchFilter']['locked']);
        $this->assertSame([], $data['rows']);
    }

    public function test_local_refresh_never_queues_remote_sources_or_changes_personnel_references(): void
    {
        $data = $this->monitorView('mbm', ['refresh' => 1])->getData();
        $this->assertCount(12, $data['rows']);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
        $result = (new AlmafactsDashboardController)->refreshKpiSourceCaches(['mantri-briguna'], '2026-09');
        $this->assertTrue($result['mantri-briguna']['success']);
        $this->assertNull($result['mantri-briguna']['reference_sync']);
    }

    public function test_stale_cached_rows_cannot_replace_the_supplied_workbook(): void
    {
        $controller = new AlmafactsDashboardController;
        $method = new \ReflectionMethod($controller, 'kpiSheetCacheKey');
        $key = $method->invoke($controller, 'mantri', '2026-09');
        Cache::put($key, ['header' => ['BO'], 'rows' => [['OLD SOURCE']]], now()->addDay());
        $data = $this->monitorView('mantri')->getData();
        $this->assertCount(464, $data['rows']);
        $this->assertSame('00391586 - Zuni Fitri Yatul Hasanah', $data['rows'][0][7]);
        Http::assertNothingSent();
    }

    public function test_accounting_zero_remains_in_the_summary_score_denominator(): void
    {
        $snapshot = (new KpiWorkbookSnapshotService)->read('mantri-briguna', '2026-09');
        $snapshot['raw_rows'][0][34] = 0;
        $snapshot['rows'][0][34] = '-';
        $mock = Mockery::mock(KpiWorkbookSnapshotService::class)->makePartial();
        $mock->shouldReceive('read')->andReturn($snapshot);
        app()->instance(KpiWorkbookSnapshotService::class, $mock);
        $html = $this->monitorView('mantri-briguna')->render();
        $this->assertStringContainsString('<strong>27,07</strong>', $html);
    }

    public function test_unrelated_kpi_keeps_july_and_briguna_has_no_invented_historical_period(): void
    {
        $data = $this->monitorView('consumer')->getData();
        $this->assertSame('2026-07', $data['selectedPeriod']);
        $this->assertSame(['2026-07', '2026-06'], array_keys($data['periodOptions']));
        $data = $this->monitorView('mantri-briguna', ['periode' => '2026-07'])->getData();
        $this->assertSame('2026-09', $data['selectedPeriod']);
        $this->assertSame(['2026-09'], array_keys($data['periodOptions']));
    }

    private function monitorView(string $key, array $query = []): View
    {
        return (new AlmafactsDashboardController)->kpi(Request::create('/report/dashboard-almafacts/kpi/'.$key, 'GET', $query), $key);
    }
}
