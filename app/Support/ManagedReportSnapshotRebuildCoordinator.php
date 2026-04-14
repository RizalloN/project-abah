<?php

namespace App\Support;

use App\Jobs\RunManagedReportSnapshotRebuildJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ManagedReportSnapshotRebuildCoordinator
{
    private const REBUILD_FALLBACK_LOCK_PREFIX = 'report_management_rebuild_lock:';
    private const REBUILD_FALLBACK_LOCK_SECONDS = 7200;
    private const REBUILD_FALLBACK_STALE_SECONDS = 15;
    private const QUEUED_FAIL_SECONDS = 300;
    private const RUNNING_FAIL_SECONDS = 1800;

    public function queue(bool $forceRebuild, ?string $source = null): array
    {
        $rebuildId = (string) Str::uuid();
        $slotReserved = false;

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
                Cache::forget(ManagedReportSnapshotRebuildStore::PENDING_KEY);
                ManagedReportSnapshotRebuildStore::forgetActiveRebuildId();

                if ($activeRebuildId) {
                    ManagedReportSnapshotRebuildStore::forgetState($activeRebuildId);
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
                }
            }

            if (!$slotReserved) {
                return [
                    'status_code' => 409,
                    'payload' => [
                        'status' => 'warning',
                        'message' => 'Rebuild snapshot seluruh report sudah sedang berjalan atau masih antre.',
                        'force_rebuild' => $forceRebuild,
                        'queued' => false,
                        'rebuild_id' => $activeRebuildId,
                        'progress_percent' => (int) ($activeState['progress_percent'] ?? 0),
                        'completed_units' => (int) ($activeState['completed_units'] ?? 0),
                        'total_units' => (int) ($activeState['total_units'] ?? 1),
                        'stage' => $activeState['stage'] ?? 'queued',
                        'current_report_label' => $activeState['current_report_label'] ?? null,
                        'current_period' => $activeState['current_period'] ?? null,
                    ],
                ];
            }
        }

        $state = ManagedReportSnapshotRebuildStore::createInitialState($rebuildId, $forceRebuild, $source);
        ManagedReportSnapshotRebuildStore::setActiveRebuildId($rebuildId);
        ManagedReportSnapshotRebuildStore::putState($state);

        try {
            RunManagedReportSnapshotRebuildJob::dispatch($forceRebuild, $source, $rebuildId)
                ->onQueue('reports-low');
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
            'payload' => $this->maybeProcessFallback($state),
        ];
    }

    public function reconcile(string $rebuildId): ?array
    {
        $state = ManagedReportSnapshotRebuildStore::getState($rebuildId);
        if ($state === null) {
            return null;
        }

        $state = $this->maybeProcessFallback($state);

        return $this->reconcileStaleState($state);
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

        return $reconciled;
    }

    private function maybeProcessFallback(array $state): array
    {
        return $state;
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

        $referenceTimestamp = $state['started_at'] ?: ($state['updated_at'] ?? $state['created_at'] ?? null);
        if (!$this->timestampIsStale($referenceTimestamp, self::REBUILD_FALLBACK_STALE_SECONDS)) {
            return false;
        }

        $activeRebuildId = ManagedReportSnapshotRebuildStore::getActiveRebuildId();

        return $activeRebuildId === null || $activeRebuildId === $rebuildId;
    }

    private function reconcileStaleState(array $state): array
    {
        $status = strtolower(trim((string) ($state['status'] ?? '')));
        $stage = strtolower(trim((string) ($state['stage'] ?? '')));
        if (in_array($status, ['completed', 'failed', 'warning'], true)) {
            return $state;
        }

        $referenceTimestamp = $state['started_at'] ?: ($state['updated_at'] ?? $state['created_at'] ?? null);

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
}
