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

    public function test_matrix_retries_while_a_missing_snapshot_is_prepared(): void
    {
        $view = file_get_contents(resource_path('views/report/dashboard-pinjaman/matrix.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString("payload.status === 'warming'", $view);
        $this->assertStringContainsString('snapshotWarmAttempts >= 24', $view);
        $this->assertStringContainsString('loadMatrix(false, true)', $view);
    }

    public function test_matrix_endpoint_does_not_rebuild_snapshots_inside_a_browser_request(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/DashboardPinjamanReportController.php'));

        $this->assertIsString($controller);
        $this->assertStringContainsString('queueMissingMatrixSnapshots', $controller);
        $this->assertStringContainsString("'status' => 'warming'", $controller);
        $this->assertStringNotContainsString('rebuildDashboard($period, false)', $controller);
    }
}
