<?php

namespace Tests\Unit;

use Tests\TestCase;

class ResponsiveOperationalViewsTest extends TestCase
{
    public function test_hourly_dpk_filter_can_shrink_on_fold_viewports(): void
    {
        $source = file_get_contents(resource_path('views/report/dashboard-dana-hourly-dpk.blade.php'));

        $this->assertStringContainsString('@media (max-width: 575.98px)', $source);
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr);', $source);
        $this->assertStringContainsString('.hourly-filter-actions {', $source);
        $this->assertStringContainsString('flex-direction: column;', $source);
        $this->assertStringContainsString('min-width: 0;', $source);
    }

    public function test_job_management_reflows_summary_and_controls_on_touch_widths(): void
    {
        $source = file_get_contents(resource_path('views/import/job-management.blade.php'));

        $this->assertStringContainsString('job-page-heading', $source);
        $this->assertStringContainsString('job-summary-grid', $source);
        $this->assertStringContainsString('job-filter-controls', $source);
        $this->assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr));', $source);
        $this->assertStringContainsString('#job-filter-status,', $source);
        $this->assertStringContainsString('grid-column: 1 / -1;', $source);
    }

    public function test_timeseries_export_control_does_not_collapse_beside_long_badges(): void
    {
        $source = file_get_contents(resource_path('views/report/dashboard-harian-timeseries.blade.php'));

        $this->assertStringContainsString('flex: 0 0 32px;', $source);
        $this->assertStringContainsString('.chart-header .unit-badge {', $source);
        $this->assertStringContainsString('text-overflow: ellipsis;', $source);
    }

    public function test_market_share_header_stacks_actions_on_narrow_screens(): void
    {
        $source = file_get_contents(resource_path('views/report/dashboard-dana-market-share.blade.php'));

        $this->assertStringContainsString('.market-workbook-copy {', $source);
        $this->assertStringContainsString('<div class="market-workbook-copy">', $source);
        $this->assertStringContainsString('.market-workbook-actions .market-workbook-button {', $source);
        $this->assertStringContainsString('overflow-wrap: anywhere;', $source);
        $this->assertStringContainsString('white-space: normal;', $source);
    }

    public function test_rm_mikro_tabs_keep_a_touch_friendly_height(): void
    {
        $source = file_get_contents(resource_path('views/report/dashboard-pinjaman/kinerjarmmikro.blade.php'));

        $this->assertStringContainsString('.rm-mikro-tab {', $source);
        $this->assertStringContainsString('min-height: 38px;', $source);
    }

    public function test_report_management_bulk_selection_has_a_full_touch_target(): void
    {
        $source = file_get_contents(resource_path('views/import/report-management.blade.php'));

        $this->assertStringContainsString('.report-management-bulkbar .form-check-label {', $source);
        $this->assertStringContainsString('display: inline-flex;', $source);
        $this->assertStringContainsString('min-height: 38px;', $source);
    }

    public function test_primary_dashboard_detail_controls_are_not_tiny_text_targets(): void
    {
        $source = file_get_contents(resource_path('views/dashboard.blade.php'));

        $this->assertStringContainsString('.kpi-card .kc-link {', $source);
        $this->assertStringContainsString('min-height: 36px;', $source);
        $this->assertStringContainsString('padding: 0 0.4rem;', $source);
    }

    public function test_daily_dashboard_export_actions_remain_touch_friendly_in_compact_header(): void
    {
        $source = file_get_contents(resource_path('views/report/dashboard-harian.blade.php'));

        $this->assertStringNotContainsString('min-height: 28px !important;', $source);
        $this->assertStringNotContainsString('height: 28px !important;', $source);
        $this->assertStringContainsString('min-height: 36px !important;', $source);
    }
}
