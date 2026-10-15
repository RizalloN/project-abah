<?php

namespace App\Console\Commands;

use App\Support\PeriodSnapshotSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SnapshotForceSyncCommand extends Command
{
    protected $signature = 'snapshot:force-sync {--period= : Periode (DD-MM-YYYY atau YYYY-MM-DD)} {--all : Diabaikan; semua snapshot periode terpilih selalu dibangun ulang} {--sync-id= : ID untuk cache progress tracking}';

    protected $description = 'Bangun ulang (force) seluruh snapshot report untuk satu periode, hanya dari sumber yang memiliki data pada tanggal tersebut';

    public function handle(PeriodSnapshotSyncService $service): int
    {
        $syncId = trim((string) $this->option('sync-id')) ?: null;
        $period = PeriodSnapshotSyncService::normalizePeriod((string) $this->option('period'));

        if ($period === null) {
            $this->error('--period wajib diisi dengan tanggal valid. Contoh: --period=14-10-2026');
            $this->updateCache($syncId, ['status' => 'failed', 'progress' => 100, 'message' => 'Periode tidak valid. Gunakan format DD-MM-YYYY.']);

            return self::FAILURE;
        }

        $label = $service->displayDate($period);
        $this->info("Sinkronisasi snapshot periode {$label}");
        $this->updateCache($syncId, ['status' => 'running', 'progress' => 0, 'message' => "Memeriksa report yang memiliki data {$label}..."]);

        try {
            $summary = $service->run($period, function (array $state) use ($syncId): void {
                $this->line('  ▪ ' . $state['message']);
                $this->updateCache($syncId, [
                    'status' => 'running',
                    'progress' => $state['percent'],
                    'completed_tables' => $state['completed'],
                    'total_tables' => $state['total'],
                    'message' => $state['message'],
                    'steps' => $this->stepSummary($state['plan']),
                    'reports' => $state['plan']['reports'],
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('snapshot:force-sync gagal', ['period' => $period, 'message' => $e->getMessage()]);
            $this->error($e->getMessage());
            $this->updateCache($syncId, ['status' => 'failed', 'progress' => 100, 'message' => 'Sinkronisasi gagal: ' . $e->getMessage()]);

            return self::FAILURE;
        }

        $plan = $summary['plan'];
        $skipped = collect($plan['steps'])->reject(fn (array $step): bool => $step['available'])->pluck('label')->all();
        $errors = $summary['errors'];
        $message = $summary['rebuilt'] === 0 && $errors === []
            ? "Tidak ada snapshot yang bersumber dari data {$label}. Cache " . count($plan['reports']) . ' report telah disegarkan.'
            : sprintf(
                '%d snapshot periode %s dibangun ulang, cache %d report disegarkan.%s%s',
                $summary['rebuilt'],
                $label,
                count($plan['reports']),
                $errors !== [] ? ' Gagal: ' . implode(', ', array_keys($errors)) . '.' : '',
                $skipped !== [] ? ' Dilewati (sumber tidak ada data tanggal ini): ' . implode(', ', $skipped) . '.' : ''
            );

        $this->info($message);
        $this->updateCache($syncId, [
            'status' => $errors === [] ? 'completed' : 'warning',
            'progress' => 100,
            'completed_tables' => $summary['total_steps'],
            'total_tables' => $summary['total_steps'],
            'failed_tables' => count($errors),
            'message' => $message,
            'steps' => $this->stepSummary($plan, $summary['results'], $errors),
            'reports' => $plan['reports'],
            'finished_at' => now()->toIso8601String(),
        ]);

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stepSummary(array $plan, array $results = [], array $errors = []): array
    {
        return array_map(static fn (array $step): array => [
            'key' => $step['key'],
            'label' => $step['label'],
            'available' => $step['available'],
            'rows' => $results[$step['key']] ?? null,
            'error' => $errors[$step['key']] ?? null,
        ], $plan['steps']);
    }

    private function updateCache(?string $syncId, array $changes): void
    {
        if ($syncId === null) {
            return;
        }

        $key = PeriodSnapshotSyncService::STATE_PREFIX . $syncId;
        $state = array_merge(Cache::get($key) ?: [], $changes, ['updated_at' => now()->toIso8601String()]);
        Cache::put($key, $state, now()->addHours(6));
    }
}
