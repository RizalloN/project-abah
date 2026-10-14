<?php

namespace Tests\Unit;

use Tests\TestCase;

class DashboardPinjamanMatrixViewTest extends TestCase
{
    public function test_matrix_filters_use_a_collapsed_single_row_summary(): void
    {
        $view = file_get_contents(resource_path('views/report/dashboard-pinjaman/matrix.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('id="loanFilterToggle"', $view);
        $this->assertStringContainsString('id="loanFilterSelectionSummary"', $view);
        $this->assertStringContainsString('.loan-filter-panel {', $view);
        $this->assertStringContainsString('display: none;', $view);
        $this->assertStringContainsString('.loan-filter-shell.is-open .loan-filter-panel', $view);
        $this->assertStringContainsString('setFilterPanelOpen(false);', $view);
        $this->assertSame(1, substr_count($view, 'id="loanActivePeriodMeta"'));
        $this->assertSame(1, substr_count($view, 'id="loanComparisonPeriodMeta"'));
    }

    public function test_matrix_retries_while_another_request_is_computing(): void
    {
        $view = file_get_contents(resource_path('views/report/dashboard-pinjaman/matrix.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString("payload.status === 'warming' || payload.status === 'computing'", $view);
        $this->assertStringContainsString('snapshotWarmAttempts >= 40', $view);
        $this->assertStringContainsString('loadMatrix(false, true)', $view);
        $this->assertStringContainsString('REKONSILIASI SESUAI', $view);
        $this->assertStringContainsString('UNIT: RUPIAH', $view);
        $this->assertStringContainsString('row.metric_debtors?.[col]', $view);
        $this->assertStringContainsString('debitur / ${formatNumber(accountCount)} rekening', $view);
        $this->assertStringContainsString('.loan-metric-count {', $view);
        $this->assertStringContainsString('id="loanOpeningPosition"', $view);
        $this->assertStringContainsString('id="loanBasisPosition"', $view);
        $this->assertStringContainsString('reconciliation?.portfolio_inflow_position', $view);
        $this->assertStringContainsString('Total Basis', $view);
    }

    public function test_matrix_endpoint_uses_daily_loan_without_snapshot_warming(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/DashboardPinjamanReportController.php'));

        $this->assertIsString($controller);
        $this->assertStringContainsString("'status' => 'computing'", $controller);
        $this->assertStringContainsString('buildMovementMatrixAndSuplesiAggregateQuery', $controller);
        $this->assertStringContainsString("'uses_snapshot' => false", $controller);
        $this->assertStringNotContainsString('rebuildDashboard($period, false)', $controller);
    }

    public function test_matrix_period_changes_invalidate_old_rows_and_keep_drilldown_on_applied_params(): void
    {
        $view = file_get_contents(resource_path('views/report/dashboard-pinjaman/matrix.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('function markMatrixDirty()', $view);
        $this->assertStringContainsString('activeMatrixParams = null', $view);
        $this->assertStringContainsString('new URLSearchParams(activeMatrixParams.toString())', $view);
        $this->assertStringContainsString("window.history.pushState({}, '', `?\${params.toString()}`)", $view);
        $this->assertStringContainsString("window.addEventListener('popstate', () => window.location.reload())", $view);
        $this->assertStringContainsString("payload.ph_period_relation === 'fallback'", $view);
        $this->assertStringContainsString('max-width: calc(100vw - 2rem)', $view);
        $this->assertStringNotContainsString("window.history.replaceState({}, '', `?\${params.toString()}`)", $view);
    }

    public function test_matrix_footer_supports_drilldown_on_total_columns(): void
    {
        $view = file_get_contents(resource_path('views/report/dashboard-pinjaman/matrix.blade.php'));
        $styles = file_get_contents(resource_path('views/report/dashboard-pinjaman/_partials/_styles.blade.php'));

        $this->assertIsString($view);
        $this->assertIsString($styles);

        $this->assertStringContainsString('data-before-bucket="TOTAL"', $view);
        $this->assertStringContainsString("const cellClass = hasValue ? 'loan-drill-cell' : 'matrix-empty';", $view);
        $this->assertStringContainsString('[body, foot].forEach(container =>', $view);
        $this->assertStringContainsString("container?.addEventListener('dblclick'", $view);
        $this->assertStringContainsString('#loanMatrixFoot td.loan-drill-cell', $view);
        $this->assertStringContainsString("Total Posisi: \${displayBucketLabel(afterBucket)} (Semua Kolek Awal)", $view);
        $this->assertStringContainsString('.loan-matrix tfoot td.loan-drill-cell', $styles);
        $this->assertStringContainsString('.loan-matrix tfoot td.loan-drill-cell.is-selected', $styles);
    }
}
