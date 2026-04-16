<?php

namespace Tests\Unit;

use App\Services\Import\Strategies\DailyLoanImportStrategy;
use App\Services\Import\Strategies\GenericCsvImportStrategy;
use App\Services\Import\Strategies\SimpananMultiPnImportStrategy;
use App\Services\Import\Strategies\SsaPinjamanImportStrategy;
use App\Services\Import\Strategies\SsaSimpananImportStrategy;
use PHPUnit\Framework\TestCase;

class ImportStrategiesTest extends TestCase
{
    public function test_daily_loan_strategy_validates_required_schema_columns(): void
    {
        $strategy = new DailyLoanImportStrategy();

        $valid = $strategy->validateSchema(['periode', 'baki_debet1']);
        $invalid = $strategy->validateSchema(['periode', 'nomor_rekening']);

        $this->assertTrue($valid['ok']);
        $this->assertFalse($invalid['ok']);
        $this->assertSame('bulk_csv_direct', $strategy->importMode(['active_filters' => []]));
        $this->assertSame('bulk_csv_filtered', $strategy->importMode(['active_filters' => [0 => ['A']]]));
    }

    public function test_simpanan_strategy_transforms_row_number_headers(): void
    {
        $strategy = new SimpananMultiPnImportStrategy();

        $headers = $strategy->transformHeaders(['No', 'Nama', 'Posisi']);
        $context = $strategy->prepareContext(['table_name' => 'simpanan_multipn']);

        $this->assertSame(['Nama', 'Posisi'], $headers);
        $this->assertTrue($context['ignore_row_number_headers']);
        $this->assertTrue($strategy->supports((object) ['table_name' => 'simpanan_multipn']));
    }

    public function test_generic_strategy_is_safe_default(): void
    {
        $strategy = new GenericCsvImportStrategy();

        $this->assertTrue($strategy->supports(null, 'anything'));
        $this->assertSame(['A', 'B'], $strategy->transformHeaders(['A', 'B']));
        $this->assertSame('bulk_csv_staging', $strategy->importMode());
    }

    public function test_ssa_strategies_use_direct_csv_mode(): void
    {
        $ssaSimpanan = new SsaSimpananImportStrategy();
        $ssaPinjaman = new SsaPinjamanImportStrategy();

        $this->assertTrue($ssaSimpanan->supports(null, 'ssa_simpanan'));
        $this->assertSame('bulk_csv_direct', $ssaSimpanan->importMode());

        $this->assertTrue($ssaPinjaman->supports(null, 'ssa_pinjaman'));
        $this->assertSame('bulk_csv_direct', $ssaPinjaman->importMode());
    }
}
