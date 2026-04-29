<?php

namespace App\Console\Commands;

use App\Services\Shadow\ShadowColumnRuleEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShadowValidateConsistencyCommand extends Command
{
    protected $signature = 'shadow:validate-consistency
        {--table= : Validate a specific table only}
        {--rule= : Validate a specific rule only}
        {--fix : Attempt to fix identified issues}
        {--detailed : Show detailed validation report}';

    protected $description = 'Validate consistency of shadow columns across all tables and rules';

    private ShadowColumnRuleEngine $ruleEngine;
    private int $errorsFound = 0;

    public function handle(): int
    {
        $this->ruleEngine = app(ShadowColumnRuleEngine::class);

        $tableName = $this->option('table');
        $ruleName = $this->option('rule');
        $fix = $this->option('fix');
        $detailed = $this->option('detailed');

        $this->line('');
        $this->info('╔════════════════════════════════════════════════════════════════╗');
        $this->info('║       SHADOW COLUMNS CONSISTENCY VALIDATION                    ║');
        $this->info('╚════════════════════════════════════════════════════════════════╝');
        $this->line('');

        // Validate rules
        if ($ruleName) {
            $this->validateRule($ruleName, $tableName, $fix, $detailed);
        } else {
            $this->validateAllRules($tableName, $fix, $detailed);
        }

        if ($this->errorsFound === 0) {
            $this->info('✓ All validations passed!');
            return self::SUCCESS;
        } else {
            $this->error("✗ Found {$this->errorsFound} validation errors");
            return self::FAILURE;
        }
    }

    /**
     * Validate a specific rule
     */
    private function validateRule(string $ruleName, ?string $tableName = null, bool $fix = false, bool $detailed = false): void
    {
        $rule = $this->ruleEngine->getRule($ruleName);

        if (!$rule) {
            $this->error("Rule '{$ruleName}' not found");
            $this->errorsFound++;
            return;
        }

        $this->info("Validating rule: {$ruleName}");

        $result = $this->ruleEngine->validateRuleConsistency($ruleName);

        if (!$result['valid']) {
            $this->errorsFound++;
            $this->error("  ✗ Validation failed");
            foreach ($result['errors'] as $error) {
                $this->line("    - {$error}");
            }
        } else {
            $this->info("  ✓ Validation passed");
        }

        // Check transformation consistency
        foreach ($rule['apply_to_tables'] as $table => $config) {
            if ($tableName && $table !== $tableName) {
                continue;
            }

            $this->validateTableRuleConsistency($table, $ruleName, $fix, $detailed);
        }

        $this->line('');
    }

    /**
     * Validate all rules
     */
    private function validateAllRules(?string $tableName = null, bool $fix = false, bool $detailed = false): void
    {
        $results = $this->ruleEngine->validateAllRules();

        foreach ($results as $ruleName => $result) {
            if (!$result['valid']) {
                $this->error("Rule '{$ruleName}':");
                foreach ($result['errors'] as $error) {
                    $this->line("  - {$error}");
                }
                $this->errorsFound++;
            }
        }

        // Validate table-specific consistency
        $tables = $tableName
            ? [$tableName]
            : ['daily_loan_dinamis', 'simpanan_multipn', 'brihc', 'casa_brilink_web'];

        foreach ($tables as $table) {
            if (!DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            foreach (array_keys($results) as $ruleName) {
                $this->validateTableRuleConsistency($table, $ruleName, $fix, $detailed);
            }
        }
    }

    /**
     * Validate consistency for a table-rule combination
     */
    private function validateTableRuleConsistency(
        string $tableName,
        string $ruleName,
        bool $fix = false,
        bool $detailed = false
    ): void {
        $rule = $this->ruleEngine->getRule($ruleName);
        if (!isset($rule['apply_to_tables'][$tableName])) {
            return;
        }

        $tableConfig = $rule['apply_to_tables'][$tableName];
        $sourceCol = $tableConfig['source_column'];
        $shadowCol = $tableConfig['shadow_column'];

        if ($detailed) {
            $this->line("  Checking {$tableName}.{$shadowCol} (from {$sourceCol})...");
        }

        // Check 1: Shadow column has NULL where source is NULL
        $nullMismatch = DB::table($tableName)
            ->whereNull($sourceCol)
            ->whereNotNull($shadowCol)
            ->count();

        if ($nullMismatch > 0) {
            $this->error("    ✗ {$nullMismatch} rows have NULL source but non-NULL shadow");
            $this->errorsFound++;

            if ($fix) {
                DB::table($tableName)
                    ->whereNull($sourceCol)
                    ->update([$shadowCol => null]);
                $this->info("      Fixed: Set {$nullMismatch} shadow values to NULL");
            }
        }

        // Check 2: Shadow column is not NULL where source is not NULL (completion check)
        if ($detailed) {
            $totalSourceNotNull = DB::table($tableName)
                ->whereNotNull($sourceCol)
                ->count();

            if ($totalSourceNotNull > 0) {
                $shadowFilled = DB::table($tableName)
                    ->whereNotNull($sourceCol)
                    ->whereNotNull($shadowCol)
                    ->count();

                $completionPercent = round(($shadowFilled / $totalSourceNotNull) * 100);
                $this->line("    Completion: {$completionPercent}% ({$shadowFilled}/{$totalSourceNotNull})");
            }
        }

        // Check 3: Validate transformation consistency (spot-check some rows)
        $sampleSize = 10;
        $samples = DB::table($tableName)
            ->whereNotNull($sourceCol)
            ->whereNotNull($shadowCol)
            ->limit($sampleSize)
            ->get([$sourceCol, $shadowCol]);

        foreach ($samples as $sample) {
            $expectedValue = $this->applyTransformation(
                $sample->$sourceCol,
                $rule['transformation']
            );

            if ($expectedValue !== $sample->$shadowCol) {
                $this->error("    ✗ Transformation mismatch detected");
                $this->line("      Source: {$sample->$sourceCol}");
                $this->line("      Shadow: {$sample->$shadowCol}");
                $this->line("      Expected: {$expectedValue}");
                $this->errorsFound++;

                if ($fix) {
                    DB::table($tableName)
                        ->where($sourceCol, $sample->$sourceCol)
                        ->update([$shadowCol => $expectedValue]);
                    $this->info("      Fixed: Updated shadow value");
                }
            }
        }
    }

    /**
     * Apply transformation to a value
     */
    private function applyTransformation(string $value, string $transformType): string
    {
        return match ($transformType) {
            'numeric_only' => preg_replace('/[^0-9]/', '', $value),
            'upper_trim' => strtoupper(trim($value)),
            'lower_trim' => strtolower(trim($value)),
            default => $value,
        };
    }
}
