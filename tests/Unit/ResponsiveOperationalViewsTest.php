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
        $this->assertStringContainsString('aria-label="Pilih job ${job.id}"', $source);
        $this->assertStringContainsString('#btn-purge-queue-jobs { min-height: 32px; }', $source);
        $this->assertMatchesRegularExpression('/\\.job-row-check\\s*\\{[^}]*width:\\s*32px;[^}]*height:\\s*32px;/s', $source);
    }

    public function test_timeseries_export_control_does_not_collapse_beside_long_badges(): void
    {
        $source = file_get_contents(resource_path('views/report/dashboard-harian-timeseries.blade.php'));

        $this->assertStringContainsString('flex: 0 0 32px;', $source);
        $this->assertStringContainsString('.chart-header .unit-badge {', $source);
        $this->assertStringContainsString('text-overflow: ellipsis;', $source);
    }

    public function test_timeseries_chart_connects_observed_points_and_keeps_sparse_months_visible(): void
    {
        $source = file_get_contents(resource_path('views/report/dashboard-harian-timeseries.blade.php'));

        $this->assertStringContainsString('const observedPointCount = Array.isArray(d.data)', $source);
        $this->assertStringContainsString('const showSparsePoint = observedPointCount <= 1;', $source);
        $this->assertStringContainsString('pointRadius: showSparsePoint ? 3 : (isLatest ? 2.25 : 0)', $source);
        $this->assertStringContainsString('spanGaps: true,', $source);
        $this->assertStringContainsString('without manufacturing values for missing dates.', $source);
    }

    public function test_market_share_header_stacks_actions_on_narrow_screens(): void
    {
        $source = file_get_contents(resource_path('views/report/dashboard-dana-market-share.blade.php'));
        $geography = file_get_contents(resource_path('views/report/dashboard-dana/_market_share_geography.blade.php'));

        $this->assertStringContainsString('.market-workbook-copy {', $source);
        $this->assertStringContainsString('<div class="market-workbook-copy">', $source);
        $this->assertStringContainsString('.market-workbook-actions .market-workbook-button {', $source);
        $this->assertStringContainsString('overflow-wrap: anywhere;', $source);
        $this->assertStringContainsString('white-space: normal;', $source);
        $this->assertStringContainsString('@media (max-width: 575.98px)', $source);
        $this->assertMatchesRegularExpression('/\\.market-native-switch\\s*\\{[^}]*display:\\s*grid;[^}]*width:\\s*100%;/s', $source);
        $this->assertMatchesRegularExpression('/\\.market-mapping-workspace\\s*\\{[^}]*min-width:\\s*0;/s', $source);
        $this->assertMatchesRegularExpression('/\\.market-geo-app\\s*\\{[^}]*max-width:\\s*100%;[^}]*min-width:\\s*0;/s', $geography);
        $this->assertStringContainsString('.market-geo-header > div:first-child', $geography);
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
        $this->assertMatchesRegularExpression('/\.area6-scope-btn\s*\{[^}]*min-height:\s*36px;/s', $source);
        $this->assertStringNotContainsString('min-height: 32px !important;', $source);
    }

    public function test_user_management_heading_badge_wraps_inside_fold_viewports(): void
    {
        $source = file_get_contents(resource_path('views/admin/user-management.blade.php'));

        $this->assertStringContainsString('user-management-page-head', $source);
        $this->assertStringContainsString('user-management-admin-badge', $source);
        $this->assertMatchesRegularExpression('/\.user-management-page-head\s*\{[^}]*flex-wrap:\s*wrap;/s', $source);
        $this->assertMatchesRegularExpression('/\.user-management-page-copy\s*\{[^}]*min-width:\s*0;/s', $source);
        $this->assertMatchesRegularExpression('/\.user-management-admin-badge\s*\{[^}]*max-width:\s*100%;/s', $source);
    }

    public function test_run_off_multi_row_header_uses_the_runtime_sticky_offset(): void
    {
        $source = file_get_contents(resource_path('views/report/dashboard-pinjaman/run-off.blade.php'));

        $this->assertStringContainsString('top: var(--abah-table-head-top, 0px);', $source);
        $this->assertStringNotContainsString('top: 53px;', $source);
        $this->assertStringContainsString('--runoff-category-width: 180px;', $source);
        $this->assertStringContainsString('left: var(--runoff-category-width);', $source);
        $this->assertStringContainsString('z-index: 55 !important;', $source);
    }

    public function test_matrix_multi_row_header_uses_the_runtime_sticky_offset(): void
    {
        $source = file_get_contents(resource_path('views/report/dashboard-pinjaman/_partials/_styles.blade.php'));

        $this->assertMatchesRegularExpression(
            '/\.loan-matrix thead tr:nth-child\(2\) th\s*\{[^}]*top:\s*var\(--abah-table-head-top,\s*0px\);/s',
            $source
        );
        $this->assertMatchesRegularExpression(
            '/\.loan-matrix thead th\.matrix-before\s*\{[^}]*top:\s*var\(--abah-table-head-top,\s*0px\)\s*!important;/s',
            $source
        );
        $this->assertStringContainsString('.loan-matrix thead tr:first-child th.matrix-before', $source);
        $this->assertStringNotContainsString('top: 38px;', $source);
    }

    public function test_six_month_arrears_actions_wrap_before_the_sidebar_reduces_content_width(): void
    {
        $source = file_get_contents(resource_path('views/report/dashboard-pinjaman/realisasi-6-bulan-menunggak.blade.php'));

        $this->assertStringContainsString('@media (max-width: 1199.98px)', $source);
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 1199\.98px\).*?\.six-arrears-actions\s*\{[^}]*grid-column:\s*1\s*\/\s*-1;[^}]*flex-wrap:\s*wrap;/s',
            $source
        );
    }

    public function test_daily_dashboard_export_actions_remain_touch_friendly_in_compact_header(): void
    {
        $source = file_get_contents(resource_path('views/report/dashboard-harian.blade.php'));

        $this->assertStringNotContainsString('min-height: 28px !important;', $source);
        $this->assertStringNotContainsString('height: 28px !important;', $source);
        $this->assertStringContainsString('min-height: 36px !important;', $source);
    }
}
