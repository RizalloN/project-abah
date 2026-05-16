<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class BulkDeleteTriggerOverheadTest extends TestCase
{
    public function test_dirty_marker_triggers_are_coalesced_and_do_not_delete_snapshots_per_row(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 2) . '/database/migrations/2026_05_12_000002_create_dirty_marker_triggers.php');

        $this->assertIsString($migration);

        $upSection = substr(
            $migration,
            strpos($migration, 'public function up'),
            strpos($migration, 'public function down') - strpos($migration, 'public function up')
        );

        $this->assertStringContainsString('INSERT INTO snapshot_dirty_periods', $migration);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $migration);
        $this->assertStringContainsString('FIND_IN_SET(@snapshot_dirty_dedupe_key', $migration);
        $this->assertStringContainsString('@skip_snapshot_invalidation', $migration);
        $this->assertStringNotContainsString('DELETE FROM dashboard_', $upSection);
        $this->assertStringNotContainsString('DELETE FROM performance_', $upSection);
    }
}
