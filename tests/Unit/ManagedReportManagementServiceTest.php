<?php

namespace Tests\Unit;

use App\Support\ManagedReportManagementService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ManagedReportManagementServiceTest extends TestCase
{
    public function test_gi405_report_management_scope_uses_kc_konsol(): void
    {
        $service = new ManagedReportManagementService();

        [$periodColumn, $kancaColumn] = $service->resolveManagementScopeColumns('gi405_rec_dh', [
            'uniqueid_namareport',
            'kode',
            'pendapatan_koreksi_ppap_dr_angsuran_ph',
            'recovery_non_klaim',
            'kc_konsol',
            'nama_uker',
            'segmen',
            'tanggal',
        ]);

        $this->assertSame('tanggal', $periodColumn);
        $this->assertSame('kc_konsol', $kancaColumn);
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

    public function test_cognos_ph_management_label_uses_unit_kerja_when_kanca_is_numeric_code(): void
    {
        $service = new ManagedReportManagementService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('resolveManagementKancaLabel');
        $method->setAccessible(true);

        $label = $method->invoke($service, 'cognos_ph', '6499', '00070 -- KC Ponorogo (Konsolidasi-MB)');

        $this->assertSame('00070 -- KC Ponorogo (Konsolidasi-MB)', $label);
    }
}
