<?php

namespace App\Services\Import;

use App\Support\Import\ImportSessionContext;
use Illuminate\Support\Facades\Cache;

class ExcelImportJobService
{
    public const SESSION_CONTEXT_KEY = 'session_context';

    private const PREVIEW_STATE_PREFIX = 'excel_preview_state_';

    public function __construct(private readonly ImportProgressService $progressService)
    {
    }

    public function createImportJobRecord(int $reportId, string $path, int $totalFiles = 0, array $jobContext = [], ?int $createdBy = null): int
    {
        return $this->progressService->createJob([
            'id_report' => $reportId,
            'file_name' => basename($path),
            'folder_path' => dirname($path),
            'status' => 'queued',
            'total_files' => $totalFiles,
            'total_success' => 0,
            'total_failed' => 0,
            'created_by' => $createdBy ?? auth()->id() ?? 1,
            'job_context' => $jobContext,
        ]);
    }

    public function putPreviewState(string $key, array $payload): void
    {
        $payload['_owner_id'] = auth()->id();

        // Konteks session milik preview ini dicatat sekali (saat upload/preview pertama)
        // dan tidak boleh tergantikan oleh session tab lain ketika state diperbarui.
        if (!array_key_exists(self::SESSION_CONTEXT_KEY, $payload)) {
            $existing = $this->getPreviewState($key);
            if (is_array($existing[self::SESSION_CONTEXT_KEY] ?? null)) {
                $payload[self::SESSION_CONTEXT_KEY] = $existing[self::SESSION_CONTEXT_KEY];
            } elseif (app()->bound('request') && request()->hasSession()) {
                $payload[self::SESSION_CONTEXT_KEY] = ImportSessionContext::capture(request()->session());
            }
        }

        Cache::put($this->previewStateKey($key), $payload, now()->addMinutes($this->previewStateTtlMinutes()));
    }

    /**
     * Daftarkan konteks session upload yang baru dibuat, sebelum preview dirender.
     */
    public function rememberPreviewSessionContext(string $key, array $context): void
    {
        if ($key === '' || $context === []) {
            return;
        }

        $existing = $this->getPreviewState($key);
        if (is_array($existing[self::SESSION_CONTEXT_KEY] ?? null)) {
            return;
        }

        $this->putPreviewState($key, array_merge($existing, [self::SESSION_CONTEXT_KEY => $context]));
    }

    public function previewStateTtlMinutes(): int
    {
        return max(30, (int) config('import.preview_state_ttl_minutes', 360));
    }

    public function getPreviewState(?string $key): array
    {
        if (!$key) {
            return [];
        }

        $cached = Cache::get($this->previewStateKey($key));
        if (!is_array($cached)) {
            return [];
        }

        if (auth()->check()) {
            $ownerId = $cached['_owner_id'] ?? null;
            if ($ownerId === null || (string) $ownerId !== (string) auth()->id()) {
                return [];
            }
        }

        return $cached;
    }

    public function putImportJobState(int $jobId, array $payload): void
    {
        if ($jobId <= 0) {
            return;
        }

        $tableName = strtolower(trim((string) ($payload['params']['table_name'] ?? '')));
        if ($tableName === DlyKapResegmentasiCsvImporter::TABLE) {
            $payload['headers'] = DlyKapResegmentasiCsvImporter::NORMALIZED_HEADERS;
        }

        $this->progressService->cacheJobState($jobId, $payload);
    }

    public function getImportJobState(int $jobId): array
    {
        if ($jobId <= 0) {
            return [];
        }

        return $this->progressService->getJobState($jobId);
    }

    private function previewStateKey(string $key): string
    {
        return self::PREVIEW_STATE_PREFIX . $key;
    }
}
