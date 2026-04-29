<?php

namespace App\Services\Shadow;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShadowColumnRuleEngine
{
    private array $config;

    public function __construct()
    {
        $this->config = config('shadow-columns');
    }

    /**
     * Get all rules from configuration
     */
    public function getAllRules(): array
    {
        return $this->config['rules'] ?? [];
    }

    /**
     * Get a specific rule by name
     */
    public function getRule(string $ruleName): ?array
    {
        return $this->config['rules'][$ruleName] ?? null;
    }

    /**
     * Get rules applicable to a specific table
     */
    public function getRulesForTable(string $tableName): array
    {
        $rules = [];
        foreach ($this->getAllRules() as $ruleName => $ruleConfig) {
            if (isset($ruleConfig['apply_to_tables'][$tableName])) {
                $rules[$ruleName] = array_merge($ruleConfig, [
                    'table_specific' => $ruleConfig['apply_to_tables'][$tableName],
                ]);
            }
        }
        return $rules;
    }

    /**
     * Get SQL transformation for a rule applied to a specific table
     */
    public function getTransformationSql(string $ruleName, string $tableName, string $sourceColumn): ?string
    {
        $rule = $this->getRule($ruleName);
        if (!$rule) {
            return null;
        }

        $tableConfig = $rule['apply_to_tables'][$tableName] ?? null;
        if (!$tableConfig) {
            return null;
        }

        if ($sourceColumn !== $tableConfig['source_column']) {
            return null;
        }

        // Use query_pattern if available, otherwise construct from transformation type
        if (isset($rule['query_pattern'])) {
            return str_replace('?', $sourceColumn, $rule['query_pattern']);
        }

        $transformType = $rule['transformation'] ?? 'upper_trim';
        $transformation = $this->config['transformations'][$transformType] ?? null;

        if (!$transformation || !isset($transformation['sql'])) {
            return null;
        }

        return str_replace('?', $sourceColumn, $transformation['sql']);
    }

    /**
     * Get all shadow columns for a table
     */
    public function getShadowColumnsForTable(string $tableName): array
    {
        $columns = [];
        foreach ($this->getRulesForTable($tableName) as $ruleName => $rule) {
            $tableSpec = $rule['table_specific'] ?? [];
            $shadowCol = $tableSpec['shadow_column'] ?? null;
            if ($shadowCol) {
                $columns[$shadowCol] = [
                    'rule' => $ruleName,
                    'source' => $tableSpec['source_column'] ?? null,
                    'priority' => $tableSpec['priority'] ?? 'MEDIUM',
                ];
            }
        }
        return $columns;
    }

    /**
     * Validate rule consistency across tables
     */
    public function validateRuleConsistency(string $ruleName): array
    {
        $rule = $this->getRule($ruleName);
        if (!$rule) {
            return ['valid' => false, 'error' => "Rule '{$ruleName}' not found"];
        }

        $errors = [];
        $transformType = $rule['transformation'] ?? null;

        if (!$transformType || !isset($this->config['transformations'][$transformType])) {
            $errors[] = "Invalid transformation type: {$transformType}";
        }

        // Verify all tables have the required columns
        foreach ($rule['apply_to_tables'] as $tableName => $config) {
            $sourceCol = $config['source_column'] ?? null;
            $shadowCol = $config['shadow_column'] ?? null;

            if (!$sourceCol) {
                $errors[] = "Table '{$tableName}' missing source_column";
            }
            if (!$shadowCol) {
                $errors[] = "Table '{$tableName}' missing shadow_column";
            }

            // Verify source column exists
            if ($sourceCol && !DB::getSchemaBuilder()->hasColumn($tableName, $sourceCol)) {
                $errors[] = "Table '{$tableName}' source column '{$sourceCol}' does not exist";
            }

            // Verify shadow column exists
            if ($shadowCol && !DB::getSchemaBuilder()->hasColumn($tableName, $shadowCol)) {
                $errors[] = "Table '{$tableName}' shadow column '{$shadowCol}' does not exist";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'rule' => $ruleName,
        ];
    }

    /**
     * Validate all rules for consistency
     */
    public function validateAllRules(): array
    {
        $results = [];
        foreach (array_keys($this->getAllRules()) as $ruleName) {
            $results[$ruleName] = $this->validateRuleConsistency($ruleName);
        }
        return $results;
    }

    /**
     * Generate UPDATE SQL for a shadow column using a rule
     */
    public function generateUpdateSql(string $tableName, string $ruleName): ?string
    {
        $rule = $this->getRule($ruleName);
        if (!$rule) {
            return null;
        }

        $tableConfig = $rule['apply_to_tables'][$tableName] ?? null;
        if (!$tableConfig) {
            return null;
        }

        $sourceCol = $tableConfig['source_column'];
        $shadowCol = $tableConfig['shadow_column'];
        $transformSql = $this->getTransformationSql($ruleName, $tableName, $sourceCol);

        if (!$transformSql) {
            return null;
        }

        // UPDATE table SET shadow_column = transformation(source_column) WHERE ...
        return "UPDATE {$tableName} SET {$shadowCol} = {$transformSql}";
    }

    /**
     * Get list of tables where a rule applies
     */
    public function getTablesForRule(string $ruleName): array
    {
        $rule = $this->getRule($ruleName);
        if (!$rule) {
            return [];
        }
        return array_keys($rule['apply_to_tables'] ?? []);
    }

    /**
     * Get priority-ordered rules for a table
     */
    public function getRulesByPriorityForTable(string $tableName): array
    {
        $rules = $this->getRulesForTable($tableName);

        $priorityMap = ['CRITICAL' => 0, 'HIGH' => 1, 'MEDIUM' => 2, 'LOW' => 3];

        usort($rules, function ($a, $b) use ($priorityMap) {
            $priorityA = $priorityMap[$a['table_specific']['priority'] ?? 'MEDIUM'] ?? 2;
            $priorityB = $priorityMap[$b['table_specific']['priority'] ?? 'MEDIUM'] ?? 2;
            return $priorityA <=> $priorityB;
        });

        return $rules;
    }

    /**
     * Get migration settings
     */
    public function getMigrationSettings(): array
    {
        return $this->config['migration'] ?? [];
    }

    /**
     * Get validation settings
     */
    public function getValidationSettings(): array
    {
        return $this->config['validation'] ?? [];
    }

    /**
     * Get monitoring settings
     */
    public function getMonitoringSettings(): array
    {
        return $this->config['monitoring'] ?? [];
    }

    /**
     * Get phase information
     */
    public function getPhaseInfo(string $phaseName): ?array
    {
        return $this->config['phases'][$phaseName] ?? null;
    }

    /**
     * Get all phases
     */
    public function getAllPhases(): array
    {
        return $this->config['phases'] ?? [];
    }

    /**
     * Check if all shadow columns exist for a table
     */
    public function shadowColumnsExistForTable(string $tableName): bool
    {
        $shadowCols = $this->getShadowColumnsForTable($tableName);

        foreach ($shadowCols as $shadowCol => $_) {
            if (!DB::getSchemaBuilder()->hasColumn($tableName, $shadowCol)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Log validation result
     */
    public function logValidationResult(array $validationResult, string $context = ''): void
    {
        $rule = $validationResult['rule'] ?? 'unknown';
        $valid = $validationResult['valid'] ?? false;

        if ($valid) {
            Log::info("Shadow column rule validation passed: {$rule}", ['context' => $context]);
        } else {
            $errors = implode('; ', $validationResult['errors'] ?? []);
            Log::warning("Shadow column rule validation failed: {$rule}", [
                'errors' => $errors,
                'context' => $context,
            ]);
        }
    }
}
