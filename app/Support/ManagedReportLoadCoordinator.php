<?php

namespace App\Support;

use App\Jobs\RunManagedReportLoadJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ManagedReportLoadCoordinator
{
    private const LOAD_QUEUE = 'imports-high';
    private const FALLBACK_LOCK_PREFIX = 'report_management:load:lock:';
    private const FALLBACK_LOCK_SECONDS = 1800;
    private const FALLBACK_STALE_SECONDS = 1;
    private const QUEUED_FAIL_SECONDS = 120;
    private const RUNNING_FAIL_SECONDS = 300;

    public function queue(int $reportId, array $options, ?string $source = null): array
    {
        $loadId = (string) Str::uuid();
        $state = ManagedReportLoadStore::createInitialState($loadId, $reportId, $options, $source);
        ManagedReportLoadStore::putState($state);

        try {
            RunManagedReportLoadJob::dispatch($reportId, $options, $source, $loadId)
                ->onQueue(self::LOAD_QUEUE);
        } catch (Throwable $e) {
            ManagedReportLoadStore::putState(array_merge($state, [
                'queued' => false,
                'message' => 'Queue tidak tersedia. Load data akan diproses lewat fallback saat status dipoll dari halaman ini.',
                'error' => $e->getMessage(),
            ]));

            Log::warning('Gagal menjadwalkan load report management: ' . $e->getMessage(), [
                'report_id' => $reportId,
                'load_id' => $loadId,
                'exception_class' => $e::class,
            ]);
        }

        return [
            'status_code' => 200,
            'payload' => [
                'status' => 'queued',
                'queued' => true,
                'load_id' => $loadId,
                'report_id' => $reportId,
                'progress_percent' => 0,
                'completed_units' => 0,
                'total_units' => 4,
                'stage' => 'queued',
                'message' => 'Load data report management dimulai di queue prioritas tinggi. Progress akan tampil realtime.',
            ],
        ];
    }

    public function status(string $loadId): array
    {
        $state = $this->reconcile($loadId);
        if ($state === null) {
            return [
                'status_code' => 404,
                'payload' => [
                    'status' => 'error',
                    'message' => 'Progress load report tidak ditemukan atau sudah kedaluwarsa.',
                ],
            ];
        }

        return [
            'status_code' => 200,
            'payload' => $this->maybeProcessFallback($state),
        ];
    }

    public function reconcile(string $loadId): ?array
    {
        $state = ManagedReportLoadStore::getState($loadId);
        if ($state === null) {
            return null;
        }

        $state = $this->maybeProcessFallback($state);

        return $this->reconcileStaleState($state);
    }

    public function sweepStaleStates(): int
    {
        $reconciled = 0;

        foreach (ManagedReportLoadStore::activeIds() as $loadId) {
            if ($this->reconcile($loadId) !== null) {
                $reconciled++;
            }
        }

        return $reconciled;
    }

    private function maybeProcessFallback(array $state): array
    {
        return $state;
    }

    private function shouldAttemptFallback(array $state): bool
    {
        $loadId = trim((string) ($state['load_id'] ?? ''));
        if ($loadId === '') {
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
            in_array($stage, ['validating', 'scanning_columns', 'grouping', 'counting', 'finalizing', 'running'], true)
                => $this->timestampIsStale($referenceTimestamp, self::RUNNING_FAIL_SECONDS),
            default => false,
        };

        if (!$shouldFail) {
            return $state;
        }

        $failedState = ManagedReportLoadStore::putState(array_merge($state, [
            'status' => 'failed',
            'stage' => 'failed',
            'queued' => false,
            'message' => 'Load report management gagal otomatis karena progress tidak bergerak terlalu lama. Jalankan ulang proses.',
            'error' => 'Load report management stale timeout.',
            'finished_at' => now()->toIso8601String(),
        ]));

        Log::warning('Load report management ditandai gagal karena stale.', [
            'load_id' => $state['load_id'] ?? null,
            'report_id' => $state['report_id'] ?? null,
            'stage' => $stage,
            'updated_at' => $state['updated_at'] ?? null,
        ]);

        return $failedState;
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
