<?php

namespace Tests\Unit;

use App\Services\Import\Strategies\DailyLoanImportStrategy;
use App\Services\Import\Strategies\ConfiguredExcelImportStrategy;
use App\Services\Import\Strategies\CrasImportStrategy;
use App\Services\Import\Strategies\Gi405RecDhImportStrategy;
use App\Services\Import\Strategies\GenericCsvImportStrategy;
use App\Services\Import\Strategies\HourlyDpkImportStrategy;
use App\Services\Import\Strategies\L1133ImportStrategy;
use App\Services\Import\Strategies\Lw321PnImportStrategy;
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
        $this->assertFalse($strategy->supports(null, 'hourly_dpk'));
        $this->assertFalse($strategy->supports(null, 'cras'));
        $this->assertFalse($strategy->supports(null, 'l1133'));
        $this->assertFalse($strategy->supports(null, 'lw321pn'));
        $this->assertSame(['A', 'B'], $strategy->transformHeaders(['A', 'B']));
        $this->assertSame('bulk_csv_staging', $strategy->importMode());
    }

    public function test_cras_uses_dedicated_exact_bulk_strategy(): void
    {
        $strategy = new CrasImportStrategy();

        $this->assertTrue($strategy->supports(null, 'cras'));
        $this->assertSame('cras_exact_bulk', $strategy->importMode());
        $this->assertTrue($strategy->validateSchema([
            'cras_uuid',
            'cras_periode',
            'month_day_year_of_posisi',
            'ket_kanca',
        ])['ok']);
        $this->assertFalse($strategy->validateSchema(['ket_kanca'])['ok']);
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

    public function test_ssa_strategies_normalize_and_validate_current_source_workbook_columns(): void
    {
        $ssaSimpanan = new SsaSimpananImportStrategy();
        $ssaPinjaman = new SsaPinjamanImportStrategy();

        $this->assertSame([
            'month_day_year_of_posisi',
            'nama_cabang',
            'nama_uker',
            'produk',
            'segmentasi',
            'segmen_kategorisasi_bisnis',
            'saldo',
        ], $ssaSimpanan->transformHeaders([
            'Month, Day, Year of Posisi',
            'Nama Cabang',
            'Nama Uker',
            'Produk',
            'Segmentasi',
            'Segmen Kategorisasi Bisnis',
            'Saldo',
        ]));
        $this->assertTrue($ssaSimpanan->validateSchema([
            'month_day_year_of_posisi', 'nama_cabang', 'nama_uker', 'produk',
            'segmentasi', 'segmen_kategorisasi_bisnis', 'saldo',
        ])['ok']);

        $this->assertSame([
            'month_day_year_of_periode',
            'nama_cabang',
            'nama_uker',
            'produk',
            'produk_dashboard',
            'segmen',
            'segmen_lama',
            'segmen_2025',
            'segmen_dashboard',
            'kolektabilitas_one_obligor',
            'flag_restruk',
            'baki_debet',
            'jumlah_debitur_aktif',
            'jumlah_rekening_aktif',
        ], $ssaPinjaman->transformHeaders([
            'Month, Day, Year of Periode',
            'Nama Cabang', 'Nama Uker', 'Produk', 'Produk_Dashboard', 'Segmen',
            'Segmen Lama', 'SEGMEN_2025', 'Segmen_Dashboard',
            'Kolektabilitas One Obligor', 'Flag Restruk', 'Baki Debet',
            'Jumlah Debitur Aktif', 'Jumlah Rekening Aktif',
        ]));
        $this->assertTrue($ssaPinjaman->validateSchema([
            'month_day_year_of_periode', 'nama_cabang', 'nama_uker', 'produk',
            'produk_dashboard', 'segmen', 'segmen_lama', 'segmen_2025',
            'segmen_dashboard', 'kolektabilitas_one_obligor', 'flag_restruk',
            'baki_debet', 'jumlah_debitur_aktif', 'jumlah_rekening_aktif',
        ])['ok']);
    }

    public function test_gi405_strategy_uses_bulk_csv_fast_path_for_imports(): void
    {
        $strategy = new Gi405RecDhImportStrategy();

        $this->assertTrue($strategy->supports(null, 'gi405_recovery'));
        $this->assertSame('bulk_csv_filtered', $strategy->importMode());
    }

    public function test_hourly_dpk_strategy_maps_workbook_headers_to_import_schema(): void
    {
        $strategy = new HourlyDpkImportStrategy();

        $headers = $strategy->transformHeaders([
            'Minute of POSISI',
            'MBNAME',
            'BRNAME',
            'SEGMEN2',
            'PRODUK',
            'Saldo',
        ]);

        $this->assertTrue($strategy->supports(null, 'hourly_dpk'));
        $this->assertSame('bulk_csv_staging', $strategy->importMode());
        $this->assertSame(['posisi', 'mbname', 'brname', 'segmen2', 'produk', 'saldo'], $headers);
        $this->assertTrue($strategy->validateSchema([
            'uniqueid_namareport',
            'posisi',
            'mbname',
            'brname',
            'segmen2',
            'produk',
            'saldo',
        ])['ok']);
    }

    public function test_l1133_strategy_registers_normalized_headers_for_bulk_csv(): void
    {
        $strategy = new L1133ImportStrategy();

        $headers = $strategy->transformHeaders(['Kode_Br', 'MBDesc', 'Jenis']);

        $this->assertTrue($strategy->supports(null, 'l1133'));
        $this->assertSame('bulk_csv_staging', $strategy->importMode());
        $this->assertSame('periode', $headers[0]);
        $this->assertSame('kode_kanwil', $headers[1]);
        $this->assertSame('dpk', $headers[14]);
        $this->assertTrue($strategy->validateSchema([
            'uniqueid_namareport',
            'created_at',
            'updated_at',
            'periode',
            'kode_kanwil',
            'nama_kanwil',
            'kode_kanca',
            'nama_kanca',
            'kode_uker',
            'nama_uker',
            'jenis',
            'jumlah_debitur',
            'jumlah_rekening',
            'outstanding',
            'jumlah_debitur_npl',
            'npl',
            'jumlah_debitur_dpk',
            'dpk',
        ])['ok']);
    }

    public function test_lw321pn_strategy_uses_fast_path_and_validates_schema(): void
    {
        $strategy = new Lw321PnImportStrategy();

        $this->assertTrue($strategy->supports(null, 'lw321pn'));
        $this->assertSame('bulk_csv_filtered', $strategy->importMode());
        $this->assertTrue($strategy->validateSchema([
            'uniqueid_namareport',
            'periode',
            'kode_kanwil',
            'kanwil',
            'kode_kanca',
            'kanca',
            'kode_uker',
            'uker',
            'no_rekening',
            'nama_debitur',
            'balance_dalam_idr',
        ])['ok']);
        $this->assertFalse($strategy->validateSchema(['periode', 'no_rekening'])['ok']);
    }

}
