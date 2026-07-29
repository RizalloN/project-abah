<?php

namespace Tests\Unit;

use Tests\TestCase;

class UgNplResponsiveViewTest extends TestCase
{
    public function test_ug_npl_view_contains_responsive_table_and_request_guards(): void
    {
        $view = file_get_contents(resource_path('views/report/dashboard-pinjaman/analisa-ug-npl.blade.php'));

        $this->assertStringContainsString('grid-template-columns: repeat(6, minmax(0, 1fr)) auto;', $view);
        $this->assertStringContainsString('@media (max-width: 1399.98px)', $view);
        $this->assertStringContainsString('overflow: auto;', $view);
        $this->assertStringContainsString('scrollbar-gutter: stable;', $view);
        $this->assertStringNotContainsString('scrollbar-gutter: stable both-edges;', $view);
        $this->assertStringContainsString('.ug-npl-table th:first-child', $view);
        $this->assertStringContainsString('.ug-npl-table td:first-child:not([colspan])', $view);
        $this->assertStringContainsString('position: sticky;', $view);
        $this->assertStringContainsString('AbortController', $view);
        $this->assertStringContainsString('ResizeObserver', $view);
        $this->assertStringContainsString('visualViewport?.addEventListener', $view);
        $this->assertStringContainsString('escapeHtml', $view);
    }
}
