<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BenchmarkDeletePerformanceCommand extends Command
{
    protected $signature = 'benchmark:delete-performance
                            {--report= : Report ID to monitor}
                            {--list : List available reports}
                            {--clear-audits : Clear old audit records before monitoring}';

    protected $description = 'Monitor managed delete via UI and auto-analyze performance';

    public function handle()
    {
        if ($this->option('list')) {
            return $this->listReports();
        }

        if (!$this->option('report')) {
            $this->error('ERROR: --report ID required');
            $this->info('Usage: php artisan benchmark:delete-performance --report=1');
            $this->info('       php artisan benchmark:delete-performance --list');
            return self::FAILURE;
        }

        $reportId = $this->option('report');
        
        if ($this->option('clear-audits')) {
            $cleared = DB::table('report_sync_audits')->delete();
            $this->info("✓ Cleared {$cleared} audit records");
        }

        $this->monitorDelete($reportId);
        return self::SUCCESS;
    }

    protected function listReports()
    {
        $this->info('📋 Available reports for benchmarking:');
        
        $tables = [
            'daily_loan_dinamis' => 'Daily Loan Dinamis',
            'simpanan_multipn' => 'Simpanan Multi-PN',
            'lw325_ph' => 'LW325 PH'
        ];

        foreach ($tables as $table => $label) {
            try {
                $count = DB::table($table)->count();
                $this->line("  1. {$label} ({$table}) - {$count} rows");
            } catch (\Exception $e) {
                $this->line("  ✗ {$label} - Error: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }

    protected function monitorDelete($reportId)
    {
        $this->info('📊 Monitoring managed delete...');
        $this->line('');
        $this->info('⚠️  DELETE MUST BE TRIGGERED VIA WEB UI');
        $this->line('');
        $this->info('Steps:');
        $this->line('  1. Open http://localhost/project-abah');
        $this->line('  2. Navigate: Report Management → Delete');
        $this->line('  3. Select report and configure scope');
        $this->line('  4. Click DELETE button');
        $this->line('  5. Command will auto-analyze when complete');
        $this->line('');
        $this->info('Watching for activity... (Ctrl+C to stop)');
        $this->line('');

        $startTime = time();
        $timeout = 3600; // 60 minutes
        $lastCount = 0;
        $stable = 0;

        while ((time() - $startTime) < $timeout) {
            try {
                $count = DB::table('report_sync_audits')
                    ->where('created_at', '>=', Carbon::now()->subMinutes(5))
                    ->count();

                if ($count > $lastCount) {
                    $lastCount = $count;
                    $stable = 0;
                    $this->line("✓ Activity detected ({$count} audit records)");
                } else {
                    $stable++;
                }

                // When stable for 10 seconds after activity
                if ($count > 0 && $stable > 20) {
                    $this->line('');
                    $this->info('✓ Delete completed. Analyzing...');
                    $this->line('');
                    $this->analyzeAudits();
                    return;
                }

            } catch (\Exception $e) {
                $this->error('ERROR: ' . $e->getMessage());
            }

            sleep(0.5);
        }

        $this->warn('⚠ Timeout: No delete activity detected within 60 minutes');
        if ($lastCount > 0) {
            $this->line("Found {$lastCount} audit records. Analyzing...");
            $this->analyzeAudits();
        }
    }

    protected function analyzeAudits()
    {
        try {
            $audits = DB::table('report_sync_audits')
                ->where('created_at', '>=', Carbon::now()->subMinutes(10))
                ->orderBy('created_at')
                ->get();

            if ($audits->isEmpty()) {
                $this->warn('No audit records found');
                return;
            }

            $totalTime = 0;
            $phases = [
                'precheck' => ['managed_delete_shortcut_prepare'],
                'delete_chunks' => ['managed_delete_chunk'],
                'cleanup' => ['cleanup_snapshot_rows'],
                'snapshot' => ['snapshot_sync', 'snapshot_parallel_dispatch', 'snapshot_rebuild_after_delete'],
                'cache' => ['cache_invalidate', 'cache_invalidate_lightweight'],
                'analysis' => ['analyze_table']
            ];

            $results = [];
            foreach ($phases as $name => $actions) {
                $duration = 0;
                $count = 0;
                foreach ($audits as $audit) {
                    if (in_array($audit->action, $actions)) {
                        $duration += $audit->duration_ms;
                        $count++;
                        $totalTime += $audit->duration_ms;
                    }
                }
                if ($duration > 0) {
                    $results[$name] = ['duration' => $duration, 'count' => $count];
                }
            }

            $this->displayAnalysis($results, $totalTime);

        } catch (\Exception $e) {
            $this->error('ERROR analyzing audits: ' . $e->getMessage());
        }
    }

    protected function displayAnalysis($results, $totalTime)
    {
        $this->info('=== DELETE PERFORMANCE ANALYSIS ===');
        $this->line('');

        if ($totalTime == 0) {
            $this->warn('No timing data available');
            return;
        }

        $labels = [
            'precheck' => 'Precheck (Full-table guard)',
            'delete_chunks' => 'Delete Chunks (Batch execution)',
            'cleanup' => 'Cleanup (Snapshot truncation)',
            'snapshot' => 'Snapshot Sync (Rebuild phase)',
            'cache' => 'Cache (Invalidation)',
            'analysis' => 'Analysis (Table ANALYZE)'
        ];

        foreach ($labels as $key => $label) {
            if (isset($results[$key])) {
                $data = $results[$key];
                $pct = ($data['duration'] / $totalTime) * 100;
                $this->line(sprintf(
                    "%s (%.1f%%)\n  Time: %dms (iterations: %d)\n",
                    $label,
                    $pct,
                    $data['duration'],
                    $data['count']
                ));
            }
        }

        $this->line('─────────────────────');
        $this->line(sprintf('Total Time: %dms', $totalTime));
    }
}
