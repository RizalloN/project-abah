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

    public function test_import_preview_uses_a_quiet_operational_workspace_layout(): void
    {
        $source = file_get_contents(resource_path('views/import/preview.blade.php'));

        $this->assertStringContainsString('form-inline import-preview-settings-form', $source);
        $this->assertStringContainsString('import-preview-settings-submit', $source);
        $this->assertStringContainsString('Quiet operational workspace', $source);
        $this->assertStringContainsString('background: #f8fafc;', $source);
        $this->assertStringContainsString('background: #0b5cab !important;', $source);
        $this->assertStringContainsString('border-radius: 8px;', $source);
    }

    public function test_import_surfaces_use_compact_progress_modals_without_duplicate_titles(): void
    {
        $preview = file_get_contents(resource_path('views/import/preview.blade.php'));
        $index = file_get_contents(resource_path('views/import/index.blade.php'));
        $excelPreview = file_get_contents(resource_path('views/import/preview_excel.blade.php'));

        $this->assertStringContainsString('Compact progress modal', $preview);
        $this->assertStringContainsString("etaInfo.innerText = 'Menunggu total';", $preview);
        $this->assertStringContainsString('Quiet upload workspace', $index);
        $this->assertStringContainsString('width: min(520px, calc(100vw - 24px)) !important;', $index);
        $this->assertStringNotContainsString('<div class="swal-import-title">${titleText}</div>', $index);
        $this->assertStringNotContainsString('<div class="swal-import-title">${loadingCopy.title}</div>', $excelPreview);
    }

    public function test_import_index_uses_a_single_step_by_step_upload_workspace(): void
    {
        $source = file_get_contents(resource_path('views/import/index.blade.php'));

        $this->assertStringContainsString('class="import-page"', $source);
        $this->assertStringContainsString('class="import-workflow"', $source);
        $this->assertStringContainsString('Konfigurasi report', $source);
        $this->assertStringContainsString('Pilih file', $source);
        $this->assertStringContainsString('Lanjut ke Preview', $source);
        $this->assertStringContainsString('Workflow layout: one clear path', $source);
        $this->assertStringContainsString('.import-template-bar .import-template-select {', $source);
        $this->assertStringContainsString('min-width: 0;', $source);
        $this->assertStringContainsString('.import-template-bar .import-template-select .select2-container {', $source);
    }

    public function test_import_index_blocks_oversized_direct_uploads_before_sending_them(): void
    {
        $source = file_get_contents(resource_path('views/import/index.blade.php'));

        $this->assertStringContainsString('let activeUploadLimits = null;', $source);
        $this->assertStringContainsString("const chunkedUploadEnabled = formImport?.dataset.chunkedUpload === '1';", $source);
        $this->assertStringContainsString('if (maxBytes > 0 && file.size > maxBytes && !chunkedUploadEnabled)', $source);
        $this->assertStringContainsString('Upload belum dijalankan agar koneksi tidak ditolak oleh server.', $source);
        $this->assertStringContainsString('activeUploadLimits?.effective_max_upload_bytes', $source);
        $this->assertStringContainsString('let nativeUploadFallbackStarted = false;', $source);
        $this->assertStringContainsString('uploadRequest.status === 0 && !nativeUploadFallbackStarted', $source);
        $this->assertStringContainsString("updateProgressSurface(3, 'Mencoba jalur upload standar...'", $source);
        $this->assertStringContainsString('HTMLFormElement.prototype.submit.call(formImport);', $source);
        $this->assertStringContainsString('const stagedUploadFiles = new WeakMap();', $source);
        $this->assertStringContainsString('async function stageUploadFile(input)', $source);
        $this->assertStringContainsString('const bytes = await file.arrayBuffer();', $source);
        $this->assertStringContainsString("formData.set('file', stagedUploadFile, stagedUploadFile.name);", $source);
        $this->assertStringNotContainsString('shouldWarnOnly', $source);
        $this->assertStringNotContainsString('Koneksi upload terputus sebelum file selesai dikirim.', $source);
    }

    public function test_cras_preview_preserves_filter_whitespace_and_locks_source_columns(): void
    {
        $preview = file_get_contents(resource_path('views/import/preview.blade.php'));
        $index = file_get_contents(resource_path('views/import/index.blade.php'));

        $this->assertStringContainsString('$preserveFilterValueWhitespace', $preview);
        $this->assertStringContainsString('preserveFilterValueWhitespace ? normalized : normalized.trim()', $preview);
        $this->assertStringContainsString('@if($lockColumnSelection)', $preview);
        $this->assertStringContainsString("const isCras = tableName === 'cras'", $index);
        $this->assertStringContainsString("formImport.dataset.chunkedUpload = '1';", $index);
        $this->assertStringContainsString("route('import.cras.upload-chunk.finalize')", $index);
        $this->assertStringContainsString("inputCsv.setAttribute('accept', '.csv,.txt,.xlsx');", $index);
        $this->assertStringContainsString('CSV/TXT UTF-16LE dan XLSX diproses tanpa membulatkan', $index);
    }

    public function test_brilink_summary_report_uses_csv_upload_with_area_6_notice(): void
    {
        $source = file_get_contents(resource_path('views/import/index.blade.php'));

        $this->assertStringContainsString("tableName === 'brilink_web_laporan_summary_transaksi_brilink_web'", $source);
        $this->assertStringContainsString('Upload BRILINK Web Summary (.csv, .txt)', $source);
        $this->assertStringContainsString('Data otomatis dibatasi ke KC Madiun, KC Magetan, KC Ngawi, dan KC Ponorogo.', $source);
        $this->assertStringContainsString("inputCsv.setAttribute('accept', '.csv,.txt');", $source);
    }
}
