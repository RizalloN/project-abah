<?php

namespace Tests\Unit;

use Tests\TestCase;

class SidebarNavigationOrderTest extends TestCase
{
    public function test_primary_dashboard_groups_use_the_requested_sidebar_order(): void
    {
        $source = file_get_contents(resource_path('views/layouts/sidebar.blade.php'));
        $expectedOrder = [
            'sidebar-dashboard-landing' => '-7',
            'sidebar-dashboard-marketshare' => '-6',
            'sidebar-dashboard-almafacts' => '-5',
            'sidebar-dashboard-harian' => '-4',
            'sidebar-dashboard-simpanan' => '-3',
            'sidebar-dashboard-pinjaman' => '-2',
            'sidebar-dashboard-kpi' => '-1',
        ];

        foreach ($expectedOrder as $class => $order) {
            $this->assertStringContainsString('> .' . $class . ' {', $source);
            $this->assertStringContainsString('order: ' . $order . ';', $source);
            $this->assertStringContainsString('nav-item ' . $class, $source);
        }
    }

    public function test_dashboard_pinjaman_exposes_internal_run_off_report(): void
    {
        $source = file_get_contents(resource_path('views/layouts/sidebar.blade.php'));

        $this->assertStringContainsString("route('report.dashboard-pinjaman.run-off')", $source);
        $this->assertStringContainsString('<p>Run OFF</p>', $source);
        $this->assertStringNotContainsString('1sFCcyfUadZq5ZVUrFtLeCDhpz_r_B8aj', $source);
    }

    public function test_sidebar_landing_page_is_dropdown_with_pinjaman_and_simpanan_links(): void
    {
        $source = file_get_contents(resource_path('views/layouts/sidebar.blade.php'));

        $this->assertStringContainsString('sidebar-dashboard-landing', $source);
        $this->assertStringContainsString("route('dashboard')", $source);
        $this->assertStringContainsString('Landing Page Pinjaman', $source);
        $this->assertStringContainsString("route('dashboard.simpanan')", $source);
        $this->assertStringContainsString('Landing Page Simpanan', $source);
        $this->assertStringContainsString('right fas fa-angle-left', $source);
    }
}
