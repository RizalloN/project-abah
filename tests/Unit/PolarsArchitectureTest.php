<?php

namespace Tests\Unit;

use App\Services\Import\ImportStrategyFactory;
use App\Services\Import\Strategies\CognosPhImportStrategy;
use App\Services\Import\Strategies\CognosRecoveryImportStrategy;
use App\Services\Import\Strategies\DailyLoanImportStrategy;
use App\Services\Import\Strategies\GenericCsvImportStrategy;
use App\Services\Import\Strategies\Gi405RecDhImportStrategy;
use App\Services\Import\Strategies\L1133ImportStrategy;
use App\Services\Import\Strategies\Lw321NpdImportStrategy;
use App\Services\Import\Strategies\Lw321NpddImportStrategy;
use App\Services\Import\Strategies\Lw321PnImportStrategy;
use App\Services\Import\Strategies\Lw325PhImportStrategy;
use App\Services\Import\Strategies\PerformancePisImportStrategy;
use App\Services\Import\Strategies\SimpananMultiPnImportStrategy;
use App\Services\Import\Strategies\SsaPinjamanImportStrategy;
use App\Services\Import\Strategies\SsaSimpananImportStrategy;
use Tests\TestCase;

class PolarsArchitectureTest extends TestCase
{
    public function test_factory_all_has_no_duplicate_strategy_keys(): void
    {
        $factory = app(ImportStrategyFactory::class);
        $keys = array_map(fn ($s) => $s->key(), $factory->all());

        $this->assertSame(count($keys), count(array_unique($keys)), 'Factory has duplicate strategy keys: ' . implode(', ', array_diff_assoc($keys, array_unique($keys))));
        $this->assertContains('ssa_simpanan', $keys);
        $this->assertContains('ssa_pinjaman', $keys);
    }

    public function test_each_strategy_specifies_correct_import_mode(): void
    {
        $cases = [
            [DailyLoanImportStrategy::class, 'bulk_csv_direct', []],
            [DailyLoanImportStrategy::class, 'bulk_csv_direct', ['active_filters' => [[0 => ['A']]]]],
            [SimpananMultiPnImportStrategy::class, 'bulk_csv_filtered', []],
            [SsaSimpananImportStrategy::class, 'bulk_csv_staging', []],
            [SsaPinjamanImportStrategy::class, 'bulk_csv_staging', []],
            [Gi405RecDhImportStrategy::class, 'bulk_csv_filtered', []],
            [Lw321NpdImportStrategy::class, 'bulk_csv_filtered', []],
            [Lw321NpddImportStrategy::class, 'bulk_csv_filtered', []],
            [GenericCsvImportStrategy::class, 'bulk_csv_staging', []],
            [CognosPhImportStrategy::class, 'bulk_csv_staging', []],
            [CognosRecoveryImportStrategy::class, 'bulk_csv_staging', []],
            [PerformancePisImportStrategy::class, 'bulk_csv_staging', []],
            [L1133ImportStrategy::class, 'bulk_csv_staging', []],
            [Lw325PhImportStrategy::class, 'bulk_csv_direct', []],
        ];

        foreach ($cases as [$class, $expectedMode, $context]) {
            $strategy = new $class();
            $this->assertSame(
                $expectedMode,
                $strategy->importMode($context),
                "{$class} should use mode {$expectedMode}"
            );
        }
    }

    public function test_each_polars_processor_script_exists(): void
    {
        $scripts = [
            'daily_loan_polars_processor.py',
            'simpanan_multipn_polars_processor.py',
            'excel_gpu_processor.py',
            'gi405_rec_dh_polars_processor.py',
            'ssa_simpanan_polars_processor.py',
            'ssa_pinjaman_polars_processor.py',
            'lw325_ph_polars_processor.py',
        ];

        foreach ($scripts as $script) {
            $this->assertFileExists(
                base_path('scripts/' . $script),
                "Polars processor script missing: {$script}"
            );
        }
    }

    public function test_each_strategy_supports_its_target_table(): void
    {
        $cases = [
            [DailyLoanImportStrategy::class, 'daily_loan_dinamis'],
            [SimpananMultiPnImportStrategy::class, 'simpanan_multipn'],
            [SsaSimpananImportStrategy::class, 'ssa_simpanan'],
            [SsaPinjamanImportStrategy::class, 'ssa_pinjaman'],
            [Gi405RecDhImportStrategy::class, 'gi405_singlerow'],
            [Lw325PhImportStrategy::class, 'lw325_ph'],
            [Lw321PnImportStrategy::class, 'lw321pn'],
            [Lw321NpdImportStrategy::class, 'lw321_npd'],
            [Lw321NpddImportStrategy::class, 'lw321_npdd'],
            [CognosPhImportStrategy::class, 'cognos_ph'],
            [CognosRecoveryImportStrategy::class, 'cognos_recovery'],
            [PerformancePisImportStrategy::class, 'performance_pis_per_produk'],
            [L1133ImportStrategy::class, 'l1133'],
        ];

        foreach ($cases as [$class, $table]) {
            $strategy = new $class();
            $this->assertTrue(
                $strategy->supports(null, $table),
                "{$class} must support table {$table}"
            );
        }

        $generic = new GenericCsvImportStrategy();
        foreach ($cases as [$class, $table]) {
            $this->assertFalse($generic->supports(null, $table), 'GenericCsvImportStrategy must not handle specialized table ' . $table);
        }

        $this->assertTrue($generic->supports(null, 'unknown_report_table'));
    }

    public function test_factory_resolves_ssa_simpanan_correctly(): void
    {
        $factory = app(ImportStrategyFactory::class);
        $resolved = $factory->resolve(null, 'ssa_simpanan');

        $this->assertInstanceOf(SsaSimpananImportStrategy::class, $resolved);
    }

    public function test_factory_resolves_ssa_pinjaman_correctly(): void
    {
        $factory = app(ImportStrategyFactory::class);
        $resolved = $factory->resolve(null, 'ssa_pinjaman');

        $this->assertInstanceOf(SsaPinjamanImportStrategy::class, $resolved);
    }

    public function test_factory_resolves_unknown_tables_to_generic_strategy(): void
    {
        $factory = app(ImportStrategyFactory::class);
        $resolved = $factory->resolve(null, 'custom_unmapped_report');

        $this->assertInstanceOf(GenericCsvImportStrategy::class, $resolved);
    }
}
