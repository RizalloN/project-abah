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
    private const ACTIVE_IMPORT_STATUSES = ['staging', 'processing'];

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
        $cache = $this->importCache();
        $existing = $cache->get($this->cacheKey($jobId));
        if (is_array($existing)) {
            $payload = array_merge($existing, $payload);
        }

        $existingStatus = is_array($existing) ? strtolower(trim((string) ($existing['status'] ?? ''))) : '';
        if ($existingStatus === 'terminated' || $this->isTerminationRequested($jobId)) {
            $payload['status'] = 'terminated';
            $payload['message'] = (string) ($existing['message'] ?? $payload['message'] ?? 'Job dihentikan melalui Job Management.');
        }

        $payload['job_id'] = $jobId;
        $payload['updated_at'] = now()->toIso8601String();
        $cache->put($this->cacheKey($jobId), $payload, now()->addHours(6));
        $this->syncActiveJobHeartbeat($jobId, $payload);

        return $payload;
    }

    public function getCachedProgress(int $jobId): array
    {
        return $this->importCache()->get($this->cacheKey($jobId), []);
    }

    public function updateJob(int $jobId, array $attributes, ?array $progressPayload = null): void
    {
        if ($attributes !== []) {
            $attributes['updated_at'] = now();
            DB::table('import_jobs')->where('id', $jobId)->update($attributes);

            if (array_key_exists('status', $attributes)) {
                app(ActiveImportJobCounter::class)->forget();
            }
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
        $this->resetReusedJobRuntimeState($nextId);
        $attributes['id'] = $nextId;

        try {
            DB::table('import_jobs')->insert($attributes);
            app(ActiveImportJobCounter::class)->forget();

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
        $payload = $this->normalizeDlyKapResegmentasiState($payload);

        $this->importCache()->put($this->stateKey($jobId), $payload, now()->addHours(6));
        $this->persistJobStateToContext($jobId, $payload);
    }

    public function getJobState(int $jobId): array
    {
        $cached = $this->importCache()->get($this->stateKey($jobId));

        if (is_array($cached)) {
            return $cached;
        }

        return $this->getPersistedJobStateFromContext($jobId);
    }

    private function persistJobStateToContext(int $jobId, array $payload): void
    {
        if ($jobId <= 0 || $payload === []) {
            return;
        }

        $payload = $this->normalizeDlyKapResegmentasiState($payload);

        try {
            $job = DB::table('import_jobs')
                ->where('id', $jobId)
                ->first(['job_context']);

            if (!$job) {
                return;
            }

            $context = json_decode((string) ($job->job_context ?? ''), true);
            if (!is_array($context)) {
                $context = [];
            }

            $context['state'] = $payload;

            DB::table('import_jobs')
                ->where('id', $jobId)
                ->update([
                    'job_context' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to persist import job state to context.', [
                'job_id' => $jobId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function getPersistedJobStateFromContext(int $jobId): array
    {
        if ($jobId <= 0) {
            return [];
        }

        try {
            $job = DB::table('import_jobs')
                ->where('id', $jobId)
                ->first(['job_context']);

            if (!$job) {
                return [];
            }

            $context = json_decode((string) ($job->job_context ?? ''), true);
            if (!is_array($context) || $context === []) {
                return [];
            }

            $state = $context['state'] ?? null;
            if (is_array($state)) {
                return $this->normalizeDlyKapResegmentasiState($state);
            }

            $filePath = trim((string) ($context['file_path'] ?? ''));
            $tableName = trim((string) ($context['table_name'] ?? ''));

            if ($filePath === '' && $tableName === '') {
                return [];
            }

            $params = $context;
            unset($params['state'], $params['controller'], $params['mode']);
            $params['file_path'] = $filePath;
            $params['table_name'] = $tableName;
            $params['job_id'] = $jobId;

            return [
                'params' => $params,
                'headers' => $this->isDlyKapResegmentasiTableName($tableName)
                    ? DlyKapResegmentasiCsvImporter::NORMALIZED_HEADERS
                    : [],
            ];
        } catch (\Throwable $e) {
            Log::warning('Failed to read import job state from context.', [
                'job_id' => $jobId,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function normalizeDlyKapResegmentasiState(array $payload): array
    {
        $tableName = (string) ($payload['params']['table_name'] ?? '');
        if (!$this->isDlyKapResegmentasiTableName($tableName)) {
            return $payload;
        }

        $payload['headers'] = DlyKapResegmentasiCsvImporter::NORMALIZED_HEADERS;

        return $payload;
    }

    private function isDlyKapResegmentasiTableName(?string $tableName): bool
    {
        return strtolower(trim((string) $tableName)) === DlyKapResegmentasiCsvImporter::TABLE;
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

    public function markStaging(int $jobId, ?array $progressPayload = null): void
    {
        $this->updateJob($jobId, ['status' => 'staging'], array_merge($progressPayload ?? [], [
            'status' => 'staging',
            'percent' => 5,
            'message' => 'Worker menyiapkan CSV staging...',
        ]));
    }

    public function markProcessing(int $jobId, ?array $progressPayload = null): void
    {
        $job = $this->findJob($jobId);
        if ($job && strtolower((string) ($job->status ?? '')) === 'terminated') {
            $this->cacheProgress($jobId, array_merge($progressPayload ?? [], [
                'status' => 'terminated',
                'message' => 'Job dihentikan melalui Job Management.',
            ]));
            return;
        }

        if ($this->isTerminationRequested($jobId)) {
            $this->markTerminated(
                $jobId,
                'Job dihentikan melalui Job Management.',
                $job ? (int) ($job->total_success ?? 0) : 0,
                $job ? (int) ($job->total_failed ?? 0) : 0
            );
            return;
        }

        $this->updateJob($jobId, ['status' => 'processing'], $progressPayload);
        $this->pauseSnapshotQueuesSafely();
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
            $progress = $this->importCache()->get($this->cacheKey($jobId));
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
        $normalizedMessage = $this->normalizeFailureMessage($message);

        if ($jobId > 0 && $success === 0 && $failed === 0 && $this->isMissingSourceFailure($message)) {
            $this->deleteJobRecord($jobId);
            return;
        }

        $this->updateTotals($jobId, $success, $failed, null, $resolvedStatus, [
            'status' => $resolvedStatus,
            'message' => $normalizedMessage,
            'total_success' => $success,
            'total_failed' => $failed,
        ]);
    }

    public function hasActiveProcessingJobs(?int $exceptJobId = null): bool
    {
        if (!Schema::hasTable('import_jobs')) {
            return false;
        }

        $query = DB::table('import_jobs')
            ->whereIn('status', self::ACTIVE_IMPORT_STATUSES);

        if ($exceptJobId !== null && $exceptJobId > 0) {
            $query->where('id', '!=', $exceptJobId);
        }

        return $query->exists();
    }

    public function hasActiveProcessingJobsForTable(string $tableName, ?int $exceptJobId = null): bool
    {
        $normalizedTable = $this->normalizeImportTableName($tableName);
        if ($normalizedTable === '' || !Schema::hasTable('import_jobs')) {
            return false;
        }

        if (!Schema::hasTable('nama_report')) {
            return $this->hasActiveProcessingJobs($exceptJobId);
        }

        $hasImportJobTableName = Schema::hasColumn('import_jobs', 'table_name');

        $query = DB::table('import_jobs as ij')
            ->leftJoin('nama_report as nr', 'nr.id_report', '=', 'ij.id_report')
            ->whereIn('ij.status', self::ACTIVE_IMPORT_STATUSES)
            ->where(function ($builder) use ($normalizedTable, $hasImportJobTableName): void {
                $builder->whereRaw('LOWER(TRIM(COALESCE(nr.table_name, ""))) = ?', [$normalizedTable]);

                if ($hasImportJobTableName) {
                    $builder->orWhereRaw('LOWER(TRIM(COALESCE(ij.table_name, ""))) = ?', [$normalizedTable]);
                }
            });

        if ($exceptJobId !== null && $exceptJobId > 0) {
            $query->where('ij.id', '!=', $exceptJobId);
        }

        return $query->exists();
    }

    private function normalizeImportTableName(string $tableName): string
    {
        $normalized = strtolower(trim($tableName));

        return match ($normalized) {
            'daily_loan', 'daily loan', 'daily loan dinamis' => 'daily_loan_dinamis',
            'simpanan multipn', 'simpanan_multi_pn' => 'simpanan_multipn',
            default => $normalized,
        };
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

            if ($this->findActiveQueueRowForJob($jobId) !== null) {
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
            ->whereIn('status', ['staging', 'processing'])
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

        if ($progressPayload !== null) {
            $progressPayload['total_success'] = $success;
            $progressPayload['total_failed'] = $failed;

            if ($totalRows !== null) {
                $progressPayload['total_rows'] = $totalRows;
                $progressPayload['processed_rows'] = min($totalRows, $success + $failed);
            }

            if ($status !== null) {
                // Keep a stale processing heartbeat from restoring a terminal job.
                $progressPayload['status'] = $status;
            }
        }

        $message = is_array($progressPayload ?? null)
            ? trim((string) ($progressPayload['message'] ?? ''))
            : '';
        if ($message !== '' && $this->importJobsHasMessageColumn()) {
            $attributes['message'] = $message;
        }

        $this->updateJob($jobId, $attributes, $progressPayload);

        if ($this->isTerminalStatus($status)) {
            $this->clearTerminationRequest($jobId);
            $this->cleanupQueuedImportJobRows($jobId);
            $this->resumeSnapshotQueuesSafely();
        }
    }

    private function snapshotQueuePauseService(): SnapshotQueuePauseService
    {
        return app(SnapshotQueuePauseService::class);
    }

    private function pauseSnapshotQueuesSafely(): void
    {
        if (!(bool) config('import.snapshot.pause_during_import', true)) {
            return;
        }

        try {
            $this->snapshotQueuePauseService()->pauseWhileImportActive();
        } catch (\Throwable $e) {
            Log::debug('Skip snapshot queue pause because service is unavailable.', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function resumeSnapshotQueuesSafely(): void
    {
        if (!(bool) config('import.snapshot.pause_during_import', true)) {
            return;
        }

        try {
            $this->snapshotQueuePauseService()->resumeWhenNoActiveImports();
        } catch (\Throwable $e) {
            Log::debug('Skip snapshot queue resume because service is unavailable.', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function requestTermination(int $jobId, ?int $requestedBy = null): void
    {
        if ($jobId <= 0) {
            return;
        }

        $this->importCache()->put($this->terminationKey($jobId), [
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

        $cached = $this->importCache()->get($this->terminationKey($jobId));

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

        $this->importCache()->forget($this->terminationKey($jobId));
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
        $progress = $this->importCache()->get($this->cacheKey($jobId));
        $progress = is_array($progress) ? $progress : [];
        $queueRow = $job->status === 'queued'
            ? $this->findActiveQueueRowForJob($jobId)
            : null;
        $queuePresent = $queueRow !== null;
        $queueReserved = $queuePresent && $queueRow->reserved_at !== null;

        $isTerminal = in_array((string) $job->status, ['completed', 'failed', 'failed_partial', 'terminated'], true);
        if ($isTerminal) {
            $success = (int) ($job->total_success ?? 0);
            $failed = (int) ($job->total_failed ?? 0);
            $processed = $success + $failed;
            $totalRows = max(0, (int) ($job->total_files ?? 0), $processed);
            $percent = $totalRows > 0 ? (int) round(($processed / $totalRows) * 100) : 0;
        } else {
            $totalRows = max(
                0,
                (int) ($job->total_files ?? 0),
                (int) ($progress['total_rows'] ?? 0)
            );
            $success = (int) ($progress['total_success'] ?? $job->total_success ?? 0);
            $failed = (int) ($progress['total_failed'] ?? $job->total_failed ?? 0);
            $processed = max($success + $failed, (int) ($progress['processed_rows'] ?? 0));
            if ($totalRows < $processed) {
                $totalRows = $processed;
            }
            $percent = (int) ($progress['percent'] ?? ($totalRows > 0 ? round(($processed / $totalRows) * 100) : 0));
        }
        $queuedAt = null;
        $queuedForSeconds = null;
        $isStaleQueue = false;
        $terminationRequest = $this->getTerminationRequest($jobId);
        $terminationRequested = (bool) ($terminationRequest['requested'] ?? false);

        if ($job->status === 'queued' && !empty($job->updated_at)) {
            try {
                $queuedAt = Carbon::parse($job->updated_at);
                $queuedForSeconds = max(0, now()->diffInSeconds($queuedAt));
                $isStaleQueue = !$queuePresent
                    && $queuedAt->lt(now()->subMinutes(self::STALE_QUEUED_MINUTES));
            } catch (\Throwable) {
                $queuedForSeconds = null;
                $isStaleQueue = false;
            }
        }

        $message = $queueReserved
            ? 'Worker queue sudah mengambil job import dan sedang menyiapkan proses.'
            : $this->resolveStatusMessage($job, $progress);

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
            'message' => $message,
            'updated_at' => $progress['updated_at'] ?? (string) $job->updated_at,
            'queued_for_seconds' => $queuedForSeconds,
            'is_stale_queue' => $isStaleQueue,
            'queue_present' => $queuePresent,
            'queue_reserved' => $queueReserved,
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
                ->where('payload', 'like', '%jobId%')
                ->where('payload', 'like', '%i:' . $jobId . ';%')
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
            ->whereIn('status', ['queued', 'staging', 'processing'])
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

        $success = (int) ($job->total_success ?? 0);
        $failed = (int) ($job->total_failed ?? 0);
        $totalRows = max(0, (int) ($job->total_files ?? 0));
        $resolvedTerminalStatus = $this->resolveTerminalStatusFromTotals($success, $failed, $totalRows);

        $status = strtolower((string) ($job->status ?? ''));

        // A stale worker/snapshot safeguard must never turn a fully committed
        // import into a failed result. Persisted totals are the terminal facts.
        if (in_array($status, ['failed', 'failed_partial'], true) && $resolvedTerminalStatus === 'completed') {
            Log::warning('Contradictory import status repaired from persisted totals.', [
                'job_id' => $jobId,
                'previous_status' => $status,
                'total_success' => $success,
                'total_failed' => $failed,
                'total_rows' => $totalRows,
            ]);

            $this->finalizeProcessingJobFromTotals($jobId, 'completed', $success, $failed, $totalRows);

            return $this->findJob($jobId);
        }

        if (!in_array($status, ['queued', 'processing'], true)) {
            return $job;
        }

        // A worker can finish writing every row just before its final status update.
        // Reconcile from persisted totals for both queued and processing states so a
        // completed import is never later mislabeled as a stale queued job.
        if ($resolvedTerminalStatus !== null) {
            $this->finalizeProcessingJobFromTotals($jobId, $resolvedTerminalStatus, $success, $failed, $totalRows);

            return $this->findJob($jobId);
        }

        if ($status === 'processing') {
            $auditedTotals = $this->resolveTerminalTotalsFromDirectLoadAudit($job);
            if ($auditedTotals !== null) {
                $this->finalizeProcessingJobFromDirectLoadAudit($jobId, $auditedTotals);

                return $this->findJob($jobId);
            }

            if ($this->isOrphanedSimpananMultiPnDirectLoad($job)) {
                $message = 'Koneksi LOAD DATA MULTIPN terputus sebelum selesai. Transaksi sudah rollback dan file aman untuk diimport ulang.';
                $this->markFailed($jobId, $message, $success, $failed, 'failed');
                $this->releaseSimpananMultiPnStreamLock($jobId);

                Log::warning('Orphaned Simpanan MultiPN LOAD DATA job reconciled.', [
                    'job_id' => $jobId,
                    'mysql_thread_id' => $this->resolveJobContext($job)['mysql_thread_id'] ?? null,
                ]);

                return $this->findJob($jobId);
            }
        }

        $sourcePath = $this->resolveJobSourcePath($job);
        $sourceExists = $sourcePath !== null && is_file($sourcePath);

        if (!$sourceExists && $this->shouldInvalidateMissingSourceJob($job, $status)) {
            $this->markFailed(
                $jobId,
                $this->resolveMissingSourceMessage($job),
                $success,
                $failed,
                'failed'
            );

            return $this->findJob($jobId);
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
            if ($queueRow !== null) {
                // An existing queue row means Laravel still owns this job. The worker
                // itself performs the queued -> processing transition.
                return $job;
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

    private function isOrphanedSimpananMultiPnDirectLoad(object $job): bool
    {
        $context = $this->resolveJobContext($job);
        if (strtolower(trim((string) ($context['table_name'] ?? ''))) !== 'simpanan_multipn') {
            return false;
        }

        if (is_array($context['direct_load_audit'] ?? null)) {
            return false;
        }

        $threadId = (int) ($context['mysql_thread_id'] ?? 0);
        $startedAt = trim((string) ($context['direct_load_started_at'] ?? ''));
        if ($startedAt === '') {
            $progress = $this->getCachedProgress((int) ($job->id ?? 0));
            $phase = strtolower(trim((string) ($progress['phase'] ?? '')));
            $percent = (int) ($progress['percent'] ?? 0);
            if ($percent >= 50 && in_array($phase, ['loading', 'preparing_load_plan'], true)) {
                $startedAt = trim((string) ($job->updated_at ?? ''));
            }
        }

        if ($threadId <= 0 || $startedAt === '') {
            return false;
        }

        try {
            $graceSeconds = max(60, (int) config(
                'import.direct_load.simpanan_multipn.orphan_grace_seconds',
                180
            ));
            if (Carbon::parse($startedAt)->gt(now()->subSeconds($graceSeconds))) {
                return false;
            }

            foreach (DB::select('SHOW FULL PROCESSLIST') as $process) {
                if ((int) ($process->Id ?? 0) === $threadId) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable $e) {
            Log::debug('Skip MULTIPN orphan reconciliation because MySQL thread state is unavailable.', [
                'job_id' => (int) ($job->id ?? 0),
                'mysql_thread_id' => $threadId,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function resolveJobContext(object $job): array
    {
        $context = $job->job_context ?? null;
        if (is_array($context)) {
            return $context;
        }

        if (!is_string($context) || trim($context) === '') {
            return [];
        }

        $decoded = json_decode($context, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function releaseSimpananMultiPnStreamLock(int $jobId): void
    {
        try {
            Cache::lock('import_excel_stream_job_' . $jobId, 1)->forceRelease();
        } catch (\Throwable $e) {
            Log::warning('Failed to release orphaned Simpanan MultiPN stream lock.', [
                'job_id' => $jobId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function resolveTerminalStatusFromTotals(int $success, int $failed, int $totalRows): ?string
    {
        if ($totalRows <= 0) {
            return null;
        }

        if (($success + $failed) < $totalRows) {
            return null;
        }

        if ($failed > 0) {
            return $success > 0 ? 'failed_partial' : 'failed';
        }

        return 'completed';
    }

    /**
     * Recover direct LOAD DATA imports that finished writing rows but failed
     * before the final import_jobs status update was persisted.
     *
     * @return array{success:int, failed:int, total_rows:int, status:string}|null
     */
    private function resolveTerminalTotalsFromDirectLoadAudit(object $job): ?array
    {
        $context = json_decode((string) ($job->job_context ?? ''), true);
        if (!is_array($context)) {
            return null;
        }

        $audit = $context['direct_load_audit'] ?? null;
        if (!is_array($audit) || trim((string) ($audit['completed_at'] ?? '')) === '') {
            return null;
        }

        $success = max(0, (int) ($audit['total_success'] ?? $audit['load_inserted_rows'] ?? 0));
        $failed = max(0, (int) ($audit['total_failed'] ?? $audit['insert_shortfall'] ?? 0));
        $totalRows = max(
            0,
            (int) ($audit['total_rows'] ?? 0),
            (int) ($audit['source_rows'] ?? 0),
            $success + $failed
        );

        if ($totalRows <= 0 || ($success + $failed) < $totalRows) {
            return null;
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'total_rows' => $totalRows,
            'status' => $failed > 0 ? ($success > 0 ? 'failed_partial' : 'failed') : 'completed',
        ];
    }

    /**
     * @param array{success:int, failed:int, total_rows:int, status:string} $totals
     */
    private function finalizeProcessingJobFromDirectLoadAudit(int $jobId, array $totals): void
    {
        $status = $totals['status'];
        $success = $totals['success'];
        $failed = $totals['failed'];
        $totalRows = $totals['total_rows'];

        $this->updateTotals($jobId, $success, $failed, $totalRows, $status, [
            'status' => $status,
            'phase' => $status === 'completed' ? 'completed' : 'failed_partial',
            'message' => $status === 'completed'
                ? 'Fast import selesai. Status dipulihkan dari audit LOAD DATA.'
                : 'Fast import selesai dengan kegagalan parsial. Status dipulihkan dari audit LOAD DATA.',
            'total_success' => $success,
            'total_failed' => $failed,
            'total_rows' => $totalRows,
            'processed_rows' => $success + $failed,
            'percent' => 100,
        ]);
    }

    private function finalizeProcessingJobFromTotals(
        int $jobId,
        string $status,
        int $success,
        int $failed,
        int $totalRows
    ): void {
        $terminalStatus = in_array($status, ['failed', 'failed_partial'], true)
            ? $status
            : 'completed';

        if ($terminalStatus === 'completed') {
            $this->markCompleted(
                $jobId,
                $success,
                $failed,
                $totalRows,
                [
                    'status' => 'completed',
                    'message' => 'Import selesai diproses.',
                    'total_success' => $success,
                    'total_failed' => $failed,
                    'total_rows' => $totalRows,
                    'processed_rows' => $success + $failed,
                    'percent' => 100,
                ]
            );

            return;
        }

        $this->markFailed(
            $jobId,
            $failed > 0 && $success > 0
                ? 'Import selesai dengan kegagalan parsial.'
                : 'Import gagal diproses.',
            $success,
            $failed,
            $terminalStatus
        );
    }

    private function resolveStatusMessage(object $job, array $progress): string
    {
        $jobStatus = strtolower(trim((string) ($job->status ?? '')));
        $progressStatus = strtolower(trim((string) ($progress['status'] ?? '')));
        $progressMessage = trim((string) ($progress['message'] ?? ''));
        if (
            $progressMessage !== ''
            && (!$this->isTerminalStatus($jobStatus) || $progressStatus === '' || $progressStatus === $jobStatus)
        ) {
            return $progressMessage;
        }

        $databaseMessage = trim((string) ($job->message ?? ''));
        if ($databaseMessage !== '') {
            return $databaseMessage;
        }

        return match ($jobStatus) {
            'queued' => 'Job import sedang menunggu di antrian.',
            'staging' => 'Worker menyiapkan CSV staging.',
            'processing' => 'Import sedang diproses.',
            'completed' => 'Import selesai diproses.',
            'failed_partial' => 'Import selesai dengan sebagian baris gagal.',
            'failed' => 'Import gagal diproses.',
            'terminated' => 'Job dihentikan melalui Job Management.',
            default => 'Import sedang diproses.',
        };
    }

    private function findActiveQueueRowForJob(int $jobId): ?object
    {
        if ($jobId <= 0) {
            return null;
        }

        try {
            return DB::table('jobs')
                ->where('payload', 'like', '%' . class_basename(RunImportJob::class) . '%')
                ->where('payload', 'like', '%jobId%')
                ->where('payload', 'like', '%i:' . $jobId . ';%')
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

    private function normalizeFailureMessage(string $message): string
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return 'Import gagal diproses.';
        }

        $normalized = strtolower($trimmed);
        $runtimeIndicators = [
            'fatal error:',
            'uncaught ',
            'undefined variable',
            'undefined array key',
            'undefined property',
            'call to undefined function',
            'attempt to read property',
            'trying to access array offset',
            'typed property',
        ];

        foreach ($runtimeIndicators as $indicator) {
            if (str_contains($normalized, $indicator)) {
                return 'Import gagal diproses karena kesalahan internal pada worker. Detail teknis sudah dicatat di log server.';
            }
        }

        return $trimmed;
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

        $this->importCache()->forget($this->cacheKey($jobId));
        $this->importCache()->forget($this->stateKey($jobId));
        $this->importCache()->forget($this->heartbeatKey($jobId));
        $this->clearTerminationRequest($jobId);
        $this->cleanupQueuedImportJobRows($jobId);
    }

    private function resetReusedJobRuntimeState(int $jobId): void
    {
        if ($jobId <= 0) {
            return;
        }

        $cache = $this->importCache();
        $cache->forget($this->cacheKey($jobId));
        $cache->forget($this->stateKey($jobId));
        $cache->forget($this->heartbeatKey($jobId));
        $cache->forget($this->terminationKey($jobId));
        $cache->forget('import_excel_dispatched_job_' . $jobId);

        try {
            $cache->lock('import_excel_execute_job_' . $jobId, 1)->forceRelease();
        } catch (\Throwable) {
            // Ignore lock reset failures; runtime flow can still self-heal on next dispatch.
        }

        try {
            $cache->lock('import_excel_dispatch_job_' . $jobId, 1)->forceRelease();
        } catch (\Throwable) {
            // Ignore lock reset failures; runtime flow can still self-heal on next dispatch.
        }

        try {
            $cache->lock('import_file_stream_job_' . $jobId, 1)->forceRelease();
        } catch (\Throwable) {
            // Ignore lock reset failures; runtime flow can still self-heal on next dispatch.
        }

        $this->cleanupQueuedImportJobRows($jobId);
    }

    private function isTerminalStatus(?string $status): bool
    {
        return in_array($status, ['completed', 'failed', 'failed_partial', 'terminated'], true);
    }

    private function importJobsHasMessageColumn(): bool
    {
        try {
            return Schema::hasTable('import_jobs') && Schema::hasColumn('import_jobs', 'message');
        } catch (\Throwable) {
            return false;
        }
    }

    private function syncActiveJobHeartbeat(int $jobId, array $payload): void
    {
        if ($jobId <= 0) {
            return;
        }

        $status = strtolower(trim((string) ($payload['status'] ?? '')));
        if (!in_array($status, ['queued', 'processing'], true)) {
            if ($status !== '') {
                $this->importCache()->forget($this->heartbeatKey($jobId));
            }

            return;
        }

        $heartbeatKey = $this->heartbeatKey($jobId);
        $cache = $this->importCache();
        $lastHeartbeat = $cache->get($heartbeatKey);
        if (is_numeric($lastHeartbeat) && ((time() - (int) $lastHeartbeat) < self::ACTIVE_HEARTBEAT_SECONDS)) {
            return;
        }

        $cache->put($heartbeatKey, time(), now()->addHours(6));

        try {
            DB::table('import_jobs')
                ->where('id', $jobId)
                ->whereNotIn('status', ['completed', 'failed', 'failed_partial', 'terminated'])
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

    private function importCache()
    {
        $store = trim((string) config('import.cache_store', 'file'));

        if ($store === '') {
            return Cache::getFacadeRoot();
        }

        return $store !== '' ? Cache::store($store) : Cache::store();
    }
}
