<?php

namespace Tests\Unit;

use App\Services\Import\Strategies\DailyLoanImportStrategy;
use App\Services\Import\Strategies\ConfiguredExcelImportStrategy;
use App\Services\Import\Strategies\Gi405RecDhImportStrategy;
use App\Services\Import\Strategies\GenericCsvImportStrategy;
use App\Services\Import\Strategies\HourlyDpkImportStrategy;
use App\Services\Import\Strategies\L1133ImportStrategy;
use App\Services\Import\Strategies\Lw321NpdImportStrategy;
use App\Services\Import\Strategies\Lw321NpddImportStrategy;
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
        $this->assertFalse($strategy->supports(null, 'l1133'));
        $this->assertFalse($strategy->supports(null, 'lw321pn'));
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

    public function test_gi405_strategy_uses_bulk_csv_fast_path_for_imports(): void
    {
        $strategy = new Gi405RecDhImportStrategy();

        $this->assertTrue($strategy->supports(null, 'gi405_singlerow'));
        $this->assertSame('bulk_csv_filtered', $strategy->importMode());
    }

    public function test_hourly_dpk_strategy_maps_workbook_headers_to_import_schema(): void
    {
        $strategy = new HourlyDpkImportStrategy();

        $headers = $strategy->transformHeaders([
            'Month, Day, Year of POSISI',
            'MBNAME',
            'BRNAME',
            'SEGMEN',
            'PRODUK',
            'Saldo',
        ]);

        $this->assertTrue($strategy->supports(null, 'hourly_dpk'));
        $this->assertSame('bulk_csv_staging', $strategy->importMode());
        $this->assertSame(['posisi', 'mbname', 'brname', 'segmen', 'produk', 'saldo'], $headers);
        $this->assertTrue($strategy->validateSchema([
            'uniqueid_namareport',
            'posisi',
            'mbname',
            'brname',
            'segmen',
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

    public function test_lw321_npd_strategy_uses_fast_path_and_validates_schema(): void
    {
        $strategy = new Lw321NpdImportStrategy();

        $this->assertTrue($strategy->supports(null, 'lw321_npd'));
        $this->assertSame('bulk_csv_filtered', $strategy->importMode());
        $this->assertTrue($strategy->validateSchema([
            'uniqueid_namareport',
            'periode',
            'billing',
            'kanca',
            'bc',
            'uker',
            'no_rekening',
            'nama_debitur',
            'update_npd',
            'm_min_1_os',
            'now_t_total',
        ])['ok']);
        $this->assertFalse($strategy->validateSchema(['billing', 'no_rekening'])['ok']);
    }

    public function test_lw321_npd_strategy_maps_old_source_headers_to_new_schema(): void
    {
        $strategy = new Lw321NpdImportStrategy();

        $headers = [
            'posisi_30_april_2026_kol',
            'posisi_30_april_2026_detail',
            'posisi_30_april_2026_os',
            'ref_kol',
            'ref_detai',
            'ref_detail',
            'ref_os',
            't_pokok',
            't_bunga',
            't_total',
        ];

        $this->assertSame([
            'm_min_1_kol',
            'm_min_1_detail',
            'm_min_1_os',
            'now_kol',
            'now_detail',
            'now_detail',
            'now_os',
            'now_t_pokok',
            'now_t_bunga',
            'now_t_total',
        ], $strategy->transformHeaders($headers));
    }

    public function test_lw321_npd_strategy_maps_raw_workbook_position_headers(): void
    {
        $strategy = new Lw321NpdImportStrategy();

        $headers = [
            'PERIODE',
            'BILLING',
            'KANCA',
            'BC',
            'MBM',
            'UKER',
            'PN',
            'MANTRI',
            'NOMOR REKENING',
            'NAMA DEBITUR',
            'PLAFON',
            'NEXT PMT DATE',
            'UPDATE NPD',
            'TGL REALISASI',
            'TGL JATUH TEMPO',
            'JANGKA WAKTU',
            'FLAG RESTRUK',
            'POSISI 30 APRIL 2026',
            'DETAIL',
            'OS',
            '18',
            'Posisi 6 Mei 2026',
            'DETAIL',
            'OS',
            'T. POKOK',
            'T. BUNGA',
            'T. TOTAL',
            'PTP',
        ];

        $transformed = $strategy->transformHeaders($headers);

        $this->assertSame([
            'm_min_1_kol',
            'm_min_1_detail',
            'm_min_1_os',
            'wba',
        ], array_slice($transformed, 17, 4));
        $this->assertSame([
            'now_kol',
            'now_detail',
            'now_os',
            'now_t_pokok',
            'now_t_bunga',
            'now_t_total',
            'ptp',
        ], array_slice($transformed, 21, 7));

        foreach (['6 Mei 2026', '6 May 2026', '06/05/2026'] as $positionHeader) {
            $headers[17] = $positionHeader;
            $headers[21] = $positionHeader;
            $transformed = $strategy->transformHeaders($headers);

            $this->assertSame([
                'm_min_1_kol',
                'm_min_1_detail',
                'm_min_1_os',
                'wba',
            ], array_slice($transformed, 17, 4));
            $this->assertSame([
                'now_kol',
                'now_detail',
                'now_os',
                'now_t_pokok',
                'now_t_bunga',
                'now_t_total',
                'ptp',
            ], array_slice($transformed, 21, 7));
        }
    }

    public function test_lw321_npdd_strategy_uses_fast_path_and_validates_schema(): void
    {
        $strategy = new Lw321NpddImportStrategy();

        $this->assertTrue($strategy->supports(null, 'lw321_npdd'));
        $this->assertSame('bulk_csv_filtered', $strategy->importMode());
        $this->assertTrue($strategy->validateSchema([
            'uniqueid_namareport',
            'periode',
            'billing',
            'kanca',
            'bc',
            'uker',
            'no_rekening',
            'nama_debitur',
            'npdd',
            'npdd_update',
            'os',
            'now_t_total',
        ])['ok']);
        $this->assertFalse($strategy->validateSchema(['billing', 'no_rekening'])['ok']);
    }

    public function test_lw321_npdd_strategy_maps_old_source_headers_to_new_schema(): void
    {
        $strategy = new Lw321NpddImportStrategy();

        $headers = [
            'ref_kol',
            'ref_detai',
            'ref_os',
            't_pokok',
            't_bunga',
            't_total',
        ];

        $this->assertSame([
            'now_kol',
            'now_detail',
            'now_os',
            'now_t_pokok',
            'now_t_bunga',
            'now_t_total',
        ], $strategy->transformHeaders($headers));
    }

    public function test_lw321_npdd_strategy_maps_broken_workbook_reference_headers(): void
    {
        $strategy = new Lw321NpddImportStrategy();

        $headers = [
            'PERIODE',
            'BILLING',
            'KANCA',
            'BC',
            'MBM',
            'UKER',
            'PN',
            'MANTRI',
            'NOMOR REKENING',
            'NAMA DEBITUR',
            'PLAFON',
            'NPDD',
            'NPDD UPDATE',
            'TGL REALISASI',
            'TGL JATUH TEMPO',
            'JANGKA WAKTU',
            'FLAG RESTRUK',
            'KOL',
            'DETAIL',
            'OS',
            'WBA',
            '#REF!',
            'COL_22',
            'COL_23',
            'COL_24',
            'COL_25',
            'COL_26',
            'COL_27',
        ];

        $this->assertSame([
            'now_kol',
            'now_detail',
            'now_os',
            'now_t_pokok',
            'now_t_bunga',
            'now_t_total',
            'ptp',
        ], array_slice($strategy->transformHeaders($headers), 21));

        $headers[21] = 'Posisi 6 Mei 2026';

        $this->assertSame([
            'now_kol',
            'now_detail',
            'now_os',
            'now_t_pokok',
            'now_t_bunga',
            'now_t_total',
            'ptp',
        ], array_slice($strategy->transformHeaders($headers), 21));

        foreach (['6 Mei 2026', '6 May 2026', '06/05/2026'] as $positionHeader) {
            $headers[21] = $positionHeader;

            $this->assertSame([
                'now_kol',
                'now_detail',
                'now_os',
                'now_t_pokok',
                'now_t_bunga',
                'now_t_total',
                'ptp',
            ], array_slice($strategy->transformHeaders($headers), 21));
        }
    }
}
