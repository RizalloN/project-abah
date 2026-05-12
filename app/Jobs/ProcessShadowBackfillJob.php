<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessShadowBackfillJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;
    public $tries = 5;
    public $backoff = [60, 120, 300, 600, 1200];

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

        Log::info("ProcessShadowBackfillJob: Attempt " . $this->attempts() . "/5", [
            'periods' => $periodString,
            'chunk_size' => $this->chunkSize,
        ]);

        try {
            $exitCode = Artisan::call('shadow:backfill', [
                '--periods' => $periodString,
                '--chunk-size' => $this->chunkSize,
                '--delay' => $this->sleepDelay,
                '--retry-count' => $this->retryCount,
                '--no-interaction' => true,
            ]);

            if ($exitCode === 0) {
                Log::info("ProcessShadowBackfillJob: Backfill completed successfully", [
                    'periods' => $periodString,
                ]);
                return;
            }

            $completion = $this->checkCompletionStatus();
            if ($completion['overall_percentage'] >= 95.0) {
                Log::warning("ProcessShadowBackfillJob: Partial backfill accepted (>95%); job will not be requeued", [
                    'completion' => $completion,
                ]);
                return;
            }

            Log::error("ProcessShadowBackfillJob: Backfill failed with exit code {$exitCode}", [
                'output' => Artisan::output(),
                'completion' => $completion,
            ]);

            if ($this->attempts() >= 5) {
                $this->failed(new \Exception("Backfill command failed after 5 attempts"));
                return;
            }

            $this->release(delay: $this->getBackoffDelay());

        } catch (Throwable $e) {
            Log::error("ProcessShadowBackfillJob: Exception during backfill", [
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            if ($this->attempts() >= 5) {
                $this->failed($e);
                return;
            }

            $this->release(delay: $this->getBackoffDelay());
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::critical("ProcessShadowBackfillJob: FAILED after all retries", [
            'periods' => implode(',', $this->periods),
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        DB::table('shadow_backfill_failures')->insertOrIgnore([
            'periods' => implode(',', $this->periods),
            'error_message' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'failed_at' => now(),
        ]);

        Log::info("ProcessShadowBackfillJob: Recovery task created - requires manual intervention");
    }

    private function checkCompletionStatus(): array
    {
        $stats = [];
        $totalOverall = 0;
        $completedOverall = 0;

        foreach ($this->periods as $period) {
            $total = DB::table('daily_loan_dinamis')
                ->where('periode', $period)
                ->count();

            $null = DB::table('daily_loan_dinamis')
                ->where('periode', $period)
                ->where(function ($q) {
                    $q->whereNull('segmen_kinerja')
                        ->orWhereNull('produk_kinerja')
                        ->orWhereNull('cabang_normalized')
                        ->orWhereNull('unit_normalized')
                        ->orWhereNull('branch_normalized')
                        ->orWhereNull('rm_normalized')
                        ->orWhereNull('pn_pemutus_normalized')
                        ->orWhereNull('cifno_clean');

                    if (\Illuminate\Support\Facades\Schema::hasColumn('daily_loan_dinamis', 'shadow_built_at')
                        && \Illuminate\Support\Facades\Schema::hasColumn('daily_loan_dinamis', 'updated_at')) {
                        $q->orWhereNull('shadow_built_at')
                            ->orWhereColumn('shadow_built_at', '<', 'updated_at');
                    }
                })
                ->count();

            $completed = $total - $null;
            $pct = $total > 0 ? (100.0 * $completed / $total) : 100.0;

            $stats[$period] = [
                'total' => $total,
                'completed' => $completed,
                'percentage' => round($pct, 2),
            ];

            $totalOverall += $total;
            $completedOverall += $completed;
        }

        $overallPct = $totalOverall > 0 ? (100.0 * $completedOverall / $totalOverall) : 100.0;

        return [
            'by_period' => $stats,
            'overall_percentage' => round($overallPct, 2),
        ];
    }

    private function getBackoffDelay(): int
    {
        return $this->backoff[min($this->attempts() - 1, count($this->backoff) - 1)] ?? 1800;
    }
}
