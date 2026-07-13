<?php

namespace Tests\Unit;

use Tests\TestCase;

class ImportPreviewFilterViewTest extends TestCase
{
    public function test_import_preview_filter_dropdown_uses_portal_above_scrollable_table(): void
    {
        $source = file_get_contents(resource_path('views/import/preview.blade.php'));

        $this->assertStringContainsString('class="table-responsive import-preview-table-shell"', $source);
        $this->assertStringNotContainsString('class="table-responsive import-preview-table-shell" style="min-height: 450px; max-height: 600px; overflow-y: auto; overflow-x: auto;"', $source);
        $this->assertStringContainsString('.import-preview-filter-menu-portal', $source);
        $this->assertStringContainsString('z-index: 2147483000 !important;', $source);
        $this->assertStringContainsString("document.body.appendChild(menu);", $source);
        $this->assertStringContainsString("menu.setAttribute('data-portal-filter-open', '1');", $source);
        $this->assertStringContainsString("menu.style.visibility = 'hidden';", $source);
        $this->assertStringContainsString("menu.style.opacity = '0';", $source);
        $this->assertStringContainsString("searchInput.focus({ preventScroll: true });", $source);
        $this->assertStringContainsString('function getFilterDropdownFromMenu(menu)', $source);
        $this->assertStringContainsString('function getFilterMenuForDropdown(dropdown)', $source);
        $this->assertStringContainsString('const menu = getFilterMenuForDropdown(dropdown);', $source);
        $this->assertStringContainsString('const preferredMenuWidth = viewportWidth <= 420', $source);
        $this->assertStringContainsString('width: min(380px, calc(100vw - 24px)) !important;', $source);
        $this->assertStringContainsString('min-width: min(280px, calc(100vw - 24px)) !important;', $source);
        $this->assertStringContainsString("menu.style.visibility = 'visible';", $source);
        $this->assertStringContainsString("menu.style.opacity = '1';", $source);
        $this->assertStringContainsString("document.querySelectorAll('.import-preview-filter-dropdown.show').forEach(positionPortaledFilterMenu);", $source);
    }
}
