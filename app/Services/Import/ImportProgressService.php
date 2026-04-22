<?php

namespace App\Services\Import;

use App\Jobs\RunImportJob;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

class ImportProgressService
{
    private const CACHE_PREFIX = 'import_job_progress:';
    private const STATE_PREFIX = 'excel_import_job:';
    private const TERMINATE_PREFIX = 'import_job_terminate:';
    private const HEARTBEAT_PREFIX = 'import_job_progress_heartbeat:';
    private const STALE_QUEUED_MINUTES = 15;
    private const STALE_PROCESSING_HOURS = 2;
    private const ACTIVE_PROCESSING_REUSE_HOURS = 6;
    private const MISSING_SOURCE_GRACE_SECONDS = 120;
    private const ACTIVE_HEARTBEAT_SECONDS = 10;
    private const DAILY_LOAN_REPORT_ID = 8;

    public function cacheProgress(int $jobId, array $payload): array
    {
        $existing = Cache::get($this->cacheKey($jobId));
        if (is_array($existing)) {
            $payload = array_merge($existing, $payload);
        }

        $payload['job_id'] = $jobId;
        $payload['updated_at'] = now()->toIso8601String();
        Cache::put($this->cacheKey($jobId), $payload, now()->addHours(6));
        $this->syncActiveJobHeartbeat($jobId, $payload);

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
        $jobContext = $attributes['job_context'] ?? null;
        $attributes['job_fingerprint'] = $this->resolveJobFingerprint($attributes);
        $attributes['job_context'] = $this->normalizeJobContextForStorage($jobContext);

        $existingJobId = $this->findReusableActiveJobId($attributes);
        if ($existingJobId !== null) {
            return $existingJobId;
        }

        // Manually assign the smallest available ID to fill holes and avoid standard auto-increment behavior
        $nextId = $this->findSmallestAvailableJobId();
        $attributes['id'] = $nextId;

        try {
            DB::table('import_jobs')->insert($attributes);

            return $nextId;
        } catch (QueryException $e) {
            if (!$this->isDuplicateFingerprintException($e)) {
                throw $e;
            }

            $existingJobId = $this->findReusableActiveJobId($attributes);
            if ($existingJobId !== null) {
                return $existingJobId;
            }

            throw $e;
        }
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

    public function deleteJobsForSourcePath(string $sourcePath): array
    {
        $normalizedSourcePath = $this->normalizeComparablePath($sourcePath);
        if ($normalizedSourcePath === '') {
            return [
                'deleted_job_ids' => [],
                'deleted_count' => 0,
            ];
        }

        $jobs = DB::table('import_jobs')
            ->orderBy('id')
            ->get(['id', 'folder_path', 'file_name']);

        $deletedJobIds = [];

        foreach ($jobs as $job) {
            $jobId = (int) ($job->id ?? 0);
            if ($jobId <= 0) {
                continue;
            }

            $resolvedPath = $this->resolveJobSourcePath($job);
            if ($resolvedPath === null) {
                continue;
            }

            if ($this->normalizeComparablePath($resolvedPath) !== $normalizedSourcePath) {
                continue;
            }

            $this->deleteJobRecord($jobId);
            $deletedJobIds[] = $jobId;
        }

        return [
            'deleted_job_ids' => $deletedJobIds,
            'deleted_count' => count($deletedJobIds),
        ];
    }

    public function deleteJob(int $jobId): bool
    {
        if ($jobId <= 0) {
            return false;
        }

        $job = $this->findJob($jobId);
        if (!$job) {
            return false;
        }

        $this->deleteJobRecord($jobId);

        return true;
    }

    public function deleteJobsByIds(array $jobIds): int
    {
        $deleted = 0;

        foreach ($jobIds as $jobId) {
            $jobId = (int) $jobId;
            if ($jobId <= 0) {
                continue;
            }

            if ($this->deleteJob($jobId)) {
                $deleted++;
            }
        }

        return $deleted;
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

    public function markTerminated(int $jobId, string $message, int $success = 0, int $failed = 0, ?array $rollbackMetadata = null): void
    {
        $this->rollbackPartiallyImportedData($jobId, $rollbackMetadata);

        $this->updateTotals($jobId, $success, $failed, null, 'terminated', [
            'status' => 'terminated',
            'message' => $message,
            'total_success' => $success,
            'total_failed' => $failed,
        ]);
    }

    private function rollbackPartiallyImportedData(int $jobId, ?array $passedMetadata = null): void
    {
        $metadata = $passedMetadata;

        if ($metadata === null) {
            $progress = Cache::get($this->cacheKey($jobId));
            if (is_array($progress) && !empty($progress['rollback_metadata'])) {
                $metadata = $progress['rollback_metadata'];
            }
        }

        if (empty($metadata)) {
            Log::info("Rollback: No metadata found for job #{$jobId}. Skipping data cleanup.");
            return;
        }

        $tableName = trim((string) ($metadata['table_name'] ?? ''));
        $uniqueIdCol = trim((string) ($metadata['unique_id_col'] ?? ''));
        $prefix = trim((string) ($metadata['unique_id_prefix'] ?? ''));

        if ($tableName === '' || $uniqueIdCol === '' || $prefix === '') {
            Log::warning("Rollback: Incomplete metadata for job #{$jobId}. Table: {$tableName}, Col: {$uniqueIdCol}, Prefix: {$prefix}");
            return;
        }

        if (!Schema::hasTable($tableName)) {
            Log::warning("Rollback: Table `{$tableName}` does not exist.");
            return;
        }

        try {
            $deletedCount = DB::table($tableName)
                ->where($uniqueIdCol, 'like', $prefix . '%')
                ->delete();

            if ($deletedCount > 0) {
                Log::info("Rollback SUCCESS: Deleted {$deletedCount} rows from `{$tableName}` for terminated job #{$jobId} (prefix: {$prefix}).");
            } else {
                Log::info("Rollback: No rows found to delete in `{$tableName}` for terminated job #{$jobId} (prefix: {$prefix}).");
            }
        } catch (\Throwable $e) {
            Log::error("Rollback FAILED for job #{$jobId}: " . $e->getMessage(), [
                'table' => $tableName,
                'col' => $uniqueIdCol,
                'prefix' => $prefix
            ]);
        }
    }

    public function markFailed(int $jobId, string $message, int $success = 0, int $failed = 0, ?string $status = null): void
    {
        $resolvedStatus = $status ?? ($success > 0 ? 'failed_partial' : 'failed');

        if ($jobId > 0 && $success === 0 && $failed === 0 && $this->isMissingSourceFailure($message)) {
            $this->deleteJobRecord($jobId);
            return;
        }

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

    public function purgeStaleProcessingJobs(): int
    {
        $cutoff = now()->subHours(self::STALE_PROCESSING_HOURS);
        $staleJobs = DB::table('import_jobs')
            ->where('status', 'processing')
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
                'Job import terlalu lama berada di status processing tanpa progress. Sistem menandainya gagal agar tidak menggantung.',
                $success,
                $failed,
                $success > 0 || $failed > 0 ? 'failed_partial' : 'failed'
            );
            $purged++;
        }

        return $purged;
    }

    public function purgeQueuedImportJobs(int $olderThanMinutes = 0): int
    {
        return $this->purgeQueuedImportJobsForQueues([], $olderThanMinutes);
    }

    public function purgeQueuedImportJobsForQueues(array|string $queues = [], int $olderThanMinutes = 0): int
    {
        try {
            $query = DB::table('jobs')
                ->whereNull('reserved_at')
                ->where('payload', 'like', '%' . class_basename(RunImportJob::class) . '%');

            $normalizedQueues = array_values(array_filter(
                array_map(static fn ($queue) => trim((string) $queue), is_array($queues) ? $queues : [$queues]),
                static fn (string $queue): bool => $queue !== ''
            ));

            if ($normalizedQueues !== []) {
                $query->whereIn('queue', $normalizedQueues);
            }

            if ($olderThanMinutes > 0) {
                $query->where('created_at', '<', now()->subMinutes($olderThanMinutes)->timestamp);
            }

            return $query->delete();
        } catch (\Throwable $e) {
            Log::warning('Failed to purge queued import jobs: ' . $e->getMessage(), [
                'older_than_minutes' => $olderThanMinutes,
            ]);

            return 0;
        }
    }

    public function cleanupQueuedImportJobRowsForJob(int $jobId): void
    {
        $this->cleanupQueuedImportJobRows($jobId);
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
            if ($this->isTerminalStatus($status)) {
                $attributes['job_fingerprint'] = null;
            }
        }

        $this->updateJob($jobId, $attributes, $progressPayload);

        if ($this->isTerminalStatus($status)) {
            $this->clearTerminationRequest($jobId);
            $this->cleanupQueuedImportJobRows($jobId);
        }
    }

