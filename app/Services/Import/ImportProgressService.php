<?php

namespace App\Services\Import;

use App\Jobs\RunImportJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class ImportProgressService
{
    private const CACHE_PREFIX = 'import_job_progress:';
    private const STATE_PREFIX = 'excel_import_job:';
    private const STALE_QUEUED_MINUTES = 15;

    public function cacheProgress(int $jobId, array $payload): array
    {
        $payload['job_id'] = $jobId;
        $payload['updated_at'] = now()->toIso8601String();
        Cache::put($this->cacheKey($jobId), $payload, now()->addHours(6));

        return $payload;
    }

    public function updateJob(int $jobId, array $attributes, ?array $progressPayload = null): void
    {
        if ($attributes !== []) {
            $attributes['updated_at'] = now();
            DB::table('import_jobs')->where('id', $jobId)->update($attributes);
        }

        if ($progressPayload !== null) {
            $this->cacheProgress($jobId, $progressPayload);
        }
    }

    public function createJob(array $attributes): int
    {
        $attributes['created_at'] = $attributes['created_at'] ?? now();
        $attributes['updated_at'] = $attributes['updated_at'] ?? now();

        return (int) DB::table('import_jobs')->insertGetId($attributes);
    }

    public function findJob(int $jobId): ?object
    {
        return DB::table('import_jobs')->where('id', $jobId)->first();
    }

    public function cacheJobState(int $jobId, array $payload): void
    {
        Cache::put($this->stateKey($jobId), $payload, now()->addHours(6));
    }

    public function getJobState(int $jobId): array
    {
        $cached = Cache::get($this->stateKey($jobId));

        return is_array($cached) ? $cached : [];
    }

    public function markQueued(int $jobId, ?array $progressPayload = null): void
    {
        $this->updateJob($jobId, ['status' => 'queued'], $progressPayload);
    }

    public function markProcessing(int $jobId, ?array $progressPayload = null): void
    {
        $this->updateJob($jobId, ['status' => 'processing'], $progressPayload);
    }

    public function markCompleted(int $jobId, int $success, int $failed, int $totalRows, ?array $progressPayload = null): void
    {
        $this->updateTotals($jobId, $success, $failed, $totalRows, 'completed', $progressPayload);
    }

    public function markFailed(int $jobId, string $message, int $success = 0, int $failed = 0, ?string $status = null): void
    {
        $resolvedStatus = $status ?? ($success > 0 ? 'failed_partial' : 'failed');

        $this->updateTotals($jobId, $success, $failed, null, $resolvedStatus, [
            'status' => $resolvedStatus,
            'message' => $message,
            'total_success' => $success,
            'total_failed' => $failed,
        ]);
    }

    public function purgeStaleQueuedJobs(): int
    {
        $cutoff = now()->subMinutes(self::STALE_QUEUED_MINUTES);
        $staleJobs = DB::table('import_jobs')
            ->where('status', 'queued')
            ->where('updated_at', '<', $cutoff)
            ->orderBy('updated_at')
            ->get(['id', 'total_success', 'total_failed']);

        $purged = 0;
        foreach ($staleJobs as $job) {
            $jobId = (int) ($job->id ?? 0);
            if ($jobId <= 0) {
                continue;
            }

            $success = (int) ($job->total_success ?? 0);
            $failed = (int) ($job->total_failed ?? 0);
            $this->markFailed(
                $jobId,
                'Job import terlalu lama berada di antrian. Silakan ulangi proses import.',
                $success,
                $failed,
                $success > 0 || $failed > 0 ? 'failed_partial' : 'failed'
            );
            $purged++;
        }

        return $purged;
    }

    public function updateTotals(
        int $jobId,
        int $success,
        int $failed,
        ?int $totalRows = null,
        ?string $status = null,
        ?array $progressPayload = null
    ): void {
        $attributes = [
            'total_success' => $success,
            'total_failed' => $failed,
        ];

        if ($totalRows !== null) {
            $attributes['total_files'] = $totalRows;
        }

        if ($status !== null) {
            $attributes['status'] = $status;
        }

        $this->updateJob($jobId, $attributes, $progressPayload);

        if ($this->isTerminalStatus($status)) {
            $this->cleanupQueuedImportJobRows($jobId);
        }
    }

    public function getStatusPayload(int $jobId): array
    {
        $job = DB::table('import_jobs')->where('id', $jobId)->first();
        if (!$job) {
            return [
                'status' => 'error',
                'message' => 'Import job tidak ditemukan.',
                'job_id' => $jobId,
            ];
        }

        $progress = Cache::get($this->cacheKey($jobId));
        $progress = is_array($progress) ? $progress : [];

        $totalRows = (int) ($progress['total_rows'] ?? $job->total_files ?? 0);
        $success = (int) ($progress['total_success'] ?? $job->total_success ?? 0);
        $failed = (int) ($progress['total_failed'] ?? $job->total_failed ?? 0);
        $processed = max($success + $failed, (int) ($progress['processed_rows'] ?? 0));
        $percent = (int) ($progress['percent'] ?? ($totalRows > 0 ? round(($processed / $totalRows) * 100) : 0));
        $queuedAt = null;
        $queuedForSeconds = null;
        $isStaleQueue = false;

        if ($job->status === 'queued' && !empty($job->updated_at)) {
            try {
                $queuedAt = Carbon::parse($job->updated_at);
                $queuedForSeconds = max(0, now()->diffInSeconds($queuedAt));
                $isStaleQueue = $queuedAt->lt(now()->subMinutes(self::STALE_QUEUED_MINUTES));
            } catch (\Throwable) {
                $queuedForSeconds = null;
                $isStaleQueue = false;
            }
        }

        return [
            'status' => (string) $job->status,
            'job_id' => (int) $job->id,
            'report_id' => (int) $job->id_report,
            'file_name' => (string) $job->file_name,
            'total_rows' => $totalRows,
            'processed_rows' => $processed,
            'total_success' => $success,
            'total_failed' => $failed,
            'percent' => max(0, min(100, $percent)),
            'message' => (string) ($progress['message'] ?? 'Import sedang diproses.'),
            'updated_at' => $progress['updated_at'] ?? (string) $job->updated_at,
            'queued_for_seconds' => $queuedForSeconds,
            'is_stale_queue' => $isStaleQueue,
        ];
    }

    private function cacheKey(int $jobId): string
    {
        return self::CACHE_PREFIX . $jobId;
    }

    private function stateKey(int $jobId): string
    {
        return self::STATE_PREFIX . $jobId;
    }

    private function cleanupQueuedImportJobRows(int $jobId): void
    {
        if ($jobId <= 0) {
            return;
        }

        try {
            DB::table('jobs')
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.data.commandName')) = ?", [RunImportJob::class])
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.data.command')) LIKE ?", ['%jobId";i:' . $jobId . ';%'])
                ->delete();
        } catch (\Throwable $e) {
            Log::warning('Failed to clean queued import job rows: ' . $e->getMessage(), [
                'job_id' => $jobId,
            ]);
        }
    }

    private function isTerminalStatus(?string $status): bool
    {
        return in_array($status, ['completed', 'failed', 'failed_partial'], true);
    }
}
