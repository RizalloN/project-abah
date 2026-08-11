<?php

namespace Tests\Unit;

use Tests\TestCase;

class ResponsiveUiAuditScriptTest extends TestCase
{
    public function test_audit_covers_landscape_and_laptop_viewports_with_configurable_timeouts(): void
    {
        $script = file_get_contents(base_path('scripts/responsive-ui-audit.mjs'));

        $this->assertStringContainsString("name: 'phone-landscape', width: 844, height: 390", $script);
        $this->assertStringContainsString("name: 'tablet-landscape', width: 1024, height: 768", $script);
        $this->assertStringContainsString("name: 'laptop', width: 1366, height: 768", $script);
        $this->assertStringContainsString('AUDIT_NAVIGATION_TIMEOUT_MS', $script);
        $this->assertStringContainsString('AUDIT_LOGIN_TIMEOUT_MS', $script);
        $this->assertStringContainsString('AUDIT_WAIT_TIMEOUT_MS', $script);
        $this->assertStringContainsString('AUDIT_CHROME_TIMEOUT_MS', $script);
    }

    public function test_sticky_audit_is_generic_and_checks_both_scroll_axes_and_backgrounds(): void
    {
        $script = file_get_contents(base_path('scripts/responsive-ui-audit.mjs'));

        $this->assertStringContainsString('findScrollHost', $script);
        $this->assertStringContainsString('inspectStickyTable', $script);
        $this->assertStringContainsString("document.querySelectorAll('table')", $script);
        $this->assertStringContainsString('verticalHeader', $script);
        $this->assertStringContainsString('noOverlap', $script);
        $this->assertStringContainsString('horizontalColumns', $script);
        $this->assertStringContainsString('transparentStickyCells', $script);
        $this->assertStringContainsString('nestedVerticalTableScrolls', $script);
        $this->assertStringContainsString('host.scrollHeight > host.clientHeight + 1', $script);
        $this->assertStringContainsString('visibleRectWithinAncestors', $script);
        $this->assertStringContainsString("current.getAttribute('aria-hidden') === 'true'", $script);
        $this->assertStringContainsString('headingText:', $script);
        $this->assertStringContainsString('controlRect:', $script);
        $this->assertStringContainsString('stickyAudits.some(stickyAuditFailed)', $script);
        $this->assertStringNotContainsString("wrapper.querySelector('.loan-summary-table')", $script);
    }
}
