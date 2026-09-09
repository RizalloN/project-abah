<?php

namespace Tests\Unit;

use Tests\TestCase;

class StickyTableViewportScriptTest extends TestCase
{
    public function test_compact_viewport_releases_inline_height_to_the_bounded_css_contract(): void
    {
        $script = file_get_contents(
            resource_path('views/report/partials/sticky-table-viewport-script.blade.php')
        );

        $this->assertStringContainsString("wrapper.style.height = 'auto';", $script);
        $this->assertStringContainsString("wrapper.style.maxHeight = 'none';", $script);
    }

    public function test_observers_are_disconnected_during_internal_style_synchronization(): void
    {
        $script = file_get_contents(
            resource_path('views/report/partials/sticky-table-viewport-script.blade.php')
        );

        $disconnectPosition = strpos($script, 'observer.disconnect();');
        $syncPosition = strpos($script, 'wrappers.forEach(syncWrapperViewport);');
        $reobservePosition = strpos($script, 'wrappers.forEach(observeWrapper);', $syncPosition);

        $this->assertNotFalse($disconnectPosition);
        $this->assertNotFalse($syncPosition);
        $this->assertNotFalse($reobservePosition);
        $this->assertLessThan($syncPosition, $disconnectPosition);
        $this->assertLessThan($reobservePosition, $syncPosition);
        $this->assertStringContainsString(
            "attributeFilter: ['class', 'style', 'hidden']",
            $script
        );
    }
}
