<?php

namespace Tests\Unit;

use App\Support\ManagedReportManagementService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ManagedReportManagementServiceTest extends TestCase
{
    public function test_gi405_report_management_scope_uses_branch(): void
    {
        $service = new ManagedReportManagementService();

        [$periodColumn, $kancaColumn] = $service->resolveManagementScopeColumns('gi405_singlerow', [
            'uniqueid_namareport',
            'periode',
            'branch',
            'posting_control',
            'account_number',
        ]);

        $this->assertSame('periode', $periodColumn);
        $this->assertSame('branch', $kancaColumn);
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

    public function test_ssa_period_label_keeps_full_date_when_bucket_has_single_exact_date(): void
    {
        $service = new ManagedReportManagementService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('resolveAggregatedPeriodLabel');
        $method->setAccessible(true);

        $this->assertSame('2026-04-30', $method->invoke($service, 'ssa_pinjaman', '2026-04-30', '2026-04', 'month_day_year_of_periode'));
    }
}
