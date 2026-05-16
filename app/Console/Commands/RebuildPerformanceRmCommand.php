<?php

namespace App\Console\Commands;

use App\Support\ReportCacheVersion;
use App\Support\ReportSnapshotBuilder;
use App\Support\SnapshotSourceSignatureService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class RebuildPerformanceRmCommand extends Command
{
    protected $signature = 'snapshot:rebuild-rm
        {--period= : Rebuild specific period (e.g., 2026-04-20)}
        {--force : Force rebuild all periods}';

    protected $description = 'Rebuild Performance RM snapshots with optimized aggregation';

    public function __construct(
        private ReportSnapshotBuilder $builder,
        private SnapshotSourceSignatureService $sourceSignatures
    )
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $period = trim((string) $this->option('period'));
            $force = (bool) $this->option('force');

            $this->info('Starting Performance RM snapshot rebuild...');
            $startTime = now();

            $result = $this->builder->rebuildPerformanceRm($period ?: null, $force, function ($progress) {
                $this->line(sprintf(
                    'Period: %s | Built: %d / %d rows',
                    $progress['current_period'],
                    $progress['current_result_count'],
                    $progress['completed_units']
                ));
            });

            $this->line(json_encode([
                'status' => 'success',
                'periods' => array_keys($result),
                'total_periods' => count($result),
                'total_rows' => array_sum($result),
                'duration_seconds' => now()->diffInSeconds($startTime),
            ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            ReportCacheVersion::bump('pinjaman');
            $this->info('Cache version bumped.');
            $this->markSnapshotSignatures($result, $period ?: null);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Performance RM snapshot rebuild failed: ' . $e->getMessage());
            Log::error('Performance RM snapshot rebuild failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * @param array<string, int> $result
     */
    private function markSnapshotSignatures(array $result, ?string $periodHint): void
    {
        $periods = $periodHint !== null && trim($periodHint) !== ''
            ? [trim($periodHint)]
            : array_keys(array_filter($result, static fn ($rows): bool => (int) $rows > 0));

        foreach ($periods as $period) {
            $this->sourceSignatures->markBuiltForApplicableSources(
                'performance_rm_snapshots',
                (string) $period,
                [
                    ['source_table' => 'daily_loan_dinamis', 'period_column' => 'periode'],
                    ['source_table' => 'simpanan_multipn', 'period_column' => 'posisi'],
                ],
                ['command' => static::class]
            );
        }
    }
}
