<?php

namespace App\Jobs;

use App\Support\SnapshotBatchAggregator;
use App\Support\SimpananMultiPnSnapshotGate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnsureDashboardSimpananSnapshotJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 0;

    public function __construct(
        public string $period,
        public ?string $source = null
    ) {
    }

    public function uniqueId(): string
    {
        return trim($this->period);
    }

    public function handle(SnapshotBatchAggregator $batchAggregator): void
    {
        try {
            if (!$this->isSnapshotReady()) {
                Log::info('Ensure dashboard simpanan snapshot job ditunda karena Area 6 belum lengkap.', [
                    'period' => $this->period,
                    'source' => $this->source,
                    'missing_branches' => app(SimpananMultiPnSnapshotGate::class)->getMissingBranches($this->period),
                ]);

                return;
            }

            $batchAggregator->registerSyncRequest(
                tableName: 'simpanan_multipn',
                periodHint: $this->period,
                jobId: null,
                source: $this->source ?? static::class
            );

            Log::debug('Registered period for batched dashboard simpanan snapshot sync.', [
                'period' => $this->period,
                'source' => $this->source,
            ]);
        } catch (Throwable $e) {
            Log::warning('Ensure dashboard simpanan snapshot job gagal: ' . $e->getMessage(), [
                'period' => $this->period,
                'source' => $this->source,
            ]);

            throw $e;
        } finally {
            Cache::forget('snapshot:dashboard_simpanan:auto-rebuild:pending:' . $this->period);
            Cache::forget('dashboard_simpanan:snapshot_exists:v' . (int) Cache::get('report_cache_version:global', 1) . ':' . $this->period);
        }
    }

    private function isSnapshotReady(): bool
    {
        return app(SimpananMultiPnSnapshotGate::class)->isReady($this->period);
    }
}
