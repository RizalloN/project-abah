<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardHarianController;
use App\Models\User;
use App\Support\DashboardHarianSnapshotService;
use Tests\TestCase;

class DashboardHarianResponsiveViewTest extends TestCase
{
    public function test_dashboard_harian_portrait_layout_keeps_controls_inside_viewport(): void
    {
        $source = file_get_contents(resource_path('views/report/dashboard-harian.blade.php'));

        $this->assertStringContainsString('class="daily-panel-title-group d-flex align-items-center"', $source);
        $this->assertStringContainsString('class="daily-panel-actions d-flex align-items-center"', $source);
        $this->assertStringContainsString('overflow-x: clip;', $source);
        $this->assertStringContainsString('.daily-dashboard *', $source);
        $this->assertStringContainsString('.daily-panel-actions {', $source);
        $this->assertStringContainsString('flex-wrap: wrap;', $source);
        $this->assertStringContainsString('@media (orientation: portrait) and (max-width: 1199.98px)', $source);
        $this->assertStringContainsString('container-type: inline-size;', $source);
        $this->assertStringContainsString('grid-template-areas: "kanca unit posisi rka action";', $source);
        $this->assertStringContainsString('"action action"', $source);
        $this->assertStringContainsString('@media (max-width: 640px)', $source);
        $this->assertStringContainsString('grid-column: 1 / -1 !important;', $source);
        $this->assertStringContainsString('white-space: normal !important;', $source);
        $this->assertStringContainsString('font-size: clamp(0.82rem, 5.2vw, 0.95rem) !important;', $source);
        $this->assertStringContainsString('overflow-x: auto !important;', $source);
        $this->assertStringContainsString('-webkit-overflow-scrolling: touch;', $source);
        $this->assertStringContainsString('Use the compact filter summary on every viewport.', $source);
        $this->assertStringContainsString('@media (min-width: 1400px)', $source);
        $this->assertStringContainsString('minmax(164px, 0.72fr)', $source);
        $this->assertStringContainsString('height: 58px !important;', $source);
        $this->assertStringContainsString('align-items: start !important;', $source);
        $this->assertStringContainsString('aria-controls="daily-filter-grid"', $source);
        $this->assertStringContainsString('const setFilterPanelOpen = function (isOpen)', $source);
        $this->assertStringContainsString('setFilterPanelOpen(false);', $source);
        $this->assertStringContainsString("applyButton.addEventListener('click', function ()", $source);
        $this->assertStringContainsString('window.addEventListener(\'orientationchange\', handleResponsiveViewportChange);', $source);
        $this->assertStringContainsString('window.visualViewport.addEventListener(\'resize\', handleResponsiveViewportChange);', $source);
    }

