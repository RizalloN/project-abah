<?php

namespace Tests\Unit;

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
}
