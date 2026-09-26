<?php

namespace Tests\Unit;

use Tests\TestCase;

class AdminLayoutResponsiveGuardrailTest extends TestCase
{
    public function test_admin_layout_applies_responsive_table_guardrails_without_sticky_filters(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $this->assertStringContainsString('.content-wrapper .abah-table-scroll', $layout);
        $this->assertStringContainsString('max-height: none !important;', $layout);
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

    public function test_table_headers_follow_page_scroll_except_on_the_landing_pages(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $this->assertStringContainsString("request()->routeIs('dashboard', 'dashboard.simpanan') ? 'off' : 'on'", $layout);
        $this->assertStringContainsString('body[data-abah-table-freeze="on"] .content-wrapper .abah-table-managed thead th', $layout);
        $this->assertMatchesRegularExpression('/\.main-header\.modern-navbar\s*\{[^}]*position:\s*sticky;/s', $layout);
        $this->assertStringContainsString('.abah-floating-table-header[hidden]', $layout);
        $this->assertStringContainsString('const freezeTableHeaders = document.body.dataset.abahTableFreeze === \'on\';', $layout);
        $this->assertStringContainsString('getFloatingCandidate', $layout);
        $this->assertStringContainsString("table.querySelector(':scope > colgroup')", $layout);
        $this->assertStringContainsString("cloneColumn.style.setProperty('width', columnWidth + 'px', 'important');", $layout);
        $this->assertStringContainsString('floatingHeader.inert = true;', $layout);
        $this->assertStringContainsString("document.addEventListener('scroll', function () { scheduleFloatingHeader(false); }", $layout);
        $this->assertStringContainsString('syncScrollableWrapper(table, wrapper)', $layout);
        $this->assertStringContainsString("wrapper.dataset.abahManagedRole = '1';", $layout);
        $this->assertStringContainsString("wrapper.dataset.abahManagedLabel = '1';", $layout);
        $this->assertStringContainsString('href="#main-content"', $layout);
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
        $this->assertStringContainsString('max-height: none !important', $layout);
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
        $this->assertStringContainsString('max-height: none !important;', $layout);
        $this->assertStringContainsString('.swal2-popup', $layout);
        $this->assertStringContainsString('.modal-body', $layout);
        $this->assertStringNotContainsString('[class*="-filter-"]', $layout);
        $this->assertStringNotContainsString('[class*="filter-"]', $layout);
        $this->assertStringNotContainsString('[class*="-title"]', $layout);
        $this->assertStringNotContainsString('min-width: 320px;', $layout);
    }

    public function test_public_and_standalone_surfaces_use_the_mobile_safe_viewport_contract(): void
    {
        $paths = [
            'views/layouts/guest.blade.php',
            'views/auth/login.blade.php',
            'views/errors/503.blade.php',
            'views/errors/database-unavailable.blade.php',
            'views/presentation.blade.php',
        ];

        foreach ($paths as $path) {
            $source = file_get_contents(resource_path($path));

            $this->assertStringContainsString('viewport-fit=cover', $source, $path);
            $this->assertStringContainsString('interactive-widget=resizes-content', $source, $path);
        }
    }

    public function test_shared_formal_theme_covers_keyboard_touch_and_high_contrast_inputs(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));
        $guestCss = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('--app-success-soft:', $layout);
        $this->assertStringContainsString('.content-wrapper .btn-outline-primary', $layout);
        $this->assertStringContainsString('.content-wrapper .page-link', $layout);
        $this->assertStringContainsString('[data-ui="empty"]', $layout);
        $this->assertStringContainsString('@media (forced-colors: active)', $layout);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $guestCss);
        $this->assertStringContainsString('accent-color: #0857c3;', $guestCss);
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
        $this->assertStringContainsString('max-height: none !important;', $style);
        $this->assertStringContainsString('overflow-y: visible;', $style);
        $this->assertStringContainsString('scrollbar-gutter: auto;', $style);
        $this->assertStringNotContainsString('scrollbar-gutter: stable both-edges;', $style);
        $this->assertStringNotContainsString('top: var(--table-sticky-top);', $style);
    }

    public function test_navbar_and_content_wrapper_have_no_invisible_left_margin_when_minimized(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));
        $sidebar = file_get_contents(resource_path('views/layouts/sidebar.blade.php'));

        $desktopMediaPos = strpos($layout, '@media (min-width: 992px)');
        $this->assertNotFalse($desktopMediaPos);
        $marginLeft48Pos = strpos($layout, 'margin-left: 4.8rem !important;', $desktopMediaPos);
        $this->assertNotFalse($marginLeft48Pos);
        $this->assertSame(1, substr_count($layout, 'margin-left: 4.8rem !important;'));

        $mobileMediaPos = strrpos($layout, '@media (max-width: 991.98px)');
        $this->assertNotFalse($mobileMediaPos);
        $this->assertStringContainsString('margin-left: 0 !important;', substr($layout, $mobileMediaPos, 500));

        $this->assertStringContainsString('body:not(.sidebar-open) .main-sidebar', $sidebar);
        $this->assertStringContainsString('margin-left: -250px !important;', $sidebar);
        $this->assertStringContainsString('body.is-resizing', $layout);
        $this->assertStringContainsString('transition: margin-left 0.3s cubic-bezier(0.2, 0.8, 0.2, 1)', $layout);
    }
}