    public function test_daily_table_uses_bounded_scroll_and_runtime_three_row_header_offsets(): void
    {
        $source = file_get_contents(resource_path('views/report/dashboard-harian.blade.php'));

        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 991\.98px\), \(max-height: 760px\).*?\.daily-table-wrap\s*\{[^}]*max-height:\s*max\(240px,\s*min\(68dvh,\s*640px\)\)\s*!important;[^}]*overflow:\s*auto\s*!important;/s',
            $source
        );
        $this->assertMatchesRegularExpression(
            '/@media \(orientation: landscape\) and \(max-height: 640px\).*?\.daily-table-wrap\s*\{[^}]*max-height:\s*max\(220px,\s*min\(60dvh,\s*520px\)\)\s*!important;[^}]*overflow:\s*auto\s*!important;/s',
            $source
        );
        $this->assertStringContainsString('--daily-header-column-top: var(--daily-header-group-height);', $source);
        $this->assertStringContainsString('--daily-header-rka-top: calc(var(--daily-header-group-height) + var(--daily-header-column-height));', $source);
        $this->assertStringContainsString('const syncStickyHeaderOffsets = function ()', $source);
        $this->assertStringContainsString("tableWrap.style.setProperty('--daily-header-column-top', groupHeight + 'px');", $source);
        $this->assertStringContainsString("tableWrap.style.setProperty('--daily-header-rka-top', (groupHeight + columnHeight) + 'px');", $source);
        $this->assertStringContainsString('const stickyHeaderResizeObserver = new ResizeObserver(scheduleTableViewportSync);', $source);
        $this->assertStringContainsString('document.fonts.ready.then(scheduleTableViewportSync);', $source);
        $this->assertStringContainsString('class="sticky-label group-label" rowspan="3"', $source);
        $this->assertStringContainsString('top: var(--daily-header-column-top);', $source);
        $this->assertStringContainsString('top: var(--daily-header-rka-top);', $source);
    }

    public function test_kanca_filter_is_single_select_in_ui_and_server_normalization(): void
    {
        $source = file_get_contents(resource_path('views/report/dashboard-harian.blade.php'));

        $this->assertStringContainsString(
            '<select id="filter-kanca" name="kanca" class="form-control daily-filter-native"></select>',
            $source
        );
        $this->assertStringContainsString('aria-multiselectable="false"', $source);
        $this->assertStringContainsString('class="daily-dropdown-radio"', $source);
        $this->assertStringContainsString("const nextValues = value === 'all' ? [] : [value];", $source);
        $this->assertStringContainsString('return normalized.length > 1', $source);
        $this->assertStringContainsString("closeDropdown('kanca');", $source);
        $this->assertStringContainsString("{ value: 'all', label: 'Semua Unit Kerja (Ritel dipecah menjadi KC dan KCP)' }", $source);
        $this->assertStringContainsString("{ value: 'all-konsol', label: 'Semua Unit Kerja (Konsol)' }", $source);
        $this->assertStringContainsString("['all', 'all-konsol'].includes(selects.unit_kerja.value)", $source);

        $controller = new DashboardHarianController(new DashboardHarianSnapshotService());
        $normalize = new \ReflectionMethod($controller, 'normalizeSingleKancaFilter');
        $normalize->setAccessible(true);

        $this->assertSame('KC Madiun', $normalize->invoke($controller, ['KC Madiun', 'KC Magetan']));
        $this->assertNull($normalize->invoke($controller, ['KC Madiun', 'KC Magetan', 'KC Ponorogo', 'KC Ngawi']));
    }

    public function test_timeseries_uses_single_branch_select_and_reveals_units_for_one_branch(): void
    {
        $source = file_get_contents(resource_path('views/report/dashboard-harian-timeseries.blade.php'));

        $this->assertStringContainsString('<select id="kancaInput" class="timeseries-dimension-select"', $source);
        $this->assertStringContainsString('data-user-branch-locked=', $source);
        $this->assertStringContainsString('id="unitFilterColumn"', $source);
        $this->assertStringContainsString('unitFilterColumn.hidden = !hasSingleBranch;', $source);
        $this->assertStringContainsString("kancaInput.value === 'all'", $source);
        $this->assertStringNotContainsString('id="kancaOptionsList"', $source);
        $this->assertStringContainsString("{ value: 'giro', label: 'Giro' }", $source);
        $this->assertStringContainsString("{ value: 'tabungan', label: 'Tabungan' }", $source);
        $this->assertStringContainsString("{ value: 'deposito', label: 'Deposito' }", $source);
        $this->assertStringContainsString('id="retailMicroFilterColumn"', $source);
        $this->assertStringContainsString('id="retailMicroFilter" class="timeseries-dimension-select"', $source);
        $this->assertStringContainsString("function usesRetailMicroDropdown()", $source);
        $this->assertStringContainsString("['ritel', 'micro'].includes(currentSegment)", $source);
        $this->assertStringContainsString('function currentSegmentLabel()', $source);
        $this->assertStringContainsString('<option value="">Pilih Ritel / Micro</option>', $source);
    }

    public function test_timeseries_branch_resolution_is_single_and_defaults_to_area_6(): void
    {
        $controller = new DashboardHarianController(new DashboardHarianSnapshotService());
        $resolve = new \ReflectionMethod($controller, 'resolveTimeseriesFilters');
        $resolve->setAccessible(true);

        $areaUser = User::factory()->make(['pn' => '9999', 'branch_scope' => 'area6']);
        $singleRequest = \Illuminate\Http\Request::create('/dashboard-harian/timeseries', 'GET', ['kanca' => 'KC Ngawi']);
        $singleRequest->setUserResolver(fn () => $areaUser);
        $invalidRequest = \Illuminate\Http\Request::create('/dashboard-harian/timeseries', 'GET', ['kanca' => ['KC Madiun', 'KC Ngawi']]);
        $invalidRequest->setUserResolver(fn () => $areaUser);
        $lockedUser = User::factory()->make(['pn' => '0045']);
        $lockedRequest = \Illuminate\Http\Request::create('/dashboard-harian/timeseries', 'GET', ['kanca' => 'KC Ngawi']);
        $lockedRequest->setUserResolver(fn () => $lockedUser);
        $areaWithUnitRequest = \Illuminate\Http\Request::create('/dashboard-harian/timeseries', 'GET', [
            'kanca' => 'all',
            'unit_kerja' => 'unit-dari-cabang-lama',
        ]);
        $areaWithUnitRequest->setUserResolver(fn () => $areaUser);

        $this->assertSame(['KC Ngawi', null, false], $resolve->invoke($controller, $singleRequest));
        $this->assertSame([
            ['KC Madiun', 'KC Magetan', 'KC Ponorogo', 'KC Ngawi'],
            null,
            false,
        ], $resolve->invoke($controller, $invalidRequest));
        $this->assertSame(['KC Madiun', null, true], $resolve->invoke($controller, $lockedRequest));
        $this->assertSame([
            ['KC Madiun', 'KC Magetan', 'KC Ponorogo', 'KC Ngawi'],
            null,
            false,
        ], $resolve->invoke($controller, $areaWithUnitRequest));
    }

    public function test_ssa_position_filters_use_native_date_pickers(): void
    {
        $dashboard = file_get_contents(resource_path('views/report/dashboard-harian.blade.php'));
        $uker = file_get_contents(resource_path('views/report/dashboard-harian-keragaan-uker.blade.php'));

        $this->assertStringContainsString('type="date" id="filter-posisi-terakhir"', $dashboard);
        $this->assertStringContainsString('syncPosisiDatePicker', $dashboard);
        $this->assertStringNotContainsString('data-daily-dropdown="posisi"', $dashboard);
        $this->assertStringContainsString('type="date" id="periodFilter"', $uker);
        $this->assertStringContainsString('syncPeriodDatePicker', $uker);
        $this->assertStringContainsString('payload?.selected?.posisi_terakhir || els.period.value', $uker);
        $this->assertStringNotContainsString('<select id="periodFilter"', $uker);

        $this->actingAs(User::factory()->make(['name' => 'Audit UI', 'pn' => 'audit-ui', 'role' => 'admin']));
        $dashboardHtml = view('report.dashboard-harian', ['dashboardPage' => []])->render();
        $ukerHtml = view('report.dashboard-harian-keragaan-uker', ['dashboardPage' => []])->render();
        $this->assertStringContainsString('type="date" id="filter-posisi-terakhir"', $dashboardHtml);
        $this->assertStringContainsString('type="date" id="periodFilter"', $ukerHtml);
    }
}
