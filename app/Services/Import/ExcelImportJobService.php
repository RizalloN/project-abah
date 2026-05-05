<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\Cache;

class ExcelImportJobService
{
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
        Cache::put($this->previewStateKey($key), $payload, now()->addMinutes(30));
    }

    public function getPreviewState(?string $key): array
    {
        if (!$key) {
            return [];
        }

        $cached = Cache::get($this->previewStateKey($key));

        return is_array($cached) ? $cached : [];
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
