<?php

namespace App\Console\Commands;

use App\Support\ReportCacheVersion;
use App\Support\ReportSnapshotBuilder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ScheduledRebuildPerformanceRmCommand extends Command
{
    protected $signature = 'snapshot:rebuild-rm-scheduled';

    protected $description = 'Rebuild important Performance RM snapshots on schedule';

    public function __construct(private ReportSnapshotBuilder $builder)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $now = Carbon::now();
            $periods = [
                $now->toDateString(),
                $now->copy()->subDay()->toDateString(),
                $now->copy()->subDays(7)->toDateString(),
                $now->copy()->endOfMonth()->toDateString(),
                $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
                $now->copy()->subYear()->toDateString(),
            ];

            $periods = array_unique(array_filter($periods));

            $rebuilt = [];
            foreach ($periods as $period) {
                try {
                    $count = $this->builder->buildPerformanceRmPeriodSnapshot($period, false);
                    if ($count > 0) {
                        $rebuilt[] = ['period' => $period, 'rows' => $count];
                    }
                } catch (Throwable $e) {
                    Log::warning("Failed to rebuild RM snapshot for {$period}", ['error' => $e->getMessage()]);
                }
            }

            if (!empty($rebuilt)) {
                ReportCacheVersion::bump('pinjaman');
            }

            $this->line(json_encode([
                'status' => 'success',
                'rebuilt_periods' => count($rebuilt),
                'details' => $rebuilt,
            ], JSON_PRETTY_PRINT));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Scheduled RM snapshot rebuild failed: ' . $e->getMessage());
            Log::error('Scheduled RM snapshot rebuild failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }
    }

    private function buildPerformanceRmPeriodSnapshot(string $period, bool $force = false): int
    {
        return $this->builder->buildPerformanceRmPeriodSnapshot($period, $force);
    }
}
