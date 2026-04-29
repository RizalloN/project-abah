<?php

namespace Tests\Unit;

use App\Services\Import\Strategies\DailyLoanImportStrategy;
use App\Services\Import\Strategies\ConfiguredExcelImportStrategy;
use App\Services\Import\Strategies\Gi405RecDhImportStrategy;
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
        $this->assertSame('bulk_csv_direct', $strategy->importMode(['active_filters' => [0 => ['A']]]));
    }

    public function test_simpanan_strategy_transforms_row_number_headers(): void
    {
        $strategy = new SimpananMultiPnImportStrategy();

        $headers = $strategy->transformHeaders(['No', 'Nama', 'Posisi']);
        $context = $strategy->prepareContext(['table_name' => 'simpanan_multipn']);

        $this->assertSame(['COL_0', 'Nama', 'Posisi'], $headers);
        $this->assertTrue($context['ignore_row_number_headers']);
        $this->assertTrue($strategy->supports((object) ['table_name' => 'simpanan_multipn']));
    }

    public function test_simpanan_strategy_keeps_status_source_position_after_row_number_header(): void
    {
        $strategy = new SimpananMultiPnImportStrategy();

        $headers = $strategy->transformHeaders([
            'No',
            'Posisi',
            '',
            'Regional Office',
            'Kantor Cabang',
            '',
            'Unit Kerja',
            'CIFNO',
            'No Rekening',
            'Status',
            'Jenis Simpanan',
            'Saldo IDR',
        ]);

        $this->assertSame('COL_0', $headers[0]);
        $this->assertSame('Status', $headers[9]);
        $this->assertSame('Saldo IDR', $headers[11]);
    }

    public function test_generic_strategy_is_safe_default(): void
    {
        $strategy = new GenericCsvImportStrategy();

        $this->assertTrue($strategy->supports(null, 'anything'));
        $this->assertFalse($strategy->supports(null, 'lw325_ph'));
        $this->assertFalse($strategy->supports(null, 'daily_loan_dinamis'));
        $this->assertFalse($strategy->supports(null, 'simpanan_multipn'));
        $this->assertFalse($strategy->supports(null, 'rka'));
        $this->assertFalse($strategy->supports(null, 'brihc'));
        $this->assertFalse($strategy->supports(null, 'wilayah_mbm'));
        $this->assertSame(['A', 'B'], $strategy->transformHeaders(['A', 'B']));
        $this->assertSame('bulk_csv_staging', $strategy->importMode());
    }

    public function test_configured_excel_strategy_owns_small_excel_reports_with_custom_logic(): void
    {
        $strategy = new ConfiguredExcelImportStrategy();

        $this->assertTrue($strategy->supports(null, 'rka'));
        $this->assertTrue($strategy->supports(null, 'brihc'));
        $this->assertTrue($strategy->supports(null, 'wilayah_mbm'));
        $this->assertFalse($strategy->supports(null, 'daily_loan_dinamis'));
        $this->assertSame('bulk_csv_staging', $strategy->importMode());
    }

    public function test_ssa_strategies_use_staging_mode_for_raw_fast_imports(): void
    {
        $ssaSimpanan = new SsaSimpananImportStrategy();
        $ssaPinjaman = new SsaPinjamanImportStrategy();

        $this->assertTrue($ssaSimpanan->supports(null, 'ssa_simpanan'));
        $this->assertSame('bulk_csv_staging', $ssaSimpanan->importMode());

        $this->assertTrue($ssaPinjaman->supports(null, 'ssa_pinjaman'));
        $this->assertSame('bulk_csv_staging', $ssaPinjaman->importMode());
    }

    public function test_gi405_strategy_uses_staging_mode_for_safer_imports(): void
    {
        $strategy = new Gi405RecDhImportStrategy();

        $this->assertTrue($strategy->supports(null, 'gi405_rec_dh'));
        $this->assertSame('bulk_csv_staging', $strategy->importMode());
    }
}
