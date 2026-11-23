<?php

namespace Tests\Unit;

use App\Support\ManagedReportManagementService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ManagedReportManagementServiceTest extends TestCase
{
    public function test_gi405_report_management_scope_does_not_use_kanca(): void
    {
        $service = new ManagedReportManagementService();

        [$periodColumn, $kancaColumn] = $service->resolveManagementScopeColumns('gi405_recovery', [
            'uniqueid_namareport',
            'periode',
            'kode_uker',
            'nama_uker',
        ]);

        $this->assertSame('periode', $periodColumn);
        $this->assertNull($kancaColumn);
    }

    public function test_cognos_ph_report_management_scope_uses_kanca(): void
    {
        $service = new ManagedReportManagementService();

        [$periodColumn, $kancaColumn] = $service->resolveManagementScopeColumns('cognos_ph', [
            'uniqueid_namareport',
            'periode',
            'kanwil',
            'region',
            'kanca',
            'unit_kerja',
            'acctno',
            'saldo_ph',
        ]);

        $this->assertSame('periode', $periodColumn);
        $this->assertSame('kanca', $kancaColumn);
    }

    public function test_dly_kap_report_management_scope_uses_branch_with_unit_extra_scope(): void
    {
        $service = new ManagedReportManagementService();
        $columns = [
            'uniqueid_dly_kap',
            'periode',
            'kanwil',
            'kode_cabang',
            'kode_unit',
            'segmen_kategori',
        ];

        [$periodColumn, $kancaColumn] = $service->resolveManagementScopeColumns('dly_kap_resegmentasi', $columns);
        $extraColumns = $service->resolveManagementExtraScopeColumns('dly_kap_resegmentasi', $columns);

        $this->assertSame('periode', $periodColumn);
        $this->assertSame('kode_cabang', $kancaColumn);
        $this->assertSame(['kode_unit'], $extraColumns);
    }

    public function test_cognos_ph_management_label_uses_unit_kerja_when_kanca_is_numeric_code(): void
    {
        $service = new ManagedReportManagementService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('resolveManagementKancaLabel');
        $method->setAccessible(true);

        $label = $method->invoke($service, 'cognos_ph', '6499', '00070 -- KC Ponorogo (Konsolidasi-MB)');

        $this->assertSame('00070 -- KC Ponorogo (Konsolidasi-MB)', $label);
    }

    public function test_ssa_pinjaman_management_period_filter_keeps_full_date(): void
    {
        $service = new ManagedReportManagementService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('normalizeManagementPeriodFilter');
        $method->setAccessible(true);

        $filter = $method->invoke($service, 'ssa_pinjaman', '2026-03-31', 'month_day_year_of_periode');

        $this->assertSame('2026-03-31', $filter);
    }

    public function test_ssa_almafacts_management_scope_uses_period_and_kanca_konsolidasi(): void
    {
        $service = new ManagedReportManagementService();

        [$periodColumn, $kancaColumn] = $service->resolveManagementScopeColumns('ssa_almafacts', [
            'uniqueid_namareport',
            'month_day_year_of_posisi',
            'kanca_konsolidasi',
            'kode_unit_kerja',
            'nominal',
        ]);

        $this->assertSame('month_day_year_of_posisi', $periodColumn);
        $this->assertSame('kanca_konsolidasi', $kancaColumn);
    }

    public function test_ssa_period_label_keeps_full_date_when_bucket_has_single_exact_date(): void
    {
        $service = new ManagedReportManagementService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('resolveAggregatedPeriodLabel');
        $method->setAccessible(true);

        $this->assertSame('2026-04-30', $method->invoke($service, 'ssa_pinjaman', '2026-04-30', '2026-04', 'month_day_year_of_periode'));
    }

    public function test_cognos_recovery_report_management_scope_uses_cabang_with_unit_kerja_fallback(): void
    {
        $service = new ManagedReportManagementService();

        [$periodColumn, $kancaColumn] = $service->resolveManagementScopeColumns('cognos_recovery', [
            'uniqueid_namareport',
            'periode',
            'cifno',
            'cabang',
            'unit_kerja',
            'total_recovery',
        ]);

        $this->assertSame('periode', $periodColumn);
        $this->assertSame('cabang', $kancaColumn);
    }

    public function test_cognos_ph_normalize_kanca_filter_uses_unit_kerja_when_kanca_is_numeric_code(): void
    {
        $service = new ManagedReportManagementService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('normalizeManagementKancaFilter');
        $method->setAccessible(true);

        $filter = $method->invoke($service, 'cognos_ph', '6499', '00070 -- KC Ponorogo (Konsolidasi-MB)');

        $this->assertSame('00070 -- KC Ponorogo (Konsolidasi-MB)', $filter);
    }

    public function test_cognos_recovery_management_label_and_filter_normalizes_null_to_blank(): void
    {
        $service = new ManagedReportManagementService();
        $reflection = new ReflectionClass($service);
        $labelMethod = $reflection->getMethod('resolveManagementKancaLabel');
        $labelMethod->setAccessible(true);
        $filterMethod = $reflection->getMethod('normalizeManagementKancaFilter');
        $filterMethod->setAccessible(true);

        $this->assertSame('(Blank)', $labelMethod->invoke($service, 'cognos_recovery', 'NULL', 'NULL'));
        $this->assertSame('', $filterMethod->invoke($service, 'cognos_recovery', 'NULL', 'NULL'));
    }

    public function test_cognos_ph_groups_and_aggregates_multiple_numeric_units_into_single_branch_segment(): void
    {
        $service = new ManagedReportManagementService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('normalizeManagementGroupRows');
        $method->setAccessible(true);

        $items = [
            (object) [
                'period_value' => '2026-02-28',
                'kanca_value' => '6109',
                'kanca_label_fallback_value' => '00007 -- KC Banyuwangi (Konsolidasi-MB)',
                'row_count' => 6,
            ],
            (object) [
                'period_value' => '2026-02-28',
                'kanca_value' => '6118',
                'kanca_label_fallback_value' => '00007 -- KC Banyuwangi (Konsolidasi-MB)',
                'row_count' => 3,
            ],
            (object) [
                'period_value' => '2026-02-28',
                'kanca_value' => '6122',
                'kanca_label_fallback_value' => '00007 -- KC Banyuwangi (Konsolidasi-MB)',
                'row_count' => 2,
            ],
            (object) [
                'period_value' => '2026-02-28',
                'kanca_value' => '00009 -- KC Blitar (Konsolidasi-MB)',
                'kanca_label_fallback_value' => '00009 -- KC Blitar',
                'row_count' => 7,
            ],
        ];

        $rows = $method->invoke(
            $service,
            'cognos_ph',
            $items,
            'periode',
            'kanca',
            'unit_kerja',
            []
        );

        $this->assertCount(2, $rows);

        $banyuwangi = collect($rows)->firstWhere('kanca', '00007 -- KC Banyuwangi (Konsolidasi-MB)');
        $this->assertNotNull($banyuwangi);
        $this->assertSame('00007 -- KC Banyuwangi (Konsolidasi-MB)', $banyuwangi['kanca_label']);
        $this->assertSame(11, $banyuwangi['row_count']);

        $blitar = collect($rows)->firstWhere('kanca', '00009 -- KC Blitar (Konsolidasi-MB)');
        $this->assertNotNull($blitar);
        $this->assertSame('00009 -- KC Blitar (Konsolidasi-MB)', $blitar['kanca_label']);
        $this->assertSame(7, $blitar['row_count']);
    }
}
