<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyzeDeleteAuditsCommand extends Command
{
    protected $signature = 'benchmark:analyze-audits
                            {--hours=24 : Hours to analyze (default 24)}
                            {--table= : Filter by table name}
                            {--action= : Filter by action}
                            {--stats : Show statistics summary}';

    protected $description = 'Analyze delete audit records to identify bottlenecks';

    public function handle()
    {
        $hours = (int)$this->option('hours');
        
        $query = DB::table('report_sync_audits')
            ->where('created_at', '>=', Carbon::now()->subHours($hours));

        if ($this->option('table')) {
            $query->where('table_name', $this->option('table'));
        }

        if ($this->option('action')) {
            $query->where('action', $this->option('action'));
        }

        $audits = $query->orderBy('created_at')->get();

        if ($audits->isEmpty()) {
            $this->warn("No audit records found in last {$hours} hours");
            return self::SUCCESS;
        }

        if ($this->option('stats')) {
            $this->displayStatistics($audits);
        } else {
            $this->displayAnalysis($audits);
        }

        return self::SUCCESS;
    }

    protected function displayAnalysis($audits)
    {
        $this->info('=== DELETE AUDIT ANALYSIS ===');
        $this->line('');

        // Group by action
        $actions = [
            'managed_delete_shortcut_prepare' => 'Precheck',
            'managed_delete_chunk' => 'Delete Chunk',
            'managed_delete_shortcut' => 'TRUNCATE',
            'cleanup_snapshot_rows' => 'Cleanup Snapshots',
            'snapshot_sync' => 'Snapshot Sync',
            'snapshot_parallel_dispatch' => 'Snapshot Parallel',
            'snapshot_rebuild_after_delete' => 'Snapshot Rebuild',
            'cache_invalidate' => 'Cache Invalidate',
            'cache_invalidate_lightweight' => 'Cache Lightweight',
            'analyze_table' => 'Table Analysis'
        ];

        foreach ($actions as $actionName => $label) {
            $records = $audits->where('action', $actionName);
            
            if ($records->count() > 0) {
                $totalMs = $records->sum('duration_ms');
                $avgMs = $records->avg('duration_ms');
                $maxMs = $records->max('duration_ms');
                $count = $records->count();
                $successCount = $records->where('status', 'success')->count();
                $failedCount = $count - $successCount;

                $this->line(sprintf(
                    "%s: %d records | Success: %d | Failed: %d | Total: %dms | Avg: %.0fms | Max: %dms",
                    $label,
                    $count,
                    $successCount,
                    $failedCount,
                    $totalMs,
                    $avgMs,
                    $maxMs
                ));
            }
        }

        $this->line('');
        $this->identifyBottleneck($audits);
    }

    protected function displayStatistics($audits)
    {
        $this->info('=== PERFORMANCE STATISTICS ===');
        $this->line('');

        $grouped = $audits->groupBy('action');
        
        $headers = ['Action', 'Count', 'Success', 'Failed', 'Avg (ms)', 'Max (ms)', 'Total (ms)'];
        $rows = [];

        foreach ($grouped as $action => $records) {
            $count = $records->count();
            $success = $records->where('status', 'success')->count();
            $failed = $count - $success;
            $avg = $records->avg('duration_ms');
            $max = $records->max('duration_ms');
            $total = $records->sum('duration_ms');

            $rows[] = [
                substr($action, 0, 25),
                $count,
                $success,
                $failed,
                number_format($avg, 2),
                $max,
                number_format($total, 0)
            ];
        }

        $this->table($headers, $rows);

        $this->line('');
        $this->identifyBottleneck($audits);
    }

    protected function identifyBottleneck($audits)
    {
        $this->info('=== BOTTLENECK ANALYSIS ===');
        $this->line('');

        // Phase definitions with correct audit actions
        $phases = [
            'precheck' => [
                'actions' => ['managed_delete_shortcut_prepare'],
                'label' => 'Precheck (Full-table guard)',
                'recommendation' => 'Use capped count instead of full COUNT(*)'
            ],
            'delete' => [
                'actions' => ['managed_delete_chunk', 'managed_delete_shortcut'],
                'label' => 'Delete Chunks (Batch execution)',
                'recommendation' => 'Optimize lock efficiency / improve batch size'
            ],
            'cleanup' => [
                'actions' => ['cleanup_snapshot_rows'],
                'label' => 'Cleanup (Snapshot truncation)',
                'recommendation' => 'Skip redundant verification step'
            ],
            'snapshot' => [
                'actions' => ['snapshot_sync', 'snapshot_parallel_dispatch', 'snapshot_rebuild_after_delete'],
                'label' => 'Snapshot Sync (Rebuild phase)',
                'recommendation' => 'Decouple snapshot from delete completion'
            ],
            'cache' => [
                'actions' => ['cache_invalidate', 'cache_invalidate_lightweight'],
                'label' => 'Cache (Invalidation)',
                'recommendation' => 'Batch invalidation operations'
            ],
            'analysis' => [
                'actions' => ['analyze_table'],
                'label' => 'Analysis (Table ANALYZE)',
                'recommendation' => 'Run async after UI response'
            ]
        ];

        $totalTime = 0;
        $phaseData = [];

        // Calculate totals
        foreach ($phases as $key => $phase) {
            $duration = 0;
            foreach ($audits as $audit) {
                if (in_array($audit->action, $phase['actions'])) {
                    $duration += $audit->duration_ms;
                }
            }
            if ($duration > 0) {
                $phaseData[$key] = [
                    'duration' => $duration,
                    'label' => $phase['label'],
                    'recommendation' => $phase['recommendation']
                ];
                $totalTime += $duration;
            }
        }

        if ($totalTime == 0) {
            $this->warn('No timing data available');
            return;
        }

        // Sort by duration (descending)
        usort($phaseData, function($a, $b) {
            return $b['duration'] <=> $a['duration'];
        });

        // Display sorted by impact
        $priority = 1;
        foreach ($phaseData as $data) {
            $pct = ($data['duration'] / $totalTime) * 100;
            
            $badge = match(true) {
                $pct >= 20 => 'RED',
                $pct >= 10 => 'YELLOW',
                default => 'BLUE'
            };

            $this->line(sprintf(
                "%s %.1f%% (%dms) - %s",
                $badge,
                $pct,
                $data['duration'],
                $data['label']
            ));
            $this->line("  -> " . $data['recommendation']);
            $this->line('');
            $priority++;
        }

        $this->info('Summary: Target the RED items first for maximum ROI');
    }
}
