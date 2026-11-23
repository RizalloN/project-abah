<?php

namespace Tests\Unit;

use App\Http\Controllers\Report\AlmafactsDashboardController;
use App\Models\User;
use App\Services\Reports\KpiWorkbookSnapshotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class KpiOctoberWorkbookSnapshotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();
        Http::preventStrayRequests();
        Queue::fake();
        $this->actingAs(new User(['pn' => 'october-kpi', 'name' => 'KPI October', 'role' => 'admin']));
    }

    public function test_four_roles_default_to_october_with_source_rows_and_numeric_metadata_untouched(): void
    {
        $service = new KpiWorkbookSnapshotService;
        foreach (['mbm' => [12, 24], 'rm-mikro' => [14, 32], 'ka-unit' => [101, 28], 'mantri' => [466, 44]] as $role => [$rows, $columns]) {
            $source = json_decode(file_get_contents(resource_path('data/kpi-'.$role.'-2026-10.json')), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame($source, $service->read($role, '2026-10'));
            $view = $this->workbookView($role);
            $data = $view->getData();
            $this->assertSame('2026-10', $data['selectedPeriod']);
            $this->assertSame('Oktober 2026', $data['selectedPeriodLabel']);
            $this->assertSame($source['rows'], $data['rows']);
            $this->assertCount($rows, $data['rows']);
            $this->assertCount($columns, $data['header']);
            $this->assertSame([], $source['source_errors']);
            $this->assertSame($source['source']['sha256'], $data['summary']['source_sha256']);
            $this->assertNull($data['spreadsheetUrl']);
            $this->assertNull($data['csvUrl']);
            $html = $view->render();
            $this->assertStringContainsString('31 Okt 2026', $html);
            foreach ($source['rows'][0] as $formatted) {
                if (trim((string) $formatted) !== '') {
                    $this->assertStringContainsString(e((string) $formatted), $html);
                }
            }
            $this->assertSame($source, $service->read($role, '2026-10'));
        }
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_september_workbooks_remain_explicitly_accessible_and_briguna_default_does_not_move(): void
    {
        $service = new KpiWorkbookSnapshotService;
        foreach (['mbm', 'ka-unit', 'mantri', 'mantri-briguna'] as $role) {
            $source = $service->read($role, '2026-09');
            $data = $this->workbookView($role, ['periode' => '2026-09'])->getData();
            $this->assertSame('2026-09', $data['selectedPeriod']);
            $this->assertSame($source['rows'], $data['rows']);
            $this->assertSame($source['source']['sha256'], $data['summary']['source_sha256']);
        }
        $briguna = $this->workbookView('mantri-briguna')->getData();
        $this->assertSame('2026-09', $briguna['selectedPeriod']);
        $this->assertSame(['2026-09'], array_keys($briguna['periodOptions']));
        $this->assertFalse($service->available('mantri-briguna', '2026-10'));
        $this->assertFalse($service->available('rm-mikro', '2026-09'));
        $this->assertSame('2026-09', $this->workbookView('mantri-briguna', ['periode' => '2026-10'])->getData()['selectedPeriod']);
        Http::assertNothingSent();
    }

    public function test_october_bypasses_old_cached_payload_and_refresh_preserves_historical_cache_and_reference_sources(): void
    {
        $controller = new AlmafactsDashboardController;
        $cacheKey = new \ReflectionMethod($controller, 'kpiSheetCacheKey');
        foreach (['mbm', 'rm-mikro', 'ka-unit', 'mantri'] as $role) {
            $key = $cacheKey->invoke($controller, $role, '2026-10');
            $old = ['header' => ['BO', 'SCORE'], 'rows' => [['OLD SOURCE', '0']]];
            Cache::put($key, $old, now()->addDay());
            $historicalKey = $cacheKey->invoke($controller, $role, '2026-07');
            Cache::put($historicalKey, $old, now()->addDay());
            $data = $this->workbookView($role, ['refresh' => 1])->getData();
            $this->assertSame((new KpiWorkbookSnapshotService)->read($role, '2026-10')['rows'], $data['rows']);
            $this->assertSame($old, Cache::get($key));
            $result = $controller->refreshKpiSourceCaches([$role], '2026-10');
            $this->assertTrue($result[$role]['success']);
            $this->assertNull($result[$role]['reference_sync']);
            $this->assertSame($data['rows'], Cache::get($key)['rows']);
            $this->assertSame($old, Cache::get($historicalKey));
        }
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_october_branch_filters_and_user_locks_cannot_expose_another_branch(): void
    {
        foreach (['mbm' => 3, 'rm-mikro' => 3, 'ka-unit' => 25, 'mantri' => 114] as $role => $ngawiCount) {
            $this->actingAs(new User(['pn' => 'october-admin', 'role' => 'admin']));
            $all = $this->workbookView($role)->getData();
            $branch = collect($all['kpiBranchFilter']['options'])->firstWhere('value', '!=', 'all')['value'];
            $filtered = $this->workbookView($role, ['cabang' => $branch])->getData();
            $this->assertNotEmpty($filtered['rows']);
            $this->assertLessThan(count($all['rows']), count($filtered['rows']));
            $this->assertSame($branch, $filtered['kpiBranchFilter']['selected']);
            $this->actingAs(new User(['pn' => 'october-locked', 'role' => 'user', 'branch_scope' => 'ngawi']));
            $locked = $this->workbookView($role, ['cabang' => 'MADIUN'])->getData();
            $natural = $this->workbookView($role)->getData();
            $this->assertTrue($locked['kpiBranchFilter']['locked']);
            $this->assertNotEmpty($locked['rows']);
            $this->assertCount($ngawiCount, $locked['rows']);
            $this->assertStringContainsString('NGAWI', strtoupper($locked['kpiBranchFilter']['selected']));
            $this->assertSame($natural['rows'], $locked['rows']);
            $this->assertSame($natural['kpiBranchFilter']['selected'], $locked['kpiBranchFilter']['selected']);
        }
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_rm_mikro_historical_july_and_june_payloads_are_still_selected_by_their_original_period(): void
    {
        $controller = new AlmafactsDashboardController;
        $method = new \ReflectionMethod($controller, 'kpiSheetCacheKey');
        foreach (['2026-07', '2026-06'] as $period) {
            $payload = ['header' => ['BO', 'SCORE'], 'rows' => [['NGAWI', '42.00']], 'fetched_at' => now()->toDateTimeString(), 'summary' => ['row_count' => 1], 'error' => null];
            $key = $method->invoke($controller, 'rm-mikro', $period);
            Cache::put($key, $payload, now()->addDay());
            $data = $this->workbookView('rm-mikro', ['periode' => $period])->getData();
            $this->assertSame($period, $data['selectedPeriod']);
            $this->assertSame($payload['rows'], $data['rows']);
            $this->assertSame($payload, Cache::get($key));
            $this->assertContains($period, array_keys($data['periodOptions']));
        }
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_mantri_cached_blank_formula_results_remain_null_and_blank_instead_of_becoming_zeroes(): void
    {
        $snapshot = (new KpiWorkbookSnapshotService)->read('mantri', '2026-10');
        $this->assertSame([], $snapshot['source_missing_formula_caches']);
        $this->assertCount(656, $snapshot['source_blank_formula_results']);
        $displayRows = $this->workbookView('mantri')->getData()['rows'];
        foreach ($snapshot['source_blank_formula_results'] as $coordinate) {
            [$column, $sourceRow] = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::coordinateFromString($coordinate);
            $row = array_search((int) $sourceRow, $snapshot['source_rows'], true);
            $this->assertNotFalse($row);
            $index = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($column) - 1;
            $this->assertNull($snapshot['raw_rows'][$row][$index], $coordinate);
            $this->assertSame('', $displayRows[$row][$index], $coordinate);
        }
        Http::assertNothingSent();
    }

    public function test_independently_audited_source_cells_keep_their_original_numeric_and_excel_display_values(): void
    {
        $anchors = [
            'mbm' => [[0, 2, 0.9967271596050129, '99.67%'], [1, 22, 1.0169958576984877, '101.70%'], [1, 23, 1, '1']],
            'rm-mikro' => [[0, 18, 1.1, '110.00%'], [0, 30, 1.0662219680374458, '106.62%'], [0, 31, 1, '1'], [0, 17, null, '']],
            'ka-unit' => [[0, 4, 1.1, '110.00%'], [0, 5, 11, '11.00'], [0, 26, 100.46757131269467, '100.47'], [0, 27, 16, '16']],
            'mantri' => [[0, 24, 0.07433453311111111, '7.43%'], [0, 25, 1.4866906622222222, '1.49'], [0, 42, 80.20710386167278, '80.21'], [0, 43, 91, '91']],
        ];
        foreach ($anchors as $role => $cells) {
            $snapshot = (new KpiWorkbookSnapshotService)->read($role, '2026-10');
            $rows = $this->workbookView($role)->getData()['rows'];
            foreach ($cells as [$row, $column, $raw, $display]) {
                $this->assertSame($raw, $snapshot['raw_rows'][$row][$column], $role.' raw '.$row.':'.$column);
                $this->assertSame($display, $rows[$row][$column], $role.' display '.$row.':'.$column);
            }
        }
    }

    private function workbookView(string $role, array $query = []): \Illuminate\View\View
    {
        return (new AlmafactsDashboardController)->kpi(Request::create('/report/dashboard-almafacts/kpi/'.$role, 'GET', $query), $role);
    }
}
