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

class EnsureRekeningDormantSnapshotJob implements ShouldQueue
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
                tableName: 'simpanan_multipn',
                periodHint: $this->period,
                jobId: null,
                source: $this->source ?? static::class
            );

            Log::debug('Registered period for batched rekening dormant snapshot sync.', [
                'period' => $this->period,
                'source' => $this->source,
            ]);
        } catch (Throwable $e) {
            Log::warning('Ensure rekening dormant snapshot job gagal: ' . $e->getMessage(), [
                'period' => $this->period,
                'source' => $this->source,
            ]);

            throw $e;
        } finally {
            Cache::forget('snapshot:dormant:auto-rebuild:pending:' . $this->period);
            Cache::forget('rekening_dormant:snapshot_exists:v' . (int) Cache::get('report_cache_version:global', 1) . ':' . $this->period);
        }
    }
}
