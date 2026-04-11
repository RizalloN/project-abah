<?php

namespace App\Jobs;

use App\Support\ReportDataSyncService;
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

    public function handle(ReportDataSyncService $syncService): void
    {
        try {
            $syncService->syncImportedTable('daily_loan_dinamis', $this->period, source: $this->source ?? static::class);
        } catch (Throwable $e) {
            Log::warning('Ensure rasio CASA snapshot job gagal: ' . $e->getMessage(), [
                'period' => $this->period,
                'source' => $this->source,
            ]);

            throw $e;
        } finally {
            Cache::forget('snapshot:rasio:auto-rebuild:pending:' . $this->period);
            Cache::forget('rasio_casa:snapshot_exists:v' . (int) Cache::get('report_cache_version:global', 1) . ':' . $this->period);
        }
    }
}
