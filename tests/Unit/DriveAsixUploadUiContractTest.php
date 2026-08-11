<?php

namespace Tests\Unit;

use Tests\TestCase;

class DriveAsixUploadUiContractTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        $source = file_get_contents(resource_path('views/drive/index.blade.php'));
        self::assertIsString($source);
        $this->source = $source;
    }

    public function test_upload_uses_sequential_batches_and_locks_repeated_interaction(): void
    {
        $this->assertStringContainsString('const UPLOAD_BATCH_SIZE = 10;', $this->source);
        $this->assertStringContainsString('let uploadInFlight = false;', $this->source);
        $this->assertStringContainsString(
            'offset += UPLOAD_BATCH_SIZE',
            $this->source
        );
        $this->assertStringContainsString(
            'selectedFiles.slice(offset, offset + UPLOAD_BATCH_SIZE)',
            $this->source
        );
        $this->assertStringContainsString(
            'for (activeBatch = 0; activeBatch < batches.length; activeBatch++)',
            $this->source
        );
        $this->assertStringContainsString(
            'if ($uploadButton) $uploadButton.disabled = busy;',
            $this->source
        );
        $this->assertStringContainsString('if ($fi) $fi.disabled = busy;', $this->source);
        $this->assertStringContainsString(
            'if (uploadInFlight || !files?.length || !$uf) return;',
            $this->source
        );
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($this->source, "if (\$fi) \$fi.value = '';")
        );
    }

    public function test_progress_separates_transport_validation_success_and_error_states(): void
    {
        $this->assertStringContainsString(
            "xhr.upload.addEventListener('progress'",
            $this->source
        );
        $this->assertStringContainsString(
            "xhr.upload.addEventListener('load'",
            $this->source
        );
        $this->assertStringContainsString(
            'Mengunggah batch ${batchIndex + 1}/${batchCount}... ${aggregatePercent}%',
            $this->source
        );
        $this->assertStringContainsString(
            'Memvalidasi dan menyimpan... (batch ${batchIndex + 1}/${batchCount})',
            $this->source
        );
        $this->assertStringContainsString(
            'Math.min(currentWidth, 96)',
            $this->source
        );
        $this->assertStringContainsString('.dv-progress.is-validating', $this->source);
        $this->assertStringContainsString('.dv-progress.is-error', $this->source);
        $this->assertStringContainsString(
            "setUploadProgress(\n      100,",
            $this->source
        );
    }

    public function test_success_contract_requires_exact_json_count_and_same_origin_redirect(): void
    {
        $this->assertStringContainsString('xhr.status === 201', $this->source);
        $this->assertStringContainsString(
            "xhr.getResponseHeader('Content-Type')",
            $this->source
        );
        $this->assertStringContainsString(
            'Number.isInteger(payload.uploaded_count)',
            $this->source
        );
        $this->assertStringContainsString(
            'payload.uploaded_count === batchFiles.length',
            $this->source
        );
        $this->assertStringContainsString(
            'uploadedTotal !== selectedFiles.length',
            $this->source
        );
        $this->assertStringContainsString(
            'url.origin !== window.location.origin',
            $this->source
        );
        $this->assertStringContainsString(
            'window.location.assign(finalRedirect.href);',
            $this->source
        );
        $this->assertStringNotContainsString('location.reload()', $this->source);
    }

    public function test_validation_error_maps_files_index_to_name_and_reports_failed_batch(): void
    {
        $this->assertStringContainsString(
            'key.match(/^files\.(\d+)(?:\.|$)/)',
            $this->source
        );
        $this->assertStringContainsString(
            '`${file.name}: ${message}`',
            $this->source
        );
        $this->assertStringContainsString(
            '`Batch ${batchNumber}/${batches.length} gagal.',
            $this->source
        );
        $this->assertStringContainsString(
            '${uploadedTotal} file dari batch sebelumnya sudah tersimpan.',
            $this->source
        );
        $this->assertStringContainsString(
            "setUploadProgress(\n    Number.isFinite(currentWidth) ? Math.min(currentWidth, 96) : 0,",
            $this->source
        );
    }
}
