<?php

namespace App\Console\Commands;

use App\Jobs\DistributedShadowBackfillJob;
use App\Services\Shadow\ShadowColumnRuleEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShadowBackfillTableCommand extends Command
{
    protected $signature = 'shadow:backfill-table
        {table : The table to backfill shadow columns for}
        {--rules= : Comma-separated rule names to apply (optional, defaults to all applicable rules)}
        {--chunk=1000 : Number of rows to process per batch}
        {--dry-run : Simulate the backfill without making changes}
        {--async : Queue the job instead of running synchronously}
        {--force : Skip confirmation prompts}';

    protected $description = 'Backfill shadow columns for a specific table using rule engine';

    private ShadowColumnRuleEngine $ruleEngine;

    public function handle(): int
    {
        $this->ruleEngine = app(ShadowColumnRuleEngine::class);

        $tableName = $this->argument('table');
        $dryRun = $this->option('dry-run');
        $async = $this->option('async');
        $force = $this->option('force');

        // Validate table exists
        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            $this->error("Table '{$tableName}' does not exist");
            return self::FAILURE;
        }

        // Get applicable rules
        $rulesArg = $this->option('rules');
        if ($rulesArg) {
            $rules = array_map('trim', explode(',', $rulesArg));
        } else {
            // Get all rules applicable to this table
            $applicableRules = $this->ruleEngine->getRulesForTable($tableName);
            $rules = array_keys($applicableRules);
        }

        if (empty($rules)) {
            $this->info("No applicable rules found for table '{$tableName}'");
            return self::SUCCESS;
        }

        // Display what will be done
        $this->info("Shadow Backfill for Table: {$tableName}");
        $this->line('');
        $this->info("Rules to apply:");
        foreach ($rules as $rule) {
            $this->line("  - {$rule}");
        }
        $this->line('');

        // Show shadow columns that will be updated
        $shadowCols = $this->ruleEngine->getShadowColumnsForTable($tableName);
        $affectedCols = [];
        foreach ($rules as $rule) {
            $rule_obj = $this->ruleEngine->getRule($rule);
            if ($rule_obj && isset($rule_obj['apply_to_tables'][$tableName])) {
                $affectedCols[] = $rule_obj['apply_to_tables'][$tableName]['shadow_column'];
            }
        }

        if (!empty($affectedCols)) {
            $this->info("Shadow columns to be updated:");
            foreach ($affectedCols as $col) {
                $this->line("  - {$col}");
            }
            $this->line('');
        }

        // Count rows that would be affected
        $rule = $this->ruleEngine->getRule($rules[0]);
        $sourceCol = $rule['apply_to_tables'][$tableName]['source_column'] ?? null;
        $shadowCol = $rule['apply_to_tables'][$tableName]['shadow_column'] ?? null;

        if ($sourceCol && $shadowCol) {
            $countSql = "SELECT COUNT(*) as count FROM {$tableName} WHERE {$sourceCol} IS NOT NULL AND {$shadowCol} IS NULL";
            $count = DB::selectOne($countSql)->count ?? 0;

            if ($dryRun) {
                $this->info("[DRY RUN] Would update approximately {$count} rows");
            } else {
                $this->info("Will update approximately {$count} rows");
            }
            $this->line('');
        }

        // Confirm unless --force
        if (!$force && !$dryRun) {
            if (!$this->confirm('Proceed with shadow column backfill?')) {
                $this->info('Cancelled');
                return self::SUCCESS;
            }
        }

        // Validate all rules before proceeding
        $this->info('Validating rule consistency...');
        $validationResults = [];
        foreach ($rules as $rule) {
            $validation = $this->ruleEngine->validateRuleConsistency($rule);
            $validationResults[$rule] = $validation;

            if (!$validation['valid']) {
                $this->error("Rule '{$rule}' validation failed:");
                foreach ($validation['errors'] as $error) {
                    $this->line("  - {$error}");
                }
                return self::FAILURE;
            }
        }
        $this->info('✓ All rules validated successfully');
        $this->line('');

        // Execute backfill
        if ($async) {
            return $this->handleAsyncBackfill($tableName, $rules, $dryRun);
        } else {
            return $this->handleSyncBackfill($tableName, $rules, $dryRun);
        }
    }

    /**
     * Handle synchronous backfill
     */
    private function handleSyncBackfill(string $tableName, array $rules, bool $dryRun): int
    {
        $startTime = now();
        $bar = $this->output->createProgressBar(count($rules));
        $bar->start();

        try {
            $totalRowsUpdated = 0;

            foreach ($rules as $rule) {
                $result = $this->backfillRule($tableName, $rule, $dryRun);

                if ($result['success']) {
                    $totalRowsUpdated += $result['rows_updated'] ?? 0;
                } else {
                    $this->error("Failed to backfill rule '{$rule}': " . ($result['error'] ?? 'Unknown error'));
                    return self::FAILURE;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->line('');
            $this->line('');

            $elapsed = $startTime->diffInSeconds(now());
            if ($dryRun) {
                $this->info("✓ [DRY RUN] Would have updated {$totalRowsUpdated} total rows in {$elapsed}s");
            } else {
                $this->info("✓ Successfully updated {$totalRowsUpdated} rows in {$elapsed}s");
            }

            Log::info("Shadow backfill completed for {$tableName}", [
                'rules' => $rules,
                'rows_updated' => $totalRowsUpdated,
                'elapsed_seconds' => $elapsed,
                'dry_run' => $dryRun,
            ]);

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $bar->finish();
            $this->error('Backfill failed: ' . $e->getMessage());
            Log::error('Shadow backfill failed', [
                'table' => $tableName,
                'error' => $e->getMessage(),
            ]);
            return self::FAILURE;
        }
    }

    /**
     * Handle asynchronous backfill via queue
     */
    private function handleAsyncBackfill(string $tableName, array $rules, bool $dryRun): int
    {
        try {
            $job = new DistributedShadowBackfillJob(
                [$tableName],
                $rules,
                $dryRun
            );

            $this->dispatch($job);

            $this->info("✓ Shadow backfill job queued for table '{$tableName}'");
            $this->line('Job will be processed asynchronously');

            Log::info("Shadow backfill job queued", [
                'table' => $tableName,
                'rules' => $rules,
                'dry_run' => $dryRun,
            ]);

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Failed to queue backfill job: ' . $e->getMessage());
            Log::error('Failed to queue shadow backfill job', [
                'table' => $tableName,
                'error' => $e->getMessage(),
            ]);
            return self::FAILURE;
        }
    }

    /**
     * Backfill shadow columns for a single rule
     */
    private function backfillRule(string $tableName, string $ruleName, bool $dryRun): array
    {
        $rule = $this->ruleEngine->getRule($ruleName);
        if (!$rule) {
            return ['success' => false, 'error' => "Rule '{$ruleName}' not found"];
        }

        $tableConfig = $rule['apply_to_tables'][$tableName] ?? null;
        if (!$tableConfig) {
            return ['success' => false, 'error' => "Rule '{$ruleName}' not applicable to table '{$tableName}'"];
        }

        try {
            $updateSql = $this->ruleEngine->generateUpdateSql($tableName, $ruleName);
            if (!$updateSql) {
                return ['success' => false, 'error' => 'Could not generate UPDATE SQL'];
            }

            $sourceCol = $tableConfig['source_column'];
            $shadowCol = $tableConfig['shadow_column'];
            $fullSql = "{$updateSql} WHERE {$sourceCol} IS NOT NULL AND {$shadowCol} IS NULL";

            if ($dryRun) {
                $countSql = "SELECT COUNT(*) as count FROM {$tableName} WHERE {$sourceCol} IS NOT NULL AND {$shadowCol} IS NULL";
                $count = DB::selectOne($countSql)->count ?? 0;
                return ['success' => true, 'rows_updated' => $count];
            } else {
                $rowsUpdated = DB::update($fullSql);
                return ['success' => true, 'rows_updated' => $rowsUpdated];
            }

        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
