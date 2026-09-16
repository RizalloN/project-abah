<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class Lw325IndexOptimizationMigrationTest extends TestCase
{
    public function test_migration_replaces_only_the_redundant_scope_index_with_recent_update_lookup(): void
    {
        $path = dirname(__DIR__, 2).'/database/migrations/2026_09_12_194500_replace_redundant_lw325_scope_index.php';
        $contents = file_get_contents($path);

        $this->assertIsString($contents);
        $this->assertStringContainsString(
            "private const REDUNDANT_COLUMNS = ['periode', 'kanca', 'unit'];",
            $contents
        );
        $this->assertStringContainsString(
            "private const COVERING_COLUMNS = ['periode', 'kanca', 'unit', 'segmen_dashboard', 'pokok'];",
            $contents
        );
        $this->assertStringContainsString(
            "private const REPLACEMENT_COLUMNS = ['updated_at', 'periode'];",
            $contents
        );
        $this->assertStringContainsString(
            'DROP INDEX `idx_dhs_period_kanca_unit`',
            $contents
        );
        $this->assertStringContainsString(
            'ADD INDEX `idx_lw325ph_updated_period` (`updated_at`, `periode`)',
            $contents
        );
        $this->assertStringContainsString('ALGORITHM=NOCOPY, LOCK=NONE', $contents);
        $this->assertStringContainsString('assertNoActiveImports', $contents);
        $this->assertStringContainsString("'non_unique' => (int) \$row->non_unique", $contents);
        $this->assertStringContainsString("'index_type' => strtoupper((string) \$row->index_type)", $contents);
        $this->assertStringContainsString("'sub_parts' => []", $contents);
        $this->assertStringNotContainsString('ADD INDEX `idx_dhs_period_kanca_unit`', $contents);
        $this->assertStringNotContainsString('DROP INDEX `idx_lw325ph_updated_period`', $contents);
        $this->assertStringNotContainsString('OPTIMIZE TABLE', $contents);
        $this->assertStringNotContainsString('simpanan_multipn', $contents);
    }

    public function test_exact_definition_guard_rejects_constraint_or_prefix_changes(): void
    {
        $path = dirname(__DIR__, 2).'/database/migrations/2026_09_12_194500_replace_redundant_lw325_scope_index.php';
        $migration = require $path;
        $matchesAuditedIndex = new ReflectionMethod($migration, 'matchesAuditedIndex');
        $columns = ['periode', 'kanca', 'unit'];
        $definition = [
            'columns' => $columns,
            'non_unique' => 1,
            'index_type' => 'BTREE',
            'sub_parts' => [null, null, null],
        ];

        $this->assertTrue($matchesAuditedIndex->invoke($migration, $definition, $columns));

        $uniqueDefinition = $definition;
        $uniqueDefinition['non_unique'] = 0;
        $this->assertFalse($matchesAuditedIndex->invoke($migration, $uniqueDefinition, $columns));

        $fulltextDefinition = $definition;
        $fulltextDefinition['index_type'] = 'FULLTEXT';
        $this->assertFalse($matchesAuditedIndex->invoke($migration, $fulltextDefinition, $columns));

        $prefixDefinition = $definition;
        $prefixDefinition['sub_parts'][2] = 12;
        $this->assertFalse($matchesAuditedIndex->invoke($migration, $prefixDefinition, $columns));

        $this->assertFalse($matchesAuditedIndex->invoke($migration, null, $columns));
    }

    public function test_recent_period_polling_uses_the_replacement_index_with_a_safe_resolver(): void
    {
        $path = dirname(__DIR__, 2).'/app/Support/DashboardHarianSnapshotService.php';
        $contents = file_get_contents($path);

        $this->assertIsString($contents);
        $this->assertStringContainsString(
            "private const LW325_RECENT_PERIOD_INDEX = 'idx_lw325ph_updated_period';",
            $contents
        );
        $this->assertStringContainsString('app(ReportIndexHintResolver::class)->qualify(', $contents);
        $this->assertStringContainsString('[self::LW325_RECENT_PERIOD_INDEX]', $contents);
    }
}
