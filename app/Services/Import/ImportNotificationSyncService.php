<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CRITICAL: Synchronize notifications between preview phase and job execution phase
 * Prevents: Preview fails but job runs (2 conflicting notifications)
 * Ensures: Notifications match actual system state 100%
 */
class ImportNotificationSyncService
{
    private const PREVIEW_CACHE_PREFIX = 'import_preview_state:';
    private const PREVIEW_CACHE_TTL_MINUTES = 30;
    private const MAX_PREVIEW_ERRORS = 50;

    public function recordPreviewValidation(string $fileIdentifier, bool $isValid, array $errors = []): void
    {
        $state = [
            'is_valid' => $isValid,
            'error_count' => count($errors),
            'errors' => array_slice($errors, 0, self::MAX_PREVIEW_ERRORS),
            'validated_at' => now()->toIso8601String(),
        ];

        Cache::put(
            $this->previewKey($fileIdentifier),
            $state,
            now()->addMinutes(self::PREVIEW_CACHE_TTL_MINUTES)
        );

        Log::info('Import preview validation recorded', [
            'file_identifier' => $fileIdentifier,
            'is_valid' => $isValid,
            'error_count' => count($errors),
        ]);
    }

    public function getPreviewValidation(string $fileIdentifier): ?array
    {
        return Cache::get($this->previewKey($fileIdentifier));
    }

    public function isPreviewValid(string $fileIdentifier): bool
    {
        $state = $this->getPreviewValidation($fileIdentifier);
        return $state !== null && $state['is_valid'] === true;
    }

    public function canProceedToImport(string $fileIdentifier, array &$errorMessages = []): bool
    {
        $preview = $this->getPreviewValidation($fileIdentifier);

        if ($preview === null) {
            $errorMessages[] = 'Preview validation state tidak ditemukan. Silakan upload dan validasi ulang.';
            return false;
        }

        if ($preview['is_valid'] !== true) {
            $errorMessages = array_merge(
                $errorMessages,
                $preview['errors'] ?? ['Preview validation failed']
            );
            return false;
        }

        return true;
    }

    public function syncJobNotificationWithPreview(int $jobId, string $fileIdentifier): void
    {
        $job = DB::table('import_jobs')->where('id', $jobId)->first();
        if (!$job) {
            return;
        }

        $preview = $this->getPreviewValidation($fileIdentifier);

        if ($preview === null) {
            $this->markJobAsValidationMissing($jobId);
            return;
        }

        if ($preview['is_valid'] !== true) {
            $this->markJobAsValidationFailed($jobId, $preview['errors'] ?? []);
            return;
        }
    }

    public function validateBeforeImportDispatch(int $jobId, string $fileIdentifier): array
    {
        $job = DB::table('import_jobs')->where('id', $jobId)->first();
        if (!$job) {
            return [
                'status' => 'error',
                'message' => 'Job tidak ditemukan',
                'can_proceed' => false,
            ];
        }

        $preview = $this->getPreviewValidation($fileIdentifier);
        if ($preview === null) {
            return [
                'status' => 'error',
                'message' => 'Preview validation tidak ditemukan. Silakan validasi ulang file.',
                'can_proceed' => false,
            ];
        }

        if ($preview['is_valid'] !== true) {
            $errors = $preview['errors'] ?? ['Unknown validation error'];
            return [
                'status' => 'error',
                'message' => 'Preview validation gagal. ' . implode(', ', array_slice($errors, 0, 3)),
                'can_proceed' => false,
                'validation_errors' => $errors,
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Preview valid, import dapat dilanjutkan',
            'can_proceed' => true,
        ];
    }

    public function clearPreviewState(string $fileIdentifier): void
    {
        Cache::forget($this->previewKey($fileIdentifier));
        Log::info('Import preview state cleared', ['file_identifier' => $fileIdentifier]);
    }

    private function markJobAsValidationMissing(int $jobId): void
    {
        DB::table('import_jobs')->where('id', $jobId)->update([
            'status' => 'failed',
            'updated_at' => now(),
        ]);

        Log::error('Job marked failed: preview validation state missing', ['job_id' => $jobId]);
    }

    private function markJobAsValidationFailed(int $jobId, array $errors): void
    {
        DB::table('import_jobs')->where('id', $jobId)->update([
            'status' => 'failed',
            'updated_at' => now(),
        ]);

        Log::error('Job marked failed: preview validation failed', [
            'job_id' => $jobId,
            'errors' => array_slice($errors, 0, 5),
        ]);
    }

    private function previewKey(string $fileIdentifier): string
    {
        return self::PREVIEW_CACHE_PREFIX . md5($fileIdentifier);
    }
}
