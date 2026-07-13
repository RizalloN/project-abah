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
        $this->assertStringContainsString('grid-template-columns: repeat(2, minmax(min(100%, 220px), 1fr)) !important;', $source);
        $this->assertStringContainsString('grid-column: 1 / -1 !important;', $source);
        $this->assertStringContainsString('overflow-x: auto !important;', $source);
        $this->assertStringContainsString('-webkit-overflow-scrolling: touch;', $source);
    }
}
