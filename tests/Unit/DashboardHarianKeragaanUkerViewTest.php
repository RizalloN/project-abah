<?php

namespace Tests\Unit;

use Tests\TestCase;

class DashboardHarianKeragaanUkerViewTest extends TestCase
{
    private string $viewContent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->viewContent = file_get_contents(resource_path('views/report/dashboard-harian-keragaan-uker.blade.php'));
    }

    public function test_uker_code_and_name_columns_are_strictly_not_sortable(): void
    {
        // th.uker-code and th.uker-name must not have uker-th-sortable class or sort data attributes
        $this->assertStringContainsString('<th class="uker-code">Kode</th>', $this->viewContent);
        $this->assertStringContainsString('<th class="uker-name">Nama</th>', $this->viewContent);

        $this->assertStringNotContainsString('data-sort-key="unit_code"', $this->viewContent);
        $this->assertStringNotContainsString('data-sort-key="unit_name"', $this->viewContent);
    }

    public function test_position_delta_rka_and_achievement_columns_are_sortable(): void
    {
        // Columns for position, delta, rka, achievement must use renderSortableTh
        $this->assertStringContainsString("renderSortableTh('position', col.key, label)", $this->viewContent);
        $this->assertStringContainsString("renderSortableTh('delta', col.key, label)", $this->viewContent);
        $this->assertStringContainsString("renderSortableTh('rka', 'rka', 'RKA')", $this->viewContent);
        $this->assertStringContainsString("renderSortableTh('achievement', 'achievement', 'Penc. RKA')", $this->viewContent);
        $this->assertStringContainsString('data-sort-metric=', $this->viewContent);
        $this->assertStringContainsString('data-sort-type=', $this->viewContent);
        $this->assertStringContainsString('data-sort-key=', $this->viewContent);
    }

    public function test_sorting_css_and_visual_indicators_are_present(): void
    {
        $this->assertStringContainsString('.uker-th-sortable {', $this->viewContent);
        $this->assertStringContainsString('.uker-th-sortable:hover {', $this->viewContent);
        $this->assertStringContainsString('.uker-th-sortable.is-sorted {', $this->viewContent);
        $this->assertStringContainsString('.th-sort-inner {', $this->viewContent);
        $this->assertStringContainsString('.th-sort-icon {', $this->viewContent);
        $this->assertStringContainsString('.uker-sort-reset-btn {', $this->viewContent);
        $this->assertStringContainsString('.uker-sort-badge {', $this->viewContent);
    }

    public function test_sort_logic_and_state_management_are_implemented(): void
    {
        // tableSortState dictionary and comparison helpers
        $this->assertStringContainsString('const tableSortState = {};', $this->viewContent);
        $this->assertStringContainsString('function getRowSortValue(row, metricKey, sortType, sortKey)', $this->viewContent);
        $this->assertStringContainsString('function compareRows(rowA, rowB, metricKey, sortState)', $this->viewContent);
        $this->assertStringContainsString('function handleSortClick(metricKey, sortType, sortKey)', $this->viewContent);
        $this->assertStringContainsString('function handleResetSort(metricKey)', $this->viewContent);
        $this->assertStringContainsString('function clearAllSorts()', $this->viewContent);
        $this->assertStringContainsString('function renderCurrentWithScrollPreserved()', $this->viewContent);
    }

    public function test_total_row_remains_at_the_bottom_after_sorted_body_rows(): void
    {
        // Body rows are sorted from a clone array, and totalRow is concatenated at the end
        $this->assertStringContainsString('let rows = [...(payload?.rows || [])];', $this->viewContent);
        $this->assertStringContainsString('rows.sort((a, b) => compareRows(a, b, metric.key, sortState));', $this->viewContent);
        $this->assertStringContainsString('<tbody>${bodyRows}${totalRow}</tbody>', $this->viewContent);
    }

    public function test_redesigned_header_uses_context_bar_and_period_card(): void
    {
        $this->assertStringContainsString('class="uker-context-bar"', $this->viewContent);
        $this->assertStringContainsString('class="context-item"', $this->viewContent);
        $this->assertStringContainsString('id="scopeLabel"', $this->viewContent);
        $this->assertStringContainsString('id="unitLabel"', $this->viewContent);
        $this->assertStringContainsString('id="sourceLabel"', $this->viewContent);
        $this->assertStringContainsString('class="uker-period-card"', $this->viewContent);
        $this->assertStringContainsString('id="periodLabel"', $this->viewContent);
    }

    public function test_custom_select_components_and_script_sync_are_implemented(): void
    {
        $this->assertStringContainsString('.custom-select-wrap {', $this->viewContent);
        $this->assertStringContainsString('.custom-select-trigger {', $this->viewContent);
        $this->assertStringContainsString('.custom-select-panel {', $this->viewContent);
        $this->assertStringContainsString('.custom-select-search-input {', $this->viewContent);
        $this->assertStringContainsString('.custom-select-options {', $this->viewContent);
        $this->assertStringContainsString('.custom-select-native-hidden {', $this->viewContent);

        $this->assertStringContainsString('function setupCustomSelect(selectId, placeholder, hasSearch = true)', $this->viewContent);
        $this->assertStringContainsString('function syncAllCustomSelects()', $this->viewContent);
        $this->assertStringContainsString("setupCustomSelect('kancaFilter', 'Pilih cabang', true);", $this->viewContent);
        $this->assertStringContainsString("setupCustomSelect('unitFilter', 'Semua Unit Kerja', true);", $this->viewContent);
        $this->assertStringContainsString("setupCustomSelect('dataTypeFilter', 'Pilih data', false);", $this->viewContent);
        $this->assertStringContainsString("setupCustomSelect('rkaFilter', 'Pilih RKA', false);", $this->viewContent);
    }

    public function test_dropdown_clipping_prevention_and_stacking_context_are_guaranteed(): void
    {
        // 1. Shell must be visible overflow to prevent panel clipping
        $this->assertStringContainsString('overflow: visible !important;', $this->viewContent);
        $this->assertStringContainsString('.uker-shell.is-loading,', $this->viewContent);
        $this->assertStringContainsString('min-height: 480px;', $this->viewContent);

        // 2. High z-index on active item and wrap
        $this->assertStringContainsString('.uker-filter-item.has-open-dropdown {', $this->viewContent);
        $this->assertStringContainsString('z-index: 1100 !important;', $this->viewContent);
        $this->assertStringContainsString('.custom-select-wrap.is-open {', $this->viewContent);

        // 3. Dropdown panel elevation and z-index
        $this->assertStringContainsString('z-index: 1150 !important;', $this->viewContent);
        $this->assertStringContainsString('min-width: max(100%, 250px);', $this->viewContent);

        // 4. JS must toggle has-open-dropdown class
        $this->assertStringContainsString("classList.add('has-open-dropdown')", $this->viewContent);
        $this->assertStringContainsString("classList.remove('has-open-dropdown')", $this->viewContent);
    }
}
