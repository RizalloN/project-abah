<?php

namespace App\Jobs;

use App\Services\ManagedReportBackupRecoveryService;
use App\Support\ManagedReportRecoveryStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class RunManagedReportRecoveryJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 0;

    private const PROCESS_LOCK_PREFIX = 'report_management:recovery:process:';
    private const PROCESS_LOCK_SECONDS = 14400;
    private const TOTAL_UNITS = 6;

    public function __construct(
        public int $reportId,
        public string $backupPath,
        public ?string $source = null,
        public ?string $recoveryId = null
    ) {
    }

    public function handle(ManagedReportBackupRecoveryService $service): void
    {
        $recoveryId = trim((string) $this->recoveryId);
        if ($recoveryId === '') {
            $recoveryId = (string) Str::uuid();
        }

        $lock = Cache::lock(self::PROCESS_LOCK_PREFIX . $recoveryId, self::PROCESS_LOCK_SECONDS);
        if (!$lock->get()) {
            return;
        }

        try {
            $state = ManagedReportRecoveryStore::getState($recoveryId)
                ?? ManagedReportRecoveryStore::createInitialState($recoveryId, $this->reportId, $this->backupPath, $this->source ?? static::class);

            if (in_array(strtolower((string) ($state['status'] ?? '')), ['completed', 'failed'], true)) {
                return;
            }

            $state = $this->writeState(array_merge($state, [
                'report_id' => $this->reportId,
                'backup_path' => $this->backupPath,
                'source' => $this->source ?? static::class,
                'status' => 'running',
                'stage' => 'validating',
                'queued' => false,
                'started_at' => $state['started_at'] ?? now()->toIso8601String(),
                'message' => 'Memvalidasi report recovery dan backup SQL...',
                'progress_percent' => 5,
                'completed_units' => 0,
                'total_units' => self::TOTAL_UNITS,
                'error' => null,
            ]));

            $result = $service->recoverReportTable(
                $this->reportId,
                $this->backupPath,
                function (array $progress) use (&$state): void {
                    $state = $this->writeState(array_merge($state, [
                        'status' => 'running',
                        'stage' => (string) ($progress['stage'] ?? 'running'),
                        'message' => (string) ($progress['message'] ?? 'Recovery backup sedang diproses...'),
                        'progress_percent' => max(0, min(100, (int) ($progress['progress_percent'] ?? 0))),
                        'completed_units' => (int) ($progress['completed_units'] ?? $state['completed_units'] ?? 0),
                        'total_units' => max(1, (int) ($progress['total_units'] ?? $state['total_units'] ?? self::TOTAL_UNITS)),
                    ]));
                }
            );

            $restoredRows = max(0, (int) ($result['restored_rows'] ?? 0));

            $this->writeState(array_merge($state, [
                'status' => 'completed',
                'stage' => 'completed',
                'message' => "Recovery selesai. {$restoredRows} baris berhasil dipulihkan dari backup.",
                'progress_percent' => 100,
                'completed_units' => self::TOTAL_UNITS,
                'total_units' => self::TOTAL_UNITS,
                'result' => $result,
                'error' => null,
                'finished_at' => now()->toIso8601String(),
            ]));
        } catch (Throwable $e) {
            Log::warning('Recovery backup report management gagal: ' . $e->getMessage(), [
                'recovery_id' => $recoveryId,
                'report_id' => $this->reportId,
                'backup_path' => $this->backupPath,
                'exception_class' => $e::class,
            ]);

            $state = ManagedReportRecoveryStore::getState($recoveryId)
                ?? ManagedReportRecoveryStore::createInitialState($recoveryId, $this->reportId, $this->backupPath, $this->source ?? static::class);

            $this->writeState(array_merge($state, [
                'status' => 'failed',
                'stage' => 'failed',
                'message' => 'Recovery backup gagal: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'finished_at' => now()->toIso8601String(),
            ]));
        } finally {
            optional($lock)->release();
        }
    }

    private function writeState(array $state): array
    {
        return ManagedReportRecoveryStore::putState($state);
    }
}
