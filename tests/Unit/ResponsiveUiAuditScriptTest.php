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
        $this->assertStringContainsString('AUDIT_SCROLL_SELECTOR', $script);
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
        $this->assertStringContainsString('clippedCards', $script);
        $this->assertStringContainsString('overflowingChildren', $script);
        $this->assertStringContainsString('childRect.right > rect.right + 2', $script);
        $this->assertStringContainsString('narrowHeadings', $script);
        $this->assertStringContainsString('rect.width < 72', $script);
        $this->assertStringContainsString('stickyAudits.some(stickyAuditFailed)', $script);
        $this->assertStringNotContainsString("wrapper.querySelector('.loan-summary-table')", $script);
    }

    public function test_audit_can_activate_and_verify_the_micro_landing_scope(): void
    {
        $script = file_get_contents(base_path('scripts/responsive-ui-audit.mjs'));

        $this->assertStringContainsString('AUDIT_LANDING_SCOPE', $script);
        $this->assertStringContainsString('[data-area6-scope="${landingScope}"]', $script);
        $this->assertStringContainsString("[data-area6-scope=\"' + scope + '\"]", $script);
        $this->assertStringContainsString('data-micro-performance-ready', $script);
        $this->assertStringContainsString('data-consumer-operations-ready', $script);
        $this->assertStringContainsString('consumerDeskVisible', $script);
        $this->assertStringContainsString('consumerIllustrationVisible', $script);
        $this->assertStringContainsString('consumerPipelineTableCount', $script);
        $this->assertStringContainsString('consumerKprPipelineVisible', $script);
        $this->assertStringContainsString('consumerKprPipelineTableCount', $script);
        $this->assertStringContainsString('consumerQuadrantProductCount', $script);
        $this->assertStringContainsString('consumerArea6TriggerCount', $script);
        $this->assertStringContainsString('consumerQuadrantDetailCount', $script);
        $this->assertStringContainsString('hasSeparateConsumerQuadrants', $script);
        $this->assertStringContainsString('segmentContainersVisible', $script);
        $this->assertStringContainsString('microProductRows', $script);
        $this->assertStringContainsString('microProductRowCount', $script);
        $this->assertStringContainsString("state.microProductRows.includes('OS MIKRO')", $script);
        $this->assertStringContainsString('recoveryVisible', $script);
        $this->assertStringContainsString('illustrationVisible', $script);
        $this->assertStringContainsString('mantriIllustrationVisible', $script);
        $this->assertStringContainsString('realizationCardCount', $script);
        $this->assertStringContainsString('mantriSummaryVisible', $script);
        $this->assertStringContainsString('mantriTierTableCount', $script);
        $this->assertStringContainsString('rmKurProductivityVisible', $script);
        $this->assertStringContainsString('rmKurTableCount', $script);
        $this->assertStringContainsString('hasRmKurProductivityLabel', $script);
        $this->assertStringContainsString('state.rmKurTableCount !== 1', $script);
        $this->assertStringContainsString('hasPlafondMetric', $script);
        $this->assertStringContainsString('hasNettMetric', $script);
        $this->assertStringContainsString('hasRunoffMetric', $script);
        $this->assertStringContainsString('hasPhMetric', $script);
        $this->assertStringContainsString('hasCifLabel', $script);
        $this->assertStringContainsString('qualityCompositionVisible', $script);
        $this->assertStringContainsString('qualityCompositionRowCount', $script);
        $this->assertStringContainsString('state.realizationCardCount !== 4', $script);
        $this->assertStringContainsString('state.qualityCompositionRowCount !== 7', $script);
        $this->assertStringContainsString('musimanBreakdownVisible', $script);
        $this->assertStringContainsString('billingInteractionFunctional', $script);
        $this->assertStringContainsString('billingInteractionDiagnostics', $script);
        $this->assertStringContainsString("[data-billing-view=\"m1\"]", $script);
        $this->assertStringContainsString("billingGrid?.classList.contains('view-mode-m1')", $script);
        $this->assertStringContainsString('gridColumnCount', $script);
        $this->assertStringContainsString('centerDelta', $script);
        $this->assertStringContainsString('lastCardCenterDelta', $script);
        $this->assertStringContainsString('landingScopeAuditFailed(result)', $script);
    }
}
