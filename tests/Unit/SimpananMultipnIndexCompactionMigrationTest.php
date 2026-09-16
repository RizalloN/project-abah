<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SimpananMultipnIndexCompactionMigrationTest extends TestCase
{
    public function test_migration_compacts_only_the_audited_covering_index_with_online_ddl_guards(): void
    {
        $path = dirname(__DIR__, 2).'/database/migrations/2026_09_07_120000_compact_simpanan_multipn_period_covering_index.php';
        $contents = file_get_contents($path);

        $this->assertIsString($contents);
        $this->assertStringContainsString("private const INDEX = 'idx_smp_period_covering_counts';", $contents);
        $this->assertStringContainsString("'no_rekening',", $contents);
        $this->assertStringContainsString("'CIFNO',", $contents);
        $this->assertStringContainsString("'jenis_simpanan',", $contents);
        $this->assertStringContainsString(
            'ADD INDEX `idx_smp_period_covering_counts` (`posisi`, `kantor_cabang`, `unit_kerja`, `saldo_idr`)',
            $contents
        );
        $this->assertStringContainsString('ALGORITHM=NOCOPY, LOCK=NONE', $contents);
        $this->assertStringContainsString('assertNoActiveImports', $contents);
        $this->assertStringContainsString('columns !== self::LEGACY_COLUMNS', $contents);
    }

    public function test_casa_queries_prefer_the_dedicated_covering_index(): void
    {
        $optimizer = file_get_contents(dirname(__DIR__, 2).'/app/Support/SnapshotQueryOptimizer.php');
        $builder = file_get_contents(dirname(__DIR__, 2).'/app/Support/ReportSnapshotBuilder.php');

        $this->assertIsString($optimizer);
        $this->assertIsString($builder);
        $this->assertStringContainsString("'idx_smp_posisi_cif_covering'", $optimizer);
        $this->assertStringContainsString("'idx_smp_posisi_cif_covering'", $builder);
        $this->assertStringNotContainsString(
            "'idx_smp_posisi_distinct_queries', // Primary: (posisi, no_rekening, CIFNO)",
            $optimizer
        );
    }

    public function test_casa_snapshot_limits_balance_aggregation_to_current_loan_identities(): void
    {
        $builder = file_get_contents(dirname(__DIR__, 2).'/app/Support/ReportSnapshotBuilder.php');

        $this->assertIsString($builder);
        $this->assertStringContainsString('tmp_rasio_loan_identities', $builder);
        $this->assertStringContainsString('INNER JOIN tmp_rasio_loan_identities eligible', $builder);
        $this->assertStringContainsString("['idx_loan_periode_cif']", $builder);
        $this->assertStringContainsString("['idx_smp_posisi_cif_covering']", $builder);
        $this->assertStringContainsString('$this->rasioCasaTempTableCacheKey === $cacheKey', $builder);
        $this->assertStringContainsString('$loanPeriod,', $builder);
        $this->assertStringContainsString('$casaDate,', $builder);
        $this->assertStringContainsString('strtolower($loanKeyColumn)', $builder);
        $this->assertStringContainsString('strtolower($casaKeyColumn)', $builder);
        $this->assertStringContainsString("\$applyCasaTypeFilter ? '1' : '0'", $builder);
        $this->assertStringContainsString('GROUP BY eligible.identity_key', $builder);
    }
}
