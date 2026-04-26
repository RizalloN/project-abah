<?php

namespace App\Support;

use App\Jobs\RunManagedReportSnapshotRebuildJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class ManagedReportSnapshotRebuildCoordinator
{
    private const REBUILD_FALLBACK_LOCK_PREFIX = 'report_management_rebuild_lock:';
    private const REBUILD_FALLBACK_LOCK_SECONDS = 7200;
    private const REBUILD_FALLBACK_STALE_SECONDS = 15;
    private const SLOT_RECOVERY_LOCK_KEY = 'report_management:rebuild:slot_recovery';
    private const SLOT_RECOVERY_LOCK_SECONDS = 8;
    private const QUEUED_FAIL_SECONDS = 300;
    private const RUNNING_FAIL_SECONDS = 3600;
    private const SNAPSHOT_QUEUELESS_STALE_SECONDS = 1800;
    private const SNAPSHOT_RESERVED_STALE_SECONDS = 600;

    public function queue(bool $forceRebuild, ?string $source = null): array
    {
        $this->terminateStaleCachedStates();

        $rebuildId = (string) Str::uuid();
        $slotReserved = false;
        $runningState = $this->findRunningRebuildState();

        if ($runningState !== null) {
            return $this->activeRebuildResponse($forceRebuild, $runningState, 'Rebuild snapshot seluruh report masih berjalan. Request baru tidak dijadwalkan agar tidak membuat queue stuck atau rebuild paralel.');
        }

        if (Cache::add(ManagedReportSnapshotRebuildStore::PENDING_KEY, $rebuildId, ManagedReportSnapshotRebuildStore::ttl())) {
            $slotReserved = true;
            $activeRebuildId = null;
            $activeState = null;
        } else {
            $pendingValue = Cache::get(ManagedReportSnapshotRebuildStore::PENDING_KEY);
            $activeRebuildId = ManagedReportSnapshotRebuildStore::getActiveRebuildId()
                ?? $this->extractManagedReportRebuildId($pendingValue);
            $activeState = $activeRebuildId ? ManagedReportSnapshotRebuildStore::getState($activeRebuildId) : null;

            if ($this->shouldRecoverManagedReportRebuildSlot($pendingValue, $activeRebuildId, $activeState)) {
                $recoveryLock = Cache::lock(self::SLOT_RECOVERY_LOCK_KEY, self::SLOT_RECOVERY_LOCK_SECONDS);

                if ($recoveryLock->get()) {
                    try {
                        // Re-read state under lock to prevent concurrent recovery race.
                        $pendingValue = Cache::get(ManagedReportSnapshotRebuildStore::PENDING_KEY);
                        $activeRebuildId = ManagedReportSnapshotRebuildStore::getActiveRebuildId()
                            ?? $this->extractManagedReportRebuildId($pendingValue);
                        $activeState = $activeRebuildId ? ManagedReportSnapshotRebuildStore::getState($activeRebuildId) : null;

                        if ($this->shouldRecoverManagedReportRebuildSlot($pendingValue, $activeRebuildId, $activeState)) {
                            Cache::forget(ManagedReportSnapshotRebuildStore::PENDING_KEY);
                            ManagedReportSnapshotRebuildStore::forgetActiveRebuildId();

                            if ($activeRebuildId) {
                                ManagedReportSnapshotRebuildStore::forgetState($activeRebuildId);
                            }

                            if (Cache::add(ManagedReportSnapshotRebuildStore::PENDING_KEY, $rebuildId, ManagedReportSnapshotRebuildStore::ttl())) {
                                $slotReserved = true;
                                $activeRebuildId = null;
                                $activeState = null;
                            }
                        }
                    } finally {
                        optional($recoveryLock)->release();
                    }
                }

                if (!$slotReserved) {
                    $pendingValue = Cache::get(ManagedReportSnapshotRebuildStore::PENDING_KEY);
                    $activeRebuildId = ManagedReportSnapshotRebuildStore::getActiveRebuildId()
                        ?? $this->extractManagedReportRebuildId($pendingValue);
                    $activeState = $activeRebuildId ? ManagedReportSnapshotRebuildStore::getState($activeRebuildId) : null;
                }
            }

            if (!$slotReserved) {
                return $this->activeRebuildResponse($forceRebuild, $activeState, 'Rebuild snapshot seluruh report sudah sedang berjalan atau masih antre.', $activeRebuildId);
            }
        }

        $state = ManagedReportSnapshotRebuildStore::createInitialState($rebuildId, $forceRebuild, $source);
        ManagedReportSnapshotRebuildStore::setActiveRebuildId($rebuildId);
        ManagedReportSnapshotRebuildStore::putState($state);

        try {
            RunManagedReportSnapshotRebuildJob::dispatch($forceRebuild, $source, $rebuildId)
                ->onQueue((string) config('queue.report_queue', 'default'));
        } catch (Throwable $e) {
            Cache::forget(ManagedReportSnapshotRebuildStore::PENDING_KEY);
            ManagedReportSnapshotRebuildStore::forgetActiveRebuildId();
            ManagedReportSnapshotRebuildStore::putState(array_merge($state, [
                'status' => 'failed',
                'stage' => 'failed',
                'queued' => false,
                'message' => 'Gagal menjadwalkan rebuild snapshot: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'finished_at' => now()->toIso8601String(),
            ]));

            Log::warning('Gagal menjadwalkan rebuild snapshot report management: ' . $e->getMessage(), [
                'force_rebuild' => $forceRebuild,
                'exception_class' => $e::class,
                'rebuild_id' => $rebuildId,
            ]);

            return [
                'status_code' => 500,
                'payload' => [
                    'status' => 'error',
                    'message' => 'Gagal menjadwalkan rebuild snapshot: ' . $e->getMessage(),
                ],
            ];
        }

        return [
            'status_code' => 200,
            'payload' => [
                'status' => 'queued',
                'queued' => true,
                'rebuild_id' => $rebuildId,
                'force_rebuild' => $forceRebuild,
                'progress_percent' => 0,
                'completed_units' => 0,
                'total_units' => 1,
                'stage' => 'queued',
                'message' => $forceRebuild
                    ? 'Rebuild snapshot seluruh report dari awal sudah masuk antrean dan progress akan tampil realtime.'
                    : 'Refresh snapshot seluruh report sudah masuk antrean dan progress akan tampil realtime.',
            ],
        ];
    }

    public function registerStandaloneJob(string $rebuildId, string $label, string $fileName = '', string $source = ''): array
    {
        $rebuildId = trim($rebuildId);
        if ($rebuildId === '') {
            $rebuildId = (string) Str::uuid();
        }

        $state = ManagedReportSnapshotRebuildStore::normalizeState([
            'rebuild_id' => $rebuildId,
            'status' => 'running',
            'stage' => 'processing',
            'queued' => false,
            'force_rebuild' => false,
            'source' => $source ?: static::class,
            'message' => 'Standalone job dipicu langsung: ' . ($label ?: 'Sinkronisasi data'),
            'current_report_label' => $label,
            'file_name' => $fileName ?: $label,
            'progress_percent' => 5,
            'started_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ], true);

        ManagedReportSnapshotRebuildStore::putState($state);
        // We don't set it as "ACTIVE_KEY" global because it's a standalone background task that shouldn't block future rebuilds.
        // But we return it so the caller knows it succeeded.

        return $state;
    }

    public function status(string $rebuildId): array
    {
        $state = $this->reconcile($rebuildId);
        if ($state === null) {
            return [
                'status_code' => 404,
                'payload' => [
                    'status' => 'error',
                    'message' => 'Progress rebuild snapshot tidak ditemukan atau sudah kedaluwarsa.',
                ],
            ];
        }

        return [
            'status_code' => 200,
            'payload' => $state,
        ];
    }

    public function forceStart(string $rebuildId): array
    {
        $this->terminateStaleCachedStates();

        $rebuildId = trim($rebuildId);
        $queueRow = $this->findSnapshotQueueRow($rebuildId);
        $state = $this->resolveOperationalState($rebuildId, $queueRow);

        if ($rebuildId === '' || $state === null) {
            return [
                'status_code' => 404,
                'payload' => [
                    'status' => 'error',
                    'message' => 'Snapshot rebuild tidak ditemukan.',
                ],
            ];
        }

        $status = strtolower(trim((string) ($state['status'] ?? '')));
        $stage = strtolower(trim((string) ($state['stage'] ?? '')));
        if ($status === 'completed') {
            return [
                'status_code' => 422,
                'payload' => [
                    'status' => 'error',
                    'message' => 'Snapshot rebuild sudah selesai dan tidak bisa di-force start.',
                ],
            ];
        }

        if (in_array($status, ['failed', 'warning'], true)) {
            return [
                'status_code' => 422,
                'payload' => [
                    'status' => 'error',
                    'message' => 'Snapshot rebuild berada pada state terminal atau tidak konsisten, sehingga tidak bisa di-force start.',
                ],
            ];
        }

        if (!in_array($stage, ['queued', 'planning'], true)) {
            return [
                'status_code' => 422,
                'payload' => [
                    'status' => 'error',
                    'message' => 'Force start hanya tersedia untuk snapshot rebuild yang masih queued.',
                ],
            ];
        }

        $runningState = $this->findRunningRebuildState([$rebuildId]);
        if ($runningState !== null) {
            return $this->activeRebuildResponse(
                (bool) ($state['force_rebuild'] ?? false),
                $runningState,
                'Force start ditolak karena rebuild snapshot lain masih berjalan. Tunggu selesai agar tidak terjadi rebuild paralel.'
            );
        }

        if (ManagedReportSnapshotRebuildStore::getState($rebuildId) === null) {
            $state = ManagedReportSnapshotRebuildStore::putState($state);
        }

        ManagedReportSnapshotRebuildStore::setActiveRebuildId($rebuildId);
        Cache::put(ManagedReportSnapshotRebuildStore::PENDING_KEY, $rebuildId, ManagedReportSnapshotRebuildStore::ttl());

        $deletedQueueRows = $queueRow ? $this->cleanupQueuedSnapshotJobs($rebuildId) : 0;
        if ($queueRow !== null) {
            Log::info('Managed report snapshot rebuild force start recovered queued job from queue row.', [
                'rebuild_id' => $rebuildId,
                'state_source' => $state['state_source'] ?? 'cache',
                'queue_job_id' => $queueRow['job_id'] ?? null,
                'queue_reserved' => (bool) ($queueRow['reserved'] ?? false),
                'deleted_queue_rows' => $deletedQueueRows,
            ]);
        }

        $executedState = $this->runInline($state, true);

        return [
            'status_code' => 200,
            'payload' => array_merge($executedState, [
                'status' => $executedState['status'] ?? 'completed',
                'message' => 'Force start snapshot dijalankan. Proses dipicu langsung tanpa menunggu worker queue.',
            ]),
        ];
    }

    public function reconcile(string $rebuildId): ?array
    {
        $state = $this->resolveOperationalState($rebuildId);
        if ($state === null) {
            return null;
        }

        $state = $this->maybeProcessFallback($state);

        return $this->reconcileStaleState($state);
    }

    public function resolveKnownRebuildIds(): array
    {
        $snapshotIds = [];
        $activeRebuildId = ManagedReportSnapshotRebuildStore::getActiveRebuildId();
        if ($activeRebuildId) {
            $snapshotIds[] = $activeRebuildId;
        }

        $pendingValue = Cache::get(ManagedReportSnapshotRebuildStore::PENDING_KEY);
        $pendingRebuildId = $this->extractManagedReportRebuildId($pendingValue);
        if ($pendingRebuildId) {
            $snapshotIds[] = $pendingRebuildId;
        }

        foreach ($this->snapshotQueueRows() as $queueRow) {
            $rebuildId = trim((string) ($queueRow['rebuild_id'] ?? ''));
            if ($rebuildId !== '') {
                $snapshotIds[] = $rebuildId;
            }
        }

        return array_values(array_unique($snapshotIds));
    }

    public function resolveOperationalState(string $rebuildId, ?array $queueRow = null): ?array
    {
        $rebuildId = trim($rebuildId);
        if ($rebuildId === '') {
            return null;
        }

        $state = ManagedReportSnapshotRebuildStore::getState($rebuildId);
        $queueRow = $queueRow ?? $this->findSnapshotQueueRow($rebuildId);
        $resolved = $this->reconcileStateWithQueueRow($rebuildId, $state, $queueRow);

        if ($resolved !== null) {
            return $resolved;
        }

        $hasTrackedMarker = ManagedReportSnapshotRebuildStore::getActiveRebuildId() === $rebuildId
            || $this->extractManagedReportRebuildId(Cache::get(ManagedReportSnapshotRebuildStore::PENDING_KEY)) === $rebuildId;

        if ($hasTrackedMarker) {
            Log::warning('Managed report snapshot rebuild markers exist without cache state or queue row.', [
                'rebuild_id' => $rebuildId,
                'state_source' => 'markers_only',
            ]);
        }

        return null;
    }

    public function snapshotQueueRows(): array
    {
        if (!Schema::hasTable('jobs')) {
            return [];
        }

        $configuredReportQueue = trim((string) config('queue.report_queue', 'default')) ?: 'default';
        $queues = array_values(array_unique(array_filter([$configuredReportQueue, 'default', 'reports-low'])));
        $basename = class_basename(RunManagedReportSnapshotRebuildJob::class);

        return DB::table('jobs')
            ->whereIn('queue', $queues)
            ->where('payload', 'like', '%' . $basename . '%')
            ->select(['id', 'queue', 'reserved_at', 'available_at', 'created_at', 'payload'])
            ->orderByDesc('id')
            ->get()
            ->map(function ($job): array {
                $payload = (string) ($job->payload ?? '');

                return [
                    'job_id' => (int) ($job->id ?? 0),
                    'queue' => (string) ($job->queue ?? ''),
                    'reserved' => $job->reserved_at !== null,
                    'reserved_at' => $job->reserved_at,
                    'reserved_age_seconds' => $this->queueTimestampAgeSeconds($job->reserved_at),
                    'created_at' => $job->created_at,
                    'created_age_seconds' => $this->queueTimestampAgeSeconds($job->created_at),
                    'available_at' => $job->available_at,
                    'payload' => $payload,
                    'rebuild_id' => $this->extractSnapshotRebuildIdFromPayload($payload),
                ];
            })
            ->filter(fn (array $row): bool => trim((string) ($row['rebuild_id'] ?? '')) !== '')
            ->values()
            ->all();
    }

    public function findSnapshotQueueRow(string $rebuildId): ?array
    {
        $needle = trim($rebuildId);
        if ($needle === '') {
            return null;
        }

        foreach ($this->snapshotQueueRows() as $queueRow) {
            if (trim((string) ($queueRow['rebuild_id'] ?? '')) === $needle) {
                return $queueRow;
            }
        }

        return null;
    }

    public function cleanupQueuedSnapshotJobs(string $rebuildId): int
    {
        $needle = trim($rebuildId);
        if ($needle === '' || !Schema::hasTable('jobs')) {
            return 0;
        }

        $basename = class_basename(RunManagedReportSnapshotRebuildJob::class);

        return DB::table('jobs')
            ->where('payload', 'like', '%' . $basename . '%')
            ->where('payload', 'like', '%' . $needle . '%')
            ->delete();
    }

    public function purgeStaleReservedQueueRows(): array
    {
        $purged = 0;
        $stale = 0;

        foreach ($this->snapshotQueueRows() as $queueRow) {
            if (!(bool) ($queueRow['reserved'] ?? false)) {
                continue;
            }

            if ((int) ($queueRow['reserved_age_seconds'] ?? 0) < self::SNAPSHOT_RESERVED_STALE_SECONDS) {
                continue;
            }

            $rebuildId = trim((string) ($queueRow['rebuild_id'] ?? ''));
            $state = $rebuildId !== '' ? ManagedReportSnapshotRebuildStore::getState($rebuildId) : null;
            $status = strtolower(trim((string) ($state['status'] ?? '')));
            if ($state !== null && !in_array($status, ['completed', 'failed', 'warning'], true)) {
                $stale++;
                continue;
            }

            DB::table('jobs')->where('id', (int) ($queueRow['job_id'] ?? 0))->delete();
            $purged++;

            Log::warning('Purged stale reserved managed report snapshot rebuild queue row without recoverable state.', [
                'rebuild_id' => $rebuildId !== '' ? $rebuildId : null,
                'queue_job_id' => $queueRow['job_id'] ?? null,
                'queue_reserved' => true,
                'state_source' => $state === null ? 'none' : 'terminal_cache',
            ]);
        }

        return [
            'purged_reserved_snapshot_jobs' => $purged,
            'stale_reserved_snapshot_jobs' => $stale,
        ];
    }

    public function sweepStaleStates(): int
    {
        $reconciled = 0;

        $activeRebuildId = ManagedReportSnapshotRebuildStore::getActiveRebuildId();
        if ($activeRebuildId !== null && $this->reconcile($activeRebuildId) !== null) {
            $reconciled++;
        }

        $pendingValue = Cache::get(ManagedReportSnapshotRebuildStore::PENDING_KEY);
        $pendingRebuildId = $this->extractManagedReportRebuildId($pendingValue);
        if ($pendingRebuildId !== null && $pendingRebuildId !== $activeRebuildId && $this->reconcile($pendingRebuildId) !== null) {
            $reconciled++;
        }

        $reconciled += $this->terminateStaleCachedStates();

        return $reconciled;
    }

    private function maybeProcessFallback(array $state): array
    {
        if (!$this->shouldAttemptFallback($state)) {
            return $state;
        }

        return $this->runInline($state);
    }

    private function runInline(array $state, bool $forceExecution = false): array
    {
        $rebuildId = trim((string) ($state['rebuild_id'] ?? ''));
        if ($rebuildId === '') {
            return $state;
        }

        ManagedReportSnapshotRebuildStore::setActiveRebuildId($rebuildId);
        Cache::put(ManagedReportSnapshotRebuildStore::PENDING_KEY, $rebuildId, ManagedReportSnapshotRebuildStore::ttl());

        $lock = Cache::lock(
            self::REBUILD_FALLBACK_LOCK_PREFIX . $rebuildId,
            self::REBUILD_FALLBACK_LOCK_SECONDS
        );

        if (!$lock->get()) {
            return $state;
        }

        try {
            $freshState = ManagedReportSnapshotRebuildStore::getState($rebuildId) ?? $state;
            if (!$forceExecution && !$this->shouldAttemptFallback($freshState)) {
                return $freshState;
            }

            app()->call([
                new RunManagedReportSnapshotRebuildJob(
                    (bool) ($freshState['force_rebuild'] ?? false),
                    $freshState['source'] ?? static::class,
                    $rebuildId
                ),
                'handle',
            ]);
        } catch (Throwable $e) {
            Log::warning('Fallback rebuild snapshot report management gagal dijalankan: ' . $e->getMessage(), [
                'rebuild_id' => $rebuildId,
                'exception_class' => $e::class,
            ]);
        } finally {
            optional($lock)->release();
        }

        return ManagedReportSnapshotRebuildStore::getState($rebuildId) ?? $state;
    }

    private function shouldAttemptFallback(array $state): bool
    {
        $rebuildId = trim((string) ($state['rebuild_id'] ?? ''));
        if ($rebuildId === '') {
            return false;
        }

        $status = strtolower(trim((string) ($state['status'] ?? '')));
        $stage = strtolower(trim((string) ($state['stage'] ?? '')));

        if (in_array($status, ['completed', 'failed', 'warning'], true)) {
            return false;
        }

        if (!in_array($stage, ['queued', 'planning'], true)) {
            return false;
        }

        $referenceTimestamp = in_array($stage, ['rebuilding', 'cache'], true)
            ? ($state['updated_at'] ?? $state['started_at'] ?? $state['created_at'] ?? null)
            : ($state['started_at'] ?: ($state['updated_at'] ?? $state['created_at'] ?? null));
        if (!$this->timestampIsStale($referenceTimestamp, self::REBUILD_FALLBACK_STALE_SECONDS)) {
            return false;
        }

        $activeRebuildId = ManagedReportSnapshotRebuildStore::getActiveRebuildId();

        if ($activeRebuildId !== null && $activeRebuildId !== $rebuildId) {
            return false;
        }

        return $this->findRunningRebuildState([$rebuildId]) === null;
    }

    private function activeRebuildResponse(bool $forceRebuild, ?array $state, string $message, ?string $fallbackRebuildId = null): array
    {
        return [
            'status_code' => 409,
            'payload' => [
                'status' => 'warning',
                'message' => $message,
                'force_rebuild' => $forceRebuild,
                'queued' => false,
                'rebuild_id' => $state['rebuild_id'] ?? $fallbackRebuildId,
                'progress_percent' => (int) ($state['progress_percent'] ?? 0),
                'completed_units' => (int) ($state['completed_units'] ?? 0),
                'total_units' => (int) ($state['total_units'] ?? 1),
                'stage' => $state['stage'] ?? 'queued',
                'current_report_label' => $state['current_report_label'] ?? null,
                'current_period' => $state['current_period'] ?? null,
            ],
        ];
    }

    private function findRunningRebuildState(array $excludeRebuildIds = []): ?array
    {
        $excluded = array_fill_keys(array_map(
            static fn (string $id): string => strtolower(trim($id)),
            $excludeRebuildIds
        ), true);

        foreach ($this->cachedRebuildStates() as $state) {
            $rebuildId = strtolower(trim((string) ($state['rebuild_id'] ?? '')));
            if ($rebuildId === '' || isset($excluded[$rebuildId])) {
                continue;
            }

            $status = strtolower(trim((string) ($state['status'] ?? '')));
            $stage = strtolower(trim((string) ($state['stage'] ?? '')));
            if ($status !== 'running' || !in_array($stage, ['planning', 'rebuilding', 'cache'], true)) {
                continue;
            }

            $updatedAt = (string) ($state['updated_at'] ?? $state['started_at'] ?? $state['created_at'] ?? '');
            if ($this->timestampIsOlderThan($updatedAt, self::RUNNING_FAIL_SECONDS)) {
                continue;
            }

            return $state;
        }

        return null;
    }

    private function cachedRebuildStates(): array
    {
        if (!Schema::hasTable('cache')) {
            return [];
        }

        try {
            return DB::table('cache')
                ->where('key', 'like', '%' . ManagedReportSnapshotRebuildStore::stateKey('') . '%')
                ->pluck('value')
                ->map(function ($value): ?array {
                    $decoded = @unserialize((string) $value);

                    return is_array($decoded) ? ManagedReportSnapshotRebuildStore::normalizeState($decoded, false) : null;
                })
                ->filter()
                ->values()
                ->all();
        } catch (Throwable $e) {
            Log::debug('Failed to scan cached managed report rebuild states.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function terminateStaleCachedStates(): int
    {
        $terminated = 0;

        foreach ($this->cachedRebuildStates() as $state) {
            $status = strtolower(trim((string) ($state['status'] ?? '')));
            $stage = strtolower(trim((string) ($state['stage'] ?? '')));
            if ($status === 'queued' && $this->timestampIsOlderThan((string) ($state['updated_at'] ?? ''), self::QUEUED_FAIL_SECONDS)) {
                $this->markStateAsFailed($state, 'Snapshot rebuild otomatis dihentikan karena terlalu lama menunggu worker tanpa queue aktif.');
                $terminated++;
                continue;
            }

            if ($status !== 'running' || !in_array($stage, ['planning', 'rebuilding', 'cache'], true)) {
                continue;
            }

            $updatedAt = (string) ($state['updated_at'] ?? $state['started_at'] ?? $state['created_at'] ?? '');
            if (!$this->timestampIsOlderThan($updatedAt, self::RUNNING_FAIL_SECONDS)) {
                continue;
            }

            $this->markStateAsFailed($state, 'Snapshot rebuild otomatis dihentikan karena progress tidak bergerak terlalu lama.');
            $terminated++;
        }

        return $terminated;
    }

    private function reconcileStateWithQueueRow(string $rebuildId, ?array $state, ?array $queueRow): ?array
    {
        if ($state === null) {
            if ($queueRow === null) {
                return null;
            }

            $syntheticState = $this->makeSyntheticState($rebuildId, $queueRow);
            Log::warning('Recovered managed report snapshot rebuild state from queue row without cache state.', [
                'rebuild_id' => $rebuildId,
                'state_source' => 'synthetic',
                'queue_job_id' => $queueRow['job_id'] ?? null,
                'queue_reserved' => (bool) ($queueRow['reserved'] ?? false),
            ]);

            return $syntheticState;
        }

        $status = strtolower(trim((string) ($state['status'] ?? '')));
        if (in_array($status, ['completed', 'failed', 'warning'], true)) {
            $state['state_source'] = 'cache';

            return $state;
        }

        $updatedAt = (string) ($state['updated_at'] ?? $state['started_at'] ?? $state['created_at'] ?? '');

        if (
            $queueRow === null
            && $this->stageCanFailWithoutQueueRow($state)
            && $this->timestampIsOlderThan($updatedAt, self::SNAPSHOT_QUEUELESS_STALE_SECONDS)
        ) {
            return $this->markStateAsFailed(
                $state,
                'Progress snapshot tidak lagi memiliki job queue aktif dan tidak bergerak terlalu lama. State dibersihkan otomatis.'
            );
        }

        if (
            $queueRow !== null
            && (bool) ($queueRow['reserved'] ?? false)
            && (int) ($queueRow['reserved_age_seconds'] ?? 0) >= self::SNAPSHOT_RESERVED_STALE_SECONDS
            && $this->timestampIsOlderThan($updatedAt, self::SNAPSHOT_RESERVED_STALE_SECONDS)
        ) {
            if (isset($queueRow['job_id'])) {
                DB::table('jobs')->where('id', $queueRow['job_id'])->delete();
            }

            return $this->markStateAsFailed(
                $state,
                'Job snapshot sudah di-reserve worker tetapi progress tidak bergerak terlalu lama. Kemungkinan worker berhenti di tengah proses.'
            );
        }

        if ($queueRow !== null && (bool) ($queueRow['reserved'] ?? false) && $status === 'queued') {
            $state['status'] = 'running';
            $state['stage'] = in_array(strtolower(trim((string) ($state['stage'] ?? ''))), ['queued'], true)
                ? 'planning'
                : ($state['stage'] ?? 'planning');
            $state['queued'] = false;
            $state['message'] = trim((string) ($state['message'] ?? '')) !== ''
                ? (string) $state['message']
                : 'Snapshot rebuild sedang diproses worker queue.';
        }

        $state['state_source'] = 'cache';
        if ($queueRow !== null) {
            $state['queue_job_id'] = $queueRow['job_id'] ?? null;
            $state['queue_reserved'] = (bool) ($queueRow['reserved'] ?? false);
        }

        return $state;
    }

    private function stageCanFailWithoutQueueRow(array $state): bool
    {
        $stage = strtolower(trim((string) ($state['stage'] ?? '')));

        return in_array($stage, ['queued', 'planning'], true);
    }

    private function makeSyntheticState(string $rebuildId, array $queueRow): array
    {
        $timestamp = now()->toIso8601String();
        $reserved = (bool) ($queueRow['reserved'] ?? false);
        $createdAt = $this->queueTimestampToIso8601($queueRow['created_at'] ?? null) ?? $timestamp;
        $updatedAt = $reserved
            ? ($this->queueTimestampToIso8601($queueRow['reserved_at'] ?? null) ?? $createdAt)
            : $createdAt;

        return ManagedReportSnapshotRebuildStore::normalizeState([
            'rebuild_id' => $rebuildId,
            'status' => $reserved ? 'running' : 'queued',
            'stage' => $reserved ? 'planning' : 'queued',
            'queued' => !$reserved,
            'force_rebuild' => true,
            'source' => static::class,
            'message' => $reserved
                ? 'Snapshot rebuild sedang diproses worker queue.'
                : 'Snapshot rebuild masih menunggu worker queue.',
            'progress_percent' => 0,
            'completed_units' => 0,
            'total_units' => 1,
            'build_units' => 0,
            'current_report_key' => null,
            'current_report_label' => null,
            'current_period' => null,
            'report_completed_units' => 0,
            'report_total_units' => 0,
            'reports' => [],
            'results' => [],
            'started_at' => $reserved ? $updatedAt : null,
            'finished_at' => null,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
            'state_source' => 'synthetic',
            'queue_job_id' => $queueRow['job_id'] ?? null,
            'queue_reserved' => $reserved,
        ], false);
    }

    private function markStateAsFailed(array $state, string $message): array
    {
        $rebuildId = trim((string) ($state['rebuild_id'] ?? ''));
        $failedState = ManagedReportSnapshotRebuildStore::putState(array_merge($state, [
            'status' => 'failed',
            'stage' => 'failed',
            'queued' => false,
            'message' => $message,
            'error' => $message,
            'finished_at' => now()->toIso8601String(),
            'state_source' => 'cache',
        ]));

        if ($rebuildId !== '' && ManagedReportSnapshotRebuildStore::getActiveRebuildId() === $rebuildId) {
            ManagedReportSnapshotRebuildStore::forgetActiveRebuildId();
        }

        $pendingRebuildId = $this->extractManagedReportRebuildId(Cache::get(ManagedReportSnapshotRebuildStore::PENDING_KEY));
        if ($rebuildId !== '' && $pendingRebuildId === $rebuildId) {
            Cache::forget(ManagedReportSnapshotRebuildStore::PENDING_KEY);
        }

        Log::warning('Managed report snapshot rebuild state marked failed during reconciliation.', [
            'rebuild_id' => $rebuildId !== '' ? $rebuildId : null,
            'state_source' => 'cache',
            'reason' => $message,
        ]);

        return $failedState;
    }

    private function reconcileStaleState(array $state): array
    {
        $status = strtolower(trim((string) ($state['status'] ?? '')));
        $stage = strtolower(trim((string) ($state['stage'] ?? '')));
        if (in_array($status, ['completed', 'failed', 'warning'], true)) {
            return $state;
        }

        $referenceTimestamp = in_array($stage, ['rebuilding', 'cache'], true)
            ? ($state['updated_at'] ?? $state['started_at'] ?? $state['created_at'] ?? null)
            : ($state['started_at'] ?: ($state['updated_at'] ?? $state['created_at'] ?? null));

        $shouldFail = match (true) {
            in_array($stage, ['queued', 'planning'], true) => $this->timestampIsStale($referenceTimestamp, self::QUEUED_FAIL_SECONDS),
            in_array($stage, ['rebuilding', 'cache'], true) => $this->timestampIsStale($referenceTimestamp, self::RUNNING_FAIL_SECONDS),
            default => false,
        };

        if (!$shouldFail) {
            return $state;
        }

        $failedState = ManagedReportSnapshotRebuildStore::putState(array_merge($state, [
            'status' => 'failed',
            'stage' => 'failed',
            'queued' => false,
            'message' => 'Rebuild snapshot gagal otomatis karena progress tidak bergerak terlalu lama. Jalankan ulang proses.',
            'error' => 'Managed report snapshot rebuild stale timeout.',
            'finished_at' => now()->toIso8601String(),
        ]));

        Log::warning('Rebuild snapshot report management ditandai gagal karena stale.', [
            'rebuild_id' => $state['rebuild_id'] ?? null,
            'stage' => $stage,
            'updated_at' => $state['updated_at'] ?? null,
        ]);

        if (ManagedReportSnapshotRebuildStore::getActiveRebuildId() === ($state['rebuild_id'] ?? null)) {
            ManagedReportSnapshotRebuildStore::forgetActiveRebuildId();
        }
        Cache::forget(ManagedReportSnapshotRebuildStore::PENDING_KEY);

        return $failedState;
    }

    private function shouldRecoverManagedReportRebuildSlot(mixed $pendingValue, ?string $activeRebuildId, ?array $activeState): bool
    {
        if ($activeState !== null) {
            return false;
        }

        if ($activeRebuildId !== null) {
            return true;
        }

        if (is_string($pendingValue) && trim($pendingValue) !== '' && !$this->looksLikeUuid($pendingValue)) {
            return true;
        }

        return $this->extractManagedReportRebuildId($pendingValue) === null;
    }

    private function extractManagedReportRebuildId(mixed $value): ?string
    {
        if (is_array($value)) {
            $candidate = trim((string) ($value['rebuild_id'] ?? ''));

            return $this->looksLikeUuid($candidate) ? $candidate : null;
        }

        if (!is_string($value)) {
            return null;
        }

        $candidate = trim($value);

        return $this->looksLikeUuid($candidate) ? $candidate : null;
    }

    private function looksLikeUuid(?string $value): bool
    {
        $candidate = trim((string) $value);

        return $candidate !== '' && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $candidate) === 1;
    }

    private function timestampIsStale(?string $value, int $thresholdSeconds): bool
    {
        $candidate = trim((string) $value);
        if ($candidate === '') {
            return true;
        }

        try {
            return \Carbon\Carbon::parse($candidate)
                ->addSeconds(max(0, $thresholdSeconds))
                ->lessThanOrEqualTo(now());
        } catch (Throwable) {
            return true;
        }
    }

    private function extractSnapshotRebuildIdFromPayload(string $payload): ?string
    {
        $candidate = '';

        if (preg_match('/rebuildId";s:\d+:"([0-9a-f\-]{36})"/i', $payload, $matches) === 1) {
            $candidate = (string) ($matches[1] ?? '');
        }

        if ($candidate === '' && preg_match('/"rebuildId":"([0-9a-f\-]{36})"/i', $payload, $matches) === 1) {
            $candidate = (string) ($matches[1] ?? '');
        }

        $candidate = trim($candidate);

        return $candidate !== '' ? $candidate : null;
    }

    private function queueTimestampAgeSeconds(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return max(0, now()->timestamp - (int) $value);
        }

        try {
            return max(0, now()->diffInSeconds(Carbon::parse($value)));
        } catch (Throwable) {
            return 0;
        }
    }

    private function queueTimestampToIso8601(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value)->toIso8601String();
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }

    private function timestampIsOlderThan(?string $value, int $seconds): bool
    {
        $candidate = trim((string) $value);
        if ($candidate === '') {
            return true;
        }

        try {
            return Carbon::parse($candidate)
                ->addSeconds(max(1, $seconds))
                ->lessThanOrEqualTo(now());
        } catch (Throwable) {
            return true;
        }
    }
}
