<?php

namespace Tests\Unit;

use App\Http\Controllers\PrognosaWeeklyController;
use App\Models\User;
use App\Services\Presentation\PresentationPrognosaWeeklyService;
use App\Support\DashboardHarianSnapshotService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PrognosaWeeklySpreadsheetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_weekly_prognosa_reads_the_official_area_sheet(): void
    {
        $fixtures = [
            'Area 6' => $this->weeklyPrognosaCsvFixture(1_000),
        ];
        Http::fake(fn ($request) => Http::response(
            $fixtures[$this->requestedSheetName($request->url())] ?? '',
            isset($fixtures[$this->requestedSheetName($request->url())]) ? 200 : 404
        ));
        $this->actingAs(new User(['pn' => 'test-prognosa-area', 'name' => 'Prognosa Area']));

        $view = $this->weeklyController(
            '2026-08-19',
            $this->weeklyDailyPayload('2026-08-19'),
            ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo']
        )->index(
            Request::create('/prognosa/weekly', 'GET', ['sheet' => 'area'])
        );
        $data = $view->getData();

        $this->assertSame('area', $data['selectedSheetKey']);
        $this->assertFalse($data['isLocked']);
        $this->assertSame('Area 6', $data['selectedSheet']['sheet']);
        $this->assertSame('Monitoring PTP - Konsolidasi Area 6', $data['title']);
        $this->assertSame('19 Agu 26', $data['latestDate']);
        $this->assertSame(3, $data['activeForecastWeek']);
        $this->assertSame('Week 3', $data['latestForecastLabel']);
        $this->assertSame(
            ['Indikator', 'Posisi', 'Prognosa', 'Delta', 'RKA Agu 26', 'RKA Des 26', 'Sisa Run Off Update'],
            array_column($data['headerGroups'], 'label')
        );
        $this->assertSame([2, 7, 1, 1, 3, 3, 2], array_column($data['headerGroups'], 'colspan'));
        $this->assertSame(['Area 6'], $data['sourceSheets']);
        $this->assertSame('YOY', $data['headerColumns'][2]['label']);
        $this->assertSame('31 Agu 25', $data['headerColumns'][2]['detail']);
        $this->assertSame('19 Agu 26', $data['headerColumns'][8]['detail']);
        $this->assertSame('Week 3', $data['headerColumns'][9]['label']);
        $this->assertSame('1.100', data_get($data, 'rows.0.cells.2.value'));
        $this->assertSame('3.000', data_get($data, 'rows.0.cells.8.value'));
        $this->assertSame('2.750', data_get($data, 'rows.0.cells.9.value'));
        $this->assertSame('250', data_get($data, 'rows.0.cells.10.value'));
        $this->assertSame('4.000', data_get($data, 'rows.0.cells.11.value'));
        $this->assertSame('(1.000)', data_get($data, 'rows.0.cells.12.value'));
        $this->assertSame('75,00%', data_get($data, 'rows.0.cells.13.value'));
        $this->assertSame('bad', data_get($data, 'rows.0.cells.13.tone'));
        $this->assertSame('5.000', data_get($data, 'rows.0.cells.14.value'));
        $this->assertSame('(2.000)', data_get($data, 'rows.0.cells.15.value'));
        $this->assertSame('60,00%', data_get($data, 'rows.0.cells.16.value'));
        $this->assertSame('74.105', data_get($data, 'rows.0.cells.17.value'));
        $this->assertSame('161.996', data_get($data, 'rows.0.cells.18.value'));
        $this->assertSame('68.244', data_get($data, 'rows.1.cells.17.value'));
        $this->assertSame('145.851', data_get($data, 'rows.1.cells.18.value'));
        $this->assertSame('714', data_get($data, 'rows.2.cells.17.value'));
        $this->assertSame('8.617', data_get($data, 'rows.2.cells.18.value'));
        $this->assertSame('5.147', data_get($data, 'rows.3.cells.17.value'));
        $this->assertSame('7.528', data_get($data, 'rows.3.cells.18.value'));
        $this->assertSame('', data_get($data, 'rows.4.cells.17.value'));
        $this->assertSame('good', data_get($data, 'rows.4.cells.12.tone'));
        $this->assertSame('133,33%', data_get($data, 'rows.4.cells.13.value'));
        $this->assertSame('23 Agu 26', $data['headerColumns'][17]['detail']);

        $html = $view->render();
        $this->assertStringContainsString('<thead>', $html);
        $this->assertStringContainsString('data-group="identity"', $html);
        $this->assertStringContainsString('class="prognosa-summary"', $html);
        $this->assertStringContainsString('class="prognosa-table-wrap"', $html);
        $this->assertStringContainsString('scope="colgroup"', $html);
        $this->assertStringContainsString('data-column-mode="all"', $html);
        $this->assertStringContainsString('data-column-mode="rka"', $html);
        $this->assertStringContainsString('data-column-mode="runoff"', $html);
        $this->assertStringContainsString('data-report-section="pinjaman"', $html);
        $this->assertStringContainsString('data-group="position"', $html);
        $this->assertStringContainsString('Posisi - target', $html);
        $this->assertStringNotContainsString('12.00 WIB', $html);

        Http::assertSent(fn ($request) => $this->requestedSheetName($request->url()) === 'Area 6');
        Http::assertSentCount(1);
    }

    public function test_weekly_prognosa_locks_sheet_to_authenticated_branch(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response($this->weeklyPrognosaCsvFixture(125), 200),
        ]);
        $this->actingAs(new User(['pn' => 'test-prognosa', 'branch_scope' => 'madiun']));

        $view = $this->weeklyController(
            '2026-08-06',
            $this->weeklyDailyPayload('2026-08-06'),
            'KC Madiun'
        )->index(
            Request::create('/prognosa/weekly', 'GET', ['sheet' => 'area'])
        );
        $data = $view->getData();

        $this->assertTrue($data['isLocked']);
        $this->assertSame('madiun', $data['selectedSheetKey']);
        $this->assertSame(['madiun'], array_keys($data['sheetOptions']));
        $this->assertSame(1, $data['activeForecastWeek']);
        $this->assertSame('Week 1', $data['latestForecastLabel']);
        $this->assertSame('3.000', data_get($data, 'rows.0.cells.8.value'));
        $this->assertSame('281', data_get($data, 'rows.0.cells.9.value'));
        $this->assertSame('19.118', data_get($data, 'rows.0.cells.17.value'));
        $this->assertSame('38.288', data_get($data, 'rows.0.cells.18.value'));
        $html = $view->render();
        $this->assertSame(1, preg_match('/<select[^>]+id="prognosa-sheet"[^>]*>/', $html, $matches));
        $this->assertStringContainsString('disabled', $matches[0]);
        $this->assertStringNotContainsString('onchange=', $matches[0]);
        Http::assertSent(fn ($request) => $this->requestedSheetName($request->url()) === 'KC Madiun');
        Http::assertSentCount(1);
    }

    public function test_weekly_prognosa_allows_an_explicit_week_override(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response($this->weeklyPrognosaCsvFixture(125), 200),
        ]);
        $this->actingAs(new User(['pn' => 'test-prognosa-week', 'branch_scope' => 'madiun']));

        $view = $this->weeklyController(
            '2026-08-19',
            $this->weeklyDailyPayload('2026-08-19'),
            'KC Madiun'
        )->index(Request::create('/prognosa/weekly', 'GET', ['week' => '4']));
        $data = $view->getData();

        $this->assertSame(4, $data['activeForecastWeek']);
        $this->assertSame('Week 4', $data['latestForecastLabel']);
        $this->assertSame('Week 4', $data['headerColumns'][9]['label']);
        $this->assertSame('375', data_get($data, 'rows.0.cells.9.value'));
        $this->assertSame('2.625', data_get($data, 'rows.0.cells.10.value'));

        $html = $view->render();
        $this->assertStringContainsString('name="week" value="4"', $html);
        $this->assertStringContainsString('data-week-selector-trigger', $html);
        $this->assertStringContainsString('data-week-selector-surface', $html);
        $this->assertStringContainsString('id="prognosa-week-modal"', $html);
        $this->assertStringContainsString('aria-current="true"', $html);
    }

    public function test_weekly_prognosa_reads_week_five_from_the_new_shifted_layout(): void
    {
        Carbon::setTestNow('2026-09-27 12:00:00');
        Http::fake([
            'docs.google.com/*' => Http::response($this->weeklyPrognosaCsvFixture(125, false, true), 200),
        ]);
        $this->actingAs(new User(['pn' => 'test-prognosa-week-five', 'branch_scope' => 'madiun']));

        try {
            $view = $this->weeklyController(
                '2026-08-31',
                $this->weeklyDailyPayload('2026-08-31'),
                'KC Madiun'
            )->index(Request::create('/prognosa/weekly', 'GET'));
            $data = $view->getData();
            $html = $view->render();
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame([1, 2, 3, 4, 5], $data['availableForecastWeeks']);
        $this->assertSame(5, $data['activeForecastWeek']);
        $this->assertSame('Week 5', $data['latestForecastLabel']);
        $this->assertSame('406', data_get($data, 'rows.0.cells.9.value'));
        $this->assertStringContainsString('name="week" value="5"', $html);
        $this->assertStringContainsString(
            '1qta-IbVG5edMAy36Ku-GCvs_sesfmmXt',
            $data['spreadsheetUrl']
        );
    }

    public function test_weekly_prognosa_reads_dashboard_layout_with_indicator_in_column_a(): void
    {
        Carbon::setTestNow('2026-09-06 12:00:00');
        Http::fake([
            'docs.google.com/*' => Http::response($this->weeklyDashboardCsvFixture(), 200),
        ]);
        $this->actingAs(new User(['pn' => 'test-prognosa-dashboard-layout', 'branch_scope' => 'madiun']));

        try {
            $view = $this->weeklyController(
                '2026-08-31',
                $this->weeklyDailyPayload('2026-08-31'),
                'KC Madiun'
            )->index(Request::create('/prognosa/weekly', 'GET'));
            $data = $view->getData();
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame([1, 2, 3, 4, 5], $data['availableForecastWeeks']);
        $this->assertSame(2, $data['activeForecastWeek']);
        $this->assertSame('1. Simpanan', data_get($data, 'rows.0.label'));
        $this->assertSame('222', data_get($data, 'rows.0.cells.9.value'));
        Http::assertSent(fn ($request) => $this->requestedSheetName($request->url()) === 'KC Madiun');
    }

    public function test_weekly_prognosa_ignores_an_invalid_week_override(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response($this->weeklyPrognosaCsvFixture(125), 200),
        ]);
        $this->actingAs(new User(['pn' => 'test-prognosa-invalid-week', 'branch_scope' => 'madiun']));

        $view = $this->weeklyController(
            '2026-08-19',
            $this->weeklyDailyPayload('2026-08-19'),
            'KC Madiun'
        )->index(Request::create('/prognosa/weekly', 'GET', ['week' => '9']));

        $this->assertSame(3, $view->getData()['activeForecastWeek']);
        $this->assertSame('344', data_get($view->getData(), 'rows.0.cells.9.value'));
    }

    public function test_weekly_position_is_refreshed_while_forecast_target_stays_cached(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response($this->weeklyPrognosaCsvFixture(125), 200),
        ]);
        $this->actingAs(new User(['pn' => 'test-prognosa-cache', 'branch_scope' => 'madiun']));

        $service = Mockery::mock(DashboardHarianSnapshotService::class);
        $service->shouldReceive('resolveEffectivePeriod')
            ->twice()
            ->with(null)
            ->andReturn('2026-08-19');
        $service->shouldReceive('buildDashboardPayload')
            ->twice()
            ->with('2026-08-19', null, 'KC Madiun', null)
            ->andReturn(
                $this->weeklyDailyPayload('2026-08-19', 3_000),
                $this->weeklyDailyPayload('2026-08-19', 3_100)
            );
        $controller = new PrognosaWeeklyController($service);

        $first = $controller->index(Request::create('/prognosa/weekly', 'GET'));
        $second = $controller->index(Request::create('/prognosa/weekly', 'GET'));

        $this->assertSame('3.000', data_get($first->getData(), 'rows.0.cells.8.value'));
        $this->assertSame('3.100', data_get($second->getData(), 'rows.0.cells.8.value'));
        $this->assertSame('19.118', data_get($first->getData(), 'rows.0.cells.17.value'));
        $this->assertSame('19.118', data_get($second->getData(), 'rows.0.cells.17.value'));
        Http::assertSentCount(1);
    }

    public function test_weekly_prognosa_uses_scoped_workbook_fallback_with_real_report_coordinates(): void
    {
        $path = $this->weeklyPrognosaWorkbookFixture();
        $contents = file_get_contents($path);

        try {
            Http::fake(function ($request) use ($contents) {
                return str_contains($request->url(), '/export?format=xlsx')
                    ? Http::response($contents, 200, [
                        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    : Http::response('', 503);
            });
            $this->actingAs(new User(['pn' => 'test-prognosa-workbook', 'name' => 'Prognosa Workbook']));

            $view = $this->weeklyController(
                '2026-08-31',
                $this->weeklyDailyPayload('2026-08-31'),
                ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo']
            )->index(
                Request::create('/prognosa/weekly', 'GET', ['sheet' => 'area'])
            );
            $data = $view->getData();

            $this->assertSame('31 Agu 26', $data['latestDate']);
            $this->assertSame(4, $data['activeForecastWeek']);
            $this->assertSame(
                ['Indikator', 'Posisi', 'Prognosa', 'Delta', 'RKA Agu 26', 'RKA Des 26', 'Sisa Run Off Update'],
                array_column($data['headerGroups'], 'label')
            );
            $this->assertSame('YOY', $data['headerColumns'][2]['label']);
            $this->assertSame('31 Agu 26', $data['headerColumns'][8]['detail']);
            $this->assertSame('Week 4', $data['headerColumns'][9]['label']);
            $this->assertSame('1.100', data_get($data, 'rows.0.cells.2.value'));
            $this->assertSame('3.000', data_get($data, 'rows.0.cells.8.value'));
            $this->assertSame('3.000', data_get($data, 'rows.0.cells.9.value'));
            $this->assertSame('0', data_get($data, 'rows.0.cells.10.value'));

            $html = $view->render();
            $this->assertStringContainsString('data-group="forecast"', $html);
            $this->assertStringContainsString('data-group-header="forecast"', $html);
            $this->assertStringNotContainsString('UPDATE POSISI', strtoupper($html));
        } finally {
            @unlink($path);
        }
    }

    public function test_weekly_prognosa_view_keeps_responsive_sticky_table_guardrails(): void
    {
        $source = file_get_contents(resource_path('views/report/prognosa-weekly.blade.php'));

        $this->assertStringContainsString("@section('styles')", $source);
        $this->assertStringNotContainsString("@push('styles')", $source);
        $this->assertStringContainsString('.prognosa-col-no', $source);
        $this->assertStringContainsString('.prognosa-col-indicator', $source);
        $this->assertStringContainsString('.prognosa-group-identity', $source);
        $this->assertStringContainsString('z-index: 45', $source);
        $this->assertStringContainsString('data-column-mode="{{ $key }}"', $source);
        $this->assertStringContainsString('data-report-section="{{ $key }}"', $source);
        $this->assertStringContainsString('window.requestAnimationFrame', $source);
        $this->assertStringContainsString('@media (max-width: 1199.98px)', $source);
        $this->assertStringContainsString('@media (max-width: 991.98px)', $source);
        $this->assertStringContainsString('@media (max-width: 767.98px)', $source);
        $this->assertStringContainsString('scrollbar-gutter: stable', $source);
        $this->assertStringContainsString('.prognosa-table tbody td[data-group="position"]', $source);
        $this->assertStringContainsString('.prognosa-value--good', $source);
        $this->assertStringContainsString('prognosa-column-detail', $source);
        $this->assertStringContainsString('data-group="{{ $column[\'group\'] }}"', $source);
        $this->assertStringContainsString("group === 'rka_current'", $source);
        $this->assertStringContainsString("group === 'runoff'", $source);
        $this->assertStringContainsString("surface.addEventListener('dblclick'", $source);
        $this->assertStringContainsString("event.key === 'Escape'", $source);
        $this->assertStringContainsString('aria-modal="true"', $source);
        $this->assertStringNotContainsString('class="prognosa-meta"', $source);
        $this->assertStringNotContainsString('Sumber Posisi', $source);
        $this->assertStringNotContainsString('<p>{{ $title }}</p>', $source);
        $this->assertStringNotContainsString('linear-gradient', $source);
    }

    public function test_weekly_prognosa_displays_dash_when_rka_is_not_available(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response($this->weeklyPrognosaCsvFixture(125), 200),
        ]);
        $this->actingAs(new User(['pn' => 'test-prognosa-no-rka', 'branch_scope' => 'madiun']));

        $view = $this->weeklyController(
            '2026-08-19',
            $this->weeklyDailyPayload('2026-08-19', 3_000, 0, 0),
            'KC Madiun'
        )->index(Request::create('/prognosa/weekly', 'GET'));

        foreach (range(11, 16) as $columnIndex) {
            $this->assertSame('', data_get($view->getData(), "rows.0.cells.{$columnIndex}.value"));
        }
    }

    public function test_weekly_prognosa_uses_complete_read_only_run_off_snapshot_for_every_scope(): void
    {
        Http::fake([
            'docs.google.com/*' => Http::response($this->weeklyPrognosaCsvFixture(125, true), 200),
        ]);
        $snapshots = [
            'area' => [
                'scope' => ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'],
                'values' => [
                    'PINJAMAN' => ['74.105', '161.996'],
                    'MICRO' => ['68.244', '145.851'],
                    'TOTAL KUPEDES KUR KPP BRIGUNA' => ['68.244', '145.851'],
                    'KUPEDES' => ['20.400', '54.686'],
                    'BRIGUNA MIKRO' => ['442', '549'],
                    'KUR MIKRO' => ['45.900', '80.989'],
                    'KREDIT MIKRO - KPP' => ['273', '826'],
                    'KUR KECIL' => ['1.229', '8.801'],
                    'RITEL' => ['5.861', '16.145'],
                    'KECIL KOMERSIAL' => ['714', '8.617'],
                    'CONSUMER' => ['5.147', '7.528'],
                    'BRIGUNA RITEL' => ['4.490', '6.555'],
                    'KPR' => ['657', '973'],
                ],
            ],
            'madiun' => [
                'scope' => 'KC Madiun',
                'values' => [
                    'PINJAMAN' => ['19.118', '38.288'],
                    'MICRO' => ['16.406', '31.070'],
                    'TOTAL KUPEDES KUR KPP BRIGUNA' => ['16.406', '31.070'],
                    'KUPEDES' => ['4.583', '10.128'],
                    'BRIGUNA MIKRO' => ['166', '203'],
                    'KUR MIKRO' => ['11.291', '18.605'],
                    'KREDIT MIKRO - KPP' => ['12', '29'],
                    'KUR KECIL' => ['354', '2.104'],
                    'RITEL' => ['2.712', '7.218'],
                    'KECIL KOMERSIAL' => ['243', '3.498'],
                    'CONSUMER' => ['2.469', '3.720'],
                    'BRIGUNA RITEL' => ['1.894', '2.852'],
                    'KPR' => ['575', '869'],
                ],
            ],
            'magetan' => [
                'scope' => 'KC Magetan',
                'values' => [
                    'PINJAMAN' => ['14.417', '33.640'],
                    'MICRO' => ['13.332', '31.434'],
                    'TOTAL KUPEDES KUR KPP BRIGUNA' => ['13.332', '31.434'],
                    'KUPEDES' => ['5.092', '12.600'],
                    'BRIGUNA MIKRO' => ['129', '172'],
                    'KUR MIKRO' => ['7.811', '16.935'],
                    'KREDIT MIKRO - KPP' => ['70', '169'],
                    'KUR KECIL' => ['230', '1.558'],
                    'RITEL' => ['1.085', '2.206'],
                    'KECIL KOMERSIAL' => ['104', '768'],
                    'CONSUMER' => ['981', '1.438'],
                    'BRIGUNA RITEL' => ['974', '1.427'],
                    'KPR' => ['7', '11'],
                ],
            ],
            'ngawi' => [
                'scope' => 'KC Ngawi',
                'values' => [
                    'PINJAMAN' => ['15.634', '48.062'],
                    'MICRO' => ['14.643', '44.596'],
                    'TOTAL KUPEDES KUR KPP BRIGUNA' => ['14.643', '44.596'],
                    'KUPEDES' => ['5.704', '22.804'],
                    'BRIGUNA MIKRO' => ['86', '85'],
                    'KUR MIKRO' => ['8.532', '19.214'],
                    'KREDIT MIKRO - KPP' => ['82', '251'],
                    'KUR KECIL' => ['239', '2.242'],
                    'RITEL' => ['991', '3.466'],
                    'KECIL KOMERSIAL' => ['114', '2.231'],
                    'CONSUMER' => ['877', '1.235'],
                    'BRIGUNA RITEL' => ['873', '1.230'],
                    'KPR' => ['4', '4'],
                ],
            ],
            'ponorogo' => [
                'scope' => 'KC Ponorogo',
                'values' => [
                    'PINJAMAN' => ['24.936', '42.005'],
                    'MICRO' => ['23.863', '38.751'],
                    'TOTAL KUPEDES KUR KPP BRIGUNA' => ['23.863', '38.751'],
                    'KUPEDES' => ['5.021', '9.153'],
                    'BRIGUNA MIKRO' => ['61', '89'],
                    'KUR MIKRO' => ['18.266', '26.235'],
                    'KREDIT MIKRO - KPP' => ['109', '377'],
                    'KUR KECIL' => ['406', '2.897'],
                    'RITEL' => ['1.073', '3.254'],
                    'KECIL KOMERSIAL' => ['253', '2.120'],
                    'CONSUMER' => ['820', '1.134'],
                    'BRIGUNA RITEL' => ['749', '1.046'],
                    'KPR' => ['71', '89'],
                ],
            ],
        ];

        foreach ($snapshots as $branchKey => $snapshot) {
            $this->actingAs(new User([
                'pn' => 'test-prognosa-runoff-'.$branchKey,
                'branch_scope' => $branchKey === 'area' ? 'area6' : $branchKey,
            ]));

            $view = $this->weeklyController(
                '2026-08-23',
                $this->weeklyDailyPayload('2026-08-23'),
                $snapshot['scope']
            )->index(Request::create('/prognosa/weekly', 'GET', ['sheet' => $branchKey]));
            $rowsByLabel = collect($view->getData()['rows'])->keyBy('label');

            foreach ($snapshot['values'] as $label => [$accounts, $amount]) {
                $this->assertSame($accounts, data_get($rowsByLabel->get($label), 'cells.17.value'), "{$branchKey} {$label} deb");
                $this->assertSame($amount, data_get($rowsByLabel->get($label), 'cells.18.value'), "{$branchKey} {$label} nominal");
            }
            $this->assertSame('', data_get($rowsByLabel->get('SML'), 'cells.17.value'));
            $this->assertSame('', data_get($rowsByLabel->get('NPL'), 'cells.18.value'));
            $this->assertSame('23 Agu 26', data_get($view->getData(), 'headerColumns.17.detail'));
        }
    }

    public function test_presentation_uses_latest_written_week_from_workbook_header(): void
    {
        $path = $this->presentationPrognosaFixture();

        try {
            $payload = app(PresentationPrognosaWeeklyService::class)->parseWorkbook($path);
        } finally {
            @unlink($path);
        }

        $this->assertTrue((bool) data_get($payload, 'meta.available'));
        $this->assertSame('2026-07-25', data_get($payload, 'meta.forecast_date'));
        $this->assertSame('2026-07-24', data_get($payload, 'meta.position_date'));
        $this->assertSame('W4', data_get($payload, 'meta.week_label'));
        $this->assertSame(
            14_144_099_000_000.0,
            data_get($payload, 'scopes.area6.metrics.simpanan.value')
        );
        $this->assertSame(
            4_188_553_000_000.0,
            data_get($payload, ['scopes', 'KC MADIUN', 'metrics', 'simpanan', 'value'])
        );
        $this->assertSame(
            2_034_941_000_000.0,
            data_get($payload, 'scopes.area6.metrics.sml.value')
        );
        $this->assertSame(
            741_761_000_000.0,
            data_get($payload, 'scopes.area6.metrics.npl.value')
        );
    }

    public function test_presentation_falls_back_to_last_local_workbook_when_drive_is_unavailable(): void
    {
        $path = $this->presentationPrognosaFixture();
        config(['services.presentation_prognosa.local_path' => $path]);
        Http::fake([
            'docs.google.com/*' => Http::response('', 503),
        ]);

        try {
            $payload = app(PresentationPrognosaWeeklyService::class)->payload(true);
        } finally {
            @unlink($path);
        }

        $this->assertTrue((bool) data_get($payload, 'meta.available'));
        $this->assertTrue((bool) data_get($payload, 'meta.stale'));
        $this->assertSame('local-workbook', data_get($payload, 'meta.fallback'));
        $this->assertSame('W4', data_get($payload, 'meta.week_label'));
        $this->assertSame('2026-07-25', data_get($payload, 'meta.forecast_date'));
        $this->assertSame(
            14_144_099_000_000.0,
            data_get($payload, 'scopes.area6.metrics.simpanan.value')
        );
    }

    public function test_weekly_prognosa_aggregates_office_detail_rows_for_branch_with_sub_offices(): void
    {
        $controller = app(PrognosaWeeklyController::class);
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('aggregateDailyMetric');
        $method->setAccessible(true);

        $dailyRowsByKey = [
            'kecil_os__office_detail__kc-madiun-detail' => [
                'values' => ['current' => 400_000_000_000.0, 'rka' => 450_000_000_000.0],
            ],
            'kecil_os__office_detail__kcp-caruban' => [
                'values' => ['current' => 150_000_000_000.0, 'rka' => 160_000_000_000.0],
            ],
            'kecil_os__office_detail__kcp-dolopo' => [
                'values' => ['current' => 70_000_000_000.0, 'rka' => 75_000_000_000.0],
            ],
            'giro_ritel__office_detail__kc-madiun-detail' => [
                'values' => ['current' => 100_000_000_000.0],
            ],
            'giro_ritel__office_detail__kcp-caruban' => [
                'values' => ['current' => 50_000_000_000.0],
            ],
        ];

        // Should aggregate the 3 kecil_os office detail rows when kecil_os top level key is absent
        $kecilCurrent = $method->invoke($controller, $dailyRowsByKey, ['kecil_os'], 'current');
        $this->assertEquals(620_000_000_000.0, $kecilCurrent);

        $kecilRka = $method->invoke($controller, $dailyRowsByKey, ['kecil_os'], 'rka');
        $this->assertEquals(685_000_000_000.0, $kecilRka);

        // Should aggregate giro_ritel office detail rows
        $giroCurrent = $method->invoke($controller, $dailyRowsByKey, ['giro_ritel'], 'current');
        $this->assertEquals(150_000_000_000.0, $giroCurrent);

        // If exact key exists, prefer exact key over detail rows
        $dailyRowsByKeyWithExact = $dailyRowsByKey;
        $dailyRowsByKeyWithExact['kecil_os'] = [
            'values' => ['current' => 999.0],
        ];
        $exactCurrent = $method->invoke($controller, $dailyRowsByKeyWithExact, ['kecil_os'], 'current');
        $this->assertEquals(999.0, $exactCurrent);
    }

    private function weeklyController(
        string $period,
        array $dailyPayload,
        array|string $expectedScope
    ): PrognosaWeeklyController {
        $service = Mockery::mock(DashboardHarianSnapshotService::class);
        $service->shouldReceive('resolveEffectivePeriod')
            ->once()
            ->with(null)
            ->andReturn($period);
        $service->shouldReceive('buildDashboardPayload')
            ->once()
            ->with($period, null, $expectedScope, null)
            ->andReturn($dailyPayload);

        return new PrognosaWeeklyController($service);
    }

    private function weeklyDailyPayload(
        string $period,
        float $currentMillions = 3_000,
        float $rkaMillions = 4_000,
        float $rkaDecemberMillions = 5_000
    ): array {
        $metricKeys = [
            'total_os_non_commercial',
            'micro_os',
            'briguna_mikro_os',
            'kupedes_os',
            'kur_mikro_os',
            'kur_kecil_os',
            'kur_kpp_os',
            'sme_os',
            'consumer_os',
            'kecil_os',
            'briguna_konsumer_os',
            'kpr_os',
            'total_sml_abs_non_commercial',
            'micro_sml',
            'briguna_mikro_sml',
            'kupedes_sml',
            'kur_mikro_sml',
            'kur_kecil_sml',
            'kur_kpp_sml',
            'sme_sml',
            'consumer_sml',
            'kecil_sml',
            'briguna_konsumer_sml',
            'kpr_sml',
            'total_npl_abs_non_commercial',
            'micro_npl',
            'briguna_mikro_npl',
            'kupedes_npl',
            'kur_mikro_npl',
            'kur_kecil_npl',
            'kur_kpp_npl',
            'sme_npl',
            'consumer_npl',
            'kecil_npl',
            'briguna_konsumer_npl',
            'kpr_npl',
            'total_simpanan',
            'simpanan_ritel',
            'simpanan_mikro',
            'simpanan_wholesale',
            'giro_ritel',
            'giro_mikro',
            'giro_wholesale',
            'tabungan_ritel',
            'tabungan_mikro',
            'tabungan_wholesale',
            'deposito_ritel',
            'deposito_mikro',
            'deposito_wholesale',
            'rec_dh_total',
            'rec_dh_small',
            'rec_dh_micro',
        ];
        $values = [
            'yoy' => 1_100_000_000.0,
            'ytd' => 1_200_000_000.0,
            'm2' => 1_300_000_000.0,
            'mtm' => 1_400_000_000.0,
            'mtd' => 1_500_000_000.0,
            'h1' => 2_900_000_000.0,
            'current' => $currentMillions * 1_000_000,
            'rka' => $rkaMillions * 1_000_000,
            'rka_dec' => $rkaDecemberMillions * 1_000_000,
        ];

        return [
            'selected_period' => $period,
            'comparison_periods' => [
                'yoy' => ['period' => '2025-08-31'],
                'ytd' => ['period' => '2025-12-31'],
                'm2' => ['period' => '2026-06-30'],
                'mtm' => ['period' => '2026-07-19'],
                'mtd' => ['period' => '2026-07-31'],
                'h1' => ['period' => '2026-08-18'],
                'rka' => ['period' => '2026-08-01'],
                'rka_dec' => ['period' => '2026-12-01'],
            ],
            'rows' => array_map(
                static fn (string $key): array => ['key' => $key, 'values' => $values],
                $metricKeys
            ),
        ];
    }

    private function presentationPrognosaFixture(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheetNames = ['Area 6', 'KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];
        $labels = [
            '1. Simpanan',
            'A. Ritel',
            'Giro',
            'Deposito',
            'Tabungan',
            'B. Mikro',
            'Giro',
            'Deposito',
            'Tabungan',
            'C. Wholesale',
            'Giro',
            'Deposito',
            'Tabungan',
            '2. OS Total',
            'Total OS Non Commercial',
            'A. Commercial',
            'B. SME',
            'Kecil',
            'Kecil Non Cashcoll',
            'Cashcoll',
            'Medium',
            'C. Konsumer',
            'Briguna',
            'KPR',
            'KKB',
            'D. Mikro',
            'Briguna Mikro',
            'Kupedes',
            'KUR Mikro',
            'KUR Kecil',
            'KUR KPP',
            '3. Total SML (%) Non Commercial',
            'Total SML (ABS) Non Commercial',
            'A. Commercial',
            'B. SME',
            'Kecil',
            'Kecil Non Cashcoll',
            'Cashcoll',
            'Medium',
            'C. Konsumer',
            'Briguna',
            'KPR',
            'KKB',
            'D. Mikro',
            'Briguna Mikro',
            'Kupedes',
            'KUR Mikro',
            'KUR Kecil',
            'KUR KPP',
            '4. Total NPL (%) Non Commercial',
            'Total NPL (ABS) Non Commercial',
            'A. Commercial',
            'B. SME',
            'Kecil',
            'Kecil Non Cashcoll',
            'Cashcoll',
            'Medium',
            'C. Konsumer',
            'Briguna',
            'KPR',
            'KKB',
            'D. Mikro',
            'Briguna Mikro',
            'Kupedes',
            'KUR Mikro',
            'KUR Kecil',
            'KUR KPP',
            '5. %CASA',
        ];
        $scopeValues = [
            'Area 6' => [
                '1. Simpanan' => [14_162_913, 14_144_099],
                'Total SML (ABS) Non Commercial' => [2_031_018, 2_034_941],
                'Total NPL (ABS) Non Commercial' => [751_310, 741_761],
            ],
            'KC Madiun' => [
                '1. Simpanan' => [4_188_552, 4_188_553],
            ],
        ];

        foreach ($sheetNames as $index => $sheetName) {
            $sheet = $index === 0
                ? $spreadsheet->getActiveSheet()
                : $spreadsheet->createSheet();
            $sheet->setTitle($sheetName);
            $sheet->setCellValue('A7', 'KETERANGAN');
            $sheet->setCellValue('G7', '23 July 2026');
            $sheet->setCellValue('H7', '24 July 2026');
            $sheet->setCellValue('I7', 'UPDATE POSISI 25 JULI 2026');
            $sheet->setCellValue('J7', 'PROGNOSA JULI 26');

            foreach ($labels as $offset => $label) {
                $row = 8 + $offset;
                $values = $scopeValues[$sheetName][$label] ?? [100 + $row, 110 + $row];
                $sheet->setCellValue("A{$row}", $label);
                $sheet->setCellValue("H{$row}", $values[0]);
                $sheet->setCellValue("I{$row}", $values[1]);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'presentation_prognosa_');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    private function weeklyPrognosaCsvFixture(
        int $base,
        bool $withRunOffProducts = false,
        bool $newLayout = false
    ): string {
        $rows = [$this->weeklyPrognosaCsvHeader($newLayout)];
        foreach ($this->weeklyPrognosaReportRows($base, $withRunOffProducts) as $reportRow) {
            if ($newLayout && is_numeric($reportRow[7] ?? null)) {
                $reportRow[12] = (int) round(((float) $reportRow[7]) * 1.625);
            }
            $sourceRow = array_fill(0, 35, '');
            foreach ($reportRow as $index => $value) {
                $sourceRow[$index + 1] = $value;
            }
            $rows[] = $sourceRow;
        }

        $stream = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($stream, $row, ',', '"', '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return (string) $csv;
    }

    private function weeklyDashboardCsvFixture(): string
    {
        $rows = [
            ['DASHBOARD KERAGAAN HARIAN'],
            ['Kanca', 'KC Madiun', '', 'Unit Kerja', 'Semua Unit Kerja'],
            ['Posisi Terakhir', '31 Aug 2026'],
            ['KETERANGAN', 'POSISI', '', '', '', '', '', '', 'PROGNOSA'],
            ['', '31 Aug 25 (YoY)', '31 Dec 25 (YtD)', '30 Jun 26 (M-2)', '31 Jul 26 (MtM)', '31 Jul 26 (MtD)', '30 Aug 26 (DtD)', '31 Aug 26 (Posisi)', 'WEEK 1 (5 Sept 26)', 'WEEK 2 (12 Sep 26)', 'WEEK 3 (19 Sep 26)', 'WEEK 4 (26 Sept 26)', 'WEEK 5 (30 Sep 26)'],
            ['1. Simpanan', '100', '110', '120', '130', '140', '150', '160', '211', '222', '233', '244', '255'],
        ];

        $stream = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($stream, $row, ',', '"', '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return (string) $csv;
    }

    /** @return array<int, string> */
    private function weeklyPrognosaCsvHeader(bool $newLayout = false): array
    {
        $header = array_fill(0, 35, '');
        foreach ($this->weeklyPrognosaHeaderValues($newLayout) as $reportIndex => $value) {
            $header[$reportIndex + 1] = $value;
        }

        return $header;
    }

    /** @return array<int, string> */
    private function weeklyPrognosaHeaderValues(bool $newLayout = false): array
    {
        if ($newLayout) {
            return [
                0 => 'MONITORING PTP TGL VS TGL NO',
                1 => 'KETERANGAN',
                2 => 'POSISI 31 AGUSTUS 2025',
                3 => '31 DESEMBER 2025',
                4 => '31 JULI 2026',
                5 => '31 JULI 2026',
                6 => '30 AGUSTUS 2026',
                7 => '31 AGUSTUS 2026',
                8 => 'PROGNOSA WEEK 1 (5 September 26)',
                9 => 'WEEK 2 (12 September 26)',
                10 => 'WEEK 3 (19 September 26)',
                11 => 'WEEK 4 (26 September 26)',
                12 => 'WEEK 5 (30 September 26)',
                13 => 'DELTA YOY',
                14 => 'YTD',
                15 => '',
                16 => 'MTD',
                17 => '',
                18 => 'RKA RKA Jan 2026',
                19 => 'RKA Feb 2026',
                20 => 'RKA Mar 2026',
                21 => 'RKA Apr 2026',
                22 => 'RKA Mei 2026',
                23 => 'RKA Juni 2026',
                24 => 'RKA Juli 2026',
                25 => 'RKA Agus 2026',
                26 => 'RKA Sep 2026',
                27 => 'RKA Okt 2026',
                28 => 'RKA Nov 2026',
                29 => 'RKA Des 2026',
                30 => 'GAP RKA Sep 2026',
                31 => 'RKA DES 2026',
                32 => 'PENCAPAIAN RKA RKA Sep 2026',
                33 => 'RKA DES 2026',
            ];
        }

        return [
            0 => 'MONITORING PTP TGL VS TGL NO',
            1 => 'KETERANGAN',
            2 => 'POSISI 31 AGUSTUS 2025',
            3 => '31 DESEMBER 2025',
            4 => '19 JULI 2026',
            5 => '31 JULI 2026',
            6 => '19 AGUSTUS 2026',
            7 => 'UPDATE POSISI JAM 12.00 WIB',
            8 => 'PROGNOSA WEEK 1',
            9 => 'WEEK 2',
            10 => 'WEEK 3',
            11 => 'WEEK 4',
            12 => 'DELTA YOY',
            13 => 'YTD',
            14 => 'MTM',
            15 => 'MTD',
            16 => 'DTD',
            17 => 'PROGNOSA WEEK 3',
            18 => 'RKA RKA Jan 2026',
            19 => 'RKA Feb 2026',
            20 => 'RKA Mar 2026',
            21 => 'RKA Apr 2026',
            22 => 'RKA Mei 2026',
            23 => 'RKA Juni 2026',
            24 => 'RKA Juli 2026',
            25 => 'RKA Agus 2026',
            26 => 'RKA Sep 2026',
            27 => 'RKA Okt 2026',
            28 => 'RKA Nov 2026',
            29 => 'RKA Des 2026',
            30 => 'GAP RKA AGT 2026',
            31 => 'RKA DES 2026',
            32 => 'PENCAPAIAN RKA RKA AGT 2026',
            33 => 'RKA DES 2026',
        ];
    }

    /** @return array<int, array<int, string|int|float>> */
    private function weeklyPrognosaReportRows(int $base, bool $withRunOffProducts = false): array
    {
        if ($withRunOffProducts) {
            return [
                $this->weeklyPrognosaReportRow('', 'PINJAMAN', $base),
                $this->weeklyPrognosaReportRow('1', 'MICRO', (int) round($base / 2)),
                $this->weeklyPrognosaReportRow('', 'TOTAL KUPEDES KUR KPP BRIGUNA', (int) round($base / 2)),
                $this->weeklyPrognosaReportRow('', 'KUPEDES', (int) round($base / 3)),
                $this->weeklyPrognosaReportRow('', 'BRIGUNA MIKRO', (int) round($base / 4)),
                $this->weeklyPrognosaReportRow('', 'KUR MIKRO', (int) round($base / 5)),
                $this->weeklyPrognosaReportRow('', 'KREDIT MIKRO - KPP', (int) round($base / 6)),
                $this->weeklyPrognosaReportRow('', 'KUR KECIL', (int) round($base / 7)),
                $this->weeklyPrognosaReportRow('2', 'RITEL', (int) round($base / 3)),
                $this->weeklyPrognosaReportRow('', 'KECIL KOMERSIAL', (int) round($base / 4)),
                $this->weeklyPrognosaReportRow('', 'CONSUMER', (int) round($base / 5)),
                $this->weeklyPrognosaReportRow('', 'BRIGUNA RITEL', (int) round($base / 6)),
                $this->weeklyPrognosaReportRow('', 'KPR', (int) round($base / 7)),
                $this->weeklyPrognosaReportRow('', 'SML', (int) round($base / 4)),
                $this->weeklyPrognosaReportRow('', 'NPL', (int) round($base / 5)),
                $this->weeklyPrognosaReportRow('', 'DANA PIHAK KE TIGA', null),
                $this->weeklyPrognosaReportRow('', 'TOTAL RITEL, MICRO & WHOLESALE', $base * 2),
                $this->weeklyPrognosaReportRow('', 'RECOVERY DH', (int) round($base / 10)),
            ];
        }

        return [
            $this->weeklyPrognosaReportRow('', 'PINJAMAN', $base),
            $this->weeklyPrognosaReportRow('1', 'MICRO', (int) round($base / 2)),
            $this->weeklyPrognosaReportRow('2', 'KECIL KOMERSIAL', (int) round($base / 3)),
            $this->weeklyPrognosaReportRow('3', 'CONSUMER', (int) round($base / 4)),
            $this->weeklyPrognosaReportRow('', 'SML', (int) round($base / 4)),
            $this->weeklyPrognosaReportRow('', 'NPL', (int) round($base / 5)),
            $this->weeklyPrognosaReportRow('', 'DANA PIHAK KE TIGA', null),
            $this->weeklyPrognosaReportRow('', 'TOTAL RITEL, MICRO & WHOLESALE', $base * 2),
            $this->weeklyPrognosaReportRow('', 'RECOVERY DH', (int) round($base / 10)),
        ];
    }

    /** @return array<int, string|int|float> */
    private function weeklyPrognosaReportRow(string $number, string $label, ?int $base): array
    {
        $row = array_fill(0, 34, '');
        $row[0] = $number;
        $row[1] = $label;
        if ($base === null) {
            return $row;
        }

        for ($index = 2; $index <= 31; $index++) {
            $row[$index] = $base;
        }
        $row[6] = (int) round($base * 1.5);
        $row[7] = $base * 2;
        $row[8] = (int) round($base * 2.25);
        $row[9] = (int) round($base * 2.5);
        $row[10] = (int) round($base * 2.75);
        $row[11] = $base * 3;
        $row[15] = $base;
        $row[25] = $base * 4;
        $row[29] = $base * 5;
        $row[30] = -$base * 2;
        $row[31] = -$base * 3;
        $row[32] = '50,00%';
        $row[33] = '40,00%';

        return $row;
    }

    private function weeklyPrognosaWorkbookFixture(): string
    {
        $spreadsheet = new Spreadsheet;
        $branchBases = [
            'Area 6' => 1_000,
            'KC Madiun' => 100,
            'KC Magetan' => 200,
            'KC Ngawi' => 300,
            'KC Ponorogo' => 400,
        ];

        foreach ($branchBases as $sheetIndex => $base) {
            $sheet = $sheetIndex === array_key_first($branchBases)
                ? $spreadsheet->getActiveSheet()
                : $spreadsheet->createSheet();
            $sheet->setTitle($sheetIndex);

            $groups = [
                'B9' => 'NO',
                'C9' => 'KETERANGAN',
                'D9' => 'POSISI',
                'I9' => 'UPDATE POSISI',
                'J9' => 'PROGNOSA',
                'N9' => 'DELTA',
                'T9' => 'RKA',
                'AF9' => 'GAP',
                'AH9' => 'PENCAPAIAN RKA',
            ];
            foreach ($groups as $coordinate => $value) {
                $sheet->setCellValue($coordinate, $value);
            }

            foreach ($this->weeklyPrognosaHeaderValues() as $reportIndex => $value) {
                if ($reportIndex < 2) {
                    continue;
                }
                $label = preg_replace(
                    ['/^(?:POSISI|UPDATE POSISI|PROGNOSA|DELTA|GAP|PENCAPAIAN RKA)\s+/i', '/^RKA\s+(?=RKA)/i'],
                    '',
                    $value
                );
                $sheet->setCellValue(
                    Coordinate::stringFromColumnIndex($reportIndex + 2).'10',
                    $label
                );
            }

            foreach ($this->weeklyPrognosaReportRows($base) as $offset => $reportRow) {
                $rowNumber = 11 + $offset;
                foreach ($reportRow as $reportIndex => $value) {
                    $sheet->setCellValue(
                        Coordinate::stringFromColumnIndex($reportIndex + 2).$rowNumber,
                        $value
                    );
                }
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'weekly_prognosa_workbook_');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    private function requestedSheetName(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return (string) ($query['sheet'] ?? '');
    }
}
