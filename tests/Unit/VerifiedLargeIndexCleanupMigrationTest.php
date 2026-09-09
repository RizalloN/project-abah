<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class VerifiedLargeIndexCleanupMigrationTest extends TestCase
{
    public function test_cleanup_is_limited_to_verified_indexes_and_uses_online_ddl(): void
    {
        $path = dirname(__DIR__, 2).'/database/migrations/2026_09_06_230000_drop_verified_redundant_large_indexes.php';
        $contents = file_get_contents($path);

        $this->assertIsString($contents);

        foreach ([
            'idx_smp_jenis_simpanan_filter',
            'daily_loan_dinamis_cifno_index',
            'idx_cifno_clean',
        ] as $indexName) {
            $this->assertStringContainsString("'{$indexName}'", $contents);
        }

        foreach ([
            'idx_posisi_uid',
            'idx_cabang_normalized',
            'idx_unit_normalized',
            'idx_branch_normalized',
            'idx_rm_normalized',
            'idx_pn_pemutus_normalized',
        ] as $protectedIndexName) {
            $this->assertStringNotContainsString("'{$protectedIndexName}'", $contents);
        }

        $this->assertStringContainsString('ALGORITHM=NOCOPY, LOCK=NONE', $contents);
        $this->assertStringContainsString('indexColumnMap', $contents);
    }
}
