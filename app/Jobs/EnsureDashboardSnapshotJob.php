<?php

namespace App\Jobs;

use App\Support\ReportDataSyncService;
use App\Support\SnapshotBatchAggregator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnsureDashboardSnapshotJob implements ShouldQueue
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

    public function handle(SnapshotBatchAggregator $batchAggregator): void
    {
        try {
            $batchAggregator->registerSyncRequest(
                tableName: 'daily_loan_dinamis',
                periodHint: $this->period,
                jobId: null,
                source: $this->source ?? static::class
            );

            Log::debug('Registered period for batched dashboard snapshot sync.', [
                'period' => $this->period,
                'source' => $this->source,
            ]);
        } catch (Throwable $e) {
            Log::warning('Ensure dashboard snapshot job gagal: ' . $e->getMessage(), [
                'period' => $this->period,
                'source' => $this->source,
            ]);

            throw $e;
        } finally {
            Cache::forget('snapshot:dashboard:auto-rebuild:pending:' . $this->period);
            Cache::forget('dashboard_pinjaman_snapshot_exists:v' . (int) Cache::get('report_cache_version:global', 1) . ':' . $this->period);
        }
    }
}
