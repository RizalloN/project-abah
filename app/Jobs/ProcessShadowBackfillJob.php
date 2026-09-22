<?php

namespace App\Jobs;

use App\Jobs\Middleware\DeferSnapshotJobsDuringImport;
use App\Support\DailyLoanManualSegmentRule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessShadowBackfillJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;
    public $tries = 40;
    public $maxExceptions = 5;
    public $backoff = [60, 120, 300, 600, 1200];

    public function __construct(
        public array $periods,
        public int $chunkSize = 10000,
        public int $sleepDelay = 0,
        public int $retryCount = 3,
        ?string $queueName = null,
        public bool $skipSnapshot = false
    ) {
        $this->onQueue($queueName ?: (string) config('queue.shadow_backfill_queue', 'shadow-backfill'));
    }

    public function uniqueId(): string
    {
        $periods = array_values(array_unique(array_filter(array_map(
            static fn ($period): string => trim((string) $period),
            $this->periods
        ))));
        sort($periods);

        return 'shadow-backfill:' . md5(implode(',', $periods));
    }

    public function middleware(): array
    {
        $periods = array_values(array_unique(array_filter(array_map(
            static fn ($period): string => trim((string) $period),
            $this->periods
        ))));
        sort($periods);

        $middleware = [
            new DeferSnapshotJobsDuringImport(sourceTable: 'daily_loan_dinamis'),
        ];

        foreach ($periods as $period) {
            $middleware[] = (new WithoutOverlapping('shadow:backfill:auto:' . $period))
                ->withPrefix('')
                ->shared()
                ->releaseAfter(60)
                ->expireAfter(1800);
        }

        return $middleware;
    }

    public function handle(): void
    {
        $periodString = implode(',', $this->periods);

        Log::info("ProcessShadowBackfillJob: Attempt " . $this->attempts(), [
            'periods' => $periodString,
            'chunk_size' => $this->chunkSize,
        ]);

        try {
            $exitCode = Artisan::call('shadow:backfill', [
                '--periods' => $periodString,
                '--chunk-size' => $this->chunkSize,
                '--delay' => $this->sleepDelay,
                '--retry-count' => $this->retryCount,
                '--skip-snapshot' => $this->skipSnapshot,
                '--no-interaction' => true,
            ]);

            if ($exitCode === 0) {
                Log::info("ProcessShadowBackfillJob: Backfill completed successfully", [
                    'periods' => $periodString,
                ]);
                return;
            }

            $completion = $this->checkCompletionStatus();
            if ($completion['overall_percentage'] >= 100.0) {
                if (!$this->skipSnapshot) {
                    $this->rebuildPerformanceRmSnapshots();
                }

                Log::warning("ProcessShadowBackfillJob: Command failed after all required shadow rows were already completed.", [
                    'completion' => $completion,
                ]);
                return;
            }

            Log::error("ProcessShadowBackfillJob: Backfill failed with exit code {$exitCode}", [
                'output' => Artisan::output(),
                'completion' => $completion,
            ]);

            throw new \RuntimeException('Backfill command returned exit code ' . $exitCode . '.');

        } catch (Throwable $e) {
            Log::error("ProcessShadowBackfillJob: Exception during backfill", [
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $e;
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
            $hasPendingRows = DB::table('daily_loan_dinamis')
                ->where('periode', $period)
                ->where(function ($q) {
                    DailyLoanManualSegmentRule::applyPendingShadowPredicate($q, [
                        'segmen_kinerja',
                        'produk_kinerja',
                        'cabang_normalized',
                        'unit_normalized',
                        'branch_normalized',
                        'rm_normalized',
                        'cifno_clean',
                    ]);
                })
                ->exists();

            $total = 1;
            $completed = $hasPendingRows ? 0 : 1;
            $pct = $hasPendingRows ? 0.0 : 100.0;

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

    private function rebuildPerformanceRmSnapshots(): void
    {
        foreach ($this->periods as $period) {
            $normalizedPeriod = trim((string) $period);
            if ($normalizedPeriod === '') {
                continue;
            }

            Artisan::call('snapshot:rebuild-rm', [
                '--period' => $normalizedPeriod,
                '--no-interaction' => true,
            ]);
        }
    }
}
