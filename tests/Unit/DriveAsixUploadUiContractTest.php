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

    public function test_upload_uses_a_sequential_single_file_queue_and_locks_repeated_interaction(): void
    {
        $this->assertStringContainsString('const UPLOAD_BATCH_SIZE = 1;', $this->source);
        $this->assertStringContainsString(
            'const MAX_CONSECUTIVE_UPLOAD_FAILURES = 3;',
            $this->source
        );
        $this->assertStringContainsString('xhr.timeout = UPLOAD_TIMEOUT_MS;', $this->source);
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
            'for (let activeBatch = 0; activeBatch < batches.length; activeBatch++)',
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
            'Mengunggah file ${batchIndex + 1}/${batchCount}... ${aggregatePercent}%',
            $this->source
        );
        $this->assertStringContainsString(
            'Memvalidasi dan menyimpan file ${batchIndex + 1}/${batchCount}...',
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
            'uploadedTotal === selectedFiles.length',
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

    public function test_validation_error_maps_the_file_name_and_queue_continues_safely(): void
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
            'failedUploads.push({',
            $this->source
        );
        $this->assertStringContainsString(
            'consecutiveTransportFailures >= MAX_CONSECUTIVE_UPLOAD_FAILURES',
            $this->source
        );
        $this->assertStringContainsString(
            "queueStopReason = 'karena koneksi tidak stabil';",
            $this->source
        );
        $this->assertStringContainsString(
            "queueStopReason = 'karena sesi atau izin akses perlu diperbarui';",
            $this->source
        );
        $this->assertStringContainsString(
            "setUploadProgress(\n    Number.isFinite(currentWidth) ? Math.min(currentWidth, 96) : 0,",
            $this->source
        );
    }

    public function test_multi_selection_and_all_delete_confirmations_use_sweetalert(): void
    {
        $this->assertStringContainsString('data-select-key="folder:', $this->source);
        $this->assertStringContainsString('data-select-key="file:', $this->source);
        $this->assertStringContainsString('class="dv-select-checkbox"', $this->source);
        $this->assertStringContainsString('id="btnSelectAll"', $this->source);
        $this->assertStringContainsString('id="btnDeleteSelected"', $this->source);
        $this->assertStringContainsString('const selectedDriveItems = new Map();', $this->source);
        $this->assertStringContainsString('async function deleteSelectedDriveItems()', $this->source);
        $this->assertStringContainsString(
            'for (const [index, item] of selectedItems.entries())',
            $this->source
        );
        $this->assertStringContainsString("method: 'DELETE'", $this->source);
        $this->assertStringContainsString('function driveSwal(options)', $this->source);
        $this->assertStringContainsString("document.querySelectorAll('.js-drive-confirm')", $this->source);
        $this->assertStringNotContainsString('confirm(', $this->source);
        $this->assertStringNotContainsString('onsubmit="return confirm', $this->source);
    }

    public function test_bank_pipeline_exposes_executive_summary_and_drag_drop_contract(): void
    {
        $this->assertStringContainsString("@section('title', 'Bank Pipeline')", $this->source);
        $this->assertStringContainsString('id="bankPipelineSummary"', $this->source);
        $this->assertStringContainsString("route('drive.pipeline-summary')", $this->source);
        $this->assertStringContainsString('id="pipelineTotal"', $this->source);
        $this->assertStringContainsString('id="pipelineFollowed"', $this->source);
        $this->assertStringContainsString('id="pipelineFollowUpPercentage"', $this->source);
        $this->assertStringContainsString('id="pipelineBranchRows"', $this->source);
        $this->assertStringContainsString('totals.follow_up_percentage ?? totals.progress', $this->source);
        $this->assertStringContainsString('Persentase TL = pipeline sudah TL / jumlah pipeline.', $this->source);
        $this->assertStringNotContainsString('id="pipelinePending"', $this->source);
        $this->assertStringNotContainsString('id="pipelineUnclassified"', $this->source);
        $this->assertStringNotContainsString('id="pipelineSourceList"', $this->source);
        $this->assertStringContainsString('draggable="true"', $this->source);
        $this->assertStringContainsString('async function moveDraggedDriveItem(destinationId)', $this->source);
        $this->assertStringContainsString("method: 'PATCH'", $this->source);
        $this->assertStringContainsString('data-drop-folder-id=', $this->source);
    }
}
