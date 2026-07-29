<?php

namespace Tests\Unit;

use Tests\TestCase;

class LandingPageRedesignContractTest extends TestCase
{
    public function test_landing_renders_recovery_as_the_fourth_area_metric_card(): void
    {
        $view = file_get_contents(resource_path('views/dashboard.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/DashboardSimpananController.php'));

        $this->assertStringContainsString("['os', 'sml', 'npl', 'recovery']", $view);
        $this->assertStringContainsString('grid-template-columns: repeat(4, minmax(0, 1fr)) !important;', $view);
        $this->assertStringContainsString('.ap-header.bg-recovery,', $view);
        $this->assertStringContainsString("'key' => 'recovery'", $controller);
        $this->assertStringContainsString("'header_title' => 'RECOVERY DH'", $controller);
        $this->assertStringContainsString("firstWhere('key', 'rec_dh_total')", $controller);
    }

    public function test_landing_navigation_is_grouped_under_dashboard_pinjaman(): void
    {
        $sidebar = file_get_contents(resource_path('views/layouts/sidebar.blade.php'));
        $simpananStart = strpos($sidebar, 'sidebar-dashboard-simpanan');
        $pinjamanStart = strpos($sidebar, 'sidebar-dashboard-pinjaman');
        $almafactsStart = strpos($sidebar, 'sidebar-dashboard-almafacts');

        $this->assertNotFalse($simpananStart);
        $this->assertNotFalse($pinjamanStart);
        $this->assertNotFalse($almafactsStart);

        $simpananBlock = substr($sidebar, $simpananStart, $pinjamanStart - $simpananStart);
        $pinjamanBlock = substr($sidebar, $pinjamanStart, $almafactsStart - $pinjamanStart);

        $this->assertStringNotContainsString('<p>Landing Page</p>', $simpananBlock);
        $this->assertStringContainsString('<p>Landing Page</p>', $pinjamanBlock);
        $this->assertStringContainsString("request()->routeIs('dashboard', 'report.dashboard-pinjaman*", $pinjamanBlock);
    }
}
