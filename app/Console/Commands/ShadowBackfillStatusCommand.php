<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ShadowBackfillStatusCommand extends Command
{
    protected $signature = 'shadow:backfill-status
        {--failures : Show recent failures}
        {--metrics : Show performance metrics}
        {--checkpoints : Show all checkpoints}
    ';

    protected $description = 'Display status of shadow backfill operations';

    public function handle(): int
    {
        $this->displayHeader();

        if ($this->option('failures')) {
            $this->displayFailures();
        } elseif ($this->option('metrics')) {
            $this->displayMetrics();
        } elseif ($this->option('checkpoints')) {
            $this->displayCheckpoints();
        } else {
            $this->displayOverview();
        }

        return self::SUCCESS;
    }

    private function displayHeader(): void
    {
        $this->info('╔════════════════════════════════════════════════════════════════╗');
        $this->info('║          Shadow Backfill - Status & Monitoring                 ║');
        $this->info('╚════════════════════════════════════════════════════════════════╝');
        $this->newLine();
    }

    private function displayOverview(): void
    {
        $this->info('📊 OVERVIEW');
        $this->newLine();

        // Recent completions
        $recent = DB::table('shadow_backfill_checkpoints')
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        if ($recent->isEmpty()) {
            $this->line('   No backfill operations recorded yet.');
            return;
        }

        $tableRows = [];
        foreach ($recent as $checkpoint) {
            $status = $checkpoint->completion_percentage >= 99.5
                ? '<fg=green>✓ COMPLETE</>'
                : '<fg=yellow>⚠ IN PROGRESS</>';

            $tableRows[] = [
                $checkpoint->period,
                number_format($checkpoint->rows_processed),
                $checkpoint->completion_percentage . '%',
                $status,
                $this->formatTimestamp($checkpoint->updated_at),
            ];
        }

        $this->table(['Period', 'Rows Processed', 'Completion', 'Status', 'Last Updated'], $tableRows);

        $this->newLine();
        $this->info('💡 TIP: Run with --failures, --metrics, or --checkpoints for details');
    }

    private function displayFailures(): void
    {
        $this->info('❌ RECENT FAILURES');
        $this->newLine();

        $failures = DB::table('shadow_backfill_failures')
            ->where('status', 'pending')
            ->orderBy('failed_at', 'desc')
            ->limit(10)
            ->get();

        if ($failures->isEmpty()) {
            $this->line('   ✓ No pending failures');
            $this->newLine();
            return;
        }

        $tableRows = [];
        foreach ($failures as $failure) {
            $tableRows[] = [
                $failure->periods,
                $failure->attempts,
                $this->formatTimestamp($failure->failed_at),
                substr($failure->error_message, 0, 50) . '...',
            ];
        }

        $this->table(['Periods', 'Attempts', 'Failed At', 'Error'], $tableRows);
        $this->newLine();

        $this->info('📝 To resolve:');
        $this->line('   php artisan shadow:backfill --periods=<period> --retry-count=10');
    }

    private function displayMetrics(): void
    {
        $this->info('📈 PERFORMANCE METRICS');
        $this->newLine();

        $metrics = DB::table('shadow_backfill_metrics')
            ->orderBy('executed_at', 'desc')
            ->limit(20)
            ->get();

        if ($metrics->isEmpty()) {
            $this->line('   No metrics recorded yet.');
            return;
        }

        // Group by period
        $byPeriod = $metrics->groupBy('period');

        foreach ($byPeriod as $period => $periodMetrics) {
            $this->line("📅 <fg=cyan>{$period}</>");

            $avgRowsPerSec = $periodMetrics->avg('rows_per_second');
            $avgDuration = $periodMetrics->avg('duration_seconds');
            $successCount = $periodMetrics->where('success', true)->count();
            $totalCount = $periodMetrics->count();

            $this->line("   Chunks: {$totalCount} (Success: {$successCount})");
            $this->line("   Avg Speed: " . number_format($avgRowsPerSec, 0) . " rows/sec");
            $this->line("   Avg Duration: " . round($avgDuration, 2) . " seconds");

            $tableRows = [];
            foreach ($periodMetrics->take(5) as $metric) {
                $statusIcon = $metric->success ? '✓' : '✗';
                $tableRows[] = [
                    $statusIcon,
                    "Chunk {$metric->chunk_number}",
                    number_format($metric->chunk_size),
                    number_format($metric->rows_per_second),
                    round($metric->duration_seconds, 2) . 's',
                ];
            }

            $this->table(['', 'Chunk', 'Size', 'Rows/Sec', 'Duration'], $tableRows);
            $this->newLine();
        }
    }

    private function displayCheckpoints(): void
    {
        $this->info('🔖 CHECKPOINTS');
        $this->newLine();

        $checkpoints = DB::table('shadow_backfill_checkpoints')
            ->orderBy('period')
            ->get();

        if ($checkpoints->isEmpty()) {
            $this->line('   No checkpoints recorded.');
            return;
        }

        $tableRows = [];
        foreach ($checkpoints as $cp) {
            $completionBar = $this->getProgressBar($cp->completion_percentage);
            $tableRows[] = [
                $cp->period,
                number_format($cp->rows_processed),
                number_format($cp->chunks_completed),
                $cp->completion_percentage . '%',
                $completionBar,
                $this->formatTimestamp($cp->updated_at, 'Y-m-d H:i:s'),
            ];
        }

        $this->table(
            ['Period', 'Rows Processed', 'Chunks', 'Completion %', 'Progress', 'Last Updated'],
            $tableRows
        );

        $this->newLine();
        $this->info('💡 Resume from checkpoint:');
        $this->line('   php artisan shadow:backfill --resume');
    }

    private function getProgressBar(float $percentage): string
    {
        $filled = (int) ($percentage / 5);
        $empty = 20 - $filled;
        return '[' . str_repeat('█', $filled) . str_repeat('░', $empty) . ']';
    }

    private function formatTimestamp(mixed $value, string $format = 'Y-m-d H:i'): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        try {
            return Carbon::parse($value)->format($format);
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