    public function requestTermination(int $jobId, ?int $requestedBy = null): void
    {
        if ($jobId <= 0) {
            return;
        }

        Cache::put($this->terminationKey($jobId), [
            'requested' => true,
            'requested_by' => $requestedBy,
            'requested_at' => now()->toIso8601String(),
        ], now()->addHours(6));
    }

    public function getTerminationRequest(int $jobId): array
    {
        if ($jobId <= 0) {
            return [];
        }

        $cached = Cache::get($this->terminationKey($jobId));

        return is_array($cached) ? $cached : [];
    }

    public function isTerminationRequested(int $jobId): bool
    {
        return (bool) ($this->getTerminationRequest($jobId)['requested'] ?? false);
    }

    public function clearTerminationRequest(int $jobId): void
    {
        if ($jobId <= 0) {
            return;
        }

        Cache::forget($this->terminationKey($jobId));
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

        $reconciledJob = $this->reconcileJobState($job);
        if ($reconciledJob === null) {
            $job = DB::table('import_jobs')->where('id', $jobId)->first();
            if (!$job) {
                return [
                    'status' => 'error',
                    'message' => 'Import job tidak ditemukan.',
                    'job_id' => $jobId,
                ];
            }
        } else {
            $job = $reconciledJob;
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
        $terminationRequest = $this->getTerminationRequest($jobId);
        $terminationRequested = (bool) ($terminationRequest['requested'] ?? false);

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
            'phase' => (string) ($progress['phase'] ?? ''),
            'mode' => (string) ($progress['mode'] ?? ''),
            'message' => (string) ($progress['message'] ?? 'Import sedang diproses.'),
            'updated_at' => $progress['updated_at'] ?? (string) $job->updated_at,
            'queued_for_seconds' => $queuedForSeconds,
            'is_stale_queue' => $isStaleQueue,
            'termination_requested' => $terminationRequested,
            'termination_requested_at' => $terminationRequest['requested_at'] ?? null,
        ];
    }

