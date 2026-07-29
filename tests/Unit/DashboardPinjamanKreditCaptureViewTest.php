<?php

namespace Tests\Unit;

use Tests\TestCase;

class DashboardPinjamanKreditCaptureViewTest extends TestCase
{
    public function test_dashboard_pinjaman_kredit_capture_uses_full_width_high_resolution_png(): void
    {
        $source = file_get_contents(base_path('resources/views/report/dashboard-pinjaman/kredit.blade.php'));

        $this->assertStringContainsString('function prepareLoanCaptureElement(section)', $source);
        $this->assertStringContainsString('function renderLoanSectionCanvas(section)', $source);
        $this->assertStringContainsString('function downloadCanvasAsPng(canvas, filename)', $source);
        $this->assertStringContainsString('function resetCaptureBackdrop()', $source);
        $this->assertStringContainsString('function closeCaptureModalSoon(delay = 900)', $source);
        $this->assertStringContainsString('function setCaptureStyle(el, property, value)', $source);
        $this->assertStringContainsString('function getFullCaptureHeight(clone)', $source);
        $this->assertStringContainsString("setCaptureStyle(wrap, 'overflow-y', 'visible')", $source);
        $this->assertStringContainsString("setCaptureStyle(wrap, 'max-height', 'none')", $source);
        $this->assertStringContainsString('table?.scrollWidth', $source);
        $this->assertStringContainsString('windowWidth: prepared.width', $source);
        $this->assertStringContainsString('windowHeight: prepared.height', $source);
        $this->assertStringContainsString('Math.min(4, Math.max(3', $source);
        $this->assertStringContainsString('image/png', $source);
        $this->assertStringContainsString("window.jQuery('.modal-backdrop').remove()", $source);
        $this->assertStringNotContainsString("toDataURL('image/jpeg'", $source);
        $this->assertStringNotContainsString("clone.style.left = '-100000px'", $source);
        $this->assertStringNotContainsString("clone.style.zIndex = '-1'", $source);
    }

    public function test_dashboard_pinjaman_kredit_does_not_show_area_6_consolidation_for_mikro(): void
    {
        $source = file_get_contents(base_path('resources/views/report/dashboard-pinjaman/kredit.blade.php'));

        $this->assertStringContainsString("consolidationSection.classList.add('d-none');", $source);
        $this->assertStringContainsString("consolidationTableContainer.innerHTML = '';", $source);
        $this->assertStringNotContainsString("kategori === 'Mikro' && (osTotal || smlTotal || nplTotal)", $source);
        $this->assertStringNotContainsString("consolidationSection.classList.remove('d-none');", $source);
    }

    public function test_dashboard_pinjaman_kredit_mikro_labels_parent_row_as_total_micro(): void
    {
        $source = file_get_contents(base_path('resources/views/report/dashboard-pinjaman/kredit.blade.php'));

        $this->assertStringContainsString("const showBranchSubtotal = segmentName !== 'Mikro';", $source);
        $this->assertStringContainsString("const isMikroTotalRow = segmentName === 'Mikro' && row.category === 'Micro';", $source);
        $this->assertStringContainsString("? 'TOTAL MICRO'", $source);
        $this->assertStringContainsString('loan-mikro-total-label', $source);
        $this->assertStringContainsString('font-weight: 900;', $source);
        $this->assertStringNotContainsString('TOTAL MICRO - ${branchName}', $source);
        $this->assertStringContainsString('if (showBranchSubtotal) {', $source);
    }

    public function test_dashboard_pinjaman_kredit_syncs_frozen_column_offsets_from_rendered_widths(): void
    {
        $source = file_get_contents(base_path('resources/views/report/dashboard-pinjaman/kredit.blade.php'));

        $this->assertStringContainsString('--loan-sticky-cabang-width', $source);
        $this->assertStringContainsString('--loan-sticky-kategori-width', $source);
        $this->assertStringContainsString('--loan-sticky-kategori-left', $source);
        $this->assertStringContainsString('--loan-sticky-total-width', $source);
        $this->assertStringContainsString('getBoundingClientRect().width', $source);
        $this->assertStringContainsString('function scheduleSummaryTableSync()', $source);
        $this->assertStringContainsString("window.addEventListener('orientationchange', scheduleSummaryTableSync)", $source);
    }
}
