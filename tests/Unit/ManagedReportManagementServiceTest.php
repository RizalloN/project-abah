<?php

namespace Tests\Unit;

use App\Support\ManagedReportManagementService;
use PHPUnit\Framework\TestCase;

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
}
