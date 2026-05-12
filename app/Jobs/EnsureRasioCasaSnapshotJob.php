<?php

namespace App\Jobs;

use App\Support\ReportDataSyncService;
use App\Support\ReportCacheVersion;
use App\Support\SnapshotBatchAggregator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnsureRasioCasaSnapshotJob implements ShouldQueue
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

            Log::debug('Registered period for batched rasio CASA snapshot sync.', [
                'period' => $this->period,
                'source' => $this->source,
            ]);
        } catch (Throwable $e) {
            Log::warning('Ensure rasio CASA snapshot job gagal: ' . $e->getMessage(), [
                'period' => $this->period,
                'source' => $this->source,
            ]);

            throw $e;
        } finally {
            Cache::forget('snapshot:rasio:auto-rebuild:pending:' . $this->period);
            Cache::forget('rasio_casa:snapshot_exists:v' . ReportCacheVersion::composite(['pinjaman', 'simpanan']) . ':' . $this->period);
        }
    }
}
