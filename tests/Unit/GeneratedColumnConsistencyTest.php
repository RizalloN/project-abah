<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class GeneratedColumnConsistencyTest extends TestCase
{
    public function test_daily_loan_generated_columns_have_legacy_backfill_pairs(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 2) . '/database/migrations/2026_05_12_000003_add_daily_loan_shadow_gc_and_cursor_columns.php');

        $this->assertIsString($migration);

        foreach ([
            'cabang_normalized_gc' => 'cabang_normalized',
            'unit_normalized_gc' => 'unit_normalized',
            'branch_normalized_gc' => 'branch_normalized',
            'rm_normalized_gc' => 'rm_normalized',
            'pn_pemutus_normalized_gc' => 'pn_pemutus_normalized',
            'cifno_clean_gc' => 'cifno_clean',
        ] as $generatedColumn => $legacyColumn) {
            $this->assertStringContainsString("'" . $generatedColumn . "'", $migration);
            $this->assertStringContainsString("'legacy' => '" . $legacyColumn . "'", $migration);
        }

        $this->assertStringContainsString('backfillLegacyColumnsFromGeneratedColumns', $migration);
        $this->assertStringContainsString('COALESCE(NULLIF(`{$legacy}`, \'\'), `{$column}`)', $migration);
        $this->assertStringContainsString('`{$legacy}` IS NULL OR `{$legacy}` = \'\'', $migration);
    }

    public function test_source_signature_prefers_generated_daily_loan_columns_when_available(): void
    {
        $service = file_get_contents(dirname(__DIR__, 2) . '/app/Support/SnapshotSourceSignatureService.php');

        $this->assertIsString($service);
        $this->assertStringContainsString("'daily_loan_dinamis' => ['cabang_normalized', 'cabang_normalized_gc', 'cabang1', 'branch1']", $service);
    }
}
