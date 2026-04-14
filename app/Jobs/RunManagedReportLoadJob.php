<?php

namespace App\Jobs;

use App\Support\ManagedReportLoadStore;
use App\Support\ManagedReportManagementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class RunManagedReportLoadJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 0;

    private const PROCESS_LOCK_PREFIX = 'report_management:load:process:';
    private const PROCESS_LOCK_SECONDS = 1800;
    private const TOTAL_UNITS = 4;

    public function __construct(
        public int $reportId,
        public array $options = [],
        public ?string $source = null,
        public ?string $loadId = null
    ) {
    }

    public function handle(ManagedReportManagementService $service): void
    {
        $loadId = trim((string) $this->loadId);
        if ($loadId === '') {
            $loadId = (string) Str::uuid();
        }

        $lock = Cache::lock(self::PROCESS_LOCK_PREFIX . $loadId, self::PROCESS_LOCK_SECONDS);
        if (!$lock->get()) {
            return;
        }

        try {
            $state = ManagedReportLoadStore::getState($loadId)
                ?? ManagedReportLoadStore::createInitialState($loadId, $this->reportId, $this->options, $this->source ?? static::class);

            if (in_array(strtolower((string) ($state['status'] ?? '')), ['completed', 'failed'], true)) {
                return;
            }

            $state = $this->writeState(array_merge($state, [
                'report_id' => $this->reportId,
                'options' => $this->options,
                'source' => $this->source ?? static::class,
                'status' => 'running',
                'stage' => 'validating',
                'queued' => false,
                'started_at' => $state['started_at'] ?? now()->toIso8601String(),
                'message' => 'Memvalidasi report dan tabel sumber...',
                'progress_percent' => 5,
                'completed_units' => 0,
                'total_units' => self::TOTAL_UNITS,
                'error' => null,
            ]));

            $resolved = $service->resolveReportManagementData(
                $this->reportId,
                $this->options,
                false,
                function (array $progress) use (&$state): void {
                    $percent = $progress['progress_percent'] ?? $this->calculatePercent(
                        (int) ($progress['completed_units'] ?? 0),
                        (int) ($progress['total_units'] ?? self::TOTAL_UNITS)
                    );

                    $state = $this->writeState(array_merge($state, [
                        'status' => 'running',
                        'stage' => (string) ($progress['stage'] ?? 'running'),
                        'message' => (string) ($progress['message'] ?? 'Memuat data report management...'),
                        'progress_percent' => $percent,
                        'completed_units' => (int) ($progress['completed_units'] ?? $state['completed_units'] ?? 0),
                        'total_units' => max(1, (int) ($progress['total_units'] ?? $state['total_units'] ?? self::TOTAL_UNITS)),
                    ]));
                }
            );

            if (($resolved['ok'] ?? false) !== true) {
                $payload = (array) ($resolved['payload'] ?? []);
                $message = (string) ($payload['message'] ?? 'Gagal memuat data report management.');

                $this->writeState(array_merge($state, [
                    'status' => 'failed',
                    'stage' => 'failed',
                    'message' => $message,
                    'error' => $message,
                    'finished_at' => now()->toIso8601String(),
                ]));

                return;
            }

            $payload = (array) ($resolved['payload'] ?? []);
            if (isset($payload['table_name'])) {
                $payload['duplicate_cleanup_available'] = strtolower(trim((string) $payload['table_name'])) === 'simpanan_multipn';
            }

            $groups = max(0, (int) ($payload['total_groups'] ?? 0));
            $rows = max(0, (int) ($payload['grand_total_rows'] ?? 0));

            $this->writeState(array_merge($state, [
                'status' => 'completed',
                'stage' => 'completed',
                'message' => "Data siap ditampilkan. {$groups} grup dan {$rows} baris sumber berhasil dipetakan.",
                'progress_percent' => 100,
                'completed_units' => self::TOTAL_UNITS,
                'total_units' => self::TOTAL_UNITS,
                'result' => $payload,
                'error' => null,
                'finished_at' => now()->toIso8601String(),
            ]));
        } catch (Throwable $e) {
            Log::warning('Load report management gagal: ' . $e->getMessage(), [
                'load_id' => $loadId,
                'report_id' => $this->reportId,
                'exception_class' => $e::class,
            ]);

            $state = ManagedReportLoadStore::getState($loadId)
                ?? ManagedReportLoadStore::createInitialState($loadId, $this->reportId, $this->options, $this->source ?? static::class);

            $this->writeState(array_merge($state, [
                'status' => 'failed',
                'stage' => 'failed',
                'message' => 'Gagal memuat data report management: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'finished_at' => now()->toIso8601String(),
            ]));
        } finally {
            optional($lock)->release();
        }
    }

    private function writeState(array $state): array
    {
        return ManagedReportLoadStore::putState($state);
    }

    private function calculatePercent(int $completedUnits, int $totalUnits): int
    {
        if ($totalUnits <= 0) {
            return 0;
        }

        return max(0, min(100, (int) round(($completedUnits / $totalUnits) * 100)));
    }
}