    private function findSmallestAvailableJobId(): int
    {
        // 1. Check if ID 1 is available
        $exists1 = DB::table('import_jobs')->where('id', 1)->exists();
        if (!$exists1) {
            return 1;
        }

        // 2. Find the first "hole" in the ID sequence using a self-join style logic.
        // We look for an existing ID (t1) where no ID exists for (t1 + 1).
        $hole = DB::table('import_jobs as t1')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('import_jobs as t2')
                    ->whereRaw('t2.id = t1.id + 1');
            })
            ->min(DB::raw('t1.id + 1'));

        return (int) ($hole ?? 1);
    }

    private function cacheKey(int $jobId): string
    {
        return self::CACHE_PREFIX . $jobId;
    }

    private function heartbeatKey(int $jobId): string
    {
        return self::HEARTBEAT_PREFIX . $jobId;
    }

    private function stateKey(int $jobId): string
    {
        return self::STATE_PREFIX . $jobId;
    }

    private function terminationKey(int $jobId): string
    {
        return self::TERMINATE_PREFIX . $jobId;
    }

    private function cleanupQueuedImportJobRows(int $jobId): void
    {
        if ($jobId <= 0) {
            return;
        }

        try {
            DB::table('jobs')
                ->where('payload', 'like', '%' . class_basename(RunImportJob::class) . '%')
                ->where('payload', 'like', '%jobId";i:' . $jobId . ';%')
                ->delete();
        } catch (\Throwable $e) {
            Log::warning('Failed to clean queued import job rows: ' . $e->getMessage(), [
                'job_id' => $jobId,
            ]);
        }
    }

    private function findReusableActiveJobId(array $attributes): ?int
    {
        $reportId = (int) ($attributes['id_report'] ?? 0);
        $fileName = trim((string) ($attributes['file_name'] ?? ''));
        $folderPath = trim((string) ($attributes['folder_path'] ?? ''));
        $createdBy = $attributes['created_by'] ?? null;
        $fingerprint = trim((string) ($attributes['job_fingerprint'] ?? ''));

        if ($reportId <= 0 || $fileName === '' || $folderPath === '' || $createdBy === null) {
            return null;
        }

        $query = DB::table('import_jobs')
            ->where('id_report', $reportId)
            ->where('file_name', $fileName)
            ->where('folder_path', $folderPath)
            ->where('created_by', $createdBy)
            ->whereIn('status', ['queued', 'processing'])
            ->orderByDesc('updated_at');

        if ($fingerprint !== '') {
            $query->where('job_fingerprint', $fingerprint);
        }

        $candidates = $query->get(['id', 'status', 'updated_at']);

        foreach ($candidates as $candidate) {
            if ($this->jobIsStillReusable($candidate)) {
                return (int) ($candidate->id ?? 0);
            }
        }

        return null;
    }

    private function resolveJobFingerprint(array $attributes): ?string
    {
        $reportId = (int) ($attributes['id_report'] ?? 0);
        $fileName = strtolower(trim((string) ($attributes['file_name'] ?? '')));
        $folderPath = strtolower(trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) ($attributes['folder_path'] ?? ''))));
        $createdBy = (string) ($attributes['created_by'] ?? '');
        $jobContext = $this->normalizeFingerprintContext($attributes['job_context'] ?? null);

        if ($reportId <= 0 || $fileName === '' || $folderPath === '' || $createdBy === '') {
            return null;
        }

        $seed = [
            'report_id' => $reportId,
            'file_name' => $fileName,
            'folder_path' => $folderPath,
            'created_by' => $createdBy,
        ];

        if ($jobContext !== null) {
            $seed['job_context'] = $jobContext;
        }

        return sha1(json_encode($seed));
    }

    private function normalizeFingerprintContext(mixed $context): ?array
    {
        if (!is_array($context) || $context === []) {
            return null;
        }

        $normalized = [];
        foreach ($context as $key => $value) {
            $normalized[(string) $key] = is_array($value)
                ? $this->normalizeFingerprintContext($value) ?? $value
                : $value;
        }

        ksort($normalized);

        return $normalized;
    }

    private function normalizeJobContextForStorage(mixed $context): ?string
    {
        $normalized = $this->normalizeFingerprintContext($context);
        if ($normalized === null) {
            return null;
        }

        $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded !== false ? $encoded : null;
    }

    private function isDuplicateFingerprintException(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'duplicate entry')
            || str_contains($message, 'unique constraint failed')
            || str_contains($message, 'job_fingerprint');
    }

    private function jobIsStillReusable(object $job): bool
    {
        $status = strtolower(trim((string) ($job->status ?? '')));
        $updatedAt = $job->updated_at ?? null;
        if ($updatedAt === null || $updatedAt === '') {
            return false;
        }

        try {
            $timestamp = Carbon::parse($updatedAt);
        } catch (\Throwable) {
            return false;
        }

        if ($status === 'queued') {
            return $timestamp->gte(now()->subMinutes(self::STALE_QUEUED_MINUTES));
        }

        if ($status === 'processing') {
            return $timestamp->gte(now()->subHours(self::ACTIVE_PROCESSING_REUSE_HOURS));
        }

        return false;
    }

    private function reconcileJobState(object $job): ?object
    {
        $jobId = (int) ($job->id ?? 0);
        if ($jobId <= 0) {
            return $job;
        }

        $status = strtolower((string) ($job->status ?? ''));
        $sourcePath = $this->resolveJobSourcePath($job);
        $sourceExists = $sourcePath !== null && is_file($sourcePath);

        if (!$sourceExists && $this->shouldInvalidateMissingSourceJob($job, $status)) {
            $this->markFailed(
                $jobId,
                $this->resolveMissingSourceMessage($job),
                (int) ($job->total_success ?? 0),
                (int) ($job->total_failed ?? 0),
                'failed'
            );

            return $this->findJob($jobId);
        }

        if (!in_array($status, ['queued', 'processing'], true)) {
            return $job;
        }

        $updatedAt = $job->updated_at ?? null;
        if ($updatedAt === null || $updatedAt === '') {
            return $job;
        }

        try {
            $queuedAt = Carbon::parse($updatedAt);
        } catch (\Throwable) {
            return $job;
        }

        if ($status === 'queued') {
            $queueRow = $this->findActiveQueueRowForJob($jobId);
            if ($queueRow !== null && $queueRow->reserved_at !== null) {
                $cachedProgress = Cache::get($this->cacheKey($jobId));
                $cachedProgress = is_array($cachedProgress) ? $cachedProgress : [];

                $this->markProcessing($jobId, [
                    'status' => 'processing',
                    'phase' => 'polars',
                    'mode' => 'polars',
                    'percent' => max(8, (int) ($cachedProgress['percent'] ?? 5)),
                    'message' => 'Worker queue sudah mengambil job import dan sedang memulai proses.',
                ]);

                return $this->findJob($jobId);
            }

            if ($queuedAt->gte(now()->subMinutes(self::STALE_QUEUED_MINUTES))) {
                return $job;
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

            return $this->findJob($jobId);
        }

        $success = (int) ($job->total_success ?? 0);
        $failed = (int) ($job->total_failed ?? 0);

        if ($queuedAt->lt(now()->subHours(self::STALE_PROCESSING_HOURS))) {
            $this->markFailed(
                $jobId,
                'Job import terlalu lama berada di status processing tanpa progress. Sistem menandainya gagal agar tidak menggantung.',
                $success,
                $failed,
                $success > 0 || $failed > 0 ? 'failed_partial' : 'failed'
            );

            return $this->findJob($jobId);
        }

        return $job;
    }

    private function findActiveQueueRowForJob(int $jobId): ?object
    {
        if ($jobId <= 0) {
            return null;
        }

        try {
            return DB::table('jobs')
                ->where('payload', 'like', '%' . class_basename(RunImportJob::class) . '%')
                ->where('payload', 'like', '%jobId";i:' . $jobId . ';%')
                ->orderByDesc('id')
                ->first(['id', 'queue', 'reserved_at', 'available_at', 'created_at']);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveJobSourcePath(object $job): ?string
    {
        $folderPath = trim((string) ($job->folder_path ?? ''));
        $fileName = trim((string) ($job->file_name ?? ''));

        if ($folderPath === '' || $fileName === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $folderPath) === 1 || str_starts_with($folderPath, '\\\\')) {
            return $folderPath . DIRECTORY_SEPARATOR . $fileName;
        }

        $cleanFolder = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $folderPath), DIRECTORY_SEPARATOR);

        return storage_path('app/' . $cleanFolder . DIRECTORY_SEPARATOR . $fileName);
    }

    private function normalizeComparablePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        return strtolower(str_replace('\\', '/', $path));
    }

    private function shouldInvalidateMissingSourceJob(object $job, string $status): bool
    {
        if (in_array($status, ['queued', 'processing'], true)) {
            $updatedAt = $job->updated_at ?? $job->created_at ?? null;
            if ($updatedAt !== null && $updatedAt !== '') {
                try {
                    $jobAgeSeconds = now()->diffInSeconds(Carbon::parse($updatedAt));
                    if ($jobAgeSeconds < self::MISSING_SOURCE_GRACE_SECONDS) {
                        return false;
                    }
                } catch (\Throwable) {
                    return false;
                }
            }

            return true;
        }

        $reportId = (int) ($job->id_report ?? 0);
        if ($reportId === self::DAILY_LOAN_REPORT_ID && in_array($status, ['completed', 'failed_partial'], true)) {
            return true;
        }

        return false;
    }

    private function resolveMissingSourceMessage(object $job): string
    {
        $reportId = (int) ($job->id_report ?? 0);
        if ($reportId === self::DAILY_LOAN_REPORT_ID) {
            return 'File sumber import Daily Loan Dinamis tidak ditemukan atau sudah dihapus. Silakan upload ulang.';
        }

        return 'File sumber import tidak ditemukan atau sudah dihapus. Silakan upload ulang.';
    }

    private function isMissingSourceFailure(string $message): bool
    {
        $normalized = strtolower(trim($message));
        if ($normalized === '') {
            return false;
        }

        $needles = [
            'file sumber import',
            'file excel tidak ditemukan',
            'file csv tidak ditemukan',
            'file tidak ditemukan di server',
            'tidak ditemukan atau sudah dihapus',
            'sudah dihapus',
        ];

        foreach ($needles as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function deleteJobRecord(int $jobId): void
    {
        if ($jobId <= 0) {
            return;
        }

        try {
            DB::table('import_jobs')->where('id', $jobId)->delete();
        } catch (\Throwable $e) {
            Log::warning('Failed to delete missing-source import job: ' . $e->getMessage(), [
                'job_id' => $jobId,
            ]);
        }

        Cache::forget($this->cacheKey($jobId));
        Cache::forget($this->stateKey($jobId));
        Cache::forget($this->heartbeatKey($jobId));
        $this->clearTerminationRequest($jobId);
        $this->cleanupQueuedImportJobRows($jobId);
    }

    private function isTerminalStatus(?string $status): bool
    {
        return in_array($status, ['completed', 'failed', 'failed_partial', 'terminated'], true);
    }

    private function syncActiveJobHeartbeat(int $jobId, array $payload): void
    {
        if ($jobId <= 0) {
            return;
        }

        $status = strtolower(trim((string) ($payload['status'] ?? '')));
        if (!in_array($status, ['queued', 'processing'], true)) {
            if ($status !== '') {
                Cache::forget($this->heartbeatKey($jobId));
            }

            return;
        }

        $heartbeatKey = $this->heartbeatKey($jobId);
        $lastHeartbeat = Cache::get($heartbeatKey);
        if (is_numeric($lastHeartbeat) && ((time() - (int) $lastHeartbeat) < self::ACTIVE_HEARTBEAT_SECONDS)) {
            return;
        }

        Cache::put($heartbeatKey, time(), now()->addHours(6));

        try {
            DB::table('import_jobs')
                ->where('id', $jobId)
                ->update([
                    'status' => $status,
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to sync import job heartbeat: ' . $e->getMessage(), [
                'job_id' => $jobId,
                'status' => $status,
            ]);
        }
    }
}
