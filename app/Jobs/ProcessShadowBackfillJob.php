<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessShadowBackfillJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Waktu maksimal job boleh berjalan (dalam detik).
     * 0 = no timeout (mengikuti konfigurasi queue worker)
     */
    public $timeout = 0;

    /**
     * Jumlah maksimal percobaan ulang jika gagal.
     */
    public $tries = 1;

    public function __construct(
        public array $periods,
        public int $chunkSize = 50000,
        public int $sleepDelay = 0,
        public int $retryCount = 3,
        ?string $queueName = null
    ) {
        $this->onQueue($queueName ?: (string) config('queue.shadow_backfill_queue', 'shadow-backfill'));
    }

    public function handle(): void
    {
        $periodString = implode(',', $this->periods);
        
        Log::info("ProcessShadowBackfillJob: Memulai backfill shadow columns di background", [
            'periods' => $periodString,
            'chunk_size' => $this->chunkSize
        ]);

        try {
            $exitCode = Artisan::call('shadow:backfill', [
                '--periods' => $periodString,
                '--chunk-size' => $this->chunkSize,
                '--delay' => $this->sleepDelay,
                '--retry-count' => $this->retryCount,
                '--no-interaction' => true // Supaya tidak ada prompt di background
            ]);

            if ($exitCode === 0) {
                Log::info("ProcessShadowBackfillJob: Backfill berhasil untuk periode: {$periodString}");
            } else {
                Log::error("ProcessShadowBackfillJob: Backfill berjalan namun mengembalikan exit code {$exitCode}", [
                    'output' => Artisan::output()
                ]);
                $this->fail(new \Exception("Backfill command failed with exit code {$exitCode}"));
            }
        } catch (Throwable $e) {
            Log::error("ProcessShadowBackfillJob: Terjadi kesalahan saat eksekusi backfill", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
