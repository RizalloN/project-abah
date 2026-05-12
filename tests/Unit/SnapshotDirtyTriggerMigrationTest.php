<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SnapshotDirtyTriggerMigrationTest extends TestCase
{
    public function test_dirty_trigger_migration_marks_all_supported_sources_without_snapshot_delete_in_up(): void
    {
        $path = dirname(__DIR__, 2) . '/database/migrations/2026_05_12_000002_create_dirty_marker_triggers.php';
        $contents = file_get_contents($path);

        $this->assertIsString($contents);

        foreach ([
            'daily_loan_dinamis',
            'simpanan_multipn',
            'ssa_simpanan',
            'ssa_pinjaman',
            'lw325_ph',
            'hourly_dpk',
        ] as $table) {
            $this->assertStringContainsString("'" . $table . "'", $contents);
        }

        $upSection = substr($contents, strpos($contents, 'public function up'), strpos($contents, 'public function down') - strpos($contents, 'public function up'));

        $this->assertStringContainsString('INSERT INTO snapshot_dirty_periods', $contents);
        $this->assertStringContainsString('@skip_snapshot_invalidation', $contents);
        $this->assertStringNotContainsString('DELETE FROM dashboard_', $upSection);
    }
}
