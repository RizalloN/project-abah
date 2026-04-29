<?php

namespace App\Support;

use App\Services\Import\ImportProgressService;
use App\Services\Import\ImportNotificationSyncService;
use Illuminate\Support\Facades\Log;

/**
 * CRITICAL: Handle preview errors atomically to prevent notification desynchronization
 *
 * Problem: Preview sends error_msg event, but job still gets created/runs
 * Solution: Record all preview errors, prevent job creation if errors exist
 */
class ImportPreviewErrorHandler
{
    public function __construct(
        private readonly ImportNotificationSyncService $notificationSync,
        private readonly ImportProgressService $progressService,
    ) {
    }

    public function handlePreviewError(
        string $fileIdentifier,
        string $errorMessage,
        ?int $jobId = null,
        ?string $errorCode = null
    ): void {
        $this->recordPreviewError($fileIdentifier, $errorMessage, $errorCode);

        if ($jobId !== null) {
            $this->markJobAsFailed($jobId, $errorMessage);
        }

        Log::warning('Import preview error handled', [
            'file_identifier' => $fileIdentifier,
            'job_id' => $jobId,
            'error' => $errorMessage,
            'error_code' => $errorCode,
        ]);
    }

    public function handleMultiplePreviewErrors(
        string $fileIdentifier,
        array $errors,
        ?int $jobId = null
    ): void {
        $this->notificationSync->recordPreviewValidation($fileIdentifier, false, $errors);

        if ($jobId !== null) {
            $this->markJobAsFailed(
                $jobId,
                'Preview validation failed: ' . implode('; ', array_slice($errors, 0, 3))
            );
        }

        Log::error('Import preview multiple errors', [
            'file_identifier' => $fileIdentifier,
            'job_id' => $jobId,
            'error_count' => count($errors),
            'sample_errors' => array_slice($errors, 0, 3),
        ]);
    }

    public function recordPreviewError(string $fileIdentifier, string $errorMessage, ?string $errorCode = null): void
    {
        $state = $this->notificationSync->getPreviewValidation($fileIdentifier);

        $errors = $state['errors'] ?? [];
        if (!in_array($errorMessage, $errors, true)) {
            $errors[] = $errorMessage;
        }

        $this->notificationSync->recordPreviewValidation($fileIdentifier, false, $errors);

        Log::debug('Preview error recorded', [
            'file_identifier' => $fileIdentifier,
            'error' => $errorMessage,
            'error_code' => $errorCode,
            'total_errors' => count($errors),
        ]);
    }

    public function markJobAsFailed(int $jobId, string $reason): void
    {
        try {
            $this->progressService->markFailed($jobId, "Preview validation failed: {$reason}");

            Log::error('Job marked as failed due to preview validation', [
                'job_id' => $jobId,
                'reason' => $reason,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to mark job as failed', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function assertPreviewValid(string $fileIdentifier, int $jobId): void
    {
        if (!$this->notificationSync->isPreviewValid($fileIdentifier)) {
            $preview = $this->notificationSync->getPreviewValidation($fileIdentifier);
            $errors = $preview['errors'] ?? ['Unknown error'];

            throw new \Exception(
                'Preview validation failed before import: ' . implode('; ', array_slice($errors, 0, 3)),
                400
            );
        }
    }
}
