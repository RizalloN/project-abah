<?php

namespace App\Support;

use App\Jobs\RunManagedReportRecoveryJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ManagedReportRecoveryCoordinator
{
    private const RECOVERY_QUEUE = 'imports-high';
    private const FALLBACK_LOCK_PREFIX = 'report_management:recovery:fallback:';
    private const FALLBACK_LOCK_SECONDS = 14400;
    private const FALLBACK_STALE_SECONDS = 15;
    private const QUEUED_FAIL_SECONDS = 600;
    private const RUNNING_FAIL_SECONDS = 14400;

    public function queue(int $reportId, string $backupPath, ?string $source = null): array
    {
        $recoveryId = (string) Str::uuid();
        $state = ManagedReportRecoveryStore::createInitialState($recoveryId, $reportId, $backupPath, $source);
        ManagedReportRecoveryStore::putState($state);

        try {
            RunManagedReportRecoveryJob::dispatch($reportId, $backupPath, $source, $recoveryId)
                ->onQueue(self::RECOVERY_QUEUE);
        } catch (Throwable $e) {
            ManagedReportRecoveryStore::putState(array_merge($state, [
                'queued' => false,
                'message' => 'Queue tidak tersedia. Recovery akan diproses lewat fallback saat status dipoll dari halaman ini.',
                'error' => $e->getMessage(),
            ]));

            Log::warning('Gagal menjadwalkan recovery backup report management: ' . $e->getMessage(), [
                'report_id' => $reportId,
                'recovery_id' => $recoveryId,
                'exception_class' => $e::class,
            ]);
        }

        return [
            'status_code' => 200,
            'payload' => [
                'status' => 'queued',
                'queued' => true,
                'recovery_id' => $recoveryId,
                'report_id' => $reportId,
                'progress_percent' => 0,
                'completed_units' => 0,
                'total_units' => 6,
                'stage' => 'queued',
                'message' => 'Recovery backup report dimulai. Progress akan tampil realtime.',
            ],
        ];
    }

    public function status(string $recoveryId): array
    {
        $state = $this->reconcile($recoveryId);
        if ($state === null) {
            return [
                'status_code' => 404,
                'payload' => [
                    'status' => 'error',
                    'message' => 'Progress recovery backup tidak ditemukan atau sudah kedaluwarsa.',
                ],
            ];
        }

        return [
            'status_code' => 200,
            'payload' => $state,
        ];
    }

    public function reconcile(string $recoveryId): ?array
    {
        $state = ManagedReportRecoveryStore::getState($recoveryId);
        if ($state === null) {
            return null;
        }

        $state = $this->maybeProcessFallback($state);

        return $this->reconcileStaleState($state);
    }

    private function maybeProcessFallback(array $state): array
    {
        if (!$this->shouldAttemptFallback($state)) {
            return $state;
        }

        $recoveryId = trim((string) ($state['recovery_id'] ?? ''));
        $lock = Cache::lock(self::FALLBACK_LOCK_PREFIX . $recoveryId, self::FALLBACK_LOCK_SECONDS);
        if (!$lock->get()) {
            return $state;
        }

        try {
            $freshState = ManagedReportRecoveryStore::getState($recoveryId) ?? $state;
            if (!$this->shouldAttemptFallback($freshState)) {
                return $freshState;
            }

            app()->call([
                new RunManagedReportRecoveryJob(
                    (int) ($freshState['report_id'] ?? 0),
                    (string) ($freshState['backup_path'] ?? ''),
                    $freshState['source'] ?? static::class,
                    $recoveryId
                ),
                'handle',
            ]);
        } catch (Throwable $e) {
            Log::warning('Fallback recovery backup report management gagal dijalankan: ' . $e->getMessage(), [
                'recovery_id' => $recoveryId,
                'exception_class' => $e::class,
            ]);
        } finally {
            optional($lock)->release();
        }

        return ManagedReportRecoveryStore::getState($recoveryId) ?? $state;
    }

    private function shouldAttemptFallback(array $state): bool
    {
        $recoveryId = trim((string) ($state['recovery_id'] ?? ''));
        if ($recoveryId === '') {
            return false;
        }

        $status = strtolower(trim((string) ($state['status'] ?? '')));
        $stage = strtolower(trim((string) ($state['stage'] ?? '')));
        if (in_array($status, ['completed', 'failed'], true)) {
            return false;
        }

        if ($stage !== 'queued') {
            return false;
        }

        $referenceTimestamp = $state['started_at'] ?: ($state['updated_at'] ?? $state['created_at'] ?? null);

        return $this->timestampIsStale($referenceTimestamp, self::FALLBACK_STALE_SECONDS);
    }

    private function reconcileStaleState(array $state): array
    {
        $status = strtolower(trim((string) ($state['status'] ?? '')));
        $stage = strtolower(trim((string) ($state['stage'] ?? '')));
        if (in_array($status, ['completed', 'failed'], true)) {
            return $state;
        }

        $referenceTimestamp = $state['started_at'] ?: ($state['updated_at'] ?? $state['created_at'] ?? null);
        $shouldFail = match (true) {
            $stage === 'queued' => $this->timestampIsStale($referenceTimestamp, self::QUEUED_FAIL_SECONDS),
            in_array($stage, ['validating', 'extracting_backup', 'importing_backup', 'swapping_data', 'syncing', 'cleanup', 'running'], true)
                => $this->timestampIsStale($referenceTimestamp, self::RUNNING_FAIL_SECONDS),
            default => false,
        };

        if (!$shouldFail) {
            return $state;
        }

        return ManagedReportRecoveryStore::putState(array_merge($state, [
            'status' => 'failed',
            'stage' => 'failed',
            'queued' => false,
            'message' => 'Recovery backup gagal otomatis karena progress tidak bergerak terlalu lama. Jalankan ulang proses.',
            'error' => 'Managed report recovery stale timeout.',
            'finished_at' => now()->toIso8601String(),
        ]));
    }

    private function timestampIsStale(?string $value, int $thresholdSeconds): bool
    {
        $candidate = trim((string) $value);
        if ($candidate === '') {
            return true;
        }

        try {
            return now()->diffInSeconds($candidate) >= $thresholdSeconds;
        } catch (Throwable) {
            return true;
        }
    }
}
