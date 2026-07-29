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
        $this->assertStringContainsString('syncStickyHeaderSurfaces', $layout);
        $this->assertStringContainsString("target.closest('table')", $layout);
        $this->assertStringContainsString('--abah-table-header-height', $layout);
        $this->assertStringContainsString('.abah-sticky-surface', $layout);
        $this->assertStringContainsString('tbody td:first-child:not(.sticky-col):not([colspan])', $layout);
        $this->assertStringContainsString("target.querySelectorAll('table').forEach(function (table)", $layout);
        $this->assertStringContainsString('tables.forEach(enhanceTable)', $layout);
        $this->assertStringContainsString('scheduleEnhance(node)', $layout);
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

    public function test_admin_layout_uses_device_safe_contracts_without_broad_component_wildcards(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $this->assertStringContainsString('viewport-fit=cover', $layout);
        $this->assertStringContainsString('interactive-widget=resizes-content', $layout);
        $this->assertStringContainsString('--app-safe-left: env(safe-area-inset-left, 0px);', $layout);
        $this->assertMatchesRegularExpression('/body\s*\{\s*min-width:\s*0;/', $layout);
        $this->assertStringContainsString('[data-ui="hero"]', $layout);
        $this->assertStringContainsString('[data-ui="filter"]', $layout);
        $this->assertStringContainsString('[data-ui="actions"]', $layout);
        $this->assertStringContainsString('@media (max-width: 359.98px)', $layout);
        $this->assertStringContainsString('@media (pointer: coarse)', $layout);
        $this->assertStringContainsString('max-height: max(420px, calc(100dvh - 150px));', $layout);
        $this->assertStringContainsString('.swal2-popup', $layout);
        $this->assertStringContainsString('.modal-body', $layout);
        $this->assertStringNotContainsString('[class*="-filter-"]', $layout);
        $this->assertStringNotContainsString('[class*="filter-"]', $layout);
        $this->assertStringNotContainsString('[class*="-title"]', $layout);
        $this->assertStringNotContainsString('min-width: 320px;', $layout);
    }

    public function test_fixed_height_operational_surfaces_follow_the_dynamic_viewport(): void
    {
        $excelPreview = file_get_contents(resource_path('views/import/preview_excel.blade.php'));
        $marketMap = file_get_contents(resource_path('views/report/dashboard-dana/_market_share_geography.blade.php'));

        $this->assertStringNotContainsString('style="min-height: 450px; max-height: 600px; overflow-y: auto; overflow-x: auto;"', $excelPreview);
        $this->assertStringContainsString('min-height: clamp(320px, 52dvh, 450px);', $excelPreview);
        $this->assertStringContainsString('min-height: clamp(480px, 68dvh, 720px);', $marketMap);
        $this->assertStringContainsString('@media (max-width: 359.98px)', $marketMap);
    }

    public function test_sticky_table_partial_keeps_only_table_headers_sticky(): void
    {
        $style = file_get_contents(resource_path('views/report/partials/sticky-table-viewport-style.blade.php'));

        $this->assertStringContainsString('position: relative;', $style);
        $this->assertStringContainsString('{{ $wrapperSelector }} {{ $tableSelector }} thead th', $style);
        $this->assertStringContainsString('position: sticky;', $style);
        $this->assertStringContainsString('max-height: min(68dvh, 680px) !important;', $style);
        $this->assertStringContainsString('overflow-y: auto;', $style);
        $this->assertStringContainsString('scrollbar-gutter: stable;', $style);
        $this->assertStringNotContainsString('scrollbar-gutter: stable both-edges;', $style);
        $this->assertStringNotContainsString('max-height: none !important;', $style);
        $this->assertStringNotContainsString('overflow-y: visible;', $style);
        $this->assertStringNotContainsString('top: var(--table-sticky-top);', $style);
    }
}
