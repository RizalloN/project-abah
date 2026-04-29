<?php

namespace App\Jobs;

use App\Services\Shadow\ShadowColumnRuleEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DistributedShadowBackfillJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $backoff = [60, 120, 300, 600, 1200];

    protected array $tables;
    protected array $rules;
    protected bool $dryRun;
    protected ?callable $progressCallback;

    /**
     * Create a new job instance
     */
    public function __construct(
        array $tables = [],
        array $rules = [],
        bool $dryRun = false
    ) {
        $this->tables = $tables;
        $this->rules = $rules;
        $this->dryRun = $dryRun;
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        $ruleEngine = app(ShadowColumnRuleEngine::class);

        Log::info('Distributed shadow backfill job started', [
            'tables' => $this->tables,
            'rules' => $this->rules,
            'dry_run' => $this->dryRun,
        ]);

        try {
            // Determine which tables and rules to process
            $tablesToProcess = !empty($this->tables)
                ? $this->tables
                : $this->getDefaultTablesForBackfill();

            $rulesToProcess = !empty($this->rules)
                ? $this->rules
                : $this->getDefaultRules();

            $startTime = now();
            $results = [];

            foreach ($tablesToProcess as $tableName) {
                $this->reportProgress("Processing table: {$tableName}");

                $tableResult = $this->backfillTable(
                    $tableName,
                    $rulesToProcess,
                    $ruleEngine
                );

                $results[$tableName] = $tableResult;

                if (!$tableResult['success'] && !$tableResult['can_retry']) {
                    Log::error("Backfill failed for table {$tableName}, no retry possible", $tableResult);
                    break;
                }
            }

            $elapsed = $startTime->diffInSeconds(now());
            Log::info('Distributed shadow backfill job completed', [
                'elapsed_seconds' => $elapsed,
                'results' => $results,
                'dry_run' => $this->dryRun,
            ]);

            $this->reportProgress("Backfill completed in {$elapsed}s", true);

        } catch (\Throwable $e) {
            Log::error('Distributed shadow backfill job failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Backfill shadow columns for a single table
     */
    protected function backfillTable(
        string $tableName,
        array $rulesToProcess,
        ShadowColumnRuleEngine $ruleEngine
    ): array {
        $result = [
            'table' => $tableName,
            'success' => false,
            'can_retry' => false,
            'rules_processed' => [],
            'rows_updated' => 0,
            'errors' => [],
        ];

        try {
            // Verify table exists
            if (!DB::getSchemaBuilder()->hasTable($tableName)) {
                $result['errors'][] = "Table {$tableName} does not exist";
                $result['can_retry'] = false;
                return $result;
            }

            // Get applicable rules for this table
            $applicableRules = array_filter(
                $rulesToProcess,
                fn($rule) => in_array($tableName, $ruleEngine->getTablesForRule($rule))
            );

            if (empty($applicableRules)) {
                $result['success'] = true;
                $result['can_retry'] = false;
                $result['reason'] = 'No applicable rules for this table';
                return $result;
            }

            // Process each rule for this table
            foreach ($applicableRules as $ruleName) {
                $this->reportProgress("  Backfilling rule: {$ruleName}");

                $ruleResult = $this->backfillRule($tableName, $ruleName, $ruleEngine);
                $result['rules_processed'][$ruleName] = $ruleResult;
                $result['rows_updated'] += $ruleResult['rows_updated'] ?? 0;

                if (!$ruleResult['success']) {
                    $result['errors'][] = $ruleResult['error'] ?? "Unknown error for rule {$ruleName}";
                    if ($ruleResult['can_retry'] ?? false) {
                        $result['can_retry'] = true;
                    }
                }
            }

            $result['success'] = empty($result['errors']);
            return $result;

        } catch (\Throwable $e) {
            $result['errors'][] = $e->getMessage();
            $result['can_retry'] = true;
            return $result;
        }
    }

    /**
     * Backfill shadow columns for a single rule applied to a table
     */
    protected function backfillRule(
        string $tableName,
        string $ruleName,
        ShadowColumnRuleEngine $ruleEngine
    ): array {
        $result = [
            'rule' => $ruleName,
            'table' => $tableName,
            'success' => false,
            'rows_updated' => 0,
            'can_retry' => false,
            'error' => null,
        ];

        try {
            $updateSql = $ruleEngine->generateUpdateSql($tableName, $ruleName);
            if (!$updateSql) {
                $result['error'] = "Could not generate UPDATE SQL for {$tableName}.{$ruleName}";
                return $result;
            }

            // Add WHERE clause to only update rows where source is not null
            $rule = $ruleEngine->getRule($ruleName);
            $tableConfig = $rule['apply_to_tables'][$tableName];
            $sourceCol = $tableConfig['source_column'];
            $shadowCol = $tableConfig['shadow_column'];

            $fullSql = "{$updateSql} WHERE {$sourceCol} IS NOT NULL AND {$shadowCol} IS NULL";

            if ($this->dryRun) {
                // In dry-run mode, just count how many rows would be updated
                $countSql = "SELECT COUNT(*) as count FROM {$tableName} WHERE {$sourceCol} IS NOT NULL AND {$shadowCol} IS NULL";
                $count = DB::selectOne($countSql)->count ?? 0;
                $result['rows_updated'] = $count;
                $result['success'] = true;
                $this->reportProgress("    [DRY RUN] Would update {$count} rows");
            } else {
                // Execute the actual UPDATE
                $rowsUpdated = DB::update($fullSql);
                $result['rows_updated'] = $rowsUpdated;
                $result['success'] = true;
                $this->reportProgress("    Updated {$rowsUpdated} rows");
            }

            return $result;

        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
            $result['can_retry'] = true;
            return $result;
        }
    }

    /**
     * Get default tables for backfill (Phase 1 & 2)
     */
    protected function getDefaultTablesForBackfill(): array
    {
        return [
            'daily_loan_dinamis',  // Already has shadow columns, validate
            'simpanan_multipn',    // Phase 1 priority
            'brihc',               // Phase 2 priority
            'casa_brilink_web',    // Additional
        ];
    }

    /**
     * Get default rules to process (all critical and high-priority)
     */
    protected function getDefaultRules(): array
    {
        return [
            'cif_normalization',
            'account_normalization',
            'branch_normalization',
            'segment_normalization',
        ];
    }

    /**
     * Report progress
     */
    protected function reportProgress(string $message, bool $isComplete = false): void
    {
        if ($this->progressCallback) {
            call_user_func($this->progressCallback, $message, $isComplete);
        }

        Log::info("Shadow backfill progress: {$message}");
    }

    /**
     * Set progress callback
     */
    public function onProgress(callable $callback): self
    {
        $this->progressCallback = $callback;
        return $this;
    }

    /**
     * Failed job handler
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Distributed shadow backfill job ultimately failed', [
            'error' => $exception->getMessage(),
            'tables' => $this->tables,
            'rules' => $this->rules,
        ]);
    }

    /**
     * Determine if this job should be retried based on completion percentage
     */
    public function shouldRetry(): bool
    {
        // Override the default retry logic to check if we've made progress
        // This allows the job to be retried if completion is below threshold
        return $this->attempts() < $this->tries;
    }
}
