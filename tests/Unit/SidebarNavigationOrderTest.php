<?php

namespace Tests\Unit;

use Tests\TestCase;

class SidebarNavigationOrderTest extends TestCase
{
    public function test_primary_dashboard_groups_use_the_requested_sidebar_order(): void
    {
        $source = file_get_contents(resource_path('views/layouts/sidebar.blade.php'));
        $expectedOrder = [
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
}
