<?php

namespace App\Console\Commands;

use App\Services\Shadow\ShadowColumnRuleEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ShadowStatusCommand extends Command
{
    protected $signature = 'shadow:status
        {--table= : Show status for a specific table only}
        {--rules : Show detailed rule information}
        {--failures : Show backfill failures}
        {--metrics : Show performance metrics}';

    protected $description = 'Display shadow column backfill status and metrics';

    private ShadowColumnRuleEngine $ruleEngine;

    public function handle(): int
    {
        $this->ruleEngine = app(ShadowColumnRuleEngine::class);

        $tableName = $this->option('table');
        $showRules = $this->option('rules');
        $showFailures = $this->option('failures');
        $showMetrics = $this->option('metrics');

        $this->displayHeader();

        if ($showRules) {
            $this->displayRuleStatus($tableName);
        } elseif ($showFailures) {
            $this->displayFailures($tableName);
        } elseif ($showMetrics) {
            $this->displayMetrics($tableName);
        } else {
            $this->displaySummary($tableName);
        }

        return self::SUCCESS;
    }

    /**
     * Display header
     */
    private function displayHeader(): void
    {
        $this->line('');
        $this->info('╔════════════════════════════════════════════════════════════════╗');
        $this->info('║       SHADOW COLUMNS BACKFILL STATUS                           ║');
        $this->info('╚════════════════════════════════════════════════════════════════╝');
        $this->line('');
    }

    /**
     * Display summary status
     */
    private function displaySummary(?string $tableName = null): void
    {
        $tables = $tableName ? [$tableName] : [
            'daily_loan_dinamis',
            'simpanan_multipn',
            'brihc',
            'casa_brilink_web',
        ];

        $this->info('Table Status Summary:');
        $this->line('');

        foreach ($tables as $table) {
            // Check if table exists
            if (!DB::getSchemaBuilder()->hasTable($table)) {
                $this->line("  ✗ {$table} - Table does not exist");
                continue;
            }

            // Get rules for this table
            $rules = $this->ruleEngine->getRulesForTable($table);
            if (empty($rules)) {
                $this->line("  - {$table} - No applicable shadow column rules");
                continue;
            }

            // Check completion percentage for each shadow column
            $shadowCols = $this->ruleEngine->getShadowColumnsForTable($table);
            $totalCols = count($shadowCols);

            if ($totalCols === 0) {
                $this->line("  - {$table} - No shadow columns configured");
                continue;
            }

            $completionPercentages = [];
            foreach ($shadowCols as $shadowCol => $info) {
                $rule = $this->ruleEngine->getRule($info['rule']);
                $tableConfig = $rule['apply_to_tables'][$table];
                $sourceCol = $tableConfig['source_column'];

                // Count total rows with source data
                $totalRows = DB::table($table)
                    ->whereNotNull($sourceCol)
                    ->count();

                if ($totalRows === 0) {
                    $completionPercentages[$shadowCol] = 100;
                    continue;
                }

                // Count rows where shadow column is filled
                $filledRows = DB::table($table)
                    ->whereNotNull($sourceCol)
                    ->whereNotNull($shadowCol)
                    ->count();

                $percentage = round(($filledRows / $totalRows) * 100);
                $completionPercentages[$shadowCol] = $percentage;
            }

            $avgCompletion = round(array_sum($completionPercentages) / count($completionPercentages));
            $status = $avgCompletion === 100 ? '✓' : '⧐';

            $this->line("  {$status} {$table} - {$avgCompletion}% complete");
        }

        $this->line('');
        $this->info('Use --rules to see detailed rule status');
        $this->info('Use --metrics to see performance metrics');
        $this->line('');
    }

    /**
     * Display detailed rule status
     */
    private function displayRuleStatus(?string $tableName = null): void
    {
        $this->info('Rule Status and Validation:');
        $this->line('');

        $rules = $this->ruleEngine->getAllRules();

        foreach ($rules as $ruleName => $ruleConfig) {
            $applicableTables = array_keys($ruleConfig['apply_to_tables']);

            if ($tableName && !in_array($tableName, $applicableTables)) {
                continue;
            }

            $this->line("Rule: {$ruleName}");
            $this->line("  Transformation: {$ruleConfig['transformation']}");
            $this->line("  Tables:");

            foreach ($applicableTables as $table) {
                if ($tableName && $table !== $tableName) {
                    continue;
                }

                $tableConfig = $ruleConfig['apply_to_tables'][$table];
                $sourceCol = $tableConfig['source_column'];
                $shadowCol = $tableConfig['shadow_column'];
                $priority = $tableConfig['priority'] ?? 'MEDIUM';

                // Validate rule for this table
                $validation = $this->ruleEngine->validateRuleConsistency($ruleName);
                $status = $validation['valid'] ? '✓' : '✗';

                $this->line("    {$status} {$table}");
                $this->line("       {$sourceCol} → {$shadowCol} [{$priority}]");

                if (!$validation['valid']) {
                    foreach ($validation['errors'] as $error) {
                        $this->line("       Error: {$error}");
                    }
                }
            }

            $this->line('');
        }
    }

    /**
     * Display backfill failures (if tracked)
     */
    private function displayFailures(?string $tableName = null): void
    {
        $this->info('Recent Backfill Failures:');
        $this->line('');

        // Check if shadow_backfill_failures table exists
        if (!DB::getSchemaBuilder()->hasTable('shadow_backfill_failures')) {
            $this->info('No failure tracking table found');
            return;
        }

        $query = DB::table('shadow_backfill_failures');

        if ($tableName) {
            $query->where('table_name', $tableName);
        }

        $failures = $query
            ->orderByDesc('failed_at')
            ->limit(10)
            ->get();

        if ($failures->isEmpty()) {
            $this->info('No recent failures');
        } else {
            foreach ($failures as $failure) {
                $this->line("Table: {$failure->table_name} | Rule: {$failure->rule_name}");
                $this->line("  Failed: {$failure->failed_at}");
                $this->line("  Error: {$failure->error_message}");
                $this->line('');
            }
        }
    }

    /**
     * Display performance metrics
     */
    private function displayMetrics(?string $tableName = null): void
    {
        $this->info('Performance Metrics:');
        $this->line('');

        // Check if shadow_backfill_metrics table exists
        if (!DB::getSchemaBuilder()->hasTable('shadow_backfill_metrics')) {
            $this->info('No metrics tracking table found');
            return;
        }

        $query = DB::table('shadow_backfill_metrics');

        if ($tableName) {
            $query->where('table_name', $tableName);
        }

        $metrics = $query
            ->orderByDesc('completed_at')
            ->limit(5)
            ->get();

        if ($metrics->isEmpty()) {
            $this->info('No metrics recorded');
        } else {
            foreach ($metrics as $metric) {
                $this->line("Table: {$metric->table_name} | Rule: {$metric->rule_name}");
                $this->line("  Rows Updated: {$metric->rows_updated}");
                $this->line("  Duration: {$metric->duration_seconds}s");
                $this->line("  Rate: " . round($metric->rows_updated / max(1, $metric->duration_seconds)) . " rows/sec");
                $this->line("  Completed: {$metric->completed_at}");
                $this->line('');
            }
        }
    }
}
