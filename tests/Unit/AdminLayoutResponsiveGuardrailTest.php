<?php

namespace Tests\Unit;

use Tests\TestCase;

class AdminLayoutResponsiveGuardrailTest extends TestCase
{
    public function test_admin_layout_applies_responsive_table_guardrails_without_sticky_filters(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $this->assertStringContainsString('.content-wrapper .abah-table-scroll', $layout);
        $this->assertStringContainsString('max-height: min(72vh, 820px)', $layout);
        $this->assertStringContainsString('.content-wrapper .abah-table-managed thead th', $layout);
        $this->assertStringContainsString('position: sticky;', $layout);
        $this->assertStringContainsString('ensureWrapper', $layout);
        $this->assertStringContainsString('syncReadableCellTitles', $layout);
        $this->assertStringContainsString("root.querySelectorAll('table').forEach(enhanceTable)", $layout);
        $this->assertStringNotContainsString('.content-wrapper .card { position: sticky;', $layout);
        $this->assertStringNotContainsString('.content-wrapper form { position: sticky;', $layout);
    }

    public function test_admin_layout_covers_remaining_report_page_patterns(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $this->assertStringContainsString('.dashboard-hero', $layout);
        $this->assertStringContainsString('.import-hero', $layout);
        $this->assertStringContainsString('.market-filter-panel', $layout);
        $this->assertStringContainsString('.hourly-filter-shell', $layout);
        $this->assertStringContainsString('.casa-shell', $layout);
        $this->assertStringContainsString('.dormant-shell', $layout);
        $this->assertStringContainsString('.kinerja-konsumer-filters', $layout);
        $this->assertStringContainsString('max-height: calc(100vh - 118px)', $layout);
    }

    public function test_sticky_table_partial_keeps_only_table_headers_sticky(): void
    {
        $style = file_get_contents(resource_path('views/report/partials/sticky-table-viewport-style.blade.php'));

        $this->assertStringContainsString('position: relative;', $style);
        $this->assertStringContainsString('{{ $wrapperSelector }} {{ $tableSelector }} thead th', $style);
        $this->assertStringContainsString('position: sticky;', $style);
        $this->assertStringNotContainsString('top: var(--table-sticky-top);', $style);
    }
}
